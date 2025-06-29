<?php
/**
 * 系统入口页面
 * 自动跳转到安装页面或管理后台
 */

require_once 'includes/Database.php';

try {
    $db = new Database();
    
    if ($db->isInstalled()) {
        // 系统已安装，跳转到管理后台
        header('Location: admin.php');
    } else {
        // 系统未安装，跳转到安装页面
        header('Location: install.php');
    }
    
} catch (Exception $e) {
    // 数据库连接失败，跳转到安装页面
    header('Location: install.php');
}

exit; 