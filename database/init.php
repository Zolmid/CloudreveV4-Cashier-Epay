<?php
/**
 * 数据库初始化脚本
 */

require_once dirname(__FILE__) . '/../includes/Database.php';

try {
    $db = new Database();
    $db->initDefaultConfigs();
    echo "数据库初始化成功！\n";
} catch (Exception $e) {
    echo "数据库初始化失败: " . $e->getMessage() . "\n";
}