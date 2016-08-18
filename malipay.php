<?php

class PayMalipay extends PayBase
{
    
    const SIGN_TYPE = 'MD5';

    const CHARSET = 'utf-8';

    const GATEWAY = 'https://mapi.alipay.com/gateway.do?';

    const SERVICE = "alipay.wap.create.direct.pay.by.user";

    const TRANSPORT = 'http';
    
    /**
     * HTTPS形式消息验证地址
     */
    const HTTPS_URL = 'https://mapi.alipay.com/gateway.do?service=notify_verify&';

    /**
     * HTTP形式消息验证地址
     */
    const HTTP_URL = 'http://notify.alipay.com/trade/notify_query.do?';

    public $_config = array(
            'alipay_partner' => '2088039090902312',
            'alipay_key' => 'sfdsf131jkcniemmsdfh'
    );

    public $error = '';

    const NOTIFY_URL = 'http://yourhost.com/pay/notify/malipay';

    const RETURN_URL = 'http://yourhost.com/pay/return/malipay';

    /**
     * 获取支付表单
     *
     * @date 2015-11-13 上午10:05:39
     * @author Ruesin
     */
    function get_payform ($order)
    {
        
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
                "out_trade_no"	=> $order['out_trade_no'], //商户订单号 商户网站订单系统中唯一订单号
                "subject"	    => "订单：{$order['order_sn']}", //订单(商品)名称 商品的标题/交易标题/订单标题/订单关键字等。该参数最长为 128 个汉字。
                "total_fee"	    => $order['order_amount'], //付款金额 该笔订单的资金总额，单位为RMB-Yuan。取值范围为[0.01，100000000.00]，精确到小数点后两位。
                "seller_id"     => $this->_config['alipay_partner'], //卖家支付宝收款账号 卖家支付宝账号对应的支付宝唯一用户号。以 2088 开头的纯 16 位数字。
                "payment_type"	=> '1',//支付类型 仅支持： 1（商品购买）。
                "body"	        => "我的订单：{$order['order_sn']}，支付单号为：".$order['out_trade_no'],//订单(商品)描述 //选填 对一笔交易的具体描述信息。如果是多种商品，请将商品描述字符串累加传给 body。
                //"show_url"	    => '', //商品展示地址 收银台页面上，商品展示的超链接。 //选填  需以http://开头的完整路径，例如：http://www.商户网址.com/myorder.html
                //"it_b_pay"	    => $it_b_pay, //超时时间 //选填 设置未付款交易的超时时间，一旦超时，该笔交易就会自动被关闭。取值范围： 1m～15d。m-分钟， h-小时， d-天， 1c-当天（无论交易何时创建，都在 0点关闭）。该参数数值不接受小数点，如1.5h，可转换为 90m。当用户输入支付密码、点击确认付款后（即创建支付宝交易后）开始计时。
                //"extern_token"	=> $extern_token, //钱包token //选填 接入极简版 wap 收银台时支持。当商户请求是来自支付宝钱包，在支付宝钱包登录后，有生成登录信息 token 时，使用该参数传入 token 将可以实现信任登录收银台，不需要再次登录。 登录后用户还是有入口可以切换账户，不能使用该参数锁定用户。
        );
        
        $html_text = $this->buildRequestForm($parameter, "get", "确认");
        echo $html_text;
    }

    /**
     * 建立请求，以表单HTML形式构造
     */
    function buildRequestForm ($para_temp, $method, $button_name)
    {
        $para = $this->buildRequestPara($para_temp);
        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='" . self::GATEWAY .
                 " _input_charset=" . trim(strtolower(self::CHARSET)) . "' method='" . $method . "'>";
        while (list ($key, $val) = each($para)) {
            $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
        }
        $sHtml = $sHtml . "<input style='display:none;' type='submit' value='" . $button_name . "'></form>";
        $sHtml = $sHtml . "<script>document.forms['alipaysubmit'].submit();</script>";
        return $sHtml;
    }
    
    /**
     * 校验返回结果
     * 
     * @date 2015-11-13 上午10:25:58
     * @author Ruesin
     */
    function verify_return ()
    {
        $data = $this->_get_notify();
        
        // 验证消息是否是支付宝发出的合法消息
        if (! $this->verifyNotify($data)) {
            return false;
        }
        
        // 本地校验：订单状态，支付单号，金额等
        // TODO
        
        if ($data['trade_status'] == 'TRADE_FINISHED' || $data['trade_status'] == 'TRADE_SUCCESS') {
            return true;
        } else {
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
    function verify_notify ()
    {
        $data = $this->_get_notify();
        
        // 验证消息是否是支付宝发出的合法消息
        if (! $this->verifyNotify($data)) {
            return false;
        }
        
        // 本地校验：订单状态，支付单号，金额等
        // TODO
        
        if ($data['trade_status'] == 'TRADE_FINISHED') {
            // 退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
            $status = 10;
        } elseif ($data['trade_status'] == 'TRADE_SUCCESS') {
            // 付款完成后，支付宝系统发送该交易状态通知
            $status = 20; // 已付款
        } else {
            $this->_error('undefined_status');
            return false;
        }
        
        // 更新支付单、订单状态等
        // TODO
        
        return $status;
    }
    
    /**
     * 生成要请求给支付宝的参数数组
     */
    function buildRequestPara ($para_temp)
    {
        $para_filter = $this->paraFilter($para_temp);
        
        $para_sort = self::arraySort($para_filter);
        
        $mysign = $this->buildRequestMysign($para_sort);
        
        // 签名结果与签名方式加入请求提交参数组中
        $para_sort['sign'] = $mysign;
        $para_sort['sign_type'] = strtoupper(trim(self::SIGN_TYPE));
        
        return $para_sort;
    }

    /**
     * 除去数组中的空值和签名参数
     *
     * @date 2015-11-13 上午10:24:26
     * 
     * @author Ruesin
     */
    function paraFilter ($para)
    {
        $para_filter = array();
        while (list ($key, $val) = each($para)) {
            if ($key == "sign" || $key == "sign_type" || $val == "")
                continue;
            else
                $para_filter[$key] = $para[$key];
        }
        return $para_filter;
    }
    
    /**
     * 对数组 按照键名升序排序，为数组值保留原来的键。
     * 
     * @date 2015-11-13 上午10:22:11
     * @author Ruesin
     */
    public static function arraySort ($array)
    {
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
    function createLinkstring ($para)
    {
        $arg = "";
        while (list ($key, $val) = each($para)) {
            $arg .= $key . "=" . $val . "&";
        }
        // 去掉最后一个&字符
        $arg = substr($arg, 0, count($arg) - 2);
        
        // 如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }
        
        return $arg;
    }
    
    /**
     * 生成签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    function buildRequestMysign ($para_sort)
    {
        $prestr = $this->createLinkstring($para_sort);
        
        $mysign = "";
        switch (strtoupper(trim(self::SIGN_TYPE))) {
            case "RSA":
                break;
            case "MD5":
                $mysign = md5($prestr . $this->_config['alipay_key']);
                break;
            default:
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
        if (empty($data)) {
            return false;
        } else {
            // 生成签名结果
            $sign = $data['sign'];
            $para_filter = $this->paraFilter($data);
            $para_sort = self::arraySort($para_filter);
            $mysign = $this->buildRequestMysign($para_sort);
            
            if ($mysign != $sign) {
                return false;
            }
            return true;
            // 获取支付宝远程服务器ATN结果（验证是否是支付宝发来的消息）
            $responseTxt = 'true';
            if (! empty($data["notify_id"])) {
                $responseTxt = $this->getResponse($data["notify_id"]);
            }
            // 验证
            // $responsetTxt的结果不是true，与服务器设置问题、合作身份者ID、notify_id一分钟失效有关
            // isSign的结果不是true，与安全校验码、请求时的参数格式（如：带自定义参数等）、编码格式有关
            if (preg_match("/true$/i", $responseTxt)) {
                return true;
            } else {
                return false;
            }
        }
    }
    
    
    /**
     * 远程获取数据，GET模式
     * 
     * @param $url 指定URL完整路径地址
     * @param $cacert_url 指定当前工作目录绝对路径
     * return 远程输出的数据
     */
    function getHttpResponseGET ($url, $cacert_url = '')
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        if ($cacert_url) {
            curl_setopt($curl, CURLOPT_CAINFO, $cacert_url);
        }
        $responseText = curl_exec($curl);
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
        if ($transport == 'https') {
            $veryfy_url = self::HTTPS_URL;
        } else {
            $veryfy_url = self::HTTP_URL;
        }
        
        $veryfy_url = $veryfy_url . "partner=" . $partner . "&notify_id=" . $notify_id;
        
        $responseTxt = $this->getHttpResponseGET($veryfy_url);
        
        return $responseTxt;
    }
    
    /**
     * 被动响应输出值
     */
    function verify_result ($result)
    {
        if ($result) {
            echo 'success';
        } else {
            echo 'fail';
        }
    }

    function _error ($msg, $obj = "")
    {
        $this->error = $msg;
    }

    function get_error ()
    {
        return $this->error;
    }

    /**
     * 获取请求参数
     */
    function _get_notify ()
    {
        if (! empty($_POST)) {
            return $_POST;
        }
        return $_GET;
    }
    
}