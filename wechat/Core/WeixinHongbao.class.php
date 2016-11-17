<?php 
/**
* 微信红包辅助类
*/
class WeixinHongbao
{
	var $parameters;
	private $partnerkey = '';
	private $pem_dir = '';

	function __construct($partnerkey, $pem_dir = '', $code = '')
	{
		$this->partnerkey = $partnerkey;
		$this->pem_dir = $pem_dir;
		$this->code = $code;
	}

	/**
	 * 设置参数值
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:41:56+0800
	 * --------------------------------------------------
	 * @param    string                   $parameter      参数名称
	 * @param    string                   $parameterValue 参数值
	 */
	public function setParameter($parameter, $parameterValue)
	{
		$this->parameters[WeixinTools::doTrimString($parameter)] = WeixinTools::doTrimString($parameterValue);
	}

	/**
	 * 取得参数值
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:42:20+0800
	 * --------------------------------------------------
	 * @param    string                   $parameter 参数名称
	 * @return   string                              
	 */
	public function getParameter($parameter)
	{
		return $this->parameters[$parameter];
	}

	/**
	 * 检测加密参数
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:45:14+0800
	 * --------------------------------------------------
	 * @return   boolean                   
	 */
	public function check_sign_paramter()
	{
		$array = array('mch_billno', 'mch_id', 'wxappid', 'send_name', 're_openid', 'total_amount', 'total_num', 'wishing', 'client_ip', 'act_name', 'remark', 'nonce_str');
		foreach ($array as $val) {
			if ($this->parameters[$val] == null) {
				return false;
			}
		}
		return true;
	}

	/**
	 * 获取加密串
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:50:44+0800
	 * --------------------------------------------------
	 * @return   string                   
	 */
	protected function get_sign()
	{
		try {
			if (null == $this->partnerkey || "" == $this->partnerkey) {
				throw new Exception("密钥不能为空");
			}
			if ($this->check_sign_paramter() == false) {
				throw new Exception("生成签名参数缺失");
			}
			ksort($this->parameters);
			$unSignParaString = WeixinTools::DoFormatQueryParamMap($this->parameters, false);
			return WeixinTools::doSign($unSignParaString, WeixinTools::doTrimString($this->partnerkey));
		} catch (Exception $e) {
			die($e->getMessage());
		}
	}

	/**
	 * 获取红包信息签名
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:54:17+0800
	 * --------------------------------------------------
	 * @return   string                   
	 */
	protected function get_info_sign()
	{
		try {
			if (null == $this->partnerkey || "" == $this->partnerkey) {
				throw new Exception("密钥不能为空！");
			}
			ksort($this->parameters);
			$unSignParaString = WeixinTools::doFormatQueryParamMap($this->parameters, false);
			return WeixinTools::doSign($unSignParaString, WeixinTools::doTrimString($this->partnerkey));
		} catch (Exception $e) {
			die($e->getMessage());
		}
	}

	/**
	 * 生成发送红包的Xml数据
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:56:57+0800
	 * --------------------------------------------------
	 * @param    integer                  $retcode   返回码
	 * @param    string                   $reterrmsg 返回信息
	 * @return   string                              
	 */
	public function create_hongbao_xml($retcode = 0, $reterrmsg = 'ok')
	{
		try {
			$this->parameters('sign', $this->get_sign());
			return WeixinTools::doArrayToXml($this->parameters);
		} catch (Exception $e) {
			die($e->getMessage());
		}
	}

	/**
	 * 生成红包获取信息的XML
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:59:46+0800
	 * --------------------------------------------------
	 * @return   string                   
	 */
	public function create_HbInfo_xml()
	{
		try {
			$this->setParameter('sign', $this->get_info_sign());
			return WeixinTools::doArrayToXml($this->parameters);
		} catch (Exception $e) {
			die($e->getMessage());
		}
	}

	/**
	 * 使用CURL来POST数据
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T18:12:38+0800
	 * --------------------------------------------------
	 * @param    string                   $url     post地址
	 * @param    string                   $vars    参数
	 * @param    integer                  $second  超时时间
	 * @param    array                    $aHeader 请求头
	 * @return   mix	                            
	 */
	public function curl_post_ssl($url, $vars, $second=30, $aHeader=array())
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		// 引入pem文件
		curl_setopt($ch, CURLOPT_SSLCERT, dirname(__FILE__) . '/pem/'. $this->pem_dir . 'apiclient_cert.pem');
		curl_setopt($ch, CURLOPT_SSLKEY,  dirname(__FILE__) . '/pem/'. $this->pem_dir . 'apiclient_key.pem');
		curl_setopt($ch, CURLOPT_CAINFO,  dirname(__FILE__) . '/pem/'. $this->pem_dir . 'rootca.pem');

		if (count($aHeader) >= 1) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
		}

		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
		$data = curl_exec($ch);
		if ($data) {
			curl_close($ch);
			return $data;
		} else {
			$error = curl_errno($ch);
			curl_close($ch);
			return false;
		}
	}
}