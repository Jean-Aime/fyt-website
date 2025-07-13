<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('tours.view');

$tour_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$tour_id) {
    header('Location: index.php?error=Tour not found');
    exit;
}

// Get tour details with related data
$stmt = $db->prepare("
    SELECT t.*, tc.name as category_name, c.name as country_name, r.name as region_name,
           u.first_name, u.last_name,
           COUNT(DISTINCT b.id) as booking_count,
           COUNT(DISTINCT br.id) as review_count,
           AVG(br.rating) as avg_rating,
           COALESCE(SUM(b.total_amount), 0) as total_revenue
    FROM tours t
    LEFT JOIN tour_categories tc ON t.category_id = tc.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN regions r ON t.region_id = r.id
    LEFT JOIN users u ON t.created_by = u.id
    LEFT JOIN bookings b ON t.id = b.tour_id AND b.status != 'cancelled'
    LEFT JOIN tour_reviews br ON t.id = br.tour_id AND br.status = 'approved'
    WHERE t.id = ?
    GROUP BY t.id
");

$stmt->execute([$tour_id]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header('Location: index.php?error=Tour not found');
    exit;
}

// Get tour add-ons
$stmt = $db->prepare("SELECT * FROM tour_addons WHERE tour_id = ? AND status = 'active' ORDER BY name");
$stmt->execute([$tour_id]);
$addons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get itinerary
$stmt = $db->prepare("SELECT * FROM itineraries WHERE tour_id = ? ORDER BY day_number");
$stmt->execute([$tour_id]);
$itinerary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent bookings
$stmt = $db->prepare("
    SELECT b.*, u.first_name, u.last_name, u.email
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.tour_id = ?
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute([$tour_id]);
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'View Tour: ' . $tour['title'];
$gallery = json_decode($tour['gallery'], true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        <?php
        // Update the tour header styling to make it more visually appealing
        $tour_header_style = "
            .tour-header {
                background: linear-gradient(135deg, #3498db, #2c3e50);
                color: white;
                padding: 40px;
                border-radius: 15px;
                margin-bottom: 30px;
                position: relative;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            }
            
            .tour-header::before {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                width: 300px;
                height: 300px;
                background: rgba(255,255,255,0.1);
                border-radius: 50%;
                transform: translate(150px, -150px);
            }
            
            .tour-header::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                width: 200px;
                height: 200px;
                background: rgba(255,255,255,0.05);
                border-radius: 50%;
                transform: translate(-100px, 100px);
            }
            
            .tour-header-content {
                position: relative;
                z-index: 2;
            }
            
            .tour-title {
                font-size: 2.5em;
                font-weight: 800;
                margin-bottom: 15px;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .tour-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 25px;
                margin-bottom: 25px;
                opacity: 0.95;
            }
            
            .tour-meta-item {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 1.1em;
            }
            
            .tour-meta-item i {
                width: 30px;
                height: 30px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .tour-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 20px;
                margin-top: 30px;
            }
            
            .tour-stat {
                text-align: center;
                padding: 20px;
                background: rgba(255,255,255,0.15);
                border-radius: 12px;
                backdrop-filter: blur(10px);
                transition: transform 0.3s ease;
            }
            
            .tour-stat:hover {
                transform: translateY(-5px);
                background: rgba(255,255,255,0.2);
            }
            
            .tour-stat-value {
                font-size: 2em;
                font-weight: bold;
                margin-bottom: 8px;
            }
            
            .tour-stat-label {
                font-size: 0.9em;
                opacity: 0.9;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .status-indicator {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 20px;
                border-radius: 30px;
                font-size: 0.9em;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 1px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            }
            
            .status-active {
                background: #2ecc71;
                color: white;
            }
            
            .status-inactive {
                background: #e74c3c;
                color: white;
            }
            
            .status-draft {
                background: #f39c12;
                color: white;
            }
            
            .featured-badge {
                background: linear-gradient(135deg, #f39c12, #e67e22);
                color: white;
                padding: 8px 15px;
                border-radius: 30px;
                font-size: 0.9em;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            }
            
            .action-buttons {
                display: flex;
                gap: 15px;
                margin: 30px 0;
            }
            
            .action-buttons .btn {
                padding: 12px 25px;
                border-radius: 10px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 10px;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .action-buttons .btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            
            .content-section {
                background: white;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.05);
                margin-bottom: 30px;
                transition: transform 0.3s ease;
            }
            
            .content-section:hover {
                transform: translateY(-5px);
            }
            
            .section-title {
                font-size: 1.4em;
                font-weight: 700;
                margin-bottom: 25px;
                color: #2c3e50;
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .section-icon {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                background: #3498db;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.2em;
            }
        ";

        // Add this style to the head section
        echo $tour_header_style;
        ?>

        .tour-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .tour-main {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .tour-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }


        .section-title {
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--admin-text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            background: var(--admin-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tour-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .gallery-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            aspect-ratio: 16/10;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .gallery-item:hover {
            transform: scale(1.05);
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .featured-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .price-display {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
        }

        .price-main {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .price-label {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .price-breakdown {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .itinerary-day {
            border: 1px solid var(--admin-border);
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .itinerary-header {
            background: var(--admin-light);
            padding: 15px 20px;
            font-weight: 600;
            color: var(--admin-text);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .addon-info h4 {
            margin-bottom: 5px;
            color: var(--admin-text);
        }

        .addon-description {
            color: var(--admin-text-muted);
            font-size: 0.9em;
        }

        .addon-price {
            font-size: 1.2em;
            font-weight: 600;
            color: var(--admin-primary);
        }

        .booking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--admin-border);
        }

        .booking-item:last-child {
            border-bottom: none;
        }

        .booking-info h5 {
            margin-bottom: 3px;
            color: var(--admin-text);
        }

        .booking-meta {
            font-size: 0.85em;
            color: var(--admin-text-muted);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-draft {
            background: #fff3cd;
            color: #856404;
        }

        .featured-badge {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        @media (max-width: 768px) {
            .tour-content {
                grid-template-columns: 1fr;
            }

            .tour-meta {
                flex-direction: column;
                gap: 10px;
            }

            .tour-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="content">
                <!-- Tour Header -->
                <div class="tour-header">
                    <div class="tour-header-content">
                        <div
                            style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                            <div>
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
                                        <i class="fas fa-tag"></i>
                                        <?php echo htmlspecialchars($tour['category_name']); ?>
                                    </div>
                                    <div class="tour-meta-item">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $tour['duration_days']; ?> days
                                        <?php if ($tour['duration_nights']): ?>
                                            / <?php echo $tour['duration_nights']; ?> nights
                                        <?php endif; ?>
                                    </div>
                                    <div class="tour-meta-item">
                                        <i class="fas fa-users"></i>
                                        <?php echo $tour['min_group_size']; ?>-<?php echo $tour['max_group_size']; ?>
                                        people
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div class="status-indicator status-<?php echo $tour['status']; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo ucfirst($tour['status']); ?>
                                </div>
                                <?php if ($tour['featured']): ?>
                                    <div class="featured-badge" style="margin-top: 10px;">
                                        <i class="fas fa-star"></i>
                                        Featured
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tour-stats">
                            <div class="tour-stat">
                                <div class="tour-stat-value"><?php echo number_format($tour['booking_count']); ?></div>
                                <div class="tour-stat-label">Total Bookings</div>
                            </div>
                            <div class="tour-stat">
                                <div class="tour-stat-value">$<?php echo number_format($tour['total_revenue']); ?></div>
                                <div class="tour-stat-label">Total Revenue</div>
                            </div>
                            <div class="tour-stat">
                                <div class="tour-stat-value">
                                    <?php echo $tour['avg_rating'] ? number_format($tour['avg_rating'], 1) : 'N/A'; ?>
                                </div>
                                <div class="tour-stat-label">Average Rating</div>
                            </div>
                            <div class="tour-stat">
                                <div class="tour-stat-value"><?php echo number_format($tour['view_count']); ?></div>
                                <div class="tour-stat-label">Page Views</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Tours
                    </a>
                    <?php if (hasPermission('tours.edit')): ?>
                        <a href="edit.php?id=<?php echo $tour['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Tour
                        </a>
                    <?php endif; ?>
                    <a href="../../tour.php?slug=<?php echo $tour['slug']; ?>" target="_blank" class="btn btn-info">
                        <i class="fas fa-external-link-alt"></i> View Public Page
                    </a>
                    <?php if (hasPermission('tours.delete')): ?>
                        <button onclick="deleteTour(<?php echo $tour['id']; ?>)" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Tour
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Tour Content -->
                <div class="tour-content">
                    <div class="tour-main">
                        <!-- Featured Image & Gallery -->
                        <div class="content-section">
                            <h3 class="section-title">
                                <div class="section-icon">
                                    <i class="fas fa-images"></i>
                                </div>
                                Media Gallery
                            </h3>

                            <?php if ($tour['featured_image']): ?>
                                <img src="../../<?php echo htmlspecialchars($tour['featured_image']); ?>"
                                    alt="<?php echo htmlspecialchars($tour['title']); ?>" class="featured-image">
                            <?php endif; ?>

                            <?php if (!empty($gallery)): ?>
                                <div class="tour-gallery">
                                    <?php foreach ($gallery as $image): ?>
                                        <div class="gallery-item"
                                            onclick="openImageModal('../../<?php echo htmlspecialchars($image); ?>')">
                                            <img src="../../<?php echo htmlspecialchars($image); ?>" alt="Tour Gallery">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($tour['video_url']): ?>
                                <div style="margin-top: 20px;">
                                    <h4>Tour Video</h4>
                                    <div
                                        style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 10px;">
                                        <iframe src="<?php echo htmlspecialchars($tour['video_url']); ?>"
                                            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;"
                                            allowfullscreen></iframe>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <div class="content-section">
                            <h3 class="section-title">
                                <div class="section-icon">
                                    <i class="fas fa-align-left"></i>
                                </div>
                                Tour Description
                            </h3>

                            <?php if ($tour['short_description']): ?>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                    <strong>Summary:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($tour['short_description'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($tour['full_description']): ?>
                                <div class="tour-description">
                                    <?php echo $tour['full_description']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Itinerary -->
                        <?php if (!empty($itinerary)): ?>
                            <div class="content-section">
                                <h3 class="section-title">
                                    <div class="section-icon">
                                        <i class="fas fa-route"></i>
                                    </div>
                                    Tour Itinerary
                                </h3>

                                <div class="itinerary-list">
                                    <?php foreach ($itinerary as $day): ?>
                                        <div class="itinerary-day">
                                            <div class="itinerary-header"
                                                onclick="toggleItinerary(<?php echo $day['day_number']; ?>)">
                                                <span><strong>Day <?php echo $day['day_number']; ?>:</strong>
                                                    <?php echo htmlspecialchars($day['title']); ?></span>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div class="itinerary-content" id="day-<?php echo $day['day_number']; ?>">
                                                <?php if ($day['description']): ?>
                                                    <p><?php echo nl2br(htmlspecialchars($day['description'])); ?></p>
                                                <?php endif; ?>

                                                <?php if ($day['activities']): ?>
                                                    <h5>Activities:</h5>
                                                    <p><?php echo nl2br(htmlspecialchars($day['activities'])); ?></p>
                                                <?php endif; ?>

                                                <div
                                                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                                                    <?php if ($day['meals_included']): ?>
                                                        <div>
                                                            <strong>Meals:</strong><br>
                                                            <?php echo htmlspecialchars($day['meals_included']); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($day['accommodation']): ?>
                                                        <div>
                                                            <strong>Accommodation:</strong><br>
                                                            <?php echo htmlspecialchars($day['accommodation']); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($day['transportation']): ?>
                                                        <div>
                                                            <strong>Transportation:</strong><br>
                                                            <?php echo htmlspecialchars($day['transportation']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Includes & Excludes -->
                        <div class="content-section">
                            <h3 class="section-title">
                                <div class="section-icon">
                                    <i class="fas fa-list-check"></i>
                                </div>
                                What's Included & Excluded
                            </h3>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                                <?php if ($tour['includes']): ?>
                                    <div>
                                        <h4 style="color: #28a745; margin-bottom: 15px;">
                                            <i class="fas fa-check-circle"></i> What's Included
                                        </h4>
                                        <div style="background: #d4edda; padding: 15px; border-radius: 8px;">
                                            <?php echo nl2br(htmlspecialchars($tour['includes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($tour['excludes']): ?>
                                    <div>
                                        <h4 style="color: #dc3545; margin-bottom: 15px;">
                                            <i class="fas fa-times-circle"></i> What's Excluded
                                        </h4>
                                        <div style="background: #f8d7da; padding: 15px; border-radius: 8px;">
                                            <?php echo nl2br(htmlspecialchars($tour['excludes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Requirements & Policies -->
                        <div class="content-section">
                            <h3 class="section-title">
                                <div class="section-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                Requirements & Policies
                            </h3>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                                <?php if ($tour['requirements']): ?>
                                    <div>
                                        <h4 style="margin-bottom: 15px;">Requirements</h4>
                                        <div style="background: #fff3cd; padding: 15px; border-radius: 8px;">
                                            <?php echo nl2br(htmlspecialchars($tour['requirements'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($tour['cancellation_policy']): ?>
                                    <div>
                                        <h4 style="margin-bottom: 15px;">Cancellation Policy</h4>
                                        <div style="background: #d1ecf1; padding: 15px; border-radius: 8px;">
                                            <?php echo nl2br(htmlspecialchars($tour['cancellation_policy'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tour-sidebar">
                        <!-- Pricing -->
                        <div class="content-section">
                            <div class="price-display">
                                <div class="price-main">
                                    <?php echo formatCurrency($tour['price_adult'], $tour['currency']); ?>
                                </div>
                                <div class="price-label">per adult</div>

                                <?php if ($tour['price_child'] || $tour['price_infant']): ?>
                                    <div class="price-breakdown">
                                        <?php if ($tour['price_child']): ?>
                                            <div class="price-item">
                                                <span>Child:</span>
                                                <span><?php echo formatCurrency($tour['price_child'], $tour['currency']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($tour['price_infant']): ?>
                                            <div class="price-item">
                                                <span>Infant:</span>
                                                <span><?php echo formatCurrency($tour['price_infant'], $tour['currency']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tour Details -->
                        <div class="content-section">
                            <h3 class="section-title">
                                <div class="section-icon">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                Tour Details
                            </h3>

                            <div style="display: flex; flex-direction: column; gap: 15px;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span><strong>Duration:</strong></span>
                                    <span><?php echo $tour['duration_days']; ?> days</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span><strong>Group Size:</strong></span>
                                    <span><?php echo $tour['min_group_size']; ?>-<?php echo $tour['max_group_size']; ?>
                                        people</span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span><strong>Difficulty:</strong></span>
                                    <span><?php echo ucfirst($tour['difficulty_level']); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span><strong>Created:</strong></span>
                                    <span><?php echo date('M j, Y', strtotime($tour['created_at'])); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span><strong>Created by:</strong></span>
                                    <span><?php echo htmlspecialchars($tour['first_name'] . ' ' . $tour['last_name']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Add-ons -->
                        <?php if (!empty($addons)): ?>
                            <div class="content-section">
                                <h3 class="section-title">
                                    <div class="section-icon">
                                        <i class="fas fa-plus-circle"></i>
                                    </div>
                                    Available Add-ons
                                </h3>

                                <div class="addons-list">
                                    <?php foreach ($addons as $addon): ?>
                                        <div class="addon-item">
                                            <div class="addon-info">
                                                <h4><?php echo htmlspecialchars($addon['name']); ?></h4>
                                                <?php if ($addon['description']): ?>
                                                    <div class="addon-description">
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
                            </div>
                        <?php endif; ?>

                        <!-- Recent Bookings -->
                        <?php if (!empty($recent_bookings)): ?>
                            <div class="content-section">
                                <h3 class="section-title">
                                    <div class="section-icon">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    Recent Bookings
                                </h3>

                                <div class="bookings-list">
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <div class="booking-item">
                                            <div class="booking-info">
                                                <h5><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                                </h5>
                                                <div class="booking-meta">
                                                    <?php echo date('M j, Y', strtotime($booking['tour_date'])); ?> •
                                                    <?php echo $booking['adults']; ?> adults
                                                    <?php if ($booking['children']): ?>
                                                        • <?php echo $booking['children']; ?> children
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div style="text-align: center; margin-top: 15px;">
                                    <a href="../bookings/index.php?tour_id=<?php echo $tour['id']; ?>"
                                        class="btn btn-outline-primary btn-sm">
                                        View All Bookings
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Downloads -->
                        <?php if ($tour['brochure_pdf']): ?>
                            <div class="content-section">
                                <h3 class="section-title">
                                    <div class="section-icon">
                                        <i class="fas fa-download"></i>
                                    </div>
                                    Downloads
                                </h3>

                                <a href="../../<?php echo htmlspecialchars($tour['brochure_pdf']); ?>" target="_blank"
                                    class="btn btn-outline-primary" style="width: 100%;">
                                    <i class="fas fa-file-pdf"></i> Download Brochure
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
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

        function deleteTour(tourId) {
            if (confirm('Are you sure you want to delete this tour? This action cannot be undone.')) {
                window.location.href = 'delete.php?id=' + tourId;
            }
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

    <script src="../../assets/js/admin.js"></script>
</body>

</html>
