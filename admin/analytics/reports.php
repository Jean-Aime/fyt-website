<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('analytics.view');

$page_title = 'Analytics Reports';

// Handle report generation
if ($_POST && isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $format = $_POST['format'] ?? 'html';

    // Redirect to report generation
    header("Location: generate-report.php?type=$report_type&from=$date_from&to=$date_to&format=$format");
    exit;
}

// Get saved reports
$saved_reports = $db->query("
    SELECT * FROM reports 
    WHERE created_by = {$_SESSION['user_id']} 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Report templates
$report_templates = [
    'revenue_summary' => [
        'name' => 'Revenue Summary Report',
        'description' => 'Comprehensive revenue analysis with trends and breakdowns',
        'icon' => 'fas fa-chart-line',
        'color' => '#28a745'
    ],
    'booking_analysis' => [
        'name' => 'Booking Analysis Report',
        'description' => 'Detailed booking patterns, conversion rates, and customer behavior',
        'icon' => 'fas fa-calendar-check',
        'color' => '#007bff'
    ],
    'customer_insights' => [
        'name' => 'Customer Insights Report',
        'description' => 'Customer segmentation, lifetime value, and acquisition analysis',
        'icon' => 'fas fa-users',
        'color' => '#6f42c1'
    ],
    'tour_performance' => [
        'name' => 'Tour Performance Report',
        'description' => 'Individual tour metrics, popularity, and profitability analysis',
        'icon' => 'fas fa-route',
        'color' => '#fd7e14'
    ],
    'geographic_analysis' => [
        'name' => 'Geographic Analysis Report',
        'description' => 'Destination performance and regional market analysis',
        'icon' => 'fas fa-globe',
        'color' => '#20c997'
    ],
    'payment_analysis' => [
        'name' => 'Payment Analysis Report',
        'description' => 'Payment method performance, success rates, and financial insights',
        'icon' => 'fas fa-credit-card',
        'color' => '#e83e8c'
    ]
];
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
        .reports-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .report-templates {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .report-template {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .report-template:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .report-template.selected {
            border-color: var(--admin-primary);
            background: rgba(212, 165, 116, 0.05);
        }

        .template-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .template-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            color: white;
        }

        .template-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
        }

        .template-description {
            color: #666;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .template-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .template-features li {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9em;
            color: #666;
        }

        .template-features i {
            color: #28a745;
            width: 16px;
        }

        .report-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            display: none;
        }

        .report-form.show {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .saved-reports {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .saved-reports-header {
            padding: 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .reports-table {
            width: 100%;
        }

        .reports-table th,
        .reports-table td {
            padding: 15px 25px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .reports-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .reports-table tr:hover {
            background: #f8f9fa;
        }

        .report-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }

        .report-status.completed {
            background: #d4edda;
            color: #155724;
        }

        .report-status.processing {
            background: #fff3cd;
            color: #856404;
        }

        .report-status.failed {
            background: #f8d7da;
            color: #721c24;
        }

        .quick-insights {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .insight-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .insight-value {
            font-size: 1.8em;
            font-weight: bold;
            color: var(--admin-primary);
            margin-bottom: 5px;
        }

        .insight-label {
            color: #666;
            font-size: 0.9em;
        }

        .format-options {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .format-option {
            flex: 1;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .format-option:hover,
        .format-option.selected {
            border-color: var(--admin-primary);
            background: rgba(212, 165, 116, 0.1);
        }

        .format-option input {
            display: none;
        }

        .format-option i {
            font-size: 1.5em;
            margin-bottom: 5px;
            color: var(--admin-primary);
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="content">
                <!-- Reports Header -->
                <div class="reports-header">
                    <h1>Analytics Reports</h1>
                    <p>Generate comprehensive reports and insights for data-driven decisions</p>
                </div>

                <!-- Quick Insights -->
                <div class="quick-insights">
                    <?php
                    $quick_stats = $db->query("
                        SELECT 
                            COUNT(DISTINCT b.id) as total_bookings,
                            COALESCE(SUM(b.total_amount), 0) as total_revenue,
                            COUNT(DISTINCT b.user_id) as unique_customers,
                            COUNT(DISTINCT t.id) as active_tours
                        FROM bookings b
                        LEFT JOIN tours t ON b.tour_id = t.id
                        WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ")->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <div class="insight-card">
                        <div class="insight-value"><?php echo number_format($quick_stats['total_bookings']); ?></div>
                        <div class="insight-label">Bookings (30 days)</div>
                    </div>
                    <div class="insight-card">
                        <div class="insight-value">$<?php echo number_format($quick_stats['total_revenue']); ?></div>
                        <div class="insight-label">Revenue (30 days)</div>
                    </div>
                    <div class="insight-card">
                        <div class="insight-value"><?php echo number_format($quick_stats['unique_customers']); ?></div>
                        <div class="insight-label">Customers (30 days)</div>
                    </div>
                    <div class="insight-card">
                        <div class="insight-value"><?php echo number_format($quick_stats['active_tours']); ?></div>
                        <div class="insight-label">Active Tours</div>
                    </div>
                </div>

                <!-- Report Templates -->
                <h2 style="margin-bottom: 20px;">Choose Report Type</h2>
                <div class="report-templates">
                    <?php foreach ($report_templates as $key => $template): ?>
                        <div class="report-template" onclick="selectTemplate('<?php echo $key; ?>')">
                            <div class="template-header">
                                <div class="template-icon" style="background: <?php echo $template['color']; ?>">
                                    <i class="<?php echo $template['icon']; ?>"></i>
                                </div>
                                <div class="template-title"><?php echo $template['name']; ?></div>
                            </div>
                            <div class="template-description">
                                <?php echo $template['description']; ?>
                            </div>
                            <ul class="template-features">
                                <?php if ($key === 'revenue_summary'): ?>
                                    <li><i class="fas fa-check"></i> Revenue trends and forecasting</li>
                                    <li><i class="fas fa-check"></i> Category-wise revenue breakdown</li>
                                    <li><i class="fas fa-check"></i> Seasonal performance analysis</li>
                                <?php elseif ($key === 'booking_analysis'): ?>
                                    <li><i class="fas fa-check"></i> Conversion funnel analysis</li>
                                    <li><i class="fas fa-check"></i> Booking patterns and trends</li>
                                    <li><i class="fas fa-check"></i> Lead time analysis</li>
                                <?php elseif ($key === 'customer_insights'): ?>
                                    <li><i class="fas fa-check"></i> Customer segmentation</li>
                                    <li><i class="fas fa-check"></i> Lifetime value calculation</li>
                                    <li><i class="fas fa-check"></i> Acquisition channel analysis</li>
                                <?php elseif ($key === 'tour_performance'): ?>
                                    <li><i class="fas fa-check"></i> Individual tour metrics</li>
                                    <li><i class="fas fa-check"></i> Popularity rankings</li>
                                    <li><i class="fas fa-check"></i> Profitability analysis</li>
                                <?php elseif ($key === 'geographic_analysis'): ?>
                                    <li><i class="fas fa-check"></i> Destination performance</li>
                                    <li><i class="fas fa-check"></i> Regional market analysis</li>
                                    <li><i class="fas fa-check"></i> Geographic trends</li>
                                <?php elseif ($key === 'payment_analysis'): ?>
                                    <li><i class="fas fa-check"></i> Payment method performance</li>
                                    <li><i class="fas fa-check"></i> Success/failure rates</li>
                                    <li><i class="fas fa-check"></i> Financial insights</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Report Generation Form -->
                <div class="report-form" id="reportForm">
                    <h3>Generate Report</h3>
                    <form method="POST">
                        <input type="hidden" name="report_type" id="selectedReportType">

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="date_from">Start Date</label>
                                <input type="date" id="date_from" name="date_from" class="form-control"
                                    value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="date_to">End Date</label>
                                <input type="date" id="date_to" name="date_to" class="form-control"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="report_name">Report Name</label>
                                <input type="text" id="report_name" name="report_name" class="form-control"
                                    placeholder="Enter custom report name (optional)">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Output Format</label>
                            <div class="format-options">
                                <label class="format-option selected">
                                    <input type="radio" name="format" value="html" checked>
                                    <i class="fas fa-globe"></i>
                                    <div>Web View</div>
                                </label>
                                <label class="format-option">
                                    <input type="radio" name="format" value="pdf">
                                    <i class="fas fa-file-pdf"></i>
                                    <div>PDF</div>
                                </label>
                                <label class="format-option">
                                    <input type="radio" name="format" value="excel">
                                    <i class="fas fa-file-excel"></i>
                                    <div>Excel</div>
                                </label>
                                <label class="format-option">
                                    <input type="radio" name="format" value="csv">
                                    <i class="fas fa-file-csv"></i>
                                    <div>CSV</div>
                                </label>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <button type="button" onclick="hideReportForm()" class="btn btn-secondary">Cancel</button>
                            <button type="submit" name="generate_report" class="btn btn-primary">
                                <i class="fas fa-chart-bar"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Saved Reports -->
                <div class="saved-reports">
                    <div class="saved-reports-header">
                        <h3>Recent Reports</h3>
                    </div>

                    <?php if (empty($saved_reports)): ?>
                        <div style="padding: 40px; text-align: center; color: #666;">
                            <i class="fas fa-chart-bar" style="font-size: 3em; margin-bottom: 15px;"></i>
                            <h4>No Reports Generated Yet</h4>
                            <p>Generate your first report using the templates above</p>
                        </div>
                    <?php else: ?>
                        <table class="reports-table">
                            <thead>
                                <tr>
                                    <th>Report Name</th>
                                    <th>Type</th>
                                    <th>Date Range</th>
                                    <th>Generated</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saved_reports as $report): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($report['name']); ?></td>
                                        <td><?php echo ucwords(str_replace('_', ' ', $report['report_type'])); ?></td>
                                        <td>
                                            <?php
                                            $params = json_decode($report['parameters'], true);
                                            echo date('M j', strtotime($params['date_from'])) . ' - ' . date('M j, Y', strtotime($params['date_to']));
                                            ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></td>
                                        <td>
                                            <span class="report-status <?php echo $report['status']; ?>">
                                                <?php echo ucfirst($report['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view-report.php?id=<?php echo $report['id']; ?>"
                                                    class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="download-report.php?id=<?php echo $report['id']; ?>"
                                                    class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectTemplate(templateKey) {
            // Remove previous selection
            document.querySelectorAll('.report-template').forEach(template => {
                template.classList.remove('selected');
            });

            // Select current template
            event.currentTarget.classList.add('selected');

            // Set form values
            document.getElementById('selectedReportType').value = templateKey;

            // Show form
            document.getElementById('reportForm').classList.add('show');

            // Scroll to form
            document.getElementById('reportForm').scrollIntoView({ behavior: 'smooth' });
        }

        function hideReportForm() {
            document.getElementById('reportForm').classList.remove('show');
            document.querySelectorAll('.report-template').forEach(template => {
                template.classList.remove('selected');
            });
        }

        // Format option selection
        document.querySelectorAll('.format-option').forEach(option => {
            option.addEventListener('click', function () {
                document.querySelectorAll('.format-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                this.querySelector('input').checked = true;
            });
        });

        // Auto-set report name based on template
        document.getElementById('selectedReportType').addEventListener('change', function () {
            const templates = <?php echo json_encode($report_templates); ?>;
            const reportName = templates[this.value]?.name + ' - ' + new Date().toLocaleDateString();
            document.getElementById('report_name').value = reportName;
        });
    </script>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>