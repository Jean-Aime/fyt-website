<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('store.view');

$page_title = 'Order Management';

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'])) {
    try {
        $action = $_POST['action'];
        $order_id = (int)$_POST['order_id'];
        
        if ($action === 'update_status' && hasPermission('store.edit')) {
            $status = $_POST['status'];
            $notes = sanitizeInput($_POST['notes'] ?? '');
            
            $stmt = $db->prepare("
                UPDATE store_orders SET status = ?, notes = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $notes, $order_id]);
            
            // Log status change
            $auth->logActivity($_SESSION['user_id'], 'order_status_updated', "Updated order #$order_id status to $status");
            
            // Send notification email to customer
            if ($status === 'shipped') {
                // Send shipping notification
            } elseif ($status === 'delivered') {
                // Send delivery confirmation
            }
            
            $success = 'Order status updated successfully!';
            
        } elseif ($action === 'process_refund' && hasPermission('store.refund')) {
            $refund_amount = (float)$_POST['refund_amount'];
            $refund_reason = sanitizeInput($_POST['refund_reason']);
            
            // Update order status
            $stmt = $db->prepare("
                UPDATE store_orders SET status = 'refunded', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$order_id]);
            
            // Process refund (integrate with payment gateway)
            // This would typically involve calling the payment gateway's refund API
            
            $auth->logActivity($_SESSION['user_id'], 'order_refunded', "Processed refund for order #$order_id: $refund_amount");
            $success = 'Refund processed successfully!';
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = ['1=1'];
$params = [];

if ($status_filter) {
    $where_conditions[] = 'o.status = ?';
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = '(o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($date_from) {
    $where_conditions[] = 'DATE(o.created_at) >= ?';
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = 'DATE(o.created_at) <= ?';
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "
    SELECT COUNT(*)
    FROM store_orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE $where_clause
";
$total_stmt = $db->prepare($count_query);
$total_stmt->execute($params);
$total_orders = $total_stmt->fetchColumn();

// Get orders
$orders_query = "
    SELECT o.*, u.first_name, u.last_name, u.email,
           (SELECT COUNT(*) FROM store_order_items WHERE order_id = o.id) as item_count
    FROM store_orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE $where_clause
    ORDER BY o.$sort $order
    LIMIT $per_page OFFSET $offset
";
$orders_stmt = $db->prepare($orders_query);
$orders_stmt->execute($params);
$orders = $orders_stmt->fetchAll();

$total_pages = ceil($total_orders / $per_page);

// Get order statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
        COUNT(CASE WHEN status = 'shipped' THEN 1 END) as shipped_orders,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_order_value
    FROM store_orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .orders-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .order-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.processing { border-left-color: #17a2b8; }
        .stat-card.shipped { border-left-color: #28a745; }
        .stat-card.delivered { border-left-color: #6f42c1; }
        .stat-card.revenue { border-left-color: #fd7e14; }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .orders-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-row {
            display: grid;
            grid-template-columns: 120px 200px 150px 120px 120px 150px 100px 150px;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
            transition: background 0.3s ease;
        }
        
        .order-row:hover {
            background: #f8f9fa;
        }
        
        .order-number {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .customer-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .customer-name {
            font-weight: 600;
            color: #333;
        }
        
        .customer-email {
            font-size: 0.8em;
            color: #666;
        }
        
        .order-status {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
            text-align: center;
        }
        
        .order-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .order-status.processing {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .order-status.shipped {
            background: #d4edda;
            color: #155724;
        }
        
        .order-status.delivered {
            background: #e2e3f0;
            color: #383d41;
        }
        
        .order-status.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-status.refunded {
            background: #ffeaa7;
            color: #6c757d;
        }
        
        .order-amount {
            font-weight: 600;
            color: #333;
            text-align: right;
        }
        
        .order-date {
            font-size: 0.9em;
            color: #666;
        }
        
        .order-actions {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            padding: 6px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.3s ease;
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-edit {
            background: #28a745;
            color: white;
        }
        
        .btn-print {
            background: #6c757d;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #333;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #666;
        }
        
        .order-items {
            margin: 20px 0;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: #333;
        }
        
        .item-details {
            font-size: 0.9em;
            color: #666;
        }
        
        .item-price {
            font-weight: 600;
            color: #333;
        }
        
        .status-update-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .order-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .order-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="content">
                <!-- Orders Header -->
                <div class="orders-header">
                    <h1>Order Management</h1>
                    <p>Manage customer orders, process payments, and track shipments</p>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Order Statistics -->
                <div class="order-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
                        <div class="stat-label">Total Orders (30d)</div>
                    </div>
                    <div class="stat-card pending">
                        <div class="stat-value"><?php echo number_format($stats['pending_orders']); ?></div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                    <div class="stat-card processing">
                        <div class="stat-value"><?php echo number_format($stats['processing_orders']); ?></div>
                        <div class="stat-label">Processing</div>
                    </div>
                    <div class="stat-card shipped">
                        <div class="stat-value"><?php echo number_format($stats['shipped_orders']); ?></div>
                        <div class="stat-label">Shipped</div>
                    </div>
                    <div class="stat-card delivered">
                        <div class="stat-value"><?php echo number_format($stats['delivered_orders']); ?></div>
                        <div class="stat-label">Delivered</div>
                    </div>
                    <div class="stat-card revenue">
                        <div class="stat-value"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                        <div class="stat-label">Revenue (30d)</div>
                    </div>
                </div>
                
                <!-- Orders Table -->
                <div class="orders-table">
                    <div class="table-header">
                        <h2>Orders (<?php echo number_format($total_orders); ?>)</h2>
                        <div class="table-actions">
                            <button class="btn btn-outline" onclick="exportOrders()">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="btn btn-secondary" onclick="printOrders()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="filters-section" style="padding: 20px; border-bottom: 1px solid #eee;">
                        <form method="GET" class="filters-form" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search orders..." class="form-control" style="width: 200px;">
                            
                            <select name="status" class="form-control" style="width: 150px;">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                            
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                                   class="form-control" style="width: 150px;">
                            
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                                   class="form-control" style="width: 150px;">
                            
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="orders.php" class="btn btn-outline">Reset</a>
                        </form>
                    </div>
                    
                    <div class="table-content">
                        <!-- Table Header -->
                        <div class="order-row" style="background: #f8f9fa; font-weight: 600;">
                            <div>Order #</div>
                            <div>Customer</div>
                            <div>Date</div>
                            <div>Items</div>
                            <div>Amount</div>
                            <div>Payment</div>
                            <div>Status</div>
                            <div>Actions</div>
                        </div>
                        
                        <!-- Order Rows -->
                        <?php foreach ($orders as $order): ?>
                        <div class="order-row">
                            <div class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                            
                            <div class="customer-info">
                                <div class="customer-name"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div>
                                <div class="customer-email"><?php echo htmlspecialchars($order['email']); ?></div>
                            </div>
                            
                            <div class="order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                            
                            <div style="text-align: center;"><?php echo $order['item_count']; ?> items</div>
                            
                            <div class="order-amount"><?php echo formatCurrency($order['total_amount']); ?></div>
                            
                            <div style="text-align: center;">
                                <span style="padding: 4px 8px; border-radius: 10px; font-size: 0.7em; background: <?php echo $order['payment_status'] === 'paid' ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo $order['payment_status'] === 'paid' ? '#155724' : '#856404'; ?>;">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                            
                            <div>
                                <span class="order-status <?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            
                            <div class="order-actions">
                                <button class="action-btn btn-view" onclick="viewOrder(<?php echo $order['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn btn-edit" onclick="editOrder(<?php echo $order['id']; ?>)" title="Edit Status">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn btn-print" onclick="printOrder(<?php echo $order['id']; ?>)" title="Print Invoice">
                                    <i class="fas fa-print"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($orders)): ?>
                        <div class="empty-state" style="text-align: center; padding: 60px 20px; color: #666;">
                            <i class="fas fa-shopping-cart" style="font-size: 4em; margin-bottom: 20px; color: #ddd;"></i>
                            <h3>No orders found</h3>
                            <p>No orders match your current filters</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="padding: 20px; text-align: center;">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>" 
                               style="padding: 8px 12px; margin: 0 2px; text-decoration: none; border-radius: 4px; <?php echo $i === $page ? 'background: var(--primary-color); color: white;' : 'background: #f8f9fa; color: #333;'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Order Details</h3>
                <span class="close" onclick="closeOrderModal()">&times;</span>
            </div>
            
            <div id="orderDetailsContent">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        function viewOrder(orderId) {
            // Load order details via AJAX
            fetch(`../api/get-order-details.php?id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayOrderDetails(data.order);
                        document.getElementById('orderModal').style.display = 'block';
                    } else {
                        alert('Error loading order details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading order details');
                });
        }
        
        function displayOrderDetails(order) {
            const content = document.getElementById('orderDetailsContent');
            content.innerHTML = `
                <div class="order-details">
                    <div class="detail-group">
                        <div class="detail-label">Order Number</div>
                        <div class="detail-value">#${order.order_number}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Customer</div>
                        <div class="detail-value">${order.customer_name}<br>${order.customer_email}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Order Date</div>
                        <div class="detail-value">${new Date(order.created_at).toLocaleDateString()}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <span class="order-status ${order.status}">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span>
                        </div>
                    </div>
                </div>
                
                <div class="order-items">
                    <h4>Order Items</h4>
                    ${order.items.map(item => `
                        <div class="item-row">
                            <div class="item-info">
                                <div class="item-name">${item.product_name}</div>
                                <div class="item-details">Qty: ${item.quantity} × ${formatCurrency(item.price)}</div>
                            </div>
                            <div class="item-price">${formatCurrency(item.quantity * item.price)}</div>
                        </div>
                    `).join('')}
                    
                    <div class="item-row" style="border-top: 2px solid #333; font-weight: 600;">
                        <div class="item-info">Total</div>
                        <div class="item-price">${formatCurrency(order.total_amount)}</div>
                    </div>
                </div>
                
                <div class="status-update-form">
                    <h4>Update Order Status</h4>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="${order.id}">
                        
                        <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px;">
                            <select name="status" class="form-control" style="width: 150px;">
                                <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>Processing</option>
                                <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Shipped</option>
                                <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Delivered</option>
                                <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </div>
                        
                        <textarea name="notes" class="form-control" rows="3" placeholder="Add notes (optional)...">${order.notes || ''}</textarea>
                    </form>
                </div>
            `;
        }
        
        function editOrder(orderId) {
            viewOrder(orderId);
        }
        
        function printOrder(orderId) {
            window.open(`../api/print-order.php?id=${orderId}`, '_blank');
        }
        
        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
        
        function exportOrders() {
            const params = new URLSearchParams(window.location.search);
            window.open(`../api/export-orders.php?${params.toString()}`, '_blank');
        }
        
        function printOrders() {
            const params = new URLSearchParams(window.location.search);
            window.open(`../api/print-orders.php?${params.toString()}`, '_blank');
        }
        
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                closeOrderModal();
            }
        }
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
