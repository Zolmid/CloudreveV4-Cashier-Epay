<?php
/**
 * 管理后台页面
 * 用于查看订单和系统状态
 */

require_once 'includes/Database.php';
require_once 'includes/Logger.php';

// 启动会话
session_start();

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

// 处理退出登录
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// 简单的访问控制（生产环境建议使用更安全的认证方式）
$adminConfig = $db->getConfig('admin_config');
$adminPassword = $adminConfig['password'] ?? 'admin123';

if (isset($_POST['password'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
    } else {
        $error = '密码错误';
    }
}

// 检查会话超时
if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > ($adminConfig['session_timeout'] ?? 3600)) {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_login_time']);
}

if (!isset($_SESSION['admin_logged_in'])) {
    // 显示登录页面
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理后台登录 - <?php echo $db->getConfig('cashier_name', '云盘支付收银台'); ?></title>
        <link rel="stylesheet" href="assets/css/common.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                padding: 20px;
            }
            
            .login-container {
                background: white;
                border-radius: 20px;
                padding: 40px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                max-width: 400px;
                width: 100%;
            }
            
            .login-title {
                text-align: center;
                margin-bottom: 30px;
                color: #212529;
                font-size: 24px;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-title">管理后台登录</div>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="password">管理员密码</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="login-btn">登录</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 获取统计信息（初始加载）
try {
    $stats = $db->getOrderStats();
    $totalOrders = $stats['total_orders'];
    $paidOrders = $stats['paid_orders'];
    $pendingOrders = $stats['pending_orders'];
    $totalAmount = $stats['total_amount'];
} catch (Exception $e) {
    $error = $e->getMessage();
    $totalOrders = $paidOrders = $pendingOrders = $totalAmount = 0;
}

// 处理清理过期订单
if (isset($_POST['clean_expired'])) {
    try {
        $db->cleanExpiredOrders();
        $success = '过期订单清理完成';
    } catch (Exception $e) {
        $error = '清理失败: ' . $e->getMessage();
    }
}

// 获取系统配置
$uiConfig = $db->getConfig('ui_config');
$paymentConfig = $db->getConfig('payment_config');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo $db->getConfig('cashier_name', '云盘支付收银台'); ?></title>
    <link rel="stylesheet" href="assets/css/common.css">
    <style>
        body {
            background: #f8f9fa;
        }
        
        .header {
            background: linear-gradient(135deg, <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?> 0%, <?php echo $uiConfig['theme']['secondary_color'] ?? '#764ba2'; ?> 100%);
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
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        .stat-card.success .stat-number {
            color: <?php echo $uiConfig['theme']['success_color'] ?? '#28a745'; ?>;
        }
        
        .stat-card.warning .stat-number {
            color: <?php echo $uiConfig['theme']['warning_color'] ?? '#ffc107'; ?>;
        }
        
        .stat-card.info .stat-number {
            color: <?php echo $uiConfig['theme']['info_color'] ?? '#17a2b8'; ?>;
        }
        
        .actions {
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filters {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 500;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?>;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }
        
        .orders-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 1fr 1fr 1fr;
            gap: 15px;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            align-items: center;
        }
        
        .table-row:hover {
            background: #f8f9fa;
        }
        
        .table-row:last-child {
            border-bottom: none;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }
        
        .pagination button {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #f8f9fa;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination button.active {
            background: <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?>;
            color: white;
            border-color: <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?>;
        }
        
        .pagination-info {
            font-size: 14px;
            color: #6c757d;
            margin: 0 15px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .loading::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid <?php echo $uiConfig['theme']['primary_color'] ?? '#667eea'; ?>;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .table-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .table-row::before {
                content: attr(data-label);
                font-weight: 600;
                color: #6c757d;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo $db->getConfig('cashier_name', '云盘支付收银台'); ?> - 管理后台</h1>
    </div>
    
    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card info">
                <div class="stat-number"><?php echo $totalOrders; ?></div>
                <div class="stat-label">总订单数</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number"><?php echo $paidOrders; ?></div>
                <div class="stat-label">已支付订单</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $pendingOrders; ?></div>
                <div class="stat-label">待支付订单</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number"><?php 
                    $currencySymbol = $paymentConfig['currency_symbols']['CNY'] ?? '¥';
                    echo $currencySymbol . number_format($totalAmount / 100, $paymentConfig['amount_precision'] ?? 2); 
                ?></div>
                <div class="stat-label">总收入</div>
            </div>
        </div>
        
        <div class="actions">
            <button type="button" id="cleanExpiredBtn" class="btn">清理过期订单</button>
            <button type="button" id="refreshBtn" class="btn">刷新数据</button>
            <a href="config_manager.php" class="btn">配置管理</a>
            <a href="?logout=1" class="btn btn-secondary">退出登录</a>
        </div>
        
        <div class="filters">
            <div class="filter-group">
                <label for="statusFilter">订单状态</label>
                <select id="statusFilter">
                    <option value="">全部状态</option>
                    <option value="pending">待支付</option>
                    <option value="processing">处理中</option>
                    <option value="paid">已支付</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="searchInput">搜索订单</label>
                <input type="text" id="searchInput" placeholder="订单号或商品名称">
            </div>
            <div class="filter-group">
                <label for="limitSelect">每页显示</label>
                <select id="limitSelect">
                    <option value="10">10条</option>
                    <option value="20" selected>20条</option>
                    <option value="50">50条</option>
                    <option value="100">100条</option>
                </select>
            </div>
        </div>
        
        <div class="orders-table">
            <div class="table-header">
                订单列表
                <div style="float: right; font-weight: normal; font-size: 12px; color: #6c757d;" id="orderCount">
                    加载中...
                </div>
            </div>
            
            <div id="ordersContainer">
                <div class="loading">正在加载订单数据</div>
            </div>
            
            <div class="pagination" id="pagination" style="display: none;">
                <button id="prevBtn" disabled>上一页</button>
                <div id="pageNumbers"></div>
                <button id="nextBtn" disabled>下一页</button>
                <div class="pagination-info" id="paginationInfo"></div>
            </div>
        </div>
    </div>
    
    <script>
    // 全局变量
    let currentPage = 1;
    let currentLimit = 20;
    let currentStatus = '';
    let currentSearch = '';
    let isLoading = false;
    
    // DOM元素
    const ordersContainer = document.getElementById('ordersContainer');
    const pagination = document.getElementById('pagination');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const pageNumbers = document.getElementById('pageNumbers');
    const paginationInfo = document.getElementById('paginationInfo');
    const orderCount = document.getElementById('orderCount');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');
    const limitSelect = document.getElementById('limitSelect');
    const cleanExpiredBtn = document.getElementById('cleanExpiredBtn');
    const refreshBtn = document.getElementById('refreshBtn');
    
    // 初始化
    document.addEventListener('DOMContentLoaded', function() {
        loadOrders();
        bindEvents();
    });
    
    // 绑定事件
    function bindEvents() {
        // 筛选器事件
        statusFilter.addEventListener('change', function() {
            currentStatus = this.value;
            currentPage = 1;
            loadOrders();
        });
        
        // 搜索事件（防抖）
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearch = this.value.trim();
                currentPage = 1;
                loadOrders();
            }, 500);
        });
        
        // 每页显示数量变化
        limitSelect.addEventListener('change', function() {
            currentLimit = parseInt(this.value);
            currentPage = 1;
            loadOrders();
        });
        
        // 分页按钮
        prevBtn.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                loadOrders();
            }
        });
        
        nextBtn.addEventListener('click', function() {
            currentPage++;
            loadOrders();
        });
        
        // 清理过期订单
        cleanExpiredBtn.addEventListener('click', function() {
            if (confirm('确定要清理过期订单吗？')) {
                cleanExpiredOrders();
            }
        });
        
        // 刷新数据
        refreshBtn.addEventListener('click', function() {
            loadOrders();
            loadStats();
        });
    }
    
    // 加载订单数据
    function loadOrders() {
        if (isLoading) return;
        
        isLoading = true;
        ordersContainer.innerHTML = '<div class="loading">正在加载订单数据</div>';
        pagination.style.display = 'none';
        
        const params = new URLSearchParams({
            action: 'get_orders',
            page: currentPage,
            limit: currentLimit,
            status: currentStatus,
            search: currentSearch
        });
        
        fetch('admin_api.php?' + params)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderOrders(data.data.orders);
                    renderPagination(data.data.pagination);
                    updateOrderCount(data.data.pagination);
                } else {
                    throw new Error(data.error || '加载失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                ordersContainer.innerHTML = `<div style="padding: 40px; text-align: center; color: #dc3545;">加载失败: ${error.message}</div>`;
                orderCount.textContent = '加载失败';
            })
            .finally(() => {
                isLoading = false;
            });
    }
    
    // 渲染订单列表
    function renderOrders(orders) {
        if (orders.length === 0) {
            ordersContainer.innerHTML = '<div style="padding: 40px; text-align: center; color: #6c757d;">暂无订单数据</div>';
            return;
        }
        
        const html = orders.map(order => `
            <div class="table-row" data-label="订单信息">
                <div data-label="订单号">${escapeHtml(order.order_no)}</div>
                <div data-label="商品名称">${escapeHtml(order.name)}</div>
                <div data-label="金额">${order.formatted_amount}</div>
                <div data-label="状态">
                    <span class="status ${order.status}">${order.status_text}</span>
                </div>
                <div data-label="支付方式">${escapeHtml(order.payment_type || '-')}</div>
                <div data-label="创建时间">${order.formatted_created_at}</div>
            </div>
        `).join('');
        
        ordersContainer.innerHTML = html;
    }
    
    // 渲染分页
     function renderPagination(paginationData) {
         if (paginationData.total_pages <= 1) {
             pagination.style.display = 'none';
             return;
         }
        
        // 更新按钮状态
         prevBtn.disabled = !paginationData.has_prev;
         nextBtn.disabled = !paginationData.has_next;
         
         // 生成页码按钮
         const pageNumbersHtml = generatePageNumbers(paginationData.current_page, paginationData.total_pages);
         pageNumbers.innerHTML = pageNumbersHtml;
         
         // 更新分页信息
         paginationInfo.textContent = `第 ${paginationData.current_page} 页，共 ${paginationData.total_pages} 页`;
         
         pagination.style.display = 'flex';
    }
    
    // 生成页码按钮
    function generatePageNumbers(currentPage, totalPages) {
        const pages = [];
        const maxVisible = 5;
        
        let start = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let end = Math.min(totalPages, start + maxVisible - 1);
        
        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }
        
        for (let i = start; i <= end; i++) {
            const isActive = i === currentPage;
            pages.push(`
                <button class="${isActive ? 'active' : ''}" onclick="goToPage(${i})">
                    ${i}
                </button>
            `);
        }
        
        return pages.join('');
    }
    
    // 跳转到指定页面
    function goToPage(page) {
        currentPage = page;
        loadOrders();
    }
    
    // 更新订单计数
    function updateOrderCount(pagination) {
        orderCount.textContent = `共 ${pagination.total_items} 条订单`;
    }
    
    // 加载统计信息
    function loadStats() {
        fetch('admin_api.php?action=get_stats')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatsDisplay(data.data);
                }
            })
            .catch(error => {
                console.error('Stats loading error:', error);
            });
    }
    
    // 更新统计显示
    function updateStatsDisplay(stats) {
        const statCards = document.querySelectorAll('.stat-card .stat-number');
        if (statCards.length >= 4) {
            statCards[0].textContent = stats.total_orders;
            statCards[1].textContent = stats.paid_orders;
            statCards[2].textContent = stats.pending_orders;
            statCards[3].textContent = stats.formatted_total_amount;
        }
    }
    
    // 清理过期订单
    function cleanExpiredOrders() {
        cleanExpiredBtn.disabled = true;
        cleanExpiredBtn.textContent = '清理中...';
        
        fetch('admin_api.php?action=clean_expired', {
            method: 'POST'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadOrders();
                    loadStats();
                } else {
                    throw new Error(data.error || '清理失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('清理失败: ' + error.message);
            })
            .finally(() => {
                cleanExpiredBtn.disabled = false;
                cleanExpiredBtn.textContent = '清理过期订单';
            });
    }
    
    // HTML转义
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
</body>
</html>