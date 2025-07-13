<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');


$auth = new SecureAuth($db);
requireLogin();
requirePermission('tours.view');

$page_title = 'Tour Management';

// Handle bulk actions
if ($_POST && isset($_POST['bulk_action']) && isset($_POST['selected_tours'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $action = $_POST['bulk_action'];
        $tour_ids = $_POST['selected_tours'];
        
        try {
            switch ($action) {
                case 'activate':
                    if (hasPermission('tours.edit')) {
                        $placeholders = str_repeat('?,', count($tour_ids) - 1) . '?';
                        $stmt = $db->prepare("UPDATE tours SET status = 'active' WHERE id IN ($placeholders)");
                        $stmt->execute($tour_ids);
                        $success = count($tour_ids) . ' tours activated successfully';
                    }
                    break;
                    
                case 'deactivate':
                    if (hasPermission('tours.edit')) {
                        $placeholders = str_repeat('?,', count($tour_ids) - 1) . '?';
                        $stmt = $db->prepare("UPDATE tours SET status = 'inactive' WHERE id IN ($placeholders)");
                        $stmt->execute($tour_ids);
                        $success = count($tour_ids) . ' tours deactivated successfully';
                    }
                    break;
                    
                case 'delete':
                    if (hasPermission('tours.delete')) {
                        $placeholders = str_repeat('?,', count($tour_ids) - 1) . '?';
                        $stmt = $db->prepare("DELETE FROM tours WHERE id IN ($placeholders)");
                        $stmt->execute($tour_ids);
                        $success = count($tour_ids) . ' tours deleted successfully';
                    }
                    break;
            }
            
            $auth->logActivity($_SESSION['user_id'], 'tours_bulk_action', "Bulk action '$action' on " . count($tour_ids) . " tours");
            
        } catch (Exception $e) {
            $error = 'Error performing bulk action: ' . $e->getMessage();
        }
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$country_filter = $_GET['country'] ?? '';
$status_filter = $_GET['status'] ?? '';
$featured_filter = $_GET['featured'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.short_description LIKE ?)";
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

if ($status_filter) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($featured_filter !== '') {
    $where_conditions[] = "t.featured = ?";
    $params[] = $featured_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM tours t 
    LEFT JOIN tour_categories tc ON t.category_id = tc.id
    LEFT JOIN countries c ON t.country_id = c.id
    $where_clause
";
$total_tours = $db->prepare($count_sql);
$total_tours->execute($params);
$total_count = $total_tours->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get tours
$sql = "
    SELECT t.*, 
           tc.name as category_name, tc.color as category_color,
           c.name as country_name, c.flag_image as country_flag,
           u.first_name as creator_first_name, u.last_name as creator_last_name,
           (SELECT COUNT(*) FROM bookings WHERE tour_id = t.id) as booking_count,
           (SELECT COUNT(*) FROM bookings WHERE tour_id = t.id AND status = 'confirmed') as confirmed_bookings
    FROM tours t
    LEFT JOIN tour_categories tc ON t.category_id = tc.id
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN users u ON t.created_by = u.id
    $where_clause
    ORDER BY $sort $order
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$categories = $db->query("SELECT id, name FROM tour_categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$countries = $db->query("SELECT id, name FROM countries WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_tours,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_tours,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_tours,
        COUNT(CASE WHEN featured = 1 THEN 1 END) as featured_tours,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as tours_30d,
        COALESCE(AVG(price_adult), 0) as avg_price
    FROM tours
")->fetch(PDO::FETCH_ASSOC);
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
        .tour-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 25px;
            border: 1px solid #ecf0f1;
        }
        
        .tour-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .tour-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .tour-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .tour-card:hover .tour-image img {
            transform: scale(1.05);
        }
        
        .tour-image-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 3em;
        }
        
        .tour-badges {
            position: absolute;
            top: 15px;
            left: 15px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .tour-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
            color: white;
        }
        
        .badge-featured {
            background: linear-gradient(135deg, #d4af37, #b8941f);
        }
        
        .badge-status {
            background: rgba(0,0,0,0.7);
        }
        
        .badge-status.active {
            background: rgba(34, 139, 34, 0.9);
        }
        
        .badge-status.draft {
            background: rgba(243, 156, 18, 0.9);
        }
        
        .badge-status.inactive {
            background: rgba(220, 53, 69, 0.9);
        }
        
        .tour-content {
            padding: 25px;
        }
        
        .tour-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .tour-title {
            font-size: 1.3em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .tour-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .meta-item i {
            color: #d4af37;
        }
        
        .tour-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .tour-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.2em;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 0.8em;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tour-actions {
            display: flex;
            gap: 8px;
            justify-content: space-between;
            align-items: center;
        }
        
        .tour-actions .btn {
            padding: 8px 12px;
            font-size: 0.85em;
        }
        
        .tour-checkbox {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 20px;
            height: 20px;
            accent-color: #d4af37;
        }
        
        .tours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .view-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .view-btn {
            padding: 8px 15px;
            border: 2px solid #d4af37;
            background: white;
            color: #d4af37;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-btn.active,
        .view-btn:hover {
            background: #d4af37;
            color: white;
        }
        
        .bulk-actions {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: none;
        }
        
        .bulk-actions.show {
            display: block;
        }
        
        .quick-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .quick-filter {
            padding: 10px 20px;
            border: 1px solid #ecf0f1;
            border-radius: 25px;
            background: white;
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.95em;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .quick-filter.active,
        .quick-filter:hover {
            background: #d4af37;
            color: white;
            border-color: #d4af37;
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .tours-grid {
                grid-template-columns: 1fr;
            }
            
            .tour-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tour-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .tour-actions .btn {
                width: 100%;
                justify-content: center;
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
                <div class="content-header">
                    <div class="content-title">
                        <h2>Tour Management</h2>
                        <p>Manage tours, itineraries, pricing, and availability</p>
                    </div>
                    <div class="content-actions">
                        <a href="export.php" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export
                        </a>
                        <?php if (hasPermission('tours.create')): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Tour
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['total_tours']); ?></h3>
                            <p>Total Tours</p>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> <?php echo $stats['tours_30d']; ?> this month
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['active_tours']); ?></h3>
                            <p>Active Tours</p>
                            <div class="stat-change neutral">
                                <?php echo round(($stats['active_tours'] / max($stats['total_tours'], 1)) * 100, 1); ?>% of total
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($stats['featured_tours']); ?></h3>
                            <p>Featured Tours</p>
                            <div class="stat-change neutral">
                                Premium listings
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3>$<?php echo number_format($stats['avg_price']); ?></h3>
                            <p>Average Price</p>
                            <div class="stat-change neutral">
                                Per adult
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Filters -->
                <div class="quick-filters">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => ''])); ?>" 
                       class="quick-filter <?php echo !$status_filter ? 'active' : ''; ?>">
                        All Tours
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'active'])); ?>" 
                       class="quick-filter <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                        Active
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'draft'])); ?>" 
                       class="quick-filter <?php echo $status_filter === 'draft' ? 'active' : ''; ?>">
                        Draft
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['featured' => '1'])); ?>" 
                       class="quick-filter <?php echo $featured_filter === '1' ? 'active' : ''; ?>">
                        Featured
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'booking_count', 'order' => 'DESC'])); ?>" 
                       class="quick-filter">
                        Most Popular
                    </a>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Tour title, description..." class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category" class="form-control">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Country</label>
                                <select name="country" class="form-control">
                                    <option value="">All Countries</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['id']; ?>" 
                                                <?php echo $country_filter == $country['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($country['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sort By</label>
                                <select name="sort" class="form-control">
                                    <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                                    <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title</option>
                                    <option value="price_adult" <?php echo $sort === 'price_adult' ? 'selected' : ''; ?>>Price</option>
                                    <option value="booking_count" <?php echo $sort === 'booking_count' ? 'selected' : ''; ?>>Popularity</option>
                                    <option value="rating_average" <?php echo $sort === 'rating_average' ? 'selected' : ''; ?>>Rating</option>
                                </select>
                            </div>
                        </div>
                        <div class="filters-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="?" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- View Toggle -->
                <div class="view-toggle">
                    <button class="view-btn active" data-view="grid">
                        <i class="fas fa-th"></i> Grid View
                    </button>
                    <button class="view-btn" data-view="list">
                        <i class="fas fa-list"></i> List View
                    </button>
                </div>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <form method="POST" id="bulkForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="bulk-controls">
                            <span id="selectedCount">0 tours selected</span>
                            <div class="bulk-buttons">
                                <?php if (hasPermission('tours.edit')): ?>
                                <button type="submit" name="bulk_action" value="activate" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Activate
                                </button>
                                <button type="submit" name="bulk_action" value="deactivate" class="btn btn-warning btn-sm">
                                    <i class="fas fa-pause"></i> Deactivate
                                </button>
                                <?php endif; ?>
                                <?php if (hasPermission('tours.delete')): ?>
                                <button type="submit" name="bulk_action" value="delete" class="btn btn-danger btn-sm" 
                                        onclick="return confirm('Are you sure you want to delete the selected tours?')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Tours Grid -->
                <div class="tours-grid" id="toursGrid">
                    <?php foreach ($tours as $tour): ?>
                        <div class="tour-card">
                            <input type="checkbox" name="selected_tours[]" value="<?php echo $tour['id']; ?>" 
                                   class="tour-checkbox" onchange="updateBulkActions()">
                            
                            <div class="tour-image">
    <?php if (!empty($tour['featured_image'])): ?>
        <img src="/webtour/<?php echo htmlspecialchars($tour['featured_image']); ?>" 
             alt="<?php echo htmlspecialchars($tour['title']); ?>" 
             style="max-width: 100px; height: auto;">
    <?php else: ?>
        <div class="tour-image-placeholder">
            <i class="fas fa-image"></i>
        </div>
    <?php endif; ?>

    <div class="tour-badges">
        <?php if (!empty($tour['featured'])): ?>
            <span class="tour-badge badge-featured">
                <i class="fas fa-star"></i> Featured
            </span>
        <?php endif; ?>
        <span class="tour-badge badge-status <?php echo htmlspecialchars($tour['status']); ?>">
            <?php echo ucfirst($tour['status']); ?>
        </span>
    </div>
</div>

                            
                            <div class="tour-content">
                                <div class="tour-header">
                                    <div>
                                        <h3 class="tour-title"><?php echo htmlspecialchars($tour['title']); ?></h3>
                                        <div class="tour-meta">
                                            <div class="meta-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars((string)$tour['country_name']); ?>

                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-tag"></i>
                                                <span style="color: <?php echo $tour['category_color']; ?>">
                                                    <?php echo htmlspecialchars($tour['category_name']); ?>
                                                </span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo $tour['duration_days']; ?> days
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tour-price">
                                        <strong>$<?php echo number_format($tour['price_adult']); ?></strong>
                                        <small>/adult</small>
                                    </div>
                                </div>
                                
                                <?php if ($tour['short_description']): ?>
                                    <p class="tour-description">
                                        <?php echo htmlspecialchars($tour['short_description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="tour-stats">
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $tour['booking_count']; ?></div>
                                        <div class="stat-label">Total Bookings</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value"><?php echo $tour['confirmed_bookings']; ?></div>
                                        <div class="stat-label">Confirmed</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-value">
                                            <?php if ($tour['rating_count'] > 0): ?>
                                                <?php echo number_format($tour['rating_average'], 1); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </div>
                                        <div class="stat-label">Rating</div>
                                    </div>
                                </div>
                                
                                <div class="tour-actions">
                                    <div class="action-buttons">
                                        <a href="view.php?id=<?php echo $tour['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if (hasPermission('tours.edit')): ?>
                                        <a href="edit.php?id=<?php echo $tour['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php endif; ?>
                                        <a href="itinerary.php?id=<?php echo $tour['id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-route"></i> Itinerary
                                        </a>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm dropdown-toggle" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="addons.php?id=<?php echo $tour['id']; ?>" class="dropdown-item">
                                                <i class="fas fa-plus-circle"></i> Manage Add-ons
                                            </a>
                                            <a href="availability.php?id=<?php echo $tour['id']; ?>" class="dropdown-item">
                                                <i class="fas fa-calendar-check"></i> Availability
                                            </a>
                                            <a href="../../tour-details.php?id=<?php echo $tour['id']; ?>" 
                                               class="dropdown-item" target="_blank">
                                                <i class="fas fa-external-link-alt"></i> Preview
                                            </a>
                                            <?php if (hasPermission('tours.delete')): ?>
                                            <div class="dropdown-divider"></div>
                                            <a href="delete.php?id=<?php echo $tour['id']; ?>" class="dropdown-item text-danger"
                                               onclick="return confirm('Are you sure you want to delete this tour?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($tours)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <h3>No tours found</h3>
                        <p>No tours match your current filters. Try adjusting your search criteria.</p>
                        <?php if (hasPermission('tours.create')): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Your First Tour
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper">
                        <div class="pagination-info">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_count); ?> 
                            of <?php echo $total_count; ?> tours
                        </div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/admin.js"></script>
    <script>
        // Bulk actions functionality
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.tour-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const bulkForm = document.getElementById('bulkForm');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = checkboxes.length + ' tour' + (checkboxes.length > 1 ? 's' : '') + ' selected';
                
                // Add selected tour IDs to form
                const existingInputs = bulkForm.querySelectorAll('input[name="selected_tours[]"]');
                existingInputs.forEach(input => input.remove());
                
                checkboxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_tours[]';
                    input.value = checkbox.value;
                    bulkForm.appendChild(input);
                });
            } else {
                bulkActions.classList.remove('show');
            }
        }
        
        // View toggle functionality
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const view = this.dataset.view;
                const grid = document.getElementById('toursGrid');
                
                if (view === 'list') {
                    grid.style.gridTemplateColumns = '1fr';
                } else {
                    grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(400px, 1fr))';
                }
            });
        });
        
        // Auto-submit filters on change
        document.querySelectorAll('.filters-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Search with debounce
        let searchTimeout;
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });
        }
    </script>
</body>
</html>
