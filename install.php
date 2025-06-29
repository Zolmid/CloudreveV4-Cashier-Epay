<?php
/**
 * 系统安装页面
 * 用于初次访问时进行系统配置
 */

require_once 'includes/Database.php';
require_once 'includes/Logger.php';

// 检查是否已安装
try {
    $db = new Database();
    if ($db->isInstalled()) {
        header('Location: admin.php');
        exit;
    }
} catch (Exception $e) {
    // 数据库连接失败，继续安装流程
}

$error = '';
$success = '';

// 处理安装表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        
        // 验证必填字段
        $requiredFields = [
            'epay_apiurl' => 'epay接口地址',
            'epay_pid' => 'epay商户ID',
            'cashier_url' => '收银台URL',
            'cashier_name' => '收银台名称',
            'admin_password' => '管理员密码'
        ];
        
        // 根据SDK版本添加相应的必填字段
        if ($_POST['epay_sdk_version'] === '2.0') {
            $requiredFields['epay_platform_public_key'] = '平台公钥';
            $requiredFields['epay_merchant_private_key'] = '商户私钥';
        } else {
            $requiredFields['epay_key'] = 'epay商户密钥';
        }
        
        foreach ($requiredFields as $field => $label) {
            if (empty($_POST[$field])) {
                throw new Exception("请填写{$label}");
            }
        }
        
        // 初始化默认配置
        $db->initDefaultConfigs();
        
        // 更新用户输入的配置
        $db->setConfig('epay_apiurl', $_POST['epay_apiurl']);
        $db->setConfig('epay_pid', $_POST['epay_pid']);
        $db->setConfig('epay_sdk_version', $_POST['epay_sdk_version']);
        
        // 根据 SDK 版本保存相应配置
        if ($_POST['epay_sdk_version'] === '2.0') {
            $db->setConfig('epay_platform_public_key', $_POST['epay_platform_public_key']);
            $db->setConfig('epay_merchant_private_key', $_POST['epay_merchant_private_key']);
            $db->setConfig('epay_key', ''); // 清空 MD5 密钥
        } else {
            $db->setConfig('epay_key', $_POST['epay_key']);
            $db->setConfig('epay_platform_public_key', ''); // 清空 RSA 密钥
            $db->setConfig('epay_merchant_private_key', '');
        }
        
        $db->setConfig('cashier_url', $_POST['cashier_url']);
        $db->setConfig('cashier_name', $_POST['cashier_name']);
        
        // 更新管理员密码
        $adminConfig = $db->getConfig('admin_config', []);
        $adminConfig['password'] = $_POST['admin_password'];
        $db->setConfig('admin_config', $adminConfig, 'json');
        
        // 更新域名配置

        if (!empty($_POST['allowed_callback_domains'])) {
            $db->setConfig('allowed_callback_domains', $_POST['allowed_callback_domains']);
        }
        
        // 标记系统为已安装
        $db->markAsInstalled();
        
        Logger::info("系统安装完成", [
            'epay_apiurl' => $_POST['epay_apiurl'],
            'cashier_url' => $_POST['cashier_url']
        ]);
        
        $success = '系统安装成功！正在跳转到管理后台...';
        
        // 3秒后跳转到管理后台
        header("refresh:3;url=admin.php");
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 获取当前URL作为默认收银台地址
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$currentPath = dirname($_SERVER['REQUEST_URI']);
$defaultCashierUrl = $currentUrl . $currentPath;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - Cloudreve支付收银台</title>
    <link rel="stylesheet" href="assets/css/common.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }
        
        .install-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .install-title {
            font-size: 28px;
            font-weight: 600;
            color: #212529;
            margin-bottom: 10px;
        }
        
        .install-subtitle {
            color: #6c757d;
            font-size: 16px;
        }
        
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .install-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .install-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .install-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .install-btn:hover::before {
            left: 100%;
        }
        
        .install-btn:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .help-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #212529;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .install-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <div class="install-title">系统安装</div>
            <div class="install-subtitle">Cloudreve支付收银台初始配置</div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="section-title">epay配置</div>
            
            <div class="form-group">
                <label for="epay_apiurl">epay接口地址 *</label>
                <input type="url" id="epay_apiurl" name="epay_apiurl" value="<?php echo htmlspecialchars($_POST['epay_apiurl'] ?? 'http://pay.www.com/'); ?>" required>
                <div class="help-text">您的epay支付接口地址，以/结尾</div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="epay_pid">商户ID *</label>
                    <input type="text" id="epay_pid" name="epay_pid" value="<?php echo htmlspecialchars($_POST['epay_pid'] ?? '1000'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="epay_sdk_version">SDK版本 *</label>
                    <select id="epay_sdk_version" name="epay_sdk_version" required onchange="toggleSDKFields()">
                        <option value="1.0" <?php echo ($_POST['epay_sdk_version'] ?? '1.0') === '1.0' ? 'selected' : ''; ?>>SDK 1.0 (MD5签名)</option>
                        <option value="2.0" <?php echo ($_POST['epay_sdk_version'] ?? '1.0') === '2.0' ? 'selected' : ''; ?>>SDK 2.0 (RSA签名)</option>
                    </select>
                </div>
            </div>
            
            <div id="sdk1_fields" style="<?php echo ($_POST['epay_sdk_version'] ?? '1.0') === '2.0' ? 'display:none' : ''; ?>">
                <div class="form-group">
                    <label for="epay_key">商户密钥 *</label>
                    <input type="text" id="epay_key" name="epay_key" value="<?php echo htmlspecialchars($_POST['epay_key'] ?? ''); ?>">
                    <div class="help-text">SDK 1.0 使用的 MD5 密钥</div>
                </div>
            </div>
            
            <div id="sdk2_fields" style="<?php echo ($_POST['epay_sdk_version'] ?? '1.0') === '1.0' ? 'display:none' : ''; ?>">
                <div class="form-group">
                    <label for="epay_platform_public_key">平台公钥 *</label>
                    <textarea id="epay_platform_public_key" name="epay_platform_public_key" rows="4" placeholder="请输入平台公钥内容（不包含BEGIN/END标识）"><?php echo htmlspecialchars($_POST['epay_platform_public_key'] ?? ''); ?></textarea>
                    <div class="help-text">SDK 2.0 用于验证回调签名的平台公钥</div>
                </div>
                
                <div class="form-group">
                    <label for="epay_merchant_private_key">商户私钥 *</label>
                    <textarea id="epay_merchant_private_key" name="epay_merchant_private_key" rows="4" placeholder="请输入商户私钥内容（不包含BEGIN/END标识）"><?php echo htmlspecialchars($_POST['epay_merchant_private_key'] ?? ''); ?></textarea>
                    <div class="help-text">SDK 2.0 用于生成请求签名的商户私钥</div>
                </div>
            </div>
            
            <div class="section-title">收银台配置</div>
            
            <div class="form-group">
                <label for="cashier_url">收银台URL *</label>
                <input type="url" id="cashier_url" name="cashier_url" value="<?php echo htmlspecialchars($_POST['cashier_url'] ?? $defaultCashierUrl); ?>" required>
                <div class="help-text">收银台的完整访问地址</div>
            </div>
            
            <div class="form-group">
                <label for="cashier_name">收银台名称 *</label>
                <input type="text" id="cashier_name" name="cashier_name" value="<?php echo htmlspecialchars($_POST['cashier_name'] ?? '云盘支付收银台'); ?>" required>
            </div>
            
            <div class="section-title">安全配置</div>
            
            <div class="form-group">
                <label for="admin_password">管理员密码 *</label>
                <input type="password" id="admin_password" name="admin_password" value="<?php echo htmlspecialchars($_POST['admin_password'] ?? ''); ?>" required>
                <div class="help-text">用于访问管理后台的密码</div>
            </div>
            

            <div class="form-group">
                <label for="allowed_callback_domains">允许的回调域名</label>
                <input type="text" id="allowed_callback_domains" name="allowed_callback_domains" value="<?php echo htmlspecialchars($_POST['allowed_callback_domains'] ?? ''); ?>" placeholder="例如: www.your-cloudreve.com">
                <div class="help-text">允许接收支付成功回调通知的Cloudreve域名，多个请用英文逗号隔开。</div>
            </div>
            
            <button type="submit" class="install-btn">开始安装</button>
        </form>
        
        <div style="margin-top: 30px; text-align: center; color: #6c757d; font-size: 14px;">
            <p>安装完成后，您可以在管理后台修改所有配置项</p>
        </div>
    </div>
    
    <script>
    function toggleSDKFields() {
        const sdkVersion = document.getElementById('epay_sdk_version').value;
        const sdk1Fields = document.getElementById('sdk1_fields');
        const sdk2Fields = document.getElementById('sdk2_fields');
        const epayKey = document.getElementById('epay_key');
        const platformKey = document.getElementById('epay_platform_public_key');
        const merchantKey = document.getElementById('epay_merchant_private_key');
        
        if (sdkVersion === '2.0') {
            sdk1Fields.style.display = 'none';
            sdk2Fields.style.display = 'block';
            epayKey.removeAttribute('required');
            platformKey.setAttribute('required', 'required');
            merchantKey.setAttribute('required', 'required');
        } else {
            sdk1Fields.style.display = 'block';
            sdk2Fields.style.display = 'none';
            epayKey.setAttribute('required', 'required');
            platformKey.removeAttribute('required');
            merchantKey.removeAttribute('required');
        }
    }
    
    // 页面加载时初始化
    document.addEventListener('DOMContentLoaded', function() {
        toggleSDKFields();
    });
    </script>
</body>
</html>