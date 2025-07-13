<?php
require_once 'config/config.php';

$tour_slug = $_GET['slug'] ?? '';
if (!$tour_slug) {
    header('Location: destinations.php');
    exit;
}

// Get tour details
$stmt = $db->prepare("
    SELECT t.*, 
           c.name as country_name, c.flag_image as country_flag,
           r.name as region_name,
           tc.name as category_name, tc.icon as category_icon,
           u.first_name as created_by_name, u.last_name as created_by_lastname
    FROM tours t
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN regions r ON t.region_id = r.id
    LEFT JOIN tour_categories tc ON t.category_id = tc.id
    LEFT JOIN users u ON t.created_by = u.id
    WHERE t.slug = ? AND t.status = 'active'
");
$stmt->execute([$tour_slug]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header('Location: destinations.php');
    exit;
}

// Update view count
$stmt = $db->prepare("UPDATE tours SET view_count = view_count + 1 WHERE id = ?");
$stmt->execute([$tour['id']]);

// Get tour itinerary
$stmt = $db->prepare("SELECT * FROM itineraries WHERE tour_id = ? ORDER BY day_number");
$stmt->execute([$tour['id']]);
$itinerary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get tour add-ons
$stmt = $db->prepare("SELECT * FROM tour_addons WHERE tour_id = ? AND status = 'active' ORDER BY name");
$stmt->execute([$tour['id']]);
$addons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get related tours
$stmt = $db->prepare("
    SELECT t.*, c.name as country_name
    FROM tours t
    LEFT JOIN countries c ON t.country_id = c.id
    WHERE t.country_id = ? AND t.id != ? AND t.status = 'active'
    ORDER BY t.featured DESC, t.created_at DESC
    LIMIT 3
");
$stmt->execute([$tour['country_id'], $tour['id']]);
$related_tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gallery = json_decode($tour['gallery'], true) ?: [];
$page_title = $tour['title'] . ' - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($tour['short_description']); ?>">
    <meta name="keywords"
        content="<?php echo htmlspecialchars($tour['seo_keywords'] ?: $tour['title'] . ', ' . $tour['country_name'] . ', tours'); ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($tour['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($tour['short_description']); ?>">
    <meta property="og:image" content="<?php echo SITE_URL . '/' . $tour['featured_image']; ?>">
    <meta property="og:type" content="article">

    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    <style>
        .tour-hero {
            height: 60vh;
            position: relative;
            overflow: hidden;
        }

        .tour-hero-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .tour-hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.7));
        }

        .tour-hero-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 40px 0;
            color: white;
        }

        .tour-title {
            font-size: 3em;
            font-family: 'Playfair Display', serif;
            margin-bottom: 15px;
        }

        .tour-meta {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tour-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1em;
        }

        .tour-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin: 40px 0;
        }

        .tour-main {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .tour-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .price-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }

        .price-main {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .price-label {
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .price-breakdown {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .section-title {
            font-size: 1.5em;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .gallery-item {
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .gallery-item:hover {
            transform: scale(1.05);
        }

        .gallery-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .itinerary-day {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .itinerary-header {
            background: #f8f9fa;
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }

        .itinerary-content {
            padding: 20px;
            display: none;
        }

        .itinerary-content.show {
            display: block;
        }

        .addon-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .addon-price {
            font-weight: bold;
            color: #667eea;
        }

        .includes-excludes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
        }

        .includes,
        .excludes {
            padding: 20px;
            border-radius: 10px;
        }

        .includes {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }

        .excludes {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }

        .related-tours {
            margin-top: 60px;
        }

        .related-tours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .booking-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .tour-content {
                grid-template-columns: 1fr;
            }

            .tour-title {
                font-size: 2em;
            }

            .tour-meta {
                flex-direction: column;
                gap: 10px;
            }

            .includes-excludes {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <div class="container">
            <a href="destinations.php">Destinations</a> /
            <a href="destinations.php?country=<?php echo strtolower(str_replace(' ', '-', $tour['country_name'])); ?>">
                <?php echo htmlspecialchars($tour['country_name']); ?>
            </a> /
            <?php echo htmlspecialchars($tour['title']); ?>
        </div>
    </div>

    <!-- Tour Hero -->
    <section class="tour-hero">
        <?php if ($tour['featured_image']): ?>
            <img src="<?php echo htmlspecialchars($tour['featured_image']); ?>"
                alt="<?php echo htmlspecialchars($tour['title']); ?>" class="tour-hero-image">
        <?php endif; ?>
        <div class="tour-hero-overlay"></div>
        <div class="tour-hero-content">
            <div class="container">
                <h1 class="tour-title"><?php echo htmlspecialchars($tour['title']); ?></h1>
                <div class="tour-meta">
                    <div class="tour-meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($tour['country_name']); ?>
                        <?php if ($tour['region_name']): ?>
                            , <?php echo htmlspecialchars($tour['region_name']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="tour-meta-item">
                        <i class="fas fa-clock"></i>
                        <?php echo $tour['duration_days']; ?> Days, <?php echo $tour['duration_nights']; ?> Nights
                    </div>
                    <div class="tour-meta-item">
                        <i class="fas fa-users"></i>
                        <?php echo $tour['min_group_size']; ?>-<?php echo $tour['max_group_size']; ?> People
                    </div>
                    <div class="tour-meta-item">
                        <i class="fas fa-star"></i>
                        Difficulty: <?php echo ucfirst($tour['difficulty_level']); ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="tour-content">
            <!-- Main Content -->
            <div class="tour-main">
                <!-- Description -->
                <section>
                    <h2 class="section-title">Tour Overview</h2>
                    <?php if ($tour['short_description']): ?>
                        <div class="tour-summary">
                            <p><strong><?php echo htmlspecialchars($tour['short_description']); ?></strong></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($tour['full_description']): ?>
                        <div class="tour-description">
                            <?php echo $tour['full_description']; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Gallery -->
                <?php if (!empty($gallery)): ?>
                    <section>
                        <h2 class="section-title">Photo Gallery</h2>
                        <div class="gallery-grid">
                            <?php foreach ($gallery as $image): ?>
                                <div class="gallery-item" onclick="openImageModal('<?php echo htmlspecialchars($image); ?>')">
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Tour Gallery">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Itinerary -->
                <?php if (!empty($itinerary)): ?>
                    <section>
                        <h2 class="section-title">Day by Day Itinerary</h2>
                        <?php foreach ($itinerary as $day): ?>
                            <div class="itinerary-day">
                                <div class="itinerary-header" onclick="toggleItinerary(<?php echo $day['day_number']; ?>)">
                                    <span>Day <?php echo $day['day_number']; ?>:
                                        <?php echo htmlspecialchars($day['title']); ?></span>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="itinerary-content" id="day-<?php echo $day['day_number']; ?>">
                                    <?php if ($day['description']): ?>
                                        <p><?php echo nl2br(htmlspecialchars($day['description'])); ?></p>
                                    <?php endif; ?>

                                    <?php if ($day['activities']): ?>
                                        <h4>Activities:</h4>
                                        <p><?php echo nl2br(htmlspecialchars($day['activities'])); ?></p>
                                    <?php endif; ?>

                                    <div
                                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                                        <?php if ($day['meals_included']): ?>
                                            <div><strong>Meals:</strong> <?php echo htmlspecialchars($day['meals_included']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($day['accommodation']): ?>
                                            <div><strong>Accommodation:</strong>
                                                <?php echo htmlspecialchars($day['accommodation']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($day['transportation']): ?>
                                            <div><strong>Transportation:</strong>
                                                <?php echo htmlspecialchars($day['transportation']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <!-- Includes & Excludes -->
                <section>
                    <h2 class="section-title">What's Included & Excluded</h2>
                    <div class="includes-excludes">
                        <?php if ($tour['includes']): ?>
                            <div class="includes">
                                <h3><i class="fas fa-check-circle"></i> What's Included</h3>
                                <?php echo nl2br(htmlspecialchars($tour['includes'])); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($tour['excludes']): ?>
                            <div class="excludes">
                                <h3><i class="fas fa-times-circle"></i> What's Excluded</h3>
                                <?php echo nl2br(htmlspecialchars($tour['excludes'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Requirements -->
                <?php if ($tour['requirements']): ?>
                    <section>
                        <h2 class="section-title">Requirements & Recommendations</h2>
                        <div
                            style="background: #fff3cd; padding: 20px; border-radius: 10px; border-left: 4px solid #ffc107;">
                            <?php echo nl2br(htmlspecialchars($tour['requirements'])); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Cancellation Policy -->
                <?php if ($tour['cancellation_policy']): ?>
                    <section>
                        <h2 class="section-title">Cancellation Policy</h2>
                        <div
                            style="background: #d1ecf1; padding: 20px; border-radius: 10px; border-left: 4px solid #17a2b8;">
                            <?php echo nl2br(htmlspecialchars($tour['cancellation_policy'])); ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="tour-sidebar">
                <!-- Pricing -->
                <div class="sidebar-card price-card">
                    <div class="price-main">
                        <?php echo formatCurrency($tour['price_adult'], $tour['currency']); ?>
                    </div>
                    <div class="price-label">per adult</div>

                    <?php if ($tour['price_child'] || $tour['price_infant']): ?>
                        <div class="price-breakdown">
                            <?php if ($tour['price_child']): ?>
                                <div class="price-item">
                                    <span>Child (2-11 years):</span>
                                    <span><?php echo formatCurrency($tour['price_child'], $tour['currency']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($tour['price_infant']): ?>
                                <div class="price-item">
                                    <span>Infant (0-2 years):</span>
                                    <span><?php echo formatCurrency($tour['price_infant'], $tour['currency']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <a href="book.php?tour=<?php echo $tour['id']; ?>" class="btn btn-light btn-block"
                        style="margin-top: 20px;">
                        <i class="fas fa-calendar-check"></i> Book Now
                    </a>
                </div>

                <!-- Tour Details -->
                <div class="sidebar-card">
                    <h3>Tour Details</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Duration:</span>
                            <strong><?php echo $tour['duration_days']; ?> days</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Group Size:</span>
                            <strong><?php echo $tour['min_group_size']; ?>-<?php echo $tour['max_group_size']; ?>
                                people</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Difficulty:</span>
                            <strong><?php echo ucfirst($tour['difficulty_level']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Category:</span>
                            <strong><?php echo htmlspecialchars($tour['category_name']); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Add-ons -->
                <?php if (!empty($addons)): ?>
                    <div class="sidebar-card">
                        <h3>Optional Add-ons</h3>
                        <?php foreach ($addons as $addon): ?>
                            <div class="addon-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($addon['name']); ?></strong>
                                    <?php if ($addon['description']): ?>
                                        <div style="font-size: 0.9em; color: #666;">
                                            <?php echo htmlspecialchars($addon['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="addon-price">
                                    <?php echo formatCurrency($addon['price'], $tour['currency']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Downloads -->
                <?php if ($tour['brochure_pdf']): ?>
                    <div class="sidebar-card">
                        <h3>Downloads</h3>
                        <a href="<?php echo htmlspecialchars($tour['brochure_pdf']); ?>" target="_blank"
                            class="btn btn-outline btn-block">
                            <i class="fas fa-file-pdf"></i> Download Brochure
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Contact -->
                <div class="sidebar-card">
                    <h3>Need Help?</h3>
                    <p>Have questions about this tour? Our travel experts are here to help!</p>
                    <a href="contact.php?tour=<?php echo $tour['id']; ?>" class="btn btn-outline btn-block">
                        <i class="fas fa-phone"></i> Contact Us
                    </a>
                    <a href="https://wa.me/250123456789" target="_blank" class="btn btn-success btn-block"
                        style="margin-top: 10px;">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                </div>
            </div>
        </div>

        <!-- Related Tours -->
        <?php if (!empty($related_tours)): ?>
            <section class="related-tours">
                <h2>More Tours in <?php echo htmlspecialchars($tour['country_name']); ?></h2>
                <div class="related-tours-grid">
                    <?php foreach ($related_tours as $related_tour): ?>
                        <div class="tour-card">
                            <div class="tour-image">
                                <?php if ($related_tour['featured_image']): ?>
                                    <img src="<?php echo htmlspecialchars($related_tour['featured_image']); ?>"
                                        alt="<?php echo htmlspecialchars($related_tour['title']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="tour-content">
                                <h3><?php echo htmlspecialchars($related_tour['title']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($related_tour['short_description'], 0, 100)); ?>...</p>
                                <div class="tour-details">
                                    <span><i class="fas fa-clock"></i> <?php echo $related_tour['duration_days']; ?> days</span>
                                    <span><i class="fas fa-dollar-sign"></i> From
                                        $<?php echo number_format($related_tour['price_adult']); ?></span>
                                </div>
                                <a href="tour-details.php?slug=<?php echo htmlspecialchars($related_tour['slug']); ?>"
                                    class="btn btn-primary btn-block">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div id="imageModal"
        style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9);">
        <div
            style="position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
            <img id="modalImage" style="max-width: 90%; max-height: 90%; object-fit: contain;">
            <button onclick="closeImageModal()"
                style="position: absolute; top: 20px; right: 30px; background: none; border: none; color: white; font-size: 2em; cursor: pointer;">&times;</button>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        function toggleItinerary(dayNumber) {
            const content = document.getElementById('day-' + dayNumber);
            const header = content.previousElementSibling;
            const icon = header.querySelector('i');

            if (content.classList.contains('show')) {
                content.classList.remove('show');
                icon.style.transform = 'rotate(0deg)';
            } else {
                content.classList.add('show');
                icon.style.transform = 'rotate(180deg)';
            }
        }

        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            modalImage.src = imageSrc;
            modal.style.display = 'block';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Close modal when clicking outside the image
        document.getElementById('imageModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>
</body>

</html>