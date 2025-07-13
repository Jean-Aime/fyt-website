<?php
require_once '../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();

// Ensure user is an MCA agent
if ($_SESSION['role_name'] !== 'mca_agent') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get MCA agent ID
$stmt = $db->prepare("SELECT id FROM mca_agents WHERE user_id = ?");
$stmt->execute([$user_id]);
$mca_id = $stmt->fetchColumn();

// Handle advisor status updates
if ($_POST && isset($_POST['action'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $action = $_POST['action'];
            $advisor_id = (int) $_POST['advisor_id'];

            if ($action === 'activate') {
                $stmt = $db->prepare("UPDATE certified_advisors SET status = 'active' WHERE id = ? AND mca_id = ?");
                $stmt->execute([$advisor_id, $mca_id]);
                $success = 'Advisor activated successfully!';
            } elseif ($action === 'deactivate') {
                $stmt = $db->prepare("UPDATE certified_advisors SET status = 'inactive' WHERE id = ? AND mca_id = ?");
                $stmt->execute([$advisor_id, $mca_id]);
                $success = 'Advisor deactivated successfully!';
            } elseif ($action === 'suspend') {
                $stmt = $db->prepare("UPDATE certified_advisors SET status = 'suspended' WHERE id = ? AND mca_id = ?");
                $stmt->execute([$advisor_id, $mca_id]);
                $success = 'Advisor suspended successfully!';
            }
        } catch (Exception $e) {
            $error = 'Error updating advisor status: ' . $e->getMessage();
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// Build query
$where_conditions = ["ca.mca_id = ?"];
$params = [$mca_id];

if ($search) {
    $where_conditions[] = "(ca.first_name LIKE ? OR ca.last_name LIKE ? OR ca.email LIKE ? OR ca.advisor_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "ca.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get advisors with performance data
$stmt = $db->prepare("
    SELECT ca.*, 
           COUNT(b.id) as total_bookings,
           COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) as confirmed_bookings,
           COALESCE(SUM(b.total_amount), 0) as total_sales,
           COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN b.total_amount END), 0) as confirmed_sales,
           COALESCE(SUM(ac.commission_amount), 0) as total_commissions,
           MAX(b.created_at) as last_booking_date
    FROM certified_advisors ca
    LEFT JOIN bookings b ON ca.id = b.advisor_id
    LEFT JOIN advisor_commissions ac ON ca.id = ac.advisor_id
    WHERE $where_clause
    GROUP BY ca.id
    ORDER BY $sort $order
");
$stmt->execute($params);
$advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_advisors,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_advisors,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_advisors,
        COUNT(CASE WHEN status = 'suspended' THEN 1 END) as suspended_advisors,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_advisors_30d
    FROM certified_advisors 
    WHERE mca_id = ?
");
$stmt->execute([$mca_id]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Manage Advisors - MCA Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/mca-portal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .advisors-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .advisors-title h1 {
            font-size: 2em;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .advisors-title p {
            color: #7f8c8d;
            margin: 0;
        }

        .advisors-actions {
            display: flex;
            gap: 15px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .summary-card h3 {
            font-size: 2em;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .summary-card p {
            color: #7f8c8d;
            margin: 0;
        }

        .summary-card.active h3 { color: #27ae60; }
        .summary-card.inactive h3 { color: #f39c12; }
        .summary-card.suspended h3 { color: #e74c3c; }
        .summary-card.new h3 { color: #3498db; }

        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filters-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 0.9em;
            color: #555;
            font-weight: 500;
        }

        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9em;
        }

        .advisors-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            margin: 0;
            color: #2c3e50;
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .advisors-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .advisors-table th,
        .advisors-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .advisors-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            position: sticky;
            top: 0;
        }

        .advisors-table tbody tr:hover {
            background: #f8f9fa;
        }

        .advisor-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .advisor-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D4AF37, #B8941F);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9em;
        }

        .advisor-details h4 {
            margin: 0 0 2px 0;
            color: #2c3e50;
            font-size: 0.95em;
        }

        .advisor-details p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.85em;
        }

        .performance-stats {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .performance-stats span {
            font-size: 0.85em;
            color: #555;
        }

        .performance-stats .highlight {
            font-weight: 600;
            color: #27ae60;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.inactive {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.suspended {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view { background: #e3f2fd; color: #1976d2; }
        .btn-edit { background: #fff3e0; color: #f57c00; }
        .btn-activate { background: #e8f5e8; color: #2e7d32; }
        .btn-suspend { background: #ffebee; color: #c62828; }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #ddd;
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        @media (max-width: 768px) {
            .advisors-header {
                flex-direction: column;
                align-items: stretch;
            }

            .filters-form {
                flex-direction: column;
            }

            .advisors-table {
                overflow-x: auto;
            }

            .advisors-table table {
                min-width: 800px;
            }
        }
    </style>
</head>

<body>
    <div class="mca-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <div class="advisors-header">
                    <div class="advisors-title">
                        <h1>Manage Advisors</h1>
                        <p>Recruit, train, and manage your certified advisor network</p>
                    </div>
                    <div class="advisors-actions">
                        <a href="recruit.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Recruit Advisor
                        </a>
                        <a href="training.php" class="btn btn-outline">
                            <i class="fas fa-graduation-cap"></i> Training Center
                        </a>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card total">
                        <h3><?php echo $summary['total_advisors']; ?></h3>
                        <p>Total Advisors</p>
                    </div>
                    <div class="summary-card active">
                        <h3><?php echo $summary['active_advisors']; ?></h3>
                        <p>Active Advisors</p>
                    </div>
                    <div class="summary-card inactive">
                        <h3><?php echo $summary['inactive_advisors']; ?></h3>
                        <p>Inactive Advisors</p>
                    </div>
                    <div class="summary-card suspended">
                        <h3><?php echo $summary['suspended_advisors']; ?></h3>
                        <p>Suspended</p>
                    </div>
                    <div class="summary-card new">
                        <h3><?php echo $summary['new_advisors_30d']; ?></h3>
                        <p>New This Month</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filter-group">
                            <label for="search">Search Advisors</label>
                            <input type="text" id="search" name="search" placeholder="Name, email, or code..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="sort">Sort By</label>
                            <select id="sort" name="sort">
                                <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date Joined</option>
                                <option value="first_name" <?php echo $sort === 'first_name' ? 'selected' : ''; ?>>Name</option>
                                <option value="confirmed_sales" <?php echo $sort === 'confirmed_sales' ? 'selected' : ''; ?>>Sales Performance</option>
                                <option value="total_bookings" <?php echo $sort === 'total_bookings' ? 'selected' : ''; ?>>Total Bookings</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Advisors Table -->
                <div class="advisors-table">
                    <div class="table-header">
                        <h2>Certified Advisors (<?php echo count($advisors); ?>)</h2>
                        <div class="table-actions">
                            <button onclick="exportAdvisors()" class="btn btn-outline btn-sm">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>

                    <?php if (empty($advisors)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-friends"></i>
                            <h3>No Advisors Found</h3>
                            <p>Start building your advisor network by recruiting certified advisors.</p>
                            <a href="recruit.php" class="btn btn-primary">Recruit First Advisor</a>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Advisor</th>
                                    <th>Contact</th>
                                    <th>Performance</th>
                                    <th>Commissions</th>
                                    <th>Last Activity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($advisors as $advisor): ?>
                                    <tr>
                                        <td>
                                            <div class="advisor-info">
                                                <div class="advisor-avatar">
                                                    <?php echo strtoupper(substr($advisor['first_name'], 0, 1) . substr($advisor['last_name'], 0, 1)); ?>
                                                </div>
                                                <div class="advisor-details">
                                                    <h4><?php echo htmlspecialchars($advisor['first_name'] . ' ' . $advisor['last_name']); ?></h4>
                                                    <p><?php echo htmlspecialchars($advisor['advisor_code']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contact-info">
                                                <div><?php echo htmlspecialchars($advisor['email']); ?></div>
                                                <div><?php echo htmlspecialchars($advisor['phone']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="performance-stats">
                                                <span><strong><?php echo $advisor['confirmed_bookings']; ?></strong> confirmed bookings</span>
                                                <span class="highlight">$<?php echo number_format($advisor['confirmed_sales'], 0); ?> in sales</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="commission-info">
                                                <span class="highlight">$<?php echo number_format($advisor['total_commissions'], 2); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($advisor['last_booking_date']): ?>
                                                <?php echo timeAgo($advisor['last_booking_date']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No bookings yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $advisor['status']; ?>">
                                                <?php echo ucfirst($advisor['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="advisor-details.php?id=<?php echo $advisor['id']; ?>" 
                                                   class="btn-icon btn-view" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($advisor['status'] === 'inactive'): ?>
                                                    <button onclick="updateAdvisorStatus(<?php echo $advisor['id']; ?>, 'activate')" 
                                                            class="btn-icon btn-activate" title="Activate">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php elseif ($advisor['status'] === 'active'): ?>
                                                    <button onclick="updateAdvisorStatus(<?php echo $advisor['id']; ?>, 'suspend')" 
                                                            class="btn-icon btn-suspend" title="Suspend">
                                                        <i class="fas fa-pause"></i>
                                                    </button>
                                                <?php elseif ($advisor['status'] === 'suspended'): ?>
                                                    <button onclick="updateAdvisorStatus(<?php echo $advisor['id']; ?>, 'activate')" 
                                                            class="btn-icon btn-activate" title="Reactivate">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                <?php endif; ?>
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

    <!-- Status Update Form (Hidden) -->
    <form id="statusUpdateForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" id="statusAction">
        <input type="hidden" name="advisor_id" id="statusAdvisorId">
    </form>

    <script src="../assets/js/mca-portal.js"></script>
    <script>
        function updateAdvisorStatus(advisorId, action) {
            const actionText = action === 'activate' ? 'activate' : 
                              action === 'suspend' ? 'suspend' : 'deactivate';
            
            if (confirm(`Are you sure you want to ${actionText} this advisor?`)) {
                document.getElementById('statusAction').value = action;
                document.getElementById('statusAdvisorId').value = advisorId;
                document.getElementById('statusUpdateForm').submit();
            }
        }

        function exportAdvisors() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'export-advisors.php?' + params.toString();
        }
    </script>
</body>
</html>
