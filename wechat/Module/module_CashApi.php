<?php
require_once('class/mlog.php');
require_once(dirname(__DIR__ ) . '/weixin/include/RedPacket/WxHongbao.class.php');

class CashApi
{
	private $wid;
	private $type;
	private $gaid;
	private $code;
	private $appid;
	private $app_mchid;
	private $partnerkey;
	private $pem_dir;
	private $notice_url;
	private $send_name;
	private $wishing;
	private $act_name;
	private $remark;
	private $client_ip;
	private $doSendCashUrl;
	private $doHbinfoUrl;
	private $nowTime;

	// 拆分红包相关
	private $switch;			// 是否拆分
	private $max_total_amount; 	// 最大金额[单位：分]
	private $break_amount;		// 拆分金额[单位：分]
	private $amount_arr;		// 实际发送金额数组[单位：分]
	private $billno_foot;		// 商户订单初始值 3位 不以0开头 INT型数字


	public function __construct($wid, $type, $gaid)
	{
		global $pay_config;
		$this->wid = $wid;
		$this->type = $type;
		$this->gaid = $gaid;
		$this->client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		
		$this->WechatConfig = new WechatConfig();
		$this->WechatBasic = new WechatBasic($this->type);
		$this->WechatSetting = new WechatSetting();
		$this->WechatLimit = new WechatLimit();
		$this->WechatGameCashGroup = new WechatGameCashGroup();

		$this->doSendCashUrl = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";
		$this->doHbinfoUrl   = "https://api.mch.weixin.qq.com/mmpaymkttransfers/gethbinfo";

		$this->phpcmd = '/usr/local/webserver/php/bin/php ';
		$this->batch = dirname(__DIR__) . '/batch/wechat_cash_group.php';
		$this->code = $this->WechatConfig->getIdCode($this->wid);
		$this->billno_foot 		= 100;						// 3位订单尾数
		
		// 获取红包配置
		$cash_params = $this->WechatSetting->getSettingData($this->wid, $this->type, $this->gaid);
		$this->switch = isset($cash_params['switch']) ? $cash_params['switch'] : 0;	// 是否拆包状态

		$this->max_total_amount = 5000;						// 最大金额，超过的部分不发
		$this->break_amount 	= 500;						// 拆分金额
		$this->amount_arr 		= array('500' => '488');	// 实际发送金额5元发4.88元
		

		$this->nowTime = time();
		
		// 读取配置文件
		$config_path = dirname(__DIR__) . '/weixin/include/RedPacket/config/' . $this->code . '.config.php';

		if (file_exists($config_path)) {
			include($config_path);
			$this->appid 		= $pay_config['pay_appid'];
			$this->app_mchid	= $pay_config['pay_mchid'];
			$this->partnerkey   = $pay_config['pay_partnerkey'];
			$this->pem_dir 		= $pay_config['pay_pem_dir'];
			$this->notice_url   = isset($cash_params['notice_url']) ? $cash_params['notice_url'] : $pay_config['pay_notice_url'];
			$this->send_name    = isset($cash_params['send_name']) ? $cash_params['send_name'] : $pay_config['pay_send_name'];
			$this->wishing 		= isset($cash_params['wishing']) ? $cash_params['wishing'] : $pay_config['pay_wishing'];
			$this->act_name		= isset($cash_params['act_name']) ? $cash_params['act_name'] : $pay_config['pay_act_name'];
			$this->remark 		= isset($cash_params['remark']) ? $cash_params['remark'] : $pay_config['pay_remark'];
		} else {
			return array('status' => 0, 'msg' => '配置文件不存在'); exit;
		}
	}

	/**
	 * 发送红包处理
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-09-07T09:09:04+0800
	 * --------------------------------------------------
	 * @param    string                   $re_openid    用户OpenID
	 * @param    string                   $mch_billno   商户订单号
	 * @param    int                   	  $total_amount 发送金额（分）
	 * @return   array
	 */
	public function doSendWxHongbao($re_openid, $mch_billno, $total_amount)
	{
		$data = array();
		$WxHongbao = new WxHongbao($this->partnerkey, $this->pem_dir, $this->code);
		$WxHongbao->setParameter("nonce_str", $this->create_rand()); 	// 随机字符串，不长于32位
        $WxHongbao->setParameter("mch_billno", $mch_billno);        	// 订单号
        $WxHongbao->setParameter("mch_id", $this->app_mchid);   		// 商户号
        $WxHongbao->setParameter("wxappid", $this->appid);     			// 公众号ID
        $WxHongbao->setParameter("send_name", $this->send_name);      	// 红包发送者名称
        $WxHongbao->setParameter("re_openid", $re_openid);      		// 接受红包的用户用户在wxappid下的openid
        $WxHongbao->setParameter("total_amount", $total_amount);		// 付款金额，单位分
        $WxHongbao->setParameter("total_num", 1);               		// 红包发放总人数 total_num = 1
        $WxHongbao->setParameter("wishing", $this->wishing);          	// 红包祝福语，不超过128个字符
        $WxHongbao->setParameter("client_ip", $this->client_ip);      	// 调用接口的机器IP地址
        $WxHongbao->setParameter("act_name", $this->act_name);        	// 活动名称
        $WxHongbao->setParameter("remark", $this->remark);            	// 备注信息

        $postXml = $WxHongbao->create_hongbao_xml();
        $responseXml = $WxHongbao->curl_post_ssl($this->doSendCashUrl, $postXml);
        $responseObj = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        // 对象转数组
        $data = (json_decode(json_encode($responseObj),true));

        log_result("CashApi:doSendWxHongbao|Wid:{$this->wid},Wxcode:{$this->code},Pem_dir:{$this->pem_dir}|[Input]-Openid:{$re_openid},Total_amount:{$total_amount},Mch_billno:{$mch_billno}|Xml:{$postXml}|[Output]-Data:".json_encode($data)."|Ip:{$this->client_ip}", 'cashapi_'.date('Ymd').'.log');

        return $data;
	}

	/**
	 * 获取商户订单号头部[后3位自己拼接]
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-09-07T11:43:48+0800
	 * --------------------------------------------------
	 * @param    int                   $cashid 订单ID
	 * @return   string                           
	 */
	private function doCreateBillno($cashid)
	{
		$mch_billno_head = "";
		// 商户ID[10位] + 游戏ID[4位] + 红包类型[1位] + 订单ID[10位] + 100[3位初始值需要后面添加]  长度不够左补零
		$billno_gid = str_pad($this->gaid, 4, "0", STR_PAD_LEFT);
		$billno_cashid = str_pad($cashid, 10, "0", STR_PAD_LEFT);
		$mch_billno_head = $this->app_mchid . $billno_gid . $this->type . $billno_cashid;
		return $mch_billno_head;
	}

	/**
	 * 分拆订单号处理
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-09-07T11:44:31+0800
	 * --------------------------------------------------
	 * @param    array                   $data      金额数组
	 * @param    int                   	 $cashid    订单ID
	 * @param    string                  $re_openid 微信Openid
	 * @return   array                              
	 */
	private function doInsertBreakCash($data, $cashid, $re_openid)
	{
		$cash_info = $returnArr = array();
		$insert_sql = "";
		if (!empty($data) && $cashid) {
			$insert_sql = "INSERT INTO wechat_game_cash_group (`wid`, `gid`, `cash_id`, `cash_new_id`, `cash_old_amount`, `cash_new_amount`, `cash_re_openid`, `add_date`) VALUES ";
			$mch_billno_head = $this->doCreateBillno($cashid);
			if ($mch_billno_head != '') {
				$i = 0;
				foreach ($data as $dval) {
					if ($dval > 0) {
						$cash_new_id = $mch_billno_head . ($this->billno_foot + $i);
						$cash_new_amount = isset($this->amount_arr[$dval]) ? $this->amount_arr[$dval] : $dval;
						$cash_info[] = array('billno' => $cash_new_id, 'amount' => $cash_new_amount);
						$insert_sql .= "('{$this->wid}', '{$this->gaid}', '{$cashid}', '{$cash_new_id}', '{$dval}', '{$cash_new_amount}', '{$re_openid}', '{$this->nowTime}'), ";
					}
					$i++;
				}
				
				$send_amount = isset($this->amount_arr[$data[0]]) ? $this->amount_arr[$data[0]] : $data[0];	//第一笔实际发送金额
				$send_billno = isset($cash_info[0]['billno']) ? $cash_info[0]['billno'] : '';	// 第一笔商户订单ID
				if($send_amount && $send_billno) {
					if ($this->WechatBasic->modify(array('id' => $cashid, 'state' => '1'), array('state' => '2', 'cash_new_amount' => $send_amount, 'cash_old_amount' => $data[0], 'mch_billno' => $send_billno, 'cash_info' => json_encode($cash_info), 're_openid' => $re_openid), true)) {
						
						// 拆包状态更新成功后，再插入分包数据
						$weixin_db = new OptionData(DB_WEIXIN);
						$re = $weixin_db->insertDb(rtrim($insert_sql, ', '));
						if ($re) {
							$returnArr = array('status' => 1, 'msg' => 'SUCCESS');
						} else {
							$returnArr = array('status' => 0, 'msg' => '[Error]:Insert wechat_game_cash_group Failed!');
						}
					} else {
						$returnArr = array('status' => 0, 'msg' => '[State]:WechatBasic update Failed!');
					}
				} else {
					$returnArr = array('status' => 0, 'msg' => '[Error]:First billno is NULL!');
				}
			} else {
				$returnArr = array('status' => 0, 'msg' => '[Error]:mch_billno_head is NULL!');
			}
		} else {
			$returnArr = array('status' => 0, 'msg' => '[Error]:Data empty OR cashid is NULL!');
		}
		log_result("CashApi:doInsertBreakCash|Wid{$this->wid},Wxcode:{$this->code}|[Input]:Data:".json_encode($data).",Cashid:{$cashid},Re_openid:{$re_openid}|Sql:". rtrim($insert_sql, ', ')."|Cash_info:".json_encode($cash_info).",Cash_new_amount:{$send_amount},Mch_billno:{$send_billno}|[Output]:Result:".json_encode($returnArr)."|Ip:{$this->client_ip}", 'cashapi_'.date('Ymd').'.log');

		return $returnArr;
	}

	/**
	 * 发送红包进程
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-09-07T14:27:22+0800
	 * --------------------------------------------------
	 * @param    string                   $re_openid    微信OPenid
	 * @param    int                   	  $total_amount 金额[单位：分]
	 * @param    string                   $oid          兑换码
	 * @param    int                      $cashid       订单ID
	 * @return   array
	 */
	public function doSendProcess($re_openid, $total_amount, $oid, $cashid)
	{
		$return = array();
		if (!$re_openid || !$total_amount || !$oid || !$cashid) {
			$return = array('status' => 0, 'msg' => '兑换信息有误');
		}

		if ($this->type == 3 && $this->switch == 1) {
			// 先修改订单状态
			$state_result = $this->WechatBasic->modify(array('id' => $cashid, 'state' => '0'), array('state' => '1'), true);
			if ($state_result) {
				// 拆分游戏红包 $cashid, $total_amount, $this->gaid, $re_openid
				$break_return = $this->doBreakCash($total_amount);
				if (!empty($break_return) && $break_return['status']) {
					// 获取拆分红包信息 红包个数、金额数组
					$number = isset($break_return['number']) ? $break_return['number'] : 0;
					$break_data = isset($break_return['data']) ? $break_return['data'] : array();
					
					// 先更新订单拆包状态和个数
					$number_result = $this->WechatBasic->modify(array('id' => $cashid, 'state' => '1'), array('number' => $number), true);
					if ($number_result) {
						// 订单更新成功后，进行拆包处理
						$break_result = $this->doInsertBreakCash($break_data, $cashid, $re_openid);
						if ($break_result['status'] == '0') {
							$return = array('status' => 0, 'msg' => '拆包处理失败');
						}
					} else {
						$return = array('status' => 0, 'msg' => '拆包订单更新失败');
					}
				} else {
					$return = array('status' => 0, 'msg' => '拆包失败');
				}
			}
		}

		$condition['oid'] = $oid;
		$condition['wid'] = $this->wid;
		if($this->type == 3) {
			$condition['gid'] = $this->gaid;
			$fields = array('id', 'mch_billno', 're_openid', 'result_code', 'uid', 'pid', 'cash_new_amount', 'total_amount', 'number');
		} else if ($this->type == 2) {
			$fields = array('mch_billno', 'id', 're_openid', 'uid', 'result_code', 'total_amount');
		}
		$cash_data = $this->WechatBasic->getDataRow($condition, $fields);

		log_result("CashApi:Cash_data|Wid:{$this->wid},Wxcode:{$this->code}|[Input]:Re_openid:{$re_openid}, Total_amount:{$total_amount},Oid:{$oid},Cashid:{$cashid}|[Output]:Result:".json_encode($cash_data)."|Ip:{$this->client_ip}|{$this->type}|{$this->switch}", 'cashapi_'.date('Ymd').'.log');

		if (empty($cash_data)) {
			$return = array('status' => 0, 'msg' => '兑换码不存在');
		} else {
			$uid = $cash_data['uid'];
			$pid = $cash_data['pid'];
			$number = isset($cash_data['number']) ? $cash_data['number'] : 0;

			if (isset($cash_data['result_code']) && $cash_data['result_code'] == '1') {
				$return = array('status' => 0, 'msg' => '该兑换码已使用');
			}
			// 当$return 不空时直接返回
			if (!empty($return)) {
				log_result("CashApi:doSendProcess|Wid:{$this->wid},Wxcode:{$this->code}|[Input]:Re_openid:{$re_openid}, Total_amount:{$total_amount},Oid:{$oid},Cashid:{$cashid}|[Output]:Result:".json_encode($return)."|Ip:{$this->client_ip}", 'cashapi_'.date('Ymd').'.log');
				return $return;
			} else {
				$send_total_amount = ($this->type == 3 && isset($cash_data['cash_new_amount']) && $cash_data['cash_new_amount'] > 0) ? $cash_data['cash_new_amount'] : $cash_data['total_amount'];
				if ($cash_data['mch_billno'] != '') {
					if ($send_total_amount > 0) {
						$mch_billno = $cash_data['mch_billno'];
					} else {
						// 金额为0时，退出
						log_result("CashApi:Error|Wid:{$this->wid}, Wxcode:{$this->code}|[Input]:Re_openid:{$re_openid}, Total_amount:{$total_amount},Oid:{$oid},Cashid:{$cashid}|Mch_billno:{$cash_data['mch_billno']},Sent_total_amount:{$send_total_amount}|Ip:{$this->client_ip}", 'cashapi_'.date('Ymd').'.log');
						exit;
					}
				} else {
					$mch_billno_head = $this->doCreateBillno($cashid);
					$mch_billno = $mch_billno_head . $this->billno_foot;
					$re = $this->WechatBasic->modify(array('id' => $cashid, 'mch_billno' => ''), array('mch_billno' => $mch_billno, 're_openid' => $re_openid), true);
					if (!$re) {
						return array('status' => 0, 'msg' => '订单号更新失败');
					}
				}

				// 发送红包处理
				$result_data = $this->doSendWxHongbao($re_openid, $mch_billno, $send_total_amount);

				log_result("CashApi:doSendProcess|Wid:{$this->wid},Wxcode:{$this->code}|[Input]:Re_openid:{$re_openid}, Total_amount:{$total_amount},Oid:{$oid},Cashid:{$cashid}|Result_data:".json_encode($result_data)."|Ip:{$this->client_ip}", 'cashapi_'.date('Ymd').'.log');

				if (!empty($result_data)) {
					$args = array(
						'mch_id' 	=> $result_data['mch_id'],
						'wxappid'	=> $result_data['wxappid'],
						'err_code'	=> $result_data['err_code'],
					);

					if ($result_data['return_code'] == 'SUCCESS' && $result_data['result_code'] == 'SUCCESS') {
						// 发送成功后的处理
						$args['result_code'] 	= 1;
			        	$args['send_time'] 		= $result_data['send_time'];
			        	$args['send_listid']	= $result_data['send_listid'];
			        	$res = $this->WechatBasic->modify($cashid, $args, true);

			        	$notice_args = array();
			        	if ($this->type == 3) {
			        		// 推送现金红包发送结果
				        	$notice_args = array(
			                    'pid' => $pid,
			                    'oid' => $oid,
			                    'billno' => $mch_billno,
			                    'openid' => $re_openid,
			                    'state'  => 2,
			                    'time'   => time()
			                );

				        	if($this->switch == 1) {
				                $group_args['cash_result_code'] = 1;
				                $this->WechatGameCashGroup->modify(array('cash_id' => $cashid, 'cash_new_id' => $mch_billno), $group_args, true);
				        	}

			        	} elseif ($this->type == 2) {
			        		$notice_args = array(
				        		'orderNO'	=> $oid,
				        		'userid'	=> $uid,
				        		'moneySend'	=> $result_data['total_amount'] / 100,	// 分转元
				        	);

				        	// 发送成功后 更新已使用的金额
							$money_use = ($result_data['total_amount']/100);
		           		 	$this->WechatConfig->modify($this->wid, array('money_use' => array('+=', $money_use)));
			        	}

			            if(!empty($notice_args)) $re = $this->doPushNotice($notice_args, $this->type);

			            // 执行该订单批处理
			            $batch_exec = "";
			            if ($number > 1) {
		            		$batch_exec = "{$this->phpcmd} {$this->batch} {$this->wid} {$this->gaid} 0 0 {$cashid} >> /dev/null 2>&1 &";
				        	exec($batch_exec);
			            	log_result("CashApi:Batch|Wid:{$this->wid},Code:{$this->code},Pem_dir:{$this->pem_dir}|Number:{$number}|Exec:{$batch_exec}|Ip:{$this->client_ip}", 'cashapi_'.date('Ymd').'.log');
			            } else {
			            	// 单个红包时更新总金额
			            	$this->WechatBasic->modify($cashid, array('cash_total' => array('+=', $send_total_amount), true));
			            }
			            return array('status' => 1, 'msg' => $result_data['return_msg']);
					} else {
						// $args['result_code'] = 0;
			        	$args['extend'] = $result_data['return_msg'];
			        	$res = $this->WechatBasic->modify($cashid, $args, true);

			        	if($this->switch == 1) {
				        	// $group_args['cash_result_code'] = 0;
				        	$group_args['cash_err_code'] = $result_data['err_code'];
			    			$group_args['cash_extend'] 	 = $result_data['return_msg'];

			    			$gres = $this->WechatGameCashGroup->modify(array('cash_id' => $cashid, 'cash_new_id' => $mch_billno), $group_args, true);
			    		}

		        		return array('status' => 0, 'msg' => $result_data['return_msg'], 'err_code' => $result_data['err_code'], 'return_code' => $result_data['return_code'], 'result_code' => $result_data['result_code'], 'billno' => $mch_billno, 'error_arr' => array('wid' => $this->wid, 'code' => $this->code, 'pem_dir' => $this->pem_dir));
					}
				}
			}
		}
	}

	/**
	 * 拆分金额
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-09-07T10:36:02+0800
	 * --------------------------------------------------
	 * @param    int                   $total_amount 总金额[单位：分]
	 * @return   array                                 
	 */
	private function doBreakCash($total_amount)
	{
		$returnArr = array('status' => 0, 'data' => array(), 'send' => 0, 'unsend' => 0, 'number' => 0);
		$unsend_amount = $amount = 0;
		if ($total_amount > $this->max_total_amount) {
			$amount = $this->max_total_amount;	// 发送总额
			$unsend_amount = $total_amount - $this->max_total_amount; //不发送总额
		} else {
			$amount = $total_amount;
		}
		// 分拆红包
		if ($amount >= $this->break_amount) {
			
			$count = floor($amount/$this->break_amount); // 5元的个数
			$min_amount = ($amount%$this->break_amount); // 最后小于5元的金额（分）

			if ($count > 0) {
				for ($i=0; $i < $count; $i++) { 
					$temp[] = $this->break_amount;
				}
			}
			if($min_amount > 0) $temp[] = $min_amount;

		} else {
			$temp[] = $amount;
		}
		$returnArr = array('status' => 1, 'data' => $temp, 'send' => $amount, 'unsend' => $unsend_amount, 'number' => count($temp));
		log_result("CashApi:doBreakCash|Wid:{$this->wid},Wxcode:{$this->code}|Result:".json_encode($returnArr)."|Ip:{$this->client_ip}", 'cashapi_'.date('Ymd').'.log');
		return $returnArr;
	}

	private function doWxHongbaoInfo($mch_billno)
	{
		$data = array();
		$WxHongbao = new WxHongbao($this->partnerkey, $this->pem_dir, $this->code);

		$WxHongbao->setParameter("nonce_str", $this->create_rand());    // 随机字符串，不长于32位
        $WxHongbao->setParameter("mch_billno", $mch_billno);            // 商户发放红包的商户订单号
        $WxHongbao->setParameter("mch_id", $this->app_mchid);           // 微信支付分配的商户号
        $WxHongbao->setParameter("appid", $this->appid);                // 公众账号ID
        $WxHongbao->setParameter("bill_type", 'MCHT');                  // MCHT：通过商户订单号获取红包信息

        $postXml = $WxHongbao->create_HbInfo_xml();
        $responseXml = $WxHongbao->curl_post_ssl($this->doHbinfoUrl, $postXml);
        $responseObj = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);

        // 对象转数组
        $data = (json_decode(json_encode($responseObj),true));

		log_result("CashApi:doWxHongbaoInfo|Wid:{$this->wid},Code:{$this->code}|[Input]:Mch_billno:{$mch_billno}|Xml:{$postXml}|[Output]-Data:".json_encode($data)."|Ip:{$this->client_ip}", 'cashapi_'.date('Ymd').'.log');

		return $data;
	}

	/**
	 * 查询红包领取状态
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-09-07T14:49:30+0800
	 * --------------------------------------------------
	 * @param    int                   $id         订单Id
	 * @param    string                $mch_billno 商户订单号
	 * @return   array                            
	 */
	public function doInfoProcess($id, $mch_billno)
	{
		$result_data = $this->doWxHongbaoInfo($mch_billno);
		
		log_result("CashApi:doInfoProcess|Wid:{$this->wid},Wxcode:{$this->code}|[Input]:Id:{$id},Mch_billno:{$mch_billno}|Result_data:".json_encode($result_data)."|Ip:{$this->client_ip}", 'cashapi_'.date('Ymd').'.log');

		if (!empty($result_data)) {
			if ($result_data['result_code'] == 'SUCCESS' && $result_data['return_code'] == 'SUCCESS') {
				$fields = array('mch_id', 'appid', 'detail_id', 'mch_billno', 'status', 'send_type', 'hb_type', 'total_num', 'total_amount', 'send_time', 'wishing', 'remark', 'act_name', 'reason');
	        	foreach ($fields as $field) {
	        		$args[$field] = isset($result_data[$field]) ? $result_data[$field] : '';
	        	}
	        	if ($temp = $this->WechatBasic->superModify($id, array('result_code' => 1, 'status' => $args['status'], 'extend' => json_encode($args)))) {
	        		if ($args['status'] == 'RECEIVED') {
	        			$this->WechatLimit->updateTotal($this->wid, $this->type, $this->gaid, $temp['re_openid'], ($temp['total_amount']/100));
	        		}
	        		return array('status' => 1, 'msg' => 'success', 'res_code' => $args['status']);
	        	} else {
	        		return array('status' => 0, 'msg' => 'fail', 'err_code' => $args['status']);
	        	}
			} else {
				return array('status' => 0, 'msg' => 'fail', 'err_code' => $result_data['err_code']);
			}
		} else {
			return array('status' => 0, 'msg' => 'fail');
		}
	}

	/**
	 * 生成随机字符串，不长于32位
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-07-19T16:45:09+0800
	 * --------------------------------------------------
	 * @return   string                   
	 */
	public function create_rand($length=10)
	{
		$string = '';
		$seed = '1234567890abcdefghijklmnopqrstuvwxyz';
		for ($i=0; $i < $length; $i++) { 
			$_seed = rand(0, 35);
			$string .= $seed[$_seed];
		}
		return $string;
	}

	/**
	 * 推送发送结果
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-09-07T14:50:13+0800
	 * --------------------------------------------------
	 * @param    array                   $params 推送内容数组
	 * @param    int                     $type   类型
	 * @return   boolean                           
	 */
	private function doPushNotice($params, $type)
	{
		// 活动：type = 2  游戏：type = 3
		ksort($params);
		$key = ($type == 2) ? 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX' : 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
		$params['sign'] = md5(implode('', $params).$key);
		$link = $this->notice_url . '?' . http_build_query($params);
		$re = file_get_contents($link);
		log_result("CashApi:doPushNotice|Wid:{$this->wid},Code:{$this->code}|[Input]-Params:".json_encode($params).",Type:{$type}|Link:{$link}|[Output]-Data:".json_encode($re)."|Ip:{$this->client_ip}", "cashapi_".date('Ymd').'.log');

		return $re;
	}
}