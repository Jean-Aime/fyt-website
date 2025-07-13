<?php
// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Get user info
$current_user = getCurrentUser();
$first_name = isset($current_user['first_name']) ? $current_user['first_name'] : 'MCA';
$last_name = isset($current_user['last_name']) ? $current_user['last_name'] : '';
?>

<aside class="mca-sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="logo">
            <img src="../assets/images/logo.png" alt="Forever Young Tours" class="logo-img">
            <span class="logo-text">MCA Portal</span>
        </a>
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-content">
        <div class="sidebar-user">
            <div class="user-avatar">
                <?php echo strtoupper(substr($first_name, 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></div>
                <div class="user-role">Master Certified Advisor</div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="dashboard.php"
                        class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <!-- My Advisors -->
                <li class="nav-item">
                    <a href="advisors.php" class="nav-link <?php echo $current_page === 'advisors' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>My Advisors</span>
                    </a>
                </li>
                
                <!-- Recruit Advisor -->
                <li class="nav-item">
                    <a href="recruit.php" class="nav-link <?php echo $current_page === 'recruit' ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i>
                        <span>Recruit Advisor</span>
                    </a>
                </li>

                <!-- My Bookings -->
                <li class="nav-item">
                    <a href="bookings.php" class="nav-link <?php echo $current_page === 'bookings' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>Bookings</span>
                        <span class="badge">3</span>
                    </a>
                </li>

                <!-- Commissions -->
                <li class="nav-item">
                    <a href="commissions.php"
                        class="nav-link <?php echo $current_page === 'commissions' ? 'active' : ''; ?>">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Commissions</span>
                    </a>
                </li>

                <!-- Training Center -->
                <li class="nav-item">
                    <a href="training.php" class="nav-link <?php echo $current_page === 'training' ? 'active' : ''; ?>">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Training Center</span>
                    </a>
                </li>

                <!-- Reports -->
                <li class="nav-item">
                    <a href="reports.php" class="nav-link <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>

                <!-- Profile -->
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog"></i>
                        <span>Profile</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</aside>

<style>
    .mca-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        background: linear-gradient(to bottom, #D4AF37, #B8941F);
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
        gap: 12px;
        font-size: 1.3em;
        font-weight: 700;
        color: white;
        text-decoration: none;
    }

    .logo-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2em;
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
        background: linear-gradient(135deg, #228B22, #006400);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        overflow: hidden;
        border: 3px solid #228B22;
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
        color: #228B22;
        font-weight: 500;
    }

    .nav-list {
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
        background: rgba(34, 139, 34, 0.2);
        color: #228B22;
        transform: translateX(5px);
    }

    .nav-link.active {
        background: linear-gradient(90deg, #228B22, transparent);
        color: white;
        border-right: 4px solid #228B22;
    }

    .nav-link i {
        width: 20px;
        text-align: center;
        font-size: 1.1em;
    }

    .badge {
        background: #228B22;
        color: white;
        font-size: 0.7em;
        padding: 3px 7px;
        border-radius: 12px;
        margin-left: auto;
        min-width: 18px;
        text-align: center;
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
        .mca-sidebar {
            transform: translateX(-100%);
        }

        .mca-sidebar.mobile-open {
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
