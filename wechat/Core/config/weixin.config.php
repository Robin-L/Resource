<?php 
/**
 * ----------------------------------------------------------------------
 * 商户号和微信红包发送控制
 * 游戏送不停
 * ----------------------------------------------------------------------
 * 
 * 下列参数不能修改参数名称
 * 
 */

$pay_config = array(
	'pay_appid' 		=> "wx33d3139439011488",	//公众账号appid,首先申请与之配套的公众账号
	'pay_mchid' 		=> "1366567402",			//商户号id
	'pay_partnerkey'	=> "PmvjLFGEK2AJc98RFI8cBliNAYMCSeKJ", 	//商户平台支付密钥  微信商户平台-> 账户设置-> API安全-> 密钥设置
	'pay_pem_dir'		=> "game2play/",				//密钥文件地址
	
	//现金红包发送成功后的推送地址
	'pay_notice_url'	=> 'http://www.game2.cn/activity/code/welcomeback2016/op/updateLingQuHongBaoState/',

	// 微信端现金红包显示内容【可覆盖】
	'pay_send_name'		=> '哥们网',
	'pay_wishing'		=> '专题活动，现金红包',
	'pay_act_name'		=> '暑期专题活动',
	'pay_remark'		=> '专题活动，现金红包'
);

// $pay_config = array(
// 	'pay_appid' 		=> "wx25c4f62656a9c8bd",	//公众账号appid,首先申请与之配套的公众账号
// 	'pay_mchid' 		=> "1308312301",			//商户号id
// 	'pay_partnerkey'	=> "2P1ToKp3vHwO5IPKYsAJLWxrRT6EakXA", 	//商户平台支付密钥  微信商户平台-> 账户设置-> API安全-> 密钥设置
// 	'pay_pem_dir'		=> "weixin/",				//密钥文件地址
// 	'pay_notice_url'	=> 'http://183.60.41.177:2000/exchangePlat/op/updateState/gkey/jiujian',//现金红包发送成功后的推送地址

// 	// 微信端现金红包显示内容【可覆盖】
// 	'pay_send_name'		=> '九阴绝学',
// 	'pay_wishing'		=> '装备靠打，宝石靠抢',
// 	'pay_act_name'		=> '黄金战场抢宝石活动',
// 	'pay_remark'		=> '宝石换RMB，等你抢抢抢！'
// );