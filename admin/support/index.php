<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('support.view');

$page_title = 'Support Center';

// Handle ticket actions
if ($_POST && isset($_POST['action'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $ticket_id = (int)$_POST['ticket_id'];
            $action = $_POST['action'];
            
            switch ($action) {
                case 'assign':
                    if (hasPermission('support.assign')) {
                        $assigned_to = (int)$_POST['assigned_to'];
                        $stmt = $db->prepare("
                            UPDATE support_tickets 
                            SET assigned_to = ?, status = 'in_progress', updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$assigned_to, $ticket_id]);
                        
                        // Add activity log
                        $stmt = $db->prepare("
                            INSERT INTO support_activities (ticket_id, user_id, action, description)
                            VALUES (?, ?, 'assigned', ?)
                        ");
                        $stmt->execute([$ticket_id, $_SESSION['user_id'], "Ticket assigned to user ID: $assigned_to"]);
                        
                        $success = 'Ticket assigned successfully!';
                    }
                    break;
                    
                case 'close':
                    if (hasPermission('support.close')) {
                        $resolution = trim($_POST['resolution']);
                        $stmt = $db->prepare("
                            UPDATE support_tickets 
                            SET status = 'closed', resolution = ?, resolved_at = NOW(), updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$resolution, $ticket_id]);
                        
                        // Add activity log
                        $stmt = $db->prepare("
                            INSERT INTO support_activities (ticket_id, user_id, action, description)
                            VALUES (?, ?, 'closed', ?)
                        ");
                        $stmt->execute([$ticket_id, $_SESSION['user_id'], "Ticket closed: $resolution"]);
                        
                        $success = 'Ticket closed successfully!';
                    }
                    break;
                    
                case 'reopen':
                    if (hasPermission('support.edit')) {
                        $stmt = $db->prepare("
                            UPDATE support_tickets 
                            SET status = 'open', resolved_at = NULL, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$ticket_id]);
                        
                        // Add activity log
                        $stmt = $db->prepare("
                            INSERT INTO support_activities (ticket_id, user_id, action, description)
                            VALUES (?, ?, 'reopened', 'Ticket reopened')
                        ");
                        $stmt->execute([$ticket_id, $_SESSION['user_id']]);
                        
                        $success = 'Ticket reopened successfully!';
                    }
                    break;
                    
                case 'reply':
                    if (hasPermission('support.reply')) {
                        $message = trim($_POST['message']);
                        $is_internal = isset($_POST['is_internal']) ? 1 : 0;
                        
                        $stmt = $db->prepare("
                            INSERT INTO support_messages (ticket_id, user_id, message, is_internal, created_at)
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$ticket_id, $_SESSION['user_id'], $message, $is_internal]);
                        
                        // Update ticket status and last activity
                        $stmt = $db->prepare("
                            UPDATE support_tickets 
                            SET status = 'awaiting_customer', updated_at = NOW() 
                            WHERE id = ? AND status != 'closed'
                        ");
                        $stmt->execute([$ticket_id]);
                        
                        $success = 'Reply sent successfully!';
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
$priority_filter = $_GET['priority'] ?? '';
$category_filter = $_GET['category'] ?? '';
$assigned_filter = $_GET['assigned'] ?? '';
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
    $where_conditions[] = "st.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter) {
    $where_conditions[] = "st.priority = ?";
    $params[] = $priority_filter;
}

if ($category_filter) {
    $where_conditions[] = "st.category = ?";
    $params[] = $category_filter;
}

if ($assigned_filter) {
    if ($assigned_filter === 'unassigned') {
        $where_conditions[] = "st.assigned_to IS NULL";
    } else {
        $where_conditions[] = "st.assigned_to = ?";
        $params[] = $assigned_filter;
    }
}

if ($search) {
    $where_conditions[] = "(st.subject LIKE ? OR st.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "
    SELECT COUNT(*)
    FROM support_tickets st
    LEFT JOIN users u ON st.user_id = u.id
    $where_clause
";
$total_stmt = $db->prepare($count_query);
$total_stmt->execute($params);
$total_tickets = $total_stmt->fetchColumn();

// Get tickets
$tickets_query = "
    SELECT st.*, 
           u.first_name, u.last_name, u.email,
           assigned.first_name as assigned_first_name, assigned.last_name as assigned_last_name,
           (SELECT COUNT(*) FROM support_messages WHERE ticket_id = st.id) as message_count
    FROM support_tickets st
    LEFT JOIN users u ON st.user_id = u.id
    LEFT JOIN users assigned ON st.assigned_to = assigned.id
    $where_clause
    ORDER BY st.$sort $order
    LIMIT $per_page OFFSET $offset
";
$tickets_stmt = $db->prepare($tickets_query);
$tickets_stmt->execute($params);
$tickets = $tickets_stmt->fetchAll();

$total_pages = ceil($total_tickets / $per_page);

// Get support agents for assignment
$agents = $db->query("
    SELECT u.id, u.first_name, u.last_name 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE r.name IN ('admin', 'support_agent') 
    AND u.status = 'active'
    ORDER BY u.first_name, u.last_name
")->fetchAll();

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_tickets,
        COUNT(CASE WHEN status = 'open' THEN 1 END) as open_tickets,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tickets,
        COUNT(CASE WHEN status = 'awaiting_customer' THEN 1 END) as awaiting_customer_tickets,
        COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_tickets,
        COUNT(CASE WHEN priority = 'high' AND status != 'closed' THEN 1 END) as high_priority_tickets,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as tickets_24h
    FROM support_tickets
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
        .support-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .support-stats {
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
        
        .stat-card.open { border-left-color: #28a745; }
        .stat-card.in-progress { border-left-color: #ffc107; }
        .stat-card.awaiting { border-left-color: #17a2b8; }
        .stat-card.closed { border-left-color: #6c757d; }
        .stat-card.high-priority { border-left-color: #dc3545; }
        
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
        
        .tickets-table {
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
        
        .ticket-row {
            display: grid;
            grid-template-columns: 1fr 150px 120px 120px 150px 120px;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
            transition: background 0.3s ease;
        }
        
        .ticket-row:hover {
            background: #f8f9fa;
        }
        
        .ticket-row:last-child {
            border-bottom: none;
        }
        
        .ticket-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .ticket-subject {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .ticket-customer {
            color: #666;
            font-size: 0.9em;
        }
        
        .ticket-description {
            color: #999;
            font-size: 0.8em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .ticket-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
            text-align: center;
        }
        
        .ticket-status.open {
            background: #d4edda;
            color: #155724;
        }
        
        .ticket-status.in_progress {
            background: #fff3cd;
            color: #856404;
        }
        
        .ticket-status.awaiting_customer {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .ticket-status.closed {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .ticket-priority {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            text-align: center;
        }
        
        .ticket-priority.low {
            background: #d4edda;
            color: #155724;
        }
        
        .ticket-priority.medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .ticket-priority.high {
            background: #f8d7da;
            color: #721c24;
        }
        
        .ticket-priority.urgent {
            background: #dc3545;
            color: white;
        }
        
        .ticket-assigned {
            font-size: 0.9em;
            color: #666;
        }
        
        .ticket-date {
            color: #666;
            font-size: 0.9em;
        }
        
        .ticket-actions {
            display: flex;
            gap: 5px;
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
        
        .btn-assign {
            background: #28a745;
            color: white;
        }
        
        .btn-close {
            background: #dc3545;
            color: white;
        }
        
        .btn-reply {
            background: #007bff;
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
            .ticket-row {
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
                <!-- Support Header -->
                <div class="support-header">
                    <h1>Support Center</h1>
                    <p>Manage customer support tickets and inquiries</p>
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
                <div class="support-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_tickets']); ?></div>
                        <div class="stat-label">Total Tickets</div>
                    </div>
                    <div class="stat-card open">
                        <div class="stat-value"><?php echo number_format($stats['open_tickets']); ?></div>
                        <div class="stat-label">Open Tickets</div>
                    </div>
                    <div class="stat-card in-progress">
                        <div class="stat-value"><?php echo number_format($stats['in_progress_tickets']); ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-card awaiting">
                        <div class="stat-value"><?php echo number_format($stats['awaiting_customer_tickets']); ?></div>
                        <div class="stat-label">Awaiting Customer</div>
                    </div>
                    <div class="stat-card high-priority">
                        <div class="stat-value"><?php echo number_format($stats['high_priority_tickets']); ?></div>
                        <div class="stat-label">High Priority</div>
                    </div>
                </div>
                
                <!-- Quick Filters -->
                <div class="quick-filters">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" 
                       class="quick-filter <?php echo !$status_filter ? 'active' : ''; ?>">
                        All Tickets
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'open'])); ?>" 
                       class="quick-filter <?php echo $status_filter === 'open' ? 'active' : ''; ?>">
                        Open
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'in_progress'])); ?>" 
                       class="quick-filter <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">
                        In Progress
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['assigned' => 'unassigned'])); ?>" 
                       class="quick-filter <?php echo $assigned_filter === 'unassigned' ? 'active' : ''; ?>">
                        Unassigned
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['priority' => 'high'])); ?>" 
                       class="quick-filter <?php echo $priority_filter === 'high' ? 'active' : ''; ?>">
                        High Priority
                    </a>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search tickets..." class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="awaiting_customer" <?php echo $status_filter === 'awaiting_customer' ? 'selected' : ''; ?>>Awaiting Customer</option>
                                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Priority</label>
                                <select name="priority" class="form-control">
                                    <option value="">All Priorities</option>
                                    <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Assigned To</label>
                                <select name="assigned" class="form-control">
                                    <option value="">All Agents</option>
                                    <option value="unassigned" <?php echo $assigned_filter === 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>" 
                                                <?php echo $assigned_filter == $agent['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="index.php" class="btn btn-outline">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Tickets Table -->
                <div class="tickets-table">
                    <div class="table-header">
                        <h2>Support Tickets (<?php echo number_format($total_tickets); ?>)</h2>
                        <div class="table-actions">
                            <button class="btn btn-outline" onclick="exportTickets()">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="btn btn-primary" onclick="refreshTickets()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-content">
                        <!-- Table Header -->
                        <div class="ticket-row" style="background: #f8f9fa; font-weight: 600;">
                            <div>Ticket Details</div>
                            <div>Customer</div>
                            <div>Status</div>
                            <div>Priority</div>
                            <div>Assigned To</div>
                            <div>Actions</div>
                        </div>
                        
                        <!-- Ticket Rows -->
                        <?php foreach ($tickets as $ticket): ?>
                        <div class="ticket-row">
                            <div class="ticket-info">
                                <div class="ticket-subject">
                                    #<?php echo $ticket['id']; ?> - <?php echo htmlspecialchars($ticket['subject']); ?>
                                </div>
                                <div class="ticket-description">
                                    <?php echo htmlspecialchars(substr($ticket['description'], 0, 100)); ?>...
                                </div>
                                <div style="font-size: 0.8em; color: #999;">
                                    <?php echo $ticket['message_count']; ?> messages • 
                                    Created: <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="ticket-customer">
                                <div><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                                <div style="font-size: 0.8em; color: #999;">
                                    <?php echo htmlspecialchars($ticket['email']); ?>
                                </div>
                            </div>
                            
                            <div>
                                <span class="ticket-status <?php echo $ticket['status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                </span>
                            </div>
                            
                            <div>
                                <span class="ticket-priority <?php echo $ticket['priority']; ?>">
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                            </div>
                            
                            <div class="ticket-assigned">
                                <?php if ($ticket['assigned_first_name']): ?>
                                    <?php echo htmlspecialchars($ticket['assigned_first_name'] . ' ' . $ticket['assigned_last_name']); ?>
                                <?php else: ?>
                                    <span style="color: #dc3545;">Unassigned</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="ticket-actions">
                                <button class="action-btn btn-view" onclick="viewTicket(<?php echo $ticket['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($ticket['status'] !== 'closed'): ?>
                                    <?php if (hasPermission('support.assign')): ?>
                                    <button class="action-btn btn-assign" onclick="assignTicket(<?php echo $ticket['id']; ?>)" title="Assign">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('support.reply')): ?>
                                    <button class="action-btn btn-reply" onclick="replyTicket(<?php echo $ticket['id']; ?>)" title="Reply">
                                        <i class="fas fa-reply"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('support.close')): ?>
                                    <button class="action-btn btn-close" onclick="closeTicket(<?php echo $ticket['id']; ?>)" title="Close">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (hasPermission('support.edit')): ?>
                                    <button class="action-btn btn-assign" onclick="reopenTicket(<?php echo $ticket['id']; ?>)" title="Reopen">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($tickets)): ?>
                        <div class="empty-state">
                            <i class="fas fa-ticket-alt"></i>
                            <h3>No tickets found</h3>
                            <p>No support tickets match your current filters</p>
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
    
    <!-- Assign Modal -->
    <div class="modal" id="assignModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('assignModal')">&times;</span>
            <h3>Assign Ticket</h3>
            
            <form method="POST" id="assignForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="ticket_id" id="assignTicketId">
                
                <div class="form-group">
                    <label>Assign to Agent</label>
                    <select name="assigned_to" class="form-control" required>
                        <option value="">Select Agent</option>
                        <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['id']; ?>">
                                <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeModal('assignModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Ticket</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reply Modal -->
    <div class="modal" id="replyModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('replyModal')">&times;</span>
            <h3>Reply to Ticket</h3>
            
            <form method="POST" id="replyForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="ticket_id" id="replyTicketId">
                
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" class="form-control" rows="6" required 
                              placeholder="Type your reply here..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_internal">
                        <span>Internal note (not visible to customer)</span>
                    </label>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeModal('replyModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Close Modal -->
    <div class="modal" id="closeModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('closeModal')">&times;</span>
            <h3>Close Ticket</h3>
            
            <form method="POST" id="closeForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="close">
                <input type="hidden" name="ticket_id" id="closeTicketId">
                
                <div class="form-group">
                    <label>Resolution Summary</label>
                    <textarea name="resolution" class="form-control" rows="4" required 
                              placeholder="Describe how this ticket was resolved..."></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="closeModal('closeModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-danger">Close Ticket</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function viewTicket(ticketId) {
            window.open(`view.php?id=${ticketId}`, '_blank');
        }
        
        function assignTicket(ticketId) {
            document.getElementById('assignTicketId').value = ticketId;
            document.getElementById('assignModal').style.display = 'block';
        }
        
        function replyTicket(ticketId) {
            document.getElementById('replyTicketId').value = ticketId;
            document.getElementById('replyModal').style.display = 'block';
        }
        
        function closeTicket(ticketId) {
            document.getElementById('closeTicketId').value = ticketId;
            document.getElementById('closeModal').style.display = 'block';
        }
        
        function reopenTicket(ticketId) {
            if (confirm('Are you sure you want to reopen this ticket?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="reopen">
                    <input type="hidden" name="ticket_id" value="${ticketId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function exportTickets() {
            const params = new URLSearchParams(window.location.search);
            window.open(`../api/export-tickets.php?${params.toString()}`, '_blank');
        }
        
        function refreshTickets() {
            location.reload();
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
