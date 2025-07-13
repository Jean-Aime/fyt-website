<?php
require_once 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user information
$user_stmt = $db->prepare("
    SELECT u.*, r.name as role_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    WHERE u.id = ?
");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// Get user's bookings
$bookings_stmt = $db->prepare("
    SELECT b.*, t.title as tour_title, t.featured_image, c.name as country_name,
           p.amount as paid_amount, p.status as payment_status
    FROM bookings b
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
    LIMIT 5
");
$bookings_stmt->execute([$user_id]);
$recent_bookings = $bookings_stmt->fetchAll();

// Get booking statistics
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(total_amount) as total_spent
    FROM bookings 
    WHERE user_id = ?
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch();

// Get wishlist items
$wishlist_stmt = $db->prepare("
    SELECT t.*, c.name as country_name
    FROM tours t
    LEFT JOIN countries c ON t.country_id = c.id
    WHERE t.id IN (
        SELECT tour_id FROM user_wishlist WHERE user_id = ?
    )
    LIMIT 4
");
$wishlist_stmt->execute([$user_id]);
$wishlist_items = $wishlist_stmt->fetchAll();

// Get recent activity
$activity_stmt = $db->prepare("
    SELECT * FROM user_activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$activity_stmt->execute([$user_id]);
$recent_activity = $activity_stmt->fetchAll();

$page_title = 'Dashboard - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <style>
        /* Dashboard Specific Styles */
        .dashboard-page {
            background-color: #f8f9fa;
            color: #000000;
        }

        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 120px);
        }

        .dashboard-sidebar {
            width: 280px;
            background-color: #000000;
            color: #ffffff;
            padding: 2rem 1rem;
            position: sticky;
            top: 80px;
            height: calc(100vh - 80px);
            overflow-y: auto;
        }

        .dashboard-main {
            flex: 1;
            padding: 2rem;
            background-color: #ffffff;
        }

        .user-profile {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        }

        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background-color: #333333;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 3px solid #D4AF37;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: #D4AF37;
            background-color: #333333;
        }

        .user-info h3 {
            color: #ffffff;
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }

        .user-info p {
            color: #cccccc;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .user-role {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: #D4AF37;
            color: #000000;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .dashboard-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .dashboard-nav li {
            margin-bottom: 0.5rem;
        }

        .dashboard-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #cccccc;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .dashboard-nav a:hover,
        .dashboard-nav a.active {
            background-color: rgba(212, 175, 55, 0.2);
            color: #D4AF37;
            text-decoration: none;
        }

        .dashboard-nav a i {
            width: 20px;
            text-align: center;
        }

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-header h1 {
            color: #000000;
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            color: #666666;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: rgba(212, 175, 55, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #D4AF37;
            font-size: 1.5rem;
        }

        .stat-info h3 {
            color: #000000;
            margin-bottom: 0.25rem;
            font-size: 1.75rem;
        }

        .stat-info p {
            color: #666666;
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        /* Quick Actions */
        .quick-actions {
            margin-bottom: 2rem;
        }

        .quick-actions h2 {
            color: #000000;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            color: #000000;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            background-color: rgba(212, 175, 55, 0.05);
        }

        .action-card i {
            font-size: 2rem;
            color: #D4AF37;
            margin-bottom: 1rem;
            display: block;
        }

        .action-card h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: #000000;
        }

        .action-card p {
            color: #666666;
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        /* Dashboard Sections */
        .dashboard-section {
            margin-bottom: 3rem;
            background-color: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            color: #000000;
            font-size: 1.5rem;
            margin-bottom: 0;
        }

        .view-all {
            color: #D4AF37;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        /* Bookings List */
        .bookings-list {
            display: grid;
            gap: 1rem;
        }

        .booking-card {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 8px;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
            gap: 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .booking-card:hover {
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .booking-image {
            width: 120px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .booking-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .booking-info {
            flex: 1;
        }

        .booking-info h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: #000000;
        }

        .booking-location,
        .booking-date,
        .booking-guests {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666666;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .booking-location i,
        .booking-date i,
        .booking-guests i {
            color: #D4AF37;
            font-size: 0.8rem;
        }

        .booking-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
            margin-right: 1rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-confirmed {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .status-cancelled {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .booking-amount {
            font-weight: 600;
            color: #000000;
        }

        .booking-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .empty-state i {
            font-size: 3rem;
            color: #D4AF37;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: #000000;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #666666;
            margin-bottom: 1.5rem;
        }

        /* Wishlist Grid */
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .wishlist-card {
            border-radius: 8px;
            overflow: hidden;
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .wishlist-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .wishlist-image {
            height: 160px;
            overflow: hidden;
        }

        .wishlist-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .wishlist-card:hover .wishlist-image img {
            transform: scale(1.05);
        }

        .wishlist-info {
            padding: 1.25rem;
        }

        .wishlist-info h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: #000000;
        }

        .wishlist-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666666;
            font-size: 0.85rem;
            margin-bottom: 0.75rem;
        }

        .wishlist-location i {
            color: #D4AF37;
            font-size: 0.8rem;
        }

        .wishlist-price {
            font-weight: 600;
            color: #D4AF37;
            margin-bottom: 1rem;
        }

        /* Activity List */
        .activity-list {
            display: grid;
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-radius: 8px;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(212, 175, 55, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #D4AF37;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-content p {
            color: #000000;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .activity-time {
            color: #666666;
            font-size: 0.8rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .dashboard-sidebar {
                width: 240px;
            }
        }

        @media (max-width: 992px) {
            .dashboard-container {
                flex-direction: column;
            }

            .dashboard-sidebar {
                width: 100%;
                height: auto;
                position: static;
                padding: 1rem;
            }

            .dashboard-main {
                padding: 1.5rem;
            }

            .user-profile {
                display: flex;
                align-items: center;
                gap: 1.5rem;
                text-align: left;
                padding-bottom: 1rem;
            }

            .user-avatar {
                width: 60px;
                height: 60px;
                margin: 0;
            }

            .dashboard-nav ul {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .dashboard-nav li {
                margin-bottom: 0;
            }

            .dashboard-nav a {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .booking-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .booking-image {
                width: 100%;
                height: 150px;
            }

            .booking-status {
                flex-direction: row;
                align-items: center;
                width: 100%;
                justify-content: space-between;
                margin-right: 0;
                margin-top: 0.5rem;
            }

            .booking-actions {
                width: 100%;
                flex-direction: row;
                justify-content: flex-end;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .wishlist-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .actions-grid {
                grid-template-columns: 1fr;
            }

            .wishlist-grid {
                grid-template-columns: 1fr;
            }

            .booking-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body class="dashboard-page">
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="dashboard-sidebar">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php if ($user['profile_image']): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                    <span class="user-role"><?php echo ucfirst($user['role_name']); ?></span>
                </div>
            </div>

            <nav class="dashboard-nav">
                <ul>
                    <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="my-bookings.php"><i class="fas fa-calendar-check"></i> My Bookings</a></li>
                    <li><a href="my-wishlist.php"><i class="fas fa-heart"></i> Wishlist</a></li>
                    <li><a href="my-reviews.php"><i class="fas fa-star"></i> Reviews</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="payment-methods.php"><i class="fas fa-credit-card"></i> Payment Methods</a></li>
                    <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="support.php"><i class="fas fa-headset"></i> Support</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="dashboard-main">
            <div class="dashboard-header">
                <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                <p>Manage your bookings, explore new destinations, and plan your next adventure.</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_bookings'] ?: 0; ?></h3>
                        <p>Total Bookings</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['confirmed_bookings'] ?: 0; ?></h3>
                        <p>Confirmed Tours</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_bookings'] ?: 0; ?></h3>
                        <p>Pending Bookings</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($stats['total_spent'] ?: 0); ?></h3>
                        <p>Total Spent</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="actions-grid">
                    <a href="tours.php" class="action-card">
                        <i class="fas fa-search"></i>
                        <h3>Browse Tours</h3>
                        <p>Discover amazing destinations</p>
                    </a>

                    <a href="book.php" class="action-card">
                        <i class="fas fa-plus"></i>
                        <h3>Book a Tour</h3>
                        <p>Start planning your next adventure</p>
                    </a>

                    <a href="my-bookings.php" class="action-card">
                        <i class="fas fa-list"></i>
                        <h3>View Bookings</h3>
                        <p>Manage your reservations</p>
                    </a>

                    <a href="support.php" class="action-card">
                        <i class="fas fa-headset"></i>
                        <h3>Get Support</h3>
                        <p>Need help? Contact us</p>
                    </a>
                </div>
            </div>

            <!-- Recent Bookings -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Recent Bookings</h2>
                    <a href="my-bookings.php" class="view-all">View All</a>
                </div>

                <?php if (empty($recent_bookings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No bookings yet</h3>
                        <p>Start exploring our amazing tours and book your first adventure!</p>
                        <a href="tours.php" class="btn btn-primary">Browse Tours</a>
                    </div>
                <?php else: ?>
                    <div class="bookings-list">
                        <?php foreach ($recent_bookings as $booking): ?>
                            <div class="booking-card">
                                <div class="booking-image">
                                    <?php if ($booking['featured_image']): ?>
                                        <img src="<?php echo htmlspecialchars($booking['featured_image']); ?>"
                                            alt="<?php echo htmlspecialchars($booking['tour_title']); ?>">
                                    <?php else: ?>
                                        <img src="/placeholder.svg?height=80&width=120&text=Tour" alt="Tour">
                                    <?php endif; ?>
                                </div>

                                <div class="booking-info">
                                    <h3><?php echo htmlspecialchars($booking['tour_title']); ?></h3>
                                    <p class="booking-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($booking['country_name']); ?>
                                    </p>
                                    <p class="booking-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M j, Y', strtotime($booking['tour_date'])); ?>
                                    </p>
                                    <p class="booking-guests">
                                        <i class="fas fa-users"></i>
                                        <?php echo $booking['adults']; ?> Adults
                                        <?php if ($booking['children'] > 0): ?>
                                            , <?php echo $booking['children']; ?> Children
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="booking-status">
                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                    <div class="booking-amount">
                                        <?php echo formatCurrency($booking['total_amount']); ?>
                                    </div>
                                </div>

                                <div class="booking-actions">
                                    <a href="booking-details.php?id=<?php echo $booking['id']; ?>"
                                        class="btn btn-outline btn-sm">
                                        View Details
                                    </a>
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <a href="payment.php?booking=<?php echo $booking['id']; ?>" class="btn btn-primary btn-sm">
                                            Complete Payment
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Wishlist -->
            <?php if (!empty($wishlist_items)): ?>
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Your Wishlist</h2>
                        <a href="my-wishlist.php" class="view-all">View All</a>
                    </div>

                    <div class="wishlist-grid">
                        <?php foreach ($wishlist_items as $item): ?>
                            <div class="wishlist-card">
                                <div class="wishlist-image">
                                    <?php if ($item['featured_image']): ?>
                                        <img src="<?php echo htmlspecialchars($item['featured_image']); ?>"
                                            alt="<?php echo htmlspecialchars($item['title']); ?>">
                                    <?php else: ?>
                                        <img src="/placeholder.svg?height=200&width=300&text=<?php echo urlencode($item['title']); ?>"
                                            alt="<?php echo htmlspecialchars($item['title']); ?>">
                                    <?php endif; ?>
                                </div>

                                <div class="wishlist-info">
                                    <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                    <p class="wishlist-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($item['country_name']); ?>
                                    </p>
                                    <div class="wishlist-price">
                                        From <?php echo formatCurrency($item['price_adult']); ?>
                                    </div>
                                    <a href="tour-details.php?id=<?php echo $item['id']; ?>" class="btn btn-primary btn-sm">
                                        View Tour
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Recent Activity</h2>
                </div>

                <div class="activity-list">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php
                                $icon = 'fas fa-circle';
                                switch ($activity['action']) {
                                    case 'login':
                                        $icon = 'fas fa-sign-in-alt';
                                        break;
                                    case 'booking':
                                        $icon = 'fas fa-calendar-plus';
                                        break;
                                    case 'payment':
                                        $icon = 'fas fa-credit-card';
                                        break;
                                    case 'review':
                                        $icon = 'fas fa-star';
                                        break;
                                    case 'profile_update':
                                        $icon = 'fas fa-user-edit';
                                        break;
                                }
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                <span class="activity-time"><?php echo timeAgo($activity['created_at']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>

</html>