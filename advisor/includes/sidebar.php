<?php
// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Get user info
$current_user = getCurrentUser();
$first_name = isset($current_user['first_name']) ? $current_user['first_name'] : 'Advisor';
$last_name = isset($current_user['last_name']) ? $current_user['last_name'] : '';
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="logo">
            <img src="../assets/images/logo.png" alt="FYT Logo" style="height: 40px; margin-right: 10px;">
            <span class="logo-text">Advisor Portal</span>
        </a>
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?php echo strtoupper(substr($first_name, 0, 1)); ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></div>
            <div class="user-role">Certified Advisor</div>
        </div>
    </div>

    <div class="sidebar-content">
        <nav class="sidebar-nav">
            <ul class="nav-menu">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="dashboard.php"
                        class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <!-- My Bookings -->
                <li class="nav-item">
                    <a href="bookings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'bookings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span class="nav-text">My Bookings</span>
                    </a>
                </li>

                <!-- My Clients -->
                <li class="nav-item">
                    <a href="clients.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'clients.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">My Clients</span>
                    </a>
                </li>

                <!-- Commissions -->
                <li class="nav-item">
                    <a href="commissions.php"
                        class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'commissions.php' ? 'active' : ''; ?>">
                        <i class="fas fa-dollar-sign"></i>
                        <span class="nav-text">Commissions</span>
                    </a>
                </li>

                <!-- Training -->
                <li class="nav-item">
                    <a href="training.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'training.php' ? 'active' : ''; ?>">
                        <i class="fas fa-graduation-cap"></i>
                        <span class="nav-text">Training</span>
                    </a>
                </li>

                <!-- Marketing Materials -->
                <li class="nav-item">
                    <a href="marketing.php"
                        class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'marketing.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bullhorn"></i>
                        <span class="nav-text">Marketing</span>
                    </a>
                </li>

                <!-- Reports -->
                <li class="nav-item">
                    <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </li>

                <!-- Profile -->
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog"></i>
                        <span class="nav-text">Profile</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <a href="../book.php" class="nav-link">
                <i class="fas fa-plus"></i>
                <span class="nav-text">New Booking</span>
            </a>

            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</aside>

<style>
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        background: linear-gradient(to bottom, #228B22, #006400);
        color: white;
        z-index: 1000;
        transition: all 0.3s ease;
        overflow-y: auto;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }

    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(0, 0, 0, 0.2);
    }

    .logo {
        display: flex;
        align-items: center;
        font-size: 1.2em;
        font-weight: 700;
        color: white;
        text-decoration: none;
    }

    .logo-text {
        font-size: 1.1em;
    }

    .sidebar-toggle {
        background: none;
        border: none;
        color: white;
        font-size: 1.2em;
        cursor: pointer;
        padding: 8px;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .sidebar-toggle:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar-user {
        padding: 0 20px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 20px;
        text-align: center;
    }

    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #32CD32, #90EE90);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        overflow: hidden;
        border: 3px solid #32CD32;
        font-size: 1.5em;
        font-weight: bold;
        color: white;
    }

    .user-info {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .user-name {
        font-size: 1.1em;
        margin-bottom: 5px;
        color: white;
    }

    .user-role {
        font-size: 0.9em;
        opacity: 0.8;
        color: #90EE90;
        font-weight: 500;
    }

    .nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .nav-item {
        margin-bottom: 2px;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 20px;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        font-weight: 500;
    }

    .nav-link:hover {
        background: rgba(50, 205, 50, 0.2);
        color: #90EE90;
        transform: translateX(5px);
    }

    .nav-link.active {
        background: linear-gradient(90deg, #32CD32, transparent);
        color: white;
        border-right: 4px solid #32CD32;
    }

    .nav-link i {
        width: 20px;
        text-align: center;
        font-size: 1.1em;
    }

    .sidebar-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .logout-btn {
        color: rgba(255, 255, 255, 0.6);
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 20px;
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        font-weight: 500;
    }

    .logout-btn:hover {
        background: rgba(220, 53, 69, 0.2);
        color: #ff6b6b;
    }

    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.mobile-open {
            transform: translateX(0);
        }
    }
</style>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
    }

    // Mobile sidebar toggle
    function toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('mobile-open');
    }
</script>
