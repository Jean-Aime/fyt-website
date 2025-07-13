<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('analytics.view');

$report_type = $_GET['type'] ?? '';
$date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['to'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'html';

if (!$report_type) {
    header('Location: reports.php');
    exit;
}

// Generate report data based on type
function generateReportData($db, $type, $date_from, $date_to)
{
    $data = [];

    switch ($type) {
        case 'revenue_summary':
            $data = generateRevenueSummary($db, $date_from, $date_to);
            break;
        case 'booking_analysis':
            $data = generateBookingAnalysis($db, $date_from, $date_to);
            break;
        case 'customer_insights':
            $data = generateCustomerInsights($db, $date_from, $date_to);
            break;
        case 'tour_performance':
            $data = generateTourPerformance($db, $date_from, $date_to);
            break;
        case 'geographic_analysis':
            $data = generateGeographicAnalysis($db, $date_from, $date_to);
            break;
        case 'payment_analysis':
            $data = generatePaymentAnalysis($db, $date_from, $date_to);
            break;
    }

    return $data;
}

function generateRevenueSummary($db, $date_from, $date_to)
{
    // Total revenue metrics
    $total_revenue = $db->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COUNT(*) as total_bookings,
            COALESCE(AVG(total_amount), 0) as avg_booking_value
        FROM bookings 
        WHERE created_at BETWEEN ? AND ?
    ");
    $total_revenue->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $revenue_summary = $total_revenue->fetch(PDO::FETCH_ASSOC);

    // Daily revenue trend
    $daily_revenue = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COALESCE(SUM(total_amount), 0) as revenue,
            COUNT(*) as bookings
        FROM bookings 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $daily_revenue->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $daily_data = $daily_revenue->fetchAll(PDO::FETCH_ASSOC);

    // Revenue by category
    $category_revenue = $db->prepare("
        SELECT 
            tc.name as category,
            COALESCE(SUM(b.total_amount), 0) as revenue,
            COUNT(b.id) as bookings
        FROM tour_categories tc
        LEFT JOIN tours t ON tc.id = t.category_id
        LEFT JOIN bookings b ON t.id = b.tour_id 
            AND b.created_at BETWEEN ? AND ?
        GROUP BY tc.id
        HAVING revenue > 0
        ORDER BY revenue DESC
    ");
    $category_revenue->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $category_data = $category_revenue->fetchAll(PDO::FETCH_ASSOC);

    return [
        'summary' => $revenue_summary,
        'daily_trend' => $daily_data,
        'category_breakdown' => $category_data
    ];
}

function generateBookingAnalysis($db, $date_from, $date_to)
{
    // Booking status distribution
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

    // Lead time analysis
    $lead_time = $db->prepare("
        SELECT 
            CASE 
                WHEN DATEDIFF(tour_date, created_at) <= 7 THEN 'Last Minute (≤7 days)'
                WHEN DATEDIFF(tour_date, created_at) <= 30 THEN 'Short Term (8-30 days)'
                WHEN DATEDIFF(tour_date, created_at) <= 90 THEN 'Medium Term (31-90 days)'
                ELSE 'Long Term (>90 days)'
            END as lead_time_category,
            COUNT(*) as bookings,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM bookings 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY lead_time_category
    ");
    $lead_time->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $lead_time_data = $lead_time->fetchAll(PDO::FETCH_ASSOC);

    // Group size analysis
    $group_size = $db->prepare("
        SELECT 
            (adults + children + infants) as total_travelers,
            COUNT(*) as bookings,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM bookings 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY total_travelers
        ORDER BY total_travelers
    ");
    $group_size->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $group_data = $group_size->fetchAll(PDO::FETCH_ASSOC);

    return [
        'status_distribution' => $status_data,
        'lead_time_analysis' => $lead_time_data,
        'group_size_analysis' => $group_data
    ];
}

function generateCustomerInsights($db, $date_from, $date_to)
{
    // Customer segmentation
    $customer_segments = $db->prepare("
        SELECT 
            CASE 
                WHEN booking_count = 1 THEN 'First-time Customers'
                WHEN booking_count BETWEEN 2 AND 3 THEN 'Repeat Customers'
                ELSE 'Loyal Customers'
            END as segment,
            COUNT(*) as customer_count,
            COALESCE(SUM(total_value), 0) as total_revenue,
            COALESCE(AVG(total_value), 0) as avg_customer_value
        FROM (
            SELECT 
                user_id,
                COUNT(*) as booking_count,
                SUM(total_amount) as total_value
            FROM bookings 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY user_id
        ) customer_stats
        GROUP BY segment
    ");
    $customer_segments->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $segment_data = $customer_segments->fetchAll(PDO::FETCH_ASSOC);

    // Top customers
    $top_customers = $db->prepare("
        SELECT 
            CONCAT(u.first_name, ' ', u.last_name) as customer_name,
            u.email,
            COUNT(b.id) as booking_count,
            COALESCE(SUM(b.total_amount), 0) as total_value,
            MAX(b.created_at) as last_booking
        FROM users u
        JOIN bookings b ON u.id = b.user_id
        WHERE b.created_at BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY total_value DESC
        LIMIT 20
    ");
    $top_customers->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $top_customer_data = $top_customers->fetchAll(PDO::FETCH_ASSOC);

    return [
        'customer_segments' => $segment_data,
        'top_customers' => $top_customer_data
    ];
}

function generateTourPerformance($db, $date_from, $date_to)
{
    // Tour performance metrics
    $tour_performance = $db->prepare("
        SELECT 
            t.title,
            t.featured_image,
            COUNT(b.id) as booking_count,
            COALESCE(SUM(b.total_amount), 0) as revenue,
            COALESCE(AVG(b.total_amount), 0) as avg_booking_value,
            t.price_adult,
            c.name as country_name,
            tc.name as category_name
        FROM tours t
        LEFT JOIN bookings b ON t.id = b.tour_id 
            AND b.created_at BETWEEN ? AND ?
        LEFT JOIN countries c ON t.country_id = c.id
        LEFT JOIN tour_categories tc ON t.category_id = tc.id
        WHERE t.status = 'active'
        GROUP BY t.id
        ORDER BY revenue DESC
    ");
    $tour_performance->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $tour_data = $tour_performance->fetchAll(PDO::FETCH_ASSOC);

    // Tour conversion rates
    $tour_conversion = $db->prepare("
        SELECT 
            t.title,
            t.view_count,
            COUNT(b.id) as bookings,
            CASE 
                WHEN t.view_count > 0 THEN ROUND((COUNT(b.id) / t.view_count) * 100, 2)
                ELSE 0
            END as conversion_rate
        FROM tours t
        LEFT JOIN bookings b ON t.id = b.tour_id 
            AND b.created_at BETWEEN ? AND ?
        WHERE t.status = 'active' AND t.view_count > 0
        GROUP BY t.id
        ORDER BY conversion_rate DESC
        LIMIT 20
    ");
    $tour_conversion->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $conversion_data = $tour_conversion->fetchAll(PDO::FETCH_ASSOC);

    return [
        'tour_performance' => $tour_data,
        'conversion_rates' => $conversion_data
    ];
}

function generateGeographicAnalysis($db, $date_from, $date_to)
{
    // Country performance
    $country_performance = $db->prepare("
        SELECT 
            c.name as country,
            c.continent,
            COUNT(b.id) as bookings,
            COALESCE(SUM(b.total_amount), 0) as revenue,
            COUNT(DISTINCT t.id) as tour_count
        FROM countries c
        LEFT JOIN tours t ON c.id = t.country_id
        LEFT JOIN bookings b ON t.id = b.tour_id 
            AND b.created_at BETWEEN ? AND ?
        WHERE c.status = 'active'
        GROUP BY c.id
        HAVING bookings > 0
        ORDER BY revenue DESC
    ");
    $country_performance->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $country_data = $country_performance->fetchAll(PDO::FETCH_ASSOC);

    // Regional analysis
    $regional_analysis = $db->prepare("
        SELECT 
            r.name as region,
            c.name as country,
            COUNT(b.id) as bookings,
            COALESCE(SUM(b.total_amount), 0) as revenue
        FROM regions r
        LEFT JOIN countries c ON r.country_id = c.id
        LEFT JOIN tours t ON r.id = t.region_id
        LEFT JOIN bookings b ON t.id = b.tour_id 
            AND b.created_at BETWEEN ? AND ?
        WHERE r.status = 'active'
        GROUP BY r.id
        HAVING bookings > 0
        ORDER BY revenue DESC
    ");
    $regional_analysis->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $regional_data = $regional_analysis->fetchAll(PDO::FETCH_ASSOC);

    return [
        'country_performance' => $country_data,
        'regional_analysis' => $regional_data
    ];
}

function generatePaymentAnalysis($db, $date_from, $date_to)
{
    // Payment method performance
    $payment_methods = $db->prepare("
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(AVG(amount), 0) as avg_transaction,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
            ROUND((COUNT(CASE WHEN status = 'completed' THEN 1 END) / COUNT(*)) * 100, 2) as success_rate
        FROM payments 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $payment_methods->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $payment_data = $payment_methods->fetchAll(PDO::FETCH_ASSOC);

    // Payment timing analysis
    $payment_timing = $db->prepare("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as payment_count,
            COALESCE(SUM(amount), 0) as total_amount
        FROM payments 
        WHERE created_at BETWEEN ? AND ? AND status = 'completed'
        GROUP BY HOUR(created_at)
        ORDER BY hour
    ");
    $payment_timing->execute([$date_from . ' 00:00:00', $date_to . ' 23:59:59']);
    $timing_data = $payment_timing->fetchAll(PDO::FETCH_ASSOC);

    return [
        'payment_methods' => $payment_data,
        'payment_timing' => $timing_data
    ];
}

// Generate the report data
$report_data = generateReportData($db, $report_type, $date_from, $date_to);

// Save report to database
$report_name = $_GET['name'] ?? ucwords(str_replace('_', ' ', $report_type)) . ' Report';
$stmt = $db->prepare("
    INSERT INTO reports (name, report_type, parameters, status, created_by)
    VALUES (?, ?, ?, 'completed', ?)
");
$stmt->execute([
    $report_name,
    $report_type,
    json_encode(['date_from' => $date_from, 'date_to' => $date_to, 'format' => $format]),
    $_SESSION['user_id']
]);

$report_id = $db->lastInsertId();

// Handle different output formats
if ($format === 'pdf') {
    // Generate PDF
    require_once '../../vendor/autoload.php'; // Assuming TCPDF or similar is installed
    // PDF generation code here
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $report_name . '.pdf"');
    // Output PDF content
    exit;
} elseif ($format === 'excel') {
    // Generate Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $report_name . '.xlsx"');
    // Output Excel content
    exit;
} elseif ($format === 'csv') {
    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_name . '.csv"');
    // Output CSV content
    exit;
}

// Default HTML output
$page_title = $report_name;
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
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            print-color-adjust: exact;
        }

        .report-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .report-actions {
            display: flex;
            gap: 10px;
        }

        .report-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .metric-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--admin-primary);
            margin-bottom: 5px;
        }

        .metric-label {
            color: #666;
            font-size: 0.9em;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        @media print {

            .report-actions,
            .admin-wrapper .sidebar,
            .admin-wrapper .header {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding-top: 0 !important;
            }

            .report-section {
                box-shadow: none;
                border: 1px solid #ddd;
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
                <!-- Report Header -->
                <div class="report-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="report-meta">
                        <div>
                            <p>Report Period: <?php echo date('M j, Y', strtotime($date_from)); ?> -
                                <?php echo date('M j, Y', strtotime($date_to)); ?></p>
                            <p>Generated: <?php echo date('M j, Y g:i A'); ?></p>
                        </div>
                        <div class="report-actions">
                            <button onclick="window.print()" class="btn btn-light">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <a href="generate-report.php?<?php echo http_build_query($_GET + ['format' => 'pdf']); ?>"
                                class="btn btn-light">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                            <a href="generate-report.php?<?php echo http_build_query($_GET + ['format' => 'excel']); ?>"
                                class="btn btn-light">
                                <i class="fas fa-file-excel"></i> Excel
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($report_type === 'revenue_summary'): ?>
                    <!-- Revenue Summary Report -->
                    <div class="report-section">
                        <h2 class="section-title">Revenue Overview</h2>
                        <div class="metrics-grid">
                            <div class="metric-card">
                                <div class="metric-value">
                                    $<?php echo number_format($report_data['summary']['total_revenue']); ?></div>
                                <div class="metric-label">Total Revenue</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-value">
                                    <?php echo number_format($report_data['summary']['total_bookings']); ?></div>
                                <div class="metric-label">Total Bookings</div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-value">
                                    $<?php echo number_format($report_data['summary']['avg_booking_value']); ?></div>
                                <div class="metric-label">Average Booking Value</div>
                            </div>
                        </div>

                        <h3>Daily Revenue Trend</h3>
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>

                        <h3>Revenue by Category</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                    <th>Avg. Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['category_breakdown'] as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['category']); ?></td>
                                        <td><?php echo number_format($category['bookings']); ?></td>
                                        <td>$<?php echo number_format($category['revenue']); ?></td>
                                        <td>$<?php echo number_format($category['revenue'] / max($category['bookings'], 1)); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type === 'booking_analysis'): ?>
                    <!-- Booking Analysis Report -->
                    <div class="report-section">
                        <h2 class="section-title">Booking Status Distribution</h2>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Revenue</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_bookings = array_sum(array_column($report_data['status_distribution'], 'count'));
                                foreach ($report_data['status_distribution'] as $status):
                                    ?>
                                    <tr>
                                        <td><?php echo ucfirst($status['status']); ?></td>
                                        <td><?php echo number_format($status['count']); ?></td>
                                        <td>$<?php echo number_format($status['revenue']); ?></td>
                                        <td><?php echo round(($status['count'] / $total_bookings) * 100, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="report-section">
                        <h2 class="section-title">Lead Time Analysis</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Lead Time Category</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                    <th>Avg. Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['lead_time_analysis'] as $lead_time): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($lead_time['lead_time_category']); ?></td>
                                        <td><?php echo number_format($lead_time['bookings']); ?></td>
                                        <td>$<?php echo number_format($lead_time['revenue']); ?></td>
                                        <td>$<?php echo number_format($lead_time['revenue'] / max($lead_time['bookings'], 1)); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type === 'customer_insights'): ?>
                    <!-- Customer Insights Report -->
                    <div class="report-section">
                        <h2 class="section-title">Customer Segmentation</h2>
                        <div class="chart-container">
                            <canvas id="segmentChart"></canvas>
                        </div>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Segment</th>
                                    <th>Customers</th>
                                    <th>Total Revenue</th>
                                    <th>Avg. Customer Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['customer_segments'] as $segment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($segment['segment']); ?></td>
                                        <td><?php echo number_format($segment['customer_count']); ?></td>
                                        <td>$<?php echo number_format($segment['total_revenue']); ?></td>
                                        <td>$<?php echo number_format($segment['avg_customer_value']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="report-section">
                        <h2 class="section-title">Top Customers</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Bookings</th>
                                    <th>Total Value</th>
                                    <th>Last Booking</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['top_customers'] as $customer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo number_format($customer['booking_count']); ?></td>
                                        <td>$<?php echo number_format($customer['total_value']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($customer['last_booking'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type === 'tour_performance'): ?>
                    <!-- Tour Performance Report -->
                    <div class="report-section">
                        <h2 class="section-title">Tour Performance Metrics</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tour</th>
                                    <th>Country</th>
                                    <th>Category</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                    <th>Avg. Value</th>
                                    <th>Base Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['tour_performance'] as $tour): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tour['title']); ?></td>
                                        <td><?php echo htmlspecialchars($tour['country_name']); ?></td>
                                        <td><?php echo htmlspecialchars($tour['category_name']); ?></td>
                                        <td><?php echo number_format($tour['booking_count']); ?></td>
                                        <td>$<?php echo number_format($tour['revenue']); ?></td>
                                        <td>$<?php echo number_format($tour['avg_booking_value']); ?></td>
                                        <td>$<?php echo number_format($tour['price_adult']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="report-section">
                        <h2 class="section-title">Conversion Rates</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tour</th>
                                    <th>Page Views</th>
                                    <th>Bookings</th>
                                    <th>Conversion Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['conversion_rates'] as $conversion): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($conversion['title']); ?></td>
                                        <td><?php echo number_format($conversion['view_count']); ?></td>
                                        <td><?php echo number_format($conversion['bookings']); ?></td>
                                        <td><?php echo $conversion['conversion_rate']; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type === 'geographic_analysis'): ?>
                    <!-- Geographic Analysis Report -->
                    <div class="report-section">
                        <h2 class="section-title">Country Performance</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Country</th>
                                    <th>Continent</th>
                                    <th>Tours</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                    <th>Avg. Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['country_performance'] as $country): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($country['country']); ?></td>
                                        <td><?php echo htmlspecialchars($country['continent']); ?></td>
                                        <td><?php echo number_format($country['tour_count']); ?></td>
                                        <td><?php echo number_format($country['bookings']); ?></td>
                                        <td>$<?php echo number_format($country['revenue']); ?></td>
                                        <td>$<?php echo number_format($country['revenue'] / max($country['bookings'], 1)); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($report_data['regional_analysis'])): ?>
                        <div class="report-section">
                            <h2 class="section-title">Regional Analysis</h2>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Region</th>
                                        <th>Country</th>
                                        <th>Bookings</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['regional_analysis'] as $region): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($region['region']); ?></td>
                                            <td><?php echo htmlspecialchars($region['country']); ?></td>
                                            <td><?php echo number_format($region['bookings']); ?></td>
                                            <td>$<?php echo number_format($region['revenue']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'payment_analysis'): ?>
                    <!-- Payment Analysis Report -->
                    <div class="report-section">
                        <h2 class="section-title">Payment Method Performance</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th>Transactions</th>
                                    <th>Total Amount</th>
                                    <th>Avg. Transaction</th>
                                    <th>Success Rate</th>
                                    <th>Failed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['payment_methods'] as $method): ?>
                                    <tr>
                                        <td><?php echo ucwords(str_replace('_', ' ', $method['payment_method'])); ?></td>
                                        <td><?php echo number_format($method['transaction_count']); ?></td>
                                        <td>$<?php echo number_format($method['total_amount']); ?></td>
                                        <td>$<?php echo number_format($method['avg_transaction']); ?></td>
                                        <td><?php echo $method['success_rate']; ?>%</td>
                                        <td><?php echo number_format($method['failed_payments']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="report-section">
                        <h2 class="section-title">Payment Timing Analysis</h2>
                        <div class="chart-container">
                            <canvas id="timingChart"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Chart configurations based on report type
        <?php if ($report_type === 'revenue_summary'): ?>
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($report_data['daily_trend'], 'date')); ?>,
                    datasets: [{
                        label: 'Daily Revenue',
                        data: <?php echo json_encode(array_column($report_data['daily_trend'], 'revenue')); ?>,
                        borderColor: '#D4A574',
                        backgroundColor: 'rgba(212, 165, 116, 0.1)',
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
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            }
                        }
                    }
                }
            });

        <?php elseif ($report_type === 'booking_analysis'): ?>
            // Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($report_data['status_distribution'], 'status')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($report_data['status_distribution'], 'count')); ?>,
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

        <?php elseif ($report_type === 'customer_insights'): ?>
            // Segment Chart
            const segmentCtx = document.getElementById('segmentChart').getContext('2d');
            new Chart(segmentCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($report_data['customer_segments'], 'segment')); ?>,
                    datasets: [{
                        label: 'Customer Count',
                        data: <?php echo json_encode(array_column($report_data['customer_segments'], 'customer_count')); ?>,
                        backgroundColor: 'rgba(212, 165, 116, 0.8)',
                        borderColor: '#D4A574',
                        borderWidth: 1
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
                            beginAtZero: true
                        }
                    }
                }
            });

        <?php elseif ($report_type === 'payment_analysis'): ?>
            // Timing Chart
            const timingCtx = document.getElementById('timingChart').getContext('2d');
            new Chart(timingCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function ($h) {
                        return $h['hour'] . ':00'; }, $report_data['payment_timing'])); ?>,
                    datasets: [{
                        label: 'Payment Count',
                        data: <?php echo json_encode(array_column($report_data['payment_timing'], 'payment_count')); ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: '#667eea',
                        borderWidth: 1
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
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Payments'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Hour of Day'
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>