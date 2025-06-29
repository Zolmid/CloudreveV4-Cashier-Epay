<?php
/**
 * Epay SDK 管理器
 * 支持 SDK1.0 (MD5签名) 和 SDK2.0 (RSA签名) 的兼容层
 */

require_once 'Logger.php';

class EpaySDKManager {
    private $db;
    private $sdkVersion;
    private $config;
    
    public function __construct(Database $db) {
        $this->db = $db;
        $this->detectSDKVersion();
        $this->loadConfig();
    }
    
    /**
     * 检测 SDK 版本
     */
    private function detectSDKVersion() {
        $sdkVersion = $this->db->getConfig('epay_sdk_version', '1.0');
        $this->sdkVersion = $sdkVersion;
        
        Logger::debug('检测到 SDK 版本', ['version' => $this->sdkVersion]);
    }
    
    /**
     * 加载配置
     */
    private function loadConfig() {
        if ($this->sdkVersion === '2.0') {
            $this->config = [
                'apiurl' => $this->db->getConfig('epay_apiurl'),
                'pid' => $this->db->getConfig('epay_pid'),
                'platform_public_key' => $this->db->getConfig('epay_platform_public_key', ''),
                'merchant_private_key' => $this->db->getConfig('epay_merchant_private_key', '')
            ];
        } else {
            // SDK 1.0 配置
            $this->config = [
                'apiurl' => $this->db->getConfig('epay_apiurl'),
                'pid' => $this->db->getConfig('epay_pid'),
                'key' => $this->db->getConfig('epay_key')
            ];
        }
    }
    
    /**
     * 获取 SDK 版本
     */
    public function getSDKVersion() {
        return $this->sdkVersion;
    }
    
    /**
     * 获取配置
     */
    public function getConfig() {
        return $this->config;
    }
    
    /**
     * 生成支付参数
     */
    public function buildPaymentParams($orderData) {
        $params = [
            'pid' => $this->config['pid'],
            'type' => $orderData['payment_type'],
            'out_trade_no' => $orderData['order_no'],
            'notify_url' => $orderData['notify_url'],
            'return_url' => $orderData['return_url'],
            'name' => $orderData['name'],
            'money' => $orderData['money'],
            'sitename' => $orderData['sitename'] ?? ''
        ];
        
        if ($this->sdkVersion === '2.0') {
            return $this->buildSDK2Params($params);
        } else {
            return $this->buildSDK1Params($params);
        }
    }
    
    /**
     * 构建 SDK1.0 参数 (MD5签名)
     */
    private function buildSDK1Params($params) {
        ksort($params);
        $signStr = '';
        foreach ($params as $key => $value) {
            $signStr .= $key . '=' . $value . '&';
        }
        $signStr = rtrim($signStr, '&');
        $params['sign'] = md5($signStr . $this->config['key']);
        $params['sign_type'] = 'MD5';
        
        return $params;
    }
    
    /**
     * 构建 SDK2.0 参数 (RSA签名)
     */
    private function buildSDK2Params($params) {
        $params['timestamp'] = time() . '';
        $signContent = $this->getSignContent($params);
        $params['sign'] = $this->rsaPrivateSign($signContent);
        $params['sign_type'] = 'RSA';
        
        return $params;
    }
    
    /**
     * 获取支付URL
     */
    public function getPaymentUrl($params) {
        if ($this->sdkVersion === '2.0') {
            return $this->config['apiurl'] . 'api/pay/submit?' . http_build_query($params);
        } else {
            return $this->config['apiurl'] . 'submit.php?' . http_build_query($params);
        }
    }
    
    /**
     * 验证回调签名
     */
    public function verifyCallback($params) {
        if ($this->sdkVersion === '2.0') {
            return $this->verifySDK2Callback($params);
        } else {
            return $this->verifySDK1Callback($params);
        }
    }
    
    /**
     * 验证 SDK1.0 回调 (MD5签名)
     */
    private function verifySDK1Callback($params) {
        $sign = $params['sign'] ?? '';
        $signType = $params['sign_type'] ?? '';
        
        if (empty($sign) || $signType !== 'MD5') {
            return false;
        }
        
        // 移除签名参数
        $verifyParams = $params;
        unset($verifyParams['sign'], $verifyParams['sign_type']);
        
        // 按参数名排序
        ksort($verifyParams);
        
        // 构建签名字符串
        $signStr = '';
        foreach ($verifyParams as $key => $value) {
            $signStr .= $key . '=' . $value . '&';
        }
        $signStr = rtrim($signStr, '&');
        
        // 计算签名
        $calculatedSign = md5($signStr . $this->config['key']);
        
        return $sign === $calculatedSign;
    }
    
    /**
     * 验证 SDK2.0 回调 (RSA签名)
     */
    private function verifySDK2Callback($params) {
        if (empty($params) || empty($params['sign'])) {
            return false;
        }
        
        if (empty($params['timestamp']) || abs(time() - $params['timestamp']) > 300) {
            return false;
        }
        
        $sign = $params['sign'];
        $signContent = $this->getSignContent($params);
        
        return $this->rsaPublicVerify($signContent, $sign);
    }
    
    /**
     * 获取待签名字符串
     */
    private function getSignContent($params) {
        ksort($params);
        $signstr = '';
        foreach ($params as $k => $v) {
            if (is_array($v) || $this->isEmpty($v) || $k == 'sign' || $k == 'sign_type') {
                continue;
            }
            $signstr .= '&' . $k . '=' . $v;
        }
        return substr($signstr, 1);
    }
    
    /**
     * 检查值是否为空
     */
    private function isEmpty($value) {
        return $value === null || trim($value) === '';
    }
    
    /**
     * RSA 私钥签名
     */
    private function rsaPrivateSign($data) {
        $key = "-----BEGIN PRIVATE KEY-----\n" .
            wordwrap($this->config['merchant_private_key'], 64, "\n", true) .
            "\n-----END PRIVATE KEY-----";
        $privatekey = openssl_get_privatekey($key);
        if (!$privatekey) {
            throw new Exception('签名失败，商户私钥错误');
        }
        openssl_sign($data, $sign, $privatekey, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }
    
    /**
     * RSA 公钥验签
     */
    private function rsaPublicVerify($data, $sign) {
        $key = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($this->config['platform_public_key'], 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        $publickey = openssl_get_publickey($key);
        if (!$publickey) {
            throw new Exception('验签失败，平台公钥错误');
        }
        $result = openssl_verify($data, base64_decode($sign), $publickey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }
    
    /**
     * 查询订单状态 (仅 SDK2.0 支持)
     */
    public function queryOrder($tradeNo) {
        if ($this->sdkVersion !== '2.0') {
            throw new Exception('订单查询功能仅在 SDK2.0 中支持');
        }
        
        $params = [
            'trade_no' => $tradeNo,
        ];
        
        return $this->executeAPI('api/pay/query', $params);
    }
    
    /**
     * 执行 API 请求 (仅 SDK2.0)
     */
    private function executeAPI($path, $params) {
        $path = ltrim($path, '/');
        $requrl = $this->config['apiurl'] . $path;
        $requestParams = $this->buildSDK2Params($params);
        
        $response = $this->getHttpResponse($requrl, http_build_query($requestParams));
        $arr = json_decode($response, true);
        
        if ($arr && $arr['code'] == 0) {
            if (!$this->verifySDK2Callback($arr)) {
                throw new Exception('返回数据验签失败');
            }
            return $arr;
        } else {
            throw new Exception($arr ? $arr['msg'] : '请求失败');
        }
    }
    
    /**
     * HTTP 请求
     */
    private function getHttpResponse($url, $post = false, $timeout = 10) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $httpheader[] = "Accept: */*";
        $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
        $httpheader[] = "Connection: close";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}