<?php
require_once 'config/config.php';

// Get filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$country_filter = $_GET['country'] ?? '';
$price_min = $_GET['price_min'] ?? '';
$price_max = $_GET['price_max'] ?? '';
$duration_filter = $_GET['duration'] ?? '';
$sort = $_GET['sort'] ?? 'featured';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = ["t.status = 'active'"];
$params = [];

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_conditions[] = "t.category_id = ?";
    $params[] = $category_filter;
}

if ($country_filter) {
    $where_conditions[] = "t.country_id = ?";
    $params[] = $country_filter;
}

if ($price_min) {
    $where_conditions[] = "t.price_adult >= ?";
    $params[] = $price_min;
}

if ($price_max) {
    $where_conditions[] = "t.price_adult <= ?";
    $params[] = $price_max;
}

if ($duration_filter) {
    switch ($duration_filter) {
        case 'short':
            $where_conditions[] = "t.duration_days <= 3";
            break;
        case 'medium':
            $where_conditions[] = "t.duration_days BETWEEN 4 AND 7";
            break;
        case 'long':
            $where_conditions[] = "t.duration_days >= 8";
            break;
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM tours t 
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN tour_categories tc ON t.category_id = tc.id
    $where_clause
";
$total_tours = $db->prepare($count_sql);
$total_tours->execute($params);
$total_count = $total_tours->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Sort options
$order_clause = '';
switch ($sort) {
    case 'price_low':
        $order_clause = 'ORDER BY t.price_adult ASC';
        break;
    case 'price_high':
        $order_clause = 'ORDER BY t.price_adult DESC';
        break;
    case 'duration':
        $order_clause = 'ORDER BY t.duration_days ASC';
        break;
    case 'rating':
        $order_clause = 'ORDER BY avg_rating DESC';
        break;
    case 'featured':
    default:
        $order_clause = 'ORDER BY t.featured DESC, t.created_at DESC';
        break;
}

// Get tours
$tours_sql = "
    SELECT t.*, c.name as country_name, tc.name as category_name,
           AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
    FROM tours t
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN tour_categories tc ON t.category_id = tc.id
    LEFT JOIN reviews r ON t.id = r.tour_id
    $where_clause
    GROUP BY t.id
    $order_clause
    LIMIT $per_page OFFSET $offset
";

$tours_stmt = $db->prepare($tours_sql);
$tours_stmt->execute($params);
$tours = $tours_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$categories = $db->query("SELECT * FROM tour_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$countries = $db->query("SELECT * FROM countries ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Tours - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description"
        content="Discover amazing tours and travel experiences with Forever Young Tours. Browse our collection of luxury group travel and adventure tours.">

    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    <style>
        /* Tours Page Styles */
        .tours-hero {
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.9), rgba(102, 234, 102, 0.9)),
                url('https://images.unsplash.com/photo-1464037866556-6812c9d1c72e?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80') center/cover;
            color: white;
            padding: 100px 0 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .tours-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/images/world-map.png') center/cover no-repeat;
            opacity: 0.1;
        }

        .tours-hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
            position: relative;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .tours-hero p {
            font-size: 1.3rem;
            max-width: 600px;
            margin: 0 auto 2rem;
            opacity: 0.9;
            position: relative;
        }

        .hero-search {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
            background: white;
            padding: 8px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .search-input-group {
            position: relative;
            flex: 1;
        }

        .search-input-group i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.1rem;
        }

        .search-input-group input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            outline: none;
            transition: var(--transition);
        }

        .search-input-group input:focus {
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.5);
        }

        .search-form .btn {
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Tours Layout */
        .tours-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 40px;
            margin: 40px 0;
        }

        /* Filters Sidebar */
        .filters-sidebar {
            background: white;
            border-radius: 15px;
            padding: 30px;
            height: fit-content;
            box-shadow: var(--shadow);
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }

        .filters-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .filters-sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .filters-sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-gold);
            border-radius: 10px;
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .filters-header h3 {
            color: var(--primary-black);
            margin: 0;
            font-size: 1.3rem;
        }

        .clear-filters {
            background: none;
            border: none;
            color: var(--primary-gold);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
            padding: 5px;
            border-radius: 4px;
        }

        .clear-filters:hover {
            text-decoration: underline;
            background: rgba(212, 175, 55, 0.1);
        }

        .filter-group {
            margin-bottom: 25px;
        }

        .filter-group h4 {
            margin-bottom: 15px;
            color: var(--primary-black);
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group h4::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #eee;
            margin-left: 10px;
        }

        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .filter-options::-webkit-scrollbar {
            width: 4px;
        }

        .filter-options::-webkit-scrollbar-thumb {
            background: var(--primary-gold);
            border-radius: 10px;
        }

        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 12px;
            transition: var(--transition);
            border-radius: 6px;
        }

        .filter-option:hover {
            background: rgba(212, 175, 55, 0.1);
        }

        .filter-option input {
            margin: 0;
            accent-color: var(--primary-gold);
            width: 16px;
            height: 16px;
        }

        .filter-option span {
            font-size: 0.95rem;
            color: var(--black-lighter);
        }

        .filter-option:hover span {
            color: var(--primary-black);
        }

        .price-inputs {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-inputs input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .price-inputs input:focus {
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.2);
        }

        .price-inputs span {
            color: var(--black-lighter);
            font-size: 0.9rem;
        }

        .filters-form .btn {
            width: 100%;
            padding: 12px;
            font-size: 0.95rem;
            margin-top: 10px;
        }

        /* Tours Content */
        .tours-content {
            min-height: 600px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .results-info h2 {
            color: var(--primary-black);
            margin: 0 0 5px 0;
            font-size: 1.8rem;
        }

        .results-info p {
            color: var(--black-lighter);
            margin: 0;
            font-size: 0.95rem;
        }

        .results-controls {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .view-toggle {
            display: flex;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .view-btn {
            padding: 8px 12px;
            border: none;
            background: white;
            cursor: pointer;
            transition: var(--transition);
            color: var(--black-lighter);
        }

        .view-btn.active {
            background: var(--primary-gold);
            color: white;
        }

        .view-btn:hover:not(.active) {
            background: rgba(212, 175, 55, 0.1);
            color: var(--primary-gold);
        }

        .sort-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .sort-select:focus {
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.2);
            outline: none;
        }

        /* Tours Grid */
        .tours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        .tours-grid.list-view {
            grid-template-columns: 1fr;
        }

        .tours-grid.list-view .tour-card {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .tours-grid.list-view .tour-image {
            width: 300px;
            flex-shrink: 0;
            height: 200px;
        }

        .tours-grid.list-view .tour-content {
            flex: 1;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        .no-results h3 {
            margin-bottom: 10px;
            color: var(--primary-black);
            font-size: 1.5rem;
        }

        .no-results p {
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .no-results .btn {
            padding: 12px 30px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .pagination-btn,
        .pagination-number {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: var(--primary-black);
            transition: var(--transition);
            min-width: 40px;
            text-align: center;
        }

        .pagination-number.active,
        .pagination-btn:hover,
        .pagination-number:hover {
            background: var(--primary-gold);
            color: white;
            border-color: var(--primary-gold);
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Mobile Responsiveness */
        @media (max-width: 992px) {
            .tours-hero h1 {
                font-size: 2.5rem;
            }

            .tours-hero p {
                font-size: 1.1rem;
            }

            .tours-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .filters-sidebar {
                position: static;
                max-height: none;
            }

            .search-form {
                flex-direction: column;
                padding: 15px;
                border-radius: 15px;
            }

            .search-input-group {
                width: 100%;
            }

            .results-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .results-controls {
                width: 100%;
                justify-content: space-between;
            }

            .tours-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }

            .tours-grid.list-view .tour-card {
                flex-direction: column;
            }

            .tours-grid.list-view .tour-image {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .tours-hero {
                padding: 80px 0 40px;
            }

            .tours-hero h1 {
                font-size: 2rem;
            }

            .search-form .btn {
                width: 100%;
            }

            .filter-options {
                max-height: 150px;
            }

            .tours-grid {
                grid-template-columns: 1fr;
            }

            .pagination {
                gap: 5px;
            }

            .pagination-btn,
            .pagination-number {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
        }

        .filter-search {
            width: 100%;
            padding: 10px 10px 10px 35px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .filter-search:focus {
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.2);
            outline: none;
        }

        .search-input-group {
            position: relative;
        }

        .search-input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--black-lighter);
            font-size: 0.9rem;
        }

        .country-options {
            max-height: 300px;
            overflow-y: auto;
        }

        /* Filter Toggle for Mobile */
        .mobile-filters-toggle {
            display: none;
            background: var(--primary-gold);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            font-weight: 600;
            margin-bottom: 20px;
            width: 100%;
            cursor: pointer;
            transition: var(--transition);
        }

        .mobile-filters-toggle:hover {
            background: var(--gold-dark);
        }

        @media (max-width: 992px) {
            .mobile-filters-toggle {
                display: block;
            }

            .filters-sidebar {
                display: none;
            }

            .filters-sidebar.mobile-open {
                display: block;
            }
        }

        /* Loading Animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(212, 175, 55, 0.2);
            border-top: 4px solid var(--primary-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
                <div id="worldMap" class="map-container"></div>
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="tours-hero">
        <div class="container">
            <h1>Discover Amazing Tours</h1>
            <p style="color: white;">Explore our handpicked collection of extraordinary travel experiences</p>

            <!-- Search Bar -->
            <div class="hero-search">
                <form class="search-form" method="GET">
                    <div class="search-input-group">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search destinations, tours..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search Tours</button>
                </form>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="tours-layout">
            <!-- Filters Sidebar -->
            <aside class="filters-sidebar">
                <div class="filters-header">
                    <h3>Filter Tours</h3>
                    <button class="clear-filters" onclick="clearAllFilters()">Clear All</button>
                </div>

                <form class="filters-form" method="GET" id="filtersForm">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">

                    <!-- Category Filter -->
                    <div class="filter-group">
                        <h4>Category</h4>
                        <div class="filter-options">
                            <?php foreach ($categories as $category): ?>
                                <label class="filter-option">
                                    <input type="radio" name="category" value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Country Filter -->
                    <div class="filter-group">
                        <h4>Destination</h4>
                        <div class="search-input-group" style="margin-bottom: 15px;">
                            <i class="fas fa-search"></i>
                            <input type="text" id="countrySearch" placeholder="Search countries..."
                                class="filter-search">
                        </div>
                        <div class="filter-options country-options">
                            <?php foreach ($countries as $country): ?>
                                <label class="filter-option">
                                    <input type="radio" name="country" value="<?php echo $country['id']; ?>" <?php echo $country_filter == $country['id'] ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($country['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Price Range -->
                    <!-- <div class="filter-group">
                        <h4>Price Range</h4>
                        <div class="price-inputs">
                            <input type="number" name="price_min" placeholder="Min"
                                value="<?php echo htmlspecialchars($price_min); ?>">
                            <span>to</span>
                            <input type="number" name="price_max" placeholder="Max"
                                value="<?php echo htmlspecialchars($price_max); ?>">
                        </div>
                    </div> -->

                    <!-- Duration Filter -->
                    <div class="filter-group">
                        <h4>Duration</h4>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="radio" name="duration" value="short" <?php echo $duration_filter == 'short' ? 'checked' : ''; ?>>
                                <span>1-3 days</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="duration" value="medium" <?php echo $duration_filter == 'medium' ? 'checked' : ''; ?>>
                                <span>4-7 days</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="duration" value="long" <?php echo $duration_filter == 'long' ? 'checked' : ''; ?>>
                                <span>8+ days</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                </form>
            </aside>

            <!-- Tours Content -->
            <main class="tours-content">
                <!-- Results Header -->
                <div class="results-header">
                    <div class="results-info">
                        <h2>Tours</h2>
                        <p><?php echo $total_count; ?> tours found</p>
                    </div>

                    <div class="results-controls">
                        <div class="view-toggle">
                            <button class="view-btn active" data-view="grid">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="view-btn" data-view="list">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>

                        <select name="sort" class="sort-select" onchange="updateSort(this.value)">
                            <option value="featured" <?php echo $sort == 'featured' ? 'selected' : ''; ?>>Featured
                            </option>
                            <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to
                                High</option>
                            <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High
                                to Low</option>
                            <option value="duration" <?php echo $sort == 'duration' ? 'selected' : ''; ?>>Duration
                            </option>
                            <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Rating</option>
                        </select>
                    </div>
                </div>

                <!-- Tours Grid -->
                <div class="tours-grid" id="toursGrid">
                    <?php foreach ($tours as $tour): ?>
                        <div class="tour-card">
                            <div class="tour-image">
                                <?php if ($tour['featured_image']): ?>
                                    <img src="<?php echo htmlspecialchars($tour['featured_image']); ?>"
                                        alt="<?php echo htmlspecialchars($tour['title']); ?>">
                                <?php else: ?>
                                    <img src="/placeholder.svg?height=250&width=350"
                                        alt="<?php echo htmlspecialchars($tour['title']); ?>">
                                <?php endif; ?>

                                <div class="tour-badges">
                                    <?php if ($tour['featured']): ?>
                                        <span class="badge featured">Featured</span>
                                    <?php endif; ?>
                                    <?php if (!empty($tour['discount_percentage']) && $tour['discount_percentage'] > 0): ?>
                                        <span class="badge discount"><?php echo $tour['discount_percentage']; ?>% OFF</span>
                                    <?php endif; ?>

                                </div>

                                <div class="tour-overlay">
                                    <a href="tour-details.php?id=<?php echo $tour['id']; ?>" class="btn btn-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>

                            <div class="tour-content">
                                <div class="tour-meta">
                                    <span class="tour-category">
                                        <i class="fas fa-tag"></i>
                                        <?php echo htmlspecialchars($tour['category_name']); ?>
                                    </span>
                                    <div class="tour-rating">
                                        <?php
                                        $rating = $tour['avg_rating'] ?: 4.5;
                                        for ($i = 1; $i <= 5; $i++):
                                            ?>
                                            <i class="fas fa-star <?php echo $i <= $rating ? 'active' : ''; ?>"></i>
                                        <?php endfor; ?>
                                        <span><?php echo number_format($rating, 1); ?></span>
                                    </div>
                                </div>

                                <h3 class="tour-title">
                                    <a href="tour-details.php?id=<?php echo $tour['id']; ?>">
                                        <?php echo htmlspecialchars($tour['title']); ?>
                                    </a>
                                </h3>

                                <div class="tour-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($tour['country_name'] ?? ''); ?>

                                </div>

                                <div class="tour-details">
                                    <div class="tour-duration">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $tour['duration_days']; ?> days
                                    </div>
                                    <div class="tour-group">
                                        <i class="fas fa-users"></i>
                                        Max
                                        <?php echo isset($tour['max_participants']) ? htmlspecialchars($tour['max_participants']) : 'N/A'; ?>
                                    </div>

                                </div>

                                <p class="tour-description">
                                    <?php
                                    $desc = strip_tags($tour['description'] ?? '');
                                    echo substr($desc, 0, 120) . '...';
                                    ?>
                                </p>


                                <div class="tour-footer">
                                    <div class="tour-price">
                                        <span class="price-from">From</span>
                                        <span class="price-amount">
                                            <?php echo formatCurrency($tour['price_adult']); ?>
                                        </span>
                                        <span class="price-per">per person</span>
                                    </div>

                                    <a href="book.php?tour=<?php echo $tour['id']; ?>" class="btn btn-primary">
                                        Book Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($tours)): ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h3>No tours found</h3>
                        <p>Try adjusting your filters or search terms</p>
                        <button class="btn btn-primary" onclick="clearAllFilters()">Clear Filters</button>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>

                        <div class="pagination-numbers">
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                    class="pagination-number <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                class="pagination-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
    <script>
        // Auto-submit filters on change
        document.querySelectorAll('.filters-form input, .filters-form select').forEach(input => {
            input.addEventListener('change', function () {
                document.getElementById('filtersForm').submit();
            });
        });

        // Clear all filters
        function clearAllFilters() {
            window.location.href = 'tours.php';
        }

        // Update sort
        function updateSort(value) {
            const url = new URL(window.location);
            url.searchParams.set('sort', value);
            window.location.href = url.toString();
        }

        // View toggle
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const view = this.dataset.view;
                const grid = document.getElementById('toursGrid');

                if (view === 'list') {
                    grid.classList.add('list-view');
                } else {
                    grid.classList.remove('list-view');
                }
            });
        });

        // Mobile filters toggle
        function toggleFilters() {
            const sidebar = document.querySelector('.filters-sidebar');
            sidebar.classList.toggle('mobile-open');
        }
    </script>
    <script>
        // Mobile filters toggle
        const mobileFiltersToggle = document.createElement('button');
        mobileFiltersToggle.className = 'mobile-filters-toggle';
        mobileFiltersToggle.innerHTML = '<i class="fas fa-filter"></i> Filter Tours';
        mobileFiltersToggle.onclick = function () {
            document.querySelector('.filters-sidebar').classList.toggle('mobile-open');
        };

        // Insert the toggle button before the tours content
        document.querySelector('.tours-content').parentNode.insertBefore(mobileFiltersToggle, document.querySelector('.tours-content'));

        // Loading overlay for filters
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = '<div class="loading-spinner"></div>';
        document.body.appendChild(loadingOverlay);

        // Show loading when filters are submitted
        document.getElementById('filtersForm').addEventListener('submit', function () {
            loadingOverlay.classList.add('active');
        });
    </script>

    <script>
        // Country search functionality
        document.getElementById('countrySearch').addEventListener('input', function (e) {
            const searchTerm = e.target.value.toLowerCase();
            const countryOptions = document.querySelectorAll('.country-options .filter-option');

            countryOptions.forEach(option => {
                const countryName = option.querySelector('span').textContent.toLowerCase();
                if (countryName.includes(searchTerm)) {
                    option.style.display = 'flex';
                } else {
                    option.style.display = 'none';
                }
            });
        });

        // Clear search when changing other filters
        document.getElementById('filtersForm').addEventListener('submit', function () {
            document.getElementById('countrySearch').value = '';
            document.querySelectorAll('.country-options .filter-option').forEach(option => {
                option.style.display = 'flex';
            });
        });
    </script>
</body>

</html>