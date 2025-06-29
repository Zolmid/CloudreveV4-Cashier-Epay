<?php
/**
 * 用户代理检测工具类
 */

class UserAgent {
    
    /**
     * 检测是否为微信应用内访问
     */
    public static function isWeChat() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return strpos($userAgent, 'MicroMessenger') !== false;
    }
    
    /**
     * 检测是否为支付宝应用内访问
     */
    public static function isAlipay() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return strpos($userAgent, 'AliApp') !== false;
    }
    
    /**
     * 检测是否为QQ应用内访问
     */
    public static function isQQ() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return strpos($userAgent, 'QQ/') !== false || strpos($userAgent, 'MQQBrowser') !== false;
    }
    
    /**
     * 检测是否为移动设备
     */
    public static function isMobile() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $mobileKeywords = [
            'Mobile', 'Android', 'iPhone', 'iPad', 'Windows Phone',
            'BlackBerry', 'Opera Mini', 'IEMobile'
        ];
        
        foreach ($mobileKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 检测是否为iOS设备
     */
    public static function isIOS() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false;
    }
    
    /**
     * 检测是否为Android设备
     */
    public static function isAndroid() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return strpos($userAgent, 'Android') !== false;
    }
    
    /**
     * 获取用户环境类型
     */
    public static function getEnvironment() {
        return self::detectEnvironment();
    }
    
    /**
     * 检测用户环境类型
     */
    public static function detectEnvironment() {
        if (self::isWeChat()) {
            return 'wechat';
        } elseif (self::isAlipay()) {
            return 'alipay';
        } elseif (self::isQQ()) {
            return 'qq';
        } elseif (self::isMobile()) {
            return 'mobile';
        } else {
            return 'desktop';
        }
    }
    
    /**
     * 获取环境描述
     */
    public static function getEnvironmentDescription() {
        global $environment_descriptions;
        
        if (!isset($environment_descriptions)) {
            $environment_descriptions = [
                'wechat' => '微信应用内',
                'alipay' => '支付宝应用内',
                'qq' => 'QQ应用内',
                'mobile' => '移动浏览器',
                'desktop' => '桌面浏览器'
            ];
        }
        
        $env = self::getEnvironment();
        return $environment_descriptions[$env] ?? '未知环境';
    }
    
    /**
     * 获取完整的用户代理字符串
     */
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * 检测是否支持特定支付方式
     */
    public static function supportsPaymentMethod($method, $paymentMethods) {
        global $payment_compatibility;
        
        // 检查支付方式是否启用
        if (!isset($paymentMethods[$method]) || !$paymentMethods[$method]['enabled']) {
            return false;
        }
        
        $env = self::getEnvironment();
        
        // 使用配置文件中的兼容性设置
        if (isset($payment_compatibility[$method])) {
            return in_array($env, $payment_compatibility[$method]);
        }
        
        // 如果没有配置，默认所有环境都支持
        return true;
    }
    
    /**
     * 获取推荐支付方式
     */
    public static function getRecommendedPaymentMethod($paymentMethods) {
        global $environment_recommendations;
        
        $env = self::getEnvironment();
        
        // 使用配置文件中的推荐设置
        if (isset($environment_recommendations[$env])) {
            $recommended = $environment_recommendations[$env];
            
            // 检查推荐的支付方式是否可用
            if (self::supportsPaymentMethod($recommended, $paymentMethods)) {
                return $recommended;
            }
        }
        
        // 如果没有配置或推荐的方式不可用，返回第一个可用的支付方式
        foreach ($paymentMethods as $method => $config) {
            if (self::supportsPaymentMethod($method, $paymentMethods)) {
                return $method;
            }
        }
        
        return null;
    }
    
    /**
     * 获取可用的支付方式列表
     */
    public static function getAvailablePaymentMethods($paymentMethods) {
        $available = [];
        
        foreach ($paymentMethods as $method => $config) {
            if (self::supportsPaymentMethod($method, $paymentMethods)) {
                $available[$method] = $config;
            }
        }
        
        return $available;
    }
    
    /**
     * 检查是否应该自动跳转
     */
    public static function shouldAutoRedirect($availableMethods) {
        global $auto_redirect_config;
        
        // 检查是否启用自动跳转
        if (!isset($auto_redirect_config['enabled']) || !$auto_redirect_config['enabled']) {
            return false;
        }
        
        $env = self::getEnvironment();
        
        // 检查当前环境是否配置了自动跳转
        if (isset($auto_redirect_config['environments'][$env])) {
            $redirectMethod = $auto_redirect_config['environments'][$env];
            
            // 检查跳转的支付方式是否可用
            return isset($availableMethods[$redirectMethod]);
        }
        
        return false;
    }
    
    /**
     * 获取自动跳转的支付方式
     */
    public static function getAutoRedirectMethod() {
        global $auto_redirect_config;
        
        $env = self::getEnvironment();
        
        if (isset($auto_redirect_config['environments'][$env])) {
            return $auto_redirect_config['environments'][$env];
        }
        
        return null;
    }
}