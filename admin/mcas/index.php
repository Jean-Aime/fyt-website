<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('mcas.view');

$page_title = 'MCA Management';

// Handle bulk actions
if ($_POST && isset($_POST['bulk_action']) && isset($_POST['selected_mcas'])) {
    $action = $_POST['bulk_action'];
    $mca_ids = $_POST['selected_mcas'];
    
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            switch ($action) {
                case 'activate':
                    if (hasPermission('mcas.edit')) {
                        $placeholders = str_repeat('?,', count($mca_ids) - 1) . '?';
                        $stmt = $db->prepare("UPDATE mcas SET status = 'active' WHERE id IN ($placeholders)");
                        $stmt->execute($mca_ids);
                        $success = count($mca_ids) . ' MCAs activated successfully';
                    }
                    break;
                    
                case 'deactivate':
                    if (hasPermission('mcas.edit')) {
                        $placeholders = str_repeat('?,', count($mca_ids) - 1) . '?';
                        $stmt = $db->prepare("UPDATE mcas SET status = 'inactive' WHERE id IN ($placeholders)");
                        $stmt->execute($mca_ids);
                        $success = count($mca_ids) . ' MCAs deactivated successfully';
                    }
                    break;
                    
                case 'delete':
                    if (hasPermission('mcas.delete')) {
                        $placeholders = str_repeat('?,', count($mca_ids) - 1) . '?';
                        $stmt = $db->prepare("DELETE FROM mcas WHERE id IN ($placeholders)");
                        $stmt->execute($mca_ids);
                        $success = count($mca_ids) . ' MCAs deleted successfully';
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
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR m.mca_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "m.status = ?";
    $params[] = $status_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM mcas m 
    JOIN users u ON m.user_id = u.id
    $where_clause";
$total_mcas = $db->prepare($count_sql);
$total_mcas->execute($params);
$total_count = $total_mcas->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get MCAs
$sql = "
    SELECT m.*, u.first_name, u.last_name, u.email, u.username,
           COUNT(DISTINCT mr.advisor_id) as recruits_count,
           COALESCE(SUM(ct.amount), 0) as total_commission_earned
    FROM mcas m
    JOIN users u ON m.user_id = u.id
    LEFT JOIN mca_recruits mr ON m.id = mr.mca_id AND mr.status = 'active'
    LEFT JOIN commission_transactions ct ON m.id = ct.mca_id AND ct.status = 'paid'
    $where_clause
    GROUP BY m.id
    ORDER BY $sort $order
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$mcas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_mcas,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_mcas,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_mcas,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_mcas,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_mcas_30d
    FROM mcas
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
        .mca-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .mca-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            border: 1px solid #ecf0f1;
        }
        
        .mca-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .mca-header {
            background: linear-gradient(135deg, #D4AF37, #B8941F);
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .mca-avatar {
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
        
        .mca-name {
            color: white;
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .mca-code {
            color: rgba(255,255,255,0.9);
            font-size: 0.9em;
            font-family: monospace;
        }
        
        .mca-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .mca-status.active {
            background: #228B22;
            color: white;
        }
        
        .mca-status.inactive {
            background: #dc3545;
            color: white;
        }
        
        .mca-status.pending {
            background: #f39c12;
            color: white;
        }
        
        .mca-status.suspended {
            background: #6c757d;
            color: white;
        }
        
        .mca-content {
            padding: 25px;
        }
        
        .mca-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .mca-stat {
            text-align: center;
        }
        
        .mca-stat-label {
            font-size: 0.8em;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .mca-stat-value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        .mca-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }
        
        .mca-actions .btn {
            flex: 1;
            padding: 10px;
            font-size: 0.9em;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .mca-checkbox {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 20px;
            height: 20px;
            accent-color: #D4AF37;
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
                        <h2>MCA Management</h2>
                        <p>Manage Master Certified Advisors</p>
                    </div>
                    <div class="content-actions">
                        <?php if (hasPermission('mcas.create')): ?>
                        <button onclick="openAddMCAModal()" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New MCA
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
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_mcas']); ?></h3>
                            <p>Total MCAs</p>
                            <div class="stat-change positive">
                                +<?php echo $stats['new_mcas_30d']; ?> this month
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['active_mcas']); ?></h3>
                            <p>Active MCAs</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['pending_mcas']); ?></h3>
                            <p>Pending Approval</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['inactive_mcas']); ?></h3>
                            <p>Inactive MCAs</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search MCAs</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name, email, MCA code..." class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sort By</label>
                                <select name="sort" class="form-control">
                                    <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                                    <option value="total_sales" <?php echo $sort === 'total_sales' ? 'selected' : ''; ?>>Total Sales</option>
                                    <option value="recruits_count" <?php echo $sort === 'recruits_count' ? 'selected' : ''; ?>>Recruits Count</option>
                                    <option value="performance_rating" <?php echo $sort === 'performance_rating' ? 'selected' : ''; ?>>Performance</option>
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
                            <span class="selected-count" id="selectedCount">0 MCAs selected</span>
                            <select name="bulk_action" class="form-control" style="width: auto;">
                                <option value="">Choose Action</option>
                                <?php if (hasPermission('mcas.edit')): ?>
                                <option value="activate">Activate</option>
                                <option value="deactivate">Deactivate</option>
                                <?php endif; ?>
                                <?php if (hasPermission('mcas.delete')): ?>
                                <option value="delete">Delete</option>
                                <?php endif; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <button type="button" onclick="clearSelection()" class="btn btn-secondary">Clear Selection</button>
                        </div>
                    </form>
                </div>
                
                <!-- MCA Grid -->
                <?php if (empty($mcas)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-tie"></i>
                        <h3>No MCAs Found</h3>
                        <p>No MCAs match your current filters. Try adjusting your search criteria.</p>
                        <?php if (hasPermission('mcas.create')): ?>
                        <button onclick="openAddMCAModal()" class="btn btn-primary">Add Your First MCA</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mca-grid">
                        <?php foreach ($mcas as $mca): ?>
                            <div class="mca-card">
                                <input type="checkbox" name="selected_mcas[]" value="<?php echo $mca['id']; ?>" 
                                       class="mca-checkbox" onchange="updateBulkActions()">
                                
                                <div class="mca-header">
                                    <span class="mca-status <?php echo $mca['status']; ?>">
                                        <?php echo ucfirst($mca['status']); ?>
                                    </span>
                                    
                                    <div class="mca-avatar">
                                        <?php echo strtoupper(substr($mca['first_name'], 0, 1)); ?>
                                    </div>
                                    
                                    <div class="mca-name">
                                        <?php echo htmlspecialchars($mca['first_name'] . ' ' . $mca['last_name']); ?>
                                    </div>
                                    <div class="mca-code">
                                        <?php echo htmlspecialchars($mca['mca_code']); ?>
                                    </div>
                                </div>
                                
                                <div class="mca-content">
                                    <div class="mca-stats">
                                        <div class="mca-stat">
                                            <div class="mca-stat-label">Recruits</div>
                                            <div class="mca-stat-value"><?php echo number_format($mca['recruits_count']); ?></div>
                                        </div>
                                        <div class="mca-stat">
                                            <div class="mca-stat-label">Commission</div>
                                            <div class="mca-stat-value"><?php echo formatCurrency($mca['total_commission_earned']); ?></div>
                                        </div>
                                        <div class="mca-stat">
                                            <div class="mca-stat-label">Sales</div>
                                            <div class="mca-stat-value"><?php echo formatCurrency($mca['total_sales']); ?></div>
                                        </div>
                                        <div class="mca-stat">
                                            <div class="mca-stat-label">Rating</div>
                                            <div class="mca-stat-value"><?php echo number_format($mca['performance_rating'], 1); ?>/5</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mca-actions">
                                        <button onclick="viewMCA(<?php echo $mca['id']; ?>)" class="btn btn-secondary">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if (hasPermission('mcas.edit')): ?>
                                        <button onclick="editMCA(<?php echo $mca['id']; ?>)" class="btn btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php endif; ?>
                                        <?php if (hasPermission('mcas.delete')): ?>
                                        <button onclick="deleteMCA(<?php echo $mca['id']; ?>)" class="btn btn-danger">
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
                            of <?php echo number_format($total_count); ?> MCAs
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
            const checkboxes = document.querySelectorAll('.mca-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = checkboxes.length + ' MCAs selected';
            } else {
                bulkActions.classList.remove('show');
            }
        }
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.mca-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkActions();
        }
        
        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const checkboxes = document.querySelectorAll('.mca-checkbox:checked');
            
            if (!action) {
                alert('Please select an action');
                return false;
            }
            
            if (checkboxes.length === 0) {
                alert('Please select at least one MCA');
                return false;
            }
            
            const actionText = action === 'delete' ? 'delete' : action;
            return confirm(`Are you sure you want to ${actionText} ${checkboxes.length} selected MCAs?`);
        }
        
        function openAddMCAModal() {
            window.location.href = 'add.php';
        }
        
        function viewMCA(mcaId) {
            window.location.href = `view.php?id=${mcaId}`;
        }
        
        function editMCA(mcaId) {
            window.location.href = `edit.php?id=${mcaId}`;
        }
        
        function deleteMCA(mcaId) {
            if (confirm('Are you sure you want to delete this MCA? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="bulk_action" value="delete">
                    <input type="hidden" name="selected_mcas[]" value="${mcaId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
