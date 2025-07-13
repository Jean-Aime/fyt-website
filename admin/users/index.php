<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$first_name = isset($current_user['first_name']) ? $current_user['first_name'] : 'Admin';
$last_name = isset($current_user['last_name']) ? $current_user['last_name'] : '';
$role_name = isset($current_user['role_display']) ? $current_user['role_display'] : 'User';
require_once '../../config/config.php';


$auth = new SecureAuth($db);
requireLogin();
requirePermission('users.view');

$page_title = 'User Management';

// Handle bulk actions
if ($_POST && isset($_POST['bulk_action']) && isset($_POST['selected_users'])) {
    $action = $_POST['bulk_action'];
    $user_ids = $_POST['selected_users'];
    
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            switch ($action) {
                case 'activate':
                    if (hasPermission('users.edit')) {
                        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
                        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id IN ($placeholders)");
                        $stmt->execute($user_ids);
                        $success = count($user_ids) . ' users activated successfully';
                    }
                    break;
                    
                case 'deactivate':
                    if (hasPermission('users.edit')) {
                        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
                        $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id IN ($placeholders)");
                        $stmt->execute($user_ids);
                        $success = count($user_ids) . ' users deactivated successfully';
                    }
                    break;
                    
                case 'delete':
    if (hasPermission('users.delete')) {
        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
        $stmt = $db->prepare("DELETE FROM users WHERE id IN ($placeholders) AND id != ?");
        $stmt->execute(array_merge($user_ids, [$_SESSION['user_id']]));
        $success = count($user_ids) . ' users deleted successfully';
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
$role_filter = $_GET['role'] ?? '';
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
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $where_conditions[] = "u.role_id = ?";
    $params[] = $role_filter;
}

if ($status_filter) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM users u 
    LEFT JOIN user_roles ur ON u.role_id = ur.id
    $where_clause";
$total_users = $db->prepare($count_sql);
$total_users->execute($params);
$total_count = $total_users->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get users
$sql = "
    SELECT u.*, r.name AS role_display
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    $where_clause
    ORDER BY $sort $order
    LIMIT $per_page OFFSET $offset
";


$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roles = $db->query("SELECT id, display_name FROM roles ORDER BY display_name")->fetchAll(PDO::FETCH_ASSOC);


// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d
    FROM users
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
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .user-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            border: 1px solid #ecf0f1;
        }
        
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .user-header {
            background: linear-gradient(135deg, #D4AF37, #B8941F);
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .user-avatar {
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
        
        .user-name {
            color: white;
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-email {
            color: rgba(255,255,255,0.9);
            font-size: 0.9em;
        }
        
        .user-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .user-status.active {
            background: #228B22;
            color: white;
        }
        
        .user-status.inactive {
            background: #dc3545;
            color: white;
        }
        
        .user-status.suspended {
            background: #f39c12;
            color: white;
        }
        
        .user-content {
            padding: 25px;
        }
        
        .user-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .user-info-item {
            text-align: center;
        }
        
        .user-info-label {
            font-size: 0.8em;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .user-info-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .user-role {
            background: linear-gradient(135deg, #000000, #333333);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }
        
        .user-actions .btn {
            flex: 1;
            padding: 10px;
            font-size: 0.9em;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .user-checkbox {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 20px;
            height: 20px;
            accent-color: #D4AF37;
        }
        
        .view-toggle {
            display: flex;
            gap: 5px;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 8px;
        }
        
        .view-toggle button {
            padding: 8px 12px;
            border: none;
            background: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-toggle button.active {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            color: #D4AF37;
        }
        
        .table-view {
            display: none;
        }
        
        .table-view.active {
            display: block;
        }
        
        .grid-view.active {
            display: block;
        }
        
        .grid-view {
            display: none;
        }
        
        .bulk-actions {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: none;
        }
        
        .bulk-actions.show {
            display: block;
        }
        
        .bulk-actions-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .selected-count {
            font-weight: 600;
            color: #333;
        }
        /* User Details Page */
.user-details-container {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.user-profile-section {
    padding: 30px;
}

.user-profile-header {
    text-align: center;
    margin-bottom: 30px;
}

.user-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 3em;
    font-weight: bold;
    color: #555;
    border: 5px solid #D4AF37;
}

.user-name {
    font-size: 1.8em;
    font-weight: 600;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.user-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.7em;
    font-weight: 600;
    text-transform: uppercase;
}

.user-status.active {
    background: #228B22;
    color: white;
}

.user-status.inactive {
    background: #dc3545;
    color: white;
}

.user-status.suspended {
    background: #f39c12;
    color: white;
}

.user-role {
    background: linear-gradient(135deg, #000000, #333333);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: 500;
    display: inline-block;
    margin-top: 10px;
}

.user-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.detail-card {
    background: #f9f9f9;
    border-radius: 10px;
    padding: 20px;
    border-left: 4px solid #D4AF37;
}

.detail-card h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 10px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.detail-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #555;
}

.detail-value {
    color: #2c3e50;
}

.permissions-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.permission-tag {
    background: #e8f4fd;
    color: #2c7be5;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8em;
}

.user-activity-section {
    padding: 30px;
    border-top: 1px solid #eee;
}

.activity-timeline {
    margin-top: 20px;
}

.activity-item {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #D4AF37;
    flex-shrink: 0;
}

.activity-content {
    flex-grow: 1;
}

.activity-description {
    font-weight: 500;
    margin-bottom: 5px;
}

.activity-meta {
    display: flex;
    gap: 15px;
    font-size: 0.8em;
    color: #7f8c8d;
}

/* Edit Form */
.user-edit-form {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #eee;
}

.form-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.form-section h3 {
    margin-top: 0;
    color: #2c3e50;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.form-note {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 0.9em;
    color: #555;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
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
                        <h2>User Management</h2>
                        <p>Manage user accounts, roles, and permissions</p>
                    </div>
                    <div class="content-actions">
                        <div class="view-toggle">
                            <button onclick="switchView('grid')" class="active" id="grid-btn">
                                <i class="fas fa-th"></i> Grid
                            </button>
                            <button onclick="switchView('table')" id="table-btn">
                                <i class="fas fa-list"></i> Table
                            </button>
                        </div>
                        <?php if (hasPermission('users.create')): ?>
                        <button onclick="openAddUserModal()" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New User
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
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_users']); ?></h3>
                            <p>Total Users</p>
                            <div class="stat-change positive">
                                +<?php echo $stats['new_users_30d']; ?> this month
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['active_users']); ?></h3>
                            <p>Active Users</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['inactive_users']); ?></h3>
                            <p>Inactive Users</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search Users</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name, email, username..." class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <select name="role" class="form-control">
                                    <option value="">All Roles</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" 
                                                <?php echo $role_filter == $role['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['display_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sort By</label>
                                <select name="sort" class="form-control">
                                    <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                                    <option value="first_name" <?php echo $sort === 'first_name' ? 'selected' : ''; ?>>First Name</option>
                                    <option value="last_name" <?php echo $sort === 'last_name' ? 'selected' : ''; ?>>Last Name</option>
                                    <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Email</option>
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
                            <span class="selected-count" id="selectedCount">0 users selected</span>
                            <select name="bulk_action" class="form-control" style="width: auto;">
                                    <option value="">Choose Action</option>
                                    <?php if (hasPermission('users.edit')): ?>
                                    <option value="activate">Activate</option>
                                    <option value="deactivate">Deactivate</option>
                                    <?php endif; ?>
                                    <?php if (hasPermission('users.delete')): ?>
                                    <option value="delete">Delete</option>
                                    <?php endif; ?>
                             </select>
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <button type="button" onclick="clearSelection()" class="btn btn-secondary">Clear Selection</button>
                        </div>
                    </form>
                </div>
                
                <!-- Grid View -->
                <div class="grid-view active" id="gridView">
                    <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>No Users Found</h3>
                            <p>No users match your current filters. Try adjusting your search criteria.</p>
                            <?php if (hasPermission('users.create')): ?>
                            <button onclick="openAddUserModal()" class="btn btn-primary">Add Your First User</button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="user-grid">
                            <?php foreach ($users as $user): ?>
                                <div class="user-card">
                                    <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" 
                                           class="user-checkbox" onchange="updateBulkActions()">
                                    
                                    <div class="user-header">
                                        <span class="user-status <?php echo $user['status']; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                        
                                        <div class="user-avatar">
                                            <?php if ($user['profile_image']): ?>
                                                <img src="../../<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                                     alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="user-name">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </div>
                                        <div class="user-email">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="user-content">
                                        <div class="user-role">
                                            <?php echo htmlspecialchars($user['role_display'] ?? 'No Role'); ?>
                                        </div>
                                        
                                        <div class="user-info">
                                            <div class="user-info-item">
                                                <div class="user-info-label">Username</div>
                                                <div class="user-info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                                            </div>
                                            <div class="user-info-item">
                                                <div class="user-info-label">Joined</div>
                                                <div class="user-info-value"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="user-actions">
                                            <button onclick="viewUser(<?php echo $user['id']; ?>)" class="btn btn-secondary">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if (hasPermission('users.edit')): ?>
                                            <button onclick="editUser(<?php echo $user['id']; ?>)" class="btn btn-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('users.delete') && $user['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="btn btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Table View -->
                <div class="table-view" id="tableView">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes()">
                                    </th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No users found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" 
                                                       class="user-checkbox" onchange="updateBulkActions()">
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div class="user-avatar" style="width: 40px; height: 40px; font-size: 1em;">
                                                        <?php if ($user['profile_image']): ?>
                                                            <img src="../../<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                                                 alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                                        <?php else: ?>
                                                            <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                        <br><small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role_display'] ?? 'No Role'); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $user['status']; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="viewUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-secondary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if (hasPermission('users.edit')): ?>
                                                    <button onclick="editUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if (hasPermission('users.delete') && $user['id'] != $_SESSION['user_id']): ?>
                                                    <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total_count)); ?> 
                            of <?php echo number_format($total_count); ?> users
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
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button onclick="closeModal('addUserModal')" class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addUserForm" method="POST" action="add.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username <span class="required">*</span></label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label for="password">Password <span class="required">*</span></label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label for="role_id">Role</label>
                            <select id="role_id" name="role_id" class="form-control">
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>">
                                        <?php echo htmlspecialchars($role['display_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" onclick="closeModal('addUserModal')" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        
        function switchView(view) {
            const gridView = document.getElementById('gridView');
            const tableView = document.getElementById('tableView');
            const gridBtn = document.getElementById('grid-btn');
            const tableBtn = document.getElementById('table-btn');
            
            if (view === 'grid') {
                gridView.classList.add('active');
                tableView.classList.remove('active');
                gridBtn.classList.add('active');
                tableBtn.classList.remove('active');
            } else {
                tableView.classList.add('active');
                gridView.classList.remove('active');
                tableBtn.classList.add('active');
                gridBtn.classList.remove('active');
            }
            
            localStorage.setItem('userViewMode', view);
        }
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = checkboxes.length + ' users selected';
            } else {
                bulkActions.classList.remove('show');
            }
        }
        
        function toggleAllCheckboxes() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.user-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            if (selectAll) {
                selectAll.checked = false;
            }
            
            updateBulkActions();
        }
        
        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            
            if (!action) {
                alert('Please select an action');
                return false;
            }
            
            if (checkboxes.length === 0) {
                alert('Please select at least one user');
                return false;
            }
            
            const actionText = action === 'delete' ? 'delete' : action;
            return confirm(`Are you sure you want to ${actionText} ${checkboxes.length} selected users?`);
        }
        
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function viewUser(userId) {
            window.location.href = `view.php?id=${userId}`;
        }
        
        function editUser(userId) {
            window.location.href = `edit.php?id=${userId}`;
        }
        
        function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="bulk_action" value="delete">
            <input type="hidden" name="selected_users[]" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
        
        // Restore view mode from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const savedView = localStorage.getItem('userViewMode');
            if (savedView && savedView === 'table') {
                switchView('table');
            }
        });
        
        // Auto-submit filters on change
        document.querySelectorAll('.filters-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Modal styles
        const modalStyles = `
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
            
            .modal-content {
                background: white;
                border-radius: 15px;
                width: 90%;
                max-width: 600px;
                max-height: 90vh;
                overflow-y: auto;
            }
            
            .modal-header {
                padding: 20px 25px;
                border-bottom: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .modal-header h3 {
                margin: 0;
                color: #2c3e50;
            }
            
            .close-btn {
                background: none;
                border: none;
                font-size: 1.5em;
                cursor: pointer;
                color: #999;
            }
            
            .modal-body {
                padding: 25px;
            }
            
            .modal-footer {
                padding: 20px 25px;
                border-top: 1px solid #eee;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = modalStyles;
        document.head.appendChild(styleSheet);
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
