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

// Mark notifications as read if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_read'])) {
    try {
        $stmt = $db->prepare("
            UPDATE notifications 
            SET read_at = NOW() 
            WHERE user_id = ? AND read_at IS NULL
        ");
        $stmt->execute([$user_id]);

        $_SESSION['success_message'] = "All notifications marked as read";
        header('Location: notifications.php');
        exit;
    } catch (PDOException $e) {
        $error = "Error updating notifications: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';

// Base SQL query
$sql = "
    SELECT n.*
    FROM notifications n
    WHERE n.user_id = ?
";

// Add status filter
if ($status_filter === 'unread') {
    $sql .= " AND n.read_at IS NULL";
} elseif ($status_filter === 'read') {
    $sql .= " AND n.read_at IS NOT NULL";
}

// Add type filter
if ($type_filter !== 'all') {
    $sql .= " AND n.type = ?";
}

// Add search filter
if (!empty($search_query)) {
    $sql .= " AND (n.title LIKE ? OR n.message LIKE ?)";
}

// Add sorting
switch ($sort_by) {
    case 'date_asc':
        $sql .= " ORDER BY n.created_at ASC";
        break;
    case 'date_desc':
        $sql .= " ORDER BY n.created_at DESC";
        break;
    default: // created_at_desc
        $sql .= " ORDER BY n.created_at DESC";
        break;
}

// Prepare and execute the query
$stmt = $db->prepare($sql);

if ($type_filter !== 'all' && !empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->execute([$user_id, $type_filter, $search_param, $search_param]);
} elseif ($type_filter !== 'all') {
    $stmt->execute([$user_id, $type_filter]);
} elseif (!empty($search_query)) {
    $search_param = "%$search_query%";
    $stmt->execute([$user_id, $search_param, $search_param]);
} else {
    $stmt->execute([$user_id]);
}

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notification statistics for filter badges
$stats = $db->prepare("
    SELECT 
        COUNT(*) AS total,
        COUNT(CASE WHEN read_at IS NULL THEN 1 END) AS unread,
        COUNT(CASE WHEN type = 'booking' THEN 1 END) AS booking,
        COUNT(CASE WHEN type = 'payment' THEN 1 END) AS payment,
        COUNT(CASE WHEN type = 'support' THEN 1 END) AS support,
        COUNT(CASE WHEN type = 'system' THEN 1 END) AS system
    FROM notifications 
    WHERE user_id = ?
");
$stats->execute([$user_id]);
$notification_stats = $stats->fetch(PDO::FETCH_ASSOC);

// Mark notifications as read when viewing the page
if (!empty($notifications)) {
    $unread_ids = array_filter($notifications, function ($n) {
        return $n['read_at'] === null;
    });
    $unread_ids = array_column($unread_ids, 'id');

    if (!empty($unread_ids)) {
        $placeholders = implode(',', array_fill(0, count($unread_ids), '?'));
        $stmt = $db->prepare("
            UPDATE notifications 
            SET read_at = NOW() 
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($unread_ids);
    }
}

// Set page title
$page_title = 'Notifications - Forever Young Tours';
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
                    <h1>Notifications</h1>
                    <div class="page-actions">
                        <form method="post" style="display: inline;">
                            <button type="submit" name="mark_as_read" class="btn btn-outline">
                                <i class="fas fa-check-circle"></i> Mark All as Read
                            </button>
                        </form>
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

                <div class="notifications-container">
                    <div class="notification-filters">
                        <div class="status-filters">
                            <a href="?status=all"
                                class="status-badge <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                                All <span class="badge-count"><?php echo $notification_stats['total']; ?></span>
                            </a>
                            <a href="?status=unread"
                                class="status-badge <?php echo $status_filter === 'unread' ? 'active' : ''; ?>">
                                Unread <span class="badge-count"><?php echo $notification_stats['unread']; ?></span>
                            </a>
                            <a href="?status=read"
                                class="status-badge <?php echo $status_filter === 'read' ? 'active' : ''; ?>">
                                Read <span
                                    class="badge-count"><?php echo $notification_stats['total'] - $notification_stats['unread']; ?></span>
                            </a>
                        </div>

                        <div class="type-filters">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'all'])); ?>"
                                class="type-badge <?php echo $type_filter === 'all' ? 'active' : ''; ?>">
                                All Types
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'booking'])); ?>"
                                class="type-badge <?php echo $type_filter === 'booking' ? 'active' : ''; ?>">
                                Bookings <span class="badge-count"><?php echo $notification_stats['booking']; ?></span>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'payment'])); ?>"
                                class="type-badge <?php echo $type_filter === 'payment' ? 'active' : ''; ?>">
                                Payments <span class="badge-count"><?php echo $notification_stats['payment']; ?></span>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'support'])); ?>"
                                class="type-badge <?php echo $type_filter === 'support' ? 'active' : ''; ?>">
                                Support <span class="badge-count"><?php echo $notification_stats['support']; ?></span>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'system'])); ?>"
                                class="type-badge <?php echo $type_filter === 'system' ? 'active' : ''; ?>">
                                System <span class="badge-count"><?php echo $notification_stats['system']; ?></span>
                            </a>
                        </div>

                        <form method="get" class="search-form">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <input type="hidden" name="type" value="<?php echo $type_filter; ?>">
                            <div class="search-input-group">
                                <input type="text" name="search" placeholder="Search notifications..."
                                    value="<?php echo htmlspecialchars($search_query); ?>">
                                <button type="submit" class="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="?status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>"
                                        class="clear-search">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="sort-dropdown">
                            <select id="sortSelect" onchange="window.location.href=this.value">
                                <option
                                    value="?status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&sort=created_at_desc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'created_at_desc' ? 'selected' : ''; ?>>
                                    Sort by: Newest First
                                </option>
                                <option
                                    value="?status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&sort=created_at_asc<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>"
                                    <?php echo $sort_by === 'created_at_asc' ? 'selected' : ''; ?>>
                                    Sort by: Oldest First
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="notifications-list">
                        <?php if (empty($notifications)): ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <h3>No Notifications Found</h3>
                                <p>You don't have any notifications matching your criteria.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['read_at'] ? 'read' : 'unread'; ?>">
                                    <div class="notification-header">
                                        <h3 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?>
                                        </h3>
                                        <span class="notification-type <?php echo $notification['type']; ?>">
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </div>

                                    <div class="notification-message">
                                        <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                    </div>

                                    <div class="notification-meta">
                                        <div class="notification-date">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                            <?php if ($notification['read_at']): ?>
                                                • <span style="color: var(--success-color);">Read</span>
                                            <?php else: ?>
                                                • <span style="color: var(--primary-color);">New</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!$notification['read_at']): ?>
                                            <div class="notification-actions">
                                                <button class="mark-read-btn"
                                                    onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                    <i class="fas fa-check"></i> Mark as read
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/client-portal.js"></script>
    <script>
        // Update page title in header
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('pageTitle').textContent = 'Notifications';

            // Highlight active filters on back/forward navigation
            const statusFilter = '<?php echo $status_filter; ?>';
            const typeFilter = '<?php echo $type_filter; ?>';

            if (statusFilter !== 'all') {
                const activeFilter = document.querySelector(`.status-filters a[href*="status=${statusFilter}"]`);
                if (activeFilter) {
                    document.querySelectorAll('.status-filters a').forEach(link => {
                        link.classList.remove('active');
                    });
                    activeFilter.classList.add('active');
                }
            }

            if (typeFilter !== 'all') {
                const activeFilter = document.querySelector(`.type-filters a[href*="type=${typeFilter}"]`);
                if (activeFilter) {
                    document.querySelectorAll('.type-filters a').forEach(link => {
                        link.classList.remove('active');
                    });
                    activeFilter.classList.add('active');
                }
            }
        });

        // Mark notification as read
        function markAsRead(notificationId) {
            fetch('../api/mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${notificationId}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const item = document.querySelector(`.notification-item.unread`);
                        if (item) {
                            item.classList.remove('unread');

                            // Update the read status text
                            const readStatus = item.querySelector('.notification-date span');
                            if (readStatus) {
                                readStatus.textContent = 'Read';
                                readStatus.style.color = 'var(--success-color)';
                            }

                            // Remove the mark as read button
                            const actions = item.querySelector('.notification-actions');
                            if (actions) {
                                actions.remove();
                            }

                            // Update unread count in header
                            const badge = document.querySelector('.notification-badge');
                            if (badge) {
                                const count = parseInt(badge.textContent);
                                if (count > 1) {
                                    badge.textContent = count - 1;
                                } else {
                                    badge.remove();
                                }
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
    </script>
</body>

</html>