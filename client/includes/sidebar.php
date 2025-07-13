<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="/webtour/assets/images/logo.png" alt="FYT Logo" style="height: 60px; margin-right: 5px;">
        </div>

        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-content">
        <div class="user-info">
            <div class="user-avatar">
                <?php if (!empty($_SESSION['profile_image'])): ?>
                    <img src="../<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Profile">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($_SESSION['user_name']); ?></h4>
                <span><?php echo htmlspecialchars($_SESSION['user_email'])   // ✅ correct key
                ; ?></span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <ul class="nav-menu">
                <li>
                    <a href="dashboard.php"
                        class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="bookings.php" class="nav-link <?php echo $current_page === 'bookings' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>My Bookings</span>
                    </a>
                </li>

                <li>
                    <a href="payments.php" class="nav-link <?php echo $current_page === 'payments' ? 'active' : ''; ?>">
                        <i class="fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                </li>

                <li>
                    <a href="documents.php"
                        class="nav-link <?php echo $current_page === 'documents' ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>Documents</span>
                    </a>
                </li>

                <li>
                    <a href="support.php" class="nav-link <?php echo $current_page === 'support' ? 'active' : ''; ?>">
                        <i class="fas fa-headset"></i>
                        <span>Support</span>
                    </a>
                </li>

                <li>
                    <a href="notifications.php"
                        class="nav-link <?php echo $current_page === 'notifications' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                        <?php
                        $unread_count = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL");
                        $unread_count->execute([$_SESSION['user_id']]);
                        $unread = $unread_count->fetchColumn();
                        if ($unread > 0):
                            ?>
                            <span class="badge"><?php echo $unread; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li>
                    <a href="profile.php" class="nav-link <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog"></i>
                        <span>Profile Settings</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <a href="../index.php" class="nav-link">
                <i class="fas fa-globe"></i>
                <span>Visit Website</span>
            </a>

            <a href="../book.php" class="nav-link">
                <i class="fas fa-plus"></i>
                <span>New Booking</span>
            </a>

            <a href="../admin/logout.php" class="nav-link logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>
