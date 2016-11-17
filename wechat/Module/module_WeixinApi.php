<?php 
require_once(dirname(__DIR__).'/Core/Weixin.class.php');
class WeixinApi
{
	private $config;
	private $wid;

	function __construct($wid)
	{
		$this->wid = intval($wid);
		$this->config = array();	// 微信配置
		$this->Weixin = new Weixin($this->config);
	}

	/**
	 * 生成菜单
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T18:36:03+0800
	 * --------------------------------------------------
	 * @return   json                   
	 */
	public function createMenu()
	{
		$menu_data = 'json';
		$return = $this->Weixin->createWeixinMenu($menu_data, 'array');
		$status = $return['errcode'];
		$msg    = $return['errmsg'];
		return $this->responseJson($status, $msg, $return);
	}

	/**
	 * 获取素材
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T19:13:10+0800
	 * --------------------------------------------------
	 * @param    string                   $type   image、video、vvoice、news
	 * @param    integer                  $offset [description]
	 * @param    integer                  $count  [description]
	 * @return   json
	 */
	public function batchMaterial($type, $offset=0, $count=20)
	{
		$return = $this->Weixin->getBatchMaterial($type, $offset, $count, 'array');
		$status = 1;
		$msg = 'success';
		return $this->responseJson($status, $msg, $return);
	}

	/**
	 * 获取各类素材数量
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T09:24:32+0800
	 * --------------------------------------------------
	 * @return   json                   
	 */
	public function materialCount()
	{
		$return = $this->Weixin->getMaterialCount('array');
		$status = 1;
		$msg = 'success';
		return $this->responseJson($status, $msg, $return);
	}

	/**
	 * 获取用户
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T09:27:27+0800
	 * --------------------------------------------------
	 * @param    string                   $next_openid 开始OpenID
	 * @return   array                                
	 */
	public function getUser($next_openid='')
	{
		$return = $this->Weixin->getUser($next_openid, 'array');
		return $return;
	}

	/**
	 * 批量获取用户信息
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T09:33:48+0800
	 * --------------------------------------------------
	 * @param    array                   $openidList openid数组
	 * @return   json                               
	 */
	public function batchGetUserInfo($openidList)
	{
		foreach ($openidList as $openid) {
			$postdata['user_list'][] = array('openid' => $openid, 'lang' => 'zh-CN');
		}
		$data = $this->Weixin->batchGetUserInfo($postdata);
		return $data;
	}

	/**
	 * 生成二维码
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T09:53:49+0800
	 * --------------------------------------------------
	 * @param    string                   $type      二维码类型
	 * @param    int                   	  $scene_id  二维码场景值
	 * @param    string                   $scene_str 永久二维码
	 * @param    integer                  $expire    过期时间
	 * @return   json	                              
	 */
	public function getQrcode($type, $scene_id, $scene_str='', $expire=1800)
	{
		if (in_array($type, array('QR_SCENE', 'QR_LIMIT_SCENE', 'QR_LIMIT_STR_SCENE'))) {
			$return = $this->Weixin->qrcode(strtoupper($type), $scene_id, $scene_str, $expire);
			$data = json_decode($return, true);
			$data['qrurl'] = 'http://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$data['ticket'];
			$status = 1;
			$msg    = 'success';
		} else {
			$data = array();
			$status = 0;
			$msg = 'Type error';
		}
		return $this->responseJson($status, $msg, $data);
	}

	/**
	 * 获取用户Openid
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T09:56:55+0800
	 * --------------------------------------------------
	 * @return   string                   
	 */
	public function getOpenid()
	{
		return $this->Weixin->getFromUserName();
	}

	/**
	 * 获取微信Openid
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T09:57:11+0800
	 * --------------------------------------------------
	 * @return   string                   
	 */
	public function getWechatName()
	{
		return $this->Weixin->getToUserName();
	}

	/**
	 * 获取用户信息
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T09:58:51+0800
	 * --------------------------------------------------
	 * @param    string                   $access_token AccessToken
	 * @param    string                   $openid       用户OpenID
	 * @return   json                                 
	 */
	public function getUserInfo($access_token, $openid)
	{
		return $this->Weixin->getUserInfo($access_token, $openid);
	}

	/**
	 * 生成JsApiTicket加密包
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:15:56+0800
	 * --------------------------------------------------
	 * @return   array                   
	 */
	public function getSignPackage()
	{
		return $this->Weixin->getSignPackage();
	}

	/**
	 * 发送客服信息
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:17:09+0800
	 * --------------------------------------------------
	 * @param    string                   $openid 微信OpenID
	 * @param    string                   $args   发送内容
	 * @return   json                           
	 */
	public function sendCustomMessage($openid, $args)
	{
		return $this->Weixin->sendCustomMessage($openid, $args);
	}

	/**
	 * 用户网页登录授权
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:19:23+0800
	 * --------------------------------------------------
	 * @param    string                   $type         snsapi_base：静默授权 snsapi_userinfo：获取基本信息，需点击同意
	 * @param    string                   $redirect_uri 跳转地址
	 * @param    string                   $state        状态码
	 * @return   uri                                 
	 */
	public function getSnsapiCode($type, $redirect_uri, $state)
	{
		return $this->Weixin->getSnsapiCode($type, $redirect_uri, $state);
	}

	/**
	 * 获取授权后的微信用户OPenid
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:21:58+0800
	 * --------------------------------------------------
	 * @param    string                   $code 授权获得的code
	 * @return   array                    array('access_token', 'openid')
	 */
	public function getWebAccessToken($code)
	{
		return $this->Weixin->getWebAccessToken($code);
	}

	/**
	 * 返回json值处理
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:24:13+0800
	 * --------------------------------------------------
	 * @param    int                   	  $status 状态码
	 * @param    string                   $msg    描述信息	
	 * @param    array                    $data   扩展数据
	 * @return   json
	 */
	private function responseJson($status, $msg, $data=array())
	{
		$returnArr['status'] = intval($status);
		$returnArr['msg']    = trim($msg);
		if (!empty($data)) {
			$returnArr['data'] = $data;
		}
		return json_encode($returnArr);
	}

	/**
	 * 获取客服列表
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:26:16+0800
	 * --------------------------------------------------
	 * @return   array
	 */
	public function getKfList()
	{
		return $this->Weixin->getKfList();
	}

	/**
	 * 获取客服聊天记录接口
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:28:37+0800
	 * --------------------------------------------------
	 * @param    string                   $date      年-月-日
	 * @param    integer                  $pageIndex 查询第几页
	 * @param    integer                  $pageSize  每页大小 <=50
	 * @return   array                              
	 */
	public function getRecord($date = '', $pageIndex = 1, $pageSize=10)
	{
		return $this->Weixin->getRecord($date, $pageIndex, $pageSize);
	}

	/**
	 * 获取未接入会话列表
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:30:58+0800
	 * --------------------------------------------------
	 * @return   array                   
	 */
	public function getWaitCase()
	{
		return $this->Weixin->getWaitCase();
	}

	/**
	 * 获取在线客服列表
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:32:03+0800
	 * --------------------------------------------------
	 * @return   array                   
	 */
	public function getOnlineKfList()
	{
		return $this->Weixin->getOnlineKfList();
	}

	/**
	 * 获取微信服务器IP地址
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:32:57+0800
	 * --------------------------------------------------
	 * @return   json                   
	 */
	public function getWeixinIp()
	{
		return $this->Weixin->getWeixinIp();
	}

	/**
	 * 发送微信信息
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:36:53+0800
	 * --------------------------------------------------
	 * @param    int                   	  $type 类型
	 * @param    string                   $text 内容
	 * @return   boolean                         
	 */
	public function responseMessage($type, $text)
	{
		switch ($type) {
			case '0':
				$this->Weixin->responseTextMessage($text); exit;
				break;
			case '1':
				$this->Weixin->responseNewsMessage(json_decode($text, true)); exit;
				break;
			case '2':
				$this->Weixin->responseImageMessage($text); exit;
				break;
			case '3':
				$this->Weixin->responseVoiceMessage($text); exit;
				break;
			case '4':
				$this->Weixin->responseVideoMessage($text); exit;
				break;
			default:
				break;
		}
	}

	/**
	 * 发送模板信息
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:38:33+0800
	 * --------------------------------------------------
	 * @param    string                   $openid      OpenID
	 * @param    int                   	  $template_id 模板ID
	 * @param    string                   $link        链接
	 * @param    array                    $dataArr     数据
	 * @return   json                                
	 */
	public function sendTemplate($openid, $template_id, $link, $dataArr)
	{
		return $this->Weixin->sendTemplate($openid. $template_id, $link, $dataArr);
	}

	/**
	 * 获取客服聊天记录
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:41:59+0800
	 * --------------------------------------------------
	 * @param    int                   $starttime 开始时间
	 * @param    int                   $endtime   结束时间
	 * @param    int                   $msgid     消息ID
	 * @param    integer               $number    数量
	 * @return   array
	 */
	public function getMsglist($starttime, $endtime, $msgid, $number=10000)
	{
		return $this->Weixin->getMsglist($starttime, $endtime, $msgid, $number);
	}

	/**
	 * 检测客服是否在线
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:44:47+0800
	 * --------------------------------------------------
	 * @param    string                   $account 客服账号
	 * @return   boolean                            
	 */
	public function checkAccountOnline($account)
	{
		$json_onlineKf = $this->Weixin->getonlinekflist(1);
		$res = stripos($json_onlineKf, $account);
		if ($res != FALSE && $res >= 0) {
			return true;
		}
		return false;
	}

	/**
	 * 关闭多客服会话
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-20T10:46:05+0800
	 * --------------------------------------------------
	 * @param    string                   $account 账号
	 * @param    string                   $openid  Openid
	 * @return   json                            
	 */
	public function kfsessioClose($account, $openid)
	{
		return $this->Weixin->kfsessioClose($account, $openid);
	}
}