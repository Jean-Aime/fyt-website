<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('destinations.view');

$page_title = 'Country Management';

// Handle form submissions
if ($_POST) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $action = $_POST['action'];
            
            if ($action === 'add' && hasPermission('destinations.create')) {
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $continent = $_POST['continent'];
                $currency = trim($_POST['currency']);
                $language = trim($_POST['language']);
                $timezone = $_POST['timezone'];
                $visa_required = isset($_POST['visa_required']) ? 1 : 0;
                $description = trim($_POST['description']);
                $travel_advisory = trim($_POST['travel_advisory']);
                $best_time_to_visit = trim($_POST['best_time_to_visit']);
                $travel_facts = trim($_POST['travel_facts']);
                $visa_tips = trim($_POST['visa_tips']);
                $status = $_POST['status'];
                $map_latitude = floatval($_POST['map_latitude']);
                $map_longitude = floatval($_POST['map_longitude']);
                $map_zoom = intval($_POST['map_zoom']);
                
                // Handle flag upload
                $flag_image = null;
                if (isset($_FILES['flag_image']) && $_FILES['flag_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../../uploads/flags/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['flag_image']['name'], PATHINFO_EXTENSION);
                    $filename = $code . '.' . $file_extension;
                    $file_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['flag_image']['tmp_name'], $file_path)) {
                        $flag_image = 'uploads/flags/' . $filename;
                    }
                }
                
                // Handle gallery images
                $gallery_images = [];
                if (isset($_FILES['gallery_images'])) {
                    $upload_dir = '../../uploads/countries/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_extension = pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION);
                            $filename = $code . '_' . time() . '_' . $key . '.' . $file_extension;
                            $file_path = $upload_dir . $filename;
                            
                            if (move_uploaded_file($tmp_name, $file_path)) {
                                $gallery_images[] = 'uploads/countries/' . $filename;
                            }
                        }
                    }
                }
                
                $stmt = $db->prepare("
                    INSERT INTO countries (
                        name, code, continent, currency, language, timezone,
                        visa_required, description, travel_advisory, best_time_to_visit,
                        travel_facts, visa_tips, flag_image, gallery_images,
                        map_latitude, map_longitude, map_zoom, status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $name, $code, $continent, $currency, $language, $timezone,
                    $visa_required, $description, $travel_advisory, $best_time_to_visit,
                    $travel_facts, $visa_tips, $flag_image, json_encode($gallery_images),
                    $map_latitude, $map_longitude, $map_zoom, $status, $_SESSION['user_id']
                ]);
                
                $auth->logActivity($_SESSION['user_id'], 'country_created', "Created country: $name");
                $success = 'Country added successfully!';
                
            } elseif ($action === 'edit' && hasPermission('destinations.edit')) {
                $country_id = (int)$_POST['country_id'];
                $name = trim($_POST['name']);
                $code = strtoupper(trim($_POST['code']));
                $continent = $_POST['continent'];
                $currency = trim($_POST['currency']);
                $language = trim($_POST['language']);
                $timezone = $_POST['timezone'];
                $visa_required = isset($_POST['visa_required']) ? 1 : 0;
                $description = trim($_POST['description']);
                $travel_advisory = trim($_POST['travel_advisory']);
                $best_time_to_visit = trim($_POST['best_time_to_visit']);
                $travel_facts = trim($_POST['travel_facts']);
                $visa_tips = trim($_POST['visa_tips']);
                $status = $_POST['status'];
                $map_latitude = floatval($_POST['map_latitude']);
                $map_longitude = floatval($_POST['map_longitude']);
                $map_zoom = intval($_POST['map_zoom']);
                
                // Get current country data
                $stmt = $db->prepare("SELECT flag_image, gallery_images FROM countries WHERE id = ?");
                $stmt->execute([$country_id]);
                $current_country = $stmt->fetch(PDO::FETCH_ASSOC);
                $flag_image = $current_country['flag_image'];
                $gallery_images = json_decode($current_country['gallery_images'] ?? '[]', true);
                
                // Handle flag upload
                if (isset($_FILES['flag_image']) && $_FILES['flag_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../../uploads/flags/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['flag_image']['name'], PATHINFO_EXTENSION);
                    $filename = $code . '.' . $file_extension;
                    $file_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['flag_image']['tmp_name'], $file_path)) {
                        $flag_image = 'uploads/flags/' . $filename;
                    }
                }
                
                // Handle gallery images
                if (isset($_FILES['gallery_images'])) {
                    $upload_dir = '../../uploads/countries/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_extension = pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION);
                            $filename = $code . '_' . time() . '_' . $key . '.' . $file_extension;
                            $file_path = $upload_dir . $filename;
                            
                            if (move_uploaded_file($tmp_name, $file_path)) {
                                $gallery_images[] = 'uploads/countries/' . $filename;
                            }
                        }
                    }
                }
                
                $stmt = $db->prepare("
                    UPDATE countries SET 
                        name = ?, code = ?, continent = ?, currency = ?, language = ?,
                        timezone = ?, visa_required = ?, description = ?, travel_advisory = ?,
                        best_time_to_visit = ?, travel_facts = ?, visa_tips = ?, flag_image = ?,
                        gallery_images = ?, map_latitude = ?, map_longitude = ?, map_zoom = ?,
                        status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $name, $code, $continent, $currency, $language, $timezone,
                    $visa_required, $description, $travel_advisory, $best_time_to_visit,
                    $travel_facts, $visa_tips, $flag_image, json_encode($gallery_images),
                    $map_latitude, $map_longitude, $map_zoom, $status, $country_id
                ]);
                
                $auth->logActivity($_SESSION['user_id'], 'country_updated', "Updated country: $name");
                $success = 'Country updated successfully!';
                
            } elseif ($action === 'delete' && hasPermission('destinations.delete')) {
                $country_id = (int)$_POST['country_id'];
                
                // Check if country has tours
                $stmt = $db->prepare("SELECT COUNT(*) FROM tours WHERE country_id = ?");
                $stmt->execute([$country_id]);
                $tour_count = $stmt->fetchColumn();
                
                if ($tour_count > 0) {
                    $error = "Cannot delete country. It has $tour_count associated tours.";
                } else {
                    $stmt = $db->prepare("DELETE FROM countries WHERE id = ?");
                    $stmt->execute([$country_id]);
                    
                    $auth->logActivity($_SESSION['user_id'], 'country_deleted', "Deleted country ID: $country_id");
                    $success = 'Country deleted successfully!';
                }
            }
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get countries with statistics
$search = $_GET['search'] ?? '';
$continent_filter = $_GET['continent'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(c.name LIKE ? OR c.code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($continent_filter) {
    $where_conditions[] = "c.continent = ?";
    $params[] = $continent_filter;
}

if ($status_filter) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "
    SELECT c.*, 
           COUNT(DISTINCT t.id) as tour_count,
           COUNT(DISTINCT r.id) as region_count,
           COUNT(DISTINCT b.id) as booking_count
    FROM countries c
    LEFT JOIN tours t ON c.id = t.country_id
    LEFT JOIN regions r ON c.id = r.country_id
    LEFT JOIN bookings b ON t.id = b.tour_id
    $where_clause
    GROUP BY c.id
    ORDER BY c.name
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_countries,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_countries,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_countries
    FROM countries
")->fetch(PDO::FETCH_ASSOC);

// Get continents for filter
$continents = [
    'Africa', 'Asia', 'Europe', 'North America', 'South America', 'Oceania', 'Antarctica'
];

// Get timezones
$timezones = timezone_identifiers_list();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        .country-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .country-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .country-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .country-header {
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            position: relative;
        }
        
        .country-flag {
            width: 60px;
            height: 40px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid #ddd;
            float: left;
            margin-right: 15px;
        }
        
        .flag-placeholder {
            width: 60px;
            height: 40px;
            border-radius: 4px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            float: left;
            margin-right: 15px;
            color: #999;
        }
        
        .country-name {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .country-code {
            font-size: 0.9em;
            color: #666;
            font-weight: 500;
        }
        
        .country-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .country-status.active { background: #d4edda; color: #155724; }
        .country-status.inactive { background: #f8d7da; color: #721c24; }
        
        .country-content {
            padding: 20px;
        }
        
        .country-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            color: #666;
        }
        
        .info-item i {
            width: 16px;
            text-align: center;
            color: var(--admin-primary);
        }
        
        .country-description {
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .country-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
        }
        
        .stat-label {
            font-size: 0.8em;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .country-actions {
            display: flex;
            gap: 8px;
            justify-content: space-between;
        }
        
        .gallery-preview {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }
        
        .gallery-thumb {
            width: 40px;
            height: 30px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid #ddd;
        }
        
        .map-container {
            height: 200px;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.4em;
            font-weight: 600;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .image-upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .image-upload-area:hover {
            border-color: var(--admin-primary);
            background: rgba(212, 165, 116, 0.05);
        }
        
        .upload-icon {
            font-size: 2em;
            color: #999;
            margin-bottom: 10px;
        }
        
        .map-picker {
            height: 300px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .coordinate-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
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
                        <h2>Country Management</h2>
                        <p>Manage countries, travel information, and destination details</p>
                    </div>
                    <div class="content-actions">
                        <a href="regions.php" class="btn btn-secondary">
                            <i class="fas fa-map"></i> Manage Regions
                        </a>
                        <?php if (hasPermission('destinations.create')): ?>
                        <button onclick="openAddModal()" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Country
                        </button>
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
                        <div class="stat-value"><?php echo number_format($stats['total_countries']); ?></div>
                        <div class="stat-label">Total Countries</div>
                    </div>
                    <div class="stat-card active">
                        <div class="stat-value"><?php echo number_format($stats['active_countries']); ?></div>
                        <div class="stat-label">Active Countries</div>
                    </div>
                    <div class="stat-card inactive">
                        <div class="stat-value"><?php echo number_format($stats['inactive_countries']); ?></div>
                        <div class="stat-label">Inactive Countries</div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search Countries</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name or code..." class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Continent</label>
                                <select name="continent" class="form-control">
                                    <option value="">All Continents</option>
                                    <?php foreach ($continents as $continent): ?>
                                        <option value="<?php echo $continent; ?>" 
                                                <?php echo $continent_filter === $continent ? 'selected' : ''; ?>>
                                            <?php echo $continent; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="countries.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Countries Grid -->
                <?php if (empty($countries)): ?>
                    <div class="empty-state">
                        <i class="fas fa-globe"></i>
                        <h3>No Countries Found</h3>
                        <p>No countries match your current filters. Try adjusting your search criteria.</p>
                        <?php if (hasPermission('destinations.create')): ?>
                        <button onclick="openAddModal()" class="btn btn-primary">Add Your First Country</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="country-grid">
                        <?php foreach ($countries as $country): ?>
                            <div class="country-card" style="position: relative;">
                                <span class="country-status <?php echo $country['status']; ?>">
                                    <?php echo ucfirst($country['status']); ?>
                                </span>
                                
                                <div class="country-header">
                                    <?php if ($country['flag_image']): ?>
                                        <img src="../../<?php echo htmlspecialchars($country['flag_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($country['name']); ?> Flag" class="country-flag">
                                    <?php else: ?>
                                        <div class="flag-placeholder">
                                            <i class="fas fa-flag"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <div class="country-name"><?php echo htmlspecialchars($country['name']); ?></div>
                                        <div class="country-code"><?php echo htmlspecialchars($country['code']); ?></div>
                                    </div>
                                    <div style="clear: both;"></div>
                                </div>
                                
                                <div class="country-content">
                                    <div class="country-info">
                                        <div class="info-item">
                                            <i class="fas fa-globe-americas"></i>
                                            <?php echo htmlspecialchars($country['continent'] ?? ''); ?>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-money-bill"></i>
                                            <?php echo htmlspecialchars($country['currency'] ?? ''); ?>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-language"></i>
                                            <?php echo htmlspecialchars($country['language'] ?? ''); ?>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-clock"></i>
                                            <?php echo htmlspecialchars($country['timezone'] ?? ''); ?>
                                        </div>
                                        <?php if ($country['visa_required']): ?>
                                            <div class="info-item">
                                                <i class="fas fa-passport"></i>
                                                Visa Required
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($country['description']): ?>
                                        <div class="country-description">
                                            <?php echo htmlspecialchars($country['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Map Display -->
                                    <?php if ($country['map_latitude'] && $country['map_longitude']): ?>
                                        <div class="map-container" id="map_<?php echo $country['id']; ?>"></div>
                                    <?php endif; ?>
                                    
                                    <!-- Gallery Preview -->
                                    <?php 
                                    $gallery = json_decode($country['gallery_images'] ?? '[]', true);
                                    if (!empty($gallery)): 
                                    ?>
                                        <div class="gallery-preview">
                                            <?php foreach (array_slice($gallery, 0, 5) as $image): ?>
                                                <img src="../../<?php echo htmlspecialchars($image); ?>" 
                                                     alt="Gallery" class="gallery-thumb">
                                            <?php endforeach; ?>
                                            <?php if (count($gallery) > 5): ?>
                                                <div class="gallery-thumb" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666; font-size: 0.8em;">
                                                    +<?php echo count($gallery) - 5; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="country-stats">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo number_format($country['tour_count']); ?></div>
                                            <div class="stat-label">Tours</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo number_format($country['region_count']); ?></div>
                                            <div class="stat-label">Regions</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo number_format($country['booking_count']); ?></div>
                                            <div class="stat-label">Bookings</div>
                                        </div>
                                    </div>
                                    
                                    <div class="country-actions">
                                        <a href="regions.php?country=<?php echo $country['id']; ?>" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-map"></i> Regions
                                        </a>
                                        <?php if (hasPermission('destinations.edit')): ?>
                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($country)); ?>)" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <?php endif; ?>
                                        <?php if (hasPermission('destinations.delete') && $country['tour_count'] == 0): ?>
                                        <button onclick="deleteCountry(<?php echo $country['id']; ?>, '<?php echo htmlspecialchars($country['name']); ?>')" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Country Modal -->
    <div id="countryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Country</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="countryForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="country_id" id="countryId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Country Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="code">Country Code <span class="required">*</span></label>
                            <input type="text" id="code" name="code" class="form-control" maxlength="3" required
                                   placeholder="e.g., USA, RWA">
                        </div>
                        
                        <div class="form-group">
                            <label for="continent">Continent <span class="required">*</span></label>
                            <select id="continent" name="continent" class="form-control" required>
                                <option value="">Select Continent</option>
                                <?php foreach ($continents as $continent): ?>
                                    <option value="<?php echo $continent; ?>"><?php echo $continent; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="currency">Currency</label>
                            <input type="text" id="currency" name="currency" class="form-control" 
                                   placeholder="e.g., USD, RWF">
                        </div>
                        
                        <div class="form-group">
                            <label for="language">Primary Language</label>
                            <input type="text" id="language" name="language" class="form-control" 
                                   placeholder="e.g., English, Kinyarwanda">
                        </div>
                        
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select id="timezone" name="timezone" class="form-control">
                                <option value="">Select Timezone</option>
                                <?php foreach (array_slice($timezones, 0, 50) as $timezone): ?>
                                    <option value="<?php echo $timezone; ?>"><?php echo $timezone; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="visa_required" id="visa_required" value="1">
                                Visa Required
                            </label>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="flag_image">Flag Image</label>
                            <div class="image-upload-area" onclick="document.getElementById('flag_image').click()">
                                <div class="upload-icon">
                                    <i class="fas fa-flag"></i>
                                </div>
                                <div>Click to upload flag image</div>
                                <div style="font-size: 0.9em; color: #666; margin-top: 5px;">
                                    Recommended: 150x100px, PNG or JPG
                                </div>
                            </div>
                            <input type="file" id="flag_image" name="flag_image" accept="image/*" style="display: none;">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="gallery_images">Gallery Images</label>
                            <div class="image-upload-area" onclick="document.getElementById('gallery_images').click()">
                                <div class="upload-icon">
                                    <i class="fas fa-images"></i>
                                </div>
                                <div>Click to upload gallery images</div>
                                <div style="font-size: 0.9em; color: #666; margin-top: 5px;">
                                    Multiple images allowed
                                </div>
                            </div>
                            <input type="file" id="gallery_images" name="gallery_images[]" accept="image/*" multiple style="display: none;">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"
                                      placeholder="Brief description of the country..."></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="travel_facts">Travel Facts</label>
                            <textarea id="travel_facts" name="travel_facts" class="form-control" rows="3"
                                      placeholder="Interesting facts about traveling to this country..."></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="visa_tips">Visa Tips</label>
                            <textarea id="visa_tips" name="visa_tips" class="form-control" rows="3"
                                      placeholder="Visa requirements and tips for travelers..."></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="travel_advisory">Travel Advisory</label>
                            <textarea id="travel_advisory" name="travel_advisory" class="form-control" rows="3"
                                      placeholder="Current travel advisories or safety information..."></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="best_time_to_visit">Best Time to Visit</label>
                            <textarea id="best_time_to_visit" name="best_time_to_visit" class="form-control" rows="2"
                                      placeholder="Recommended seasons or months to visit..."></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Map Location</label>
                            <div class="coordinate-inputs">
                                <input type="number" id="map_latitude" name="map_latitude" class="form-control" 
                                       placeholder="Latitude" step="any">
                                <input type="number" id="map_longitude" name="map_longitude" class="form-control" 
                                       placeholder="Longitude" step="any">
                                <input type="number" id="map_zoom" name="map_zoom" class="form-control" 
                                       placeholder="Zoom Level" min="1" max="18" value="6">
                            </div>
                            <div id="mapPicker" class="map-picker"></div>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Country
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        let mapPicker;
        let mapMarker;
        
        function initMapPicker() {
            mapPicker = L.map('mapPicker').setView([0, 0], 2);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(mapPicker);
            
            mapPicker.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                
                document.getElementById('map_latitude').value = lat.toFixed(6);
                document.getElementById('map_longitude').value = lng.toFixed(6);
                
                if (mapMarker) {
                    mapPicker.removeLayer(mapMarker);
                }
                
                mapMarker = L.marker([lat, lng]).addTo(mapPicker);
            });
        }
        
        function updateMapMarker() {
            const lat = parseFloat(document.getElementById('map_latitude').value);
            const lng = parseFloat(document.getElementById('map_longitude').value);
            const zoom = parseInt(document.getElementById('map_zoom').value) || 6;
            
            if (lat && lng) {
                mapPicker.setView([lat, lng], zoom);
                
                if (mapMarker) {
                    mapPicker.removeLayer(mapMarker);
                }
                
                mapMarker = L.marker([lat, lng]).addTo(mapPicker);
            }
        }
        
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Country';
            document.getElementById('formAction').value = 'add';
            document.getElementById('countryForm').reset();
            document.getElementById('countryModal').style.display = 'block';
            
            setTimeout(() => {
                initMapPicker();
            }, 100);
        }
        
        function openEditModal(country) {
            document.getElementById('modalTitle').textContent = 'Edit Country';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('countryId').value = country.id;
            
            // Populate form fields
            document.getElementById('name').value = country.name;
            document.getElementById('code').value = country.code;
            document.getElementById('continent').value = country.continent;
            document.getElementById('currency').value = country.currency || '';
            document.getElementById('language').value = country.language || '';
            document.getElementById('timezone').value = country.timezone || '';
            document.getElementById('status').value = country.status;
            document.getElementById('visa_required').checked = country.visa_required == 1;
            document.getElementById('description').value = country.description || '';
            document.getElementById('travel_facts').value = country.travel_facts || '';
            document.getElementById('visa_tips').value = country.visa_tips || '';
            document.getElementById('travel_advisory').value = country.travel_advisory || '';
            document.getElementById('best_time_to_visit').value = country.best_time_to_visit || '';
            document.getElementById('map_latitude').value = country.map_latitude || '';
            document.getElementById('map_longitude').value = country.map_longitude || '';
            document.getElementById('map_zoom').value = country.map_zoom || 6;
            
            document.getElementById('countryModal').style.display = 'block';
            
            setTimeout(() => {
                initMapPicker();
                updateMapMarker();
            }, 100);
        }
        
        function closeModal() {
            document.getElementById('countryModal').style.display = 'none';
            if (mapPicker) {
                mapPicker.remove();
            }
        }
        
        function deleteCountry(countryId, countryName) {
            if (confirm(`Are you sure you want to delete "${countryName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="country_id" value="${countryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Initialize maps for country cards
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($countries as $country): ?>
                <?php if ($country['map_latitude'] && $country['map_longitude']): ?>
                    const map_<?php echo $country['id']; ?> = L.map('map_<?php echo $country['id']; ?>').setView([<?php echo $country['map_latitude']; ?>, <?php echo $country['map_longitude']; ?>], <?php echo $country['map_zoom'] ?: 6; ?>);
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }).addTo(map_<?php echo $country['id']; ?>);
                    
                    L.marker([<?php echo $country['map_latitude']; ?>, <?php echo $country['map_longitude']; ?>]).addTo(map_<?php echo $country['id']; ?>);
                <?php endif; ?>
            <?php endforeach; ?>
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('countryModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Auto-submit filters on change
        document.querySelectorAll('.filters-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Image preview
        document.getElementById('flag_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const uploadArea = document.querySelector('.image-upload-area');
                    uploadArea.innerHTML = `
                        <img src="${e.target.result}" alt="Flag Preview" style="max-width: 150px; max-height: 100px; border-radius: 4px;">
                        <div style="margin-top: 10px;">Click to change flag image</div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Coordinate input listeners
        document.getElementById('map_latitude').addEventListener('input', updateMapMarker);
        document.getElementById('map_longitude').addEventListener('input', updateMapMarker);
        document.getElementById('map_zoom').addEventListener('input', updateMapMarker);
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
