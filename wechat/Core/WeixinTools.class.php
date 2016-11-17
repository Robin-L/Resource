<?php 
/**
* 微信工具类
*/
class Tools
{
	
	function __construct()
	{
		# code...
	}

	/**
	 * 生成随机字符串
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:05:31+0800
	 * --------------------------------------------------
	 * @param    int                   $length 字符串长度
	 * @return   string                           
	 */
	public static function doCreateRand($length)
	{
		$str = '';
		$seed = '1234567890abcdefghijklmopqrstuvwxyz';
		for ($i=0; $i < $length; $i++) { 
			$j = rand(0, 35);
			$str .= $seed[$j];
		}
		return $str;
	}

	/**
	 * 获取完整的链接
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:11:29+0800
	 * --------------------------------------------------
	 * @param    string                   $url   域名地址
	 * @param    string                   $param 参数
	 * @return   string
	 */
	public static function doGetAllUrl($url, $param)
	{
		$returnUrl = null;
		if (null == $url) {
			die('Url is null');
		}
		if (stripos($url, '?') == '') {
			$returnUrl = $url . '?' . $param;
		} else {
			$returnUrl = $url . '&' . $param;
		}
		return $returnUrl;
	}

	/**
	 * 过滤值null
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:14:15+0800
	 * --------------------------------------------------
	 * @param    string                   $value 数值
	 * @return   string                          
	 */
	public static function doTrimString($value)
	{
		$returnValue = null;
		if (null != $value) {
			$returnValue = $value;
			if (strlen($returnValue) == 0) {
				$returnValue = null;
			}
		}
		return $returnValue;
	}

	/**
	 * 根据参数图获取参数串
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:21:26+0800
	 * --------------------------------------------------
	 * @param    array                   $paramMap  参数数组
	 * @param    boolean                 $urlencode 是否编码
	 */
	public static function DoFormatQueryParamMap($paramMap, $urlencode=true)
	{
		$returnQuery = $buffer = "";
		ksort($paramMap);
		foreach ($paramMap as $pkey => $pval) {
			if (null != $pval && 'null' != $pval && 'sign' != $pkey) {
				if ($urlencode) {
					$pval = urlencode($pval);
				}
				$buffer .= "{$pkey}={$pval}&";
			}
		}
		if (strlen($buffer) > 0	) {
			$returnQuery = substr($buffer, 0, strlen($buffer)-1);
		}
		return $returnQuery;
	}

	/**
	 * 数组转XML
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:25:54+0800
	 * --------------------------------------------------
	 * @param    array                   $array 数组
	 * @return   string                          
	 */
	public static function doArrayToXml($array)
	{
		$xml = "<xml>";
		foreach ($array as $key => $val) {
			if (is_numeric($val)) {
				$xml .= "<{$key}>{$val}</{$key}>";
			} else {
				$xml .= "<{$key}><![CDATA[{$val}]]></{$key}>";
			}
		}
		$xml .= "</xml>";
		return $xml;
	}

	/**
	 * 签名处理
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:31:41+0800
	 * --------------------------------------------------
	 * @param    string                   $content 签名内容
	 * @param    string                   $key     签名KEY
	 * @return   string                            
	 */
	public static function doSign($content, $key)
	{
		try {
			if (null == $key) {
				throw new Exception('签名KEY不能为空！');
			}
			if (null == $content) {
				throw new Exception("签名内容不能为空！");
			}

			$signStr = $content . "&key={$key}";
			return strtoupper(md5($signStr));
		} catch (Exception $e) {
			die($e->getMessage());
		}
	}

	/**
	 * 核查签名
	 * --------------------------------------------------
	 * @Author   Robin-L
	 * @DateTime 2016-10-19T17:35:07+0800
	 * --------------------------------------------------
	 * @param    string                   $content 签名内容
	 * @param    string                   $sign    加密串
	 * @param    string                   $md5Key  key
	 * @return   string                            
	 */
	public static function doVerifySignature($content, $sign, $md5Key)
	{
		$signStr = $content . "&key={$md5Key}";
		$calculateSign = strtolower(md5($signStr));
		$tenpaySign = strtolower($sign);
		return $calculateSign = $tenpaySign;
	}
}