<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('mca.view');

$page_title = 'MCA Dashboard';

// Get MCA statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_agents,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_agents,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_agents,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_agents_30d,
        AVG(commission_rate) as avg_commission_rate,
        SUM(total_sales) as total_sales,
        SUM(total_commission) as total_commissions
    FROM mca_agents
")->fetch();

// Get top performing agents
$top_agents = $db->query("
    SELECT ma.*, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM bookings WHERE agent_id = ma.user_id) as total_bookings,
           (SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE agent_id = ma.user_id AND status = 'confirmed') as total_revenue
    FROM mca_agents ma
    JOIN users u ON ma.user_id = u.id
    WHERE ma.status = 'active'
    ORDER BY ma.total_sales DESC
    LIMIT 10
")->fetchAll();

// Get recent commissions
$recent_commissions = $db->query("
    SELECT mc.*, ma.agent_code, u.first_name, u.last_name, b.booking_reference
    FROM mca_commissions mc
    JOIN mca_agents ma ON mc.agent_id = ma.id
    JOIN users u ON ma.user_id = u.id
    JOIN bookings b ON mc.booking_id = b.id
    ORDER BY mc.created_at DESC
    LIMIT 10
")->fetchAll();

// Get commission trends (last 12 months)
$commission_trends = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as commission_count,
        SUM(commission_amount) as total_amount
    FROM mca_commissions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
")->fetchAll();

// Get training completion stats
$training_stats = $db->query("
    SELECT 
        COUNT(DISTINCT mtp.agent_id) as agents_in_training,
        COUNT(CASE WHEN mtp.status = 'completed' THEN 1 END) as completed_modules,
        COUNT(CASE WHEN mtp.status = 'in_progress' THEN 1 END) as in_progress_modules,
        AVG(mtp.score) as avg_score
    FROM mca_training_progress mtp
    JOIN mca_agents ma ON mtp.agent_id = ma.id
    WHERE ma.status = 'active'
")->fetch();
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
        .mca-dashboard {
            padding: 20px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .dashboard-title {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .dashboard-subtitle {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 5px solid var(--primary-color);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.agents { border-left-color: #3498db; }
        .stat-card.commissions { border-left-color: #2ecc71; }
        .stat-card.sales { border-left-color: #f39c12; }
        .stat-card.training { border-left-color: #9b59b6; }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-change {
            font-size: 0.8em;
            margin-top: 5px;
        }
        
        .stat-change.positive {
            color: #2ecc71;
        }
        
        .stat-change.negative {
            color: #e74c3c;
        }
        
        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .main-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .side-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .panel-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .panel-title {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }
        
        .agent-list {
            list-style: none;
            padding: 0;
        }
        
        .agent-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .agent-item:last-child {
            border-bottom: none;
        }
        
        .agent-info {
            display: flex;
            flex-direction: column;
        }
        
        .agent-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .agent-code {
            font-size: 0.8em;
            color: #7f8c8d;
        }
        
        .agent-performance {
            text-align: right;
        }
        
        .performance-value {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .performance-label {
            font-size: 0.8em;
            color: #7f8c8d;
        }
        
        .commission-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .commission-item:last-child {
            border-bottom: none;
        }
        
        .commission-info {
            flex: 1;
        }
        
        .commission-amount {
            font-weight: 600;
            color: #2ecc71;
        }
        
        .commission-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .commission-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .commission-status.approved {
            background: #d4edda;
            color: #155724;
        }
        
        .commission-status.paid {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }
        
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .action-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .action-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .action-description {
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
                <div class="mca-dashboard">
                    <!-- Dashboard Header -->
                    <div class="dashboard-header">
                        <h1 class="dashboard-title">MCA Dashboard</h1>
                        <p class="dashboard-subtitle">Multi-Channel Agent Management & Analytics</p>
                    </div>
                    
                    <!-- Statistics Grid -->
                    <div class="stats-grid">
                        <div class="stat-card agents">
                            <div class="stat-value"><?php echo number_format($stats['total_agents']); ?></div>
                            <div class="stat-label">Total Agents</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> <?php echo $stats['new_agents_30d']; ?> new this month
                            </div>
                        </div>
                        
                        <div class="stat-card agents">
                            <div class="stat-value"><?php echo number_format($stats['active_agents']); ?></div>
                            <div class="stat-label">Active Agents</div>
                            <div class="stat-change">
                                <?php echo $stats['pending_agents']; ?> pending approval
                            </div>
                        </div>
                        
                        <div class="stat-card sales">
                            <div class="stat-value"><?php echo formatCurrency($stats['total_sales']); ?></div>
                            <div class="stat-label">Total Sales</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> 12% vs last month
                            </div>
                        </div>
                        
                        <div class="stat-card commissions">
                            <div class="stat-value"><?php echo formatCurrency($stats['total_commissions']); ?></div>
                            <div class="stat-label">Total Commissions</div>
                            <div class="stat-change">
                                Avg: <?php echo number_format($stats['avg_commission_rate'], 1); ?>%
                            </div>
                        </div>
                        
                        <div class="stat-card training">
                            <div class="stat-value"><?php echo number_format($training_stats['agents_in_training']); ?></div>
                            <div class="stat-label">Agents in Training</div>
                            <div class="stat-change">
                                Avg Score: <?php echo number_format($training_stats['avg_score'], 1); ?>%
                            </div>
                        </div>
                    </div>
                    
                    <!-- Main Dashboard Content -->
                    <div class="dashboard-content">
                        <!-- Commission Trends Chart -->
                        <div class="main-panel">
                            <h2 class="panel-title">Commission Trends (Last 12 Months)</h2>
                            <div class="chart-container">
                                <canvas id="commissionChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Side Panels -->
                        <div class="side-panel">
                            <!-- Top Performing Agents -->
                            <div class="panel-card">
                                <h3 class="panel-title">Top Performing Agents</h3>
                                <ul class="agent-list">
                                    <?php foreach ($top_agents as $agent): ?>
                                    <li class="agent-item">
                                        <div class="agent-info">
                                            <div class="agent-name"><?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?></div>
                                            <div class="agent-code"><?php echo htmlspecialchars($agent['agent_code']); ?></div>
                                        </div>
                                        <div class="agent-performance">
                                            <div class="performance-value"><?php echo formatCurrency($agent['total_revenue']); ?></div>
                                            <div class="performance-label"><?php echo $agent['total_bookings']; ?> bookings</div>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <a href="agents.php" class="btn btn-outline btn-sm" style="margin-top: 15px;">
                                    View All Agents
                                </a>
                            </div>
                            
                            <!-- Recent Commissions -->
                            <div class="panel-card">
                                <h3 class="panel-title">Recent Commissions</h3>
                                <div class="commission-list">
                                    <?php foreach ($recent_commissions as $commission): ?>
                                    <div class="commission-item">
                                        <div class="commission-info">
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($commission['first_name'] . ' ' . $commission['last_name']); ?></div>
                                            <div style="font-size: 0.8em; color: #7f8c8d;"><?php echo htmlspecialchars($commission['booking_reference']); ?></div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div class="commission-amount"><?php echo formatCurrency($commission['commission_amount']); ?></div>
                                            <span class="commission-status <?php echo $commission['status']; ?>">
                                                <?php echo ucfirst($commission['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <a href="commissions.php" class="btn btn-outline btn-sm" style="margin-top: 15px;">
                                    View All Commissions
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <div class="action-card" onclick="window.location.href='agents.php'">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="action-title">Manage Agents</div>
                            <div class="action-description">View and manage MCA agents</div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='commissions.php'">
                            <div class="action-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="action-title">Process Commissions</div>
                            <div class="action-description">Review and pay commissions</div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='training.php'">
                            <div class="action-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="action-title">Training Center</div>
                            <div class="action-description">Manage training modules</div>
                        </div>
                        
                        <div class="action-card" onclick="window.location.href='reports.php'">
                            <div class="action-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="action-title">Analytics & Reports</div>
                            <div class="action-description">View detailed reports</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Commission Trends Chart
        const ctx = document.getElementById('commissionChart').getContext('2d');
        const commissionData = <?php echo json_encode($commission_trends); ?>;
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: commissionData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Commission Amount',
                    data: commissionData.map(item => item.total_amount),
                    borderColor: '#d4a574',
                    backgroundColor: 'rgba(212, 165, 116, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Commission Count',
                    data: commissionData.map(item => item.commission_count),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: false,
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
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Commission Amount ($)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Commission Count'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
