<?php
// Get current user info
$current_user = getCurrentUser();
?>

<header class="mca-header">
    <div class="header-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="breadcrumb">
            <span class="breadcrumb-item">MCA Portal</span>
            <?php
            $page = basename($_SERVER['PHP_SELF'], '.php');
            $page_names = [
                'dashboard' => 'Dashboard',
                'advisors' => 'My Advisors',
                'recruit' => 'Recruit Advisor',
                'bookings' => 'Bookings',
                'commissions' => 'Commissions',
                'training' => 'Training Center',
                'reports' => 'Reports',
                'profile' => 'Profile'
            ];
            if (isset($page_names[$page])):
            ?>
            <i class="fas fa-chevron-right"></i>
            <span class="breadcrumb-item active"><?php echo $page_names[$page]; ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="header-right">
        <!-- Quick Actions -->
        <div class="header-actions">
            <a href="../book.php?agent=<?php echo $current_user['id']; ?>" class="quick-action-btn">
                <i class="fas fa-plus"></i>
                <span>New Booking</span>
            </a>

            <div class="header-notifications">
                <button class="notification-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <div class="notifications-dropdown" id="notificationsDropdown">
                    <div class="notifications-header">
                        <h4>Notifications</h4>
                        <button class="mark-all-read">Mark all as read</button>
                    </div>
                    <div class="notifications-list">
                        <div class="notification-item unread">
                            <div class="notification-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="notification-content">
                                <p>New advisor application received</p>
                                <small>2 hours ago</small>
                            </div>
                        </div>
                        <div class="notification-item unread">
                            <div class="notification-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="notification-content">
                                <p>Commission payment processed</p>
                                <small>1 day ago</small>
                            </div>
                        </div>
                        <div class="notification-item">
                            <div class="notification-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="notification-content">
                                <p>New booking confirmed</p>
                                <small>2 days ago</small>
                            </div>
                        </div>
                    </div>
                    <div class="notifications-footer">
                        <a href="notifications.php">View all notifications</a>
                    </div>
                </div>
            </div>

            <div class="header-user">
                <button class="user-menu-btn" onclick="toggleUserMenu()">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'M', 0, 1)); ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'MCA'); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="user-menu-dropdown" id="userMenuDropdown">
                    <a href="profile.php" class="user-menu-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="settings.php" class="user-menu-item">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <div class="user-menu-divider"></div>
                    <a href="logout.php" class="user-menu-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
    .mca-header {
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

    .sidebar-toggle {
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

    .sidebar-toggle:hover {
        background: #f8f9fa;
        color: #333;
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9em;
        color: #333;
    }

    .breadcrumb-item {
        color: #666;
    }

    .breadcrumb-item.active {
        font-weight: bold;
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

    .quick-action-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #228B22, #006400);
        color: white;
        padding: 10px 15px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .quick-action-btn:hover {
        background: linear-gradient(135deg, #006400, #228B22);
        transform: translateY(-2px);
        color: white;
    }

    .header-notifications,
    .header-user {
        position: relative;
    }

    .notification-btn,
    .user-menu-btn {
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

    .notification-btn:hover,
    .user-menu-btn:hover {
        background: #f8f9fa;
    }

    .notification-btn {
        position: relative;
        font-size: 1.2em;
        color: #666;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #228B22;
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
        background: linear-gradient(135deg, #D4AF37, #B8941F);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9em;
    }

    .user-name {
        font-size: 0.9em;
        color: #333;
        font-weight: 500;
        display: block;
    }

    .notifications-dropdown,
    .user-menu-dropdown {
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

    .notifications-dropdown.show,
    .user-menu-dropdown.show {
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

    .notifications-header {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notifications-header h4 {
        margin: 0;
        font-size: 1.1em;
        color: #333;
    }

    .notifications-header .mark-all-read {
        background: none;
        border: none;
        color: #D4AF37;
        font-size: 0.9em;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .notifications-header .mark-all-read:hover {
        text-decoration: underline;
    }

    .notifications-list {
        padding: 10px 0;
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
        border-left: 3px solid #D4AF37;
    }

    .notification-icon {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: #e3f2fd;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #D4AF37;
        flex-shrink: 0;
    }

    .notification-content p {
        font-size: 0.8em;
        color: #666;
        margin: 0 0 2px 0;
    }

    .notification-content small {
        color: #999;
        font-size: 0.7em;
    }

    .user-menu-dropdown {
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
        color: #D4AF37;
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

    .notifications-footer {
        padding: 15px 20px;
        text-align: right;
    }

    .notifications-footer a {
        color: #D4AF37;
        text-decoration: none;
        font-size: 0.9em;
        font-weight: 500;
    }

    /* Mobile Styles */
    @media (max-width: 768px) {
        .mca-header {
            padding: 0 15px;
        }

        .sidebar-toggle {
            display: block;
        }

        .breadcrumb {
            min-width: 200px;
        }

        .breadcrumb-item {
            font-size: 0.8em;
        }

        .header-actions {
            gap: 10px;
        }

        .user-name {
            display: none;
        }

        .quick-action-btn span {
            display: none;
        }
    }
</style>

<script>
    function toggleSidebar() {
        document.querySelector('.mca-wrapper').classList.toggle('sidebar-collapsed');
    }

    function toggleNotifications() {
        const dropdown = document.getElementById('notificationsDropdown');
        dropdown.classList.toggle('show');
    }

    function toggleUserMenu() {
        const dropdown = document.getElementById('userMenuDropdown');
        dropdown.classList.toggle('show');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.header-notifications')) {
            document.getElementById('notificationsDropdown').classList.remove('show');
        }
        if (!event.target.closest('.header-user')) {
            document.getElementById('userMenuDropdown').classList.remove('show');
        }
    });
</script>
