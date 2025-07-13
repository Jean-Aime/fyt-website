<div class="header">
    <div class="header-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="breadcrumb">
            <span class="breadcrumb-item">Advisor Portal</span>
            <?php
            $page = basename($_SERVER['PHP_SELF'], '.php');
            $page_names = [
                'dashboard' => 'Dashboard',
                'bookings' => 'My Bookings',
                'clients' => 'My Clients',
                'commissions' => 'Commissions',
                'training' => 'Training',
                'marketing' => 'Marketing',
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
        <div class="header-notifications">
            <button class="notification-btn" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">2</span>
            </button>
            <div class="notifications-dropdown" id="notificationsDropdown">
                <div class="notifications-header">
                    <h4>Notifications</h4>
                    <button class="mark-all-read">Mark all as read</button>
                </div>
                <div class="notifications-list">
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
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Advisor'); ?></span>
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

<script>
function toggleSidebar() {
    document.querySelector('.client-wrapper').classList.toggle('sidebar-collapsed');
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
document.addEventListener('click', function(event) {
    if (!event.target.closest('.header-notifications')) {
        document.getElementById('notificationsDropdown').classList.remove('show');
    }
    if (!event.target.closest('.header-user')) {
        document.getElementById('userMenuDropdown').classList.remove('show');
    }
});
</script>
