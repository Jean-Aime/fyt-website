<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('destinations.view');

$page_title = 'Region Management';
$country_id = (int) ($_GET['country'] ?? 0);

// Get country information
$country = null;
if ($country_id) {
    $stmt = $db->prepare("SELECT * FROM countries WHERE id = ?");
    $stmt->execute([$country_id]);
    $country = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submissions
if ($_POST) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $action = $_POST['action'];

            if ($action === 'add' && hasPermission('destinations.create')) {
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $climate = trim($_POST['climate']);
                $attractions = trim($_POST['attractions']);
                $status = $_POST['status'];
                $region_country_id = (int) $_POST['country_id'];

                $stmt = $db->prepare("
                    INSERT INTO regions (country_id, name, description, climate, attractions, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([$region_country_id, $name, $description, $climate, $attractions, $status]);

                $auth->logActivity($_SESSION['user_id'], 'region_created', "Created region: $name");
                $success = 'Region added successfully!';

            } elseif ($action === 'edit' && hasPermission('destinations.edit')) {
                $region_id = (int) $_POST['region_id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $climate = trim($_POST['climate']);
                $attractions = trim($_POST['attractions']);
                $status = $_POST['status'];
                $region_country_id = (int) $_POST['country_id'];

                $stmt = $db->prepare("
                    UPDATE regions SET 
                        country_id = ?, name = ?, description = ?, climate = ?, 
                        attractions = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([$region_country_id, $name, $description, $climate, $attractions, $status, $region_id]);

                $auth->logActivity($_SESSION['user_id'], 'region_updated', "Updated region: $name");
                $success = 'Region updated successfully!';

            } elseif ($action === 'delete' && hasPermission('destinations.delete')) {
                $region_id = (int) $_POST['region_id'];

                // Check if region has tours
                $stmt = $db->prepare("SELECT COUNT(*) FROM tours WHERE region_id = ?");
                $stmt->execute([$region_id]);
                $tour_count = $stmt->fetchColumn();

                if ($tour_count > 0) {
                    $error = "Cannot delete region. It has $tour_count associated tours.";
                } else {
                    $stmt = $db->prepare("DELETE FROM regions WHERE id = ?");
                    $stmt->execute([$region_id]);

                    $auth->logActivity($_SESSION['user_id'], 'region_deleted', "Deleted region ID: $region_id");
                    $success = 'Region deleted successfully!';
                }
            }

        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get regions with statistics
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];

if ($country_id) {
    $where_conditions[] = "r.country_id = ?";
    $params[] = $country_id;
}

if ($search) {
    $where_conditions[] = "(r.name LIKE ? OR r.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "
    SELECT r.*, 
           c.name as country_name, c.flag_image as country_flag,
           COUNT(DISTINCT t.id) as tour_count
    FROM regions r
    LEFT JOIN countries c ON r.country_id = c.id
    LEFT JOIN tours t ON r.id = t.region_id
    $where_clause
    GROUP BY r.id
    ORDER BY c.name, r.name
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$regions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all countries for dropdown
$countries = $db->query("SELECT id, name FROM countries WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_where = $country_id ? "WHERE country_id = $country_id" : "";
$stats = $db->query("
    SELECT 
        COUNT(*) as total_regions,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_regions,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_regions
    FROM regions $stats_where
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
        .region-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .region-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        
        .region-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .region-header {
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            position: relative;
        }
        
        .region-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .region-status.active { background: #d4edda; color: #155724; }
        .region-status.inactive { background: #f8d7da; color: #721c24; }
        
        .region-name {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .region-country {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 0.9em;
        }
        
        .country-flag {
            width: 24px;
            height: 16px;
            border-radius: 2px;
            object-fit: cover;
        }
        
        .region-content {
            padding: 20px;
        }
        
        .region-description {
            color: #666;
            font-size: 0.9em;
            line-height: 1.5;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .region-details {
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.9em;
        }
        
        .detail-item i {
            width: 16px;
            text-align: center;
            color: var(--admin-primary);
            margin-top: 2px;
        }
        
        .region-stats {
            display: flex;
            justify-content: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
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
        
        .region-actions {
            display: flex;
            gap: 8px;
            justify-content: space-between;
        }
        
        .country-breadcrumb {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .breadcrumb-flag {
            width: 32px;
            height: 21px;
            border-radius: 4px;
            object-fit: cover;
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
                        <h2>Region Management</h2>
                        <p>Manage regions and areas within countries</p>
                    </div>
                    <div class="content-actions">
                        <a href="countries.php" class="btn btn-secondary">
                            <i class="fas fa-globe"></i> Countries
                        </a>
                        <?php if (hasPermission('destinations.create')): ?>
                            <button onclick="openAddModal()" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Region
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($country): ?>
                    <div class="country-breadcrumb">
                        <?php if ($country['flag_image']): ?>
                                <img src="../../<?php echo htmlspecialchars($country['flag_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($country['name']); ?>" class="breadcrumb-flag">
                        <?php endif; ?>
                        <div>
                            <strong>Viewing regions in: <?php echo htmlspecialchars($country['name']); ?></strong>
                            <div style="font-size: 0.9em; color: #666;">
                                <a href="regions.php" style="color: #666;">All Regions</a> / <?php echo htmlspecialchars($country['name']); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
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
                        <div class="stat-value"><?php echo number_format($stats['total_regions']); ?></div>
                        <div class="stat-label">Total Regions</div>
                    </div>
                    <div class="stat-card active">
                        <div class="stat-value"><?php echo number_format($stats['active_regions']); ?></div>
                        <div class="stat-label">Active Regions</div>
                    </div>
                    <div class="stat-card inactive">
                        <div class="stat-value"><?php echo number_format($stats['inactive_regions']); ?></div>
                        <div class="stat-label">Inactive Regions</div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <?php if ($country_id): ?>
                                <input type="hidden" name="country" value="<?php echo $country_id; ?>">
                        <?php endif; ?>
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search Regions</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name or description..." class="form-control">
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
                                <a href="regions.php<?php echo $country_id ? '?country=' . $country_id : ''; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Regions Grid -->
                <?php if (empty($regions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-map"></i>
                            <h3>No Regions Found</h3>
                            <p>No regions match your current filters. Try adjusting your search criteria.</p>
                            <?php if (hasPermission('destinations.create')): ?>
                                <button onclick="openAddModal()" class="btn btn-primary">Add Your First Region</button>
                            <?php endif; ?>
                        </div>
                <?php else: ?>
                        <div class="region-grid">
                            <?php foreach ($regions as $region): ?>
                                    <div class="region-card">
                                        <span class="region-status <?php echo $region['status']; ?>">
                                            <?php echo ucfirst($region['status']); ?>
                                        </span>
                                
                                        <div class="region-header">
                                            <div class="region-name"><?php echo htmlspecialchars($region['name']); ?></div>
                                            <div class="region-country">
                                                <?php if ($region['country_flag']): ?>
                                                        <img src="../../<?php echo htmlspecialchars($region['country_flag']); ?>" 
                                                             alt="<?php echo htmlspecialchars($region['country_name']); ?>" class="country-flag">
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($region['country_name'] ?? ''); ?>

                                            </div>
                                        </div>
                                
                                        <div class="region-content">
                                            <?php if ($region['description']): ?>
                                                    <div class="region-description">
                                                        <?php echo htmlspecialchars($region['description']); ?>
                                                    </div>
                                            <?php endif; ?>
                                    
                                            <div class="region-details">
                                                <?php if ($region['climate']): ?>
                                                        <div class="detail-item">
                                                            <i class="fas fa-thermometer-half"></i>
                                                            <div>
                                                                <strong>Climate:</strong> <?php echo htmlspecialchars($region['climate']); ?>
                                                            </div>
                                                        </div>
                                                <?php endif; ?>
                                        
                                                <?php if ($region['attractions']): ?>
                                                        <div class="detail-item">
                                                            <i class="fas fa-star"></i>
                                                            <div>
                                                                <strong>Attractions:</strong> <?php echo htmlspecialchars($region['attractions']); ?>
                                                            </div>
                                                        </div>
                                                <?php endif; ?>
                                            </div>
                                    
                                            <div class="region-stats">
                                                <div class="stat-item">
                                                    <div class="stat-value"><?php echo number_format($region['tour_count']); ?></div>
                                                    <div class="stat-label">Tours</div>
                                                </div>
                                            </div>
                                    
                                            <div class="region-actions">
                                                <?php if (hasPermission('destinations.edit')): ?>
                                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($region)); ?>)" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (hasPermission('destinations.delete') && $region['tour_count'] == 0): ?>
                                                    <button onclick="deleteRegion(<?php echo $region['id']; ?>, '<?php echo htmlspecialchars($region['name']); ?>')" class="btn btn-sm btn-danger">
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
    
    <!-- Add/Edit Region Modal -->
    <div id="regionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Region</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="regionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="region_id" id="regionId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Region Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="country_id">Country <span class="required">*</span></label>
                            <select id="country_id" name="country_id" class="form-control" required>
                                <option value="">Select Country</option>
                                <?php foreach ($countries as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" 
                                                <?php echo ($country_id == $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </option>
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
                        
                        <div class="form-group full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"
                                      placeholder="Brief description of the region..."></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="climate">Climate</label>
                            <textarea id="climate" name="climate" class="form-control" rows="2"
                                      placeholder="Climate information for this region..."></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="attractions">Main Attractions</label>
                            <textarea id="attractions" name="attractions" class="form-control" rows="3"
                                      placeholder="Key attractions and points of interest..."></textarea>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Region
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Region';
            document.getElementById('formAction').value = 'add';
            document.getElementById('regionForm').reset();
            
            // Pre-select country if viewing specific country
            <?php if ($country_id): ?>
                    document.getElementById('country_id').value = <?php echo $country_id; ?>;
            <?php endif; ?>
            
            document.getElementById('regionModal').style.display = 'block';
        }
        
        function openEditModal(region) {
            document.getElementById('modalTitle').textContent = 'Edit Region';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('regionId').value = region.id;
            
            // Populate form fields
            document.getElementById('name').value = region.name;
            document.getElementById('country_id').value = region.country_id;
            document.getElementById('status').value = region.status;
            document.getElementById('description').value = region.description || '';
            document.getElementById('climate').value = region.climate || '';
            document.getElementById('attractions').value = region.attractions || '';
            
            document.getElementById('regionModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('regionModal').style.display = 'none';
        }
        
        function deleteRegion(regionId, regionName) {
            if (confirm(`Are you sure you want to delete "${regionName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="region_id" value="${regionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('regionModal');
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
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>