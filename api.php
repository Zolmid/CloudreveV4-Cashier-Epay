<?php
/**
 * Cloudreve支付API接口
 * 处理Cloudreve的支付请求
 */

require_once 'includes/Database.php';
require_once 'includes/Logger.php';
require_once 'includes/Validator.php';
require_once 'includes/DomainValidator.php';
require_once 'includes/SignatureValidator.php';

// 检查系统是否已安装
try {
    $db = new Database();
    if (!$db->isInstalled()) {
        http_response_code(500);
        echo json_encode(['code' => 1, 'message' => '系统未安装']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['code' => 1, 'message' => '系统错误']);
    exit;
}

// 获取配置
$cashierUrl = $db->getConfig('cashier_url', '');

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    // 验证签名
    $signatureValidator = new SignatureValidator($db);
    if (!$signatureValidator->verify()) {
        http_response_code(401);
        echo json_encode(['code' => 1, 'message' => '签名验证失败']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 创建订单
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('无效的请求数据');
        }
        
        // 验证必填字段
        $requiredFields = ['name', 'amount', 'notify_url', 'order_no'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                throw new Exception("缺少必填字段: {$field}");
            }
        }
        
        // 验证金额
        if (!is_numeric($input['amount']) || $input['amount'] <= 0) {
            throw new Exception('无效的金额');
        }
        
        // 验证通知URL
        if (!filter_var($input['notify_url'], FILTER_VALIDATE_URL)) {
            throw new Exception('无效的通知URL');
        }

        // 验证回调域名
        $validator = new DomainValidator($db);
        if (!$validator->verify_callback($input['notify_url'])) {
            throw new Exception('不允许的回调URL');
        }
        
        // 使用Cloudreve传入的订单号
        $orderNo = $input['order_no'];
        
        // 创建订单数据
        $orderData = [
            'order_no' => $orderNo,
            'name' => $input['name'],
            'amount' => (int)$input['amount'], // Cloudreve已传入分为单位，无需转换
            'currency' => $input['currency'] ?? 'CNY',
            'status' => 'pending',
            'notify_url' => $input['notify_url'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // 保存到数据库
        try {
            $orderId = $db->createOrder($orderData);
            
            Logger::info("订单创建成功", [
                'order_no' => $orderNo,
                'order_id' => $orderId,
                'amount' => $orderData['amount'],
                'name' => $orderData['name'],
                'cloudreve_order_no' => $input['order_no']
            ]);
        } catch (Exception $e) {
            Logger::error("订单创建失败", [
                'order_no' => $orderNo,
                'error' => $e->getMessage(),
                'cloudreve_order_no' => $input['order_no']
            ]);
            throw $e;
        }
        
        // 生成收银台URL
        $cashierUrl = rtrim($cashierUrl, '/') . '/checkout.php?order_no=' . $orderNo;
        
        // 返回Cloudreve要求的格式
        echo json_encode([
            'code' => 0,
            'data' => $cashierUrl
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 查询订单状态
        $orderNo = $_GET['order_no'] ?? '';
        
        // 记录查询请求
        Logger::info('订单状态查询请求', [
            'order_no' => $orderNo,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'all_params' => $_GET
        ]);
        
        if (empty($orderNo)) {
            throw new Exception('缺少订单号');
        }
        
        $order = $db->getOrderByNo($orderNo);
        
        if (!$order) {
            // 记录订单不存在的详细信息
            Logger::warning('订单查询失败 - 订单不存在', [
                'order_no' => $orderNo,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            throw new Exception('订单不存在');
        }
        
        // 记录成功查询
        Logger::info('订单状态查询成功', [
            'order_no' => $orderNo,
            'status' => $order['status'],
            'amount' => $order['amount']
        ]);
        
        // 返回订单状态（按照Cloudreve V4规范）
        $status = ($order['status'] === 'paid') ? 'PAID' : 'UNPAID';
        
        echo json_encode([
            'code' => 0,
            'data' => $status
        ]);
        
        Logger::info('订单状态响应', [
            'order_no' => $orderNo,
            'db_status' => $order['status'],
            'response_status' => $status
        ]);
        
    } else {
        throw new Exception('不支持的请求方法');
    }
    
} catch (Exception $e) {
    Logger::error("API错误", [
        'error' => $e->getMessage(),
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    http_response_code(400);
    echo json_encode([
        'code' => 1,
        'message' => $e->getMessage()
    ]);
}