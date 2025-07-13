<?php
require_once 'config/config.php';

$page_title = 'About Us - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description"
        content="Learn about Forever Young Tours - your trusted partner for luxury group travel and adventure tours across five continents.">

    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    <style>
        .page-hero {
            position: relative;
            height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background:
                linear-gradient(135deg, rgba(212, 165, 116, 0.9), rgba(102, 126, 234, 0.9)),
                url('../assets/images/about.jpg') center/cover no-repeat;

            color: white;
        }

        .page-hero-content {
            text-align: center;
            z-index: 2;
        }

        .page-hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .page-hero p {
            font-size: 1.3rem;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.95;
        }

        .our-story {
            padding: 80px 0;
            background: white;
        }

        .story-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .story-text h2 {
            color: var(--primary-black);
            margin-bottom: 2rem;
        }

        .story-text p {
            margin-bottom: 1.5rem;
            line-height: 1.8;
            color: var(--black-lighter);
        }

        .story-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .stat {
            text-align: center;
            padding: 20px;
            background: var(--gray-100);
            border-radius: 15px;
        }

        .stat h3 {
            font-size: 2.5rem;
            color: var(--primary-gold);
            margin-bottom: 5px;
            font-weight: 700;
        }

        .stat p {
            color: var(--black-lighter);
            font-weight: 600;
            margin: 0;
        }

        .story-image {
            position: relative;
        }

        .story-image img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: var(--shadow-lg);
        }

        .mission-vision {
            background: var(--gray-100);
            padding: 80px 0;
        }

        .mission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-top: 50px;
        }

        .mission-card {
            background: white;
            padding: 40px 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .mission-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .mission-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-gold), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
        }

        .mission-card h3 {
            color: var(--primary-black);
            margin-bottom: 15px;
        }

        .mission-card p {
            color: var(--black-lighter);
            line-height: 1.7;
        }

        .team-section {
            padding: 80px 0;
            background: white;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
            margin-top: 50px;
        }

        .team-member {
            text-align: center;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .team-member:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .member-image {
            height: 300px;
            overflow: hidden;
        }

        .member-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-info {
            padding: 30px 20px;
        }

        .member-info h4 {
            color: var(--primary-black);
            margin-bottom: 5px;
        }

        .member-role {
            color: var(--primary-gold);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .member-bio {
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
            color: var(--black-lighter);
        }

        .member-social {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .member-social a {
            width: 40px;
            height: 40px;
            background: var(--gray-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--black-lighter);
            transition: var(--transition);
        }

        .member-social a:hover {
            background: var(--primary-gold);
            color: white;
        }

        .sustainability {
            background: var(--gray-100);
            padding: 80px 0;
        }

        .sustainability-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .sustainability-text h2 {
            color: var(--primary-black);
            margin-bottom: 2rem;
        }

        .sustainability-text p {
            margin-bottom: 2rem;
            line-height: 1.8;
            color: var(--black-lighter);
        }

        .sustainability-points {
            margin-top: 30px;
        }

        .point {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            align-items: flex-start;
        }

        .point i {
            color: var(--primary-gold);
            font-size: 1.5rem;
            margin-top: 5px;
            flex-shrink: 0;
        }

        .point div h4 {
            color: var(--primary-black);
            margin-bottom: 8px;
        }

        .point div p {
            color: var(--black-lighter);
            margin: 0;
            line-height: 1.6;
        }

        .sustainability-image img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: var(--shadow-lg);
        }

        .awards {
            padding: 80px 0;
            background: white;
        }

        .awards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .award-item {
            text-align: center;
            padding: 30px 20px;
            background: var(--gray-100);
            border-radius: 15px;
            transition: var(--transition);
        }

        .award-item:hover {
            transform: translateY(-5px);
            background: white;
            box-shadow: var(--shadow-lg);
        }

        .award-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-gold), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 1.5rem;
        }

        .award-item h4 {
            color: var(--primary-black);
            margin-bottom: 10px;
        }

        .award-item p {
            color: var(--black-lighter);
            margin: 0;
        }

        .cta-section {
            background: linear-gradient(135deg, var(--primary-gold), var(--gold-dark));
            color: white;
            padding: 80px 0;
            text-align: center;
        }

        .cta-content h2 {
            color: white;
            margin-bottom: 20px;
            font-size: 2.5rem;
        }

        .cta-content p {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.9;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-white {
            background: white;
            color: var(--primary-gold);
            border: 2px solid white;
        }

        .btn-white:hover {
            background: transparent;
            color: white;
            border-color: white;
        }

        .btn-outline-white {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-outline-white:hover {
            background: white;
            color: var(--primary-gold);
        }

        @media (max-width: 768px) {
            .page-hero h1 {
                font-size: 2.5rem;
            }

            .story-content,
            .sustainability-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .story-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .mission-grid,
            .team-grid,
            .awards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="page-hero">
        <div class="container">
            <div class="page-hero-content">
                <h1>About Forever Young Tours</h1>
                <p>Your trusted partner for luxury group travel and adventure tours across five continents</p>
            </div>
        </div>
    </section>

    <!-- Our Story -->
    <section class="our-story">
        <div class="container">
            <div class="story-content">
                <div class="story-text">
                    <h2>Our Story</h2>
                    <p>Founded in 2015 in the heart of Rwanda, Forever Young Tours was born from a simple belief: travel
                        should transform you. Our founders, passionate travelers themselves, recognized that the most
                        meaningful journeys happen when you step outside your comfort zone and embrace the unknown.</p>

                    <p>What started as a small local tour company has grown into a trusted partner for adventurous
                        travelers from around the world. We've guided thousands of guests through life-changing
                        experiences, from intimate encounters with mountain gorillas to cultural immersions in remote
                        villages.</p>

                    <p>Today, we operate across five continents, but our core values remain unchanged: authentic
                        experiences, sustainable tourism, and the belief that travel keeps us forever young at heart.
                    </p>

                    <div class="story-stats">
                        <div class="stat">
                            <h3>10,000+</h3>
                            <p>Happy Travelers</p>
                        </div>
                        <div class="stat">
                            <h3>50+</h3>
                            <p>Destinations</p>
                        </div>
                        <div class="stat">
                            <h3>8</h3>
                            <p>Years Experience</p>
                        </div>
                        <div class="stat">
                            <h3>98%</h3>
                            <p>Satisfaction Rate</p>
                        </div>
                    </div>
                </div>

                <div class="story-image">
                    <img src="assets/images/about.jpg" alt="Our Founders">
                </div>
            </div>
        </div>
    </section>

    <!-- Our Mission -->
    <section class="mission-vision">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Our Mission & Values</h2>
                <p class="section-subtitle">What drives us to create extraordinary travel experiences</p>
            </div>

            <div class="mission-grid">
                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-compass"></i>
                    </div>
                    <h3>Our Mission</h3>
                    <p>To create transformative travel experiences that connect people with diverse cultures, stunning
                        landscapes, and authentic local communities while promoting sustainable tourism practices.</p>
                </div>

                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3>Our Vision</h3>
                    <p>To be the world's leading provider of authentic, sustainable travel experiences that inspire
                        global understanding and preserve the beauty of our planet for future generations.</p>
                </div>

                <div class="mission-card">
                    <div class="mission-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Our Values</h3>
                    <p>Authenticity, sustainability, safety, and respect for local cultures guide everything we do. We
                        believe in responsible tourism that benefits both travelers and destinations.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Meet Our Team</h2>
                <p class="section-subtitle">The passionate individuals who make your travel dreams come true</p>
            </div>

            <div class="team-grid">

                <div class="team-member">
                    <div class="member-image">
                        <img src="assets/images/placeholder-user.jpg" alt="Grace Mukamana">
                    </div>
                    <div class="member-info">
                        <h4>Grace Mukamana</h4>
                        <p class="member-role">Operations Manager</p>
                        <p class="member-bio">Grace ensures every detail of your journey is perfectly planned. Her
                            attention to detail and local knowledge create seamless experiences.</p>
                        <div class="member-social">
                            <a href="#"><i class="fab fa-linkedin"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-image">
                        <img src="assets/images/placeholder-user.jpg" alt="Patrick Nzeyimana">
                    </div>
                    <div class="member-info">
                        <h4>Patrick Nzeyimana</h4>
                        <p class="member-role">Lead Guide</p>
                        <p class="member-bio">Patrick's expertise in wildlife and conservation, combined with his
                            storytelling ability, makes every safari an unforgettable adventure.</p>
                        <div class="member-social">
                            <a href="#"><i class="fab fa-facebook"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>

                <div class="team-member">
                    <div class="member-image">
                        <img src="assets/images/placeholder-user.jpg" alt="Sarah Johnson">
                    </div>
                    <div class="member-info">
                        <h4>Sarah Johnson</h4>
                        <p class="member-role">Customer Experience Manager</p>
                        <p class="member-bio">Sarah's dedication to exceptional service ensures that every traveler
                            feels valued and supported throughout their journey with us.</p>
                        <div class="member-social">
                            <a href="#"><i class="fab fa-linkedin"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sustainability -->
    <section class="sustainability">
        <div class="container">
            <div class="sustainability-content">
                <div class="sustainability-text">
                    <h2>Our Commitment to Sustainability</h2>
                    <p>We believe that travel should leave a positive impact on the destinations we visit. Our
                        commitment to sustainable tourism goes beyond just protecting the environment – it's about
                        creating meaningful connections between travelers and local communities.</p>

                    <div class="sustainability-points">
                        <div class="point">
                            <i class="fas fa-leaf"></i>
                            <div>
                                <h4>Environmental Protection</h4>
                                <p>We partner with eco-lodges, minimize waste, and support conservation projects in
                                    every destination we visit.</p>
                            </div>
                        </div>

                        <div class="point">
                            <i class="fas fa-users"></i>
                            <div>
                                <h4>Community Support</h4>
                                <p>We work with local guides, stay in locally-owned accommodations, and ensure tourism
                                    benefits reach local communities.</p>
                            </div>
                        </div>

                        <div class="point">
                            <i class="fas fa-paw"></i>
                            <div>
                                <h4>Wildlife Conservation</h4>
                                <p>A portion of every booking goes directly to wildlife conservation efforts, helping
                                    protect endangered species and their habitats.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sustainability-image">
                    <img src="assets/images/about-story.jpg" alt="Sustainability Efforts">
                </div>
            </div>
        </div>
    </section>

    <!-- Awards & Recognition -->
    <section class="awards">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Awards & Recognition</h2>
                <p class="section-subtitle">Our commitment to excellence has been recognized by industry leaders</p>
            </div>

            <div class="awards-grid">
                <div class="award-item">
                    <div class="award-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h4>Best Tour Operator 2023</h4>
                    <p>Rwanda Tourism Board</p>
                </div>

                <div class="award-item">
                    <div class="award-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h4>Excellence in Service</h4>
                    <p>TripAdvisor Travelers' Choice</p>
                </div>

                <div class="award-item">
                    <div class="award-icon">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <h4>Sustainable Tourism Award</h4>
                    <p>East Africa Tourism Association</p>
                </div>

                <div class="award-item">
                    <div class="award-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h4>Community Impact Award</h4>
                    <p>Global Sustainable Tourism Council</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Start Your Adventure?</h2>
                <p>Join thousands of travelers who have discovered the magic of authentic, sustainable travel with
                    Forever Young Tours.</p>
                <div class="cta-buttons">
                    <a href="tours.php" class="btn btn-white btn-lg">Explore Tours</a>
                    <a href="contact.php" class="btn btn-outline-white btn-lg">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
</body>

</html>