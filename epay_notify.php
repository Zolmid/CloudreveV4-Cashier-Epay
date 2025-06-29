<?php
/**
 * epay异步通知处理
 * 处理epay的支付结果通知
 */

require_once 'includes/Database.php';
require_once 'includes/Logger.php';

// 检查系统是否已安装
try {
    $db = new Database();
    if (!$db->isInstalled()) {
        exit('fail');
    }
} catch (Exception $e) {
    exit('fail');
}

// 使用 EpaySDKManager 处理回调验证
require_once 'includes/EpaySDKManager.php';
$sdkManager = new EpaySDKManager($db);

try {
    // 获取通知参数（支持GET和POST）
    $params = array_merge($_GET, $_POST);
    
    if (empty($params)) {
        throw new Exception('无通知参数');
    }
    
    // 记录请求方法和原始参数
    Logger::info("epay通知请求详情", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'get_params' => $_GET,
        'post_params' => $_POST,
        'merged_params' => $params
    ]);
    
    // 记录通知日志
    Logger::info("epay通知接收", [
        'params' => $params,
        'sdk_version' => $sdkManager->getSDKVersion(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // 验证回调签名
    if (!$sdkManager->verifyCallback($params)) {
        Logger::error("epay签名验证失败", [
            'params' => $params,
            'sdk_version' => $sdkManager->getSDKVersion(),
            'expected_sign_type' => $params['sign_type'] ?? 'unknown',
            'received_sign' => $params['sign'] ?? 'missing'
        ]);
        throw new Exception('签名验证失败');
    }
    
    Logger::info("epay签名验证成功", [
        'order_no' => $params['out_trade_no'] ?? 'unknown',
        'trade_status' => $params['trade_status'] ?? 'unknown'
    ]);
    

    
    // 获取订单信息
    $orderNo = $params['out_trade_no'] ?? '';
    $tradeStatus = $params['trade_status'] ?? '';
    $tradeNo = $params['trade_no'] ?? '';
    $money = $params['money'] ?? '';
    
    if (empty($orderNo)) {
        throw new Exception('订单号为空');
    }
    
    // 获取订单
    $order = $db->getOrderByNo($orderNo);
    
    if (!$order) {
        throw new Exception('订单不存在: ' . $orderNo);
    }
    
    // 验证金额
    $expectedAmount = $order['amount'] / 100; // 转换为元
    if (abs($money - $expectedAmount) > 0.01) {
        throw new Exception('金额不匹配: 期望' . $expectedAmount . ', 实际' . $money);
    }
    
    // 处理支付状态
    if ($tradeStatus === 'TRADE_SUCCESS') {
        // 支付成功
        if ($order['status'] !== 'paid') {
            // 更新订单状态
            $db->markOrderAsPaid($orderNo);
            
            // 记录支付成功日志
            Logger::info("支付成功", [
                'order_no' => $orderNo,
                'trade_no' => $tradeNo,
                'amount' => $money,
                'payment_type' => $order['payment_type']
            ]);
            
            // 通知Cloudreve
            $notifyResult = notifyCloudreve($order);
            
            Logger::info("Cloudreve通知结果", [
                'order_no' => $orderNo,
                'result' => $notifyResult
            ]);
        }
        
        echo 'success';
    } else {
        // 支付失败或其他状态
        Logger::warning("支付状态异常", [
            'order_no' => $orderNo,
            'trade_status' => $tradeStatus,
            'trade_no' => $tradeNo
        ]);
        
        echo 'success'; // 仍然返回success，避免epay重复通知
    }
    
} catch (Exception $e) {
    Logger::error("epay通知处理错误", [
        'error' => $e->getMessage(),
        'params' => $params ?? [],
        'get_params' => $_GET ?? [],
        'post_params' => $_POST ?? [],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ]);
    
    echo 'fail';
}

/**
 * 通知Cloudreve支付结果（带重试机制）
 */
function notifyCloudreve($order) {
    global $db;
    $paymentConfig = $db->getConfig('payment_config', []);
    $maxRetries = $paymentConfig['retry_times'] ?? 3;
    $baseInterval = $paymentConfig['retry_interval'] ?? 5;
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $result = sendNotifyRequest($order, $attempt);
        
        if ($result['success']) {
            // 检查Cloudreve响应
            $responseData = json_decode($result['response'], true);
            if ($responseData && isset($responseData['code'])) {
                if ($responseData['code'] === 0) {
                    Logger::info("Cloudreve通知成功", [
                        'order_no' => $order['order_no'],
                        'attempt' => $attempt
                    ]);
                    return $result;
                } else {
                    // Cloudreve明确返回错误，不再重试
                    Logger::error("Cloudreve回调处理失败", [
                        'order_no' => $order['order_no'],
                        'error_code' => $responseData['code'],
                        'error_message' => $responseData['error'] ?? 'Unknown error'
                    ]);
                    return [
                        'success' => false,
                        'error' => 'Cloudreve处理失败: ' . ($responseData['error'] ?? 'Unknown error')
                    ];
                }
            }
        }
        
        // 如果不是最后一次尝试，等待后重试
        if ($attempt < $maxRetries) {
            $waitTime = $baseInterval * pow(2, $attempt - 1); // 指数后退
            Logger::warning("Cloudreve通知失败，{$waitTime}秒后重试", [
                'order_no' => $order['order_no'],
                'attempt' => $attempt,
                'max_retries' => $maxRetries,
                'error' => $result['error'] ?? 'Unknown error'
            ]);
            sleep($waitTime);
        }
    }
    
    // 所有重试都失败
    Logger::error("Cloudreve通知最终失败", [
        'order_no' => $order['order_no'],
        'total_attempts' => $maxRetries
    ]);
    
    return [
        'success' => false,
        'error' => '通知失败，已达到最大重试次数'
    ];
}

/**
 * 发送单次通知请求
 */
function sendNotifyRequest($order, $attempt = 1) {
    try {
        $notifyUrl = $order['notify_url'];
        
        if (empty($notifyUrl)) {
            throw new Exception('通知URL为空');
        }
        
        // 准备通知数据
        $notifyData = [
            'order_no' => $order['order_no'],
            'status' => 'PAID',
            'amount' => $order['amount'] / 100,
            'currency' => $order['currency'],
            'payment_type' => $order['payment_type'],
            'paid_at' => $order['paid_at']
        ];
        
        // 发送GET请求（按照Cloudreve V4规范）
        $queryParams = http_build_query($notifyData);
        $getUrl = $notifyUrl . (strpos($notifyUrl, '?') !== false ? '&' : '?') . $queryParams;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Cloudreve-Payment-Cashier/1.0'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL错误: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('HTTP状态码错误: ' . $httpCode);
        }
        
        return [
            'success' => true,
            'response' => $response,
            'http_code' => $httpCode
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}