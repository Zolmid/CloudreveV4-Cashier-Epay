<?php
/**
 * 数据库操作类
 * 使用SQLite数据库存储订单信息和系统配置
 */

// 定义数据库路径常量
if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/../database/orders.db');
}

class Database {
    private $pdo;
    
    public function __construct() {
        try {
            // 确保数据库目录存在
            $dbDir = dirname(DB_PATH);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // 创建表
            $this->createTables();
            
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 创建数据库表
     */
    private function createTables() {
        // 订单表
        $sql = "
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_no VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                amount INTEGER NOT NULL,
                currency VARCHAR(10) NOT NULL,
                notify_url TEXT NOT NULL,
                site_id VARCHAR(100),
                site_url TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                payment_type VARCHAR(20),
                epay_trade_no VARCHAR(100),
                paid_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->pdo->exec($sql);

        // 创建索引
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_no ON orders(order_no)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_status ON orders(status)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON orders(created_at)");
        
        // 配置表
        $sql = "
            CREATE TABLE IF NOT EXISTS configs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                config_key VARCHAR(100) UNIQUE NOT NULL,
                config_value TEXT NOT NULL,
                config_type VARCHAR(20) NOT NULL DEFAULT 'string',
                description TEXT,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )
        ";
        $this->pdo->exec($sql);
        
        // 数据库迁移：添加paid_at字段（如果不存在）
        $this->migratePaidAtField();
    }
    
    /**
     * 迁移：添加paid_at字段
     */
    private function migratePaidAtField() {
        try {
            // 检查paid_at字段是否存在
            $stmt = $this->pdo->query("PRAGMA table_info(orders)");
            $columns = $stmt->fetchAll();
            $hasPaidAt = false;
            
            foreach ($columns as $column) {
                if ($column['name'] === 'paid_at') {
                    $hasPaidAt = true;
                    break;
                }
            }
            
            // 如果不存在则添加
            if (!$hasPaidAt) {
                $this->pdo->exec("ALTER TABLE orders ADD COLUMN paid_at DATETIME");
            }
        } catch (Exception $e) {
            // 忽略迁移错误，可能字段已存在
        }
    }
    
    /**
     * 检查系统是否已安装
     */
    public function isInstalled() {
        $sql = "SELECT COUNT(*) as count FROM configs WHERE config_key = 'system_installed'";
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    /**
     * 标记系统为已安装
     */
    public function markAsInstalled() {
        $this->setConfig('system_installed', 'true', 'boolean', '系统安装标记');
    }
    
    /**
     * 设置配置项
     */
    public function setConfig($key, $value, $type = 'string', $description = '') {
        $sql = "
            INSERT OR REPLACE INTO configs (config_key, config_value, config_type, description, created_at, updated_at)
            VALUES (:key, :value, :type, :description, :created_at, :updated_at)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':key' => $key,
            ':value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
            ':type' => $type,
            ':description' => $description,
            ':created_at' => date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * 获取配置项
     */
    public function getConfig($key, $default = null) {
        $sql = "SELECT config_value, config_type FROM configs WHERE config_key = :key";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return $default;
        }
        
        $value = $result['config_value'];
        $type = $result['config_type'];
        
        // 根据类型转换值
        switch ($type) {
            case 'boolean':
                return $value === 'true' || $value === '1';
            case 'integer':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'array':
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    /**
     * 获取所有配置项
     */
    public function getAllConfigs() {
        $sql = "SELECT * FROM configs ORDER BY config_key";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * 删除配置项
     */
    public function deleteConfig($key) {
        $sql = "DELETE FROM configs WHERE config_key = :key";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':key' => $key]);
    }
    
    /**
     * 初始化默认配置
     */
    public function initDefaultConfigs() {
        $defaultConfigs = [
            // epay配置
            'epay_apiurl' => ['value' => 'http://pay.www.com/', 'type' => 'string', 'desc' => 'epay接口地址'],
            'epay_pid' => ['value' => '1000', 'type' => 'string', 'desc' => 'epay商户ID'],
            'epay_key' => ['value' => 'WWc3Z2jkK7jhNGPALcGKjHLPK47wRK85', 'type' => 'string', 'desc' => 'epay商户密钥'],
            'epay_sdk_version' => ['value' => '1.0', 'type' => 'string', 'desc' => 'epay SDK版本 (1.0或2.0)'],
            'epay_platform_public_key' => ['value' => '', 'type' => 'text', 'desc' => 'epay平台公钥 (SDK2.0)'],
            'epay_merchant_private_key' => ['value' => '', 'type' => 'text', 'desc' => 'epay商户私钥 (SDK2.0)'],

            // 安全配置
            'allowed_callback_domains' => ['value' => '', 'type' => 'string', 'desc' => '允许接收回调的域名，多个用逗号隔开'],
            
            // 收银台配置
            'cashier_url' => ['value' => 'http://localhost/cashier', 'type' => 'string', 'desc' => '收银台URL'],
            'cashier_name' => ['value' => '云盘支付收银台', 'type' => 'string', 'desc' => '收银台名称'],
            
            // 支付方式配置
            'payment_methods' => [
                'value' => [
                    'alipay' => ['enabled' => true, 'name' => '支付宝', 'icon' => '💰', 'description' => '推荐使用支付宝APP扫码支付'],
                    'wxpay' => ['enabled' => true, 'name' => '微信支付', 'icon' => '💳', 'description' => '推荐使用微信扫码支付'],
                    'qqpay' => ['enabled' => false, 'name' => 'QQ钱包', 'icon' => '📱', 'description' => '使用QQ钱包支付'],
                    'bank' => ['enabled' => false, 'name' => '云闪付', 'icon' => '🏦', 'description' => '使用云闪付支付'],
                    'jdpay' => ['enabled' => false, 'name' => '京东支付', 'icon' => '🛒', 'description' => '使用京东支付']
                ],
                'type' => 'json',
                'desc' => '支付方式配置'
            ],
            
            // 支付方式兼容性配置
            'payment_compatibility' => [
                'value' => [
                    'wxpay' => ['wechat', 'alipay', 'qq', 'mobile', 'desktop'],
                    'alipay' => ['wechat', 'alipay', 'qq', 'mobile', 'desktop'],
                    'qqpay' => ['wechat', 'alipay', 'qq', 'mobile', 'desktop'],
                    'bank' => ['mobile'],
                    'jdpay' => ['mobile']
                ],
                'type' => 'json',
                'desc' => '支付方式兼容性配置'
            ],
            
            // 环境推荐配置
            'environment_recommendations' => [
                'value' => [
                    'wechat' => 'wxpay',
                    'alipay' => 'alipay',
                    'qq' => 'qqpay',
                    'mobile' => 'alipay',
                    'desktop' => 'alipay'
                ],
                'type' => 'json',
                'desc' => '环境推荐支付方式配置'
            ],
            
            // 自动跳转配置
            'auto_redirect_config' => [
                'value' => [
                    'enabled' => true,
                    'environments' => [
                        'wechat' => 'wxpay',
                        'alipay' => 'alipay'
                    ]
                ],
                'type' => 'json',
                'desc' => '自动跳转配置'
            ],
            
            // UI配置
            'ui_config' => [
                'value' => [
                    'theme' => [
                        'primary_color' => '#a0aec0',
                'secondary_color' => '#718096',
                'success_color' => '#48bb78',
                'warning_color' => '#ed8936',
                'danger_color' => '#f56565',
                'info_color' => '#4fd1c7'
                    ],
                    'layout' => [
                        'max_width' => '500px',
                        'border_radius' => '20px',
                        'box_shadow' => '0 20px 40px rgba(0, 0, 0, 0.1)'
                    ],
                    'payment_grid' => [
                        'columns' => 2,
                        'mobile_columns' => 1
                    ]
                ],
                'type' => 'json',
                'desc' => 'UI主题配置'
            ],
            
            // 管理后台配置
            'admin_config' => [
                'value' => [
                    'password' => 'admin123',
                    'session_timeout' => 3600,
                    'orders_per_page' => 50,
                    'cleanup_expired_hours' => 24
                ],
                'type' => 'json',
                'desc' => '管理后台配置'
            ],
            
            // 支付处理配置
            'payment_config' => [
                'value' => [
                    'timeout' => 10,
                    'retry_times' => 3,
                    'retry_interval' => 5,
                    'amount_precision' => 2,
                    'currency_symbols' => [
                        'CNY' => '¥',
                        'USD' => '$',
                        'EUR' => '€',
                        'JPY' => '¥'
                    ]
                ],
                'type' => 'json',
                'desc' => '支付处理配置'
            ],
            
            // 安全配置
            'security_config' => [
                'value' => [
                    'csrf_protection' => true,
                    'rate_limit' => [
                        'enabled' => true,
                        'max_requests' => 100,
                        'time_window' => 3600
                    ],
                    'input_sanitization' => true,
                    'xss_protection' => true
                ],
                'type' => 'json',
                'desc' => '安全配置'
            ],
            
            // 调试配置
            'debug_config' => [
                'value' => [
                    'enabled' => false,
                    'log_level' => 'info',
                    'show_errors' => false,
                    'log_user_agent' => true
                ],
                'type' => 'json',
                'desc' => '调试配置'
            ]
        ];
        
        foreach ($defaultConfigs as $key => $config) {
            $this->setConfig($key, $config['value'], $config['type'], $config['desc']);
        }
    }
    
    /**
     * 创建订单
     */
    public function createOrder($data) {
        // 检查订单号是否已存在
        $existingOrder = $this->getOrderByNo($data['order_no']);
        if ($existingOrder) {
            throw new Exception('订单号已存在: ' . $data['order_no']);
        }
        
        $sql = "
            INSERT INTO orders (
                order_no, name, amount, currency, status, 
                notify_url, created_at, updated_at
            ) VALUES (
                :order_no, :name, :amount, :currency, :status,
                :notify_url, :created_at, :updated_at
            )
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':order_no' => $data['order_no'],
            ':name' => $data['name'],
            ':amount' => $data['amount'],
            ':currency' => $data['currency'],
            ':status' => $data['status'],
            ':notify_url' => $data['notify_url'],
            ':created_at' => $data['created_at'],
            ':updated_at' => $data['created_at']
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 根据订单号获取订单
     */
    public function getOrderByNo($orderNo) {
        $sql = "SELECT * FROM orders WHERE order_no = :order_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_no' => $orderNo]);
        
        return $stmt->fetch();
    }
    
    /**
     * 更新订单状态
     */
    public function updateOrderStatus($orderNo, $status, $paymentType = null) {
        $currentTime = date('Y-m-d H:i:s');
        
        // 如果状态为成功，同时设置paid_at字段
        if ($status === 'success') {
            $sql = "
                UPDATE orders 
                SET status = :status, 
                    payment_type = :payment_type,
                    paid_at = :paid_at,
                    updated_at = :updated_at
                WHERE order_no = :order_no
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':payment_type' => $paymentType,
                ':paid_at' => $currentTime,
                ':updated_at' => $currentTime,
                ':order_no' => $orderNo
            ]);
        } else {
            $sql = "
                UPDATE orders 
                SET status = :status, 
                    payment_type = :payment_type,
                    updated_at = :updated_at
                WHERE order_no = :order_no
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':payment_type' => $paymentType,
                ':updated_at' => $currentTime,
                ':order_no' => $orderNo
            ]);
        }
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 标记订单为已支付
     */
    public function markOrderAsPaid($orderNo) {
        $sql = "
            UPDATE orders 
            SET status = 'paid', 
                paid_at = :paid_at,
                updated_at = :updated_at
            WHERE order_no = :order_no
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':paid_at' => date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s'),
            ':order_no' => $orderNo
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * 获取所有订单
     */
    public function getAllOrders($limit = null) {
        $limit = $limit ?? $this->getConfig('admin_config')['orders_per_page'] ?? 50;
        
        $sql = "
            SELECT * FROM orders 
            ORDER BY created_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT :limit";
        }
        
        $stmt = $this->pdo->prepare($sql);
        
        if ($limit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * 获取订单统计信息
     */
    public function getOrderStats() {
        $sql = "
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_amount
            FROM orders
        ";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch();
    }
    
    /**
     * 清理过期订单
     */
    public function cleanExpiredOrders() {
        $expiredHours = $this->getConfig('admin_config')['cleanup_expired_hours'] ?? 24;
        $cutoffTime = date('Y-m-d H:i:s', time() - ($expiredHours * 3600));
        
        $sql = "
            DELETE FROM orders 
            WHERE status = 'pending' 
            AND created_at < :cutoff_time
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cutoff_time' => $cutoffTime]);
        
        $deletedCount = $stmt->rowCount();
        
        if ($deletedCount > 0) {
            Logger::info("清理过期订单", [
                'deleted_count' => $deletedCount,
                'cutoff_time' => $cutoffTime
            ]);
        }
        
        return $deletedCount;
    }
    
    /**
     * 获取数据库大小
     */
    public function getDatabaseSize() {
        $dbPath = __DIR__ . '/../database/orders.db';
        if (!file_exists($dbPath)) {
            return 0;
        }
        
        return filesize($dbPath);
    }
    
    /**
     * 备份数据库
     */
    public function backupDatabase($backupPath = null) {
        $dbPath = __DIR__ . '/../database/orders.db';
        
        if ($backupPath === null) {
            $backupPath = dirname($dbPath) . '/backup_' . date('Y-m-d_H-i-s') . '.db';
        }
        
        if (copy($dbPath, $backupPath)) {
            Logger::info("数据库备份成功", ['backup_path' => $backupPath]);
            return $backupPath;
        } else {
            throw new Exception('数据库备份失败');
        }
    }
    
    /**
     * 优化数据库
     */
    public function optimizeDatabase() {
        $this->pdo->exec('VACUUM');
        $this->pdo->exec('ANALYZE');
        
        Logger::info("数据库优化完成");
    }
    
    /**
     * 检查数据库连接
     */
    public function checkConnection() {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * 获取PDO对象
     */
    public function getPdo() {
        return $this->pdo;
    }
}