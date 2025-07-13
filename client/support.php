<?php
// Show any PHP errors clearly (remove on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Ensure user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../login.php');
    exit;
}

// Load config and DB connection
require_once __DIR__ . '/../config/config.php';

// Get user ID
$user_id = $_SESSION['user_id'];

// Handle new ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject']);
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $message = trim($_POST['message']);

    // Validate inputs
    if (empty($subject) || empty($message)) {
        $error = "Subject and message are required.";
    } else {
        try {
            $db->beginTransaction();

            // Generate ticket number
            $ticket_number = 'TKT-' . strtoupper(uniqid());

            // Insert ticket
            $stmt = $db->prepare("
                INSERT INTO support_tickets (
                    user_id, 
                    ticket_number, 
                    subject, 
                    category, 
                    priority, 
                    description, 
                    status, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())
            ");
            $stmt->execute([
                $user_id,
                $ticket_number,
                $subject,
                $category,
                $priority,
                $message
            ]);

            // Get the new ticket ID
            $ticket_id = $db->lastInsertId();

            // Insert initial message
            $stmt = $db->prepare("
                INSERT INTO support_messages (
                    ticket_id, 
                    user_id, 
                    message, 
                    is_internal, 
                    created_at
                ) VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$ticket_id, $user_id, $message]);

            $db->commit();

            // Create notification for support team
            $stmt = $db->prepare("
                INSERT INTO notifications (
                    user_id, 
                    title, 
                    message, 
                    type
                ) VALUES (0, 'New Support Ticket', ?, 'support')
            ");
            $stmt->execute(["New ticket #$ticket_number: $subject"]);

            $_SESSION['success_message'] = "Your support ticket has been submitted successfully! Ticket #$ticket_number";
            header('Location: support.php');
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Error submitting ticket: " . $e->getMessage();
        }
    }
}

// Handle ticket reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    $ticket_id = (int) $_POST['ticket_id'];
    $reply_message = trim($_POST['reply_message']);

    if (empty($reply_message)) {
        $error = "Reply message cannot be empty.";
    } else {
        try {
            // Verify ticket belongs to user
            $stmt = $db->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
            $stmt->execute([$ticket_id, $user_id]);

            if ($stmt->fetch()) {
                $db->beginTransaction();

                // Insert reply
                $stmt = $db->prepare("
                    INSERT INTO support_messages (
                        ticket_id, 
                        user_id, 
                        message, 
                        is_internal, 
                        created_at
                    ) VALUES (?, ?, ?, 0, NOW())
                ");
                $stmt->execute([$ticket_id, $user_id, $reply_message]);

                // Update ticket status
                $stmt = $db->prepare("
                    UPDATE support_tickets 
                    SET status = 'awaiting_support', updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$ticket_id]);

                $db->commit();

                $_SESSION['success_message'] = "Your reply has been submitted successfully!";
                header("Location: support.php?view=$ticket_id");
                exit;
            } else {
                $error = "Invalid ticket or you don't have permission to reply to this ticket.";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Error submitting reply: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';

// Base SQL query for tickets
$sql = "
    SELECT st.*, 
           COUNT(sm.id) as message_count,
           u.first_name as assigned_first_name, 
           u.last_name as assigned_last_name
    FROM support_tickets st
    LEFT JOIN support_messages sm ON st.id = sm.ticket_id
    LEFT JOIN users u ON st.assigned_to = u.id
    WHERE st.user_id = ?
";

// Add status filter
if ($status_filter !== 'all') {
    $sql .= " AND st.status = ?";
}

// Add search filter
if (!empty($search_query)) {
    $sql .= " AND (st.subject LIKE ? OR st.description LIKE ? OR st.ticket_number LIKE ?)";
}

// Group by ticket ID
$sql .= " GROUP BY st.id";

// Add sorting
switch ($sort_by) {
    case 'date_asc':
        $sql .= " ORDER BY st.created_at ASC";
        break;
    case 'date_desc':
        $sql .= " ORDER BY st.created_at DESC";
        break;
    case 'priority_asc':
        $sql .= " ORDER BY FIELD(st.priority, 'low', 'medium', 'high', 'urgent')";
        break;
    case 'priority_desc':
        $sql .= " ORDER BY FIELD(st.priority, 'urgent', 'high', 'medium', 'low')";
        break;
    default: // created_at_desc
        $sql .= " ORDER BY st.created_at DESC";
        break;
}

// Prepare and execute the query
$stmt = $db->prepare($sql);

if ($status_filter !== 'all' && !empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->execute([$user_id, $status_filter, $search_param, $search_param, $search_param]);
} elseif ($status_filter !== 'all') {
    $stmt->execute([$user_id, $status_filter]);
} elseif (!empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->execute([$user_id, $search_param, $search_param, $search_param]);
} else {
    $stmt->execute([$user_id]);
}

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ticket statistics for filter badges
$stats = $db->prepare("
    SELECT 
        COUNT(*) AS total,
        COUNT(CASE WHEN status = 'open' THEN 1 END) AS open,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) AS in_progress,
        COUNT(CASE WHEN status = 'awaiting_support' THEN 1 END) AS awaiting_support,
        COUNT(CASE WHEN status = 'awaiting_customer' THEN 1 END) AS awaiting_customer,
        COUNT(CASE WHEN status = 'closed' THEN 1 END) AS closed
    FROM support_tickets 
    WHERE user_id = ?
");
$stats->execute([$user_id]);
$ticket_stats = $stats->fetch(PDO::FETCH_ASSOC);

// Get single ticket details if viewing
$view_ticket = null;
$ticket_messages = [];
if (isset($_GET['view'])) {
    $ticket_id = (int) $_GET['view'];

    // Get ticket details
    $stmt = $db->prepare("
        SELECT st.*, 
               u.first_name as assigned_first_name, 
               u.last_name as assigned_last_name
        FROM support_tickets st
        LEFT JOIN users u ON st.assigned_to = u.id
        WHERE st.id = ? AND st.user_id = ?
    ");
    $stmt->execute([$ticket_id, $user_id]);
    $view_ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($view_ticket) {
        // Get ticket messages
        $stmt = $db->prepare("
            SELECT sm.*, 
                   u.first_name, u.last_name, u.profile_image,
                   CASE WHEN u.id IS NULL THEN 'System' ELSE CONCAT(u.first_name, ' ', u.last_name) END as author_name
            FROM support_messages sm
            LEFT JOIN users u ON sm.user_id = u.id
            WHERE sm.ticket_id = ?
            ORDER BY sm.created_at ASC
        ");
        $stmt->execute([$ticket_id]);
        $ticket_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Set page title
$page_title = 'Support Center - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/client-portal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

</head>

<body>
    <div class="client-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <div class="page-header">
                    <h1>Support Center</h1>
                    <div class="page-actions">
                        <a href="#new-ticket" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Ticket
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($view_ticket): ?>
                    <!-- Single Ticket View -->
                    <div class="ticket-view">
                        <div class="ticket-view-header">
                            <h2 class="ticket-view-title">
                                Ticket #<?php echo $view_ticket['ticket_number']; ?>:
                                <?php echo htmlspecialchars($view_ticket['subject']); ?>
                            </h2>
                            <span class="ticket-status <?php echo $view_ticket['status']; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $view_ticket['status'])); ?>
                            </span>
                        </div>

                        <div class="ticket-view-meta">
                            <div>
                                <strong>Category:</strong>
                                <?php echo htmlspecialchars(ucfirst($view_ticket['category'])); ?>
                            </div>
                            <div>
                                <strong>Priority:</strong>
                                <span class="ticket-priority <?php echo $view_ticket['priority']; ?>">
                                    <?php echo ucfirst($view_ticket['priority']); ?>
                                </span>
                            </div>
                            <div>
                                <strong>Created:</strong>
                                <?php echo date('M j, Y g:i A', strtotime($view_ticket['created_at'])); ?>
                            </div>
                            <?php if ($view_ticket['assigned_first_name']): ?>
                                <div>
                                    <strong>Assigned To:</strong>
                                    <?php echo htmlspecialchars($view_ticket['assigned_first_name'] . ' ' . $view_ticket['assigned_last_name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="ticket-view-description">
                            <h4>Original Message</h4>
                            <p><?php echo nl2br(htmlspecialchars($view_ticket['description'])); ?></p>
                        </div>

                        <!-- Messages -->
                        <div class="messages-container">
                            <h3>Conversation</h3>

                            <div class="messages-list">
                                <?php foreach ($ticket_messages as $message): ?>
                                    <div class="message-item">
                                        <div class="message-avatar">
                                            <?php if (!empty($message['profile_image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($message['profile_image']); ?>"
                                                    alt="Profile">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="message-content">
                                            <div class="message-header">
                                                <div>
                                                    <span
                                                        class="message-author"><?php echo htmlspecialchars($message['author_name']); ?></span>
                                                    <?php if ($message['is_internal']): ?>
                                                        <span class="message-internal">Internal Note</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="message-date">
                                                    <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                                </div>
                                            </div>

                                            <div class="message-text">
                                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($view_ticket['status'] !== 'closed'): ?>
                                <!-- Reply Form -->
                                <div class="reply-form">
                                    <h3>Reply to Ticket</h3>

                                    <form method="POST">
                                        <input type="hidden" name="ticket_id" value="<?php echo $view_ticket['id']; ?>">

                                        <div class="form-group">
                                            <label for="reply_message">Your Message</label>
                                            <textarea id="reply_message" name="reply_message" class="form-control"
                                                required></textarea>
                                        </div>

                                        <div class="form-actions">
                                            <a href="support.php" class="btn btn-outline">Back to Tickets</a>
                                            <button type="submit" name="submit_reply" class="btn btn-primary">
                                                <i class="fas fa-reply"></i> Send Reply
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    This ticket is closed. If you need further assistance, please create a new ticket.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <a href="support.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to All Tickets
                    </a>
                <?php else: ?>
                    <!-- New Ticket Form -->
                    <div class="new-ticket-form" id="new-ticket">
                        <h2>Create New Support Ticket</h2>

                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="subject">Subject</label>
                                    <input type="text" id="subject" name="subject" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="category">Category</label>
                                    <select id="category" name="category" class="form-control" required>
                                        <option value="">Select Category</option>
                                        <option value="booking">Booking Inquiry</option>
                                        <option value="payment">Payment Issue</option>
                                        <option value="document">Document Request</option>
                                        <option value="technical">Technical Problem</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="priority">Priority</label>
                                    <select id="priority" name="priority" class="form-control" required>
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" class="form-control" required></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="submit_ticket" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Submit Ticket
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tickets List -->
                    <div class="support-container">
                        <div class="ticket-filters">
                            <div class="status-filters">
                                <a href="?status=all"
                                    class="status-badge <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                                    All <span class="badge-count"><?php echo $ticket_stats['total']; ?></span>
                                </a>
                                <a href="?status=open"
                                    class="status-badge <?php echo $status_filter === 'open' ? 'active' : ''; ?>">
                                    Open <span class="badge-count"><?php echo $ticket_stats['open']; ?></span>
                                </a>
                                <a href="?status=in_progress"
                                    class="status-badge <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">
                                    In Progress <span class="badge-count"><?php echo $ticket_stats['in_progress']; ?></span>
                                </a>
                                <a href="?status=awaiting_customer"
                                    class="status-badge <?php echo $status_filter === 'awaiting_customer' ? 'active' : ''; ?>">
                                    Your Reply Needed <span
                                        class="badge-count"><?php echo $ticket_stats['awaiting_customer']; ?></span>
                                </a>
                                <a href="?status=closed"
                                    class="status-badge <?php echo $status_filter === 'closed' ? 'active' : ''; ?>">
                                    Closed <span class="badge-count"><?php echo $ticket_stats['closed']; ?></span>
                                </a>
                            </div>

                            <form method="get" class="search-form">
                                <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                                <div class="search-input-group">
                                    <input type="text" name="search" placeholder="Search tickets..."
                                        value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button type="submit" class="search-btn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if (!empty($search_query)): ?>
                                        <a href="?status=<?php echo $status_filter; ?>" class="clear-search">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>

                            <div class="sort-dropdown">
                                <select id="sortSelect" onchange="window.location.href=this.value">
                                    <option
                                        value="?status=<?php echo $status_filter; ?>&sort=created_at_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                        <?php echo $sort_by === 'created_at_desc' ? 'selected' : ''; ?>>
                                        Sort by: Newest First
                                    </option>
                                    <option
                                        value="?status=<?php echo $status_filter; ?>&sort=created_at_asc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                        <?php echo $sort_by === 'created_at_asc' ? 'selected' : ''; ?>>
                                        Sort by: Oldest First
                                    </option>
                                    <option
                                        value="?status=<?php echo $status_filter; ?>&sort=priority_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                        <?php echo $sort_by === 'priority_desc' ? 'selected' : ''; ?>>
                                        Sort by: Priority (High to Low)
                                    </option>
                                    <option
                                        value="?status=<?php echo $status_filter; ?>&sort=priority_asc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                        <?php echo $sort_by === 'priority_asc' ? 'selected' : ''; ?>>
                                        Sort by: Priority (Low to High)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="tickets-list">
                            <?php if (empty($tickets)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-ticket-alt"></i>
                                    <h3>No Tickets Found</h3>
                                    <p>You don't have any support tickets matching your criteria.</p>
                                    <a href="#new-ticket" class="btn btn-primary">Create New Ticket</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <div class="ticket-item">
                                        <div class="ticket-header">
                                            <h3 class="ticket-title">
                                                <a href="?view=<?php echo $ticket['id']; ?>" class="ticket-number">
                                                    #<?php echo $ticket['ticket_number']; ?>
                                                </a>: <?php echo htmlspecialchars($ticket['subject']); ?>
                                            </h3>
                                            <span class="ticket-status <?php echo $ticket['status']; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                            </span>
                                        </div>

                                        <div class="ticket-meta">
                                            <div>
                                                <strong>Category:</strong>
                                                <?php echo htmlspecialchars(ucfirst($ticket['category'])); ?>
                                            </div>
                                            <div>
                                                <strong>Priority:</strong>
                                                <span class="ticket-priority <?php echo $ticket['priority']; ?>">
                                                    <?php echo ucfirst($ticket['priority']); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <strong>Created:</strong>
                                                <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                                            </div>
                                        </div>

                                        <div class="ticket-excerpt">
                                            <?php echo htmlspecialchars(substr($ticket['description'], 0, 200)); ?>...
                                        </div>

                                        <div class="ticket-footer">
                                            <div class="ticket-message-count">
                                                <i class="fas fa-comments"></i> <?php echo $ticket['message_count']; ?> messages
                                            </div>
                                            <a href="?view=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/client-portal.js"></script>
    <script>
        // Update page title in header
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('pageTitle').textContent = 'Support Center';

            // Highlight active status filter on back/forward navigation
            const statusFilter = '<?php echo $status_filter; ?>';
            if (statusFilter !== 'all') {
                const activeFilter = document.querySelector(`.status-filters a[href*="status=${statusFilter}"]`);
                if (activeFilter) {
                    document.querySelectorAll('.status-filters a').forEach(link => {
                        link.classList.remove('active');
                    });
                    activeFilter.classList.add('active');
                }
            }

            // Scroll to new ticket form if hash is present
            if (window.location.hash === '#new-ticket') {
                document.getElementById('new-ticket').scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    </script>
</body>

</html>