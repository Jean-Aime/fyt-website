<?php
// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!-- Top Bar -->
<div class="top-bar">
    <div class="container">
        <div class="top-bar-content">
            <div class="contact-info">
            </div>
            <div class="top-bar-actions">
                <div class="language-selector">
                    <select onchange="changeLanguage(this.value)">
                        <option value="en" <?php echo $current_language === 'en' ? 'selected' : ''; ?>>English</option>
                        <option value="fr" <?php echo $current_language === 'fr' ? 'selected' : ''; ?>>Français</option>
                        <option value="rw" <?php echo $current_language === 'rw' ? 'selected' : ''; ?>>Kinyarwanda
                        </option>
                    </select>
                </div>
                <div class="portal-links">
                    <a href="client/dashboard.php">Client Portal</a>
                    <a href="mca/dashboard.php">MCA Portal</a>
                    <a href="admin/dashboard.php">Admin</a>
                </div>
            </div>
        </div>
    </div>
</div>

<header class="header" id="header">
    <div class="container">
        <div class="header-content">
            <a href="index.php" class="logo" style="display: flex; align-items: center; text-decoration: none;">
                <img src="assets/images/logo.png" alt="Logo" style="height: 60px; margin-right: 10px;">
            </a>

            <nav class="nav">
                <div class="nav-container">
                    <ul class="nav-menu" id="navMenu">
                        <li><a href="index.php"
                                class="nav-link <?php echo $current_page === 'index' ? 'active' : ''; ?>">
                                <span class="nav-text"><?php echo $lang['home']; ?></span>
                            </a></li>
                        <li><a href="about.php"
                                class="nav-link <?php echo $current_page === 'about' ? 'active' : ''; ?>">
                                <span class="nav-text"><?php echo $lang['about_us']; ?></span>
                            </a></li>
                        <li><a href="blog.php" class="nav-link <?php echo $current_page === 'blog' ? 'active' : ''; ?>">
                                <span class="nav-text"><?php echo $lang['blog']; ?></span>
                            </a></li>
                        <li><a href="travel.php"
                                class="nav-link <?php echo $current_page === 'travel' ? 'active' : ''; ?>">
                                <span class="nav-text">Travel</span>
                            </a></li>
                        <li><a href="destinations.php"
                                class="nav-link <?php echo $current_page === 'tours' ? 'active' : ''; ?>">
                                <span class="nav-text"><?php echo $lang['tours']; ?></span>
                            </a></li>
                        <li><a href="store.php"
                                class="nav-link <?php echo $current_page === 'store' ? 'active' : ''; ?>">
                                <span class="nav-text">Store</span>
                            </a></li>
                        <li><a href="contact.php"
                                class="nav-link <?php echo $current_page === 'contact' ? 'active' : ''; ?>">
                                <span class="nav-text"><?php echo $lang['contact_us']; ?></span>
                            </a></li>
                    </ul>

                    <div class="header-actions">
                        <div class="search-toggle">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count">0</span>
                        </div>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="user-menu">
                                <a href="client/dashboard.php" class="btn btn-outline">
                                    <i class="fas fa-user"></i> Dashboard
                                </a>
                            </div>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-outline">
                                <i class="fas fa-sign-in-alt"></i> <?php echo $lang['login']; ?>
                            </a>
                        <?php endif; ?>

                        <a href="book.php" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> <?php echo $lang['book_now']; ?>
                        </a>
                    </div>
                </div>

                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>
        </div>
    </div>

    <!-- Search Overlay -->
    <div class="search-overlay" id="searchOverlay">
        <div class="search-container">
            <input type="text" placeholder="Search destinations, tours..." class="search-input">
            <button class="search-close"><i class="fas fa-times"></i></button>
        </div>
    </div>
</header>
<style>
    /* ====================[ TOP BAR ]==================== */
    .top-bar {
        background-color: #000000;
        /* Black background */
        color: white;
        padding: 10px 0;
        font-size: 0.9rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        /* Custom gold border */
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
    }

    .top-bar-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .contact-info span {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        margin-right: 2px;
        font-weight: 500;
    }

    .contact-info i {
        color: #d4af37;
        /* Custom gold */
        font-size: 0.9rem;
    }

    .contact-info a {
        color: white;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .contact-info a:hover {
        color: #d4af37;
        /* Custom gold */
    }

    .top-bar-actions {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .language-selector select {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(212, 175, 55, 0.3);
        /* Custom gold */
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .language-selector select:hover {
        background: rgba(212, 175, 55, 0.1);
        /* Custom gold */
        border-color: #d4af37;
        /* Custom gold */
    }

    .portal-links {
        display: flex;
        gap: 15px;
    }

    .portal-links a {
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .portal-links a:hover {
        color: #d4af37;
        /* Custom gold */
        background: rgba(212, 175, 55, 0.1);
        /* Custom gold */
    }

    /* ====================[ HEADER ]==================== */
    .header {
        background: white;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        position: sticky;
        top: 0;
        z-index: 1000;
        transition: all 0.3s ease;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .header.scrolled {
        background: white;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    }

    .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 0;
        position: relative;
    }

    /* Logo */
    .logo {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        transition: transform 0.3s ease;
    }

    .logo:hover {
        transform: scale(1.02);
    }

    .logo-icon {
        width: 55px;
        height: 55px;
        background: linear-gradient(135deg, #d4af37, #b5922e);
        /* Custom gold gradient */
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: black;
        font-weight: bold;
        font-size: 1.3rem;
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        /* Custom gold */
        transition: all 0.3s ease;
    }

    .logo:hover .logo-icon {
        transform: rotate(5deg) scale(1.05);
        box-shadow: 0 6px 16px rgba(212, 175, 55, 0.4);
        /* Custom gold */
    }

    .logo-text {
        display: flex;
        flex-direction: column;
    }

    .logo-name {
        font-size: 1.6rem;
        font-weight: 700;
        color: black;
        font-family: "Playfair Display", serif;
        line-height: 1;
    }

    .logo-tagline {
        font-size: 0.8rem;
        color: #666;
        margin-top: 2px;
        font-weight: 500;
        font-style: italic;
    }

    /* Navigation */
    .nav {
        display: flex;
        align-items: center;
    }

    .nav-container {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .nav-menu {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 0;
        gap: 5px;
    }

    .nav-menu li {
        position: relative;
    }

    .nav-link {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 12px 15px;
        color: black;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        border-radius: 8px;
        font-size: 0.9rem;
        position: relative;
        overflow: hidden;
    }

    .nav-icon {
        font-size: 1.1rem;
        margin-bottom: 5px;
        color: #666;
        transition: all 0.3s ease;
    }

    .nav-text {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .nav-link::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #d4af37, #b5922e);
        /* Custom gold gradient */
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: -1;
    }

    .nav-link:hover::before,
    .nav-link.active::before {
        opacity: 0.1;
    }

    .nav-link:hover,
    .nav-link.active {
        color: #d4af37;
        /* Custom gold */
    }

    .nav-link:hover .nav-icon,
    .nav-link.active .nav-icon {
        color: #d4af37;
        /* Custom gold */
        transform: translateY(-3px);
    }

    /* Header Actions */
    .header-actions {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .search-toggle,
    .cart-icon {
        cursor: pointer;
        padding: 10px;
        border-radius: 50%;
        transition: all 0.3s ease;
        position: relative;
        background: rgba(212, 175, 55, 0.1);
        /* Custom gold */
        color: #d4af37;
        /* Custom gold */
        border: 1px solid rgba(212, 175, 55, 0.2);
        /* Custom gold */
    }

    .search-toggle:hover,
    .cart-icon:hover {
        background: rgba(212, 175, 55, 0.2);
        /* Custom gold */
        transform: scale(1.05);
    }

    .cart-count {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #4CAF50;
        /* Green accent */
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.7rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border: 2px solid white;
    }

    .btn {
        font-weight: 600;
        padding: 10px 20px;
        border-radius: 6px;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.85rem;
        border: 2px solid transparent;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        position: relative;
        overflow: hidden;
    }

    .btn-outline {
        background-color: transparent;
        border-color: #d4af37;
        /* Custom gold */
        color: #d4af37;
        /* Custom gold */
    }

    .btn-outline:hover {
        background-color: #d4af37;
        /* Custom gold */
        color: black;
    }

    .btn-primary {
        background: linear-gradient(135deg, #d4af37, #b5922e);
        /* Custom gold gradient */
        border-color: #d4af37;
        /* Custom gold */
        color: black;
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        /* Custom gold */
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #b5922e, #d4af37);
        /* Custom gold gradient */
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(212, 175, 55, 0.4);
        /* Custom gold */
    }

    .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        font-size: 1.5rem;
        color: black;
        cursor: pointer;
        padding: 8px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .mobile-menu-toggle:hover {
        background: rgba(212, 175, 55, 0.1);
        /* Custom gold */
        color: #d4af37;
        /* Custom gold */
    }

    /* Search Overlay */
    .search-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.9);
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(10px);
    }

    .search-overlay.active {
        display: flex;
    }

    .search-container {
        position: relative;
        width: 90%;
        max-width: 600px;
    }

    .search-input {
        width: 100%;
        padding: 20px 60px 20px 20px;
        font-size: 1.5rem;
        border: none;
        border-radius: 50px;
        outline: none;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }

    .search-close {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #666;
        transition: all 0.3s ease;
    }

    .search-close:hover {
        color: #4CAF50;
        /* Green accent */
        transform: translateY(-50%) scale(1.1);
    }

    /* Responsive Styles */
    @media (max-width: 992px) {
        .nav-menu {
            position: fixed;
            top: 100px;
            left: 0;
            right: 0;
            background: white;
            backdrop-filter: blur(20px);
            flex-direction: column;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transform: translateY(-150%);
            transition: transform 0.3s ease;
            z-index: 999;
            gap: 5px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .nav-menu.active {
            transform: translateY(0);
        }

        .nav-menu li {
            width: 100%;
        }

        .nav-link {
            width: 100%;
            flex-direction: row;
            justify-content: flex-start;
            gap: 15px;
            padding: 15px;
            border-radius: 8px;
        }

        .nav-icon {
            margin-bottom: 0;
            font-size: 1rem;
        }

        .nav-text {
            font-size: 0.9rem;
        }

        .mobile-menu-toggle {
            display: block;
        }

        .header-actions .btn {
            display: none;
        }

        .header-actions .search-toggle,
        .header-actions .cart-icon {
            display: flex;
        }

        .logo-text {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .top-bar-content {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }

        .contact-info {
            justify-content: center;
            flex-wrap: wrap;
        }

        .contact-info span {
            margin-right: 15px;
        }

        .top-bar-actions {
            flex-direction: column;
            gap: 10px;
        }

        .portal-links {
            justify-content: center;
        }
    }

    @media (max-width: 576px) {
        .logo-icon {
            width: 45px;
            height: 45px;
            font-size: 1.1rem;
        }

        .search-input {
            font-size: 1.2rem;
            padding: 15px 50px 15px 15px;
        }
    }
</style>

<script>
    // Header scroll effect
    window.addEventListener('scroll', function () {
        const header = document.getElementById('header');
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        if (scrollTop > 100) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });

    // Mobile menu toggle
    document.getElementById('mobileMenuToggle').addEventListener('click', function () {
        const navMenu = document.getElementById('navMenu');
        const icon = this.querySelector('i');

        navMenu.classList.toggle('active');
        document.body.classList.toggle('menu-open');

        if (navMenu.classList.contains('active')) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });

    // Search overlay
    document.querySelector('.search-toggle').addEventListener('click', function () {
        document.getElementById('searchOverlay').classList.add('active');
        document.querySelector('.search-input').focus();
    });

    document.querySelector('.search-close').addEventListener('click', function () {
        document.getElementById('searchOverlay').classList.remove('active');
    });

    // Language change
    function changeLanguage(lang) {
        const url = new URL(window.location);
        url.searchParams.set('lang', lang);
        window.location.href = url.toString();
    }

    // Close mobile menu when clicking on a link
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function () {
            const navMenu = document.getElementById('navMenu');
            const toggle = document.getElementById('mobileMenuToggle');
            const icon = toggle.querySelector('i');

            navMenu.classList.remove('active');
            document.body.classList.remove('menu-open');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        });
    });

    // Close search on escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.getElementById('searchOverlay').classList.contains('active')) {
            document.getElementById('searchOverlay').classList.remove('active');
        }
    });
</script>