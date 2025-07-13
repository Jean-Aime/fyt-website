<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('advisors.view');

$page_title = 'Advisor Management';

// Handle bulk actions
if ($_POST && isset($_POST['bulk_action']) && isset($_POST['selected_advisors'])) {
    $action = $_POST['bulk_action'];
    $advisor_ids = $_POST['selected_advisors'];
    
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            switch ($action) {
                case 'activate':
                    if (hasPermission('advisors.edit')) {
                        $placeholders = str_repeat('?,', count($advisor_ids) - 1) . '?';
                        $stmt = $db->prepare("UPDATE certified_advisors SET status = 'active' WHERE id IN ($placeholders)");
                        $stmt->execute($advisor_ids);
                        $success = count($advisor_ids) . ' advisors activated successfully';
                    }
                    break;
                    
                case 'deactivate':
                    if (hasPermission('advisors.edit')) {
                        $placeholders = str_repeat('?,', count($advisor_ids) - 1) . '?';
                        $stmt = $db->prepare("UPDATE certified_advisors SET status = 'inactive' WHERE id IN ($placeholders)");
                        $stmt->execute($advisor_ids);
                        $success = count($advisor_ids) . ' advisors deactivated successfully';
                    }
                    break;
                    
                case 'delete':
                    if (hasPermission('advisors.delete')) {
                        $placeholders = str_repeat('?,', count($advisor_ids) - 1) . '?';
                        $stmt = $db->prepare("DELETE FROM certified_advisors WHERE id IN ($placeholders)");
                        $stmt->execute($advisor_ids);
                        $success = count($advisor_ids) . ' advisors deleted successfully';
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error performing bulk action: ' . $e->getMessage();
        }
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$level_filter = $_GET['level'] ?? '';
$mca_filter = $_GET['mca'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR ca.advisor_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "ca.status = ?";
    $params[] = $status_filter;
}

if ($level_filter) {
    $where_conditions[] = "ca.certification_level = ?";
    $params[] = $level_filter;
}

if ($mca_filter) {
    $where_conditions[] = "ca.mca_id = ?";
    $params[] = $mca_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM certified_advisors ca 
    JOIN users u ON ca.user_id = u.id
    LEFT JOIN mcas m ON ca.mca_id = m.id
    $where_clause";
$total_advisors = $db->prepare($count_sql);
$total_advisors->execute($params);
$total_count = $total_advisors->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get advisors
$sql = "
    SELECT ca.*, u.first_name, u.last_name, u.email, u.username,
           m.mca_code, CONCAT(mu.first_name, ' ', mu.last_name) as mca_name,
           COALESCE(SUM(ct.amount), 0) as total_commission_earned
    FROM certified_advisors ca
    JOIN users u ON ca.user_id = u.id
    LEFT JOIN mcas m ON ca.mca_id = m.id
    LEFT JOIN users mu ON m.user_id = mu.id
    LEFT JOIN commission_transactions ct ON ca.id = ct.advisor_id AND ct.status = 'paid'
    $where_clause
    GROUP BY ca.id
    ORDER BY $sort $order
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get MCAs for filter
$mcas = $db->query("
    SELECT m.id, m.mca_code, CONCAT(u.first_name, ' ', u.last_name) as name
    FROM mcas m
    JOIN users u ON m.user_id = u.id
    WHERE m.status = 'active'
    ORDER BY u.first_name, u.last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_advisors,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_advisors,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_advisors,
        COUNT(CASE WHEN status = 'training' THEN 1 END) as training_advisors,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_advisors,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_advisors_30d
    FROM certified_advisors
")->fetch(PDO::FETCH_ASSOC);
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
        .advisor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .advisor-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            border: 1px solid #ecf0f1;
        }
        
        .advisor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .advisor-header {
            background: linear-gradient(135deg, #228B22, #1e7e1e);
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .advisor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2em;
            font-weight: bold;
            color: white;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .advisor-name {
            color: white;
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .advisor-code {
            color: rgba(255,255,255,0.9);
            font-size: 0.9em;
            font-family: monospace;
        }
        
        .advisor-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .advisor-status.active {
            background: #228B22;
            color: white;
        }
        
        .advisor-status.inactive {
            background: #dc3545;
            color: white;
        }
        
        .advisor-status.training {
            background: #17a2b8;
            color: white;
        }
        
        .advisor-status.pending {
            background: #f39c12;
            color: white;
        }
        
        .advisor-status.suspended {
            background: #6c757d;
            color: white;
        }
        
        .advisor-content {
            padding: 25px;
        }
        
        .advisor-level {
            background: linear-gradient(135deg, #000000, #333333);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
            text-align: center;
            margin-bottom: 20px;
            text-transform: capitalize;
        }
        
        .advisor-level.bronze {
            background: linear-gradient(135deg, #cd7f32, #b8722c);
        }
        
        .advisor-level.silver {
            background: linear-gradient(135deg, #c0c0c0, #a8a8a8);
        }
        
        .advisor-level.gold {
            background: linear-gradient(135deg, #ffd700, #e6c200);
            color: #333;
        }
        
        .advisor-level.platinum {
            background: linear-gradient(135deg, #e5e4e2, #d3d3d3);
            color: #333;
        }
        
        .advisor-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .advisor-stat {
            text-align: center;
        }
        
        .advisor-stat-label {
            font-size: 0.8em;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .advisor-stat-value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        .advisor-mca {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        
        .advisor-mca-label {
            color: #7f8c8d;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .advisor-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }
        
        .advisor-actions .btn {
            flex: 1;
            padding: 10px;
            font-size: 0.9em;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .advisor-checkbox {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 20px;
            height: 20px;
            accent-color: #228B22;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="content">
                <div class="content-header">
                    <div class="content-title">
                        <h2>Advisor Management</h2>
                        <p>Manage Certified Travel Advisors</p>
                    </div>
                    <div class="content-actions">
                        <?php if (hasPermission('advisors.create')): ?>
                        <button onclick="openAddAdvisorModal()" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Advisor
                        </button>
                        <?php endif; ?>
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
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_advisors']); ?></h3>
                            <p>Total Advisors</p>
                            <div class="stat-change positive">
                                +<?php echo $stats['new_advisors_30d']; ?> this month
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['active_advisors']); ?></h3>
                            <p>Active Advisors</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['training_advisors']); ?></h3>
                            <p>In Training</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['pending_advisors']); ?></h3>
                            <p>Pending Approval</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search Advisors</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name, email, advisor code..." class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="training" <?php echo $status_filter === 'training' ? 'selected' : ''; ?>>Training</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Level</label>
                                <select name="level" class="form-control">
                                    <option value="">All Levels</option>
                                    <option value="bronze" <?php echo $level_filter === 'bronze' ? 'selected' : ''; ?>>Bronze</option>
                                    <option value="silver" <?php echo $level_filter === 'silver' ? 'selected' : ''; ?>>Silver</option>
                                    <option value="gold" <?php echo $level_filter === 'gold' ? 'selected' : ''; ?>>Gold</option>
                                    <option value="platinum" <?php echo $level_filter === 'platinum' ? 'selected' : ''; ?>>Platinum</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>MCA</label>
                                <select name="mca" class="form-control">
                                    <option value="">All MCAs</option>
                                    <?php foreach ($mcas as $mca): ?>
                                        <option value="<?php echo $mca['id']; ?>" <?php echo $mca_filter == $mca['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mca['mca_code'] . ' - ' . $mca['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <form method="POST" onsubmit="return confirmBulkAction()">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="bulk-actions-content">
                            <span class="selected-count" id="selectedCount">0 advisors selected</span>
                            <select name="bulk_action" class="form-control" style="width: auto;">
                                <option value="">Choose Action</option>
                                <?php if (hasPermission('advisors.edit')): ?>
                                <option value="activate">Activate</option>
                                <option value="deactivate">Deactivate</option>
                                <?php endif; ?>
                                <?php if (hasPermission('advisors.delete')): ?>
                                <option value="delete">Delete</option>
                                <?php endif; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <button type="button" onclick="clearSelection()" class="btn btn-secondary">Clear Selection</button>
                        </div>
                    </form>
                </div>
                
                <!-- Advisor Grid -->
                <?php if (empty($advisors)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-graduate"></i>
                        <h3>No Advisors Found</h3>
                        <p>No advisors match your current filters. Try adjusting your search criteria.</p>
                        <?php if (hasPermission('advisors.create')): ?>
                        <button onclick="openAddAdvisorModal()" class="btn btn-primary">Add Your First Advisor</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="advisor-grid">
                        <?php foreach ($advisors as $advisor): ?>
                            <div class="advisor-card">
                                <input type="checkbox" name="selected_advisors[]" value="<?php echo $advisor['id']; ?>" 
                                       class="advisor-checkbox" onchange="updateBulkActions()">
                                
                                <div class="advisor-header">
                                    <span class="advisor-status <?php echo $advisor['status']; ?>">
                                        <?php echo ucfirst($advisor['status']); ?>
                                    </span>
                                    
                                    <div class="advisor-avatar">
                                        <?php echo strtoupper(substr($advisor['first_name'], 0, 1)); ?>
                                    </div>
                                    
                                    <div class="advisor-name">
                                        <?php echo htmlspecialchars($advisor['first_name'] . ' ' . $advisor['last_name']); ?>
                                    </div>
                                    <div class="advisor-code">
                                        <?php echo htmlspecialchars($advisor['advisor_code']); ?>
                                    </div>
                                </div>
                                
                                <div class="advisor-content">
                                    <div class="advisor-level <?php echo $advisor['certification_level']; ?>">
                                        <?php echo ucfirst($advisor['certification_level']); ?> Level
                                    </div>
                                    
                                    <?php if ($advisor['mca_name']): ?>
                                    <div class="advisor-mca">
                                        <div class="advisor-mca-label">Recruited by:</div>
                                        <?php echo htmlspecialchars($advisor['mca_name'] . ' (' . $advisor['mca_code'] . ')'); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="advisor-stats">
                                        <div class="advisor-stat">
                                            <div class="advisor-stat-label">Sales</div>
                                            <div class="advisor-stat-value"><?php echo formatCurrency($advisor['total_sales']); ?></div>
                                        </div>
                                        <div class="advisor-stat">
                                            <div class="advisor-stat-label">Commission</div>
                                            <div class="advisor-stat-value"><?php echo formatCurrency($advisor['total_commission_earned']); ?></div>
                                        </div>
                                        <div class="advisor-stat">
                                            <div class="advisor-stat-label">Rating</div>
                                            <div class="advisor-stat-value"><?php echo number_format($advisor['performance_rating'], 1); ?>/5</div>
                                        </div>
                                        <div class="advisor-stat">
                                            <div class="advisor-stat-label">Training</div>
                                            <div class="advisor-stat-value"><?php echo $advisor['training_completed'] ? 'Complete' : 'Pending'; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="advisor-actions">
                                        <button onclick="viewAdvisor(<?php echo $advisor['id']; ?>)" class="btn btn-secondary">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if (hasPermission('advisors.edit')): ?>
                                        <button onclick="editAdvisor(<?php echo $advisor['id']; ?>)" class="btn btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php endif; ?>
                                        <?php if (hasPermission('advisors.delete')): ?>
                                        <button onclick="deleteAdvisor(<?php echo $advisor['id']; ?>)" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total_count)); ?> 
                            of <?php echo number_format($total_count); ?> advisors
                        </div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.advisor-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = checkboxes.length + ' advisors selected';
            } else {
                bulkActions.classList.remove('show');
            }
        }
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.advisor-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkActions();
        }
        
        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const checkboxes = document.querySelectorAll('.advisor-checkbox:checked');
            
            if (!action) {
                alert('Please select an action');
                return false;
            }
            
            if (checkboxes.length === 0) {
                alert('Please select at least one advisor');
                return false;
            }
            
            const actionText = action === 'delete' ? 'delete' : action;
            return confirm(`Are you sure you want to ${actionText} ${checkboxes.length} selected advisors?`);
        }
        
        function openAddAdvisorModal() {
            window.location.href = 'add.php';
        }
        
        function viewAdvisor(advisorId) {
            window.location.href = `view.php?id=${advisorId}`;
        }
        
        function editAdvisor(advisorId) {
            window.location.href = `edit.php?id=${advisorId}`;
        }
        
        function deleteAdvisor(advisorId) {
            if (confirm('Are you sure you want to delete this advisor? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="bulk_action" value="delete">
                    <input type="hidden" name="selected_advisors[]" value="${advisorId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
