<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('mca.view');

$page_title = 'MCA Agents Management';

// Handle agent actions
if ($_POST && isset($_POST['action'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $agent_id = (int)$_POST['agent_id'];
            $action = $_POST['action'];
            
            switch ($action) {
                case 'approve':
                    if (hasPermission('mca.approve')) {
                        $stmt = $db->prepare("
                            UPDATE mca_agents 
                            SET status = 'active', approved_at = NOW(), approved_by = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $agent_id]);
                        
                        // Send approval email
                        $agent = $db->prepare("SELECT * FROM mca_agents WHERE id = ?")->execute([$agent_id]);
                        $agent = $stmt->fetch();
                        
                        // Create user account for agent
                        $password = generateRandomPassword();
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        $stmt = $db->prepare("
                            INSERT INTO users (first_name, last_name, email, password, role, status, created_at)
                            VALUES (?, ?, ?, ?, 'mca_agent', 'active', NOW())
                        ");
                        $stmt->execute([
                            $agent['first_name'], 
                            $agent['last_name'], 
                            $agent['email'], 
                            $hashed_password
                        ]);
                        
                        $user_id = $db->lastInsertId();
                        
                        // Update agent with user_id
                        $stmt = $db->prepare("UPDATE mca_agents SET user_id = ? WHERE id = ?");
                        $stmt->execute([$user_id, $agent_id]);
                        
                        $success = 'Agent approved successfully!';
                    }
                    break;
                    
                case 'reject':
                    if (hasPermission('mca.reject')) {
                        $rejection_reason = trim($_POST['rejection_reason']);
                        $stmt = $db->prepare("
                            UPDATE mca_agents 
                            SET status = 'rejected', rejection_reason = ?, rejected_at = NOW(), rejected_by = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$rejection_reason, $_SESSION['user_id'], $agent_id]);
                        
                        $success = 'Agent application rejected.';
                    }
                    break;
                    
                case 'suspend':
                    if (hasPermission('mca.suspend')) {
                        $suspension_reason = trim($_POST['suspension_reason']);
                        $stmt = $db->prepare("
                            UPDATE mca_agents 
                            SET status = 'suspended', suspension_reason = ?, suspended_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$suspension_reason, $agent_id]);
                        
                        // Also suspend user account
                        $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = (SELECT user_id FROM mca_agents WHERE id = ?)");
                        $stmt->execute([$agent_id]);
                        
                        $success = 'Agent suspended successfully.';
                    }
                    break;
                    
                case 'activate':
                    if (hasPermission('mca.activate')) {
                        $stmt = $db->prepare("
                            UPDATE mca_agents 
                            SET status = 'active', suspension_reason = NULL, suspended_at = NULL
                            WHERE id = ?
                        ");
                        $stmt->execute([$agent_id]);
                        
                        // Also activate user account
                        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = (SELECT user_id FROM mca_agents WHERE id = ?)");
                        $stmt->execute([$agent_id]);
                        
                        $success = 'Agent activated successfully.';
                    }
                    break;
                    
                case 'update_commission':
                    if (hasPermission('mca.edit')) {
                        $commission_rate = (float)$_POST['commission_rate'];
                        $stmt = $db->prepare("
                            UPDATE mca_agents 
                            SET commission_rate = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$commission_rate, $agent_id]);
                        
                        $success = 'Commission rate updated successfully.';
                    }
                    break;
            }
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "ma.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(ma.first_name LIKE ? OR ma.last_name LIKE ? OR ma.email LIKE ? OR ma.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) FROM mca_agents ma $where_clause";
$total_stmt = $db->prepare($count_query);
$total_stmt->execute($params);
$total_agents = $total_stmt->fetchColumn();

// Get agents
$agents_query = "
    SELECT ma.*, 
           u.last_login,
           (SELECT COUNT(*) FROM bookings WHERE referred_by = ma.id) as total_referrals,
           (SELECT COALESCE(SUM(commission_amount), 0) FROM mca_commissions WHERE agent_id = ma.id) as total_commissions,
           (SELECT COALESCE(SUM(commission_amount), 0) FROM mca_commissions WHERE agent_id = ma.id AND status = 'paid') as paid_commissions
    FROM mca_agents ma
    LEFT JOIN users u ON ma.user_id = u.id
    $where_clause
    ORDER BY ma.$sort $order
    LIMIT $per_page OFFSET $offset
";
$agents_stmt = $db->prepare($agents_query);
$agents_stmt->execute($params);
$agents = $agents_stmt->fetchAll();

$total_pages = ceil($total_agents / $per_page);

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_agents,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_agents,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_agents,
        COUNT(CASE WHEN status = 'suspended' THEN 1 END) as suspended_agents,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_agents,
        (SELECT COALESCE(SUM(commission_amount), 0) FROM mca_commissions WHERE status = 'pending') as pending_commissions,
        (SELECT COALESCE(SUM(commission_amount), 0) FROM mca_commissions WHERE status = 'paid') as paid_commissions
    FROM mca_agents
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
    <style>
        .mca-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .mca-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid var(--admin-primary);
        }
        
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.active { border-left-color: #28a745; }
        .stat-card.suspended { border-left-color: #dc3545; }
        .stat-card.commissions { border-left-color: #17a2b8; }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--admin-primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .agents-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .agent-row {
            display: grid;
            grid-template-columns: 1fr 150px 120px 120px 150px 120px 150px;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
            transition: background 0.3s ease;
        }
        
        .agent-row:hover {
            background: #f8f9fa;
        }
        
        .agent-row:last-child {
            border-bottom: none;
        }
        
        .agent-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .agent-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .agent-email {
            color: #666;
            font-size: 0.9em;
        }
        
        .agent-phone {
            color: #999;
            font-size: 0.8em;
        }
        
        .agent-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
            text-align: center;
        }
        
        .agent-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .agent-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .agent-status.suspended {
            background: #f8d7da;
            color: #721c24;
        }
        
        .agent-status.rejected {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .agent-stats {
            text-align: center;
            font-size: 0.9em;
        }
        
        .stat-number {
            font-weight: 600;
            color: var(--admin-primary);
            display: block;
        }
        
        .stat-text {
            color: #666;
            font-size: 0.8em;
        }
        
        .agent-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.3s ease;
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
        }
        
        .btn-reject {
            background: #dc3545;
            color: white;
        }
        
        .btn-suspend {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-activate {
            background: #28a745;
            color: white;
        }
        
        .btn-commission {
            background: #6f42c1;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 1.5em;
            cursor: pointer;
            color: #999;
        }
        
        .quick-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .quick-filter {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background: white;
            color: #666;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }
        
        .quick-filter.active,
        .quick-filter:hover {
            background: var(--admin-primary);
            color: white;
            border-color: var(--admin-primary);
        }
        
        @media (max-width: 768px) {
            .agent-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .filters-grid {
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
                <!-- MCA Header -->
                <div class="mca-header">
                    <h1>MCA Agents Management</h1>
                    <p>Manage your Multi-Channel Affiliate agents and their performance</p>
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
                <div class="mca-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_agents']); ?></div>
                        <div class="stat-label">Total Agents</div>
                    </div>
                    <div class="stat-card pending">
                        <div class="stat-value"><?php echo number_format($stats['pending_agents']); ?></div>
                        <div class="stat-label">Pending Applications</div>
                    </div>
                    <div class="stat-card active">
                        <div class="stat-value"><?php echo number_format($stats['active_agents']); ?></div>
                        <div class="stat-label">Active Agents</div>
                    </div>
                    <div class="stat-card suspended">
                        <div class="stat-value"><?php echo number_format($stats['suspended_agents']); ?></div>
                        <div class="stat-label">Suspended Agents</div>
                    </div>
                    <div class="stat-card commissions">
                        <div class="stat-value">$<?php echo number_format($stats['pending_commissions']); ?></div>
                        <div class="stat-label">Pending Commissions</div>
                    </div>
                </div>
                
                <!-- Quick Filters -->
                <div class="quick-filters">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" 
                       class="quick-filter <?php echo !$status_filter ? 'active' : ''; ?>">
                        All Agents
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'pending'])); ?>" 
                       class="quick-filter <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                        Pending Applications
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'active'])); ?>" 
                       class="quick-filter <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                        Active Agents
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'suspended'])); ?>" 
                       class="quick-filter <?php echo $status_filter === 'suspended' ? 'active' : ''; ?>">
                        Suspended
                    </a>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search agents..." class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Sort By</label>
                                <select name="sort" class="form-control">
                                    <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Application Date</option>
                                    <option value="first_name" <?php echo $sort === 'first_name' ? 'selected' : ''; ?>>Name</option>
                                    <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Status</option>
                                    <option value="commission_rate" <?php echo $sort === 'commission_rate' ? 'selected' : ''; ?>>Commission Rate</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="agents.php" class="btn btn-outline">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Agents Table -->
                <div class="agents-table">
                    <div class="table-header">
                        <h2>MCA Agents (<?php echo number_format($total_agents); ?>)</h2>
                        <div class="table-actions">
                            <button class="btn btn-outline" onclick="exportAgents()">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <a href="applications.php" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> View Applications
                            </a>
                        </div>
                    </div>
                    
                    <div class="table-content">
                        <!-- Table Header -->
                        <div class="agent-row" style="background: #f8f9fa; font-weight: 600;">
                            <div>Agent Details</div>
                            <div>Status</div>
                            <div>Commission Rate</div>
                            <div>Referrals</div>
                            <div>Total Commissions</div>
                            <div>Join Date</div>
                            <div>Actions</div>
                        </div>
                        
                        <!-- Agent Rows -->
                        <?php foreach ($agents as $agent): ?>
                        <div class="agent-row">
                            <div class="agent-info">
                                <div class="agent-name">
                                    <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                </div>
                                <div class="agent-email"><?php echo htmlspecialchars($agent['email']); ?></div>
                                <div class="agent-phone"><?php echo htmlspecialchars($agent['phone'] ?: 'No phone'); ?></div>
                            </div>
                            
                            <div>
                                <span class="agent-status <?php echo $agent['status']; ?>">
                                    <?php echo ucfirst($agent['status']); ?>
                                </span>
                            </div>
                            
                            <div class="agent-stats">
                                <span class="stat-number"><?php echo $agent['commission_rate']; ?>%</span>
                            </div>
                            
                            <div class="agent-stats">
                                <span class="stat-number"><?php echo $agent['total_referrals']; ?></span>
                                <span class="stat-text">referrals</span>
                            </div>
                            
                            <div class="agent-stats">
                                <span class="stat-number">$<?php echo number_format($agent['total_commissions']); ?></span>
                                <span class="stat-text">($<?php echo number_format($agent['paid_commissions']); ?> paid)</span>
                            </div>
                            
                            <div style="font-size: 0.9em; color: #666;">
                                <?php echo date('M j, Y', strtotime($agent['created_at'])); ?>
                            </div>
                            
                            <div class="agent-actions">
                                <button class="action-btn btn-view" onclick="viewAgent(<?php echo $agent['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($agent['status'] === 'pending'): ?>
                                    <?php if (hasPermission('mca.approve')): ?>
                                    <button class="action-btn btn-approve" onclick="approveAgent(<?php echo $agent['id']; ?>)" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('mca.reject')): ?>
                                    <button class="action-btn btn-reject" onclick="rejectAgent(<?php echo $agent['id']; ?>)" title="Reject">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                <?php elseif ($agent['status'] === 'active'): ?>
                                    <?php if (hasPermission('mca.suspend')): ?>
                                    <button class="action-btn btn-suspend" onclick="suspendAgent(<?php echo $agent['id']; ?>)" title="Suspend">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                <?php elseif ($agent['status'] === 'suspended'): ?>
                                    <?php if (hasPermission('mca.activate')): ?>
                                    <button class="action-btn btn-activate" onclick="activateAgent(<?php echo $agent['id']; ?>)" title="Activate">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('mca.edit')): ?>
                                <button class="action-btn btn-commission" onclick="updateCommission(<?php echo $agent['id']; ?>, <?php echo $agent['commission_rate']; ?>)" title="Update Commission">
                                    <i class="fas fa-percentage"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($agents)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>No agents found</h3>
                            <p>No MCA agents match your current filters</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Approve Modal -->
    <div class="modal" id="approveModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('approveModal')">&times;</span>
            <h3>Approve Agent Application</h3>
            
            <form method="POST" id="approveForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="agent_id" id="approveAgentId">
                
                <p>Are you sure you want to approve this agent application? This will create a user account and activate the agent.</p>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeModal('approveModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Agent</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('rejectModal')">&times;</span>
            <h3>Reject Agent Application</h3>
            
            <form method="POST" id="rejectForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="agent_id" id="rejectAgentId">
                
                <div class="form-group">
                    <label>Rejection Reason</label>
                    <textarea name="rejection_reason" class="form-control" rows="4" required 
                              placeholder="Please provide a reason for rejection..."></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeModal('rejectModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Application</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Suspend Modal -->
    <div class="modal" id="suspendModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('suspendModal')">&times;</span>
            <h3>Suspend Agent</h3>
            
            <form method="POST" id="suspendForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="suspend">
                <input type="hidden" name="agent_id" id="suspendAgentId">
                
                <div class="form-group">
                    <label>Suspension Reason</label>
                    <textarea name="suspension_reason" class="form-control" rows="4" required 
                              placeholder="Please provide a reason for suspension..."></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeModal('suspendModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-warning">Suspend Agent</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Commission Modal -->
    <div class="modal" id="commissionModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('commissionModal')">&times;</span>
            <h3>Update Commission Rate</h3>
            
            <form method="POST" id="commissionForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_commission">
                <input type="hidden" name="agent_id" id="commissionAgentId">
                
                <div class="form-group">
                    <label>Commission Rate (%)</label>
                    <input type="number" name="commission_rate" id="commissionRate" class="form-control" 
                           min="0" max="50" step="0.1" required>
                    <small class="form-text">Enter the commission percentage (0-50%)</small>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeModal('commissionModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Commission</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function viewAgent(agentId) {
            window.open(`view.php?id=${agentId}`, '_blank');
        }
        
        function approveAgent(agentId) {
            document.getElementById('approveAgentId').value = agentId;
            document.getElementById('approveModal').style.display = 'block';
        }
        
        function rejectAgent(agentId) {
            document.getElementById('rejectAgentId').value = agentId;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function suspendAgent(agentId) {
            document.getElementById('suspendAgentId').value = agentId;
            document.getElementById('suspendModal').style.display = 'block';
        }
        
        function activateAgent(agentId) {
            if (confirm('Are you sure you want to activate this agent?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="agent_id" value="${agentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function updateCommission(agentId, currentRate) {
            document.getElementById('commissionAgentId').value = agentId;
            document.getElementById('commissionRate').value = currentRate;
            document.getElementById('commissionModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function exportAgents() {
            const params = new URLSearchParams(window.location.search);
            window.open(`../api/export-agents.php?${params.toString()}`, '_blank');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Auto-submit filters on change
        document.querySelectorAll('.filters-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
