<?php
/**
 * 配置管理页面
 * 允许在管理后台修改所有配置项
 */

require_once 'includes/Database.php';
require_once 'includes/Logger.php';

// 检查管理员登录状态
session_start();
$db = new Database();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

$error = '';
$success = '';

// 处理AJAX获取兼容性配置请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_compatibility_config') {
    header('Content-Type: application/json');
    
    try {
        $paymentCompatibility = json_decode($db->getConfig('payment_compatibility', '{}'), true);
        echo json_encode(['payment_compatibility' => $paymentCompatibility]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// 处理AJAX自动保存请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'auto_save') {
    header('Content-Type: application/json');
    
    try {
        $success = true;
        
        // epay配置
         if (isset($_POST['epay_apiurl'])) {
             $configs = [
                 'epay_apiurl' => $_POST['epay_apiurl'],
                 'epay_pid' => $_POST['epay_pid'],
                 'epay_key' => $_POST['epay_key'],
                 'epay_sdk_version' => $_POST['epay_sdk_version'],
                 'epay_platform_public_key' => $_POST['epay_platform_public_key'],
                 'epay_merchant_private_key' => $_POST['epay_merchant_private_key']
             ];
             
             foreach ($configs as $key => $value) {
                 $db->setConfig($key, $value, 'string');
             }
         }
        
        // 支付方式配置
        if (isset($_POST['payment_methods'])) {
            $payment_methods = [];
            foreach ($_POST['payment_methods'] as $method => $data) {
                $payment_methods[$method] = [
                    'name' => $data['name'] ?? '',
                    'enabled' => isset($data['enabled']) ? true : false,
                    'icon' => $data['icon'] ?? 'fas fa-credit-card',
                    'description' => $data['description'] ?? ''
                ];
            }
            $db->setConfig('payment_methods', json_encode($payment_methods, JSON_UNESCAPED_UNICODE), 'json');
        }
        
        // 支付兼容性配置
        if (isset($_POST['payment_compatibility'])) {
            $db->setConfig('payment_compatibility', json_encode($_POST['payment_compatibility'], JSON_UNESCAPED_UNICODE), 'json');
        }
        
        // 环境推荐配置
        if (isset($_POST['environment_recommendations'])) {
            $db->setConfig('environment_recommendations', json_encode($_POST['environment_recommendations'], JSON_UNESCAPED_UNICODE), 'json');
        }
        
        // 自动跳转配置
        if (isset($_POST['auto_redirect_config'])) {
            $db->setConfig('auto_redirect_config', json_encode($_POST['auto_redirect_config'], JSON_UNESCAPED_UNICODE), 'json');
        }
        
        // 支付处理配置
        if (isset($_POST['payment_config'])) {
            $db->setConfig('payment_config', json_encode($_POST['payment_config'], JSON_UNESCAPED_UNICODE), 'json');
        }
        
        // 收银台配置
        if (isset($_POST['cashier_name'])) {
            $configs = [
                'cashier_name' => $_POST['cashier_name'],
                'cashier_url' => $_POST['cashier_url']
            ];
            
            foreach ($configs as $key => $value) {
                $db->setConfig($key, $value, 'string');
            }
        }
        
        // UI配置
        if (isset($_POST['ui_config'])) {
            $db->setConfig('ui_config', json_encode($_POST['ui_config'], JSON_UNESCAPED_UNICODE), 'json');
        }
        
        // 管理员配置
        if (isset($_POST['admin_config'])) {
            $db->setConfig('admin_config', json_encode($_POST['admin_config'], JSON_UNESCAPED_UNICODE), 'json');
        }
        
        // 安全配置
        if (isset($_POST['security_config'])) {
            $db->setConfig('security_config', json_encode($_POST['security_config'], JSON_UNESCAPED_UNICODE), 'json');
        }
        
        // 调试配置
        if (isset($_POST['debug_config'])) {
            $db->setConfig('debug_config', json_encode($_POST['debug_config'], JSON_UNESCAPED_UNICODE), 'json');
        }
        
        echo json_encode(['success' => $success]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// 处理配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_config') {
            $configKey = $_POST['config_key'] ?? '';
            $configValue = $_POST['config_value'] ?? '';
            $configType = $_POST['config_type'] ?? 'string';
            
            if (empty($configKey)) {
                throw new Exception('配置键不能为空');
            }
            
            // 处理可视化编辑器的数据
            if ($configType === 'json') {
                if ($configKey === 'payment_methods' && isset($_POST['payment_methods'])) {
                    $paymentMethods = [];
                    foreach ($_POST['payment_methods'] as $method => $methodData) {
                        $paymentMethods[$method] = [
                            'enabled' => isset($methodData['enabled']),
                            'name' => $methodData['name'] ?? '',
                            'icon' => $methodData['icon'] ?? '',
                            'description' => $methodData['description'] ?? ''
                        ];
                    }
                    $configValue = json_encode($paymentMethods, JSON_UNESCAPED_UNICODE);
                } elseif ($configKey === 'ui_config' && isset($_POST['ui_config'])) {
                    $configValue = json_encode($_POST['ui_config'], JSON_UNESCAPED_UNICODE);
                } elseif ($configKey === 'admin_config' && isset($_POST['admin_config'])) {
                    $configValue = json_encode($_POST['admin_config'], JSON_UNESCAPED_UNICODE);
                } else {
                    // 验证JSON格式
                    if (!empty($configValue)) {
                        $decoded = json_decode($configValue, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new Exception('JSON格式错误: ' . json_last_error_msg());
                        }
                    }
                }
            } else {
                // 根据类型处理值
                switch ($configType) {
                    case 'boolean':
                        $configValue = $configValue === 'true' || $configValue === '1';
                        break;
                    case 'integer':
                        $configValue = (int)$configValue;
                        break;
                    case 'float':
                        $configValue = (float)$configValue;
                        break;
                }
            }
            
            $db->setConfig($configKey, $configValue, $configType);
            
            Logger::info("配置更新", [
                'config_key' => $configKey,
                'admin_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            $success = '配置更新成功！';
            
        } elseif ($action === 'reset_config') {
            $configKey = $_POST['config_key'] ?? '';
            
            if (empty($configKey)) {
                throw new Exception('配置键不能为空');
            }
            
            // 重新初始化该配置项
            $db->initDefaultConfigs();
            
            Logger::info("配置重置", [
                'config_key' => $configKey,
                'admin_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            $success = '配置重置成功！';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 获取所有配置项
$configsArray = $db->getAllConfigs();
// 转换为以config_key为键的关联数组
$configs = [];
foreach ($configsArray as $config) {
    $configs[$config['config_key']] = $config;
}

// 配置项分组
$configGroups = [
    'epay' => ['epay_apiurl', 'epay_pid', 'epay_key', 'epay_sdk_version', 'epay_platform_public_key', 'epay_merchant_private_key'],
    'cashier' => ['cashier_url', 'cashier_name'],
    'payment' => ['payment_methods', 'payment_compatibility', 'environment_recommendations', 'auto_redirect_config', 'payment_config'],
    'security' => ['allowed_callback_domains', 'security_config'],
    'admin' => ['admin_config'],
    'ui' => ['ui_config'],
    'debug' => ['debug_config']
];

// 过滤掉不存在的配置项
foreach ($configGroups as $group => $keys) {
    $configGroups[$group] = array_filter($keys, function($key) use ($configs) {
        return isset($configs[$key]);
    });
}

$groupNames = [
    'epay' => 'epay配置',
    'cashier' => '收银台配置',
    'payment' => '支付配置',
    'security' => '安全配置',
    'admin' => '管理后台配置',
    'ui' => 'UI主题配置',
    'debug' => '调试配置'
];

// 渲染JSON可视化编辑器
function renderJsonVisualEditor($configKey, $jsonData) {
    global $configs;
    $output = '';
    
    switch ($configKey) {
        case 'payment_compatibility':
            $output .= '<h4>支付方式兼容性配置</h4>';
            // 获取当前的支付方式列表
            $paymentMethods = json_decode($configs['payment_methods']['config_value'] ?? '{}', true);
            $availableMethods = array_keys($paymentMethods);
            
            // 为每个支付方式显示兼容性配置
            foreach ($availableMethods as $method) {
                $environments = $jsonData[$method] ?? [];
                $output .= '<div class="compatibility-item">';
                $methodName = $paymentMethods[$method]['name'] ?? ucfirst($method);
                $output .= '<h5>' . htmlspecialchars($methodName) . ' 兼容环境</h5>';
                $output .= '<div class="checkbox-group">';
                $allEnvs = ['wechat', 'alipay', 'qq', 'mobile', 'desktop'];
                foreach ($allEnvs as $env) {
                    $checked = in_array($env, $environments) ? 'checked' : '';
                    $output .= '<label class="checkbox-item">';
                    $output .= '<input type="checkbox" name="payment_compatibility[' . $method . '][]" value="' . $env . '" ' . $checked . '>';
                    $output .= '<span>' . $env . '</span>';
                    $output .= '</label>';
                }
                $output .= '</div></div>';
            }
            break;
            
        case 'environment_recommendations':
            $output .= '<h4>环境推荐配置</h4>';
            $output .= '<div class="recommendation-grid">';
            // 获取当前的支付方式列表
            $paymentMethods = json_decode($configs['payment_methods']['config_value'] ?? '{}', true);
            $availableMethods = array_keys($paymentMethods);
            
            foreach ($jsonData as $env => $method) {
                $output .= '<div class="recommendation-item">';
                $output .= '<label>' . ucfirst($env) . '环境推荐：</label>';
                $output .= '<select name="environment_recommendations[' . $env . ']">';
                $output .= '<option value="">请选择支付方式</option>';
                foreach ($availableMethods as $m) {
                    $selected = $m === $method ? 'selected' : '';
                    $methodName = $paymentMethods[$m]['name'] ?? ucfirst($m);
                    $output .= '<option value="' . $m . '" ' . $selected . '>' . htmlspecialchars($methodName) . '</option>';
                }
                $output .= '</select></div>';
            }
            $output .= '</div>';
            break;
            
        case 'auto_redirect_config':
            $output .= '<h4>自动跳转配置</h4>';
            $output .= '<div class="auto-redirect-section">';
            $output .= '<label class="switch-label">';
            $output .= '<span>启用自动跳转：</span>';
            $checked = $jsonData['enabled'] ? 'checked' : '';
            $output .= '<label class="switch"><input type="checkbox" name="auto_redirect_config[enabled]" ' . $checked . '><span class="slider"></span></label>';
            $output .= '</label>';
            $output .= '<h5>环境跳转配置</h5>';
            $output .= '<div class="redirect-grid">';
            // 获取当前的支付方式列表
            $paymentMethods = json_decode($configs['payment_methods']['config_value'] ?? '{}', true);
            $availableMethods = array_keys($paymentMethods);
            
            foreach ($jsonData['environments'] as $env => $method) {
                $output .= '<div class="redirect-item">';
                $output .= '<label>' . ucfirst($env) . '：</label>';
                $output .= '<select name="auto_redirect_config[environments][' . $env . ']">';
                $output .= '<option value="">请选择支付方式</option>';
                foreach ($availableMethods as $m) {
                    $selected = $m === $method ? 'selected' : '';
                    $methodName = $paymentMethods[$m]['name'] ?? ucfirst($m);
                    $output .= '<option value="' . $m . '" ' . $selected . '>' . htmlspecialchars($methodName) . '</option>';
                }
                $output .= '</select></div>';
            }
            $output .= '</div></div>';
            break;
            
        case 'payment_config':
            $output .= '<h4>支付处理配置</h4>';
            $output .= '<div class="payment-config-grid">';
            $output .= '<div class="config-item"><label>超时时间（秒）：</label><input type="number" name="payment_config[timeout]" value="' . $jsonData['timeout'] . '" min="1"></div>';
            $output .= '<div class="config-item"><label>重试次数：</label><input type="number" name="payment_config[retry_times]" value="' . $jsonData['retry_times'] . '" min="0"></div>';
            $output .= '<div class="config-item"><label>重试间隔（秒）：</label><input type="number" name="payment_config[retry_interval]" value="' . $jsonData['retry_interval'] . '" min="1"></div>';
            $output .= '<div class="config-item"><label>金额精度：</label><input type="number" name="payment_config[amount_precision]" value="' . $jsonData['amount_precision'] . '" min="0" max="4"></div>';
            $output .= '</div>';
            $output .= '<h5>货币符号</h5>';
            $output .= '<div class="currency-grid">';
            foreach ($jsonData['currency_symbols'] as $currency => $symbol) {
                $output .= '<div class="currency-item">';
                $output .= '<label>' . $currency . '：</label>';
                $output .= '<input type="text" name="payment_config[currency_symbols][' . $currency . ']" value="' . htmlspecialchars($symbol) . '">';
                $output .= '</div>';
            }
            $output .= '</div>';
            break;
            
        case 'security_config':
            $output .= '<h4>安全配置</h4>';
            $output .= '<div class="security-grid">';
            $output .= '<label class="switch-label"><span>CSRF保护：</span><label class="switch"><input type="checkbox" name="security_config[csrf_protection]" ' . ($jsonData['csrf_protection'] ? 'checked' : '') . '><span class="slider"></span></label></label>';
            $output .= '<label class="switch-label"><span>输入过滤：</span><label class="switch"><input type="checkbox" name="security_config[input_sanitization]" ' . ($jsonData['input_sanitization'] ? 'checked' : '') . '><span class="slider"></span></label></label>';
            $output .= '<label class="switch-label"><span>XSS保护：</span><label class="switch"><input type="checkbox" name="security_config[xss_protection]" ' . ($jsonData['xss_protection'] ? 'checked' : '') . '><span class="slider"></span></label></label>';
            $output .= '</div>';
            $output .= '<h5>频率限制</h5>';
            $output .= '<div class="rate-limit-section">';
            $output .= '<label class="switch-label"><span>启用频率限制：</span><label class="switch"><input type="checkbox" name="security_config[rate_limit][enabled]" ' . ($jsonData['rate_limit']['enabled'] ? 'checked' : '') . '><span class="slider"></span></label></label>';
            $output .= '<div class="rate-limit-grid">';
            $output .= '<div class="rate-item"><label>最大请求数：</label><input type="number" name="security_config[rate_limit][max_requests]" value="' . $jsonData['rate_limit']['max_requests'] . '" min="1"></div>';
            $output .= '<div class="rate-item"><label>时间窗口（秒）：</label><input type="number" name="security_config[rate_limit][time_window]" value="' . $jsonData['rate_limit']['time_window'] . '" min="60"></div>';
            $output .= '</div></div>';
            break;
            
        case 'debug_config':
            $output .= '<h4>调试配置</h4>';
            $output .= '<div class="debug-grid">';
            $output .= '<label class="switch-label"><span>启用调试：</span><label class="switch"><input type="checkbox" name="debug_config[enabled]" ' . ($jsonData['enabled'] ? 'checked' : '') . '><span class="slider"></span></label></label>';
            $output .= '<label class="switch-label"><span>显示错误：</span><label class="switch"><input type="checkbox" name="debug_config[show_errors]" ' . ($jsonData['show_errors'] ? 'checked' : '') . '><span class="slider"></span></label></label>';
            $output .= '<label class="switch-label"><span>记录用户代理：</span><label class="switch"><input type="checkbox" name="debug_config[log_user_agent]" ' . ($jsonData['log_user_agent'] ? 'checked' : '') . '><span class="slider"></span></label></label>';
            $output .= '</div>';
            $output .= '<div class="log-level-section">';
            $output .= '<label>日志级别：</label>';
            $output .= '<select name="debug_config[log_level]">';
            $levels = ['debug', 'info', 'warning', 'error'];
            foreach ($levels as $level) {
                $selected = $level === $jsonData['log_level'] ? 'selected' : '';
                $output .= '<option value="' . $level . '" ' . $selected . '>' . ucfirst($level) . '</option>';
            }
            $output .= '</select></div>';
            break;
            
        default:
            $output .= '<p>此配置项暂不支持可视化编辑，请使用代码编辑模式。</p>';
            break;
    }
    
    return $output;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>配置管理 - <?php echo $db->getConfig('cashier_name', '云盘支付收银台'); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/common.css">
    <style>
        body {
            background: #f8f9fa;
            color: #212529;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            display: block;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            cursor: pointer;
        }
        
        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-item.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: #ffd700;
        }
        
        .nav-item i {
            margin-right: 10px;
            width: 16px;
        }
        
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            background: #f8f9fa;
        }
        
        .content-header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .content-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            color: #495057;
        }
        
        .content-body {
            padding: 30px;
        }
        
        .config-page {
            display: none;
        }
        
        .config-page.active {
            display: block;
        }
        
        .config-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .form-section {
            padding: 25px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
        }
        
        .config-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .config-key {
            font-weight: 600;
            color: #495057;
            font-size: 16px;
        }
        
        .config-type {
            background: #6c757d;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .config-description {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-field label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-field input,
        .form-field textarea,
        .form-field select {
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-field input:focus,
        .form-field textarea:focus,
        .form-field select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-field .help-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .auto-save-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 15px;
            background: #28a745;
            color: white;
            border-radius: 6px;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1001;
        }
        
        .auto-save-indicator.show {
            opacity: 1;
        }
        
        .auto-save-indicator.error {
            background: #dc3545;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: 'Courier New', monospace;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            margin-right: 10px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #5a6268;
        }
        
        .json-preview {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 10px;
        }
        
        /* 可视化编辑器样式 */
        .visual-editor {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
        }
        
        .visual-editor h4 {
            margin-bottom: 20px;
            color: #495057;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .visual-editor h5 {
            margin: 20px 0 15px 0;
            color: #6c757d;
            font-size: 16px;
        }
        
        /* 支付方式配置样式 */
        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .payment-method-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .payment-method-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        
        .payment-method-card.enabled {
            border-color: #28a745;
        }
        
        .payment-method-card.disabled {
            opacity: 0.6;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-color: #dee2e6;
        }
        
        .payment-method-card.disabled .method-icon {
            color: #6c757d;
        }
        
        .payment-method-card.disabled .method-details h4 {
            color: #6c757d;
        }
        
        .method-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .method-info {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .method-icon {
            font-size: 24px;
            margin-right: 12px;
            color: #667eea;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            min-height: 24px;
        }
        
        .method-icon svg {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }
        
        .method-details h4 {
            margin: 0;
            font-size: 16px;
            color: #495057;
        }
        
        .method-details p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #6c757d;
        }
        
        .add-method-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed #6c757d;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .add-method-card:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff 0%, #e6efff 100%);
        }
        
        .add-method-card i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
            transition: color 0.3s ease;
        }
        
        .add-method-card:hover i {
            color: #667eea;
        }
        
        .add-method-card h4 {
            margin: 0;
            color: #495057;
            font-size: 16px;
        }
        
        .method-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .method-icon {
            font-size: 24px;
            margin-right: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            min-height: 24px;
        }
        
        .method-name {
            font-weight: 600;
            color: #495057;
            flex: 1;
        }
        
        .method-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .delete-method-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .delete-method-btn:hover {
            background: #c82333;
            transform: scale(1.05);
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 28px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #28a745;
        }
        
        input:checked + .slider:before {
            transform: translateX(22px);
        }
        
        /* 模态框样式 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #495057;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        /* 移除重复的slider定义 */
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #667eea;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .method-description {
            margin-top: 10px;
        }
        
        .method-description label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .method-description input {
            width: 100%;
            padding: 8px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }
        
        /* UI配置样式 */
        .ui-section {
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .color-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .color-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .color-item label {
            min-width: 80px;
            font-weight: 500;
        }
        
        .color-item input[type="color"] {
            width: 50px;
            height: 35px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .layout-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .layout-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .layout-item label {
            font-weight: 500;
        }
        
        .layout-item input {
            padding: 8px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }
        
        .grid-settings {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .grid-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .grid-item label {
            font-weight: 500;
        }
        
        .grid-item input {
            padding: 8px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }
        
        /* 管理后台配置样式 */
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .admin-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .admin-item label {
            font-weight: 500;
        }
        
        .admin-item input {
            padding: 8px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }
        
        /* JSON编辑器标签页样式 */
        .json-editor-wrapper {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .json-editor-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .json-tab {
            flex: 1;
            padding: 12px 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .json-tab.active {
            background: #667eea;
            color: white;
        }
        
        .json-tab:hover:not(.active) {
            background: #e9ecef;
        }
        
        .json-editor-content {
            display: none;
            padding: 20px;
        }
        
        .json-editor-content.active {
            display: block;
        }
        
        .json-visual-editor {
             /* 通用可视化编辑器样式 */
         }
         
         /* 兼容性配置样式 */
         .compatibility-item {
             margin-bottom: 20px;
             padding: 15px;
             background: #f8f9fa;
             border-radius: 8px;
         }
         
         .checkbox-group {
             display: flex;
             flex-wrap: wrap;
             gap: 10px;
             margin-top: 10px;
         }
         
         .checkbox-item {
             display: flex;
             align-items: center;
             gap: 5px;
             padding: 5px 10px;
             background: white;
             border: 1px solid #e9ecef;
             border-radius: 4px;
             cursor: pointer;
         }
         
         .checkbox-item:hover {
             background: #f0f0f0;
         }
         
         .checkbox-item input[type="checkbox"] {
             margin: 0;
         }
         
         /* 推荐配置样式 */
         .recommendation-grid {
             display: grid;
             grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
             gap: 15px;
         }
         
         .recommendation-item {
             display: flex;
             flex-direction: column;
             gap: 5px;
         }
         
         .recommendation-item label {
             font-weight: 500;
         }
         
         .recommendation-item select {
             padding: 8px;
             border: 1px solid #e9ecef;
             border-radius: 4px;
         }
         
         /* 自动跳转配置样式 */
         .auto-redirect-section {
             padding: 15px;
             background: #f8f9fa;
             border-radius: 8px;
         }
         
         .switch-label {
             display: flex;
             align-items: center;
             justify-content: space-between;
             margin-bottom: 15px;
         }
         
         .switch-label span {
             font-weight: 500;
         }
         
         .redirect-grid {
             display: grid;
             grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
             gap: 15px;
         }
         
         .redirect-item {
             display: flex;
             flex-direction: column;
             gap: 5px;
         }
         
         .redirect-item label {
             font-weight: 500;
         }
         
         .redirect-item select {
             padding: 8px;
             border: 1px solid #e9ecef;
             border-radius: 4px;
         }
         
         /* 支付配置样式 */
         .payment-config-grid {
             display: grid;
             grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
             gap: 15px;
             margin-bottom: 20px;
         }
         
         .config-item {
             display: flex;
             flex-direction: column;
             gap: 5px;
         }
         
         .config-item label {
             font-weight: 500;
         }
         
         .config-item input {
             padding: 8px;
             border: 1px solid #e9ecef;
             border-radius: 4px;
         }
         
         .currency-grid {
             display: grid;
             grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
             gap: 15px;
         }
         
         .currency-item {
             display: flex;
             flex-direction: column;
             gap: 5px;
         }
         
         .currency-item label {
             font-weight: 500;
         }
         
         .currency-item input {
             padding: 8px;
             border: 1px solid #e9ecef;
             border-radius: 4px;
         }
         
         /* 安全配置样式 */
         .security-grid {
             display: flex;
             flex-direction: column;
             gap: 15px;
             margin-bottom: 20px;
         }
         
         .rate-limit-section {
             padding: 15px;
             background: #f8f9fa;
             border-radius: 8px;
         }
         
         .rate-limit-grid {
             display: grid;
             grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
             gap: 15px;
             margin-top: 15px;
         }
         
         .rate-item {
             display: flex;
             flex-direction: column;
             gap: 5px;
         }
         
         .rate-item label {
             font-weight: 500;
         }
         
         .rate-item input {
             padding: 8px;
             border: 1px solid #e9ecef;
             border-radius: 4px;
         }
         
         /* 调试配置样式 */
         .debug-grid {
             display: flex;
             flex-direction: column;
             gap: 15px;
             margin-bottom: 20px;
         }
         
         .log-level-section {
             display: flex;
             flex-direction: column;
             gap: 5px;
         }
         
         .log-level-section label {
             font-weight: 500;
         }
         
         .log-level-section select {
             padding: 8px;
             border: 1px solid #e9ecef;
             border-radius: 4px;
             max-width: 200px;
         }
        
        @media (max-width: 768px) {
            .nav-tabs {
                flex-direction: column;
            }
            
            .config-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .config-type {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- 自动保存提示 -->
    <div id="autoSaveIndicator" class="auto-save-indicator">
        <i class="fas fa-check"></i> 配置已自动保存
    </div>
    
    <!-- 侧边栏导航 -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-cogs"></i> 配置管理</h2>
        </div>
        <nav class="nav-menu">
            <a href="#" class="nav-item active" data-page="epay">
                <i class="fas fa-credit-card"></i> epay配置
            </a>
            <a href="#" class="nav-item" data-page="payment">
                <i class="fas fa-money-bill-wave"></i> 支付配置
            </a>
            <a href="#" class="nav-item" data-page="cashier">
                <i class="fas fa-cash-register"></i> 收银台配置
            </a>
            <a href="#" class="nav-item" data-page="ui">
                <i class="fas fa-palette"></i> UI主题配置
            </a>
            <a href="#" class="nav-item" data-page="admin">
                <i class="fas fa-user-shield"></i> 管理后台配置
            </a>
            <a href="#" class="nav-item" data-page="security">
                <i class="fas fa-shield-alt"></i> 安全配置
            </a>
            <a href="#" class="nav-item" data-page="debug">
                <i class="fas fa-bug"></i> 调试配置
            </a>
            <a href="admin.php" class="nav-item" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-arrow-left"></i> 返回管理后台
            </a>
        </nav>
    </div>
    
    <!-- 主内容区域 -->
    <div class="main-content">
        <div class="content-header">
            <h1 id="pageTitle">epay配置</h1>
        </div>
        
        <div class="content-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
        
            <!-- epay配置页面 -->
            <div id="epay-page" class="config-page active">
                <div class="config-form">
                    <form id="epayForm" class="auto-save-form">
                        <input type="hidden" name="page" value="epay">
                        
                        <div class="form-section">
                            <h3 class="section-title">基础配置</h3>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="epay_apiurl">API地址</label>
                                    <input type="url" id="epay_apiurl" name="epay_apiurl" value="<?php echo htmlspecialchars($configs['epay_apiurl']['config_value'] ?? ''); ?>" required>
                                    <div class="help-text">epay支付接口的API地址</div>
                                </div>
                                <div class="form-field">
                                    <label for="epay_pid">商户ID</label>
                                    <input type="text" id="epay_pid" name="epay_pid" value="<?php echo htmlspecialchars($configs['epay_pid']['config_value'] ?? ''); ?>" required>
                                    <div class="help-text">epay平台分配的商户ID</div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="epay_key">商户密钥</label>
                                    <input type="text" id="epay_key" name="epay_key" value="<?php echo htmlspecialchars($configs['epay_key']['config_value'] ?? ''); ?>" required autocomplete="off">
                                    <div class="help-text">epay平台分配的商户密钥</div>
                                </div>
                                <div class="form-field">
                                    <label for="epay_sdk_version">SDK版本</label>
                                    <select id="epay_sdk_version" name="epay_sdk_version">
                                        <option value="1.0" <?php echo ($configs['epay_sdk_version']['config_value'] ?? '') === '1.0' ? 'selected' : ''; ?>>SDK 1.0 (MD5签名)</option>
                                        <option value="2.0" <?php echo ($configs['epay_sdk_version']['config_value'] ?? '') === '2.0' ? 'selected' : ''; ?>>SDK 2.0 (RSA签名)</option>
                                    </select>
                                    <div class="help-text">选择使用的SDK版本</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3 class="section-title">安全配置</h3>
                            <div class="form-field">
                                <label for="epay_platform_public_key">平台公钥</label>
                                <textarea id="epay_platform_public_key" name="epay_platform_public_key" rows="4"><?php echo htmlspecialchars($configs['epay_platform_public_key']['config_value'] ?? ''); ?></textarea>
                                <div class="help-text">epay平台的RSA公钥</div>
                            </div>
                            <div class="form-field">
                                <label for="epay_merchant_private_key">商户私钥</label>
                                <textarea id="epay_merchant_private_key" name="epay_merchant_private_key" rows="4"><?php echo htmlspecialchars($configs['epay_merchant_private_key']['config_value'] ?? ''); ?></textarea>
                                <div class="help-text">商户的RSA私钥</div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 支付配置页面 -->
            <div id="payment-page" class="config-page">
                <div class="config-form">
                    <form id="paymentForm" class="auto-save-form">
                        <input type="hidden" name="page" value="payment">
                        
                        <div class="form-section">
                            <h3 class="section-title">支付方式管理</h3>
                            <div class="payment-methods-grid">
                                <?php 
                                $paymentMethods = json_decode($configs['payment_methods']['config_value'] ?? '{}', true);
                                foreach ($paymentMethods as $method => $methodData): 
                                ?>
                                    <div class="payment-method-card <?php echo ($methodData['enabled'] ?? false) ? 'enabled' : 'disabled'; ?>" data-method="<?php echo $method; ?>">
                                        <div class="method-header">
                                            <div class="method-info">
                                                <div class="method-icon"><?php echo $methodData['icon'] ?? '💳'; ?></div>
                                                <div class="method-details">
                                                    <h4><?php echo htmlspecialchars($methodData['name'] ?? $method); ?></h4>
                                                    <p><?php echo htmlspecialchars($methodData['description'] ?? ''); ?></p>
                                                </div>
                                            </div>
                                            <div class="method-controls">
                                                <label class="switch">
                                                    <input type="checkbox" name="payment_methods[<?php echo $method; ?>][enabled]" <?php echo ($methodData['enabled'] ?? false) ? 'checked' : ''; ?> onchange="togglePaymentMethod(this)">
                                                    <span class="slider"></span>
                                                </label>
                                                <button type="button" class="delete-method-btn" onclick="deletePaymentMethod('<?php echo $method; ?>')" title="删除支付方式">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-field">
                                                <label>标识</label>
                                                <input type="text" name="payment_methods[<?php echo $method; ?>][key]" value="<?php echo htmlspecialchars($method); ?>" readonly>
                                            </div>
                                            <div class="form-field">
                                                <label>名称</label>
                                                <input type="text" name="payment_methods[<?php echo $method; ?>][name]" value="<?php echo htmlspecialchars($methodData['name'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-field">
                                <label>图标 (emoji/svg)</label>
                                <input type="text" name="payment_methods[<?php echo $method; ?>][icon]" value="<?php echo htmlspecialchars($methodData['icon'] ?? ''); ?>" placeholder="如: 💳 或 <svg>...</svg>">
                            </div>
                                            <div class="form-field">
                                                <label>描述</label>
                                                <input type="text" name="payment_methods[<?php echo $method; ?>][description]" value="<?php echo htmlspecialchars($methodData['description'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <!-- 添加新支付方式 -->
                                <div class="add-method-card" onclick="openAddMethodModal()">
                                    <i class="fas fa-plus"></i>
                                    <h4>添加支付方式</h4>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 支付兼容性配置 -->
                        <div class="form-section">
                            <h3 class="section-title">支付兼容性配置</h3>
                            <?php 
                            $paymentCompatibility = json_decode($configs['payment_compatibility']['config_value'] ?? '{}', true);
                            echo renderJsonVisualEditor('payment_compatibility', $paymentCompatibility);
                            ?>
                        </div>
                        
                        <!-- 环境推荐配置 -->
                        <div class="form-section">
                            <h3 class="section-title">环境推荐配置</h3>
                            <?php 
                            $environmentRecommendations = json_decode($configs['environment_recommendations']['config_value'] ?? '{}', true);
                            echo renderJsonVisualEditor('environment_recommendations', $environmentRecommendations);
                            ?>
                        </div>
                        
                        <!-- 自动跳转配置 -->
                        <div class="form-section">
                            <h3 class="section-title">自动跳转配置</h3>
                            <?php 
                            $autoRedirectConfig = json_decode($configs['auto_redirect_config']['config_value'] ?? '{}', true);
                            echo renderJsonVisualEditor('auto_redirect_config', $autoRedirectConfig);
                            ?>
                        </div>
                        
                        <!-- 支付处理配置 -->
                        <div class="form-section">
                            <h3 class="section-title">支付处理配置</h3>
                            <?php 
                            $paymentConfig = json_decode($configs['payment_config']['config_value'] ?? '{}', true);
                            echo renderJsonVisualEditor('payment_config', $paymentConfig);
                            ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 收银台配置页面 -->
            <div id="cashier-page" class="config-page">
                <div class="config-form">
                    <form id="cashierForm" class="auto-save-form">
                        <input type="hidden" name="page" value="cashier">
                        
                        <div class="form-section">
                            <h3 class="section-title">收银台设置</h3>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="cashier_name">收银台名称</label>
                                    <input type="text" id="cashier_name" name="cashier_name" value="<?php echo htmlspecialchars($configs['cashier_name']['config_value'] ?? ''); ?>">
                                    <div class="help-text">显示在收银台页面的名称</div>
                                </div>
                                <div class="form-field">
                                    <label for="cashier_url">收银台URL</label>
                                    <input type="url" id="cashier_url" name="cashier_url" value="<?php echo htmlspecialchars($configs['cashier_url']['config_value'] ?? ''); ?>">
                                    <div class="help-text">收银台的访问地址</div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- UI配置页面 -->
            <div id="ui-page" class="config-page">
                <div class="config-form">
                    <form id="uiForm" class="auto-save-form">
                        <input type="hidden" name="page" value="ui">
                        
                        <div class="form-section">
                            <h3 class="section-title">主题配置</h3>
                            <?php 
                            $uiConfig = json_decode($configs['ui_config']['config_value'] ?? '{}', true);
                            ?>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="primary_color">主色调</label>
                                    <input type="color" id="primary_color" name="ui_config[theme][primary_color]" value="<?php echo htmlspecialchars($uiConfig['theme']['primary_color'] ?? '#007bff'); ?>">
                                    <div class="help-text">网站的主要颜色</div>
                                </div>
                                <div class="form-field">
                                    <label for="secondary_color">次色调</label>
                                    <input type="color" id="secondary_color" name="ui_config[theme][secondary_color]" value="<?php echo htmlspecialchars($uiConfig['theme']['secondary_color'] ?? '#6c757d'); ?>">
                                    <div class="help-text">网站的次要颜色</div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="success_color">成功色</label>
                                    <input type="color" id="success_color" name="ui_config[theme][success_color]" value="<?php echo htmlspecialchars($uiConfig['theme']['success_color'] ?? '#28a745'); ?>">
                                    <div class="help-text">成功状态的颜色</div>
                                </div>
                                <div class="form-field">
                                    <label for="danger_color">危险色</label>
                                    <input type="color" id="danger_color" name="ui_config[theme][danger_color]" value="<?php echo htmlspecialchars($uiConfig['theme']['danger_color'] ?? '#dc3545'); ?>">
                                    <div class="help-text">错误状态的颜色</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3 class="section-title">布局设置</h3>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="max_width">最大宽度</label>
                                    <input type="text" id="max_width" name="ui_config[layout][max_width]" value="<?php echo htmlspecialchars($uiConfig['layout']['max_width'] ?? '500px'); ?>">
                                    <div class="help-text">收银台的最大宽度</div>
                                </div>
                                <div class="form-field">
                                    <label for="border_radius">圆角大小</label>
                                    <input type="text" id="border_radius" name="ui_config[layout][border_radius]" value="<?php echo htmlspecialchars($uiConfig['layout']['border_radius'] ?? '20px'); ?>">
                                    <div class="help-text">元素的圆角大小</div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="payment_grid_columns">支付网格列数</label>
                                    <input type="number" id="payment_grid_columns" name="ui_config[payment_grid][columns]" value="<?php echo htmlspecialchars($uiConfig['payment_grid']['columns'] ?? '2'); ?>" min="1" max="6">
                                    <div class="help-text">支付方式网格显示的列数</div>
                                </div>
                                <div class="form-field">
                                    <label for="mobile_columns">移动端列数</label>
                                    <input type="number" id="mobile_columns" name="ui_config[payment_grid][mobile_columns]" value="<?php echo htmlspecialchars($uiConfig['payment_grid']['mobile_columns'] ?? '1'); ?>" min="1" max="3">
                                    <div class="help-text">移动端支付方式网格列数</div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 管理员配置页面 -->
            <div id="admin-page" class="config-page">
                <div class="config-form">
                    <form id="adminForm" class="auto-save-form">
                        <input type="hidden" name="page" value="admin">
                        
                        <div class="form-section">
                            <h3 class="section-title">管理员设置</h3>
                            <?php 
                            $adminConfig = json_decode($configs['admin_config']['config_value'] ?? '{}', true);
                            ?>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="admin_password">管理员密码</label>
                                    <input type="password" id="admin_password" name="admin_config[password]" value="<?php echo htmlspecialchars($adminConfig['password'] ?? ''); ?>">
                                    <div class="help-text">管理员登录密码</div>
                                </div>
                                <div class="form-field">
                                    <label for="session_timeout">会话超时时间（秒）</label>
                                    <input type="number" id="session_timeout" name="admin_config[session_timeout]" value="<?php echo htmlspecialchars($adminConfig['session_timeout'] ?? '3600'); ?>" min="300">
                                    <div class="help-text">管理员会话超时时间</div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="orders_per_page">每页订单数</label>
                                    <input type="number" id="orders_per_page" name="admin_config[orders_per_page]" value="<?php echo htmlspecialchars($adminConfig['orders_per_page'] ?? '50'); ?>" min="10" max="200">
                                    <div class="help-text">管理后台每页显示的订单数量</div>
                                </div>
                                <div class="form-field">
                                    <label for="cleanup_expired_hours">过期订单清理时间（小时）</label>
                                    <input type="number" id="cleanup_expired_hours" name="admin_config[cleanup_expired_hours]" value="<?php echo htmlspecialchars($adminConfig['cleanup_expired_hours'] ?? '24'); ?>" min="1">
                                    <div class="help-text">自动清理过期订单的时间间隔</div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 安全配置页面 -->
            <div id="security-page" class="config-page">
                <div class="config-form">
                    <form id="securityForm" class="auto-save-form">
                        <input type="hidden" name="page" value="security">
                        
                        <div class="form-section">
                            <h3 class="section-title">安全设置</h3>
                            <?php 
                            $securityConfig = json_decode($configs['security_config']['config_value'] ?? '{}', true);
                            ?>
                            <div class="form-row">
                                <div class="form-field">
                                    <label>
                                        <input type="checkbox" name="security_config[csrf_protection]" <?php echo ($securityConfig['csrf_protection'] ?? false) ? 'checked' : ''; ?>>
                                        启用CSRF保护
                                    </label>
                                    <div class="help-text">防止跨站请求伪造攻击</div>
                                </div>
                                <div class="form-field">
                                    <label>
                                        <input type="checkbox" name="security_config[xss_protection]" <?php echo ($securityConfig['xss_protection'] ?? false) ? 'checked' : ''; ?>>
                                        启用XSS保护
                                    </label>
                                    <div class="help-text">防止跨站脚本攻击</div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="rate_limit_requests">速率限制（请求/分钟）</label>
                                    <input type="number" id="rate_limit_requests" name="security_config[rate_limit][requests_per_minute]" value="<?php echo htmlspecialchars($securityConfig['rate_limit']['requests_per_minute'] ?? '60'); ?>" min="1">
                                    <div class="help-text">每分钟允许的最大请求数</div>
                                </div>
                                <div class="form-field">
                                    <label for="rate_limit_window">限制窗口（秒）</label>
                                    <input type="number" id="rate_limit_window" name="security_config[rate_limit][window_seconds]" value="<?php echo htmlspecialchars($securityConfig['rate_limit']['window_seconds'] ?? '60'); ?>" min="1">
                                    <div class="help-text">速率限制的时间窗口</div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 调试配置页面 -->
            <div id="debug-page" class="config-page">
                <div class="config-form">
                    <form id="debugForm" class="auto-save-form">
                        <input type="hidden" name="page" value="debug">
                        
                        <div class="form-section">
                            <h3 class="section-title">调试设置</h3>
                            <?php 
                            $debugConfig = json_decode($configs['debug_config']['config_value'] ?? '{}', true);
                            ?>
                            <div class="form-row">
                                <div class="form-field">
                                    <label>
                                        <input type="checkbox" name="debug_config[enabled]" <?php echo ($debugConfig['enabled'] ?? false) ? 'checked' : ''; ?>>
                                        启用调试模式
                                    </label>
                                    <div class="help-text">开启后将显示详细的错误信息</div>
                                </div>
                                <div class="form-field">
                                    <label>
                                        <input type="checkbox" name="debug_config[log_queries]" <?php echo ($debugConfig['log_queries'] ?? false) ? 'checked' : ''; ?>>
                                        记录SQL查询
                                    </label>
                                    <div class="help-text">记录所有数据库查询到日志</div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="log_level">日志级别</label>
                                    <select id="log_level" name="debug_config[log_level]">
                                        <option value="error" <?php echo ($debugConfig['log_level'] ?? '') === 'error' ? 'selected' : ''; ?>>错误</option>
                                        <option value="warning" <?php echo ($debugConfig['log_level'] ?? '') === 'warning' ? 'selected' : ''; ?>>警告</option>
                                        <option value="info" <?php echo ($debugConfig['log_level'] ?? '') === 'info' ? 'selected' : ''; ?>>信息</option>
                                        <option value="debug" <?php echo ($debugConfig['log_level'] ?? '') === 'debug' ? 'selected' : ''; ?>>调试</option>
                                    </select>
                                    <div class="help-text">设置日志记录的详细程度</div>
                                </div>
                                <div class="form-field">
                                    <label for="max_log_size">最大日志文件大小（MB）</label>
                                    <input type="number" id="max_log_size" name="debug_config[max_log_size_mb]" value="<?php echo htmlspecialchars($debugConfig['max_log_size_mb'] ?? '10'); ?>" min="1" max="100">
                                    <div class="help-text">日志文件的最大大小</div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        
        </div>
        
        <!-- 添加支付方式模态框 -->
        <div id="addMethodModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>添加支付方式</h3>
                    <span class="close" onclick="closeAddMethodModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="addMethodForm">
                        <div class="form-field">
                            <label for="new_method_key">支付方式标识</label>
                            <input type="text" id="new_method_key" name="method_key" required>
                            <div class="help-text">英文标识，如：wechat、alipay</div>
                        </div>
                        <div class="form-field">
                            <label for="new_method_name">支付方式名称</label>
                            <input type="text" id="new_method_name" name="method_name" required>
                            <div class="help-text">显示名称，如：微信支付</div>
                        </div>
                        <div class="form-field">
                            <label for="new_method_icon">图标 (emoji/svg)</label>
                            <input type="text" id="new_method_icon" name="method_icon" value="💳" placeholder="如: 💳 或 <svg>...</svg>">
                            <div class="help-text">支持emoji表情或SVG代码</div>
                        </div>
                        <div class="form-field">
                            <label for="new_method_description">描述</label>
                            <input type="text" id="new_method_description" name="method_description">
                            <div class="help-text">支付方式描述</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddMethodModal()">取消</button>
                    <button type="button" class="btn btn-primary" onclick="addPaymentMethod()">添加</button>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="admin.php" class="back-btn">返回管理后台</a>
        </div>
    </div>
    
    <script>
        // 页面切换功能
         function showPage(pageId) {
             // 隐藏所有页面
             document.querySelectorAll('.config-page').forEach(page => {
                 page.classList.remove('active');
             });
             
             // 移除所有导航项的active状态
             document.querySelectorAll('.nav-item').forEach(item => {
                 item.classList.remove('active');
             });
             
             // 显示选中的页面
             document.getElementById(pageId + '-page').classList.add('active');
             
             // 激活选中的导航项
             document.querySelector(`[data-page="${pageId}"]`).classList.add('active');
             
             // 更新页面标题
             const titles = {
                 'epay': 'epay配置',
                 'payment': '支付配置',
                 'cashier': '收银台配置',
                 'ui': 'UI配置',
                 'admin': '管理员配置',
                 'security': '安全配置',
                 'debug': '调试配置'
             };
             document.getElementById('pageTitle').textContent = titles[pageId] || '配置管理';
         }
        
        // 自动保存功能
        let autoSaveTimeout;
        
        function autoSave(form) {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                saveConfig(form);
            }, 1000); // 1秒后自动保存
        }
        
        function saveConfig(form) {
            const formData = new FormData(form);
            formData.append('action', 'auto_save');
            
            // 显示保存指示器
             const indicator = document.getElementById('autoSaveIndicator');
             indicator.textContent = '保存中...';
             indicator.style.display = 'block';
            
            fetch('config_manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    indicator.textContent = '已保存';
                    setTimeout(() => {
                        indicator.style.display = 'none';
                    }, 2000);
                } else {
                    indicator.textContent = '保存失败';
                    indicator.style.color = '#dc3545';
                }
            })
            .catch(error => {
                console.error('保存失败:', error);
                indicator.textContent = '保存失败';
                indicator.style.color = '#dc3545';
            });
        }
        
        // 支付方式切换处理
        function togglePaymentMethod(checkbox) {
            const card = checkbox.closest('.payment-method-card');
            if (checkbox.checked) {
                card.classList.remove('disabled');
                card.classList.add('enabled');
                card.style.opacity = '1';
            } else {
                card.classList.remove('enabled');
                card.classList.add('disabled');
                card.style.opacity = '0.6';
            }
            
            // 自动保存
            const form = checkbox.closest('form');
            autoSave(form);
        }
        
        // 删除支付方式
        function deletePaymentMethod(methodKey) {
            if (confirm('确定要删除这个支付方式吗？')) {
                const card = document.querySelector(`.payment-method-card[data-method="${methodKey}"]`);
                if (card) {
                    // 先重新渲染相关配置区域（这会保存当前状态）
                    refreshPaymentConfigurations();
                    
                    // 然后删除支付方式卡片
                    card.remove();
                    
                    // 删除兼容性配置项
                    const compatibilityItem = document.querySelector(`.compatibility-item[data-method="${methodKey}"]`);
                    if (compatibilityItem) {
                        compatibilityItem.remove();
                    }
                    
                    // 删除环境推荐和自动跳转配置中的选项
                    const optionsToRemove = document.querySelectorAll(`option[value="${methodKey}"]`);
                    optionsToRemove.forEach(option => {
                        const select = option.parentElement;
                        if (select.name && (select.name.includes('environment_recommendations') || select.name.includes('auto_redirect_config'))) {
                            // 如果当前选中的是要删除的选项，重置为空
                            if (select.value === methodKey) {
                                select.value = '';
                            }
                            option.remove();
                        }
                    });
                    
                    // 再次重新渲染相关配置区域（基于删除后的支付方式列表）
                    refreshPaymentConfigurations();
                    
                    // 自动保存
                    const form = document.getElementById('paymentForm');
                    autoSave(form);
                }
            }
        }
        
        // 添加支付方式模态框
        function openAddMethodModal() {
            document.getElementById('addMethodModal').style.display = 'block';
        }
        
        function closeAddMethodModal() {
            document.getElementById('addMethodModal').style.display = 'none';
            document.getElementById('addMethodForm').reset();
        }
        
        function addPaymentMethod() {
            const form = document.getElementById('addMethodForm');
            const formData = new FormData(form);
            
            // 验证表单
            if (!formData.get('method_key') || !formData.get('method_name')) {
                alert('请填写必填字段');
                return;
            }
            
            // 添加到支付方式列表
            const methodKey = formData.get('method_key');
            const methodName = formData.get('method_name');
            const methodIcon = formData.get('method_icon') || '💳';
            const methodDescription = formData.get('method_description') || '';
            
            // 创建新的支付方式卡片
            const methodsGrid = document.querySelector('.payment-methods-grid');
            const addCard = document.querySelector('.add-method-card');
            
            const newCard = document.createElement('div');
            newCard.className = 'payment-method-card enabled';
            newCard.setAttribute('data-method', methodKey);
            newCard.innerHTML = `
                <div class="method-header">
                    <div class="method-info">
                        <div class="method-icon">${methodIcon}</div>
                        <div class="method-details">
                            <h4>${methodName}</h4>
                            <p>${methodDescription}</p>
                        </div>
                    </div>
                    <div class="method-controls">
                        <label class="switch">
                            <input type="checkbox" name="payment_methods[${methodKey}][enabled]" checked onchange="togglePaymentMethod(this)">
                            <span class="slider"></span>
                        </label>
                        <button type="button" class="delete-method-btn" onclick="deletePaymentMethod('${methodKey}')" title="删除支付方式">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field">
                        <label>标识</label>
                        <input type="text" name="payment_methods[${methodKey}][key]" value="${methodKey}" readonly>
                    </div>
                    <div class="form-field">
                        <label>名称</label>
                        <input type="text" name="payment_methods[${methodKey}][name]" value="${methodName}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field">
                        <label>图标 (emoji/svg)</label>
                        <input type="text" name="payment_methods[${methodKey}][icon]" value="${methodIcon}" placeholder="如: 💳 或 <svg>...</svg>">
                    </div>
                    <div class="form-field">
                        <label>描述</label>
                        <input type="text" name="payment_methods[${methodKey}][description]" value="${methodDescription}">
                    </div>
                </div>
            `;
            
            methodsGrid.insertBefore(newCard, addCard);
            
            // 自动保存
            const paymentForm = document.getElementById('paymentForm');
            autoSave(paymentForm);
            
            // 更新兼容性和环境推荐配置
            updatePaymentCompatibilityOptions(methodKey, methodName);
            updateEnvironmentRecommendationOptions(methodKey, methodName);
            updateAutoRedirectOptions(methodKey, methodName);
            
            closeAddMethodModal();
        }
        
        // 更新支付兼容性配置选项
         function updatePaymentCompatibilityOptions(methodKey, methodName) {
             const sections = document.querySelectorAll('.form-section');
             let compatibilitySection = null;
             sections.forEach(section => {
                 const h3 = section.querySelector('h3');
                 if (h3 && h3.textContent.includes('支付兼容性配置')) {
                     compatibilitySection = section;
                 }
             });
             
             if (compatibilitySection) {
                 const existingItem = compatibilitySection.querySelector(`[data-method="${methodKey}"]`);
                 if (!existingItem) {
                    const newCompatibilityItem = document.createElement('div');
                    newCompatibilityItem.className = 'compatibility-item';
                    newCompatibilityItem.setAttribute('data-method', methodKey);
                    newCompatibilityItem.innerHTML = `
                        <h5>${methodName} 兼容环境</h5>
                        <div class="checkbox-group">
                            <label class="checkbox-item">
                                <input type="checkbox" name="payment_compatibility[${methodKey}][]" value="wechat">
                                <span>wechat</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="payment_compatibility[${methodKey}][]" value="alipay">
                                <span>alipay</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="payment_compatibility[${methodKey}][]" value="qq">
                                <span>qq</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="payment_compatibility[${methodKey}][]" value="mobile">
                                <span>mobile</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="payment_compatibility[${methodKey}][]" value="desktop">
                                <span>desktop</span>
                            </label>
                        </div>
                    `;
                     compatibilitySection.appendChild(newCompatibilityItem);
                 }
             }
         }
        
        // 更新环境推荐配置选项
        function updateEnvironmentRecommendationOptions(methodKey, methodName) {
            const selects = document.querySelectorAll('select[name^="environment_recommendations"]');
            selects.forEach(select => {
                const existingOption = select.querySelector(`option[value="${methodKey}"]`);
                if (!existingOption) {
                    const newOption = document.createElement('option');
                    newOption.value = methodKey;
                    newOption.textContent = methodName;
                    select.appendChild(newOption);
                }
            });
        }
        
        // 更新自动跳转配置选项
        function updateAutoRedirectOptions(methodKey, methodName) {
            const selects = document.querySelectorAll('select[name^="auto_redirect_config[environments]"]');
            selects.forEach(select => {
                const existingOption = select.querySelector(`option[value="${methodKey}"]`);
                if (!existingOption) {
                    const newOption = document.createElement('option');
                    newOption.value = methodKey;
                    newOption.textContent = methodName;
                    select.appendChild(newOption);
                }
            });
        }
        
        // 刷新支付配置区域
        function refreshPaymentConfigurations() {
            // 获取当前所有支付方式
            const paymentCards = document.querySelectorAll('.payment-method-card[data-method]');
            const currentMethods = Array.from(paymentCards).map(card => {
                const methodKey = card.getAttribute('data-method');
                const methodName = card.querySelector('.method-details h4').textContent;
                return { key: methodKey, name: methodName };
            });
            
            // 从数据库重新加载兼容性配置数据
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_compatibility_config'
            })
            .then(response => response.json())
            .then(data => {
                const currentCompatibilityData = data.payment_compatibility || {};
                rebuildCompatibilitySection(currentMethods, currentCompatibilityData);
            })
            .catch(error => {
                console.error('获取兼容性配置失败:', error);
                const existingCompatibilityItems = document.querySelectorAll('.compatibility-item');
                existingCompatibilityItems.forEach(item => {
                    const methodKey = item.getAttribute('data-method');
                    if (methodKey) {
                        const checkboxes = item.querySelectorAll('input[type="checkbox"]:checked');
                        currentCompatibilityData[methodKey] = Array.from(checkboxes).map(cb => cb.value);
                    }
                });
                rebuildCompatibilitySection(currentMethods, currentCompatibilityData);
            });
        }
        
        // 重建兼容性配置区域
        function rebuildCompatibilitySection(currentMethods, currentCompatibilityData) {
            
            // 重新构建兼容性配置
            const sections = document.querySelectorAll('.form-section');
            let compatibilitySection = null;
            sections.forEach(section => {
                const h3 = section.querySelector('h3');
                if (h3 && h3.textContent.includes('支付兼容性配置')) {
                    compatibilitySection = section;
                }
            });
            
            if (compatibilitySection) {
                // 清除现有的兼容性配置项
                const existingItems = compatibilitySection.querySelectorAll('.compatibility-item');
                existingItems.forEach(item => item.remove());
                
                // 重新添加当前支付方式的兼容性配置
                currentMethods.forEach(method => {
                    const savedCompatibility = currentCompatibilityData[method.key] || [];
                    const newCompatibilityItem = document.createElement('div');
                    newCompatibilityItem.className = 'compatibility-item';
                    newCompatibilityItem.setAttribute('data-method', method.key);
                    
                    const environments = ['wechat', 'alipay', 'qq', 'mobile', 'desktop'];
                    let checkboxesHtml = '';
                    environments.forEach(env => {
                        const checked = savedCompatibility.includes(env) ? 'checked' : '';
                        checkboxesHtml += `
                            <label class="checkbox-item">
                                <input type="checkbox" name="payment_compatibility[${method.key}][]" value="${env}" ${checked}>
                                <span>${env}</span>
                            </label>`;
                    });
                    
                    newCompatibilityItem.innerHTML = `
                        <h5>${method.name} 兼容环境</h5>
                        <div class="checkbox-group">${checkboxesHtml}
                        </div>
                    `;
                    compatibilitySection.appendChild(newCompatibilityItem);
                });
            }
            
            // 重新构建环境推荐和自动跳转配置的选项
            const envSelects = document.querySelectorAll('select[name^="environment_recommendations"], select[name^="auto_redirect_config[environments]"]');
            envSelects.forEach(select => {
                const currentValue = select.value;
                // 清除除了空选项外的所有选项
                const options = select.querySelectorAll('option');
                options.forEach(option => {
                    if (option.value !== '') {
                        option.remove();
                    }
                });
                
                // 重新添加当前支付方式选项
                currentMethods.forEach(method => {
                    const newOption = document.createElement('option');
                    newOption.value = method.key;
                    newOption.textContent = method.name;
                    if (method.key === currentValue) {
                        newOption.selected = true;
                    }
                    select.appendChild(newOption);
                });
                
                // 如果之前选中的选项已被删除，重置为空
                if (currentValue && !currentMethods.find(m => m.key === currentValue)) {
                    select.value = '';
                }
            });
         }
        
        // SDK版本切换处理
        function handleSDKVersionChange() {
            const sdkVersion = document.getElementById('epay_sdk_version').value;
            const securitySection = document.querySelector('#epay-page .form-section:last-child');
            const merchantKeyField = document.getElementById('epay_key').closest('.form-field');
            
            if (sdkVersion === '2.0') {
                // SDK 2.0版本：隐藏商户密钥，显示RSA密钥对
                merchantKeyField.style.display = 'none';
                securitySection.style.display = 'block';
                // 不清空商户密钥，只是隐藏
            } else {
                // SDK 1.0版本：显示商户密钥，隐藏RSA密钥对
                merchantKeyField.style.display = 'block';
                securitySection.style.display = 'none';
                // 不清空RSA密钥，只是隐藏
            }
        }
        
        // 为所有表单元素添加自动保存事件监听器
         document.addEventListener('DOMContentLoaded', function() {
             const forms = document.querySelectorAll('.auto-save-form');
             forms.forEach(form => {
                 const inputs = form.querySelectorAll('input, select, textarea');
                 inputs.forEach(input => {
                     input.addEventListener('input', () => autoSave(form));
                     input.addEventListener('change', () => autoSave(form));
                 });
             });
             
             // SDK版本切换事件
             const sdkVersionSelect = document.getElementById('epay_sdk_version');
             if (sdkVersionSelect) {
                 sdkVersionSelect.addEventListener('change', handleSDKVersionChange);
                 // 初始化显示状态
                 handleSDKVersionChange();
             }
             
             // 添加导航点击事件
             document.querySelectorAll('.nav-item[data-page]').forEach(item => {
                 item.addEventListener('click', function(e) {
                     e.preventDefault();
                     const pageId = this.getAttribute('data-page');
                     showPage(pageId);
                 });
             });
             
             // 点击模态框外部关闭
             window.addEventListener('click', function(event) {
                 const modal = document.getElementById('addMethodModal');
                 if (event.target === modal) {
                     closeAddMethodModal();
                 }
             });
         });
    </script>
</body>
</html>