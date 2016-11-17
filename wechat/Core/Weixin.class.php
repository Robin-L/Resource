<?php
require_once('class/mlog.php');
class Weixin {
    const MSG_TYPE_TEXT     = 'text';  //文本类型
    const MSG_TYPE_EVENT    = 'event'; //推送类型
    const MSG_EVENT_SUBSCRIBE   = 'subscribe';  //关注
    const MSG_EVENT_UNSUBCRIBE  = 'unsubscribe';//取消关注
    const MSG_EVENT_CLICK       = 'CLICK';      //点击推送
    const MSG_EVENT_SCAN        = 'SCAN';       //扫码推送
    const REPLY_TYPE_TEXT       = 'text';       //回复文本类型
    const REPLY_TYPE_IMAGE      = 'image';      //回复图片
    const REPLY_TYPE_VOICE      = 'voice';      //回复声音
	const REPLY_TYPE_MUSIC      = 'music';      //回复音乐
	const REPLY_TYPE_NEWS       = 'news';       //回复图文
	const REPLY_TYPE_VIDEO      = 'video';      //回复视频
    const TEMPLATESENDJOBFINISH = 'TEMPLATESENDJOBFINISH';  // 模板发送结果推送
    
    private $access_token;
    private $message;
    private $postdata;
    private $debug;
    public function __construct($config = array()){
        $this->code  = isset($config['code']) ? $config['code'] : (defined('WECHAT_CODE') ? WECHAT_CODE : '');
        $this->appid = isset($config['appid']) ? $config['appid'] : (defined('WECHAT_APPID') ? WECHAT_APPID : '');
        $this->appsecret = isset($config['appsecret']) ? $config['appsecret'] : (defined('WECHAT_APPSECRET') ? WECHAT_APPSECRET : '');
        $this->token = isset($config['token']) ? $config['token'] : (defined('WECHAT_TOKEN') ? WECHAT_TOKEN : '');
        $this->Cache = new Cache();
        $this->debug = true;
        //$this->access_token = getToken();
        $this->initRequest();
    }

    private function initRequest(){
        if (isset($_GET['echostr'])) {
            echo $_GET['echostr'];exit;
        } else {
            if (isset($GLOBALS['HTTP_RAW_POST_DATA']) && $GLOBALS['HTTP_RAW_POST_DATA']) {
                $this->message = $GLOBALS['HTTP_RAW_POST_DATA'];
				$this->postdata= simplexml_load_string($this->message, 'SimpleXMLElement', LIBXML_NOCDATA);
            } elseif(isset($_GET['HTTP_RAW_POST_DATA']) && $_GET['HTTP_RAW_POST_DATA']) {
                $this->message = $_GET['HTTP_RAW_POST_DATA'];
                $this->postdata= json_decode($this->message);
            } else {
                $this->postdata = (object)array('FromUserName' => '', 'ToUserName' => '', 'MsgType' => '',  'Event' => '');
            }
        }
    }

    //请求中的发信人OpenId
    public function getFromUserName(){
        return (string)$this->postdata->FromUserName;
    }

    //请求中的收信人OpenId,一般为公众账号自身
    public function getToUserName(){
        return (string)$this->postdata->ToUserName;
    }

    //判断是否为文本信息
    public function isTextMessage(){
        return $this->postdata->MsgType == self::MSG_TYPE_TEXT;
    }

    //获取文本消息内容
    public function requestText(){
        return strval($this->postdata->Content);
    }

    //判断是否为菜单点击事件
    public function isClickEvent(){
        return $this->postdata->MsgType == self::MSG_TYPE_EVENT && $this->postdata->Event == self::MSG_EVENT_CLICK;
    }

    //判断是否为扫码事件
    public function isEventScan()
    {
        return $this->postdata->MsgType == self::MSG_TYPE_EVENT && $this->postdata->Event == self::MSG_EVENT_SCAN;
    }

    //判断是否为模板消息返回事件 TEMPLATESENDJOBFINISH
    public function isTemplateSendJobFinish()
    {
        return $this->postdata->MsgType == self::MSG_TYPE_EVENT && $this->postdata->Event == self::TEMPLATESENDJOBFINISH;
    }

    //获取模板发放状态
    public function getRequestStatus()
    {
        return $this->postdata->Status;
    }

    //获取模板消息ID
    public function getRequestMsgId()
    {
        return $this->postdata->MsgID;
    }

    //返回二维码Ticket值
    public function getQrcodeTicket()
    {
        return $this->postdata->Ticket;
    }

    // 返回二维码SCENE_ID > 0
    public function getQrcodeSceneId()
    {
        return $this->postdata->EventKey;
    }

    //获取点击KEY
    public function requestClickEvent(){
        return strval($this->postdata->EventKey);
    }

    //是否为普通关注事件
    public function isEventSubscribe() {
        if ($this->postdata->Event !='') {
            if ($this->postdata->MsgType == self::MSG_TYPE_EVENT && $this->postdata->Event == self::MSG_EVENT_SUBSCRIBE) {
                return true;
            }
        } else {
            return false;
        }
    }

    private function getNormalAccessToken(){    //GET方式 获取 普通access_token
        $access_token = $this->Cache->get('g2_wechat_normal_access_token_cache_'.$this->code);
        if(!$access_token){
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->appsecret;
            $_json_token = $this->http_get($url);
            if($_json_token){
                $json_token = json_decode($_json_token, true);
                $access_token = isset($json_token['access_token']) ? $json_token['access_token'] : '';
                $this->Cache->set('g2_wechat_normal_access_token_cache_'.$this->code, $access_token, 0, 7000);  //过期时间
                //$this->Cache->set('wechat_normal_access_token_cache_'.$this->code, $access_token, 0, $json_token['expires_in']);  //过期时间
            }
        }
        return $access_token;
    }

    public function getWebAccessToken($code){   //GET方式 获取 网页授权access_token
        $returnArr = array();
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $this->appid . "&secret=" . $this->appsecret . "&code=" . $code . "&grant_type=authorization_code";
        $_json_accessToken = $this->http_get($url);
        $json_accessToken = json_decode($_json_accessToken, true);
        if (isset($json_accessToken['access_token']) && isset($json_accessToken['openid'])) {
            $returnArr['access_token'] = $json_accessToken['access_token'];
            $returnArr['openid']       = $json_accessToken['openid'];
        }
        return $returnArr;
    }

    // 拉取用户信息 scope = snsapi_userinfo
    public function getUserInfo($access_token, $openid){
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token=" . $access_token . "&openid=" . $openid . "&lang=zh_CN";
        $data = $this->http_get($url);
        // $this->result_log('WeixinApi_'.date('Ym'), '[GET_USER_INFO] - [Openid:'.$openid.'] - [Data:'.$data.'] - [Url:'.$url.']');
        $this->result_log("Weixin:getUserInfo|Wxcode:{$this->code}|[Input]-Access_token:{$access_token},Openid:{$openid}|Url:{$url}|[Output]-Result:{$data}");
        return $data;
    }

    //获取用户列表 https://api.weixin.qq.com/cgi-bin/user/get?access_token=ACCESS_TOKEN&next_openid=NEXT_OPENID
    public function getUser($next_openid = "", $type='json'){
        $accessToken = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token=$accessToken&next_openid=$next_openid";
        $data = $this->http_get($url);
        // $this->result_log('WeixinApi_'.date('Ym'), '[GET_USER] - [Data:'.$data.'] - [Url:'.$url.']');
        $this->result_log("Weixin:getUser|Wxcode:{$this->code}|[Input]-Next_openid:{$next_openid}|Url:{$url}|[Output]-Result:{$data}");
        return $this->returnResult($data, $type);
    }

    /**
        * @brief    用户同意授权，获取code
        *
        * @param    $type   snsapi_base     获取进入页面用户的openid，静默授权 
        *                   snsapi_userifo  获取用户的基本信息，需要用户手动同意
        * @param    $redirect_uri 授权后重定向的回调地址 urlenode处理
        *
        * @param    $state  重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值，最多128字节
        *
        * @return   跳转到生成的url
     */
    public function getSnsapiCode($type = 'snsapi_base', $redirect_uri, $state=0){
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $this->appid . "&redirect_uri=" . urlencode($redirect_uri) . "&response_type=code&scope=" . $type . "&state=" . $state . "#wechat_redirect";
        // $this->result_log('WeixinApi_'.date('Ym'), '[SNS_API_CODE] - [url:'.$url.']');
        $this->result_log("Weixin:getSnsapiCode|Wxcode:{$this->code}|[Input]-Type:{$type},Redirect_uri:{$redirect_uri},State:{$state}|Url:{$url}");
        header('Location:'.$url);exit;
    }

    // 生成jsApiTicket加密包
    public function getSignPackage($nurl = '') {
        $jsapiTicket = $this->getJsApiTicket();

        // URL 一定要动态获取
        $protocal = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $url = ($nurl !== '') ? $nurl : $protocal . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $timestamp = time();
        $nonceStr = $this->createNonceStr();
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);
        $signPackage = array(
            'appId'     => $this->appid,
            'nonceStr'  => $nonceStr,
            'timestamp' => $timestamp,
            'url'       => $url,
            'signature' => $signature,
            'rawString' => $string
        );
        $this->result_log("Weixin:getSignPackage|Wxcode:{$this->code}|[Input]-Nurl:{$nurl}|Url:{$url}|[Output]-Result:".json_encode($signPackage));
        return $signPackage;
    }

    // 获取随机数
    private function createNonceStr($length = 16){
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) -1), 1);
        }
        return $str;
    }

    //获取jsapi_ticket，用于调用微信JS-SDK接口
    private function getJsApiTicket() {
        $jsApiTicket = $this->Cache->get('g2_wechat_js_api_ticket_cache_'.$this->code);
        if (!$jsApiTicket) {
            $accessToken = $this->getNormalAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$accessToken}&type=jsapi";
            $_json_ticket = $this->http_get($url);
            $json_ticket = json_decode($_json_ticket, true);
            $jsApiTicket = $json_ticket['ticket'];
            $this->Cache->set('g2_wechat_js_api_ticket_cache_'.$this->code, $jsApiTicket, 1, 7000);
            // $this->result_log('WeixinApi_'.date('Ym'), '[JS_API_TICKET] - [Ticket:'.$jsApiTicket.'] - [Json:'.$_json_ticket.']');
            //$this->Cache->set('g2_wechat_js_api_ticket_cache_'.$this->code, $jsApiTicket, 1, $json_ticket['expires_in']);
        }
        return $jsApiTicket;
    }

    //获取永久素材列表
    //$type:    素材的类型，图片（image）、视频（video）、语音 （voice）、图文（news）
    //$offset:  从全部素材的该偏移位置开始返回，0表示从第一个素材 返回 
    //$count:   返回素材的数量，取值在1到20之间
    public function getBatchMaterial($type, $offset=0, $count=20){
        if(in_array($type, array('image', 'video', 'voice', 'news')) && $count > 0 && $count <= 20){
            $access_token = $this->getNormalAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token={$access_token}";
            $param = array(
                'type'  => $type,
                'offset'=> $offset,
                'count' => $count,
            );
            //*注：这边post的数据为json格式
            $data = $this->http_post($url, $this->json_encode_ex($param));
            // $this->result_log('WeixinApi_'.date('Ym'), '[GET_BATCH_MATERIAL] - [Material:'.$data.']');
            $this->result_log("Weixin:getBatchMaterial|Wxcode:{$this->code}|[Input]-Type:{$type},Offset:{$offset},Count:{$count}|Url:{$url},Param:".json_encode($param)."|[Output]-Result:{$data}");
        } else {
            $data = '参数不符合要求';
        }
        return $data;
    }

    //上传素材接口
    public function addMaterial($fileType, $filePath){
        if (file_exists($filePath)) {
            $media = array();
            $media['type'] = $fileType;
            $media['media'] = '@'.dirname(dirname(__FILE__)).'\song.mp3';//.$filePath;
            $access_token = $this->getNormalAccessToken();
            $url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.$access_token;
            $data = $this->http_post($url, $media);
            // $this->result_log('WeixinApi_'.date('Ym'), '[ADD_MATERIAL] - [Result:'.$data.']');
            return $data;
        }
    }

    //获取素材总数
    public function getMaterialCount(){
        $access_token = $this->getNormalAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/material/get_materialcount?access_token='.$access_token;
        $data = $this->http_get($url);
        // $this->result_log('WeixinApi_'.date('Ym'), '[MATERIAL_COUNT] - [Result:'.$data.'] - [UrL:'.$url.']');
        $this->result_log("Weixin:getMaterialCount|Wxcode:{$this->code}|Url:{$url}|[Output]-Result:{$data}");
        return $data;
    }

    //生成微信菜单
    //$param => json串
    public function createWeixinMenu($param){
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$access_token}";
        $data = $this->http_post($url, $this->json_encode_ex($param));
        // $this->result_log('WeixinApi_'.date('Ym'), '[CREATE_WEIXIN_MENU] -[Param:'.$this->json_encode_ex($param).'] - [Result'.$data.']');
        $this->result_log("Weixin:createWeixinMenu|Wxcode:{$this->code}|[Input]-Param:".json_encode($param)."|Url:{$url}|[Output]-Result:{$data}");
        return $data;
    }

    // GET方式获取数据
    private function http_get($url) {
		$oCurl = curl_init ();
		if (stripos ( $url, "https://" ) !== FALSE) {
			curl_setopt ( $oCurl, CURLOPT_SSL_VERIFYPEER, FALSE );
			curl_setopt ( $oCurl, CURLOPT_SSL_VERIFYHOST, FALSE );
		}
		curl_setopt ( $oCurl, CURLOPT_URL, $url );
		curl_setopt ( $oCurl, CURLOPT_RETURNTRANSFER, 1 );
		$sContent = curl_exec ( $oCurl );
		$aStatus = curl_getinfo ( $oCurl );
		curl_close ( $oCurl );
		if (intval ( $aStatus ["http_code"] ) == 200) {
			return $sContent;
		} else {
			return false;
		}
	}

    // POST方式获取数据
    public function http_post($url, $param) {
		$oCurl = curl_init ();
		if (stripos ( $url, "https://" ) !== FALSE) {
			curl_setopt ( $oCurl, CURLOPT_SSL_VERIFYPEER, FALSE );
			curl_setopt ( $oCurl, CURLOPT_SSL_VERIFYHOST, false );
		}
		if (is_string ( $param )) {
			$strPOST = $param;
		} else {
			$aPOST = array ();
			foreach ( $param as $key => $val ) {
				$aPOST [] = $key . "=" . urlencode ( $val );
			}
			$strPOST = join ( "&", $aPOST );
		}
		curl_setopt ( $oCurl, CURLOPT_URL, $url );
		curl_setopt ( $oCurl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $oCurl, CURLOPT_POST, true );
		curl_setopt ( $oCurl, CURLOPT_POSTFIELDS, $strPOST );
		$sContent = curl_exec ( $oCurl );
		$aStatus = curl_getinfo ( $oCurl );
		curl_close ( $oCurl );
		if (intval ( $aStatus ["http_code"] ) == 200) {
			return $sContent;
		} else {
			return false;
		}
	}

    /**
     * 对变量进行 JSON 编码
     * @param mixed value 待编码的 value ，除了resource 类型之外，可以为任何数据类型，该函数只能接受 UTF-8 编码的数据
     * @return string 返回 value 值的 JSON 形式
     */
    public function json_encode_ex($value) {
        if (version_compare(PHP_VERSION,'5.4.0','<')) {
            $str = json_encode($value);
            $str = preg_replace_callback("#\\\u([0-9a-f]{4})#i",
                function($matchs){
                    return iconv('UCS-2BE', 'UTF-8', pack('H4', $matchs[1]));
                }, $str);
            return $str;
        } else {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
    }

    //回复文本消息
    public function responseTextMessage($content){
        $textTpl = "<xml>
		<ToUserName><![CDATA[%s]]></ToUserName>
		<FromUserName><![CDATA[%s]]></FromUserName>
		<CreateTime>%s</CreateTime>
		<MsgType><![CDATA[%s]]></MsgType>
		<Content><![CDATA[%s]]></Content>
		</xml>";
        $resultStr = sprintf($textTpl, $this->postdata->FromUserName, $this->postdata->ToUserName, time(), self::REPLY_TYPE_TEXT, $content);
		if (!headers_sent()){
            header('Content-Type: application/xml; charset=utf-8');
        }
        echo $resultStr;
    }

    //回复图文消息
    public function responseNewsMessage($items) {
		$textTpl = '<xml>
		<ToUserName><![CDATA[%s]]></ToUserName>
		<FromUserName><![CDATA[%s]]></FromUserName>
		<CreateTime>%s</CreateTime>
		<MsgType><![CDATA[%s]]></MsgType>
		<ArticleCount>%d</ArticleCount>
		<Articles>%s</Articles>
		</xml>';

		$itemTpl = '<item>
		<Title><![CDATA[%s]]></Title>
		<Description><![CDATA[%s]]></Description>
		<PicUrl><![CDATA[%s]]></PicUrl>
		<Url><![CDATA[%s]]></Url>
		</item>';

		$articles = '';
		if ($items && is_array($items)) {
			foreach ($items as $item) {
				if (is_array($item) && (isset($item['Title']) || isset($item['Description']) || isset($item['PicUrl']) || isset($item['Url']))){
                    $articles .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
                } else{
					throw new Exception("item => array('Title'=>'','Description'=>'','PicUrl'=>'','Url'=>'')");
                }
			}
		}
		$resultStr = sprintf($textTpl, $this->postdata->FromUserName, $this->postdata->ToUserName, time(), self::REPLY_TYPE_NEWS, count($items), $articles);
		if (!headers_sent()){
            header('Content-Type: application/xml; charset=utf-8');
        }
		echo $resultStr;
	}

    //回复图片消息
    public function responseImageMessage($mediaid) {
		$textTpl = "<xml>
		<ToUserName><![CDATA[%s]]></ToUserName>
		<FromUserName><![CDATA[%s]]></FromUserName>
		<CreateTime>%s</CreateTime>
		<MsgType><![CDATA[%s]]></MsgType>
		<Image>
		<MediaId><![CDATA[%s]]></MediaId>
		</Image>
		</xml>";
		$resultStr = sprintf($textTpl, $this->postdata->FromUserName, $this->postdata->ToUserName, time(), self::REPLY_TYPE_IMAGE, $mediaid);
		if (!headers_sent()){
            header('Content-Type: application/xml; charset=utf-8');
        }
		echo $resultStr;
    }

    //回复语音消息
    public function responseVoiceMessage($mediaid) {
		$textTpl = "<xml>
		<ToUserName><![CDATA[%s]]></ToUserName>
		<FromUserName><![CDATA[%s]]></FromUserName>
		<CreateTime>%s</CreateTime>
		<MsgType><![CDATA[%s]]></MsgType>
		<Voice>
		<MediaId><![CDATA[%s]]></MediaId>
		</Voice>
		</xml>";
		$resultStr = sprintf($textTpl, $this->postdata->FromUserName, $this->postdata->ToUserName, time(), self::REPLY_TYPE_VOICE, $mediaid);
		if (!headers_sent()){
            header('Content-Type: application/xml; charset=utf-8');
        }
		echo $resultStr;
    }

    //回复视频消息
    public function responseVideoMessage($mediaid, $title= "", $description="") {
		$textTpl = "<xml>
		<ToUserName><![CDATA[%s]]></ToUserName>
		<FromUserName><![CDATA[%s]]></FromUserName>
		<CreateTime>%s</CreateTime>
		<MsgType><![CDATA[%s]]></MsgType>
		<Video>
		<MediaId><![CDATA[%s]]></MediaId>
        <Title><![CDATA[%s]]></Title>
        <Description><![CDATA[%s]]></Description>
		</Video>
		</xml>";
		$resultStr = sprintf($textTpl, $this->postdata->FromUserName, $this->postdata->ToUserName, time(), self::REPLY_TYPE_VIDEO, $mediaid, $title, $description);
		if (!headers_sent()){
            header('Content-Type: application/xml; charset=utf-8');
        }
		echo $resultStr;
    }

    //发送客服消息
    //@args = array('type' => '0/1/2/3', 'text' => '...')
    public function sendCustomMessage($openid, $args){
        $access_token = $this->getNormalAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$access_token;
        if (isset($args['type'])) {
            switch ($args['type']) {
                case '0':
                    $json = array(
                        'touser' => "$openid",  //必须使用双引号
                        'msgtype'=> "text",
                        'text'   => array('content' => $args['text']),
                    );
                    //$this->result_log('custom_text', $this->json_encode_ex($json));
                    break;
                case '2':
                    $json = array(
                        'touser' => "$openid",
                        'msgtype'=> "image",
                        'image'   => array('media_id' => trim($args['text']))
                    );
                    break;
                case '3':
                    $json = array(
                        'touser' => "$openid",
                        'msgtype'=> "voice",
                        'voice'   => array('media_id' => trim($args['text']))
                    );
                    break;
                case '4': //视频暂时不支持
                    /*$json = array(
                        'touser' => "$openid",
                        'msgtype'=> "video",
                        'video'   => array('media_id' => trim($args['text']))
                    );*/
                    $json = array();
                    break;
                case '1':
                    if ($args['text']) {
                        $temp = json_decode($args['text'], true);
                        $json = array(
                            'touser' => "$openid",
                            'msgtype'=> "news",
                        );
                        foreach ($temp as $tkey => $titem) {
                            $json['news']['articles'][] = array(
                                'title' => isset($titem['Title']) ? $titem['Title'] : '',
                                'description' => isset($titem['Description']) ? $titem['Description'] : '',
                                'url' => isset($titem['Url']) ? $titem['Url'] : '',
                                'picurl' => isset($titem['PicUrl']) ? $titem['PicUrl'] : '',
                            );
                        }
                    }
                    break;
            }
            $data = $this->http_post($url, $this->json_encode_ex($json));
            //$this->result_log('custom_result', $this->json_encode_ex($json));
            return $data;
        }
    }

    /**
        * @brief    生成带参数的二维码
        *
        * @param    $action_name        二维码类型 QR_SCENE -> 临时 QR_LIMIT_SCENE -> 永久 QR_LIMIT_STR_SCENE
        * @param    $expire_seconds     二维码有效时间，单位秒。不超过2592000(30天)，默认为30秒
        * @param    $action_info        二维码详细信息
        * @param    $scene_id           场景值ID，临时二维码为32位非0整型，永久二维码时最大值为100000（1-100000）
        * @param    $scene_str          场景值ID（字符串形式的ID），长度限制为1到64，仅永久二维码
        *
        * @return   
    */
    public function qrcode($action_name, $scene_id, $scene_str = '', $expire_seconds = 3600)
    {
        $access_token = $this->getNormalAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$access_token;
        switch ($action_name) {
            case 'QR_SCENE':    //临时二维码
                if($scene_id && is_numeric($scene_id)){
                    //{"expire_seconds": 604800, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": 123}}}
                    $post_data = array(
                        'expire_seconds'    => $expire_seconds,
                        'action_name'       => $action_name,
                        'action_info'       => array(
                            'scene' => array(
                                'scene_id'  => $scene_id
                            ),
                        ),
                    );
                } else {
                    $post_data = array();
                }
                break;

            case 'QR_LIMIT_SCENE':  //永久二维码
                // {"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": 123}}}
                if ($scene_id && is_numeric($scene_id) && $scene_id <= 100000) {
                    $post_data = array(
                        'action_name'   => $action_name,
                        'action_info'   => array(
                            'scene'     => array(
                                'scene_id' => $scene_id,
                            )
                        ),
                    );
                } else {
                    $post_data = array();
                }
                break;
            case 'QR_LIMIT_STR_SCENE':
                // {"action_name": "QR_LIMIT_STR_SCENE", "action_info": {"scene": {"scene_str": "123"}}}
                if($scene_str != '' && strlen($scene_str) <= 64){
                    $post_data = array(
                        'action_name'   => $action_name,
                        'action_info'   => array(
                            'scene'     => array(
                                'scene_str'=> $scene_str,
                            )
                        ),
                    );
                } else {
                    $post_data = array();
                }
                break;
        }

        if(empty($post_data)){
            $data = json_encode(array('status' => 0, 'msg' => '参数不符合格式'));
        } else {
            $data = $this->http_post($url, $this->json_encode_ex($post_data));
        }
        // $this->result_log('WeixinApi_'.date('Ym'), '[QRCODE] - [Type:'.$action_name.'] - [Result:'.$this->json_encode_ex($data).']');
        $this->result_log("Weixin:qrcode|Wxcode:{$this->code}|[Input]-Action_name:{$action_name},Scene_id:{$scene_id},Scene_str:{$scene_str},Expire_seconds:{$expire_seconds}|Url:{$url},Post_data:".json_encode($post_data)."|[Output]-Result:".json_encode($data));
        return $data;
    }

    /**
     * 将信息转到多客服系统
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-07-01T14:28:16+0800
     * --------------------------------------------------
     * @return   xml                   
     */
    public function transmitService()
    {
        $xmlTpl = "<xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[transfer_customer_service]]></MsgType>
        </xml>";
        $result = sprintf($xmlTpl, $this->postdata->FromUserName, $this->postdata->ToUserName, time());
        if (!headers_sent()) {
            header('Content-Type:application/xml; charset=utf-8');
        }
        // $this->result_log('WeixinApi_'.date('Ym'), '[TRANSMET_SERVICE] - [Result:'.$result.']');
        echo $result;
    }

    public function transmitServiceAccount($account)
    {
        $xmlTpl = "<xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[transfer_customer_service]]></MsgType>
        <TransInfo>
            <KfAccount><![CDATA[%s]]></KfAccount>
        </TransInfo>
        </xml>";
        $result = sprintf($xmlTpl, $this->postdata->FromUserName, $this->postdata->ToUserName, time(), $account);
        if (!headers_sent()) {
            header('Content-Type:application/xml; charset=utf-8');
        }
        $this->result_log("Weixin:transmitServiceAccount|Wxcode:{$this->code}|[Input]-Account:{$account}|[Output]-Result:{$result}");
        echo $result;
    }

    /**
     * 获取客服基本信息
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-07-01T14:33:15+0800
     * --------------------------------------------------
     * @return   array
     */
    public function getKfList()
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/customservice/getkflist?access_token={$access_token}";
        $data = $this->http_get($url);
        // $this->result_log('WeixinApi_'.date('Ym'), '[GET_KF_LIST] - [Result:'.$data.']');
        $this->result_log("Weixin:getKfList|Wxcode:{$this->code}|Url:{$url}|[Output]-Result:{$data}");
        return  json_decode($data, true);
    }

    /**
     * 获取在线客服基本信息
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-07-01T14:34:26+0800
     * --------------------------------------------------
     * @return   array
     */
    public function getOnlineKfList($json=0)
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/customservice/getonlinekflist?access_token={$access_token}";
        $data = $this->http_get($url);
        // $this->result_log('WeixinApi_'.date('Ym'), '[ONLINE_KF_LIST] - [Result:'.$data.'] - [Url:'.$url.']');
        $this->result_log("Weixin:getOnlineKfList|Wxcode:{$this->code}|Url:{$url}|[Output]-Result:{$data}");
        if ($json > 0) {
            return $data;
        }
        return json_decode($data, true);
    }

    /**
     * 获取客服聊天记录接口
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-07-01T15:02:53+0800       
     * --------------------------------------------------
     * @param    string                   $date      年-月-日
     * @param    integer                  $pageIndex 查询第几页
     * @param    integer                  $pageSize  每页大小，<=50
     * @return   array
     */
    public function getRecord($date = '', $pageIndex = 1, $pageSize = 10)
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/customservice/msgrecord/getrecord?access_token={$access_token}";
        if ($date == '') {
            $date = date('Y-m-d');
        }
        $starttime = strtotime($date . ' 00:00:00');
        $endtime   = strtotime($date . ' 23:59:59');
        $postdata = array(
            'endtime'   => $endtime,
            'pageindex' => $pageIndex,
            'pagesize'  => $pageSize,
            'starttime' => $starttime
        );
        $data = $this->http_post($url, $this->json_encode_ex($postdata));
        // $this->result_log('WeixinApi_'.date('Ym'), '[GET_RECORD] - [Json:'.$data.'] - [Post:'.$this->json_encode_ex($postdata).']');
        $this->result_log("Weixin:getRecord|Wxcode:{$this->code}|[Input]-Date:{$date},PageIndex:{$pageIndex},PageSize:{$pageSize}|Postdata:".json_encode($postdata)."|[Output]-Result:{$data}");
        return json_decode($data, true);
    }

    /**
     * 获取聊天记录
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-10-12T09:48:49+0800
     * --------------------------------------------------
     * @param    int                   $starttime 起始时间戳
     * @param    int                   $endtime   结束时间戳，每次查询时段不能超过24小时
     * @param    int                   $msgid     消息id顺序从小到达，从1开始
     * @param    int                   $number    每次获取条数，最多10000条
     * @return   array                              
     */
    public function getMsglist($starttime, $endtime, $msgid, $number=10000)
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/customservice/msgrecord/getmsglist?access_token={$access_token}";
        $postdata = array(
            'starttime' => $starttime,
            'endtime'   => $endtime,
            'msgid'     => $msgid,
            'number'    => $number,
        );
        $data = $this->http_post($url, $this->json_encode_ex($postdata));
        $this->result_log("Weixin:getMsglist|Wxcode:{$this->code}|[Input]-Starttime:{$starttime},Endtime:{$endtime},Msgid:{$msgid},number:{$number}|Postdata:".json_encode($postdata)."|[Output]-Result:{$data}");
        return json_decode($data, true);
    }

    /**
     * 获取未接入会话列表
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-07-04T16:12:19+0800
     * --------------------------------------------------
     * @return   array                   
     */
    public function getWaitCase()
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/customservice/kfsession/getwaitcase?access_token={$access_token}";
        $data = $this->http_get($url);
        // $this->result_log('WeixinApi_'.date('Ym'), '[GET_WAIT_CASE] - [Json:'.$data.'] - [Url:'.$url.']');
        $this->result_log("Weixin:getWaitCase|Wxcode:{$this->code}|Url:{$url}|[Output]-Result:{$data}");
        return json_decode($data, true);
    }

    /**
     * 创建会话  -- 不可用
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-10-13T11:58:21+0800
     * --------------------------------------------------
     * @param    string                   $account 客服账号
     * @param    string                   $openid  微信OpenID
     * @return   array                            
     */
    public function kfsessionCreate($account, $openid)
    {
        $access_token = $this->getNormalAccessToken();
        $url = " https://api.weixin.qq.com/customservice/kfsession/create?access_token={$access_token}";
        $postdata = array(
            'kf_account'    => $account,
            'openid'        => $openid
        );
        $data = $this->http_post($url, $this->json_encode_ex($postdata));
        $this->result_log("Weixin:kfsessionCreate|Wxcode:{$this->code}|[Input]-Account:{$account},Openid:{$openid}|Postdata:".json_encode($postdata)."|[Output]-Result:{$data}");
        return json_decode($data, true);
    }

    /**
     * 关闭多客服会话
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-10-13T11:33:29+0800
     * --------------------------------------------------
     * @param    string                   $account 客服账号
     * @param    string                   $openid  微信Openid
     * @return   array                            
     */
    public function kfsessionClose($account, $openid)
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/customservice/kfsession/close?access_token={$access_token}";
        $postdata = array(
            'kf_account' => $account,
            'openid'     => $openid,
        );
        $data = $this->http_post($url, $this->json_encode_ex($postdata));
        $this->result_log("Weixin:kfsessionClose|Wxcode:{$this->code}|[Input]-Account:{$account},Openid:{$openid}|Postdata:".json_encode($postdata)."|[Output]-Result:{$data}");
        return json_decode($data, true);
    }

    /**
     * 获取用户基本信息（包括UnionID机制）
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-07-18T10:18:50+0800
     * --------------------------------------------------
     * @params   string                   Openid
     * @return   Json                   
     */
    public function getUserInfoByOpenid($openid)
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$access_token}&openid={$openid}&lang=zh_CN";
        $data = $this->http_get($url);
        // $this->result_log('WeixinApi_'.date('Ym'), '[USERINFO_BY_OPENID] - [Json:'.$data.'] - [Url:'.$url.']');
        $this->result_log("Weixin:getUserInfoByOpenid|Wxcode:{$this->code}|[Input]-Openid:{$openid}|Url:{$url}|[Output]-Result:{$data}");
        return $data;
    }

    /**
     * 批量获取用户基本信息
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-07-19T10:19:04+0800
     * --------------------------------------------------
     * @param    array                    $postdata post的openid数据
     * @return   json                             
     */
    public function batchGetUserInfo($postdata = array())
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token={$access_token}";
        $data = $this->http_post($url, $this->json_encode_ex($postdata));
        // $this->result_log('WeixinApi_'.date('Ymd'), "[BATCH_GET_USER_INFO]-[Json:'.$data.']");
        $this->result_log("Weixin:batchGetUserInfo|Wxcode:{$this->code}|[Input]-Postdata:".json_encode($postdata)."|Url:{$url}|[Output]-Result:{$data}");
        return $data;
    }

    /**
     * 模板相关-设置所属行业
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-09-20T18:25:41+0800
     * --------------------------------------------------
     * @param    int                   $industry_id1 公众号模板消息所属行业编号
     * @param    int                   $industry_id2 公众号模板消息所属行业编号
     * @return   json                                 
     */
    public function apiSetIndustry($industry_id1, $industry_id2)
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/template/api_set_industry?access_token={$access_token}";
        $postdata = array(
            'industry_id1'  => $industry_id1,
            'industry_id2'  => $industry_id2,
        );
        $data = $this->http_post($url, $this->json_encode_ex($postdata));
        $this->result_log("Weixin:apiSetIndustry|Wxcode:{$this->code}|[Input]-Industry_id1:{$industry_id1},Industry_id2:{$industry_id2}|Url:{$url}|Postdata:".json_encode($postdata)."|[Output]-Result:{$data}");
        return $data;
    }

    /**
     * 模板相关-获取设置的行业信息
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-09-20T18:29:03+0800
     * --------------------------------------------------
     * @return   json                   
     */
    public function getIndustry()
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/template/get_industry?access_token={$access_token}";
        $data = $this->http_get($url);
        $this->result_log("Weixin:getIndustry|Wxcode:{$this->code}|Url:{$url}|[Output]-Result:{$data}");
        return $data;
    }

    /**
     * 模板相关-获得模板ID
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-09-20T18:32:31+0800
     * --------------------------------------------------
     * @param    string                   $template_id_short 模板库中模板的编号，有“TM**”和“OPENTMTM**”等形式
     * @return   json
     */
    public function apiAddTemplate($template_id_short)
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token={$access_token}";
        $postdata = array('template_id_short' => $template_id_short);
        $data = $this->http_post($url, $this->json_encode_ex($postdata));
        $this->result_log("Weixin:apiAddTemplate|Wxcode:{$this->code}|[Input]-Template_id_short:{$template_id_short}|Url:{$url}|Postdata:".json_encode($postdata)."|[Output]-Result:{$data}");
        return $data;
    }

    /**
     * 模板相关-获取模板列表
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-09-20T18:34:57+0800
     * --------------------------------------------------
     * @return   json                   
     */
    public function getAllPrivateTemplate()
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token={$access_token}";
        $data = $this->http_get($url);
        $this->result_log("Weixin:getAllPrivateTemplate|Wxcode:{$this->code}|Url:{$url}|[Output]-Result:{$data}");
        return $data;
    }

    /**
     * 模板相关-删除模板
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-09-20T18:38:08+0800
     * --------------------------------------------------
     * @param    string                   $template_id 公众账号下模板消息ID
     * @return   json                                
     */
    public function delPrivateTemplate($template_id)
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/template/del_private_template?access_token={$access_token}";
        $postdata = array(
            'template_id'   => $template_id
        );
        $data = $this->http_post($url, $this->json_encode_ex($postdata));
        $this->result_log("Weixin:delPrivateTemplate|Wxcode:{$this->code}|[Input]-Template_id:{$template_id}|Url:{$url}|Postdata:".json_encode($postdata)."|[Output]-Result:{$data}");
        return $data;
    }

    /**
     * 模板相关-发送模板消息
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-09-20T19:31:27+0800
     * --------------------------------------------------
     * @param    string                   $openid      接收者Openid
     * @param    string                   $template_id 模板ID
     * @param    string                   $link        模板跳转链接
     * @param    array                    $dataArr     模板数据
     * @return   mix                                
     */
    public function sendTemplate($openid, $template_id, $link, $dataArr)
    {
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";
        $postdata = array(
            'touser'    => $openid,
            'template_id'   => $template_id,
            'url'       => $link,
            'data'      => $dataArr
        );
        $data = $this->http_post($url, $this->json_encode_ex($postdata));
        $this->result_log("Weixin:sendTemplate|Wxcode:{$this->code}|[Input]-Openid:{$openid},Template_id:{$template_id},Link:{$link},DataArr:[".json_encode($dataArr)."]|Url:{$url}|Postdata:".json_encode($postdata)."|[Output]-Result:{$data}");
        return $data;
    }

    /**
     * 获取微信服务器IP地址
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-09-06T20:31:29+0800
     * --------------------------------------------------
     * @return   json
     */
    public function getWeixinIp(){
        $access_token = $this->getNormalAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/getcallbackip?access_token={$access_token}";
        $data = $this->http_get($url);
        $this->result_log("Weixin:getWeixinIp|Wxcode:{$this->code}|Url:{$url}|[Output]-Result:{$data}");
        return $data;
    }

    //保存日志记录
    public function result_log($str){
        if($this->debug == true){
            log_result($str, 'weixin_class_'.date('Ym').'.log');
        }
    }

    /**
     * 返回值处理
     * --------------------------------------------------
     * @Author   Robin-L
     * @DateTime 2016-10-19T18:32:05+0800
     * --------------------------------------------------
     * @param    mixed                   $returnArr 数组或json串
     * @param    string                  $type      json/array
     * @return   json/array
     */
    public function returnResult($returnArr, $type='json')
    {
        if ($type == 'json' && is_array($returnArr)) {
            return json_encode($returnArr);
        } else if($type == 'array' && !is_array($returnArr)) {
            return json_decode($returnArr, true);
        }
        return $returnArr;
    }
}