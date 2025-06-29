<?php
/**
 * 收银台页面
 * 用户选择支付方式并完成支付
 */

require_once 'includes/Database.php';
require_once 'includes/Logger.php';
require_once 'includes/UserAgent.php';

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
$paymentMethods = $db->getConfig('payment_methods', []);
$paymentCompatibility = $db->getConfig('payment_compatibility', []);
$environmentRecommendations = $db->getConfig('environment_recommendations', []);
$autoRedirectConfig = $db->getConfig('auto_redirect_config', []);
$uiConfig = $db->getConfig('ui_config', []);
$paymentConfig = $db->getConfig('payment_config', []);

// 获取订单号
$orderNo = $_GET['order_no'] ?? '';

if (empty($orderNo)) {
    http_response_code(400);
    echo '订单号不能为空';
    exit;
}

// 获取订单信息
try {
    $order = $db->getOrderByNo($orderNo);
    
    if (!$order) {
        http_response_code(404);
        echo '订单不存在';
        exit;
    }
    
    if ($order['status'] === 'paid') {
        echo '<script>alert("订单已支付"); window.close();</script>';
        exit;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo '系统错误';
    exit;
}

// 检测用户环境
$environment = UserAgent::detectEnvironment();

// 处理自动跳转
if ($autoRedirectConfig['enabled'] && isset($autoRedirectConfig['environments'][$environment])) {
    $autoPaymentType = $autoRedirectConfig['environments'][$environment];
    
    if (isset($paymentMethods[$autoPaymentType]) && $paymentMethods[$autoPaymentType]['enabled']) {
        // 自动跳转到对应支付方式
        $epayConfig = [
            'apiurl' => $db->getConfig('epay_apiurl'),
            'pid' => $db->getConfig('epay_pid'),
            'key' => $db->getConfig('epay_key')
        ];
        
        $notifyUrl = rtrim($db->getConfig('cashier_url', ''), '/') . '/epay_notify.php';
        $returnUrl = rtrim($db->getConfig('cashier_url', ''), '/') . '/epay_return.php';
        
        // 自动跳转到支付处理页面
        $redirectUrl = 'process_payment.php?' . http_build_query([
            'order_no' => $orderNo,
            'payment_type' => $autoPaymentType
        ]);
        
        Logger::info("自动跳转支付", [
            'order_no' => $orderNo,
            'environment' => $environment,
            'payment_type' => $autoPaymentType
        ]);
        
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// 获取推荐支付方式
$recommendedPayment = $environmentRecommendations[$environment] ?? 'alipay';

// 获取所有启用的支付方式（不再严格限制环境兼容性）
$availablePayments = [];
foreach ($paymentMethods as $type => $method) {
    if ($method['enabled']) {
        $availablePayments[$type] = $method;
        
        // 为非兼容环境的支付方式添加警告标识
        $compatible = $paymentCompatibility[$type] ?? [];
        if (!in_array($environment, $compatible) && !in_array('desktop', $compatible)) {
            $availablePayments[$type]['warning'] = true;
        }
    }
}

// 如果没有可用支付方式
if (empty($availablePayments)) {
    $error = '当前环境不支持任何支付方式';
    $showPaymentMethods = false;
} else {
    $showPaymentMethods = true;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $db->getConfig('cashier_name', '云盘支付收银台'); ?></title>
    <link rel="stylesheet" href="assets/css/common.css">
    <style>
        body {
            background: linear-gradient(135deg, <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?> 0%, <?php echo $uiConfig['theme']['secondary_color'] ?? '#764ba2'; ?> 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .checkout-container {
            background: white;
            border-radius: <?php echo $uiConfig['layout']['border_radius'] ?? '20px'; ?>;
            padding: 40px;
            box-shadow: <?php echo $uiConfig['layout']['box_shadow'] ?? '0 20px 40px rgba(0, 0, 0, 0.1)'; ?>;
            max-width: <?php echo $uiConfig['layout']['max_width'] ?? '500px'; ?>;
            width: 100%;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .title {
            font-size: 24px;
            font-weight: 600;
            color: #212529;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #6c757d;
            font-size: 16px;
        }
        
        .order-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .order-item:last-child {
            margin-bottom: 0;
        }
        
        .order-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .order-value {
            color: #212529;
            font-weight: 600;
        }
        
        .amount {
            font-size: 24px;
            color: <?php echo $uiConfig['theme']['success_color'] ?? '#28a745'; ?>;
        }
        
        .payment-methods {
            margin-bottom: 30px;
        }
        
        .payment-title {
            font-size: 18px;
            font-weight: 600;
            color: #212529;
            margin-bottom: 20px;
        }
        
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(<?php echo $uiConfig['payment_grid']['columns'] ?? 2; ?>, 1fr);
            gap: 15px;
        }
        
        .payment-method {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .payment-method:hover {
            border-color: <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?>;
            transform: translateY(-2px);
        }
        
        .payment-method.recommended {
            border-color: <?php echo $uiConfig['theme']['success_color'] ?? '#28a745'; ?>;
            background: #f8fff9;
        }
        
        .payment-method.recommended::before {
            content: '推荐';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: <?php echo $uiConfig['theme']['success_color'] ?? '#28a745'; ?>;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .payment-method.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .payment-method.disabled:hover {
            border-color: #e9ecef;
            transform: none;
        }
        
        .payment-method.warning {
            border-color: #ffc107;
            background: #fffbf0;
        }
        
        .payment-method.warning:hover {
            border-color: #ff9800;
        }
        
        .payment-method.selected {
            border-color: <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?>;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
        }
        
        .payment-method.selected::after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            background: <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?>;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
        }
        
        .payment-icon {
            font-size: 32px;
            margin-bottom: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            min-height: 32px;
        }
        
        .payment-icon svg {
            width: 32px;
            height: 32px;
            fill: currentColor;
        }
        
        .payment-name {
            font-weight: 600;
            color: #212529;
            margin-bottom: 5px;
        }
        
        .payment-description {
            font-size: 12px;
            color: #6c757d;
        }
        
        .pay-btn {
            width: 100%;
            background: linear-gradient(135deg, <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?> 0%, <?php echo $uiConfig['theme']['secondary_color'] ?? '#764ba2'; ?> 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }
        
        .pay-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .pay-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .pay-btn:hover:not(:disabled)::before {
            left: 100%;
        }
        
        .pay-btn:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .pay-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .environment-info {
            background: #e7f3ff;
            color: #004085;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #b3d9ff;
        }
        
        @media (max-width: 768px) {
            .payment-grid {
                grid-template-columns: <?php echo $uiConfig['payment_grid']['mobile_columns'] ?? 1; ?>fr;
            }
            
            .checkout-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="header">
            <div class="title"><?php echo $db->getConfig('cashier_name', '云盘支付收银台'); ?></div>
            <div class="subtitle">请选择支付方式完成支付</div>
        </div>
        
        <div class="order-info">
            <div class="order-item">
                <span class="order-label">商品名称：</span>
                <span class="order-value"><?php echo htmlspecialchars($order['name']); ?></span>
            </div>
            <div class="order-item">
                <span class="order-label">订单号：</span>
                <span class="order-value"><?php echo htmlspecialchars($order['order_no']); ?></span>
            </div>
            <div class="order-item">
                <span class="order-label">支付金额：</span>
                <span class="order-value amount">
                    <?php 
                    $currencySymbol = $paymentConfig['currency_symbols'][$order['currency']] ?? '¥';
                    echo $currencySymbol . number_format($order['amount'] / 100, $paymentConfig['amount_precision'] ?? 2); 
                    ?>
                </span>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="environment-info">
            检测到您正在使用：<?php echo UserAgent::getEnvironmentDescription(); ?>
        </div>
        
        <?php if ($showPaymentMethods): ?>
            <div class="payment-methods">
                <div class="payment-title">选择支付方式</div>
                <div class="payment-grid">
                    <?php foreach ($availablePayments as $type => $method): ?>
                        <div class="payment-method <?php echo $type === $recommendedPayment ? 'recommended' : ''; ?><?php echo isset($method['warning']) ? ' warning' : ''; ?>" 
                             onclick="selectPayment('<?php echo $type; ?>')">
                            <div class="payment-icon"><?php echo $method['icon']; ?></div>
                            <div class="payment-name"><?php echo htmlspecialchars($method['name']); ?></div>
                            <div class="payment-description">
                                <?php echo htmlspecialchars($method['description']); ?>
                                <?php if (isset($method['warning'])): ?>
                                    <br><small style="color: #ff9800;">⚠️ 当前环境可能不支持此支付方式</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button class="pay-btn" onclick="processPayment()" id="payBtn" disabled>
                请选择支付方式
            </button>
        <?php else: ?>
            <div class="error-message">
                当前环境不支持任何支付方式。<br>
                支持的支付方式：<?php echo implode('、', array_map(function($method) { return $method['name']; }, array_filter($paymentMethods, function($method) { return $method['enabled']; }))); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        let selectedPayment = '';
        
        function selectPayment(type) {
            selectedPayment = type;
            
            // 移除所有选中状态
            document.querySelectorAll('.payment-method').forEach(method => {
                method.classList.remove('selected');
            });
            
            // 添加选中状态
            event.currentTarget.classList.add('selected');
            
            // 更新按钮
            const payBtn = document.getElementById('payBtn');
            payBtn.textContent = '立即支付';
            payBtn.disabled = false;
        }
        
        function selectPaymentByType(type) {
            const paymentMethod = document.querySelector(`[onclick="selectPayment('${type}')"]`);
            if (paymentMethod) {
                selectedPayment = type;
                
                // 移除所有选中状态
                document.querySelectorAll('.payment-method').forEach(method => {
                    method.classList.remove('selected');
                });
                
                // 添加选中状态
                paymentMethod.classList.add('selected');
                
                // 更新按钮
                const payBtn = document.getElementById('payBtn');
                payBtn.textContent = '立即支付';
                payBtn.disabled = false;
                
                return true;
            }
            return false;
        }
        
        function processPayment() {
            if (!selectedPayment) {
                alert('请选择支付方式');
                return;
            }
            
            // 构建支付参数
            const params = new URLSearchParams({
                order_no: '<?php echo $order['order_no']; ?>',
                payment_type: selectedPayment
            });
            
            // 跳转到支付处理页面
            window.location.href = 'process_payment.php?' + params.toString();
        }
        
        // 页面加载完成后自动选择推荐支付方式
        document.addEventListener('DOMContentLoaded', function() {
            const recommendedPayment = '<?php echo $recommendedPayment; ?>';
            
            // 首先尝试选择推荐支付方式
            if (!selectPaymentByType(recommendedPayment)) {
                // 如果推荐支付方式不可用，选择第一个可用的支付方式
                const firstPaymentMethod = document.querySelector('.payment-method');
                if (firstPaymentMethod) {
                    const onclick = firstPaymentMethod.getAttribute('onclick');
                    const match = onclick.match(/selectPayment\('([^']+)'\)/);
                    if (match) {
                        selectPaymentByType(match[1]);
                    }
                }
            }
        });
    </script>
</body>
</html>