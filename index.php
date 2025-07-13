<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';

// Get featured tours
$featured_tours = $db->query("
    SELECT t.*, c.name as country_name, tc.name as category_name,
           AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
    FROM tours t
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN tour_categories tc ON t.category_id = tc.id
    LEFT JOIN reviews r ON t.id = r.tour_id
    WHERE t.status = 'active' AND t.featured = 1
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Get destination stats
$destination_stats = $db->query("
    SELECT 
        c.name as continent,
        COUNT(DISTINCT co.id) as country_count,
        COUNT(t.id) as tour_count
    FROM continents c
    LEFT JOIN countries co ON c.id = co.continent_id
    LEFT JOIN tours t ON co.id = t.country_id AND t.status = 'active'
    GROUP BY c.id, c.name
    ORDER BY tour_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Forever Young Tours - Travel Bold. Stay Forever Young.';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description"
        content="Discover breathtaking destinations with Forever Young Tours. Experience luxury group travel, adventure tours, and cultural exchanges across five continents.">

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <style>
        /* Main Color Scheme */
        :root {
            --gold-primary: #D4AF37;
            --gold-light: #f4e4a6;
            --gold-dark: #b8941f;
            --black-primary: #000000;
            --black-light: #333333;
            --black-lighter: #666666;
            --white-primary: #FFFFFF;
            --green-accent: #228B22;
            --green-light: #32cd32;
            --green-dark: #006400;
            --shadow-gold: 0 4px 12px rgba(212, 175, 55, 0.3);
            --transition: all 0.3s ease;
        }

        /* Hero Section */
        .hero {
            position: relative;
            height: 100vh;
            min-height: 600px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--white-primary);
            overflow: hidden;
            background: linear-gradient(135deg, rgba(212, 166, 116, 0.75), rgba(102, 234, 102, 0.78)),
                url('https://images.unsplash.com/photo-1464037866556-6812c9d1c72e?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80') center/cover;
        }

        .hero-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            padding: 0 20px;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            font-family: 'Playfair Display', serif;
        }

        .hero-description {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .hero-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }

        .play-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: 2px solid var(--white-primary);
            border-radius: 50px;
            color: var(--white-primary);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .play-button:hover {
            background: var(--white-primary);
            color: var(--black-primary);
        }

        .hero-scroll {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            animation: bounce 2s infinite;
            cursor: pointer;
        }

        @keyframes bounce {

            0%,
            20%,
            50%,
            80%,
            100% {
                transform: translateY(0) translateX(-50%);
            }

            40% {
                transform: translateY(-20px) translateX(-50%);
            }

            60% {
                transform: translateY(-10px) translateX(-50%);
            }
        }

        /* Sections */
        section {
            padding: 5rem 0;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            font-family: 'Playfair Display', serif;
        }

        .section-header p {
            font-size: 1.1rem;
            color: var(--black-lighter);
            max-width: 700px;
            margin: 0 auto;
        }

        /* Destinations Overview */
        .destinations-overview {
            background: var(--white-primary);
        }

        .destination-filters {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .filter-chip {
            padding: 0.5rem 1.25rem;
            background: var(--white-primary);
            border: 1px solid var(--gold-primary);
            border-radius: 50px;
            color: var(--gold-primary);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .filter-chip:hover,
        .filter-chip.active {
            background: var(--gold-primary);
            color: var(--white-primary);
        }

        .world-map-container {
            margin: 3rem 0;
        }

        .world-map {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }

        .map-image {
            width: 100%;
            height: auto;
            display: block;
        }

        .map-marker {
            position: absolute;
            transform: translate(-50%, -50%);
            cursor: pointer;
        }

        .marker-dot {
            width: 12px;
            height: 12px;
            background: var(--gold-primary);
            border-radius: 50%;
            border: 2px solid var(--white-primary);
            box-shadow: 0 0 0 2px var(--gold-primary);
        }

        .marker-popup {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--white-primary);
            padding: 0.75rem 1rem;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            min-width: 150px;
            display: none;
        }

        .marker-popup h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
            color: var(--black-primary);
        }

        .marker-popup p {
            font-size: 0.8rem;
            color: var(--black-lighter);
            margin: 0;
        }

        .map-marker:hover .marker-popup {
            display: block;
        }

        /* Tours Grid */
        .tours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .tour-card {
            background: var(--white-primary);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .tour-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-gold);
        }

        .tour-image {
            position: relative;
            height: 250px;
            overflow: hidden;
        }

        .tour-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .tour-card:hover .tour-image img {
            transform: scale(1.05);
        }

        .tour-badges {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--white-primary);
            text-align: center;
        }

        .badge.discount {
            background: linear-gradient(135deg, var(--green-accent), var(--green-dark));
        }

        .badge.availability {
            background: var(--gold-primary);
        }

        .tour-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: var(--transition);
        }

        .tour-card:hover .tour-overlay {
            opacity: 1;
        }

        .tour-link {
            color: var(--white-primary);
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--white-primary);
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
        }

        .tour-link:hover {
            background: var(--white-primary);
            color: var(--gold-primary);
        }

        .tour-content {
            padding: 1.5rem;
        }

        .tour-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .tour-category {
            font-size: 0.85rem;
            color: var(--gold-primary);
            font-weight: 600;
        }

        .tour-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .tour-rating i {
            color: var(--gold-primary);
            font-size: 0.9rem;
        }

        .tour-rating span {
            font-size: 0.85rem;
            color: var(--black-lighter);
            margin-left: 0.25rem;
        }

        .tour-title {
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
        }

        .tour-title a {
            color: var(--black-primary);
            transition: var(--transition);
        }

        .tour-title a:hover {
            color: var(--gold-primary);
        }

        .tour-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--black-lighter);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .tour-location i {
            color: var(--gold-primary);
        }

        .tour-details {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: var(--black-lighter);
        }

        .tour-details i {
            color: var(--gold-primary);
            margin-right: 0.25rem;
        }

        .tour-highlights {
            margin-bottom: 1.5rem;
        }

        .tour-highlights h4 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: var(--black-primary);
        }

        .tour-highlights ul {
            list-style: none;
            padding-left: 1.25rem;
        }

        .tour-highlights li {
            position: relative;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .tour-highlights li::before {
            content: "•";
            position: absolute;
            left: -1rem;
            color: var(--gold-primary);
        }

        .tour-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .tour-price {
            display: flex;
            align-items: baseline;
            gap: 0.25rem;
        }

        .price-from {
            font-size: 0.8rem;
            color: var(--black-lighter);
        }

        .price-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gold-primary);
        }

        .price-per {
            font-size: 0.8rem;
            color: var(--black-lighter);
        }

        /* Why Choose Us */
        .why-choose {
            background: #f8f9fa;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .value-card {
            background: var(--white-primary);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .value-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-gold);
        }

        .value-icon {
            width: 60px;
            height: 60px;
            background: var(--gold-primary);
            color: var(--white-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
        }

        .value-card h3 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: var(--black-primary);
        }

        .value-card p {
            color: var(--black-lighter);
            margin: 0;
        }

        /* Testimonials */
        .testimonials {
            background: var(--white-primary);
        }

        .testimonials-carousel {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .testimonial-card {
            background: var(--white-primary);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .testimonial-quote {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            color: var(--gold-primary);
            opacity: 0.2;
            font-size: 3rem;
        }

        .testimonial-content {
            margin-bottom: 1.5rem;
            padding-top: 1rem;
        }

        .testimonial-content p {
            font-style: italic;
            color: var(--black-light);
            position: relative;
            z-index: 1;
        }

        .testimonial-author {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .author-info h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
            color: var(--black-primary);
        }

        .author-info span {
            font-size: 0.8rem;
            color: var(--black-lighter);
        }

        .testimonial-rating i {
            color: var(--gold-primary);
            font-size: 0.9rem;
        }

        /* Newsletter Section - Specific Styling */
        .newsletter {
            position: relative;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),
                url('https://images.unsplash.com/photo-1506929562872-bb421503ef21?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80') center/cover;
            background-size: cover;
            color: var(--white-primary);
            text-align: center;
            padding: 5rem 0;
        }

        .newsletter .container {
            position: relative;
            z-index: 1;
        }

        .newsletter-content {
            max-width: 700px;
            margin: 0 auto;
        }

        .newsletter-text h2 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            font-family: 'Playfair Display', serif;
        }

        .newsletter-text p {
            margin-bottom: 2.5rem;
            font-size: 1.1rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Specific targeting for newsletter form only */
        .newsletter-form #newsletterForm {
            max-width: 500px;
            margin: 0 auto;
        }

        .newsletter-form #newsletterForm .form-group {
            position: relative;
            display: flex;
            width: 100%;
        }

        .newsletter-form #newsletterForm input[type="email"] {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            width: 100%;
            padding-right: 120px;
            /* Space for button */
        }

        .newsletter-form #newsletterForm button[type="submit"] {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            padding: 0 1.5rem;
            background: var(--gold-primary);
            color: var(--white-primary);
            border: none;
            border-radius: 0 50px 50px 0;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .newsletter-form #newsletterForm button[type="submit"]:hover {
            background: var(--gold-dark);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .newsletter-text h2 {
                font-size: 2rem;
            }

            .newsletter-text p {
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .newsletter-form #newsletterForm input[type="email"] {
                padding: 1rem;
                padding-right: 1rem;
                border-radius: 50px;
            }

            .newsletter-form #newsletterForm button[type="submit"] {
                position: relative;
                width: 100%;
                margin-top: 1rem;
                border-radius: 50px;
                padding: 0.75rem;
                height: auto;
            }
        }

        /* Section Footer */
        .section-footer {
            margin-top: 3rem;
            text-align: center;
        }

        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: 2px solid var(--gold-primary);
            border-radius: 50px;
            color: var(--gold-primary);
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-outline:hover {
            background: var(--gold-primary);
            color: var(--white-primary);
        }

        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .section-header h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            .hero {
                height: auto;
                min-height: 100vh;
                padding: 100px 0;
            }

            .hero-title {
                font-size: 2rem;
            }

            .hero-description {
                font-size: 1rem;
            }

            .tours-grid {
                grid-template-columns: 1fr;
            }

            .tour-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 1.8rem;
            }

            .section-header h2 {
                font-size: 1.5rem;
            }

            .newsletter-form .form-group {
                flex-direction: column;
            }

            .newsletter-form input,
            .newsletter-form button {
                border-radius: 50px;
                width: 100%;
            }

            .newsletter-form button {
                margin-top: 1rem;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-media">
            <video autoplay muted loop class="hero-video">
                <source src="assets/video/here-video.mp4" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>

        <div class="hero-overlay"></div>

        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">
                    <?php echo $lang['hero_title']; ?><br>
                    <span style="color: #d4a574;"><?php echo $lang['hero_subtitle']; ?></span>
                </h1>
                <p class="hero-description"><?php echo $lang['hero_description']; ?></p>



                <div class="hero-actions">
                    <button class="play-button" onclick="playVideo()">
                        <i class="fas fa-play"></i>
                        <?php echo $lang['watch_our_story']; ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="hero-scroll">
            <i class="fas fa-chevron-down"></i>
        </div>
    </section>

    <!-- Destinations Overview -->
    <section class="destinations-overview">
        <div class="container">
            <div class="section-header">
                <h2>Explore the <span style="color: #d4a574;">World</span> with FYT</h2>
                <p>Discover breathtaking destinations across five continents, each offering unique experiences and
                    unforgettable memories. From African safaris to Caribbean beaches, European culture to Asian
                    adventures.</p>
            </div>

            <div class="destination-filters">
                <?php foreach ($destination_stats as $stat): ?>
                    <div class="filter-chip" data-continent="<?php echo strtolower($stat['continent']); ?>">
                        <span class="continent-name"><?php echo $stat['continent']; ?></span>
                        <span class="tour-count">(<?php echo $stat['tour_count']; ?>)</span>
                    </div>
                <?php endforeach; ?>

                <label class="featured-only">
                    <input type="checkbox" id="featuredOnly">
                    <span>Show featured destinations only</span>
                </label>
            </div>

            <div class="world-map-container">
                <div class="world-map">
                    <!-- Interactive world map would go here -->
                    <img src="/placeholder.svg?height=400&width=800" alt="World Map" class="map-image">

                    <!-- Destination markers -->
                    <div class="map-marker" style="top: 45%; left: 15%;" data-destination="rwanda">
                        <div class="marker-dot"></div>
                        <div class="marker-popup">
                            <h4>Rwanda</h4>
                            <p>Mountain Gorillas & Culture</p>
                        </div>
                    </div>

                    <div class="map-marker" style="top: 35%; left: 50%;" data-destination="europe">
                        <div class="marker-dot"></div>
                        <div class="marker-popup">
                            <h4>Europe</h4>
                            <p>Historic Cities & Culture</p>
                        </div>
                    </div>

                    <div class="map-marker" style="top: 55%; left: 25%;" data-destination="caribbean">
                        <div class="marker-dot"></div>
                        <div class="marker-popup">
                            <h4>Caribbean</h4>
                            <p>Tropical Paradise</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Tours -->
    <section class="featured-tours">
        <div class="container">
            <div class="section-header">
                <h2><?php echo $lang['featured_tours']; ?></h2>
                <p>Handpicked experiences that showcase the best of each destination</p>
            </div>

            <div class="tours-grid">
                <?php foreach ($featured_tours as $tour): ?>
                    <div class="tour-card" data-category="<?php echo strtolower($tour['category_name']); ?>">
                        <div class="tour-image">
                            <?php if ($tour['featured_image']): ?>
                                <img src="<?php echo htmlspecialchars($tour['featured_image']); ?>"
                                    alt="<?php echo htmlspecialchars($tour['title']); ?>">
                            <?php else: ?>
                                <img src="/placeholder.svg?height=250&width=350"
                                    alt="<?php echo htmlspecialchars($tour['title']); ?>">
                            <?php endif; ?>

                            <div class="tour-badges">
                                <?php if ($tour['discount_percentage'] > 0): ?>
                                    <span class="badge discount">Save
                                        $<?php echo number_format($tour['price_adult'] * $tour['discount_percentage'] / 100); ?></span>
                                <?php endif; ?>
                                <span class="badge availability"><?php echo rand(3, 15); ?> spots left</span>
                            </div>

                            <div class="tour-overlay">
                                <a href="tour-details.php?id=<?php echo $tour['id']; ?>" class="tour-link">
                                    <?php echo $lang['view_details']; ?>
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
                                    <span><?php echo number_format($rating, 1); ?>
                                        (<?php echo $tour['review_count'] ?: rand(50, 200); ?>)</span>
                                </div>
                            </div>

                            <h3 class="tour-title">
                                <a href="tour-details.php?id=<?php echo $tour['id']; ?>">
                                    <?php echo htmlspecialchars($tour['title']); ?>
                                </a>
                            </h3>

                            <div class="tour-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($tour['country_name']); ?>
                            </div>

                            <div class="tour-details">
                                <div class="tour-duration">
                                    <i class="fas fa-clock"></i>
                                    <?php echo $tour['duration_days']; ?>     <?php echo $lang['days']; ?> /
                                    <?php echo $tour['duration_nights']; ?>     <?php echo $lang['nights']; ?>
                                </div>

                                <div class="tour-group">
                                    <i class="fas fa-users"></i>
                                    <?php echo $tour['min_participants']; ?>-<?php echo $tour['max_participants']; ?> people
                                </div>
                            </div>

                            <div class="tour-highlights">
                                <h4>Highlights:</h4>
                                <ul>
                                    <?php
                                    $highlights = explode("\n", $tour['highlights']);
                                    foreach (array_slice($highlights, 0, 3) as $highlight):
                                        if (trim($highlight)):
                                            ?>
                                            <li><?php echo htmlspecialchars(trim($highlight)); ?></li>
                                            <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </ul>
                            </div>

                            <div class="tour-footer">
                                <div class="tour-price">
                                    <span class="price-from"><?php echo $lang['from']; ?></span>
                                    <span class="price-amount">
                                        <?php echo formatCurrency($tour['price_adult']); ?>
                                    </span>
                                    <span class="price-per"><?php echo $lang['per_person']; ?></span>
                                </div>

                                <a href="book.php?tour=<?php echo $tour['id']; ?>" class="btn btn-primary">
                                    <?php echo $lang['book_now']; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="section-footer">
                <a href="tours.php" class="btn btn-outline btn-lg">
                    View All Tours <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="why-choose">
        <div class="container">
            <div class="section-header">
                <h2>Why Choose Forever Young Tours?</h2>
                <p>We're committed to creating extraordinary travel experiences that exceed your expectations</p>
            </div>

            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3>Expert Guides</h3>
                    <p>Our local guides are passionate experts who bring destinations to life with insider knowledge and
                        authentic stories.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Small Groups</h3>
                    <p>Intimate group sizes ensure personalized attention and authentic connections with fellow
                        travelers.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <h3>Sustainable Travel</h3>
                    <p>We're committed to responsible tourism that benefits local communities and protects natural
                        environments.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Safety First</h3>
                    <p>Your safety is our priority with comprehensive insurance, emergency support, and carefully vetted
                        accommodations.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Authentic Experiences</h3>
                    <p>Go beyond tourist attractions to experience genuine local culture, cuisine, and traditions.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>24/7 Support</h3>
                    <p>Round-the-clock support before, during, and after your trip ensures peace of mind throughout your
                        journey.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials">
        <div class="container">
            <div class="section-header">
                <h2>What Our Travelers Say</h2>
                <p>Real stories from real travelers who've experienced the magic of Forever Young Tours</p>
            </div>

            <div class="testimonials-carousel">
                <div class="testimonial-card">
                    <div class="testimonial-quote">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <div class="testimonial-content">
                        <p>"The Rwanda gorilla trek was absolutely life-changing. Our guide was incredibly
                            knowledgeable, and seeing these magnificent creatures in their natural habitat was beyond
                            words. Forever Young Tours made every detail perfect."</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-info">
                            <h4>Sarah Johnson</h4>
                            <span>Wildlife Photographer, USA</span>
                        </div>
                        <div class="testimonial-rating">
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-quote">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <div class="testimonial-content">
                        <p>"Our European cultural tour exceeded all expectations. The small group size allowed for
                            intimate experiences, and our guide's passion for history brought every city to life. Highly
                            recommend!"</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-info">
                            <h4>Michael Chen</h4>
                            <span>Teacher, Canada</span>
                        </div>
                        <div class="testimonial-rating">
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-quote">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <div class="testimonial-content">
                        <p>"The Caribbean island hopping adventure was pure paradise. Beautiful accommodations, amazing
                            food, and the perfect balance of adventure and relaxation. Can't wait to book our next
                            trip!"</p>
                    </div>
                    <div class="testimonial-author">
                        <div class="author-info">
                            <h4>Emma Thompson</h4>
                            <span>Marketing Executive, UK</span>
                        </div>
                        <div class="testimonial-rating">
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                            <i class="fas fa-star active"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter -->
    <section class="newsletter">
        <div class="container">
            <div class="newsletter-content">
                <div class="newsletter-text">
                    <h2 style="color: #b8941f;">Stay Connected</h2>
                    <p style="color: white;">Get exclusive travel tips, destination guides, and special offers delivered
                        to your inbox. Join
                        our community of adventurous travelers!</p>
                </div>

                <div class="newsletter-form">
                    <form id="newsletterForm">
                        <div class="form-group">
                            <input type="email" name="email" placeholder="Enter your email address" required>
                            <button type="submit" class="btn btn-light">
                                <?php echo $lang['subscribe']; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
    <script>
        // Play video function
        function playVideo() {
            // Video modal implementation
            alert('Video player would open here');
        }

        // Map marker interactions
        document.querySelectorAll('.map-marker').forEach(marker => {
            marker.addEventListener('mouseenter', function () {
                this.querySelector('.marker-popup').style.display = 'block';
            });

            marker.addEventListener('mouseleave', function () {
                this.querySelector('.marker-popup').style.display = 'none';
            });
        });

        // Filter functionality
        document.querySelectorAll('.filter-chip').forEach(chip => {
            chip.addEventListener('click', function () {
                const continent = this.dataset.continent;
                // Filter tours by continent
                console.log('Filter by:', continent);
            });
        });
    </script>
</body>

</html>