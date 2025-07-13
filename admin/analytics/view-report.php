<?php
// Error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/secure_auth.php';

try {
    $auth = new SecureAuth($db);
    requireLogin();
    requirePermission('analytics.view');

    $page_title = 'View Report';

    // Validate report ID
    $report_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$report_id || $report_id <= 0) {
        header("Location: reports.php?error=Invalid report ID");
        exit;
    }

    // Fetch report data with user access check
    $report = $db->prepare("
        SELECT r.*, u.first_name, u.last_name
        FROM reports r
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.id = ? AND (r.created_by = ? OR ? = 1)
    ");
    $report->execute([$report_id, $_SESSION['user_id'], $_SESSION['is_admin'] ?? 0]);
    $report_data = $report->fetch(PDO::FETCH_ASSOC);

    if (!$report_data) {
        header("Location: reports.php?error=Report not found or access denied");
        exit;
    }

    // Decode report parameters and data with error handling
    $parameters = json_decode($report_data['parameters'] ?? '{}', true) ?? [];
    $report_content = json_decode($report_data['content'] ?? '{}', true) ?? [];

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error in report {$report_id}: " . json_last_error_msg());
        header("Location: reports.php?error=Error processing report data");
        exit;
    }

    // Format date range with validation
    $date_from = isset($parameters['date_from']) ? strtotime($parameters['date_from']) : time();
    $date_to = isset($parameters['date_to']) ? strtotime($parameters['date_to']) : time();
    $date_range = date('M j, Y', $date_from) . ' - ' . date('M j, Y', $date_to);

    // Report type mapping
    $report_types = [
        'revenue_summary' => 'Revenue Summary',
        'booking_analysis' => 'Booking Analysis',
        'customer_insights' => 'Customer Insights',
        'tour_performance' => 'Tour Performance',
        'geographic_analysis' => 'Geographic Analysis',
        'payment_analysis' => 'Payment Analysis'
    ];

    // Format status with color
    function formatStatus($status)
    {
        $status_classes = [
            'completed' => 'text-success',
            'processing' => 'text-warning',
            'failed' => 'text-danger'
        ];
        return '<span class="' . ($status_classes[$status] ?? '') . '">' . ucfirst($status) . '</span>';
    }

} catch (Exception $e) {
    error_log("Error in view-report.php: " . $e->getMessage());
    header("Location: reports.php?error=An unexpected error occurred");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Forever Young Tours Admin</title>
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
        }

        .report-meta {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .meta-item {
            padding: 10px;
        }

        .meta-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .meta-value {
            font-size: 1.1em;
            font-weight: 500;
            color: #333;
        }

        .report-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .kpi-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--admin-primary);
            margin-bottom: 5px;
        }

        .kpi-label {
            color: #666;
            font-size: 0.9em;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 40px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .insight-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .insight-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .insight-content {
            color: #666;
            line-height: 1.6;
        }

        .text-success {
            color: #28a745;
        }

        .text-warning {
            color: #ffc107;
        }

        .text-danger {
            color: #dc3545;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .tour-thumb {
            width: 50px;
            height: 35px;
            border-radius: 4px;
            object-fit: cover;
            margin-right: 10px;
        }

        .tour-info {
            display: flex;
            align-items: center;
        }

        @media (max-width: 768px) {
            .report-meta {
                grid-template-columns: 1fr;
            }

            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .insights-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <div class="content">
                <!-- Report Header -->
                <div class="report-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h1><?php echo htmlspecialchars($report_data['name'] ?? 'Unnamed Report'); ?></h1>
                            <p>Generated report with detailed analytics and insights</p>
                        </div>
                        <div class="action-buttons">
                            <a href="download-report.php?id=<?php echo $report_id; ?>" class="btn btn-light">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <a href="reports.php" class="btn btn-light">
                                <i class="fas fa-arrow-left"></i> Back to Reports
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Report Metadata -->
                <div class="report-meta">
                    <div class="meta-item">
                        <div class="meta-label">Report Type</div>
                        <div class="meta-value">
                            <?php echo htmlspecialchars($report_types[$report_data['report_type']]) ?? ucwords(str_replace('_', ' ', $report_data['report_type'] ?? 'Unknown')); ?>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Date Range</div>
                        <div class="meta-value"><?php echo htmlspecialchars($date_range); ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Generated By</div>
                        <div class="meta-value">
                            <?php echo htmlspecialchars(($report_data['first_name'] ?? '') . ' ' . ($report_data['last_name'] ?? '')); ?>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Generated On</div>
                        <div class="meta-value">
                            <?php echo date('M j, Y g:i A', strtotime($report_data['created_at'] ?? 'now')); ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Status</div>
                        <div class="meta-value"><?php echo formatStatus($report_data['status'] ?? 'unknown'); ?></div>
                    </div>
                </div>

                <!-- Report Content -->
                <div class="report-content">
                    <?php if (($report_data['status'] ?? '') !== 'completed'): ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-spinner fa-spin"
                                style="font-size: 3em; margin-bottom: 20px; color: #ffc107;"></i>
                            <h3>Report Processing</h3>
                            <p>This report is still being generated. Please check back later.</p>
                        </div>
                    <?php else: ?>
                        <!-- Summary Section -->
                        <div class="report-section">
                            <h2 class="section-title">Executive Summary</h2>
                            <div class="insights-grid">
                                <div class="insight-card">
                                    <h3 class="insight-title">Key Findings</h3>
                                    <div class="insight-content">
                                        <?php echo nl2br(htmlspecialchars($report_content['summary']['key_findings'] ?? 'No key findings available.')); ?>
                                    </div>
                                </div>
                                <div class="insight-card">
                                    <h3 class="insight-title">Recommendations</h3>
                                    <div class="insight-content">
                                        <?php echo nl2br(htmlspecialchars($report_content['summary']['recommendations'] ?? 'No recommendations available.')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KPIs Section -->
                        <div class="report-section">
                            <h2 class="section-title">Key Performance Indicators</h2>
                            <div class="kpi-grid">
                                <?php foreach (($report_content['kpis'] ?? []) as $kpi): ?>
                                    <div class="kpi-card">
                                        <div class="kpi-value"><?php echo htmlspecialchars($kpi['value'] ?? 'N/A'); ?></div>
                                        <div class="kpi-label">
                                            <?php echo htmlspecialchars($kpi['label'] ?? 'Unknown Metric'); ?></div>
                                        <?php if (isset($kpi['growth'])): ?>
                                            <div style="margin-top: 5px; font-size: 0.8em;">
                                                <span
                                                    class="<?php echo ($kpi['growth'] ?? 0) > 0 ? 'text-success' : (($kpi['growth'] ?? 0) < 0 ? 'text-danger' : ''); ?>">
                                                    <i
                                                        class="fas fa-arrow-<?php echo ($kpi['growth'] ?? 0) >= 0 ? 'up' : 'down'; ?>"></i>
                                                    <?php echo abs($kpi['growth'] ?? 0); ?>%
                                                </span>
                                                <span>vs previous period</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Charts Section -->
                        <?php if (!empty($report_content['charts'])): ?>
                            <div class="report-section">
                                <h2 class="section-title">Data Visualizations</h2>
                                <?php foreach ($report_content['charts'] as $chart): ?>
                                    <div style="margin-bottom: 40px;">
                                        <h3 style="margin-bottom: 20px;">
                                            <?php echo htmlspecialchars($chart['title'] ?? 'Untitled Chart'); ?></h3>
                                        <div class="chart-container">
                                            <canvas id="chart-<?php echo htmlspecialchars($chart['id'] ?? uniqid()); ?>"></canvas>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Tables Section -->
                        <?php if (!empty($report_content['tables'])): ?>
                            <div class="report-section">
                                <h2 class="section-title">Detailed Data</h2>
                                <?php foreach ($report_content['tables'] as $table): ?>
                                    <div style="margin-bottom: 40px;">
                                        <h3 style="margin-bottom: 20px;">
                                            <?php echo htmlspecialchars($table['title'] ?? 'Untitled Table'); ?></h3>
                                        <div style="overflow-x: auto;">
                                            <table class="data-table">
                                                <thead>
                                                    <tr>
                                                        <?php foreach ($table['headers'] ?? [] as $header): ?>
                                                            <th><?php echo htmlspecialchars($header); ?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($table['rows'] ?? [] as $row): ?>
                                                        <tr>
                                                            <?php foreach ($row as $cell): ?>
                                                                <td>
                                                                    <?php if (is_array($cell) && isset($cell['type']) && $cell['type'] === 'tour'): ?>
                                                                        <div class="tour-info">
                                                                            <?php if (!empty($cell['image'])): ?>
                                                                                <img src="../../<?php echo htmlspecialchars($cell['image']); ?>"
                                                                                    class="tour-thumb" alt="Tour image">
                                                                            <?php endif; ?>
                                                                            <span><?php echo htmlspecialchars($cell['value'] ?? ''); ?></span>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <?php echo is_array($cell) ? htmlspecialchars($cell['value'] ?? '') : htmlspecialchars($cell ?? ''); ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Insights Section -->
                        <?php if (!empty($report_content['insights'])): ?>
                            <div class="report-section">
                                <h2 class="section-title">Analyst Insights</h2>
                                <div class="insights-grid">
                                    <?php foreach ($report_content['insights'] as $insight): ?>
                                        <div class="insight-card">
                                            <h3 class="insight-title">
                                                <?php echo htmlspecialchars($insight['title'] ?? 'Untitled Insight'); ?></h3>
                                            <div class="insight-content">
                                                <?php echo nl2br(htmlspecialchars($insight['content'] ?? 'No content available.')); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (($report_data['status'] ?? '') === 'completed' && !empty($report_content['charts'])): ?>
        <script>
            // Initialize charts
            document.addEventListener('DOMContentLoaded', function () {
                <?php foreach ($report_content['charts'] as $chart): ?>
                    try {
                        const ctx_<?php echo htmlspecialchars($chart['id'] ?? uniqid()); ?> = document.getElementById('chart-<?php echo htmlspecialchars($chart['id'] ?? uniqid()); ?>');
                        if (ctx_<?php echo htmlspecialchars($chart['id'] ?? uniqid()); ?>) {
                            new Chart(ctx_<?php echo htmlspecialchars($chart['id'] ?? uniqid()); ?>, {
                                type: '<?php echo htmlspecialchars($chart['type'] ?? 'bar'); ?>',
                                data: {
                                    labels: <?php echo json_encode($chart['labels'] ?? []); ?>,
                                    datasets: <?php echo json_encode($chart['datasets'] ?? []); ?>
                                },
                                options: <?php echo json_encode($chart['options'] ?? (object) []); ?>
                            });
                        }
                    } catch (e) {
                        console.error('Error initializing chart:', e);
                    }
                <?php endforeach; ?>
            });
        </script>
    <?php endif; ?>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>