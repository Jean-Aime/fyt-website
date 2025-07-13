<?php
require_once 'config/config.php';

// Get all active countries with tour counts
$countries = $db->query("
    SELECT c.*, COUNT(t.id) as tour_count,
           MIN(t.price_adult) as min_price,
           MAX(t.price_adult) as max_price
    FROM countries c
    LEFT JOIN tours t ON c.id = t.country_id AND t.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    HAVING tour_count > 0
    ORDER BY c.continent, c.name
")->fetchAll(PDO::FETCH_ASSOC);

// Group countries by continent
$continents = [];
foreach ($countries as $country) {
    $continent = $country['continent'] ?: 'Other';
    if (!isset($continents[$continent])) {
        $continents[$continent] = [];
    }
    $continents[$continent][] = $country;
}

// Get selected country details if specified
$selected_country = null;
$country_tours = [];
if (isset($_GET['country'])) {
    $country_slug = $_GET['country'];
    $stmt = $db->prepare("SELECT * FROM countries WHERE LOWER(REPLACE(name, ' ', '-')) = ? AND status = 'active'");
    $stmt->execute([strtolower($country_slug)]);
    $selected_country = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selected_country) {
        // Get tours for this country grouped by category
        $stmt = $db->prepare("
            SELECT t.*, tc.name as category_name, tc.icon as category_icon
            FROM tours t
            LEFT JOIN tour_categories tc ON t.category_id = tc.id
            WHERE t.country_id = ? AND t.status = 'active'
            ORDER BY tc.name, t.title
        ");
        $stmt->execute([$selected_country['id']]);
        $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group tours by category
        foreach ($tours as $tour) {
            $category = $tour['category_name'] ?: 'Other';
            if (!isset($country_tours[$category])) {
                $country_tours[$category] = [
                    'icon' => $tour['category_icon'],
                    'tours' => []
                ];
            }
            $country_tours[$category]['tours'][] = $tour;
        }
    }
}

$page_title = $selected_country ?
    'Tours in ' . $selected_country['name'] . ' - Forever Young Tours' :
    'Destinations - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="Explore amazing destinations and book luxury tours with Forever Young Tours">

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    <!-- Leaflet for interactive map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        /* Base Styles */
        :root {
            --gold: #D4AF37;
            --gold-light: #f4e4a6;
            --gold-dark: #b8941f;
            --black: #000000;
            --black-light: #333333;
            --white: #ffffff;
            --green: #228B22;
            --green-light: #32cd32;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }

        /* Hero Section */
        .destinations-hero {
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.9), rgba(102, 234, 102, 0.9)),
                url('https://images.unsplash.com/photo-1464037866556-6812c9d1c72e?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80') center/cover;
            color: var(--white);
            padding: 120px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .destinations-hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/images/world-map-pattern.png') center/cover;
            opacity: 0.1;
        }

        .destinations-hero h1 {
            font-size: 3.5em;
            margin-bottom: 20px;
            font-family: 'Playfair Display', serif;
            position: relative;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .destinations-hero p {
            font-size: 1.2em;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
        }

        /* Map Container */
        .map-container {
            height: 600px;
            margin: 60px 0;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .map-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(34, 139, 34, 0.1) 100%);
            pointer-events: none;
            z-index: 100;
        }

        /* Continent Filters */
        .continent-filters {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 40px 0;
            flex-wrap: wrap;
        }

        .continent-filter {
            padding: 12px 25px;
            border: 2px solid var(--gold);
            border-radius: 30px;
            background: transparent;
            color: var(--black);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .continent-filter:hover,
        .continent-filter.active {
            background: var(--gold);
            color: var(--black);
            border-color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        /* Countries Grid */
        .countries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            margin: 50px 0;
        }

        .country-card {
            background: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            border: 1px solid var(--gray-200);
        }

        .country-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
        }

        .country-image {
            height: 220px;
            background: linear-gradient(45deg, var(--black-light), var(--black));
            position: relative;
            overflow: hidden;
        }

        .country-image::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 50%);
        }

        .country-flag {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 50px;
            height: 34px;
            border-radius: 4px;
            object-fit: cover;
            border: 2px solid var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 2;
        }

        .country-info {
            padding: 25px;
            position: relative;
        }

        .country-name {
            font-size: 1.5em;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--black);
            font-family: 'Playfair Display', serif;
        }

        .country-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.95em;
            color: var(--black-light);
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray-200);
        }

        .country-stats i {
            color: var(--gold);
            margin-right: 5px;
        }

        .country-description {
            color: var(--black-light);
            line-height: 1.7;
            margin-bottom: 20px;
            font-size: 0.95em;
        }

        .btn-explore {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
            color: var(--black);
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }

        .btn-explore:hover {
            background: linear-gradient(135deg, var(--gold-dark) 0%, var(--gold) 100%);
            color: var(--black);
            transform: translateY(-2px);
        }

        /* Country Tours Page */
        .breadcrumb {
            background: var(--gray-100);
            padding: 20px 0;
            margin-bottom: 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .breadcrumb a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--gold-dark);
            text-decoration: underline;
        }

        .country-tours {
            margin-top: 60px;
        }

        .category-section {
            margin-bottom: 60px;
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 25px;
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            border-radius: 12px;
            border-left: 5px solid var(--gold);
        }

        .category-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
            color: var(--black);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        .category-header h2 {
            margin-bottom: 5px;
            color: var(--black);
        }

        .category-header p {
            color: var(--black-light);
            font-size: 0.95em;
        }

        .tours-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 30px;
        }

        .tour-summary {
            background: var(--white);
            border-radius: 12px;
            padding: 0;
            box-shadow: var(--shadow);
            transition: var(--transition);
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        .tour-summary:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gold);
        }

        .tour-image {
            height: 200px;
            overflow: hidden;
        }

        .tour-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .tour-summary:hover .tour-image img {
            transform: scale(1.05);
        }

        .tour-content {
            padding: 25px;
        }

        .tour-summary h4 {
            color: var(--black);
            margin-bottom: 15px;
            font-size: 1.4em;
            font-family: 'Playfair Display', serif;
        }

        .tour-summary p {
            color: var(--black-light);
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .tour-meta {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            font-size: 0.95em;
            color: var(--black-light);
            flex-wrap: wrap;
        }

        .tour-meta i {
            color: var(--gold);
            margin-right: 5px;
            width: 16px;
            text-align: center;
        }

        .btn-details {
            background: var(--black);
            color: var(--white);
            border: none;
            width: 100%;
            margin-top: 15px;
            transition: var(--transition);
        }

        .btn-details:hover {
            background: var(--gold);
            color: var(--black);
        }

        /* Contact Form Modal */
        .contact-form-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            margin: 20px;
            padding: 40px;
            border-radius: 15px;
            width: 100%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--gray-200);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close-modal {
            position: absolute;
            right: 25px;
            top: 25px;
            font-size: 1.8em;
            cursor: pointer;
            color: var(--black-light);
            transition: var(--transition);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-modal:hover {
            color: var(--gold);
            background: var(--gray-100);
        }

        .modal-content h3 {
            color: var(--black);
            margin-bottom: 15px;
            font-family: 'Playfair Display', serif;
        }

        .modal-content p {
            color: var(--black-light);
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--black);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            transition: var(--transition);
            font-size: 1em;
        }

        .form-control:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
            outline: none;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
            color: var(--black);
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 100%;
            transition: var(--transition);
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, var(--gold-dark) 0%, var(--gold) 100%);
            transform: translateY(-2px);
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .countries-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }

            .tours-list {
                grid-template-columns: 1fr;
            }

            .destinations-hero h1 {
                font-size: 2.8em;
            }
        }

        @media (max-width: 768px) {
            .continent-filters {
                gap: 10px;
            }

            .continent-filter {
                padding: 10px 15px;
                font-size: 0.85em;
            }

            .map-container {
                height: 450px;
                margin: 40px 0;
            }

            .country-image {
                height: 180px;
            }

            .modal-content {
                padding: 30px 20px;
            }
        }

        @media (max-width: 576px) {
            .destinations-hero h1 {
                font-size: 2.2em;
            }

            .destinations-hero p {
                font-size: 1em;
            }

            .country-card {
                max-width: 100%;
            }

            .category-header {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }

            .category-icon {
                margin-bottom: 15px;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <?php if ($selected_country): ?>
        <!-- Country-specific page -->
        <div class="breadcrumb">
            <div class="container">
                <a href="destinations.php">Destinations</a> / <?php echo htmlspecialchars($selected_country['name']); ?>
            </div>
        </div>

        <section class="destinations-hero">
            <div class="container">
                <h1>Tours in <?php echo htmlspecialchars($selected_country['name']); ?></h1>
                <p>Discover amazing experiences in this beautiful destination</p>
            </div>
        </section>

        <div class="container">
            <div class="country-tours">
                <?php foreach ($country_tours as $category_name => $category_data): ?>
                    <div class="category-section">
                        <div class="category-header">
                            <div class="category-icon">
                                <i class="<?php echo $category_data['icon'] ?: 'fas fa-map-marked-alt'; ?>"></i>
                            </div>
                            <div>
                                <h2><?php echo htmlspecialchars($category_name); ?></h2>
                                <p><?php echo count($category_data['tours']); ?> tours available</p>
                            </div>
                        </div>

                        <div class="tours-list">
                            <?php foreach ($category_data['tours'] as $tour): ?>
                                <div class="tour-summary">
                                    <?php if ($tour['featured_image']): ?>
                                        <img src="<?php echo htmlspecialchars($tour['featured_image']); ?>"
                                            alt="<?php echo htmlspecialchars($tour['title']); ?>"
                                            style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 15px;">
                                    <?php endif; ?>

                                    <h4><?php echo htmlspecialchars($tour['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($tour['short_description']); ?></p>

                                    <div class="tour-meta">
                                        <span><i class="fas fa-clock"></i> <?php echo $tour['duration_days']; ?> days</span>
                                        <span><i class="fas fa-users"></i>
                                            <?php echo $tour['min_group_size']; ?>-<?php echo $tour['max_group_size']; ?>
                                            people</span>
                                        <span><i class="fas fa-dollar-sign"></i> From
                                            $<?php echo number_format($tour['price_adult']); ?></span>
                                    </div>

                                    <a href="tours.php" class="btn btn-primary btn-block">
                                        Get Full Details
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Main destinations page -->
        <section class="destinations-hero">
            <div class="container">
                <h1>Explore Our Destinations</h1>
                <p>Discover amazing places around the world with Forever Young Tours</p>
            </div>
        </section>

        <div class="container">
            <!-- Continent Filters -->
            <div class="continent-filters">
                <a href="#" class="continent-filter active" data-continent="all">All Destinations</a>
                <?php foreach (array_keys($continents) as $continent): ?>
                    <a href="#" class="continent-filter" data-continent="<?php echo strtolower($continent); ?>">
                        <?php echo htmlspecialchars($continent); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Interactive Map -->
            <div id="worldMap" class="map-container"></div>

            <!-- Countries Grid -->
            <div class="countries-grid" id="countriesGrid">
                <?php foreach ($countries as $country): ?>
                    <div class="country-card" data-continent="<?php echo strtolower($country['continent'] ?: 'other'); ?>"
                        onclick="window.location.href='destinations.php?country=<?php echo strtolower(str_replace(' ', '-', $country['name'])); ?>'">
                        <div class="country-image">
                            <?php if ($country['flag_image']): ?>
                                <img src="<?php echo htmlspecialchars($country['flag_image']); ?>"
                                    alt="<?php echo htmlspecialchars($country['name']); ?>" class="country-flag">
                            <?php endif; ?>
                        </div>
                        <div class="country-info">
                            <h3 class="country-name"><?php echo htmlspecialchars($country['name']); ?></h3>
                            <div class="country-stats">
                                <span><i class="fas fa-route"></i> <?php echo $country['tour_count']; ?> Tours</span>
                                <span><i class="fas fa-dollar-sign"></i> From
                                    $<?php echo number_format($country['min_price']); ?></span>
                            </div>
                            <p class="country-description">
                                <?php echo htmlspecialchars(substr($country['description'], 0, 120)); ?>...
                            </p>
                            <div class="btn btn-primary btn-block">
                                Explore Tours <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Contact Form Modal -->
    <div id="contactModal" class="contact-form-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeContactForm()">&times;</span>
            <h3>Get Full Tour Details</h3>
            <p>Please provide your contact information to access complete tour details and pricing.</p>

            <form id="contactForm">
                <input type="hidden" id="tourId" name="tour_id">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" required class="form-control">
                </div>
                <div class="form-group">
                    <label for="message">Additional Message</label>
                    <textarea id="message" name="message" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    Get Tour Details
                </button>
            </form>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>

    <script>
        // Initialize map
        let map;
        let markers = [];

        function initMap() {
            map = L.map('worldMap', {
                zoomControl: false,
                scrollWheelZoom: false,
                touchZoom: true,
                doubleClickZoom: true,
                boxZoom: true,
                keyboard: true,
                fadeAnimation: true,
                zoomAnimation: true,
            }).setView([20, 0], 2);

            // Add custom zoom controls
            L.control.zoom({
                position: 'topright'
            }).addTo(map);

            // Use a more elegant tile layer
            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                subdomains: 'abcd',
                maxZoom: 19,
                minZoom: 2,
            }).addTo(map);

            // Add custom style for markers
            const goldIcon = L.divIcon({
                className: 'custom-marker',
                html: '<div class="marker-pin"></div><i class="fas fa-map-marker-alt"></i>',
                iconSize: [30, 42],
                iconAnchor: [15, 42],
                popupAnchor: [0, -42]
            });

            // Add markers for countries
            const countries = <?php echo json_encode($countries); ?>;
            countries.forEach(country => {
                if (country.latitude && country.longitude) {
                    const marker = L.marker([country.latitude, country.longitude], {
                        icon: goldIcon,
                        riseOnHover: true
                    })
                        .addTo(map)
                        .bindPopup(`
                        <div class="map-popup">
                            <h4>${country.name}</h4>
                            <div class="popup-stats">
                                <span><i class="fas fa-route"></i> ${country.tour_count} tours</span>
                                <span><i class="fas fa-dollar-sign"></i> From $${country.min_price.toLocaleString()}</span>
                            </div>
                            <a href="destinations.php?country=${country.name.toLowerCase().replace(' ', '-')}" 
                               class="popup-btn">Explore Tours</a>
                        </div>
                    `, {
                            maxWidth: 250,
                            minWidth: 200,
                            className: 'custom-popup'
                        });

                    markers.push(marker);
                }
            });

            // Add custom styles to the map
            const style = document.createElement('style');
            style.textContent = `
                .custom-marker {
                    position: relative;
                    width: 30px;
                    height: 42px;
                    text-align: center;
                }
                .marker-pin {
                    position: absolute;
                    width: 30px;
                    height: 30px;
                    background: var(--gold);
                    border-radius: 50% 50% 50% 0;
                    transform: rotate(-45deg);
                    left: 0;
                    top: 0;
                    margin: 5px 0 0 -12px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                }
                .custom-marker i {
                    position: relative;
                    color: var(--black);
                    font-size: 18px;
                    margin-top: 7px;
                    z-index: 1;
                }
                .custom-popup {
                    border-radius: 8px;
                    padding: 15px;
                    box-shadow: 0 3px 14px rgba(0,0,0,0.15);
                    border: none;
                }
                .custom-popup h4 {
                    margin: 0 0 10px;
                    color: var(--black);
                    font-size: 1.1em;
                }
                .popup-stats {
                    display: flex;
                    gap: 15px;
                    margin-bottom: 15px;
                    font-size: 0.9em;
                    color: var(--black-light);
                }
                .popup-stats i {
                    color: var(--gold);
                    margin-right: 5px;
                }
                .popup-btn {
                    display: inline-block;
                    background: var(--gold);
                    color: var(--black);
                    padding: 8px 15px;
                    border-radius: 5px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 0.9em;
                    transition: all 0.3s ease;
                }
                .popup-btn:hover {
                    background: var(--gold-dark);
                }
                .leaflet-popup-tip {
                    background: white;
                }
                .leaflet-popup-content-wrapper {
                    border-radius: 8px;
                    padding: 0;
                }
            `;
            document.head.appendChild(style);
        }

        // Continent filtering
        document.querySelectorAll('.continent-filter').forEach(filter => {
            filter.addEventListener('click', function (e) {
                e.preventDefault();

                // Update active filter
                document.querySelectorAll('.continent-filter').forEach(f => f.classList.remove('active'));
                this.classList.add('active');

                const continent = this.dataset.continent;
                const countryCards = document.querySelectorAll('.country-card');

                countryCards.forEach(card => {
                    if (continent === 'all' || card.dataset.continent === continent) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Contact form functions
        function showContactForm(tourId) {
            document.getElementById('tourId').value = tourId;
            document.getElementById('contactModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeContactForm() {
            document.getElementById('contactModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Handle contact form submission
        document.getElementById('contactForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('api/tour-inquiry.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Thank you! We will send you the full tour details shortly.');
                        closeContactForm();
                        this.reset();

                        // Redirect to tour details page
                        if (data.tour_slug) {
                            window.location.href = `tour-details.php?slug=${data.tour_slug}`;
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
        });

        // Initialize map when page loads
        <?php if (!$selected_country): ?>
            document.addEventListener('DOMContentLoaded', function () {
                if (document.getElementById('worldMap')) {
                    initMap();
                }
            });
        <?php endif; ?>

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('contactModal');
            if (event.target === modal) {
                closeContactForm();
            }
        }
    </script>
</body>

</html>