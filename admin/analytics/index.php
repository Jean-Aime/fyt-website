<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('analytics.view');

$page_title = 'Analytics Dashboard';

// Get date range from filters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$compare_period = $_GET['compare'] ?? 'previous_period';

// Calculate comparison dates
$days_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24);
$compare_from = date('Y-m-d', strtotime($date_from . " -{$days_diff} days"));
$compare_to = date('Y-m-d', strtotime($date_to . " -{$days_diff} days"));

// Revenue Analytics
$revenue_current = $db->prepare("
    SELECT 
        COUNT(DISTINCT b.id) as total_bookings,
        COALESCE(SUM(b.total_amount), 0) as total_revenue,
        COALESCE(AVG(b.total_amount), 0) as avg_booking_value,
        COUNT(DISTINCT b.user_id) as unique_customers
    FROM bookings b 
    WHERE b.created_at BETWEEN ? AND ? 
    AND b.status IN ('confirmed', 'completed')
");
$revenue_current->execute([$date_from, $date_to . ' 23:59:59']);
$current_stats = $revenue_current->fetch();

$revenue_previous = $db->prepare("
    SELECT 
        COUNT(DISTINCT b.id) as total_bookings,
        COALESCE(SUM(b.total_amount), 0) as total_revenue,
        COALESCE(AVG(b.total_amount), 0) as avg_booking_value,
        COUNT(DISTINCT b.user_id) as unique_customers
    FROM bookings b 
    WHERE b.created_at BETWEEN ? AND ? 
    AND b.status IN ('confirmed', 'completed')
");
$revenue_previous->execute([$compare_from, $compare_to . ' 23:59:59']);
$previous_stats = $revenue_previous->fetch();

// Calculate growth percentages
function calculateGrowth($current, $previous)
{
    if ($previous == 0)
        return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

// Daily revenue chart data
$daily_revenue = $db->prepare("
    SELECT 
        DATE(b.created_at) as date,
        COUNT(b.id) as bookings,
        COALESCE(SUM(b.total_amount), 0) as revenue
    FROM bookings b 
    WHERE b.created_at BETWEEN ? AND ? 
    AND b.status IN ('confirmed', 'completed')
    GROUP BY DATE(b.created_at)
    ORDER BY date ASC
");
$daily_revenue->execute([$date_from, $date_to . ' 23:59:59']);
$daily_data = $daily_revenue->fetchAll();

// Top performing tours
$top_tours = $db->prepare("
    SELECT 
        t.title,
        t.id,
        COUNT(b.id) as booking_count,
        SUM(b.total_amount) as total_revenue,
        AVG(b.total_amount) as avg_revenue,
        c.name as country_name
    FROM tours t
    LEFT JOIN bookings b ON t.id = b.tour_id 
        AND b.created_at BETWEEN ? AND ?
        AND b.status IN ('confirmed', 'completed')
    LEFT JOIN countries c ON t.country_id = c.id
    WHERE t.status = 'active'
    GROUP BY t.id
    HAVING booking_count > 0
    ORDER BY total_revenue DESC
    LIMIT 10
");
$top_tours->execute([$date_from, $date_to . ' 23:59:59']);
$tour_performance = $top_tours->fetchAll();

// Customer analytics
$customer_analytics = $db->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN b.created_at BETWEEN ? AND ? THEN b.user_id END) as new_customers,
        COUNT(DISTINCT CASE WHEN b.created_at BETWEEN ? AND ? 
              AND EXISTS(SELECT 1 FROM bookings b2 WHERE b2.user_id = b.user_id AND b2.created_at < ?) 
              THEN b.user_id END) as returning_customers,
        AVG(customer_bookings.booking_count) as avg_bookings_per_customer
    FROM bookings b
    LEFT JOIN (
        SELECT user_id, COUNT(*) as booking_count 
        FROM bookings 
        WHERE status IN ('confirmed', 'completed')
        GROUP BY user_id
    ) customer_bookings ON b.user_id = customer_bookings.user_id
    WHERE b.status IN ('confirmed', 'completed')
");
$customer_analytics->execute([$date_from, $date_to . ' 23:59:59', $date_from, $date_to . ' 23:59:59', $date_from]);
$customer_stats = $customer_analytics->fetch();

// Geographic performance
$geographic_data = $db->prepare("
    SELECT 
        c.name as country,
        COUNT(b.id) as bookings,
        SUM(b.total_amount) as revenue,
        COUNT(DISTINCT b.user_id) as customers
    FROM countries c
    LEFT JOIN tours t ON c.id = t.country_id
    LEFT JOIN bookings b ON t.id = b.tour_id 
        AND b.created_at BETWEEN ? AND ?
        AND b.status IN ('confirmed', 'completed')
    WHERE c.status = 'active'
    GROUP BY c.id, c.name
    HAVING bookings > 0
    ORDER BY revenue DESC
    LIMIT 10
");
$geographic_data->execute([$date_from, $date_to . ' 23:59:59']);
$geo_performance = $geographic_data->fetchAll();

// Conversion funnel
$funnel_data = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM analytics_events WHERE event_name = 'page_view' AND page_url LIKE '%tours%') as tour_views,
        (SELECT COUNT(*) FROM analytics_events WHERE event_name = 'tour_detail_view') as tour_detail_views,
        (SELECT COUNT(*) FROM analytics_events WHERE event_name = 'booking_started') as booking_started,
        (SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed', 'completed')) as bookings_completed
")->fetch();

// Recent activity
$recent_bookings = $db->prepare("
    SELECT 
        b.id,
        b.booking_reference,
        b.total_amount,
        b.created_at,
        b.status,
        u.first_name,
        u.last_name,
        t.title as tour_title
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN tours t ON b.tour_id = t.id
    WHERE b.created_at BETWEEN ? AND ?
    ORDER BY b.created_at DESC
    LIMIT 10
");
$recent_bookings->execute([$date_from, $date_to . ' 23:59:59']);
$recent_activity = $recent_bookings->fetchAll();
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
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
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
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .kpi-title {
            color: #666;
            font-size: 0.9em;
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

        .chart-container {
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
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .chart-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
        }

        .chart-canvas {
            position: relative;
            height: 300px;
        }

        .performance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .performance-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
        }

        .table-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #333;
        }

        .table-content {
            max-height: 400px;
            overflow-y: auto;
        }

        .performance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }

        .performance-item:hover {
            background: #f8f9fa;
        }

        .performance-item:last-child {
            border-bottom: none;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .item-meta {
            font-size: 0.8em;
            color: #666;
        }

        .item-stats {
            text-align: right;
        }

        .item-value {
            font-weight: 600;
            color: var(--admin-primary);
        }

        .item-metric {
            font-size: 0.8em;
            color: #666;
        }

        .funnel-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .funnel-step {
            display: flex;
            align-items: center;
            padding: 15px 0;
            position: relative;
        }

        .funnel-step:not(:last-child)::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 30px;
            right: 0;
            height: 1px;
            background: #eee;
        }

        .funnel-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--admin-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.2em;
        }

        .funnel-info {
            flex: 1;
        }

        .funnel-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .funnel-value {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--admin-primary);
        }

        .funnel-rate {
            font-size: 0.9em;
            color: #666;
        }

        .recent-activity {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 25px;
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
            color: #1976d2;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .activity-info {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .activity-meta {
            font-size: 0.8em;
            color: #666;
        }

        .activity-value {
            font-weight: 600;
            color: var(--admin-primary);
        }

        @media (max-width: 768px) {

            .charts-grid,
            .performance-grid {
                grid-template-columns: 1fr;
            }

            .date-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .kpi-grid {
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
                <div class="analytics-header">
                    <h1>Analytics Dashboard</h1>
                    <p>Track your business performance and key metrics</p>
                </div>

                <!-- Date Filters -->
                <div class="date-filters">
                    <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div>
                            <label>From:</label>
                            <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-control">
                        </div>
                        <div>
                            <label>To:</label>
                            <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-control">
                        </div>
                        <div>
                            <label>Compare to:</label>
                            <select name="compare" class="form-control">
                                <option value="previous_period" <?php echo $compare_period === 'previous_period' ? 'selected' : ''; ?>>Previous Period</option>
                                <option value="previous_year" <?php echo $compare_period === 'previous_year' ? 'selected' : ''; ?>>Previous Year</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <button type="button" class="btn btn-outline" onclick="exportData()">
                            <i class="fas fa-download"></i> Export
                        </button>
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
                        <div class="kpi-value"><?php echo formatCurrency($current_stats['total_revenue']); ?></div>
                        <div class="kpi-growth">
                            <?php
                            $revenue_growth = calculateGrowth($current_stats['total_revenue'], $previous_stats['total_revenue']);
                            $growth_class = $revenue_growth > 0 ? 'growth-positive' : ($revenue_growth < 0 ? 'growth-negative' : 'growth-neutral');
                            ?>
                            <i
                                class="fas fa-arrow-<?php echo $revenue_growth >= 0 ? 'up' : 'down'; ?> <?php echo $growth_class; ?>"></i>
                            <span class="<?php echo $growth_class; ?>"><?php echo abs($revenue_growth); ?>%</span>
                            <span>vs previous period</span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div class="kpi-title">Total Bookings</div>
                            <div class="kpi-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="kpi-value"><?php echo number_format($current_stats['total_bookings']); ?></div>
                        <div class="kpi-growth">
                            <?php
                            $bookings_growth = calculateGrowth($current_stats['total_bookings'], $previous_stats['total_bookings']);
                            $growth_class = $bookings_growth > 0 ? 'growth-positive' : ($bookings_growth < 0 ? 'growth-negative' : 'growth-neutral');
                            ?>
                            <i
                                class="fas fa-arrow-<?php echo $bookings_growth >= 0 ? 'up' : 'down'; ?> <?php echo $growth_class; ?>"></i>
                            <span class="<?php echo $growth_class; ?>"><?php echo abs($bookings_growth); ?>%</span>
                            <span>vs previous period</span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div class="kpi-title">Average Booking Value</div>
                            <div class="kpi-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        <div class="kpi-value"><?php echo formatCurrency($current_stats['avg_booking_value']); ?></div>
                        <div class="kpi-growth">
                            <?php
                            $abv_growth = calculateGrowth($current_stats['avg_booking_value'], $previous_stats['avg_booking_value']);
                            $growth_class = $abv_growth > 0 ? 'growth-positive' : ($abv_growth < 0 ? 'growth-negative' : 'growth-neutral');
                            ?>
                            <i
                                class="fas fa-arrow-<?php echo $abv_growth >= 0 ? 'up' : 'down'; ?> <?php echo $growth_class; ?>"></i>
                            <span class="<?php echo $growth_class; ?>"><?php echo abs($abv_growth); ?>%</span>
                            <span>vs previous period</span>
                        </div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div class="kpi-title">Unique Customers</div>
                            <div class="kpi-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="kpi-value"><?php echo number_format($current_stats['unique_customers']); ?></div>
                        <div class="kpi-growth">
                            <?php
                            $customers_growth = calculateGrowth($current_stats['unique_customers'], $previous_stats['unique_customers']);
                            $growth_class = $customers_growth > 0 ? 'growth-positive' : ($customers_growth < 0 ? 'growth-negative' : 'growth-neutral');
                            ?>
                            <i
                                class="fas fa-arrow-<?php echo $customers_growth >= 0 ? 'up' : 'down'; ?> <?php echo $growth_class; ?>"></i>
                            <span class="<?php echo $growth_class; ?>"><?php echo abs($customers_growth); ?>%</span>
                            <span>vs previous period</span>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="charts-grid">
                    <div class="chart-container">
                        <div class="chart-header">
                            <div class="chart-title">Revenue Trend</div>
                            <div class="chart-controls">
                                <button class="btn btn-sm btn-outline" onclick="toggleChartType('revenue')">
                                    <i class="fas fa-chart-bar"></i>
                                </button>
                            </div>
                        </div>
                        <div class="chart-canvas">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <div class="chart-header">
                            <div class="chart-title">Conversion Funnel</div>
                        </div>
                        <div class="funnel-container">
                            <div class="funnel-step">
                                <div class="funnel-icon">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <div class="funnel-info">
                                    <div class="funnel-label">Tour Page Views</div>
                                    <div class="funnel-value"><?php echo number_format($funnel_data['tour_views']); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="funnel-step">
                                <div class="funnel-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="funnel-info">
                                    <div class="funnel-label">Tour Details Viewed</div>
                                    <div class="funnel-value">
                                        <?php echo number_format($funnel_data['tour_detail_views']); ?></div>
                                    <div class="funnel-rate">
                                        <?php echo $funnel_data['tour_views'] > 0 ? round(($funnel_data['tour_detail_views'] / $funnel_data['tour_views']) * 100, 1) : 0; ?>%
                                        conversion
                                    </div>
                                </div>
                            </div>

                            <div class="funnel-step">
                                <div class="funnel-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="funnel-info">
                                    <div class="funnel-label">Booking Started</div>
                                    <div class="funnel-value">
                                        <?php echo number_format($funnel_data['booking_started']); ?></div>
                                    <div class="funnel-rate">
                                        <?php echo $funnel_data['tour_detail_views'] > 0 ? round(($funnel_data['booking_started'] / $funnel_data['tour_detail_views']) * 100, 1) : 0; ?>%
                                        conversion
                                    </div>
                                </div>
                            </div>

                            <div class="funnel-step">
                                <div class="funnel-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="funnel-info">
                                    <div class="funnel-label">Bookings Completed</div>
                                    <div class="funnel-value">
                                        <?php echo number_format($funnel_data['bookings_completed']); ?></div>
                                    <div class="funnel-rate">
                                        <?php echo $funnel_data['booking_started'] > 0 ? round(($funnel_data['bookings_completed'] / $funnel_data['booking_started']) * 100, 1) : 0; ?>%
                                        conversion
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Tables -->
                <div class="performance-grid">
                    <div class="performance-table">
                        <div class="table-header">
                            <div class="table-title">Top Performing Tours</div>
                        </div>
                        <div class="table-content">
                            <?php foreach ($tour_performance as $tour): ?>
                                <div class="performance-item">
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($tour['title']); ?></div>
                                        <div class="item-meta"><?php echo htmlspecialchars($tour['country_name']); ?></div>
                                    </div>
                                    <div class="item-stats">
                                        <div class="item-value"><?php echo formatCurrency($tour['total_revenue']); ?></div>
                                        <div class="item-metric"><?php echo $tour['booking_count']; ?> bookings</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="performance-table">
                        <div class="table-header">
                            <div class="table-title">Top Destinations</div>
                        </div>
                        <div class="table-content">
                            <?php foreach ($geo_performance as $destination): ?>
                                <div class="performance-item">
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($destination['country']); ?>
                                        </div>
                                        <div class="item-meta"><?php echo $destination['customers']; ?> customers</div>
                                    </div>
                                    <div class="item-stats">
                                        <div class="item-value"><?php echo formatCurrency($destination['revenue']); ?></div>
                                        <div class="item-metric"><?php echo $destination['bookings']; ?> bookings</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <div class="table-header">
                        <div class="table-title">Recent Bookings</div>
                    </div>
                    <div class="table-content">
                        <?php foreach ($recent_activity as $booking): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="activity-info">
                                    <div class="activity-title">
                                        <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                        booked <?php echo htmlspecialchars($booking['tour_title']); ?>
                                    </div>
                                    <div class="activity-meta">
                                        <?php echo timeAgo($booking['created_at']); ?> •
                                        Ref: <?php echo htmlspecialchars($booking['booking_reference']); ?>
                                    </div>
                                </div>
                                <div class="activity-value">
                                    <?php echo formatCurrency($booking['total_amount']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
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
                labels: [<?php echo "'" . implode("','", array_column($daily_data, 'date')) . "'"; ?>],
                datasets: [{
                    label: 'Revenue',
                    data: [<?php echo implode(',', array_column($daily_data, 'revenue')); ?>],
                    borderColor: '#D4A574',
                    backgroundColor: 'rgba(212, 165, 116, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Bookings',
                    data: [<?php echo implode(',', array_column($daily_data, 'bookings')); ?>],
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

        // Auto-refresh data every 5 minutes
        setInterval(function () {
            location.reload();
        }, 300000);

        // Export functionality
        function exportData() {
            const dateFrom = '<?php echo $date_from; ?>';
            const dateTo = '<?php echo $date_to; ?>';
            window.open(`../api/export-analytics.php?date_from=${dateFrom}&date_to=${dateTo}`, '_blank');
        }

        // Toggle chart type
        function toggleChartType(chartId) {
            if (chartId === 'revenue') {
                revenueChart.config.type = revenueChart.config.type === 'line' ? 'bar' : 'line';
                revenueChart.update();
            }
        }
    </script>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>