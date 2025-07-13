<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('tours.edit');

$tour_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$tour_id) {
    header('Location: index.php?error=Tour not found');
    exit;
}

// Get existing tour data
$stmt = $db->prepare("SELECT * FROM tours WHERE id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header('Location: index.php?error=Tour not found');
    exit;
}

$page_title = 'Edit Tour: ' . $tour['title'];

// Handle form submission
if ($_POST && verifyCSRFToken($_POST['csrf_token'])) {
    try {
        $title = trim($_POST['title']);
        $slug = trim($_POST['slug']) ?: generateSlug($title);
        $short_description = trim($_POST['short_description']);
        $full_description = trim($_POST['full_description']);
        $country_id = (int)$_POST['country_id'];
        $region_id = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
        $category_id = (int)$_POST['category_id'];
        $duration_days = (int)$_POST['duration_days'];
        $duration_nights = (int)$_POST['duration_nights'];
        $min_group_size = (int)$_POST['min_group_size'];
        $max_group_size = (int)$_POST['max_group_size'];
        $difficulty_level = $_POST['difficulty_level'];
        $price_adult = (float)$_POST['price_adult'];
        $price_child = !empty($_POST['price_child']) ? (float)$_POST['price_child'] : null;
        $price_infant = !empty($_POST['price_infant']) ? (float)$_POST['price_infant'] : null;
        $currency = $_POST['currency'];
        $includes = trim($_POST['includes']);
        $excludes = trim($_POST['excludes']);
        $requirements = trim($_POST['requirements']);
        $cancellation_policy = trim($_POST['cancellation_policy']);
        $status = $_POST['status'];
        $featured = isset($_POST['featured']) ? 1 : 0;
        
        // Handle file uploads
        $featured_image = $tour['featured_image'];
        $gallery = json_decode($tour['gallery'], true) ?: [];
        $brochure_pdf = $tour['brochure_pdf'];
        
        // Upload featured image
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/tours/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $file_path)) {
                // Delete old image
                if ($featured_image && file_exists('../../' . $featured_image)) {
                    unlink('../../' . $featured_image);
                }
                $featured_image = 'uploads/tours/' . $filename;
            }
        }
        
        // Upload gallery images
        if (isset($_FILES['gallery']) && !empty($_FILES['gallery']['name'][0])) {
            $upload_dir = '../../uploads/tours/gallery/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['gallery']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_extension = pathinfo($_FILES['gallery']['name'][$key], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $gallery[] = 'uploads/tours/gallery/' . $filename;
                    }
                }
            }
        }
        
        // Upload brochure PDF
        if (isset($_FILES['brochure_pdf']) && $_FILES['brochure_pdf']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/tours/brochures/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['brochure_pdf']['name'], PATHINFO_EXTENSION);
            if (strtolower($file_extension) === 'pdf') {
                $filename = uniqid() . '.pdf';
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['brochure_pdf']['tmp_name'], $file_path)) {
                    // Delete old brochure
                    if ($brochure_pdf && file_exists('../../' . $brochure_pdf)) {
                        unlink('../../' . $brochure_pdf);
                    }
                    $brochure_pdf = 'uploads/tours/brochures/' . $filename;
                }
            }
        }
        
        // Update tour
        $stmt = $db->prepare("
            UPDATE tours SET 
                title = ?, slug = ?, short_description = ?, full_description = ?,
                country_id = ?, region_id = ?, category_id = ?, duration_days = ?,
                duration_nights = ?, min_group_size = ?, max_group_size = ?,
                difficulty_level = ?, price_adult = ?, price_child = ?, price_infant = ?,
                currency = ?, includes = ?, excludes = ?, requirements = ?,
                cancellation_policy = ?, featured_image = ?, gallery = ?,
                brochure_pdf = ?, status = ?, featured = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $title, $slug, $short_description, $full_description,
            $country_id, $region_id, $category_id, $duration_days,
            $duration_nights, $min_group_size, $max_group_size,
            $difficulty_level, $price_adult, $price_child, $price_infant,
            $currency, $includes, $excludes, $requirements,
            $cancellation_policy, $featured_image, json_encode($gallery),
            $brochure_pdf, $status, $featured, $tour_id
        ]);
        
        $auth->logActivity($_SESSION['user_id'], 'tour_updated', "Updated tour: $title");
        $success = 'Tour updated successfully!';
        
        // Refresh tour data
        $stmt = $db->prepare("SELECT * FROM tours WHERE id = ?");
        $stmt->execute([$tour_id]);
        $tour = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = 'Error updating tour: ' . $e->getMessage();
    }
}

// Get form data
$countries = $db->query("SELECT id, name FROM countries WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$categories = $db->query("SELECT id, name FROM tour_categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get regions for selected country
$regions = [];
if ($tour['country_id']) {
    $stmt = $db->prepare("SELECT id, name FROM regions WHERE country_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$tour['country_id']]);
    $regions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
    <style>
        .form-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .form-sections {
            display: grid;
            gap: 30px;
        }
        
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--admin-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
        }
        
        .section-title {
            font-size: 1.4em;
            font-weight: 600;
            color: #333;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-grid.two-col {
            grid-template-columns: 1fr 1fr;
        }
        
        .form-grid.three-col {
            grid-template-columns: 1fr 1fr 1fr;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .image-upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .image-upload-area:hover {
            border-color: var(--admin-primary);
            background: rgba(212, 165, 116, 0.05);
        }
        
        .image-upload-area.has-image {
            border-style: solid;
            border-color: var(--admin-primary);
        }
        
        .upload-icon {
            font-size: 3em;
            color: #999;
            margin-bottom: 15px;
        }
        
        .upload-text {
            color: #666;
            margin-bottom: 10px;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            margin: 10px auto;
            display: block;
        }
        
        .gallery-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .gallery-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .gallery-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }
        
        .gallery-item .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255,0,0,0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 0.8em;
        }
        
        .price-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            align-items: end;
        }
        
        .duration-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .group-size-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-actions {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn-save {
            background: var(--admin-primary);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            background: #B8956A;
            transform: translateY(-2px);
        }
        
        .btn-draft {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-draft:hover {
            background: #5a6268;
        }
        
        .form-help {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--admin-primary);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
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
                        <h2><?php echo $page_title; ?></h2>
                        <p>Update tour information and settings</p>
                    </div>
                    <div class="content-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Tours
                        </a>
                        <a href="view.php?id=<?php echo $tour_id; ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i> Preview Tour
                        </a>
                        <a href="../../tour-details.php?slug=<?php echo $tour['slug']; ?>" target="_blank" class="btn btn-success">
                            <i class="fas fa-external-link-alt"></i> View Live
                        </a>
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
                
                <form method="POST" enctype="multipart/form-data" id="tourForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-container">
                        <div class="form-sections">
                            <!-- Basic Information -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <h3 class="section-title">Basic Information</h3>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label for="title">Tour Title <span class="required">*</span></label>
                                        <input type="text" id="title" name="title" class="form-control" required
                                               value="<?php echo htmlspecialchars($tour['title']); ?>"
                                               onkeyup="generateSlugFromTitle()">
                                        <div class="form-help">Enter a descriptive title for your tour</div>
                                    </div>
                                    
                                    <div class="form-group full-width">
                                        <label for="slug">URL Slug <span class="required">*</span></label>
                                        <input type="text" id="slug" name="slug" class="form-control" required
                                               value="<?php echo htmlspecialchars($tour['slug']); ?>">
                                        <div class="form-help">URL-friendly version of the title</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="country_id">Country <span class="required">*</span></label>
                                        <select id="country_id" name="country_id" class="form-control" required onchange="loadRegions()">
                                            <option value="">Select Country</option>
                                            <?php foreach ($countries as $country): ?>
                                                <option value="<?php echo $country['id']; ?>" 
                                                        <?php echo $tour['country_id'] == $country['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($country['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="region_id">Region</label>
                                        <select id="region_id" name="region_id" class="form-control">
                                            <option value="">Select Region</option>
                                            <?php foreach ($regions as $region): ?>
                                                <option value="<?php echo $region['id']; ?>" 
                                                        <?php echo $tour['region_id'] == $region['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($region['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="category_id">Category <span class="required">*</span></label>
                                        <select id="category_id" name="category_id" class="form-control" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>" 
                                                        <?php echo $tour['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="difficulty_level">Difficulty Level</label>
                                        <select id="difficulty_level" name="difficulty_level" class="form-control">
                                            <option value="easy" <?php echo $tour['difficulty_level'] === 'easy' ? 'selected' : ''; ?>>Easy</option>
                                            <option value="moderate" <?php echo $tour['difficulty_level'] === 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                                            <option value="challenging" <?php echo $tour['difficulty_level'] === 'challenging' ? 'selected' : ''; ?>>Challenging</option>
                                            <option value="extreme" <?php echo $tour['difficulty_level'] === 'extreme' ? 'selected' : ''; ?>>Extreme</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group full-width">
                                        <label for="short_description">Short Description</label>
                                        <textarea id="short_description" name="short_description" class="form-control" rows="3"
                                                  placeholder="Brief description for tour listings..."><?php echo htmlspecialchars($tour['short_description']); ?></textarea>
                                        <div class="form-help">Brief description shown in tour listings (recommended: 150-200 characters)</div>
                                    </div>
                                    
                                    <div class="form-group full-width">
                                        <label for="full_description">Full Description</label>
                                        <textarea id="full_description" name="full_description" class="form-control" rows="8"><?php echo htmlspecialchars($tour['full_description']); ?></textarea>
                                        <div class="form-help">Detailed description with tour highlights, activities, and what to expect</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Duration & Group Size -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <h3 class="section-title">Duration & Group Size</h3>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="duration-grid">
                                        <div class="form-group">
                                            <label for="duration_days">Duration (Days) <span class="required">*</span></label>
                                            <input type="number" id="duration_days" name="duration_days" class="form-control" 
                                                   min="1" max="365" required value="<?php echo $tour['duration_days']; ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="duration_nights">Duration (Nights)</label>
                                            <input type="number" id="duration_nights" name="duration_nights" class="form-control" 
                                                   min="0" max="364" value="<?php echo $tour['duration_nights']; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="group-size-grid">
                                        <div class="form-group">
                                            <label for="min_group_size">Min Group Size</label>
                                            <input type="number" id="min_group_size" name="min_group_size" class="form-control" 
                                                   min="1" max="100" value="<?php echo $tour['min_group_size']; ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="max_group_size">Max Group Size</label>
                                            <input type="number" id="max_group_size" name="max_group_size" class="form-control" 
                                                   min="1" max="100" value="<?php echo $tour['max_group_size']; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Pricing -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-dollar-sign"></i>
                                    </div>
                                    <h3 class="section-title">Pricing</h3>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="currency">Currency</label>
                                        <select id="currency" name="currency" class="form-control">
                                            <option value="USD" <?php echo $tour['currency'] === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                            <option value="EUR" <?php echo $tour['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                            <option value="GBP" <?php echo $tour['currency'] === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                                            <option value="RWF" <?php echo $tour['currency'] === 'RWF' ? 'selected' : ''; ?>>RWF (₣)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="price-grid">
                                        <div class="form-group">
                                            <label for="price_adult">Adult Price <span class="required">*</span></label>
                                            <input type="number" id="price_adult" name="price_adult" class="form-control" 
                                                   step="0.01" min="0" required value="<?php echo $tour['price_adult']; ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="price_child">Child Price</label>
                                            <input type="number" id="price_child" name="price_child" class="form-control" 
                                                   step="0.01" min="0" value="<?php echo $tour['price_child']; ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="price_infant">Infant Price</label>
                                            <input type="number" id="price_infant" name="price_infant" class="form-control" 
                                                   step="0.01" min="0" value="<?php echo $tour['price_infant']; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tour Details -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-list-ul"></i>
                                    </div>
                                    <h3 class="section-title">Tour Details</h3>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="includes">What's Included</label>
                                        <textarea id="includes" name="includes" class="form-control" rows="5"
                                                  placeholder="• Accommodation&#10;• Meals&#10;• Transportation&#10;• Guide services"><?php echo htmlspecialchars($tour['includes']); ?></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="excludes">What's Excluded</label>
                                        <textarea id="excludes" name="excludes" class="form-control" rows="5"
                                                  placeholder="• International flights&#10;• Travel insurance&#10;• Personal expenses"><?php echo htmlspecialchars($tour['excludes']); ?></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="requirements">Requirements</label>
                                        <textarea id="requirements" name="requirements" class="form-control" rows="5"
                                                  placeholder="• Valid passport&#10;• Fitness level&#10;• Age restrictions"><?php echo htmlspecialchars($tour['requirements']); ?></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="cancellation_policy">Cancellation Policy</label>
                                        <textarea id="cancellation_policy" name="cancellation_policy" class="form-control" rows="5"><?php echo htmlspecialchars($tour['cancellation_policy']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Media -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-images"></i>
                                    </div>
                                    <h3 class="section-title">Media</h3>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="featured_image">Featured Image</label>
                                        <div class="image-upload-area <?php echo $tour['featured_image'] ? 'has-image' : ''; ?>" onclick="document.getElementById('featured_image').click()">
                                            <?php if ($tour['featured_image']): ?>
                                                <img src="../../<?php echo htmlspecialchars($tour['featured_image']); ?>" alt="Featured Image" class="image-preview">
                                                <p class="upload-text">Click to change featured image</p>
                                            <?php else: ?>
                                                <div class="upload-icon">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                </div>
                                                <p class="upload-text">Click to upload featured image</p>
                                                <p style="font-size: 0.9em; color: #999;">Recommended: 1200x800px, JPG/PNG, max 5MB</p>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" id="featured_image" name="featured_image" accept="image/*" style="display: none;">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="gallery">Gallery Images</label>
                                        <div class="image-upload-area" onclick="document.getElementById('gallery').click()">
                                            <div class="upload-icon">
                                                <i class="fas fa-images"></i>
                                            </div>
                                            <p class="upload-text">Click to add gallery images</p>
                                            <p style="font-size: 0.9em; color: #999;">Multiple images allowed, max 5MB each</p>
                                        </div>
                                        <input type="file" id="gallery" name="gallery[]" accept="image/*" multiple style="display: none;">
                                        
                                        <?php if ($tour['gallery']): ?>
                                            <div class="gallery-preview">
                                                <?php 
                                                $gallery_images = json_decode($tour['gallery'], true);
                                                foreach ($gallery_images as $index => $image): 
                                                ?>
                                                    <div class="gallery-item">
                                                        <img src="../../<?php echo htmlspecialchars($image); ?>" alt="Gallery Image">
                                                        <button type="button" class="remove-btn" onclick="removeGalleryImage(<?php echo $index; ?>)">×</button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="brochure_pdf">Tour Brochure (PDF)</label>
                                        <div class="image-upload-area" onclick="document.getElementById('brochure_pdf').click()">
                                            <?php if ($tour['brochure_pdf']): ?>
                                                <div class="upload-icon">
                                                    <i class="fas fa-file-pdf" style="color: #dc3545;"></i>
                                                </div>
                                                <p class="upload-text">Current: <?php echo basename($tour['brochure_pdf']); ?></p>
                                                <p style="font-size: 0.9em; color: #999;">Click to replace brochure</p>
                                            <?php else: ?>
                                                <div class="upload-icon">
                                                    <i class="fas fa-file-pdf"></i>
                                                </div>
                                                <p class="upload-text">Click to upload tour brochure</p>
                                                <p style="font-size: 0.9em; color: #999;">PDF format, max 10MB</p>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" id="brochure_pdf" name="brochure_pdf" accept=".pdf" style="display: none;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Settings -->
                            <div class="form-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-cog"></i>
                                    </div>
                                    <h3 class="section-title">Settings</h3>
                                </div>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select id="status" name="status" class="form-control">
                                            <option value="active" <?php echo $tour['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $tour['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="draft" <?php echo $tour['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Featured Tour</label>
                                        <div style="margin-top: 10px;">
                                            <label class="toggle-switch">
                                                <input type="checkbox" name="featured" <?php echo $tour['featured'] ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <span style="margin-left: 15px; color: #666;">Show in featured tours section</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <div>
                                <small class="text-muted">Last updated: <?php echo date('M j, Y g:i A', strtotime($tour['updated_at'])); ?></small>
                            </div>
                            <div class="action-buttons">
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="status" value="draft" class="btn-draft">
                                    <i class="fas fa-save"></i> Save as Draft
                                </button>
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-check"></i> Update Tour
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize CKEditor
        CKEDITOR.replace('full_description', {
            height: 300,
            toolbar: [
                { name: 'document', items: ['Source'] },
                { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', 'Undo', 'Redo'] },
                { name: 'editing', items: ['Find', 'Replace'] },
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike'] },
                { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent'] },
                { name: 'links', items: ['Link', 'Unlink'] },
                { name: 'insert', items: ['Image', 'Table', 'HorizontalRule'] },
                { name: 'styles', items: ['Format', 'Font', 'FontSize'] },
                { name: 'colors', items: ['TextColor', 'BGColor'] }
            ]
        });
        
        // Generate slug from title
        function generateSlugFromTitle() {
            const title = document.getElementById('title').value;
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/[\s-]+/g, '-')
                .replace(/^-+|-+$/g, '');
            document.getElementById('slug').value = slug;
        }
        
        // Load regions based on selected country
        function loadRegions() {
            const countryId = document.getElementById('country_id').value;
            const regionSelect = document.getElementById('region_id');
            
            if (!countryId) {
                regionSelect.innerHTML = '<option value="">Select Region</option>';
                return;
            }
            
            fetch(`../api/get-regions.php?country_id=${countryId}`)
                .then(response => response.json())
                .then(data => {
                    regionSelect.innerHTML = '<option value="">Select Region</option>';
                    data.forEach(region => {
                        regionSelect.innerHTML += `<option value="${region.id}">${region.name}</option>`;
                    });
                })
                .catch(error => {
                    console.error('Error loading regions:', error);
                });
        }
        
        // Remove gallery image
        function removeGalleryImage(index) {
            if (confirm('Are you sure you want to remove this image?')) {
                // This would need to be implemented with AJAX to actually remove from server
                event.target.parentElement.remove();
            }
        }
        
        // Form validation
        document.getElementById('tourForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const countryId = document.getElementById('country_id').value;
            const categoryId = document.getElementById('category_id').value;
            const priceAdult = document.getElementById('price_adult').value;
            
            if (!title || !countryId || !categoryId || !priceAdult) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Update CKEditor content
            for (let instance in CKEDITOR.instances) {
                CKEDITOR.instances[instance].updateElement();
            }
        });
        
        // Auto-save functionality (optional)
        let autoSaveTimer;
        function autoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                // Implement auto-save logic here
                console.log('Auto-saving...');
            }, 30000); // Auto-save every 30 seconds
        }
        
        // Trigger auto-save on form changes
        document.getElementById('tourForm').addEventListener('input', autoSave);
        document.getElementById('tourForm').addEventListener('change', autoSave);
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
