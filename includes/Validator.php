<?php
/**
 * 数据验证工具类
 */

class Validator {
    
    /**
     * 验证订单号格式
     */
    public static function validateOrderNo($orderNo) {
        // 订单号长度限制：3-64位
        if (strlen($orderNo) < 3 || strlen($orderNo) > 64) {
            return false;
        }
        
        // 只允许字母、数字、下划线、横线
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $orderNo)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 验证支付方式
     */
    public static function validatePaymentType($paymentType) {
        global $payment_methods;
        
        if (!isset($payment_methods[$paymentType])) {
            throw new Exception('不支持的支付方式');
        }
        
        if (!$payment_methods[$paymentType]['enabled']) {
            throw new Exception('该支付方式已禁用');
        }
        
        return true;
    }
    
    /**
     * 验证金额
     */
    public static function validateAmount($amount) {
        if (!is_numeric($amount) || $amount <= 0) {
            throw new Exception('金额必须大于0');
        }
        
        // 检查金额是否在合理范围内（1分到100万元）
        if ($amount < 1 || $amount > 100000000) {
            throw new Exception('金额超出范围');
        }
        
        return true;
    }
    
    /**
     * 验证货币类型
     */
    public static function validateCurrency($currency) {
        global $payment_config;
        
        $supportedCurrencies = array_keys($payment_config['currency_symbols']);
        
        if (!in_array(strtoupper($currency), $supportedCurrencies)) {
            throw new Exception('不支持的货币类型');
        }
        
        return true;
    }
    
    /**
     * 验证URL格式
     */
    public static function validateUrl($url) {
        if (empty($url)) {
            return false;
        }
        
        // 检查URL格式
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // 只允许HTTP和HTTPS协议
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 清理和过滤输入数据
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleaned[$key] = self::sanitizeInput($value);
            }
            return $cleaned;
        } else {
            // 移除HTML标签
            $cleaned = strip_tags($data);
            
            // 移除多余的空白字符
            $cleaned = trim($cleaned);
            
            // 转义特殊字符
            $cleaned = htmlspecialchars($cleaned, ENT_QUOTES, 'UTF-8');
            
            return $cleaned;
        }
    }
    
    /**
     * 验证创建订单请求
     */
    public static function validateCreateOrderRequest($data) {
        $requiredFields = ['order_no', 'name', 'amount', 'currency', 'notify_url'];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("缺少必要字段: {$field}");
            }
        }
        
        // 验证各个字段
        self::validateOrderNo($data['order_no']);
        self::validateAmount($data['amount']);
        self::validateCurrency($data['currency']);
        
        if (!self::validateUrl($data['notify_url'])) {
            throw new Exception('无效的通知URL');
        }
        
        // 验证商品名称长度
        if (strlen($data['name']) > 100) {
            throw new Exception('商品名称过长');
        }
        
        return true;
    }
    
    /**
     * 验证epay回调数据
     */
    public static function validateEpayNotify($data) {
        $requiredFields = ['out_trade_no', 'trade_status', 'money'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("缺少必要字段: {$field}");
            }
        }
        
        // 验证订单号
        if (!self::validateOrderNo($data['out_trade_no'])) {
            throw new Exception('无效的订单号');
        }
        
        // 验证交易状态
        $validStatuses = ['TRADE_SUCCESS', 'TRADE_CLOSED'];
        if (!in_array($data['trade_status'], $validStatuses)) {
            throw new Exception('无效的交易状态');
        }
        
        // 验证金额
        if (!is_numeric($data['money']) || $data['money'] <= 0) {
            throw new Exception('无效的金额');
        }
        
        return true;
    }
    
    /**
     * 生成CSRF令牌
     */
    public static function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * 验证CSRF令牌
     */
    public static function validateCsrfToken($token) {
        global $security_config;
        
        if (!($security_config['csrf_protection'] ?? true)) {
            return true;
        }
        
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * 检查请求频率限制
     */
    public static function checkRateLimit($identifier, $maxRequests = null, $timeWindow = null) {
        global $security_config;
        
        if (!($security_config['rate_limit']['enabled'] ?? true)) {
            return true;
        }
        
        $maxRequests = $maxRequests ?? ($security_config['rate_limit']['max_requests'] ?? 100);
        $timeWindow = $timeWindow ?? ($security_config['rate_limit']['time_window'] ?? 3600);
        
        $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($identifier) . '.json';
        
        $currentTime = time();
        $requests = [];
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && isset($data['requests'])) {
                $requests = $data['requests'];
            }
        }
        
        // 清理过期的请求记录
        $requests = array_filter($requests, function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });
        
        // 检查是否超过限制
        if (count($requests) >= $maxRequests) {
            return false;
        }
        
        // 添加当前请求
        $requests[] = $currentTime;
        
        // 保存到文件
        file_put_contents($cacheFile, json_encode([
            'requests' => $requests,
            'last_update' => $currentTime
        ]));
        
        return true;
    }
}