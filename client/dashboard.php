<?php
// Show any PHP errors clearly (remove on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Ensure user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../login.php');
    exit;
}

// Load config and DB connection
require_once __DIR__ . '/../config/config.php';

// Optional: Uncomment if you need to use role-based access protection
/*
require_once __DIR__ . '/../includes/secure_auth.php';
$auth = new SecureAuth($db);
requireLogin();
*/

// Get user ID
$user_id = $_SESSION['user_id'];

// Get user bookings
$stmt = $db->prepare("
    SELECT b.*, 
           t.title AS tour_title, t.featured_image AS tour_image, t.duration_days,
           c.name AS country_name,
           COALESCE(SUM(p.amount), 0) AS paid_amount
    FROM bookings b
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'completed'
    WHERE b.user_id = ?
    GROUP BY b.id
    ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent support tickets
$stmt = $db->prepare("
    SELECT * FROM support_tickets 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent notifications
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get booking statistics
$stats = $db->prepare("
    SELECT 
        COUNT(*) AS total_bookings,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) AS confirmed_bookings,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_bookings,
        COALESCE(SUM(total_amount), 0) AS total_spent,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN 1 END) AS bookings_this_year
    FROM bookings 
    WHERE user_id = ?
");
$stats->execute([$user_id]);
$user_stats = $stats->fetch(PDO::FETCH_ASSOC);

// Set page title
$page_title = 'Client Dashboard - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/client-portal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="client-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <div class="welcome-section">
                    <div class="welcome-content">
                        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                        <p>Manage your bookings, view travel history, and plan your next adventure.</p>
                    </div>
                    <div class="quick-actions">
                        <a href="../book.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Booking
                        </a>
                        <a href="support.php" class="btn btn-outline">
                            <i class="fas fa-headset"></i> Get Support
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $user_stats['total_bookings']; ?></h3>
                            <p>Total Bookings</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $user_stats['confirmed_bookings']; ?></h3>
                            <p>Confirmed Tours</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $user_stats['pending_bookings']; ?></h3>
                            <p>Pending Bookings</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3>$<?php echo number_format($user_stats['total_spent']); ?></h3>
                            <p>Total Spent</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Recent Bookings -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Recent Bookings</h2>
                            <a href="bookings.php" class="view-all">View All</a>
                        </div>

                        <?php if (empty($bookings)): ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Bookings Yet</h3>
                                <p>Start planning your next adventure!</p>
                                <a href="../book.php" class="btn btn-primary">Book a Tour</a>
                            </div>
                        <?php else: ?>
                            <div class="bookings-list">
                                <?php foreach (array_slice($bookings, 0, 3) as $booking): ?>
                                    <div class="booking-card">
                                        <div class="booking-image">
                                            <?php if ($booking['tour_image']): ?>
                                                <img src="../<?php echo htmlspecialchars($booking['tour_image']); ?>"
                                                    alt="<?php echo htmlspecialchars($booking['tour_title']); ?>">
                                            <?php else: ?>
                                                <div class="image-placeholder">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="booking-content">
                                            <div class="booking-header">
                                                <h3><?php echo htmlspecialchars($booking['tour_title']); ?></h3>
                                                <span class="booking-status <?php echo $booking['status']; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </div>

                                            <div class="booking-details">
                                                <div class="detail-item">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($booking['country_name']); ?>
                                                </div>
                                                <div class="detail-item">
                                                    <i class="fas fa-calendar"></i>
                                                    <?php echo date('M j, Y', strtotime($booking['tour_date'])); ?>
                                                </div>
                                                <div class="detail-item">
                                                    <i class="fas fa-users"></i>
                                                    <?php echo $booking['adults']; ?> Adults
                                                    <?php if ($booking['children'] > 0): ?>
                                                        , <?php echo $booking['children']; ?> Children
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="booking-footer">
                                                <div class="booking-amount">
                                                    <span
                                                        class="total">$<?php echo number_format($booking['total_amount'], 2); ?></span>
                                                    <span class="paid">Paid:
                                                        $<?php echo number_format($booking['paid_amount'], 2); ?></span>
                                                </div>
                                                <a href="booking-details.php?id=<?php echo $booking['id']; ?>"
                                                    class="btn btn-sm btn-outline">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Support Tickets -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Support Tickets</h2>
                            <a href="support.php" class="view-all">View All</a>
                        </div>

                        <?php if (empty($tickets)): ?>
                            <div class="empty-state">
                                <i class="fas fa-headset"></i>
                                <h3>No Support Tickets</h3>
                                <p>Need help? Create a support ticket.</p>
                                <a href="support.php" class="btn btn-primary">Get Support</a>
                            </div>
                        <?php else: ?>
                            <div class="tickets-list">
                                <?php foreach ($tickets as $ticket): ?>
                                    <div class="ticket-item">
                                        <div class="ticket-header">
                                            <h4><?php echo htmlspecialchars($ticket['subject']); ?></h4>
                                            <span class="ticket-status <?php echo $ticket['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                            </span>
                                        </div>
                                        <div class="ticket-meta">
                                            <span class="ticket-number">#<?php echo $ticket['ticket_number']; ?></span>
                                            <span
                                                class="ticket-date"><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications -->
                <?php if (!empty($notifications)): ?>
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Recent Notifications</h2>
                            <a href="notifications.php" class="view-all">View All</a>
                        </div>

                        <div class="notifications-list">
                            <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                                <div class="notification-item <?php echo $notification['read_at'] ? 'read' : 'unread'; ?>">
                                    <div class="notification-icon">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                    <div class="notification-content">
                                        <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <span
                                            class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/client-portal.js"></script>
</body>

</html>
