<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('analytics.view');

$page_title = 'Analytics Dashboard';

// Get date range from request or default to last 30 days
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$compare_period = $_GET['compare'] ?? 'previous';

// Calculate comparison dates
$days_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);
$compare_from = date('Y-m-d', strtotime($date_from . " -{$days_diff} days"));
$compare_to = date('Y-m-d', strtotime($date_to . " -{$days_diff} days"));

// Get KPI data
function getKPIData($db, $date_from, $date_to)
{
    $sql = "
        SELECT 
            COUNT(DISTINCT b.id) as total_bookings,
            COALESCE(SUM(b.total_amount), 0) as total_revenue,
            COUNT(DISTINCT b.user_id) as unique_customers,
            COALESCE(AVG(b.total_amount), 0) as avg_booking_value,
            COUNT(DISTINCT CASE WHEN b.status = 'confirmed' THEN b.id END) as confirmed_bookings,
            COUNT(DISTINCT CASE WHEN b.status = 'pending' THEN b.id END) as pending_bookings,
            COUNT(DISTINCT CASE WHEN b.status = 'cancelled' THEN b.id END) as cancelled_bookings,
            COUNT(DISTINCT t.id) as active_tours,
            COUNT(DISTINCT c.id) as active_countries
        FROM bookings b
        LEFT JOIN tours t ON b.tour_id = t.id
        LEFT JOIN countries c ON t.country_id = c.id
        WHERE b.created_at BETWEEN ? AND ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$current_kpis = getKPIData($db, $date_from, $date_to);
$previous_kpis = getKPIData($db, $compare_from, $compare_to);

// Calculate growth percentages
function calculateGrowth($current, $previous)
{
    if ($previous == 0)
        return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

// Get daily revenue data for chart
$revenue_data = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as bookings,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM bookings 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
");
$revenue_data->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
$daily_revenue = $revenue_data->fetchAll(PDO::FETCH_ASSOC);

// Get top performing tours
$top_tours = $db->prepare("
    SELECT 
        t.title,
        t.featured_image,
        COUNT(b.id) as booking_count,
        COALESCE(SUM(b.total_amount), 0) as revenue,
        COALESCE(AVG(b.total_amount), 0) as avg_value
    FROM tours t
    LEFT JOIN bookings b ON t.id = b.tour_id 
        AND b.created_at BETWEEN ? AND ?
    WHERE t.status = 'active'
    GROUP BY t.id
    ORDER BY revenue DESC
    LIMIT 10
");
$top_tours->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
$top_tours_data = $top_tours->fetchAll(PDO::FETCH_ASSOC);

// Get booking status distribution
$status_distribution = $db->prepare("
    SELECT 
        status,
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM bookings 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY status
");
$status_distribution->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
$status_data = $status_distribution->fetchAll(PDO::FETCH_ASSOC);

// Get recent activities
$recent_activities = $db->prepare("
    SELECT 
        'booking' as type,
        CONCAT('New booking #', booking_reference, ' for ', t.title) as description,
        b.created_at AS activity_time,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        b.total_amount as amount
    FROM bookings b
    LEFT JOIN tours t ON b.tour_id = t.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)

    UNION ALL

    SELECT 
        'tour' as type,
        CONCAT('New tour created: ', title) as description,
        t.created_at AS activity_time,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        price_adult as amount
    FROM tours t
    LEFT JOIN users u ON t.created_by = u.id
    WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)

    ORDER BY activity_time DESC
    LIMIT 20
");
$recent_activities->execute();
$activities = $recent_activities->fetchAll(PDO::FETCH_ASSOC);

// Get geographic data
$geographic_data = $db->prepare("
    SELECT 
        c.name as country,
        COUNT(b.id) as bookings,
        COALESCE(SUM(b.total_amount), 0) as revenue
    FROM countries c
    LEFT JOIN tours t ON c.id = t.country_id
    LEFT JOIN bookings b ON t.id = b.tour_id 
        AND b.created_at BETWEEN ? AND ?
    WHERE c.status = 'active'
    GROUP BY c.id
    HAVING bookings > 0
    ORDER BY revenue DESC
    LIMIT 10
");
$geographic_data->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
$geo_data = $geographic_data->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .date-filters {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 20px;
        }

        .date-filters input,
        .date-filters select {
            padding: 8px 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .date-filters input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--admin-primary);
        }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .kpi-title {
            font-size: 0.9em;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(212, 165, 116, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--admin-primary);
        }

        .kpi-value {
            font-size: 2.2em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .kpi-growth {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }

        .growth-positive {
            color: #28a745;
        }

        .growth-negative {
            color: #dc3545;
        }

        .growth-neutral {
            color: #6c757d;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .data-tables {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .table-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #333;
        }

        .data-table {
            width: 100%;
        }

        .data-table th,
        .data-table td {
            padding: 12px 25px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 0.9em;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .tour-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tour-thumb {
            width: 40px;
            height: 30px;
            border-radius: 4px;
            object-fit: cover;
        }

        .activity-feed {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .activity-item {
            padding: 15px 25px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9em;
        }

        .activity-icon.booking {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .activity-icon.tour {
            background: rgba(212, 165, 116, 0.1);
            color: var(--admin-primary);
        }

        .activity-content {
            flex: 1;
        }

        .activity-description {
            font-weight: 500;
            color: #333;
            margin-bottom: 3px;
        }

        .activity-meta {
            font-size: 0.8em;
            color: #666;
        }

        .activity-amount {
            font-weight: 600;
            color: var(--admin-primary);
        }

        .export-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-export {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            color: #666;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            background: #f8f9fa;
            border-color: var(--admin-primary);
            color: var(--admin-primary);
        }

        .real-time-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8em;
            color: #28a745;
        }

        .pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #28a745;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }

        @media (max-width: 768px) {

            .charts-grid,
            .data-tables {
                grid-template-columns: 1fr;
            }

            .date-filters {
                flex-direction: column;
                align-items: stretch;
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
                <!-- Analytics Header -->
                <div class="analytics-header">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h1>Analytics Dashboard</h1>
                            <p>Real-time insights and performance metrics</p>
                            <div class="real-time-indicator">
                                <div class="pulse"></div>
                                Live Data
                            </div>
                        </div>
                        <div class="export-buttons">
                            <a href="export.php?type=pdf&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>"
                                class="btn-export">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </a>
                            <a href="export.php?type=excel&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>"
                                class="btn-export">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </a>
                        </div>
                    </div>

                    <form method="GET" class="date-filters">
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" required>
                        <span style="color: rgba(255,255,255,0.7);">to</span>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" required>
                        <select name="compare">
                            <option value="previous" <?php echo $compare_period === 'previous' ? 'selected' : ''; ?>>vs
                                Previous Period</option>
                            <option value="year" <?php echo $compare_period === 'year' ? 'selected' : ''; ?>>vs Last Year
                            </option>
                        </select>
                        <button type="submit" class="btn btn-light">Update</button>
                    </form>
                </div>

                <!-- KPI Cards -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div class="kpi-title">Total Revenue</div>
                            <div class="kpi-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                        <div class="kpi-value">$<?php echo number_format($current_kpis['total_revenue']); ?></div>
                        <div class="kpi-growth <?php
                        $growth = calculateGrowth($current_kpis['total_revenue'], $previous_kpis['total_revenue']);
                        echo $growth > 0 ? 'growth-positive' : ($growth < 0 ? 'growth-negative' : 'growth-neutral');
                        ?>">
                            <i
                                class="fas fa-<?php echo $growth > 0 ? 'arrow-up' : ($growth < 0 ? 'arrow-down' : 'minus'); ?>"></i>
                            <?php echo abs($growth); ?>% vs previous period
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div class="kpi-title">Total Bookings</div>
                            <div class="kpi-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="kpi-value"><?php echo number_format($current_kpis['total_bookings']); ?></div>
                        <div class="kpi-growth <?php
                        $growth = calculateGrowth($current_kpis['total_bookings'], $previous_kpis['total_bookings']);
                        echo $growth > 0 ? 'growth-positive' : ($growth < 0 ? 'growth-negative' : 'growth-neutral');
                        ?>">
                            <i
                                class="fas fa-<?php echo $growth > 0 ? 'arrow-up' : ($growth < 0 ? 'arrow-down' : 'minus'); ?>"></i>
                            <?php echo abs($growth); ?>% vs previous period
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div class="kpi-title">Average Booking Value</div>
                            <div class="kpi-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        <div class="kpi-value">$<?php echo number_format($current_kpis['avg_booking_value']); ?></div>
                        <div class="kpi-growth <?php
                        $growth = calculateGrowth($current_kpis['avg_booking_value'], $previous_kpis['avg_booking_value']);
                        echo $growth > 0 ? 'growth-positive' : ($growth < 0 ? 'growth-negative' : 'growth-neutral');
                        ?>">
                            <i
                                class="fas fa-<?php echo $growth > 0 ? 'arrow-up' : ($growth < 0 ? 'arrow-down' : 'minus'); ?>"></i>
                            <?php echo abs($growth); ?>% vs previous period
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div class="kpi-title">Conversion Rate</div>
                            <div class="kpi-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                        </div>
                        <div class="kpi-value">
                            <?php
                            $conversion_rate = $current_kpis['total_bookings'] > 0 ?
                                round(($current_kpis['confirmed_bookings'] / $current_kpis['total_bookings']) * 100, 1) : 0;
                            echo $conversion_rate;
                            ?>%
                        </div>
                        <div class="kpi-growth growth-positive">
                            <i class="fas fa-info-circle"></i>
                            <?php echo $current_kpis['confirmed_bookings']; ?> confirmed bookings
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Revenue Trend</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Booking Status</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Data Tables -->
                <div class="data-tables">
                    <div class="table-card">
                        <div class="table-header">
                            <h3 class="table-title">Top Performing Tours</h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tour</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($top_tours_data, 0, 5) as $tour): ?>
                                    <tr>
                                        <td>
                                            <div class="tour-info">
                                                <?php if ($tour['featured_image']): ?>
                                                    <img src="../../<?php echo htmlspecialchars($tour['featured_image']); ?>"
                                                        alt="Tour" class="tour-thumb">
                                                <?php endif; ?>
                                                <span><?php echo htmlspecialchars($tour['title']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($tour['booking_count']); ?></td>
                                        <td>$<?php echo number_format($tour['revenue']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-card">
                        <div class="table-header">
                            <h3 class="table-title">Top Destinations</h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($geo_data, 0, 5) as $country): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($country['country']); ?></td>
                                        <td><?php echo number_format($country['bookings']); ?></td>
                                        <td>$<?php echo number_format($country['revenue']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="activity-feed">
                    <div class="table-header">
                        <h3 class="table-title">Recent Activity (Last 24 Hours)</h3>
                    </div>
                    <?php if (empty($activities)): ?>
                        <div style="padding: 40px; text-align: center; color: #666;">
                            <i class="fas fa-clock" style="font-size: 2em; margin-bottom: 10px;"></i>
                            <p>No recent activity</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $activity['type']; ?>">
                                    <i
                                        class="fas fa-<?php echo $activity['type'] === 'booking' ? 'calendar-check' : 'route'; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-description"><?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                    <div class="activity-meta">
                                        <?php echo htmlspecialchars($activity['user_name']); ?> •
                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                                <?php if ($activity['amount']): ?>
                                    <div class="activity-amount">$<?php echo number_format($activity['amount']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($daily_revenue, 'date')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($daily_revenue, 'revenue')); ?>,
                    borderColor: '#D4A574',
                    backgroundColor: 'rgba(212, 165, 116, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Bookings',
                    data: <?php echo json_encode(array_column($daily_revenue, 'bookings')); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue ($)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Bookings'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($status_data, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($status_data, 'count')); ?>,
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#dc3545',
                        '#17a2b8',
                        '#6c757d'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Auto-refresh every 5 minutes
        setInterval(() => {
            window.location.reload();
        }, 300000);
    </script>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>