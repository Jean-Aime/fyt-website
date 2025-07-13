<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/secure_auth.php';


$auth = new SecureAuth($db);
requireLogin();

$page_title = 'Admin Dashboard';
$current_user = getCurrentUser();

// Get dashboard statistics
$stats = [];

// Tours statistics
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_tours,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_tours,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_tours,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_tours_30d
    FROM tours
");
$stats['tours'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Bookings statistics
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_bookings,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_bookings_30d,
        SUM(CASE WHEN status = 'confirmed' THEN total_amount ELSE 0 END) as total_revenue
    FROM bookings
");
$stats['bookings'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Users statistics
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d
    FROM users
");
$stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent activities
$stmt = $db->prepare("
    SELECT ua.*, u.first_name, u.last_name 
    FROM user_activity ua
    JOIN users u ON ua.user_id = u.id
    ORDER BY ua.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent bookings
$stmt = $db->query("
    SELECT b.*, t.title as tour_title, u.first_name, u.last_name
    FROM bookings b
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN users u ON b.user_id = u.id
    ORDER BY b.created_at DESC
    LIMIT 5
");
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly revenue data for chart
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(total_amount) as revenue,
        COUNT(*) as bookings
    FROM bookings 
    WHERE status = 'confirmed' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-welcome {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .dashboard-welcome::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        .welcome-title {
            font-size: 2em;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .welcome-subtitle {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .quick-action {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .quick-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            color: #3498db;
        }

        .quick-action i {
            font-size: 2em;
            margin-bottom: 10px;
            color: #3498db;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .chart-title {
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .activity-feed {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498db;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 0.9em;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .activity-time {
            font-size: 0.8em;
            color: #7f8c8d;
        }

        .recent-bookings {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .recent-bookings-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa, white);
            border-bottom: 1px solid #e9ecef;
        }

        .booking-item {
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .booking-item:last-child {
            border-bottom: none;
        }

        .booking-info h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 1em;
        }

        .booking-info p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .booking-amount {
            font-weight: bold;
            color: #27ae60;
            font-size: 1.1em;
        }

        @media (max-width: 992px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <!-- Welcome Section -->
                <div class="dashboard-welcome">
                    <div class="welcome-content">
                        <h1 class="welcome-title">Welcome back,
                            <?php echo htmlspecialchars($current_user['first_name'] ?? 'User'); ?>
                        </h1>
                        <p class="welcome-subtitle">Here's what's happening with your tours today.</p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['tours']['total_tours']); ?></h3>
                            <p>Total Tours</p>
                            <div class="stat-change positive">
                                +<?php echo $stats['tours']['new_tours_30d']; ?> this month
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['bookings']['total_bookings']); ?></h3>
                            <p>Total Bookings</p>
                            <div class="stat-change positive">
                                +<?php echo $stats['bookings']['new_bookings_30d']; ?> this month
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3>$<?php echo number_format($stats['bookings']['total_revenue'] ?? 0); ?>
                            </h3>
                            <p>Total Revenue</p>
                            <div class="stat-change positive">
                                Revenue from confirmed bookings
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['users']['total_users']); ?></h3>
                            <p>Total Users</p>
                            <div class="stat-change positive">
                                +<?php echo $stats['users']['new_users_30d']; ?> this month
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="tours/add.php" class="quick-action">
                        <i class="fas fa-plus"></i>
                        <h4>Add New Tour</h4>
                        <p>Create a new tour package</p>
                    </a>
                    <a href="bookings/index.php?status=pending" class="quick-action">
                        <i class="fas fa-clock"></i>
                        <h4>Pending Bookings</h4>
                        <p><?php echo $stats['bookings']['pending_bookings']; ?> awaiting confirmation</p>
                    </a>
                    <a href="users/index.php" class="quick-action">
                        <i class="fas fa-user-plus"></i>
                        <h4>Manage Users</h4>
                        <p>Add or edit user accounts</p>
                    </a>
                    <a href="analytics/reports.php" class="quick-action">
                        <i class="fas fa-chart-bar"></i>
                        <h4>View Reports</h4>
                        <p>Analyze performance data</p>
                    </a>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <!-- Revenue Chart -->
                    <div class="chart-container">
                        <h3 class="chart-title">Monthly Revenue</h3>
                        <canvas id="revenueChart" width="400" height="200"></canvas>
                    </div>

                    <!-- Recent Activity -->
                    <div class="activity-feed">
                        <h3 class="chart-title">Recent Activity</h3>
                        <?php if (empty($recent_activities)): ?>
                            <p style="text-align: center; color: #7f8c8d; padding: 20px;">No recent activity</p>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i
                                            class="fas fa-<?php echo isset($activity['action']) ? getActivityIcon($activity['action']) : 'info-circle'; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-text">
                                            <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                            <?php echo htmlspecialchars($activity['description']); ?>
                                        </div>
                                        <div class="activity-time"><?php echo timeAgo($activity['created_at']); ?></div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="recent-bookings">
                    <div class="recent-bookings-header">
                        <h3>Recent Bookings</h3>
                    </div>
                    <?php if (empty($recent_bookings)): ?>
                        <div style="padding: 40px; text-align: center; color: #7f8c8d;">
                            <i class="fas fa-calendar-times" style="font-size: 3em; margin-bottom: 15px; color: #ddd;"></i>
                            <p>No recent bookings</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_bookings as $booking): ?>
                            <div class="booking-item">
                                <div class="booking-info">
                                    <h4><?php echo htmlspecialchars($booking['tour_title']); ?></h4>
                                    <p>
                                        <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?> •
                                        <?php echo date('M j, Y', strtotime($booking['created_at'])); ?> •
                                        <span class="status-badge <?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="booking-amount">
                                    $<?php echo number_format($booking['total_amount'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_data); ?>;

        const labels = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });

        const revenueData = monthlyData.map(item => parseFloat(item.revenue) || 0);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue ($)',
                    data: revenueData,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Auto-refresh dashboard data every 5 minutes
        setInterval(function () {
            location.reload();
        }, 300000);
    </script> -->

    <script src="../assets/js/admin.js"></script>
</body>

</html>

<?php
function getActivityIcon($action)
{
    $icons = [
        'user_created' => 'user-plus',
        'user_updated' => 'user-edit',
        'user_deleted' => 'user-times',
        'tour_created' => 'map-marked-alt',
        'tour_updated' => 'edit',
        'booking_created' => 'calendar-plus',
        'booking_updated' => 'calendar-check',
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt'
    ];

    return $icons[$action] ?? 'info-circle';
}

function timeAgo($datetime)
{
    $time = time() - strtotime($datetime);

    if ($time < 60)
        return 'just now';
    if ($time < 3600)
        return floor($time / 60) . ' minutes ago';
    if ($time < 86400)
        return floor($time / 3600) . ' hours ago';
    if ($time < 2592000)
        return floor($time / 86400) . ' days ago';
    if ($time < 31536000)
        return floor($time / 2592000) . ' months ago';

    return floor($time / 31536000) . ' years ago';
}
?>