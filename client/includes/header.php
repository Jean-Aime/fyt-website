<header class="client-header">
    <div class="header-content">
        <div class="header-left">
            <button class="mobile-sidebar-toggle" id="mobileSidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title" id="pageTitle">Dashboard</h1>
        </div>

        <div class="header-right">
            <div class="header-search">
                <input type="text" placeholder="Search bookings, tours..." class="search-input">
                <button class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>

            <div class="header-notifications">
                <button class="notification-btn" id="notificationBtn">
                    <i class="fas fa-bell"></i>
                    <?php
                    $unread_count = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL");
                    $unread_count->execute([$_SESSION['user_id']]);
                    $unread = $unread_count->fetchColumn();
                    if ($unread > 0):
                        ?>
                        <span class="notification-badge"><?php echo $unread; ?></span>
                    <?php endif; ?>
                </button>

                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="dropdown-header">
                        <h3>Notifications</h3>
                        <a href="notifications.php">View All</a>
                    </div>
                    <div class="dropdown-content" id="notificationContent">
                        <!-- Notifications loaded via AJAX -->
                    </div>
                </div>
            </div>

            <div class="header-profile">
                <div class="profile-dropdown">
                    <button class="profile-btn" id="profileBtn">
                        <?php if (!empty($_SESSION['profile_image'])): ?>
                            <img src="../<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Profile">
                        <?php else: ?>
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>

                    <div class="profile-menu" id="profileMenu">
                        <a href="profile.php" class="menu-item">
                            <i class="fas fa-user"></i> Profile Settings
                        </a>
                        <a href="security.php" class="menu-item">
                            <i class="fas fa-shield-alt"></i> Security
                        </a>
                        <a href="preferences.php" class="menu-item">
                            <i class="fas fa-cog"></i> Preferences
                        </a>
                        <div class="menu-divider"></div>
                        <a href="../client/logout.php" class="menu-item logout">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    // Header functionality
    document.addEventListener('DOMContentLoaded', function () {
        // Mobile sidebar toggle
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebar = document.getElementById('sidebar');

        mobileSidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
        });

        // Notification dropdown
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');

        notificationBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            loadNotifications();
        });

        // Profile dropdown
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');

        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            profileMenu.classList.toggle('show');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function () {
            notificationDropdown.classList.remove('show');
            profileMenu.classList.remove('show');
        });

        // Load notifications
        function loadNotifications() {
            fetch('../api/get-notifications.php')
                .then(response => response.json())
                .then(data => {
                    const content = document.getElementById('notificationContent');
                    if (data.notifications && data.notifications.length > 0) {
                        content.innerHTML = data.notifications.map(notification => `
                        <div class="notification-item ${notification.read_at ? 'read' : 'unread'}">
                            <div class="notification-content">
                                <h4>${notification.title}</h4>
                                <p>${notification.message}</p>
                                <span class="notification-time">${notification.time_ago}</span>
                            </div>
                        </div>
                    `).join('');
                    } else {
                        content.innerHTML = '<div class="no-notifications">No new notifications</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                });
        }
    });
</script>
