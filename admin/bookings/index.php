<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');


$auth = new SecureAuth($db);
requireLogin();
requirePermission('bookings.view');

$page_title = 'Booking Management';

// Handle status updates
if ($_POST && isset($_POST['action'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $booking_id = (int)$_POST['booking_id'];
            $action = $_POST['action'];
            
            switch ($action) {
                case 'confirm':
                    if (hasPermission('bookings.approve')) {
                        $stmt = $db->prepare("
                            UPDATE bookings 
                            SET status = 'confirmed', confirmed_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$booking_id]);
                        
                        // Send confirmation email
                        // TODO: Implement email sending
                        
                        $auth->logActivity($_SESSION['user_id'], 'booking_confirmed', "Confirmed booking ID: $booking_id");
                        $success = 'Booking confirmed successfully!';
                    }
                    break;
                    
                case 'cancel':
                    if (hasPermission('bookings.edit')) {
                        $reason = $_POST['cancellation_reason'] ?? '';
                        $stmt = $db->prepare("
                            UPDATE bookings 
                            SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$reason, $booking_id]);
                        
                        $auth->logActivity($_SESSION['user_id'], 'booking_cancelled', "Cancelled booking ID: $booking_id");
                        $success = 'Booking cancelled successfully!';
                    }
                    break;
                    
                case 'refund':
                    if (hasPermission('payments.refund')) {
                        $refund_amount = (float)$_POST['refund_amount'];
                        
                        // Update booking status
                        $stmt = $db->prepare("UPDATE bookings SET status = 'refunded' WHERE id = ?");
                        $stmt->execute([$booking_id]);
                        
                        // Create refund record
                        $stmt = $db->prepare("
                            INSERT INTO payments (booking_id, payment_reference, amount, payment_method, status, refunded_at, refund_amount)
                            VALUES (?, ?, ?, 'refund', 'completed', NOW(), ?)
                        ");
                        $stmt->execute([$booking_id, 'REF-' . uniqid(), -$refund_amount, $refund_amount]);
                        
                        $auth->logActivity($_SESSION['user_id'], 'booking_refunded', "Refunded booking ID: $booking_id, Amount: $refund_amount");
                        $success = 'Refund processed successfully!';
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error processing request: ' . $e->getMessage();
        }
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$tour_filter = $_GET['tour'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$agent_filter = $_GET['agent'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(b.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "b.status = ?";
    $params[] = $status_filter;
}

if ($tour_filter) {
    $where_conditions[] = "b.tour_id = ?";
    $params[] = $tour_filter;
}

if ($date_from) {
    $where_conditions[] = "b.tour_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "b.tour_date <= ?";
    $params[] = $date_to;
}

if ($agent_filter) {
    $where_conditions[] = "b.agent_id = ?";
    $params[] = $agent_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN tours t ON b.tour_id = t.id
    $where_clause
";
$total_bookings = $db->prepare($count_sql);
$total_bookings->execute($params);
$total_count = $total_bookings->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get bookings
$sql = "
    SELECT b.*, 
           u.first_name, u.last_name, u.email, u.phone,
           t.title as tour_title, t.featured_image as tour_image,
           c.name as country_name,
           agent.first_name as agent_first_name, agent.last_name as agent_last_name,
           COALESCE(SUM(p.amount), 0) as paid_amount
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN users agent ON b.agent_id = agent.id
    LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'completed'
    $where_clause
    GROUP BY b.id
    ORDER BY $sort $order
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$tours = $db->query("SELECT id, title FROM tours WHERE status = 'active' ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
$agents = $db->query("
    SELECT u.id, u.first_name, u.last_name 
    FROM users u 
    JOIN mca_agents ma ON u.id = ma.user_id 
    WHERE ma.status = 'active' 
    ORDER BY u.first_name, u.last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_bookings,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as bookings_30d,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN total_amount END), 0) as confirmed_revenue
    FROM bookings
")->fetch(PDO::FETCH_ASSOC);
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
        .booking-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #ecf0f1;
        }
        
        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .booking-info {
            flex: 1;
        }
        
        .booking-reference {
            font-size: 1.3em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .booking-date {
            color: #7f8c8d;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .booking-date i {
            color: #3498db;
        }
        
        .booking-status {
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .booking-status.pending { background: #f39c12; color: white; }
        .booking-status.confirmed { background: #2ecc71; color: white; }
        .booking-status.cancelled { background: #e74c3c; color: white; }
        .booking-status.completed { background: #3498db; color: white; }
        .booking-status.refunded { background: #95a5a6; color: white; }
        
        .booking-content {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 25px;
            align-items: center;
        }
        
        .tour-image {
            width: 100px;
            height: 75px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .tour-placeholder {
            width: 100px;
            height: 75px;
            border-radius: 10px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #95a5a6;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .booking-details {
            flex: 1;
        }
        
        .tour-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 10px;
        }
        
        .customer-name {
            font-weight: 500;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .customer-name i {
            color: #3498db;
        }
        
        .customer-email {
            color: #7f8c8d;
            font-size: 0.95em;
        }
        
        .booking-meta {
            display: flex;
            gap: 25px;
            font-size: 0.95em;
            color: #7f8c8d;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .meta-item i {
            color: #3498db;
        }
        
        .booking-amount {
            text-align: right;
        }
        
        .total-amount {
            font-size: 1.4em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .payment-status {
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .payment-status.paid { color: #2ecc71; }
        .payment-status.partial { color: #f39c12; }
        .payment-status.pending { color: #e74c3c; }
        
        .payment-status i {
            font-size: 1.1em;
        }
        
        .booking-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }
        
        .booking-actions .btn {
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .booking-actions .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            text-align: center;
            border-left: 5px solid #3498db;
            transition: transform 0.3s ease;
            border: 1px solid #ecf0f1;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.pending { border-left-color: #f39c12; }
        .stat-card.confirmed { border-left-color: #2ecc71; }
        .stat-card.cancelled { border-left-color: #e74c3c; }
        .stat-card.revenue { border-left-color: #3498db; }
        
        .stat-value {
            font-size: 2.2em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid #ecf0f1;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .quick-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .quick-filter {
            padding: 10px 20px;
            border: 1px solid #ecf0f1;
            border-radius: 25px;
            background: white;
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.95em;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .quick-filter.active,
        .quick-filter:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
            transform: translateY(-3px);
        }
        
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
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="content">
                <div class="content-header">
                    <div class="content-title">
                        <h2>Booking Management</h2>
                        <p>Manage customer bookings, payments, and tour confirmations</p>
                    </div>
                    <div class="content-actions">
                        <a href="export.php" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export
                        </a>
                        <?php if (hasPermission('bookings.create')): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Manual Booking
                        </a>
                        <?php endif; ?>
                    </div>
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
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_bookings']); ?></div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                    <div class="stat-card pending">
                        <div class="stat-value"><?php echo number_format($stats['pending_bookings']); ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card confirmed">
                        <div class="stat-value"><?php echo number_format($stats['confirmed_bookings']); ?></div>
                        <div class="stat-label">Confirmed</div>
                    </div>
                    <div class="stat-card cancelled">
                        <div class="stat-value"><?php echo number_format($stats['cancelled_bookings']); ?></div>
                        <div class="stat-label">Cancelled</div>
                    </div>
                    <div class="stat-card revenue">
                        <div class="stat-value">$<?php echo number_format($stats['confirmed_revenue']); ?></div>
                        <div class="stat-label">Confirmed Revenue</div>
                    </div>
                </div>
                
                <!-- Quick Filters -->
                <div class="quick-filters">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" 
                       class="quick-filter <?php echo !$status_filter ? 'active' : ''; ?>">
                        All Bookings
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'pending'])); ?>" 
                       class="quick-filter <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                        Pending
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'confirmed'])); ?>" 
                       class="quick-filter <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">
                        Confirmed
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'cancelled'])); ?>" 
                       class="quick-filter <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                        Cancelled
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['date_from' => date('Y-m-d'), 'date_to' => ''])); ?>" 
                       class="quick-filter">
                        Today's Tours
                    </a>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Booking reference, customer name, email..." class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Tour</label>
                                <select name="tour" class="form-control">
                                    <option value="">All Tours</option>
                                    <?php foreach ($tours as $tour): ?>
                                        <option value="<?php echo $tour['id']; ?>" 
                                                <?php echo $tour_filter == $tour['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tour['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Agent</label>
                                <select name="agent" class="form-control">
                                    <option value="">All Agents</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>" 
                                                <?php echo $agent_filter == $agent['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Bookings List -->
                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Bookings Found</h3>
                        <p>No bookings match your current filters. Try adjusting your search criteria.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                <div class="booking-info">
                                    <div class="booking-reference">#<?php echo htmlspecialchars($booking['booking_reference']); ?></div>
                                    <div class="booking-date">
                                        Booked: <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?>
                                    </div>
                                </div>
                                <span class="booking-status <?php echo $booking['status']; ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>
                            
                            <div class="booking-content">
                                <div>
                                    <?php if ($booking['tour_image']): ?>
                                        <img src="../../<?php echo htmlspecialchars($booking['tour_image']); ?>" 
                                             alt="Tour" class="tour-image">
                                    <?php else: ?>
                                        <div class="tour-placeholder">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="booking-details">
                                    <div class="tour-title"><?php echo htmlspecialchars($booking['tour_title']); ?></div>
                                    
                                    <div class="customer-info">
                                        <div class="customer-name">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                        </div>
                                        <div class="customer-email"><?php echo htmlspecialchars($booking['email']); ?></div>
                                    </div>
                                    
                                    <div class="booking-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M j, Y', strtotime($booking['tour_date'])); ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-users"></i>
                                            <?php echo $booking['adults']; ?> Adults
                                            <?php if ($booking['children'] > 0): ?>
                                                , <?php echo $booking['children']; ?> Children
                                            <?php endif; ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($booking['country_name']); ?>
                                        </div>
                                        <?php if ($booking['agent_first_name']): ?>
                                            <div class="meta-item">
                                                <i class="fas fa-handshake"></i>
                                                <?php echo htmlspecialchars($booking['agent_first_name'] . ' ' . $booking['agent_last_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="booking-amount">
                                    <div class="total-amount">$<?php echo number_format($booking['total_amount'], 2); ?></div>
                                    <div class="payment-status <?php echo $booking['paid_amount'] >= $booking['total_amount'] ? 'paid' : ($booking['paid_amount'] > 0 ? 'partial' : 'pending'); ?>">
                                        <?php if ($booking['paid_amount'] >= $booking['total_amount']): ?>
                                            <i class="fas fa-check-circle"></i> Paid
                                        <?php elseif ($booking['paid_amount'] > 0): ?>
                                            <i class="fas fa-clock"></i> Partial ($<?php echo number_format($booking['paid_amount'], 2); ?>)
                                        <?php else: ?>
                                            <i class="fas fa-exclamation-circle"></i> Pending Payment
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="booking-actions">
                                <a href="view.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                
                                <?php if ($booking['status'] === 'pending' && hasPermission('bookings.approve')): ?>
                                    <button onclick="confirmBooking(<?php echo $booking['id']; ?>)" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Confirm
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (in_array($booking['status'], ['pending', 'confirmed']) && hasPermission('bookings.edit')): ?>
                                    <button onclick="cancelBooking(<?php echo $booking['id']; ?>)" class="btn btn-sm btn-warning">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($booking['status'] === 'confirmed' && $booking['paid_amount'] > 0 && hasPermission('payments.refund')): ?>
                                    <button onclick="refundBooking(<?php echo $booking['id']; ?>, <?php echo $booking['paid_amount']; ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-undo"></i> Refund
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('bookings.edit')): ?>
                                    <a href="edit.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                <?php endif; ?>
                                
                                <a href="invoice.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                    <i class="fas fa-file-invoice"></i> Invoice
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total_count)); ?> 
                            of <?php echo number_format($total_count); ?> bookings
                        </div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = ma($page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Cancellation Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cancel Booking</h3>
                <span class="close" onclick="closeCancelModal()">&times;</span>
            </div>
            <form method="POST" id="cancelForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="booking_id" id="cancelBookingId">
                
                <div class="form-group">
                    <label for="cancellation_reason">Cancellation Reason</label>
                    <textarea id="cancellation_reason" name="cancellation_reason" class="form-control" rows="3" 
                              placeholder="Please provide a reason for cancellation..."></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeCancelModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Cancel Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Refund Modal -->
    <div id="refundModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Process Refund</h3>
                <span class="close" onclick="closeRefundModal()">&times;</span>
            </div>
            <form method="POST" id="refundForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="refund">
                <input type="hidden" name="booking_id" id="refundBookingId">
                
                <div class="form-group">
                    <label for="refund_amount">Refund Amount</label>
                    <input type="number" id="refund_amount" name="refund_amount" class="form-control" 
                           step="0.01" min="0" required>
                    <div style="font-size: 0.9em; color: #666; margin-top: 5px;">
                        Maximum refundable amount: $<span id="maxRefund">0.00</span>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeRefundModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-undo"></i> Process Refund
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function confirmBooking(bookingId) {
            if (confirm('Are you sure you want to confirm this booking?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function cancelBooking(bookingId) {
            document.getElementById('cancelBookingId').value = bookingId;
            document.getElementById('cancelModal').style.display = 'block';
        }
        
        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }
        
        function refundBooking(bookingId, paidAmount) {
            document.getElementById('refundBookingId').value = bookingId;
            document.getElementById('refund_amount').value = paidAmount;
            document.getElementById('refund_amount').max = paidAmount;
            document.getElementById('maxRefund').textContent = paidAmount.toFixed(2);
            document.getElementById('refundModal').style.display = 'block';
        }
        
        function closeRefundModal() {
            document.getElementById('refundModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const cancelModal = document.getElementById('cancelModal');
            const refundModal = document.getElementById('refundModal');
            
            if (event.target === cancelModal) {
                closeCancelModal();
            }
            if (event.target === refundModal) {
                closeRefundModal();
            }
        }
        
        // Auto-submit filters on change
        document.querySelectorAll('.filters-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Auto-submit date filters
        document.querySelectorAll('.filters-form input[type="date"]').forEach(input => {
            input.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
