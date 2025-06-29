<?php
/**
 * 管理后台Ajax API
 * 用于动态加载订单数据和统计信息
 */

require_once 'includes/Database.php';
require_once 'includes/Logger.php';

// 启动会话
session_start();

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// 检查登录状态
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => '未登录']);
    exit;
}

try {
    $db = new Database();
    
    // 检查会话超时
    $adminConfig = $db->getConfig('admin_config');
    if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > ($adminConfig['session_timeout'] ?? 3600)) {
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['admin_login_time']);
        http_response_code(401);
        echo json_encode(['error' => '会话已过期']);
        exit;
    }
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_orders':
            handleGetOrders($db);
            break;
            
        case 'get_stats':
            handleGetStats($db);
            break;
            
        case 'clean_expired':
            handleCleanExpired($db);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => '无效的操作']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * 处理获取订单列表请求
 */
function handleGetOrders($db) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 20))); // 限制每页最多100条
    $status = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    
    $offset = ($page - 1) * $limit;
    
    // 构建查询条件
    $where = [];
    $params = [];
    
    if ($status && in_array($status, ['pending', 'processing', 'paid'])) {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }
    
    if ($search) {
        $where[] = '(order_no LIKE :search OR name LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // 获取总数
    $countSql = "SELECT COUNT(*) as total FROM orders $whereClause";
    $countStmt = $db->getPdo()->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // 获取订单数据
    $sql = "
        SELECT * FROM orders 
        $whereClause
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->getPdo()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $orders = $stmt->fetchAll();
    
    // 格式化订单数据
    $paymentConfig = $db->getConfig('payment_config');
    foreach ($orders as &$order) {
        $order['formatted_amount'] = formatAmount($order['amount'], $order['currency'], $paymentConfig);
        $order['formatted_created_at'] = date('Y-m-d H:i', strtotime($order['created_at']));
        $order['status_text'] = getStatusText($order['status']);
    }
    
    $totalPages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'orders' => $orders,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $total,
                'per_page' => $limit,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages
            ]
        ]
    ]);
}

/**
 * 处理获取统计信息请求
 */
function handleGetStats($db) {
    $stats = $db->getOrderStats();
    $paymentConfig = $db->getConfig('payment_config');
    
    $stats['formatted_total_amount'] = formatAmount($stats['total_amount'], 'CNY', $paymentConfig);
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}

/**
 * 处理清理过期订单请求
 */
function handleCleanExpired($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => '方法不允许']);
        return;
    }
    
    $db->cleanExpiredOrders();
    
    echo json_encode([
        'success' => true,
        'message' => '过期订单清理完成'
    ]);
}

/**
 * 格式化金额
 */
function formatAmount($amount, $currency, $paymentConfig) {
    $currencySymbol = $paymentConfig['currency_symbols'][$currency] ?? '$';
    $precision = $paymentConfig['amount_precision'] ?? 2;
    return $currencySymbol . number_format($amount / 100, $precision);
}

/**
 * 获取状态文本
 */
function getStatusText($status) {
    $statusNames = [
        'pending' => '待支付',
        'processing' => '处理中',
        'paid' => '已支付'
    ];
    return $statusNames[$status] ?? $status;
}