<?php
require_once '../config/config.php';
require_once '../includes/secure_auth.php';
require_once '../includes/auth_middleware.php';

$auth = new SecureAuth($db);
requireLogin();
$mca = requireMCA();

$user_id = $_SESSION['user_id'];
$mca_id = $_SESSION['mca_id'];

// Get MCA agent details
$agent = getMCADetails($user_id);

if (!$agent) {
    header('Location: ../login.php?error=Agent profile not found');
    exit;
}

// Get advisor count under this MCA
$stmt = $db->prepare("
    SELECT COUNT(*) as advisor_count,
           COUNT(CASE WHEN ca.status = 'active' THEN 1 END) as active_advisors,
           COUNT(CASE WHEN ca.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_advisors_30d
    FROM certified_advisors ca
    WHERE ca.mca_id = ?
");
$stmt->execute([$mca_id]);
$advisor_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get booking and commission statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(b.id) as total_bookings,
        COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) as confirmed_bookings,
        COUNT(CASE WHEN b.status = 'pending' THEN 1 END) as pending_bookings,
        COUNT(CASE WHEN b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as bookings_30d,
        COALESCE(SUM(b.total_amount), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN b.total_amount END), 0) as confirmed_revenue
    FROM bookings b
    WHERE b.mca_id = ?
");
$stmt->execute([$mca_id]);
$booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get commission statistics
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN mc.status = 'pending' THEN mc.commission_amount END), 0) as pending_commission,
        COALESCE(SUM(CASE WHEN mc.status = 'approved' THEN mc.commission_amount END), 0) as approved_commission,
        COALESCE(SUM(CASE WHEN mc.status = 'paid' THEN mc.commission_amount END), 0) as paid_commission,
        COUNT(mc.id) as total_commissions
    FROM mca_commissions mc
    WHERE mc.mca_id = ?
");
$stmt->execute([$mca_id]);
$commission_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent bookings with client and tour details
$stmt = $db->prepare("
    SELECT b.*, 
           t.title as tour_title, t.featured_image as tour_image, t.duration_days,
           c.name as country_name,
           u.first_name, u.last_name, u.email as customer_email,
           ca.advisor_code, ca.first_name as advisor_first_name, ca.last_name as advisor_last_name,
           mc.commission_amount, mc.status as commission_status
    FROM bookings b
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN certified_advisors ca ON b.advisor_id = ca.id
    LEFT JOIN mca_commissions mc ON b.id = mc.booking_id
    WHERE b.mca_id = ?
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute([$mca_id]);
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top performing advisors
$stmt = $db->prepare("
    SELECT ca.*, 
           COUNT(b.id) as booking_count,
           COALESCE(SUM(b.total_amount), 0) as total_sales,
           COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN b.total_amount END), 0) as confirmed_sales
    FROM certified_advisors ca
    LEFT JOIN bookings b ON ca.id = b.advisor_id
    WHERE ca.mca_id = ?
    GROUP BY ca.id
    ORDER BY confirmed_sales DESC
    LIMIT 5
");
$stmt->execute([$mca_id]);
$top_advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly performance data for charts
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(b.created_at, '%Y-%m') as month,
        COUNT(b.id) as booking_count,
        COALESCE(SUM(b.total_amount), 0) as revenue
    FROM bookings b
    WHERE b.mca_id = ?
    AND b.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([$mca_id]);
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'MCA Dashboard - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/mca-portal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="mca-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <!-- MCA Welcome Section -->
                <div class="mca-welcome">
                    <div class="welcome-content">
                        <h1>Welcome, MCA <?php echo htmlspecialchars($agent['first_name']); ?>!</h1>
                        <p><strong>Country:</strong> <?php echo htmlspecialchars($agent['country_name'] ?? 'Not Set'); ?> | 
                           <strong>Agent Code:</strong> <?php echo htmlspecialchars($agent['agent_code']); ?></p>
                        <div class="mca-status">
                            <span class="status-badge <?php echo $agent['status']; ?>">
                                <?php echo ucfirst($agent['status']); ?>
                            </span>
                            <span class="commission-rate">
                                Commission Rate: <?php echo $agent['commission_rate']; ?>%
                            </span>
                            <span class="advisor-count">
                                <?php echo $advisor_stats['advisor_count']; ?> Advisors
                            </span>
                        </div>
                    </div>
                    <div class="quick-actions">
                        <a href="advisors.php" class="btn btn-primary">
                            <i class="fas fa-users"></i> Manage Advisors
                        </a>
                        <a href="recruit.php" class="btn btn-outline">
                            <i class="fas fa-user-plus"></i> Recruit Advisor
                        </a>
                        <a href="training.php" class="btn btn-secondary">
                            <i class="fas fa-graduation-cap"></i> Training Center
                        </a>
                    </div>
                </div>

                <!-- Key Performance Metrics -->
                <div class="stats-grid">
                    <div class="stat-card revenue">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatCurrency($booking_stats['confirmed_revenue']); ?></h3>
                            <p>Total Revenue</p>
                            <span class="stat-change">
                                <?php echo formatCurrency($commission_stats['paid_commission']); ?> earned
                            </span>
                        </div>
                    </div>

                    <div class="stat-card bookings">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $booking_stats['confirmed_bookings']; ?></h3>
                            <p>Confirmed Bookings</p>
                            <span class="stat-change">
                                <?php echo $booking_stats['bookings_30d']; ?> this month
                            </span>
                        </div>
                    </div>

                    <div class="stat-card advisors">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $advisor_stats['active_advisors']; ?></h3>
                            <p>Active Advisors</p>
                            <span class="stat-change">
                                +<?php echo $advisor_stats['new_advisors_30d']; ?> new this month
                            </span>
                        </div>
                    </div>

                    <div class="stat-card commission">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo formatCurrency($commission_stats['pending_commission'] + $commission_stats['approved_commission']); ?></h3>
                            <p>Pending Commissions</p>
                            <span class="stat-change">
                                <?php echo $commission_stats['total_commissions']; ?> transactions
                            </span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <!-- Performance Chart -->
                    <div class="dashboard-section chart-section">
                        <div class="section-header">
                            <h2>Monthly Performance</h2>
                            <div class="chart-controls">
                                <button class="chart-toggle active" data-chart="revenue">Revenue</button>
                                <button class="chart-toggle" data-chart="bookings">Bookings</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="performanceChart" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <!-- Top Performing Advisors -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Top Performing Advisors</h2>
                            <a href="advisors.php" class="view-all">View All</a>
                        </div>

                        <?php if (empty($top_advisors)): ?>
                            <div class="empty-state">
                                <i class="fas fa-user-friends"></i>
                                <h3>No Advisors Yet</h3>
                                <p>Start recruiting certified advisors to grow your network!</p>
                                <a href="recruit.php" class="btn btn-primary">Recruit First Advisor</a>
                            </div>
                        <?php else: ?>
                            <div class="advisors-list">
                                <?php foreach ($top_advisors as $advisor): ?>
                                    <div class="advisor-card">
                                        <div class="advisor-avatar">
                                            <?php echo strtoupper(substr($advisor['first_name'], 0, 1) . substr($advisor['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="advisor-info">
                                            <h4><?php echo htmlspecialchars($advisor['first_name'] . ' ' . $advisor['last_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($advisor['advisor_code']); ?></p>
                                            <div class="advisor-stats">
                                                <span><i class="fas fa-calendar"></i> <?php echo $advisor['booking_count']; ?> bookings</span>
                                                <span><i class="fas fa-dollar-sign"></i> <?php echo formatCurrency($advisor['confirmed_sales']); ?></span>
                                            </div>
                                        </div>
                                        <div class="advisor-actions">
                                            <a href="advisor-details.php?id=<?php echo $advisor['id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Recent Bookings</h2>
                        <a href="bookings.php" class="view-all">View All</a>
                    </div>

                    <?php if (empty($recent_bookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Bookings Yet</h3>
                            <p>Your advisors haven't made any bookings yet. Support them with training and marketing materials!</p>
                            <a href="training.php" class="btn btn-primary">Access Training</a>
                        </div>
                    <?php else: ?>
                        <div class="bookings-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Tour</th>
                                        <th>Advisor</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Commission</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <div class="client-info">
                                                    <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                                    <small><?php echo htmlspecialchars($booking['customer_email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="tour-info">
                                                    <strong><?php echo htmlspecialchars($booking['tour_title']); ?></strong>
                                                    <small><?php echo htmlspecialchars($booking['country_name']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($booking['advisor_code']): ?>
                                                    <div class="advisor-info">
                                                        <strong><?php echo htmlspecialchars($booking['advisor_first_name'] . ' ' . $booking['advisor_last_name']); ?></strong>
                                                        <small><?php echo htmlspecialchars($booking['advisor_code']); ?></small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Direct Booking</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($booking['tour_date'])); ?></td>
                                            <td><?php echo formatCurrency($booking['total_amount']); ?></td>
                                            <td>
                                                <?php if ($booking['commission_amount']): ?>
                                                    <span class="commission-amount">
                                                        <?php echo formatCurrency($booking['commission_amount']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Calculating...</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $booking['status']; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="booking-details.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Commission Summary -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Commission Overview</h2>
                        <a href="commissions.php" class="view-all">View Details</a>
                    </div>

                    <div class="commission-summary">
                        <div class="commission-item">
                            <div class="commission-label">Pending Commission</div>
                            <div class="commission-value pending">
                                <?php echo formatCurrency($commission_stats['pending_commission']); ?>
                            </div>
                        </div>

                        <div class="commission-item">
                            <div class="commission-label">Approved Commission</div>
                            <div class="commission-value approved">
                                <?php echo formatCurrency($commission_stats['approved_commission']); ?>
                            </div>
                        </div>

                        <div class="commission-item">
                            <div class="commission-label">Paid Commission</div>
                            <div class="commission-value paid">
                                <?php echo formatCurrency($commission_stats['paid_commission']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/mca-portal.js"></script>
    <script>
        // Performance Chart
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        
        const chartData = {
            labels: monthlyData.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            }),
            datasets: [{
                label: 'Revenue ($)',
                data: monthlyData.map(item => parseFloat(item.revenue)),
                borderColor: '#D4AF37',
                backgroundColor: 'rgba(212, 175, 55, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        };

        const performanceChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: $' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Chart toggle functionality
        document.querySelectorAll('.chart-toggle').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.chart-toggle').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                const chartType = this.dataset.chart;
                if (chartType === 'bookings') {
                    performanceChart.data.datasets[0].label = 'Bookings';
                    performanceChart.data.datasets[0].data = monthlyData.map(item => parseInt(item.booking_count));
                    performanceChart.options.scales.y.ticks.callback = function(value) {
                        return value;
                    };
                } else {
                    performanceChart.data.datasets[0].label = 'Revenue ($)';
                    performanceChart.data.datasets[0].data = monthlyData.map(item => parseFloat(item.revenue));
                    performanceChart.options.scales.y.ticks.callback = function(value) {
                        return '$' + value.toLocaleString();
                    };
                }
                performanceChart.update();
            });
        });
    </script>
</body>
</html>
