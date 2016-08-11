<?php
namespace mobile\components\payments;
use Yii;
use mobile\components\shop\ShopConf;

class PayMalipay extends PayBase
{
    
    const SIGN_TYPE = 'MD5';
    const CHARSET   = 'utf-8';
    const GATEWAY   = 'https://mapi.alipay.com/gateway.do?';
    const SERVICE   = "alipay.wap.create.direct.pay.by.user";
    const TRANSPORT = 'http';//访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
    /**
     * HTTPS形式消息验证地址
     */
    const HTTPS_URL = 'https://mapi.alipay.com/gateway.do?service=notify_verify&';
    /**
     * HTTP形式消息验证地址
     */
    const HTTP_URL  = 'http://notify.alipay.com/trade/notify_query.do?';
    
    
    public $_config = array(
            'alipay_partner' => '2088039090902312',
            'alipay_key' => 'sfdsf131jkcniemmsdfh'
    );
    public $error = '';
    private $payment_sn = '';
    
    const NOTIFY_URL = 'http://yourhost.com/pay/notify/malipay';
    const RETURN_URL = 'http://yourhost.com/pay/return/malipay';
    
    /**
     * 获取支付表单
     * 
     * @date 2015-11-13 上午10:05:39
     * @author Ruesin
     */
    function get_payform($order_info, $type ="order"){
        
        $parameter = array(
                ## 基本参数
                "service"       => self::SERVICE, //接口名称
                "partner"       => trim($this->_config['alipay_partner']), //合作身份者id，签约的支付宝账号对应的支付宝唯一用户号 以2088开头的16位纯数字
                "_input_charset"	=> self::CHARSET, //字符编码格式 目前支持 gbk 或 utf-8
                //"sign_type" => 'MD5', //签名方式 DSA、 RSA、 MD5 三个值可选，必须大写。
                //"sign" => '',
                "notify_url"	=> self::NOTIFY_URL, //服务器异步通知页面路径 支付宝服务器主动通知商户网站里指定的页面 http 路径。 需http://格式的完整路径，不能加?id=123这类自定义参数
                "return_url"	=> self::RETURN_URL, //页面跳转同步通知页面路径 支付宝处理完请求后，当前页面自动跳转到商户网站里指定页面的 http 路径。 需http://格式的完整路径，不能加?id=123这类自定义参数，不能写成http://localhost/
                 
                ## 业务参数
                "out_trade_no"	=> self::$payment_sn, //商户订单号 商户网站订单系统中唯一订单号
                "subject"	    => "订单：{$order_info['order_sn']}", //订单(商品)名称 商品的标题/交易标题/订单标题/订单关键字等。该参数最长为 128 个汉字。
                "total_fee"	    => $order_info['order_amount'], //付款金额 该笔订单的资金总额，单位为RMB-Yuan。取值范围为[0.01，100000000.00]，精确到小数点后两位。
                "seller_id"     => $this->_config['alipay_partner'], //卖家支付宝收款账号 卖家支付宝账号对应的支付宝唯一用户号。以 2088 开头的纯 16 位数字。
                "payment_type"	=> '1',//支付类型 仅支持： 1（商品购买）。
                "body"	        => "我的订单：{$order_info['order_sn']}，支付单号为：".self::$payment_sn,//订单(商品)描述 //选填 对一笔交易的具体描述信息。如果是多种商品，请将商品描述字符串累加传给 body。
                //"show_url"	    => '', //商品展示地址 收银台页面上，商品展示的超链接。 //选填  需以http://开头的完整路径，例如：http://www.商户网址.com/myorder.html
                //"it_b_pay"	    => $it_b_pay, //超时时间 //选填 设置未付款交易的超时时间，一旦超时，该笔交易就会自动被关闭。取值范围： 1m～15d。m-分钟， h-小时， d-天， 1c-当天（无论交易何时创建，都在 0点关闭）。该参数数值不接受小数点，如1.5h，可转换为 90m。当用户输入支付密码、点击确认付款后（即创建支付宝交易后）开始计时。
                //"extern_token"	=> $extern_token, //钱包token //选填 接入极简版 wap 收银台时支持。当商户请求是来自支付宝钱包，在支付宝钱包登录后，有生成登录信息 token 时，使用该参数传入 token 将可以实现信任登录收银台，不需要再次登录。 登录后用户还是有入口可以切换账户，不能使用该参数锁定用户。
        );
        
        $html_text = $this->buildRequestForm($parameter,"get", "确认");
        echo $html_text ;
    }
    
    /**
     * 建立请求，以表单HTML形式构造
     */
    function buildRequestForm($para_temp, $method, $button_name) {
        $para = $this->buildRequestPara($para_temp);
        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='".self::GATEWAY."_input_charset=".trim(strtolower(self::CHARSET))."' method='".$method."'>";
        while (list ($key, $val) = each ($para)) {
            $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
        }
        //$sHtml = $sHtml."<input style='display:none;' type='submit' value='".$button_name."'></form>";
        $sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";
        return $sHtml;
    }
    
    /**
     * 校验返回结果 get
     * 
     * @date 2015-11-13 上午10:25:58
     * @author Ruesin
     */
    function verify_return($order_info){
        $data = $this->_get_notify();
// 	    $return = array(
// 	            ## 基本参数
// 	            "is_success"   => "T", //成功标识 表示接口调用是否成功，并不表明业务处理结果。// 必填
// 	            "sign_type"    => "MD5", //签名方式 SA、 RSA、 MD5 三个值可选，必须大写。 // 必填
// 	            "sign"         => "asdasdasdasdasvdgsd", //签名 // 必填
// 	            "service"      => "alipay.wap.create.direct.pay.by.user",//接口名称 标志调用哪个接口返回的链接。
// 	            "notify_id"    => "RqPnCoPT3K9%252Fvwbh3InQ8DTlBqQF2KlM0p08vXXXXXXXXXXMK3zQ4hsFX%252FtstP",  //通知校验ID 支付宝通知校验 ID，商户可以用这个流水号询问支付宝该条通知的合法性。
// 	            "notify_time"  => "2014-11-24 00:22:12", //通知时间（支付宝时间）。格式为 yyyy-MM-ddHH:mm:ss。
// 	            "notify_type"  => "trade_status_sync", // 返回通知类型。
// 	            ## 业务参数
// 	            "out_trade_no" => "11111111111", //商户网站唯一订单号  对应商户网站的订单系统中的唯一订单号，非支付宝交易号。需保证在商户网站中的唯一性。是请求时对应的参数，原样返回。
// 	            "trade_no"     => "2014112400001000340011111118",//该交易在支付宝系统中的交易流水号。最长 64 位。
// 	            "subject"      => "阿萨德",//商品的标题/交易标题/订单标题/订单关键字等。
// 	            "payment_type" => "1",//支付类型 对应请求时的参数，原样返回。
// 	            "trade_status" => "TRADE_SUCCESS", //交易状态  交易目前所处的状态。成功状态的值只有两个：TRADE_FINISHED普通即时到账的交易成功状态 TRADE_SUCCESS开通了高级即时到账或机票分销产品后的交易成功状态
// 	            "seller_id"    => "2088111111111112",//卖家支付宝账户号 卖家支付宝账号对应的支付宝唯一用户号。以 2088 开头的纯 16 位数字。
// 	            "total_fee"    => "173.36", //交易金额 该笔订单的资金总额，单位为RMB-Yuan。取值范围为[0.01，100000000.00]，精确到小数点后两位。
// 	            "body"         => "Amazon",//商品描述 对一笔交易的具体描述信息。请求参数原样返回。
// 	    );

        //验证消息是否是支付宝发出的合法消息
        if(!$this->verifyNotify($data)) {
            return false;
        }
        //远程校验通过
        //本地校验，订单状态，支付单号，金额等
        //TODO
        
        if($data['trade_status'] == 'TRADE_FINISHED' || $data['trade_status'] == 'TRADE_SUCCESS') {
            return true;
        }else {
            return false;
        }
        return true;
    }
    
    /**
     * 校验通知结果 post
     * 
     * @date 2015-11-13 上午10:32:28
     * @author Ruesin
     */
    function verify_notify ($order_info)
    {
//         $notify = array(
//                 ## 基本参数
//                 "notify_time" => "2014-11-24 00:22:07", ## 通知的发送时间
//                 "notify_type" => "trade_status_sync", ## 通知的类型
//                 "notify_id" => "bb7620a82f057fadfadfa1d05d05be77fc3w",## 通知校验 ID。
//                 "sign_type" => "MD5", ## 签名方式 SA、 RSA、 MD5 三个值可选，必须大写。
//                 "sign" => "asdasdasdasdasvdgsd", ## 签名
 
//                 ## 业务参数
//                 "out_trade_no" => "11111111111", // 商户网站唯一订单号 对应商户网站的订单系统中的唯一订单号，非支付宝交易号。需保证在商户网站中的唯一性。是请求时对应的参数，原样返回。
//                 "subject" => "阿萨德", // 商品的标题/交易标题/订单标题/订单关键字等。
//                 "payment_type" => "1", // 支付类型 对应请求时的 数，原样返回。 payment_type 参数，原样返回。
//                 "trade_no" => "2014112400001000340011111118", // 该交易在支付宝系统中的交易流水号。最长64位。
//                 "trade_status" => "TRADE_SUCCESS", // 交易状态 WAIT_BUYER_PAY 交易创建，等待买家付款。TRADE_CLOSED 在指定时间段内未支付时关闭的交易； 在交易完成全额退款成功时关闭的交易。TRADE_SUCCESS 交易成功，且可对该交易做操作，如：多级分润、退款等。TRADE_PENDING 等待卖家收款（买家付款后，如果卖家账号被冻结）。TRADE_FINISHED 交易成功且结束，即不可再做任何操作。
//                 "gmt_create" => "2014-11-24 00:21:52", // 交易创建时间 该笔交易创建的时间。
//                 "gmt_payment" => "2014-11-24 00:22:07", // 交易付款时间 该笔交易的买家付款时间。
//                 // "gmt_close" => "2014-11-24 00:23:07", //交易关闭时间
//                 "seller_email" => "payada@aadfad.com", // 卖家支付宝账号 卖家支付宝账号，可以是 email 和手机号码。
//                 "buyer_email" => "sherry.adf@a.com", // 买家支付宝账号 买家支付宝账号，可以是 Email或手机号码。
//                 "seller_id" => "2088001111111152", // 卖家支付宝账户号 卖家支付宝账号对应的支付宝唯一用户号。以 2088 开头的纯 16 位数字。
//                 "buyer_id" => "20880024011111110", // 买家支付宝账户号 卖家支付宝账号对应的支付宝唯一用户号。以 2088 开头的纯 16 位数字。
//                 "price" => "173.36", // 商品单价 如果请求时使用的是 total_fee，那么 price 等于 total_fee；如果请求时传了 price，那么对应请求时的 price 参数，原样通知回来。
//                 "total_fee" => "173.36", // 交易金额 该笔订单的总金额。 请求时对应的参数，原样通知回来。
//                 "quantity" => "1", // 购买数量 如果请求时使用的是 total_fee，那么 quantity 等于 1；如果请求时有传 quantity，那么对应请求时的 quantity 参数，原样通知回来。
//                 "body" => "Amazon", // 商品描述 对一笔交易的具体描述信息。请求参数原样返回。
//                 //"discount" => "-1", //折扣 支付宝系统会把 discount 的值加到交易金额上，如果需要折扣，本参数为负数。
//                 "is_total_fee_adjust" => "N", // 是否调整总价 该交易是否调整过价格。
//                 "use_coupon" => "N", // 是否使用红包买家 是否在交易过程中使用了红包
//                 //"refund_status" => "REFUND_SUCCESS", //退款状态 REFUND_SUCCESS 退款成功：z 全额退款情况： trade_status= TRADE_CLOSED，而refund_status=REFUND_SUCCESS 非全额退款情况： trade_status= TRADE_SUCCESS，而 refund_status=REFUND_SUCCESS REFUND_CLOSED 退款关闭
//                 // "gmt_refund" => "2008-10-29 19:38:25",//退款时间 卖家退款的时间，退款通知时会发送。
//         );
        $data = $this->_get_notify();
        //验证消息是否是支付宝发出的合法消息
        if (! $this->verifyNotify($data)) { 
            return false;
        }
        
        //远程校验通过
        //本地校验，订单状态，支付单号，金额等
        //TODO
        
        if ($data['trade_status'] == 'TRADE_FINISHED') {
            // 注意： 退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
            $order_status = 10;
        } elseif ($data['trade_status'] == 'TRADE_SUCCESS') {
            // 注意： 付款完成后，支付宝系统发送该交易状态通知
            $order_status = 20; // 已付款
        } else {
            $this->_error('undefined_status');
            return false;
        }
        $billdata = array(
                'status'   => $data['trade_status'],
                'end_time' => time(),
        );

        $this->updateBill($data['out_trade_no'], $billdata);
        
        //返回响应状态，支付成功的后续处理在其他类中，然后 verify_result(),响应支付宝
        return array(
                'status' => $order_status
        );
    }
    
    /**
     * 生成要请求给支付宝的参数数组
     */
    function buildRequestPara($para_temp) {
        
        $para_filter = $this->paraFilter($para_temp);
        
        $para_sort = self::arraySort($para_filter);
        
        $mysign = $this->buildRequestMysign($para_sort);
    
        //签名结果与签名方式加入请求提交参数组中
        $para_sort['sign'] = $mysign;
        $para_sort['sign_type'] = strtoupper(trim(self::SIGN_TYPE));
    
        return $para_sort;
    }
    
    /**
     * 除去数组中的空值和签名参数
     * 
     * @date 2015-11-13 上午10:24:26
     * @author Ruesin
     */
    function paraFilter($para) {
        $para_filter = array();
        while (list ($key, $val) = each ($para)) {
            if($key == "sign" || $key == "sign_type" || $val == "")continue;
            else	$para_filter[$key] = $para[$key];
        }
        return $para_filter;
    }
    
    /**
     * 对数组 按照键名升序排序，为数组值保留原来的键。
     * 
     * @date 2015-11-13 上午10:22:11
     * @author Ruesin
     */
    public static function arraySort($array){
        ksort($array);
        reset($array);
        return $array;
    }
    
    /**
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * 
     * @date 2015-11-13 上午10:24:42
     * @author Ruesin
     */
    function createLinkstring($para) {
        $arg  = "";
        while (list ($key, $val) = each ($para)) {
            $arg.=$key."=".$val."&";
        }
        //去掉最后一个&字符
        $arg = substr($arg,0,count($arg)-2);
    
        //如果存在转义字符，那么去掉转义
        if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}
    
        return $arg;
    }
    
    /**
     * 生成签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    function buildRequestMysign($para_sort) {
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort);
    
        $mysign = "";
        switch (strtoupper(trim(self::SIGN_TYPE))) {
        	case "RSA" :
        	    //$mysign = $this->rsaSign($prestr, $this->private_key_path);
        	    break;
        	case "MD5" :
        	    $mysign = md5($prestr.$this->_config['alipay_key']);
        	    break;
        	default :
        	    $mysign = "";
        }
    
        return $mysign;
    }
    
    
    /**
     * 验证消息是否是支付宝发出的合法消息
     *
     * @date 2015-11-13 上午10:33:29
     * @author Ruesin
     */
    function verifyNotify($data){
        if(empty($data)) {
            return false;
        } else {
            //生成签名结果
            $sign = $data['sign'];
            $para_filter = $this->paraFilter($data);
            $para_sort = self::arraySort($para_filter);
            $mysign = $this->buildRequestMysign($para_sort);
            
            if ($mysign != $sign){
                return false;
            }
            return true;
            //获取支付宝远程服务器ATN结果（验证是否是支付宝发来的消息）
            $responseTxt = 'true';
            if (! empty($data["notify_id"])) {
                $responseTxt = $this->getResponse($data["notify_id"]);
            }
            //验证
            //$responsetTxt的结果不是true，与服务器设置问题、合作身份者ID、notify_id一分钟失效有关
            //isSign的结果不是true，与安全校验码、请求时的参数格式（如：带自定义参数等）、编码格式有关
            if (preg_match("/true$/i",$responseTxt)) {
                return true;
            } else {
                return false;
            }
        }
    }
    
    
    /**
     * 远程获取数据，GET模式
     * 注意：
     * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
     * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
     * @param $url 指定URL完整路径地址
     * @param $cacert_url 指定当前工作目录绝对路径
     * return 远程输出的数据
     */
    function getHttpResponseGET($url,$cacert_url = '') {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
        if ($cacert_url){
            curl_setopt($curl, CURLOPT_CAINFO,$cacert_url);//证书地址
        }
        $responseText = curl_exec($curl);
        //var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
        curl_close($curl);
    
        return $responseText;
    }

    
    /**
     * 获取远程服务器ATN结果,验证返回URL
     * @param $notify_id 通知校验ID
     * @return 服务器ATN结果
     * 验证结果集：
     * invalid命令参数不对 出现这个错误，请检测返回处理中partner和key是否为空
     * true 返回正确信息
     * false 请检查防火墙或者是服务器阻止端口问题以及验证时间是否超过一分钟
     */
    function getResponse($notify_id) {
        $transport = strtolower(trim(self::TRANSPORT));
        
        $partner = trim($this->_config['alipay_partner']);
        
        $veryfy_url = '';
        if($transport == 'https') {
            $veryfy_url = self::HTTPS_URL;
        }
        else {
            $veryfy_url = self::HTTP_URL;
        }
        
        $veryfy_url = $veryfy_url."partner=" . $partner . "&notify_id=" . $notify_id;
        
        $responseTxt = $this->getHttpResponseGET($veryfy_url);
    
        return $responseTxt;
    }
    
    
    function verify_result($result)
    {
        if ($result)
        {
            echo 'success';
        }
        else
        {
            echo 'fail';
        }
    }
    
    // =========================
    // This can set in base class
    
    /**
     * 创建支付单
     * 
     * @date 2016年8月11日 下午3:23:03
     * @author Ruesin
     */
    public function createBill($order) {
        self::get_payment_sn();
        $bill = array(
                'payment_sn' => self::$payment_sn,
                'amount'     => $order['order_amount'],
                'member_id'  => $order['user_id'],
                'start_time' => time(),
                'order_id'   => $order['order_id'],
                'status'     => 0,
                'end_time'   => 0,
        );
        if(PaymentBills::insert($bill) < 0 ) return false;
        return true;
    }
    
    /**
     * 更新支付单
     * 
     * @date 2016年8月11日 下午3:23:19
     * @author Ruesin
     */
    function updateBill($payment_sn, $data){
        PaymentBills::updateDataBySn($data,$payment_sn);
    }
    
    /**
     * 生成支付单号
     * 
     * @date 2016年8月11日 下午3:23:32
     * @author Ruesin
     */
    static function get_payment_sn(){
        do{
            $sn = time() . str_pad(mt_rand(0,9999), 4, '0', STR_PAD_LEFT);
        }while(PaymentBills::getDataBySn($sn));
        self::$payment_sn = $sn;
    }
    
    function _error($msg, $obj=""){
        $this->error = $msg;
    }
    
    function get_error(){
        return $this->error;
    }
    
    function _get_notify()
    {
        if (!empty($_POST))
        {
            return $_POST;
        }
        return $_GET;
    }
    
}