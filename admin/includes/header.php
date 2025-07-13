<?php

if (basename($_SERVER['PHP_SELF']) === 'view.php' || basename($_SERVER['PHP_SELF']) === 'edit.php'): ?>
    <link rel="stylesheet" href="/webtour/assets/css/user-management.css">
<?php endif;
// Get current user info
$current_user = getCurrentUser();

// Get unread notifications count
$stmt = $db->prepare("
SELECT COUNT(*)
FROM notifications
WHERE user_id = ? AND is_read = 0
");
$stmt->execute([$current_user['id']]);
$unread_notifications = $stmt->fetchColumn();

// Get recent notifications
$stmt = $db->prepare("
SELECT *
FROM notifications
WHERE user_id = ?
ORDER BY created_at DESC
LIMIT 5
");
$stmt->execute([$current_user['id']]);
$recent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<header class="admin-header">
    <div class="header-left">
        <button class="mobile-menu-toggle" onclick="toggleMobileSidebar()">
            <i class="fas fa-bars"></i>
        </button>

        <div class="header-search">
            <form action="../search.php" method="GET" class="search-form">
                <input type="text" name="q" placeholder="Search tours, bookings, customers..." class="search-input"
                    value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                <button type="submit" class="search-button">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>

    <div class="header-right">
        <!-- Quick Actions -->
        <div class="header-actions">
            <div class="notification-dropdown">
                <button class="notification-button" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="badge notification-badge"><?php echo $unread_notifications; ?></span>
                    <?php endif; ?>
                </button>
                <div class="notification-list" id="notificationsMenu">
                    <?php if (empty($recent_notifications)): ?>
                        <div class="no-notifications">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notifications</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_notifications as $notification): ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                <i class="fas fa-<?php echo $notification['icon'] ?? 'info-circle'; ?>"></i>
                                <div class="notification-content">
                                    <span
                                        class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></span>
                                    <small class="notification-time"><?php echo timeAgo($notification['created_at']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="notification-footer">
                        <a href="../notifications.php">View All Notifications</a>
                    </div>
                </div>
            </div>

            <div class="user-dropdown">
                <button class="user-button" onclick="toggleUserMenu()">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($current_user['first_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <span
                            class="user-name"><?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></span>
                        <small
                            class="user-role"><?php echo htmlspecialchars($current_user['role_display'] ?? 'User'); ?></small>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="user-menu" id="userMenu">
                    <a href="../profile.php" class="user-menu-item">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="../settings/general.php" class="user-menu-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="../help.php" class="user-menu-item">
                        <i class="fas fa-question-circle"></i>
                        <span>Help & Support</span>
                    </a>
                    <div class="user-menu-divider"></div>
                    <a href="../logout.php" class="user-menu-item logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Sign Out</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileSidebar()"></div>

<style>
    .admin-header {
        height: 70px;
        background: white;
        border-bottom: 1px solid #e3e6f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 30px;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        font-size: 1.2em;
        color: #666;
        cursor: pointer;
        padding: 8px;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .mobile-menu-toggle:hover {
        background: #f8f9fa;
        color: #333;
    }

    .header-search {
        display: flex;
        align-items: center;
        background: #f8f9fa;
        border: 1px solid #ddd;
        border-radius: 25px;
        overflow: hidden;
        min-width: 350px;
    }

    .search-form {
        display: flex;
        width: 100%;
    }

    .search-input {
        flex: 1;
        padding: 10px 15px;
        border: none;
        font-size: 0.9em;
        outline: none;
        background: transparent;
        transition: all 0.3s ease;
    }

    .search-input:focus {
        background: white;
    }

    .search-button {
        background: #3498db;
        border: none;
        padding: 10px 15px;
        cursor: pointer;
        color: white;
        transition: all 0.3s ease;
    }

    .search-button:hover {
        background: #2980b9;
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .notification-dropdown,
    .user-dropdown {
        position: relative;
    }

    .notification-button,
    .user-button {
        background: none;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .notification-button:hover,
    .user-button:hover {
        background: #f8f9fa;
    }

    .notification-button {
        position: relative;
        font-size: 1.2em;
        color: #666;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #e74c3c;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.7em;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    .user-avatar {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9em;
    }

    .user-info {
        text-align: left;
    }

    .user-name {
        font-size: 0.9em;
        color: #333;
        font-weight: 500;
        display: block;
    }

    .user-role {
        font-size: 0.8em;
        color: #666;
    }

    .notification-list,
    .user-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        min-width: 300px;
        z-index: 1000;
        display: none;
        overflow: hidden;
    }

    .notification-list.show,
    .user-menu.show {
        display: block;
        animation: fadeInDown 0.3s ease;
    }

    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.3s ease;
    }

    .notification-item:hover {
        background: #f8f9fa;
    }

    .notification-item.unread {
        background: #f0f8ff;
        border-left: 3px solid #3498db;
    }

    .notification-item i {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: #e3f2fd;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #3498db;
        flex-shrink: 0;
    }

    .notification-content {
        flex: 1;
    }

    .notification-title {
        font-size: 0.9em;
        color: #333;
        display: block;
        margin-bottom: 2px;
    }

    .notification-time {
        color: #999;
        font-size: 0.7em;
    }

    .notification-footer {
        padding: 15px 20px;
        border-top: 1px solid #eee;
        text-align: center;
    }

    .notification-footer a {
        color: #3498db;
        text-decoration: none;
        font-size: 0.9em;
        font-weight: 500;
    }

    .user-menu {
        padding: 10px 0;
        min-width: 200px;
    }

    .user-menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .user-menu-item:hover {
        background: #f8f9fa;
        color: #3498db;
    }

    .user-menu-item.logout:hover {
        background: #fee;
        color: #e74c3c;
    }

    .user-menu-divider {
        height: 1px;
        background: #eee;
        margin: 8px 0;
    }

    .no-notifications {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    .no-notifications i {
        font-size: 2em;
        margin-bottom: 10px;
        color: #ddd;
    }

    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        display: none;
    }

    .sidebar-overlay.active {
        display: block;
    }

    /* Mobile Styles */
    @media (max-width: 768px) {
        .admin-header {
            padding: 0 15px;
        }

        .mobile-menu-toggle {
            display: block;
        }

        .header-search {
            min-width: 200px;
        }

        .search-input {
            font-size: 0.8em;
        }

        .header-actions {
            gap: 10px;
        }

        .user-info {
            display: none;
        }
    }
</style>

<script>
    function toggleNotifications() {
        const menu = document.getElementById('notificationsMenu');
        const userMenu = document.getElementById('userMenu');

        // Close user menu if open
        userMenu.classList.remove('show');

        // Toggle notifications menu
        menu.classList.toggle('show');
    }

    function toggleUserMenu() {
        const menu = document.getElementById('userMenu');
        const notificationsMenu = document.getElementById('notificationsMenu');

        // Close notifications menu if open
        notificationsMenu.classList.remove('show');

        // Toggle user menu
        menu.classList.toggle('show');
    }

    function toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
    }

    function markAllAsRead() {
        fetch('../api/mark-notifications-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo generateCSRFToken(); ?>'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.notification-dropdown')) {
            document.getElementById('notificationsMenu').classList.remove('show');
        }
        if (!e.target.closest('.user-dropdown')) {
            document.getElementById('userMenu').classList.remove('show');
        }
    });
</script>