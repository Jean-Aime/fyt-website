<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('payments.view');

$page_title = 'Payment Management';

// Handle refund processing
if ($_POST && isset($_POST['process_refund'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $payment_id = (int) $_POST['payment_id'];
            $refund_amount = (float) $_POST['refund_amount'];
            $refund_reason = sanitizeInput($_POST['refund_reason']);

            // Get payment details
            $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch();

            if (!$payment) {
                throw new Exception('Payment not found');
            }

            if ($refund_amount > $payment['amount']) {
                throw new Exception('Refund amount cannot exceed payment amount');
            }

            // Process refund based on payment method
            $refund_success = false;
            $gateway_response = [];

            switch ($payment['payment_method']) {
                case 'stripe':
                    $refund_success = processStripeRefund($payment['gateway_transaction_id'], $refund_amount);
                    break;
                case 'paypal':
                    $refund_success = processPayPalRefund($payment['gateway_transaction_id'], $refund_amount);
                    break;
                default:
                    // Manual refund
                    $refund_success = true;
                    break;
            }

            if ($refund_success) {
                // Update payment record
                $stmt = $db->prepare("
                    UPDATE payments SET 
                        status = 'refunded', 
                        refunded_at = NOW(), 
                        refund_amount = ?, 
                        refund_reason = ?,
                        gateway_response = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $refund_amount,
                    $refund_reason,
                    json_encode($gateway_response),
                    $payment_id
                ]);

                // Update booking status if full refund
                if ($refund_amount >= $payment['amount']) {
                    $db->prepare("UPDATE bookings SET status = 'refunded' WHERE id = ?")
                        ->execute([$payment['booking_id']]);
                }

                // Log activity
                $auth->logActivity(
                    $_SESSION['user_id'],
                    'payment_refunded',
                    "Refunded payment ID: $payment_id, Amount: " . formatCurrency($refund_amount)
                );

                $success = 'Refund processed successfully';
            } else {
                throw new Exception('Failed to process refund with payment gateway');
            }

        } catch (Exception $e) {
            $error = 'Error processing refund: ' . $e->getMessage();
        }
    }
}

// Handle payment status update
if ($_POST && isset($_POST['update_status'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $payment_id = (int) $_POST['payment_id'];
            $new_status = $_POST['new_status'];
            $notes = sanitizeInput($_POST['notes']);

            $stmt = $db->prepare("
                UPDATE payments SET 
                    status = ?, 
                    updated_at = NOW(),
                    admin_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $notes, $payment_id]);

            $auth->logActivity(
                $_SESSION['user_id'],
                'payment_status_updated',
                "Updated payment ID: $payment_id to status: $new_status"
            );

            $success = 'Payment status updated successfully';

        } catch (Exception $e) {
            $error = 'Error updating payment status: ' . $e->getMessage();
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$method_filter = $_GET['method'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = ['p.created_at BETWEEN ? AND ?'];
$params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

if ($status_filter) {
    $where_conditions[] = 'p.status = ?';
    $params[] = $status_filter;
}

if ($method_filter) {
    $where_conditions[] = 'p.payment_method = ?';
    $params[] = $method_filter;
}

if ($search) {
    $where_conditions[] = '(p.payment_reference LIKE ? OR b.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "
    SELECT COUNT(*)
    FROM payments p
    LEFT JOIN bookings b ON p.booking_id = b.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE $where_clause
";
$total_stmt = $db->prepare($count_query);
$total_stmt->execute($params);
$total_payments = $total_stmt->fetchColumn();

// Get payments
$payments_query = "
    SELECT p.*, 
           b.booking_reference, b.tour_date,
           u.first_name, u.last_name, u.email,
           t.title as tour_title
    FROM payments p
    LEFT JOIN bookings b ON p.booking_id = b.id
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN tours t ON b.tour_id = t.id
    WHERE $where_clause
    ORDER BY p.$sort $order
    LIMIT $per_page OFFSET $offset
";
$payments_stmt = $db->prepare($payments_query);
$payments_stmt->execute($params);
$payments = $payments_stmt->fetchAll();

$total_pages = ceil($total_payments / $per_page);

// Get payment statistics
$stats = $db->prepare("
    SELECT 
        COUNT(*) as total_payments,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_payments,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
        COUNT(CASE WHEN status = 'refunded' THEN 1 END) as refunded_payments,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN amount END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN status = 'refunded' THEN refund_amount END), 0) as total_refunds
    FROM payments 
    WHERE created_at BETWEEN ? AND ?
");
$stats->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
$payment_stats = $stats->fetch();

function processStripeRefund($transaction_id, $amount)
{
    // Stripe refund implementation
    return true; // Placeholder
}

function processPayPalRefund($transaction_id, $amount)
{
    // PayPal refund implementation
    return true; // Placeholder
}
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
        .payments-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .payment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--admin-primary);
        }

        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--admin-primary);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .payments-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
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

        .payment-row {
            display: grid;
            grid-template-columns: 1fr 150px 120px 120px 100px 150px 120px;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
            transition: background 0.3s ease;
        }

        .payment-row:hover {
            background: #f8f9fa;
        }

        .payment-row:last-child {
            border-bottom: none;
        }

        .payment-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .payment-reference {
            font-weight: 600;
            color: #333;
        }

        .payment-customer {
            color: #666;
            font-size: 0.9em;
        }

        .payment-booking {
            color: var(--admin-primary);
            font-size: 0.9em;
            text-decoration: none;
        }

        .payment-booking:hover {
            text-decoration: underline;
        }

        .payment-amount {
            font-weight: 600;
            color: #333;
            text-align: right;
        }

        .payment-method {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
        }

        .method-icon {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.7em;
        }

        .method-stripe {
            background: #635bff;
        }

        .method-paypal {
            background: #0070ba;
        }

        .method-bank {
            background: #28a745;
        }

        .method-cash {
            background: #6c757d;
        }

        .payment-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
            text-align: center;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .status-refunded {
            background: #e2e3e5;
            color: #383d41;
        }

        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }

        .payment-date {
            color: #666;
            font-size: 0.9em;
        }

        .payment-actions {
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

        .btn-refund {
            background: #dc3545;
            color: white;
        }

        .btn-update {
            background: #28a745;
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
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            position: relative;
        }

        .modal-close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 1.5em;
            cursor: pointer;
            color: #999;
        }

        .auto-refresh {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--admin-primary);
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            font-size: 0.9em;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .payment-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .filters-grid {
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
                <div class="payments-header">
                    <h1>Payment Management</h1>
                    <p>Monitor and manage all payment transactions</p>
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

                <!-- Statistics -->
                <div class="payment-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($payment_stats['total_payments']); ?></div>
                        <div class="stat-label">Total Payments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo formatCurrency($payment_stats['total_revenue']); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($payment_stats['completed_payments']); ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($payment_stats['pending_payments']); ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo formatCurrency($payment_stats['total_refunds']); ?></div>
                        <div class="stat-label">Total Refunds</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Payment ref, booking ref, customer..." class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>
                                        Pending</option>
                                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>
                                        Failed</option>
                                    <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="method" class="form-control">
                                    <option value="">All Methods</option>
                                    <option value="stripe" <?php echo $method_filter === 'stripe' ? 'selected' : ''; ?>>
                                        Credit Card</option>
                                    <option value="paypal" <?php echo $method_filter === 'paypal' ? 'selected' : ''; ?>>
                                        PayPal</option>
                                    <option value="bank_transfer" <?php echo $method_filter === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                    <option value="cash" <?php echo $method_filter === 'cash' ? 'selected' : ''; ?>>Cash
                                    </option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" value="<?php echo $date_from; ?>"
                                    class="form-control">
                            </div>

                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-control">
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="index.php" class="btn btn-outline">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Payments Table -->
                <div class="payments-table">
                    <div class="table-header">
                        <h2>Payments (<?php echo number_format($total_payments); ?>)</h2>
                        <div class="table-actions">
                            <button class="btn btn-outline" onclick="exportPayments()">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="btn btn-primary" onclick="refreshPayments()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="table-content">
                        <!-- Table Header -->
                        <div class="payment-row" style="background: #f8f9fa; font-weight: 600;">
                            <div>Payment Details</div>
                            <div>Amount</div>
                            <div>Method</div>
                            <div>Status</div>
                            <div>Date</div>
                            <div>Customer</div>
                            <div>Actions</div>
                        </div>

                        <!-- Payment Rows -->
                        <?php foreach ($payments as $payment): ?>
                            <div class="payment-row">
                                <div class="payment-info">
                                    <div class="payment-reference">
                                        <?php echo htmlspecialchars($payment['payment_reference']); ?></div>
                                    <a href="../bookings/view.php?id=<?php echo $payment['booking_id']; ?>"
                                        class="payment-booking">
                                        <?php echo htmlspecialchars($payment['booking_reference']); ?>
                                    </a>
                                    <?php if ($payment['tour_title']): ?>
                                        <div style="font-size: 0.8em; color: #999;">
                                            <?php echo htmlspecialchars($payment['tour_title']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="payment-amount">
                                    <?php echo formatCurrency($payment['amount'], $payment['currency']); ?>
                                    <?php if ($payment['refund_amount'] > 0): ?>
                                        <div style="font-size: 0.8em; color: #dc3545;">
                                            Refunded:
                                            <?php echo formatCurrency($payment['refund_amount'], $payment['currency']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="payment-method">
                                    <div class="method-icon method-<?php echo $payment['payment_method']; ?>">
                                        <?php
                                        $method_icons = [
                                            'stripe' => 'fab fa-cc-stripe',
                                            'paypal' => 'fab fa-paypal',
                                            'bank_transfer' => 'fas fa-university',
                                            'cash' => 'fas fa-money-bill'
                                        ];
                                        ?>
                                        <i
                                            class="<?php echo $method_icons[$payment['payment_method']] ?? 'fas fa-credit-card'; ?>"></i>
                                    </div>
                                    <span><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></span>
                                </div>

                                <div>
                                    <span class="payment-status status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </div>

                                <div class="payment-date">
                                    <?php echo date('M j, Y', strtotime($payment['created_at'])); ?>
                                    <div style="font-size: 0.8em; color: #999;">
                                        <?php echo date('g:i A', strtotime($payment['created_at'])); ?>
                                    </div>
                                </div>

                                <div class="payment-customer">
                                    <div>
                                        <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                    </div>
                                    <div style="font-size: 0.8em; color: #999;">
                                        <?php echo htmlspecialchars($payment['email']); ?>
                                    </div>
                                </div>

                                <div class="payment-actions">
                                    <button class="action-btn btn-view" onclick="viewPayment(<?php echo $payment['id']; ?>)"
                                        title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <?php if ($payment['status'] === 'completed' && $payment['refund_amount'] < $payment['amount']): ?>
                                        <button class="action-btn btn-refund"
                                            onclick="openRefundModal(<?php echo $payment['id']; ?>)" title="Process Refund">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($payment['status'], ['pending', 'failed'])): ?>
                                        <button class="action-btn btn-update"
                                            onclick="openStatusModal(<?php echo $payment['id']; ?>)" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($payments)): ?>
                            <div class="empty-state">
                                <i class="fas fa-credit-card"></i>
                                <h3>No payments found</h3>
                                <p>No payments match your current filters</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                    class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Refund Modal -->
    <div class="modal" id="refundModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeRefundModal()">&times;</span>
            <h3>Process Refund</h3>

            <form method="POST" id="refundForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="payment_id" id="refundPaymentId">

                <div class="form-group">
                    <label>Refund Amount</label>
                    <input type="number" name="refund_amount" id="refundAmount" step="0.01" class="form-control"
                        required>
                    <small class="form-text">Maximum refundable amount: <span id="maxRefundAmount"></span></small>
                </div>

                <div class="form-group">
                    <label>Refund Reason</label>
                    <select name="refund_reason" class="form-control" required>
                        <option value="">Select reason...</option>
                        <option value="customer_request">Customer Request</option>
                        <option value="tour_cancelled">Tour Cancelled</option>
                        <option value="duplicate_payment">Duplicate Payment</option>
                        <option value="service_issue">Service Issue</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="closeRefundModal()">Cancel</button>
                    <button type="submit" name="process_refund" class="btn btn-danger">Process Refund</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeStatusModal()">&times;</span>
            <h3>Update Payment Status</h3>

            <form method="POST" id="statusForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="payment_id" id="statusPaymentId">

                <div class="form-group">
                    <label>New Status</label>
                    <select name="new_status" class="form-control" required>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="3"
                        placeholder="Add notes about this status change..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Auto-refresh indicator -->
    <div class="auto-refresh" id="autoRefresh">
        <i class="fas fa-sync-alt"></i> Auto-refresh in <span id="refreshTimer">60</span>s
    </div>

    <script>
        // Refund modal functions
        function openRefundModal(paymentId) {
            document.getElementById('refundPaymentId').value = paymentId;

            // Get payment details to set max refund amount
            fetch(`../api/get-payment.php?id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const maxRefund = data.payment.amount - data.payment.refund_amount;
                        document.getElementById('refundAmount').max = maxRefund;
                        document.getElementById('refundAmount').value = maxRefund;
                        document.getElementById('maxRefundAmount').textContent = formatCurrency(maxRefund);
                    }
                });

            document.getElementById('refundModal').style.display = 'block';
        }

        function closeRefundModal() {
            document.getElementById('refundModal').style.display = 'none';
        }

        // Status modal functions
        function openStatusModal(paymentId) {
            document.getElementById('statusPaymentId').value = paymentId;
            document.getElementById('statusModal').style.display = 'block';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        // View payment details
        function viewPayment(paymentId) {
            window.open(`view.php?id=${paymentId}`, '_blank');
        }

        // Export payments
        function exportPayments() {
            const params = new URLSearchParams(window.location.search);
            window.open(`../api/export-payments.php?${params.toString()}`, '_blank');
        }

        // Refresh payments
        function refreshPayments() {
            location.reload();
        }

        // Auto-refresh functionality
        let refreshInterval = 60;
        const refreshTimer = document.getElementById('refreshTimer');

        setInterval(() => {
            refreshInterval--;
            refreshTimer.textContent = refreshInterval;

            if (refreshInterval <= 0) {
                location.reload();
            }
        }, 1000);

        // Format currency helper
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        }

        // Close modals when clicking outside
        window.onclick = function (event) {
            const refundModal = document.getElementById('refundModal');
            const statusModal = document.getElementById('statusModal');

            if (event.target === refundModal) {
                closeRefundModal();
            }
            if (event.target === statusModal) {
                closeStatusModal();
            }
        }
    </script>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>