<?php
$first_name = $_SESSION['first_name'] ?? '';
$initial = !empty($first_name) ? strtoupper(substr($first_name, 0, 1)) : '?';

$last_name = $_SESSION['last_name'] ?? null;
$role_name = $_SESSION['role_display'] ?? null;


require_once __DIR__ . '/../../includes/secure_auth.php';

// Refresh permissions to be sure
if (isset($_SESSION['user_id'])) {
    $_SESSION['permissions'] = array_column($auth->getUserPermissions($_SESSION['user_id']), 'name');
}

?>

<aside class="admin-sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../dashboard.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-plane"></i>
            </div>
            <span class="logo-text">Forever Young Tours</span>
        </a>
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-content">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($first_name, 0, 1)); ?>
            </div>

            <div class="user-details">
                <h4><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></h4>
                <span><?php echo htmlspecialchars($role_name); ?></span>
            </div>

        </div>

        <nav class="sidebar-nav">
            <ul class="nav-menu">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="/webtour/admin/dashboard.php"
                        class="nav-link <?php echo ($current_page === 'dashboard' && $current_dir === 'admin') ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <!-- Tours Management -->
                <li class="nav-item has-submenu <?php echo $current_dir === 'tours' ? 'active' : ''; ?>">
                    <a href="#" class="nav-link" onclick="toggleSubmenu(this)">
                        <i class="fas fa-map-marked-alt"></i>
                        <span class="nav-text">Tours</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li><a href="/webtour/admin/tours/index.php"
                                class="nav-link <?php echo $current_page === 'index' && $current_dir === 'tours' ? 'active' : ''; ?>">All
                                Tours</a></li>
                        <li><a href="/webtour/admin/tours/add.php"
                                class="nav-link <?php echo $current_page === 'add' && $current_dir === 'tours' ? 'active' : ''; ?>">Add
                                Tour</a></li>
                        <li><a href="/webtour/admin/tours/itinerary.php"
                                class="nav-link <?php echo $current_page === 'itinerary' && $current_dir === 'tours' ? 'active' : ''; ?>">Itinerary
                                Builder</a></li>
                        <li><a href="/webtour/admin/tours/addons.php"
                                class="nav-link <?php echo $current_page === 'addons' && $current_dir === 'tours' ? 'active' : ''; ?>">Tour
                                Add-ons</a></li>
                    </ul>
                </li>

                <!-- Bookings Management -->
                <li class="nav-item has-submenu <?php echo $current_dir === 'bookings' ? 'active' : ''; ?>">
                    <a href="#" class="nav-link" onclick="toggleSubmenu(this)">
                        <i class="fas fa-calendar-check"></i>
                        <span class="nav-text">Bookings</span>
                        <span class="badge">5</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li><a href="/webtour/admin/bookings/index.php"
                                class="nav-link <?php echo $current_page === 'index' && $current_dir === 'bookings' ? 'active' : ''; ?>">All
                                Bookings</a></li>
                        <li><a href="/webtour/admin/bookings/index.php?status=pending" class="nav-link">Pending</a></li>
                        <li><a href="/webtour/admin/bookings/index.php?status=confirmed" class="nav-link">Confirmed</a>
                        </li>
                        <li><a href="/webtour/admin/bookings/index.php?status=cancelled" class="nav-link">Cancelled</a>
                        </li>
                    </ul>
                </li>

                <!-- Destinations Management -->
                <li class="nav-item has-submenu <?php echo $current_dir === 'destinations' ? 'active' : ''; ?>">
                    <a href="#" class="nav-link" onclick="toggleSubmenu(this)">
                        <i class="fas fa-globe-africa"></i>
                        <span class="nav-text">Destinations</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li><a href="/webtour/admin/destinations/index.php"
                                class="nav-link <?php echo $current_page === 'index' && $current_dir === 'destinations' ? 'active' : ''; ?>">All
                                Destinations</a></li>
                        <li><a href="/webtour/admin/destinations/countries.php"
                                class="nav-link <?php echo $current_page === 'countries' && $current_dir === 'destinations' ? 'active' : ''; ?>">Countries</a>
                        </li>
                        <li><a href="/webtour/admin/destinations/regions.php"
                                class="nav-link <?php echo $current_page === 'regions' && $current_dir === 'destinations' ? 'active' : ''; ?>">Regions</a>
                        </li>
                    </ul>
                </li>

                <!-- User Management -->
                <li class="nav-item has-submenu <?php echo $current_dir === 'users' ? 'active' : ''; ?>">
                    <a href="#" class="nav-link" onclick="toggleSubmenu(this)">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Users</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li>
                            <a href="/webtour/admin/users/index.php"
                                class="nav-link <?php echo $current_page === 'index' && $current_dir === 'users' ? 'active' : ''; ?>">
                                All Users</a>
                        </li>
                        <li>
                            <a href="/webtour/admin/users/roles.php"
                                class="nav-link <?php echo $current_page === 'roles' && $current_dir === 'users' ? 'active' : ''; ?>">
                                Roles & Permissions</a>
                        </li>
                    </ul>
                </li>

                <!-- Payments -->
                <li class="nav-item">
                    <a href="/webtour/admin/payments/index.php"
                        class="nav-link <?php echo $current_dir === 'payments' ? 'active' : ''; ?>">
                        <i class="fas fa-credit-card"></i>
                        <span class="nav-text">Payments</span>
                    </a>
                </li>

                <!-- Analytics -->
                <li class="nav-item has-submenu <?php echo $current_dir === 'analytics' ? 'active' : ''; ?>">
                    <a href="#" class="nav-link" onclick="toggleSubmenu(this)">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Analytics</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li><a href="/webtour/admin/analytics/dashboard.php"
                                class="nav-link <?php echo $current_page === 'dashboard' && $current_dir === 'analytics' ? 'active' : ''; ?>">Dashboard</a>
                        </li>
                        <li><a href="/webtour/admin/analytics/reports.php"
                                class="nav-link <?php echo $current_page === 'reports' && $current_dir === 'analytics' ? 'active' : ''; ?>">Reports</a>
                        </li>
                    </ul>
                </li>

                <!-- Content Management -->
                <li class="nav-item has-submenu <?php echo $current_dir === 'content' ? 'active' : ''; ?>">
                    <a href="#" class="nav-link" onclick="toggleSubmenu(this)">
                        <i class="fas fa-edit"></i>
                        <span class="nav-text">Content</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li><a href="/webtour/admin/content/index.php"
                                class="nav-link <?php echo $current_page === 'index' && $current_dir === 'Blog' ? 'active' : ''; ?>">Blog
                                post</a></li>
                        <li><a href="/webtour/admin/content/add.php"
                                class="nav-link <?php echo $current_page === 'add' && $current_dir === 'add' ? 'active' : ''; ?>">Add
                                Post</a>
                        </li>
                        <li><a href="/webtour/admin/content/pages.php"
                                class="nav-link <?php echo $current_page === 'pages' && $current_dir === 'pages' ? 'active' : ''; ?>">Pages</a>
                        </li>

                    </ul>
                </li>

                <!-- Media Library -->
                <li class="nav-item">
                    <a href="/webtour/admin/media/index.php"
                        class="nav-link <?php echo $current_dir === 'media' ? 'active' : ''; ?>">
                        <i class="fas fa-images"></i>
                        <span class="nav-text">Media Library</span>
                    </a>
                </li>

                <!-- E-commerce Store -->
                <li class="nav-item has-submenu <?php echo $current_dir === 'store' ? 'active' : ''; ?>">
                    <a href="#" class="nav-link" onclick="toggleSubmenu(this)">
                        <i class="fas fa-edit"></i>
                        <span class="nav-text">Store</span>
                        <i class="fas fa-chevron-down submenu-arrow"></i>
                    </a>
                    <ul class="nav-submenu">
                        <li><a href="/webtour/admin/store/index.php"
                                class="nav-link <?php echo $current_page === 'index' && $current_dir === 'Store' ? 'active' : ''; ?>">Store</a>
                        </li>
                        <li><a href="/webtour/admin/store/products.php"
                                class="nav-link <?php echo $current_page === 'products' && $current_dir === 'add' ? 'active' : ''; ?>">Products</a>
                        </li>
                        <li><a href="/webtour/admin/store/orders.php"
                                class="nav-link <?php echo $current_page === 'orders' && $current_dir === 'orders' ? 'active' : ''; ?>">Orders</a>
                        </li>

                    </ul>
                </li>

                <!-- Email Campaigns -->
                <li class="nav-item">
                    <a href="/webtour/admin/email/campaigns.php"
                        class="nav-link <?php echo $current_dir === 'email' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i>
                        <span class="nav-text">Email Campaigns</span>
                    </a>
                </li>

                <!-- Settings -->
                <li class="nav-item">
                    <a href="/webtour/admin/settings/general.php"
                        class="nav-link <?php echo $current_dir === 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>

<style>
    .admin-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        background: linear-gradient(to bottom, #2c3e50, #1a2530);
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

    .logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #3498db, #2980b9);
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

    .user-info {
        padding: 0 20px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 20px;
        text-align: center;
    }

    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3498db, #2980b9);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        overflow: hidden;
        border: 3px solid #3498db;
        font-size: 1.5em;
        font-weight: bold;
        color: white;
    }

    .user-details h4 {
        font-size: 1.1em;
        margin-bottom: 5px;
        color: white;
    }

    .user-details span {
        font-size: 0.9em;
        opacity: 0.8;
        color: #3498db;
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
        background: rgba(52, 152, 219, 0.1);
        color: #3498db;
        transform: translateX(5px);
    }

    .nav-link.active {
        background: linear-gradient(90deg, #3498db, transparent);
        color: white;
        border-right: 4px solid #3498db;
    }

    .nav-link i {
        width: 20px;
        text-align: center;
        font-size: 1.1em;
    }

    .badge {
        background: #e74c3c;
        color: white;
        font-size: 0.7em;
        padding: 3px 7px;
        border-radius: 12px;
        margin-left: auto;
        min-width: 18px;
        text-align: center;
    }

    .submenu-arrow {
        margin-left: auto;
        font-size: 0.8em;
        transition: transform 0.3s ease;
    }

    .nav-item.has-submenu.active .submenu-arrow {
        transform: rotate(180deg);
    }

    .nav-submenu {
        list-style: none;
        padding: 0;
        margin: 0;
        background: rgba(0, 0, 0, 0.2);
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }

    .nav-item.has-submenu.active .nav-submenu {
        max-height: 300px;
    }

    .nav-submenu .nav-link {
        padding: 12px 20px 12px 55px;
        font-size: 0.9em;
    }

    @media (max-width: 992px) {
        .admin-sidebar {
            transform: translateX(-100%);
        }

        .admin-sidebar.mobile-open {
            transform: translateX(0);
        }
    }
</style>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
    }

    function toggleSubmenu(element) {
        const navItem = element.parentElement;
        const isActive = navItem.classList.contains('active');

        // Close all other submenus
        document.querySelectorAll('.nav-item.has-submenu').forEach(item => {
            item.classList.remove('active');
        });

        // Toggle current submenu
        if (!isActive) {
            navItem.classList.add('active');
        }
    }

    // Mobile sidebar toggle
    function toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('mobile-open');
    }
</script>