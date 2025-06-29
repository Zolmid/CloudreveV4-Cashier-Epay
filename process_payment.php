<?php
/**
 * 支付处理页面
 * 处理用户选择的支付方式并跳转到epay
 */

require_once 'includes/Database.php';
require_once 'includes/Logger.php';

// 检查系统是否已安装
try {
    $db = new Database();
    if (!$db->isInstalled()) {
        header('Location: install.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// 获取配置
$paymentConfig = $db->getConfig('payment_config', []);

// 获取参数
$orderNo = $_GET['order_no'] ?? $_POST['order_no'] ?? '';
$paymentType = $_GET['payment_type'] ?? $_POST['payment_type'] ?? '';

if (empty($orderNo) || empty($paymentType)) {
    http_response_code(400);
    echo '参数错误';
    exit;
}

try {
    // 获取订单信息
    $order = $db->getOrderByNo($orderNo);
    
    if (!$order) {
        throw new Exception('订单不存在');
    }
    
    if ($order['status'] === 'paid') {
        echo '<script>alert("订单已支付"); window.close();</script>';
        exit;
    }
    
    // 验证支付方式
    $paymentMethods = $db->getConfig('payment_methods', []);
    if (!isset($paymentMethods[$paymentType]) || !$paymentMethods[$paymentType]['enabled']) {
        throw new Exception('不支持的支付方式');
    }
    
    // 更新订单状态
    $db->updateOrderStatus($orderNo, 'processing', $paymentType);
    
    // 获取epay配置
    $epayConfig = [
        'apiurl' => $db->getConfig('epay_apiurl'),
        'pid' => $db->getConfig('epay_pid'),
        'key' => $db->getConfig('epay_key')
    ];
    
    // 设置回调地址
    $notifyUrl = rtrim($db->getConfig('cashier_url', ''), '/') . '/epay_notify.php';
    $returnUrl = rtrim($db->getConfig('cashier_url', ''), '/') . '/epay_return.php';
    
    // 使用 EpaySDKManager 处理支付
    require_once 'includes/EpaySDKManager.php';
    $sdkManager = new EpaySDKManager($db);
    
    // 构建订单数据
    $orderData = [
        'payment_type' => $paymentType,
        'order_no' => $orderNo,
        'notify_url' => $notifyUrl,
        'return_url' => $returnUrl,
        'name' => $order['name'],
        'money' => number_format($order['amount'] / 100, $paymentConfig['amount_precision'] ?? 2, '.', ''),
        'sitename' => $db->getConfig('cashier_name', '云盘支付收银台')
    ];
    
    // 生成支付参数
    $params = $sdkManager->buildPaymentParams($orderData);
    
    // 记录支付请求日志
    Logger::info("支付请求", [
        'order_no' => $orderNo,
        'payment_type' => $paymentType,
        'amount' => $order['amount'],
        'sdk_version' => $sdkManager->getSDKVersion(),
        'epay_url' => $sdkManager->getConfig()['apiurl']
    ]);
    
    // 构建跳转URL
    $redirectUrl = $sdkManager->getPaymentUrl($params);
    
    // 跳转到epay支付页面
    header('Location: ' . $redirectUrl);
    exit;
    
} catch (Exception $e) {
    Logger::error("支付处理错误", [
        'error' => $e->getMessage(),
        'order_no' => $orderNo,
        'payment_type' => $paymentType
    ]);
    
    // 获取UI配置用于错误页面样式
    $uiConfig = $db->getConfig('ui_config', []);
    $debugConfig = $db->getConfig('debug_config', []);
    
    // 显示错误页面
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>支付错误</title>
        <link rel="stylesheet" href="assets/css/common.css">
        <style>
            body {
                background: linear-gradient(135deg, <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?> 0%, <?php echo $uiConfig['theme']['secondary_color'] ?? '#764ba2'; ?> 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                padding: 20px;
            }
            
            .error-container {
                background: white;
                border-radius: <?php echo $uiConfig['layout']['border_radius'] ?? '20px'; ?>;
                padding: 40px;
                text-align: center;
                box-shadow: <?php echo $uiConfig['layout']['box_shadow'] ?? '0 20px 40px rgba(0, 0, 0, 0.1)'; ?>;
                max-width: 400px;
                width: 100%;
            }
            
            .error-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            
            .error-title {
                color: <?php echo $uiConfig['theme']['danger_color'] ?? '#dc3545'; ?>;
                font-size: 24px;
                font-weight: 600;
                margin-bottom: 15px;
            }
            
            .error-message {
                color: #6c757d;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            
            .back-button {
                background: linear-gradient(135deg, <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?> 0%, <?php echo $uiConfig['theme']['secondary_color'] ?? '#764ba2'; ?> 100%);
                border-radius: 12px;
                padding: 12px 24px;
                font-size: 16px;
                text-decoration: none;
                display: inline-block;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">❌</div>
            <div class="error-title">支付处理失败</div>
            <div class="error-message">
                <?php 
                if ($debugConfig['show_errors'] ?? false) {
                    echo htmlspecialchars($e->getMessage());
                } else {
                    echo '支付处理错误，请稍后重试';
                }
                ?>
            </div>
            <a href="checkout.php?order_no=<?php echo urlencode($orderNo); ?>" class="back-button">
                返回重试
            </a>
        </div>
    </body>
    </html>
    <?php
}