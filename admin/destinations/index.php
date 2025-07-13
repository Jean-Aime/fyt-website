<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('destinations.view');

$page_title = 'Destinations Dashboard';

// Get destination statistics
$stats = $db->query("
    SELECT 
        COUNT(DISTINCT c.id) as total_countries,
        COUNT(DISTINCT r.id) as total_regions,
        COUNT(DISTINCT t.id) as total_tours,
        COUNT(DISTINCT CASE WHEN c.status = 'active' THEN c.id END) as active_countries,
        COUNT(DISTINCT CASE WHEN r.status = 'active' THEN r.id END) as active_regions,
        COUNT(DISTINCT CASE WHEN t.status = 'active' THEN t.id END) as active_tours
    FROM countries c
    LEFT JOIN regions r ON c.id = r.country_id
    LEFT JOIN tours t ON c.id = t.country_id
")->fetch(PDO::FETCH_ASSOC);

// Get top countries by tour count
$top_countries = $db->query("
    SELECT c.*, COUNT(t.id) as tour_count, COUNT(b.id) as booking_count
    FROM countries c
    LEFT JOIN tours t ON c.id = t.country_id
    LEFT JOIN bookings b ON t.id = b.tour_id
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY tour_count DESC, booking_count DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent destinations
$recent_destinations = $db->query("
    SELECT 'country' as type, c.id, c.name, c.created_at, c.flag_image as image, 
           COUNT(t.id) as tour_count
    FROM countries c
    LEFT JOIN tours t ON c.id = t.country_id
    WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY c.id
    
    UNION ALL
    
    SELECT 'region' as type, r.id, CONCAT(r.name, ' (', c.name, ')') as name, 
           r.created_at, c.flag_image as image, COUNT(t.id) as tour_count
    FROM regions r
    LEFT JOIN countries c ON r.country_id = c.id
    LEFT JOIN tours t ON r.id = t.region_id
    WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY r.id
    
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get continent distribution
$continent_stats = $db->query("
    SELECT continent, COUNT(*) as country_count, COUNT(DISTINCT t.id) as tour_count
    FROM countries c
    LEFT JOIN tours t ON c.id = t.country_id
    WHERE c.status = 'active' AND c.continent IS NOT NULL
    GROUP BY continent
    ORDER BY country_count DESC
")->fetchAll(PDO::FETCH_ASSOC);
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
        .destination-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .destination-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }

        .destination-card:hover {
            transform: translateY(-5px);
        }

        .destination-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
        }

        .destination-flag {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 40px;
            height: 27px;
            border-radius: 4px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .destination-name {
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .destination-stats {
            display: flex;
            gap: 20px;
            font-size: 0.9em;
        }

        .destination-content {
            padding: 20px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .stat-value {
            font-weight: 600;
            color: #333;
        }

        .continent-chart {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .continent-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .continent-item:last-child {
            border-bottom: none;
        }

        .continent-bar {
            flex: 1;
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            margin: 0 15px;
            overflow: hidden;
        }

        .continent-progress {
            height: 100%;
            background: linear-gradient(90deg, var(--admin-primary), #764ba2);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .recent-list {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .recent-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9em;
        }

        .recent-icon.country {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .recent-icon.region {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .recent-info {
            flex: 1;
        }

        .recent-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .recent-meta {
            font-size: 0.8em;
            color: #666;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .quick-action {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .quick-action:hover {
            transform: translateY(-3px);
            text-decoration: none;
            color: inherit;
        }

        .quick-action i {
            font-size: 2em;
            margin-bottom: 10px;
            color: var(--admin-primary);
        }

        .quick-action h4 {
            margin: 0;
            color: #333;
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="content">
                <div class="content-header">
                    <div class="content-title">
                        <h2>Destinations Dashboard</h2>
                        <p>Overview of countries, regions, and destination management</p>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_countries']); ?></div>
                        <div class="stat-label">Total Countries</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['active_countries']); ?></div>
                        <div class="stat-label">Active Countries</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_regions']); ?></div>
                        <div class="stat-label">Total Regions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['active_regions']); ?></div>
                        <div class="stat-label">Active Regions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_tours']); ?></div>
                        <div class="stat-label">Total Tours</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['active_tours']); ?></div>
                        <div class="stat-label">Active Tours</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="countries.php" class="quick-action">
                        <i class="fas fa-globe"></i>
                        <h4>Manage Countries</h4>
                    </a>
                    <a href="regions.php" class="quick-action">
                        <i class="fas fa-map"></i>
                        <h4>Manage Regions</h4>
                    </a>
                    <a href="../tours/index.php" class="quick-action">
                        <i class="fas fa-route"></i>
                        <h4>View Tours</h4>
                    </a>
                    <a href="../tours/add.php" class="quick-action">
                        <i class="fas fa-plus"></i>
                        <h4>Add New Tour</h4>
                    </a>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                    <!-- Top Countries -->
                    <div>
                        <h3 style="margin-bottom: 20px;">Top Destinations</h3>
                        <div class="destination-grid">
                            <?php foreach ($top_countries as $country): ?>
                                <div class="destination-card">
                                    <div class="destination-header">
                                        <?php if ($country['flag_image']): ?>
                                            <img src="../../<?php echo htmlspecialchars($country['flag_image']); ?>"
                                                alt="<?php echo htmlspecialchars($country['name']); ?>"
                                                class="destination-flag">
                                        <?php endif; ?>
                                        <div class="destination-name"><?php echo htmlspecialchars($country['name']); ?>
                                        </div>
                                        <div class="destination-stats">
                                            <span><i class="fas fa-route"></i> <?php echo $country['tour_count']; ?>
                                                Tours</span>
                                            <span><i class="fas fa-calendar-check"></i>
                                                <?php echo $country['booking_count']; ?> Bookings</span>
                                        </div>
                                    </div>
                                    <div class="destination-content">
                                        <div class="stat-row">
                                            <span class="stat-label">Continent:</span>
                                            <span
                                                class="stat-value"><?php echo htmlspecialchars($country['continent'] ?: 'Not specified'); ?></span>
                                        </div>
                                        <div class="stat-row">
                                            <span class="stat-label">Currency:</span>
                                            <span
                                                class="stat-value"><?php echo htmlspecialchars($country['currency'] ?: 'Not specified'); ?></span>
                                        </div>
                                        <div class="stat-row">
                                            <span class="stat-label">Language:</span>
                                            <span
                                                class="stat-value"><?php echo htmlspecialchars($country['language'] ?: 'Not specified'); ?></span>
                                        </div>
                                        <div style="margin-top: 15px; display: flex; gap: 8px;">
                                            <a href="regions.php?country=<?php echo $country['id']; ?>"
                                                class="btn btn-sm btn-secondary">
                                                <i class="fas fa-map"></i> Regions
                                            </a>
                                            <a href="../tours/index.php?country=<?php echo $country['id']; ?>"
                                                class="btn btn-sm btn-primary">
                                                <i class="fas fa-route"></i> Tours
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div>
                        <?php if (!empty($continent_stats)): ?>
                            <?php
                            $max_countries = max(array_column($continent_stats, 'country_count'));
                            foreach ($continent_stats as $continent):
                                $percentage = $max_countries > 0 ? ($continent['country_count'] / $max_countries) * 100 : 0;
                                ?>
                                <div class="continent-item">
                                    <div style="min-width: 80px;">
                                        <strong><?= htmlspecialchars($continent['continent']) ?></strong>
                                    </div>
                                    <div class="continent-bar">
                                        <div class="continent-progress" style="width: <?= $percentage ?>%;"></div>
                                    </div>
                                    <div style="min-width: 60px; text-align: right;">
                                        <span style="font-weight: 600;"><?= $continent['country_count'] ?></span>
                                        <span style="color: #666; font-size: 0.9em;">countries</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No continent data available for this region.</p>
                        <?php endif; ?>


                        <!-- Recent Destinations -->
                        <?php if (!empty($recent_destinations)): ?>
                            <div class="recent-list" style="margin-top: 30px;">
                                <h3 style="margin-bottom: 20px;">Recently Added</h3>
                                <?php foreach ($recent_destinations as $destination): ?>
                                    <div class="recent-item">
                                        <div class="recent-icon <?php echo $destination['type']; ?>">
                                            <i
                                                class="fas fa-<?php echo $destination['type'] === 'country' ? 'globe' : 'map-marker-alt'; ?>"></i>
                                        </div>
                                        <div class="recent-info">
                                            <div class="recent-name"><?php echo htmlspecialchars($destination['name'] ?? ''); ?>
                                            </div>

                                            <div class="recent-meta">
                                                <?php echo ucfirst($destination['type']); ?> •
                                                <?php echo $destination['tour_count']; ?> tours •
                                                <?php echo date('M j', strtotime($destination['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>