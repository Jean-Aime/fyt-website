<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('bookings.view');

$booking_id = (int)($_GET['id'] ?? 0);

if (!$booking_id) {
    header('Location: index.php');
    exit;
}

// Get booking details with all related information
$stmt = $db->prepare("
    SELECT b.*, 
           u.first_name, u.last_name, u.email, u.phone, u.date_of_birth, u.nationality, u.passport_number,
           t.title as tour_title, t.featured_image as tour_image, t.duration_days, t.duration_nights,
           c.name as country_name, c.flag_image as country_flag,
           r.name as region_name,
           cat.name as category_name,
           agent.first_name as agent_first_name, agent.last_name as agent_last_name,
           agent.email as agent_email,
           COALESCE(SUM(p.amount), 0) as paid_amount,
           COUNT(p.id) as payment_count
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN regions r ON t.region_id = r.id
    LEFT JOIN tour_categories cat ON t.category_id = cat.id
    LEFT JOIN users agent ON b.agent_id = agent.id
    LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'completed'
    WHERE b.id = ?
    GROUP BY b.id
");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: index.php?error=Booking not found');
    exit;
}

// Get booking add-ons
$stmt = $db->prepare("
    SELECT ba.*, ta.name as addon_name, ta.description as addon_description
    FROM booking_addons ba
    JOIN tour_addons ta ON ba.addon_id = ta.id
    WHERE ba.booking_id = ?
");
$stmt->execute([$booking_id]);
$addons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment history
$stmt = $db->prepare("
    SELECT * FROM payments 
    WHERE booking_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$booking_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get activity logs for this booking
$stmt = $db->prepare("
    SELECT ual.*, u.first_name, u.last_name
    FROM user_activity_logs ual
    LEFT JOIN users u ON ual.user_id = u.id
    WHERE ual.description LIKE ?
    ORDER BY ual.created_at DESC
    LIMIT 20
");
$stmt->execute(["%booking ID: {$booking_id}%"]);
$activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Booking Details - #' . $booking['booking_reference'];
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
        .booking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .booking-reference {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .booking-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        
        .booking-status.pending { background: rgba(255, 193, 7, 0.2); color: #856404; }
        .booking-status.confirmed { background: rgba(40, 167, 69, 0.2); color: #155724; }
        .booking-status.cancelled { background: rgba(220, 53, 69, 0.2); color: #721c24; }
        .booking-status.completed { background: rgba(23, 162, 184, 0.2); color: #0c5460; }
        .booking-status.refunded { background: rgba(108, 117, 125, 0.2); color: #383d41; }
        
        .booking-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .meta-item i {
            width: 20px;
            text-align: center;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .detail-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .card-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .tour-preview {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .tour-image {
            width: 120px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
        }
        
        .tour-info h3 {
            font-size: 1.2em;
            color: #333;
            margin-bottom: 8px;
        }
        
        .tour-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-label {
            font-weight: 500;
            color: #666;
        }
        
        .detail-value {
            font-weight: 600;
            color: #333;
        }
        
        .customer-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .payment-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .payment-total {
            font-size: 1.2em;
            font-weight: bold;
            padding-top: 10px;
            border-top: 2px solid #dee2e6;
        }
        
        .payment-history {
            margin-top: 20px;
        }
        
        .payment-record {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .payment-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .payment-status.completed { background: #d4edda; color: #155724; }
        .payment-status.pending { background: #fff3cd; color: #856404; }
        .payment-status.failed { background: #f8d7da; color: #721c24; }
        
        .addon-list {
            margin-top: 15px;
        }
        
        .addon-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .activity-log {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            gap: 15px;
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--admin-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-action {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .activity-time {
            font-size: 0.9em;
            color: #666;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .booking-meta {
                grid-template-columns: 1fr;
            }
            
            .tour-preview {
                flex-direction: column;
            }
            
            .customer-info {
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
                <!-- Booking Header -->
                <div class="booking-header">
                    <div class="booking-reference">#<?php echo htmlspecialchars($booking['booking_reference']); ?></div>
                    <span class="booking-status <?php echo $booking['status']; ?>">
                        <?php echo ucfirst($booking['status']); ?>
                    </span>
                    
                    <div class="booking-meta">
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>Booked: <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-plane-departure"></i>
                            <span>Tour Date: <?php echo date('M j, Y', strtotime($booking['tour_date'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo $booking['adults']; ?> Adults
                            <?php if ($booking['children'] > 0): ?>
                                , <?php echo $booking['children']; ?> Children
                            <?php endif; ?>
                            <?php if ($booking['infants'] > 0): ?>
                                , <?php echo $booking['infants']; ?> Infants
                            <?php endif; ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-dollar-sign"></i>
                            <span>$<?php echo number_format($booking['total_amount'], 2); ?> 
                            (Paid: $<?php echo number_format($booking['paid_amount'], 2); ?>)</span>
                        </div>
                    </div>
                </div>
                
                <div class="content-actions" style="margin-bottom: 20px;">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Bookings
                    </a>
                    <a href="edit.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Booking
                    </a>
                    <a href="invoice.php?id=<?php echo $booking['id']; ?>" class="btn btn-info" target="_blank">
                        <i class="fas fa-file-invoice"></i> Generate Invoice
                    </a>
                </div>
                
                <div class="details-grid">
                    <!-- Main Details -->
                    <div>
                        <!-- Tour Information -->
                        <div class="detail-card">
                            <h3 class="card-title">Tour Information</h3>
                            <div class="tour-preview">
                                <?php if ($booking['tour_image']): ?>
                                    <img src="../../<?php echo htmlspecialchars($booking['tour_image']); ?>" 
                                         alt="Tour" class="tour-image">
                                <?php else: ?>
                                    <div class="tour-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-image" style="color: #999;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="tour-info">
                                    <h3><?php echo htmlspecialchars($booking['tour_title']); ?></h3>
                                    <div style="display: flex; gap: 15px; color: #666; font-size: 0.9em;">
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($booking['country_name']); ?></span>
                                        <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($booking['category_name']); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo $booking['duration_days']; ?> Days</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tour-details">
                                <div class="detail-item">
                                    <span class="detail-label">Duration:</span>
                                    <span class="detail-value"><?php echo $booking['duration_days']; ?> Days, <?php echo $booking['duration_nights']; ?> Nights</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Country:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['country_name']); ?></span>
                                </div>
                                <?php if ($booking['region_name']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Region:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['region_name']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <span class="detail-label">Category:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['category_name']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Customer Information -->
                        <div class="detail-card">
                            <h3 class="card-title">Customer Information</h3>
                            <div class="customer-info">
                                <div class="detail-item">
                                    <span class="detail-label">Name:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Email:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['phone'] ?: 'Not provided'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Nationality:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['nationality'] ?: 'Not provided'); ?></span>
                                </div>
                                <?php if ($booking['date_of_birth']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Date of Birth:</span>
                                    <span class="detail-value"><?php echo date('M j, Y', strtotime($booking['date_of_birth'])); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($booking['passport_number']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Passport:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['passport_number']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($booking['emergency_contact_name'] || $booking['emergency_contact_phone']): ?>
                            <h4 style="margin-top: 20px; margin-bottom: 10px; color: #333;">Emergency Contact</h4>
                            <div class="customer-info">
                                <?php if ($booking['emergency_contact_name']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Name:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['emergency_contact_name']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($booking['emergency_contact_phone']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Phone:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($booking['emergency_contact_phone']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Special Requirements -->
                        <?php if ($booking['special_requests'] || $booking['dietary_requirements'] || $booking['medical_conditions']): ?>
                        <div class="detail-card">
                            <h3 class="card-title">Special Requirements</h3>
                            
                            <?php if ($booking['special_requests']): ?>
                            <div style="margin-bottom: 15px;">
                                <strong>Special Requests:</strong>
                                <p style="margin-top: 5px; color: #666;"><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['dietary_requirements']): ?>
                            <div style="margin-bottom: 15px;">
                                <strong>Dietary Requirements:</strong>
                                <p style="margin-top: 5px; color: #666;"><?php echo nl2br(htmlspecialchars($booking['dietary_requirements'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['medical_conditions']): ?>
                            <div style="margin-bottom: 15px;">
                                <strong>Medical Conditions:</strong>
                                <p style="margin-top: 5px; color: #666;"><?php echo nl2br(htmlspecialchars($booking['medical_conditions'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Add-ons -->
                        <?php if (!empty($addons)): ?>
                        <div class="detail-card">
                            <h3 class="card-title">Add-ons</h3>
                            <div class="addon-list">
                                <?php foreach ($addons as $addon): ?>
                                <div class="addon-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($addon['addon_name']); ?></strong>
                                        <?php if ($addon['addon_description']): ?>
                                        <div style="font-size: 0.9em; color: #666;"><?php echo htmlspecialchars($addon['addon_description']); ?></div>
                                        <?php endif; ?>
                                        <div style="font-size: 0.9em; color: #666;">Quantity: <?php echo $addon['quantity']; ?></div>
                                    </div>
                                    <div style="font-weight: 600;">$<?php echo number_format($addon['total_price'], 2); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sidebar -->
                    <div>
                        <!-- Payment Summary -->
                        <div class="detail-card">
                            <h3 class="card-title">Payment Summary</h3>
                            <div class="payment-summary">
                                <div class="payment-item">
                                    <span>Total Amount:</span>
                                    <span>$<?php echo number_format($booking['total_amount'], 2); ?></span>
                                </div>
                                <div class="payment-item">
                                    <span>Amount Paid:</span>
                                    <span style="color: #28a745;">$<?php echo number_format($booking['paid_amount'], 2); ?></span>
                                </div>
                                <div class="payment-item payment-total">
                                    <span>Balance Due:</span>
                                    <span style="color: <?php echo ($booking['total_amount'] - $booking['paid_amount']) > 0 ? '#dc3545' : '#28a745'; ?>;">
                                        $<?php echo number_format($booking['total_amount'] - $booking['paid_amount'], 2); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($booking['status'] === 'pending' && hasPermission('bookings.approve')): ?>
                            <button onclick="confirmBooking(<?php echo $booking['id']; ?>)" class="btn btn-success btn-block">
                                <i class="fas fa-check"></i> Confirm Booking
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Agent Information -->
                        <?php if ($booking['agent_first_name']): ?>
                        <div class="detail-card">
                            <h3 class="card-title">MCA Agent</h3>
                            <div class="detail-item">
                                <span class="detail-label">Agent:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['agent_first_name'] . ' ' . $booking['agent_last_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['agent_email']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Payment History -->
                        <?php if (!empty($payments)): ?>
                        <div class="detail-card">
                            <h3 class="card-title">Payment History</h3>
                            <div class="payment-history">
                                <?php foreach ($payments as $payment): ?>
                                <div class="payment-record">
                                    <div>
                                        <div style="font-weight: 600;">$<?php echo number_format($payment['amount'], 2); ?></div>
                                        <div style="font-size: 0.9em; color: #666;">
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                        </div>
                                        <div style="font-size: 0.8em; color: #999;">
                                            <?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?>
                                        </div>
                                    </div>
                                    <span class="payment-status <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Activity Log -->
                        <?php if (!empty($activity_logs)): ?>
                        <div class="detail-card">
                            <h3 class="card-title">Activity Log</h3>
                            <div class="activity-log">
                                <?php foreach ($activity_logs as $log): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-action"><?php echo htmlspecialchars($log['description']); ?></div>
                                        <div class="activity-time">
                                            <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                            <?php if ($log['first_name']): ?>
                                                by <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
