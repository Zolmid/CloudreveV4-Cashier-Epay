<?php
/**
 * epay同步返回处理
 * 处理用户支付完成后的页面跳转
 */

require_once 'includes/Database.php';
require_once 'includes/Logger.php';

// 检查系统是否已安装
try {
    $db = new Database();
    if (!$db->isInstalled()) {
        echo '系统未安装';
        exit;
    }
} catch (Exception $e) {
    echo '系统错误';
    exit;
}

// 使用 EpaySDKManager 处理回调验证
require_once 'includes/EpaySDKManager.php';
$sdkManager = new EpaySDKManager($db);

// 获取UI配置
$uiConfig = $db->getConfig('ui_config', []);

try {
    // 获取返回参数
    $params = $_GET;
    
    if (empty($params)) {
        throw new Exception('无返回参数');
    }
    
    // 记录返回日志
    Logger::info("epay返回接收", [
        'params' => $params,
        'sdk_version' => $sdkManager->getSDKVersion(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // 验证回调签名
    if (!$sdkManager->verifyCallback($params)) {
        throw new Exception('签名验证失败');
    }
    
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
        throw new Exception('订单不存在');
    }
    
    // 验证金额
    $expectedAmount = $order['amount'] / 100; // 转换为元
    if (abs($money - $expectedAmount) > 0.01) {
        throw new Exception('金额不匹配');
    }
    
    // 判断支付状态
    $isSuccess = ($tradeStatus === 'TRADE_SUCCESS');
    $orderStatus = $order['status'];
    
    // 记录返回日志
    Logger::info("epay返回处理", [
        'order_no' => $orderNo,
        'trade_status' => $tradeStatus,
        'order_status' => $orderStatus,
        'is_success' => $isSuccess
    ]);
    
} catch (Exception $e) {
    Logger::error("epay返回处理错误", [
        'error' => $e->getMessage(),
        'params' => $_GET ?? [],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $error = $e->getMessage();
    $isSuccess = false;
    $orderNo = $_GET['out_trade_no'] ?? 'unknown';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付结果 - <?php echo $db->getConfig('cashier_name', '云盘支付收银台'); ?></title>
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
        
        .result-container {
            background: white;
            border-radius: <?php echo $uiConfig['layout']['border_radius'] ?? '20px'; ?>;
            padding: 40px;
            text-align: center;
            box-shadow: <?php echo $uiConfig['layout']['box_shadow'] ?? '0 20px 40px rgba(0, 0, 0, 0.1)'; ?>;
            max-width: 500px;
            width: 100%;
        }
        
        .result-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .result-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .result-message {
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .order-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
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
        
        .success .result-icon {
            color: <?php echo $uiConfig['theme']['success_color'] ?? '#28a745'; ?>;
        }
        
        .success .result-title {
            color: <?php echo $uiConfig['theme']['success_color'] ?? '#28a745'; ?>;
        }
        
        .error .result-icon {
            color: <?php echo $uiConfig['theme']['danger_color'] ?? '#dc3545'; ?>;
        }
        
        .error .result-title {
            color: <?php echo $uiConfig['theme']['danger_color'] ?? '#dc3545'; ?>;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?> 0%, <?php echo $uiConfig['theme']['secondary_color'] ?? '#764ba2'; ?> 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .auto-close {
            margin-top: 20px;
            color: #6c757d;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .result-container {
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="result-container <?php echo $isSuccess ? 'success' : 'error'; ?>">
        <div class="result-icon">
            <?php echo $isSuccess ? '✅' : '❌'; ?>
        </div>
        
        <div class="result-title">
            <?php echo $isSuccess ? '支付成功' : '支付失败'; ?>
        </div>
        
        <div class="result-message">
            <?php if ($isSuccess): ?>
                您的订单已支付成功，感谢您的购买！
            <?php else: ?>
                <?php echo isset($error) ? htmlspecialchars($error) : '支付过程中出现错误，请稍后重试'; ?>
            <?php endif; ?>
        </div>
        
        <?php if (isset($order) && $order): ?>
            <div class="order-info">
                <div class="order-item">
                    <span class="order-label">订单号：</span>
                    <span class="order-value"><?php echo htmlspecialchars($order['order_no']); ?></span>
                </div>
                <div class="order-item">
                    <span class="order-label">商品名称：</span>
                    <span class="order-value"><?php echo htmlspecialchars($order['name']); ?></span>
                </div>
                <div class="order-item">
                    <span class="order-label">支付金额：</span>
                    <span class="order-value">
                        <?php 
                        $paymentConfig = $db->getConfig('payment_config', []);
                        $currencySymbol = $paymentConfig['currency_symbols'][$order['currency']] ?? '¥';
                        echo $currencySymbol . number_format($order['amount'] / 100, $paymentConfig['amount_precision'] ?? 2); 
                        ?>
                    </span>
                </div>
                <?php if ($isSuccess && isset($order['paid_at']) && $order['paid_at']): ?>
                    <div class="order-item">
                        <span class="order-label">支付时间：</span>
                        <span class="order-value"><?php echo date('Y-m-d H:i:s', strtotime($order['paid_at'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <?php if ($isSuccess): ?>
                <button class="btn btn-primary" data-action="close">关闭页面</button>
            <?php else: ?>
                <a href="checkout.php?order_no=<?php echo urlencode($orderNo); ?>" class="btn btn-primary">重新支付</a>
                <button class="btn btn-secondary" data-action="close">关闭页面</button>
            <?php endif; ?>
        </div>
        
        <div class="auto-close">
            页面将在 <span id="countdown">10</span> 秒后自动关闭
        </div>
    </div>
    
    <script>
        // 自动关闭倒计时
        let countdown = 10;
        let autoCloseAttempted = false;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            
            if (countdown <= 0) {
                clearInterval(timer);
                autoCloseAttempted = true;
                
                // 尝试自动关闭
                if (window.opener || window.parent !== window) {
                    try {
                        window.close();
                        return;
                    } catch (e) {
                        console.log('自动关闭失败:', e.message);
                    }
                }
                
                // 无法自动关闭，显示提示
                if (countdownElement) {
                    countdownElement.parentElement.innerHTML = '无法自动关闭页面，请手动关闭';
                }
                showCloseHint();
            }
        }, 1000);
        
        // 统一的关闭窗口函数
        function closeWindow() {
            // 检查是否为弹出窗口或iframe
            if (window.opener || window.parent !== window) {
                try {
                    window.close();
                    return;
                } catch (e) {
                    console.log('无法关闭窗口:', e.message);
                }
            }
            
            // 显示用户提示
            showCloseHint();
        }
        
        // 显示关闭提示
        function showCloseHint() {
            const hintDiv = document.createElement('div');
            hintDiv.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: #ff6b6b;
                color: white;
                padding: 15px 25px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                font-size: 14px;
                text-align: center;
                animation: slideDown 0.3s ease;
            `;
            hintDiv.innerHTML = '请手动关闭此页面或标签页 (Ctrl+W / Cmd+W)';
            
            // 添加动画样式
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideDown {
                    from { transform: translateX(-50%) translateY(-100%); opacity: 0; }
                    to { transform: translateX(-50%) translateY(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(hintDiv);
            
            // 5秒后自动隐藏提示
            setTimeout(() => {
                if (hintDiv.parentNode) {
                    hintDiv.style.animation = 'slideDown 0.3s ease reverse';
                    setTimeout(() => hintDiv.remove(), 300);
                }
            }, 5000);
        }
        
        // 为所有关闭按钮绑定统一的关闭函数
         document.addEventListener('DOMContentLoaded', function() {
             const closeButtons = document.querySelectorAll('button[data-action="close"]');
             closeButtons.forEach(button => {
                 button.onclick = function(e) {
                     e.preventDefault();
                     closeWindow();
                 };
             });
         });
         
         // 添加键盘快捷键支持
         document.addEventListener('keydown', function(e) {
             if (e.key === 'Escape') {
                 closeWindow();
             }
         });
    </script>
</body>
</html>