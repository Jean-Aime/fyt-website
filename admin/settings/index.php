<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('settings.view');

$page_title = 'General Settings';

// Handle form submission
if ($_POST && isset($_POST['save_settings'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $settings = [
                'site_name' => trim($_POST['site_name']),
                'site_description' => trim($_POST['site_description']),
                'site_url' => trim($_POST['site_url']),
                'admin_email' => trim($_POST['admin_email']),
                'contact_email' => trim($_POST['contact_email']),
                'contact_phone' => trim($_POST['contact_phone']),
                'contact_address' => trim($_POST['contact_address']),
                'default_currency' => $_POST['default_currency'],
                'default_language' => $_POST['default_language'],
                'timezone' => $_POST['timezone'],
                'date_format' => $_POST['date_format'],
                'time_format' => $_POST['time_format'],
                'items_per_page' => (int)$_POST['items_per_page'],
                'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
                'maintenance_message' => trim($_POST['maintenance_message']),
                'allow_registration' => isset($_POST['allow_registration']) ? 1 : 0,
                'email_verification' => isset($_POST['email_verification']) ? 1 : 0,
                'auto_approve_bookings' => isset($_POST['auto_approve_bookings']) ? 1 : 0,
                'booking_cancellation_hours' => (int)$_POST['booking_cancellation_hours'],
                'max_file_size' => (int)$_POST['max_file_size'],
                'allowed_file_types' => trim($_POST['allowed_file_types']),
                'google_analytics_id' => trim($_POST['google_analytics_id']),
                'facebook_pixel_id' => trim($_POST['facebook_pixel_id']),
                'social_facebook' => trim($_POST['social_facebook']),
                'social_twitter' => trim($_POST['social_twitter']),
                'social_instagram' => trim($_POST['social_instagram']),
                'social_youtube' => trim($_POST['social_youtube']),
                'smtp_enabled' => isset($_POST['smtp_enabled']) ? 1 : 0,
                'smtp_host' => trim($_POST['smtp_host']),
                'smtp_port' => (int)$_POST['smtp_port'],
                'smtp_username' => trim($_POST['smtp_username']),
                'smtp_password' => trim($_POST['smtp_password']),
                'smtp_encryption' => $_POST['smtp_encryption']
            ];
            
            // Update settings in database
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                    VALUES (?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            // Handle logo upload
            if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['site_logo'], UPLOADS_PATH . '/logos', ['jpg', 'jpeg', 'png', 'gif']);
                if ($upload_result['success']) {
                    $stmt = $db->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                        VALUES ('site_logo', ?, NOW()) 
                        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
                    ");
                    $stmt->execute(['uploads/logos/' . $upload_result['filename'], 'uploads/logos/' . $upload_result['filename']]);
                }
            }
            
            // Handle favicon upload
            if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['site_favicon'], UPLOADS_PATH . '/logos', ['ico', 'png']);
                if ($upload_result['success']) {
                    $stmt = $db->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                        VALUES ('site_favicon', ?, NOW()) 
                        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
                    ");
                    $stmt->execute(['uploads/logos/' . $upload_result['filename'], 'uploads/logos/' . $upload_result['filename']]);
                }
            }
            
            $auth->logActivity($_SESSION['user_id'], 'settings_updated', 'Updated general settings');
            $success = 'Settings updated successfully!';
            
        } catch (Exception $e) {
            $error = 'Error updating settings: ' . $e->getMessage();
        }
    }
}

// Get current settings
$current_settings = [];
$stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    'site_name' => 'Forever Young Tours',
    'site_description' => 'Travel Bold. Stay Forever Young.',
    'site_url' => 'https://foreveryoungtours.com',
    'admin_email' => 'admin@foreveryoungtours.com',
    'contact_email' => 'info@foreveryoungtours.com',
    'contact_phone' => '+1 (555) 123-4567',
    'contact_address' => '123 Travel Street, Adventure City, AC 12345',
    'default_currency' => 'USD',
    'default_language' => 'en',
    'timezone' => 'America/New_York',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i',
    'items_per_page' => 20,
    'maintenance_mode' => 0,
    'maintenance_message' => 'We are currently performing maintenance. Please check back soon.',
    'allow_registration' => 1,
    'email_verification' => 1,
    'auto_approve_bookings' => 0,
    'booking_cancellation_hours' => 24,
    'max_file_size' => 10,
    'allowed_file_types' => 'jpg,jpeg,png,gif,pdf,doc,docx',
    'smtp_enabled' => 0,
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls'
];

// Merge with current settings
foreach ($defaults as $key => $default_value) {
    if (!isset($current_settings[$key])) {
        $current_settings[$key] = $default_value;
    }
}

// Available options
$currencies = [
    'USD' => 'US Dollar ($)',
    'EUR' => 'Euro (€)',
    'GBP' => 'British Pound (£)',
    'CAD' => 'Canadian Dollar (C$)',
    'AUD' => 'Australian Dollar (A$)',
    'RWF' => 'Rwandan Franc (₣)'
];

$languages = [
    'en' => 'English',
    'fr' => 'French',
    'es' => 'Spanish',
    'de' => 'German',
    'rw' => 'Kinyarwanda'
];

$timezones = [
    'America/New_York' => 'Eastern Time (US)',
    'America/Chicago' => 'Central Time (US)',
    'America/Denver' => 'Mountain Time (US)',
    'America/Los_Angeles' => 'Pacific Time (US)',
    'Europe/London' => 'London',
    'Europe/Paris' => 'Paris',
    'Africa/Kigali' => 'Kigali',
    'Asia/Tokyo' => 'Tokyo',
    'Australia/Sydney' => 'Sydney'
];
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
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .settings-nav {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .nav-tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            margin: 0;
        }
        
        .nav-tab {
            flex: 1;
            padding: 15px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .nav-tab:hover {
            background: #f8f9fa;
            color: var(--admin-primary);
        }
        
        .nav-tab.active {
            color: var(--admin-primary);
            border-bottom-color: var(--admin-primary);
            background: #f8f9fa;
        }
        
        .settings-form {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .form-section {
            display: none;
            padding: 30px;
        }
        
        .form-section.active {
            display: block;
        }
        
        .section-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: var(--admin-primary);
            background: rgba(212, 165, 116, 0.05);
        }
        
        .file-upload-area.dragover {
            border-color: var(--admin-primary);
            background: rgba(212, 165, 116, 0.1);
        }
        
        .upload-icon {
            font-size: 3em;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .upload-text {
            color: #666;
            margin-bottom: 10px;
        }
        
        .current-file {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 0.9em;
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
        
        .form-actions {
            background: #f8f9fa;
            padding: 20px 30px;
            border-top: 1px solid #dee2e6;
            text-align: right;
        }
        
        .preview-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .preview-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .social-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .social-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2em;
        }
        
        .social-icon.facebook { background: #1877f2; }
        .social-icon.twitter { background: #1da1f2; }
        .social-icon.instagram { background: #e4405f; }
        .social-icon.youtube { background: #ff0000; }
        
        @media (max-width: 768px) {
            .nav-tabs {
                flex-direction: column;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
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
                <!-- Settings Header -->
                <div class="settings-header">
                    <h1>General Settings</h1>
                    <p>Configure your website settings and preferences</p>
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
                
                <!-- Settings Navigation -->
                <div class="settings-nav">
                    <div class="nav-tabs">
                        <button class="nav-tab active" onclick="showSection('general')">
                            <i class="fas fa-cog"></i> General
                        </button>
                        <button class="nav-tab" onclick="showSection('contact')">
                            <i class="fas fa-address-book"></i> Contact
                        </button>
                        <button class="nav-tab" onclick="showSection('localization')">
                            <i class="fas fa-globe"></i> Localization
                        </button>
                        <button class="nav-tab" onclick="showSection('booking')">
                            <i class="fas fa-calendar-check"></i> Booking
                        </button>
                        <button class="nav-tab" onclick="showSection('media')">
                            <i class="fas fa-images"></i> Media
                        </button>
                        <button class="nav-tab" onclick="showSection('social')">
                            <i class="fas fa-share-alt"></i> Social
                        </button>
                        <button class="nav-tab" onclick="showSection('email')">
                            <i class="fas fa-envelope"></i> Email
                        </button>
                    </div>
                </div>
                
                <!-- Settings Form -->
                <form method="POST" enctype="multipart/form-data" class="settings-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- General Settings -->
                    <div class="form-section active" id="general">
                        <h2 class="section-title">General Settings</h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="site_name">Site Name</label>
                                <input type="text" id="site_name" name="site_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['site_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_url">Site URL</label>
                                <input type="url" id="site_url" name="site_url" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['site_url']); ?>" required>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="site_description">Site Description</label>
                                <textarea id="site_description" name="site_description" class="form-control" rows="3"
                                          placeholder="Brief description of your website"><?php echo htmlspecialchars($current_settings['site_description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="admin_email">Admin Email</label>
                                <input type="email" id="admin_email" name="admin_email" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['admin_email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="items_per_page">Items Per Page</label>
                                <input type="number" id="items_per_page" name="items_per_page" class="form-control" 
                                       value="<?php echo $current_settings['items_per_page']; ?>" min="5" max="100">
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <span style="display: flex; align-items: center; gap: 15px;">
                                        Maintenance Mode
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="maintenance_mode" 
                                                   <?php echo $current_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </span>
                                </label>
                                <small class="form-text">Enable to put the site in maintenance mode</small>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <span style="display: flex; align-items: center; gap: 15px;">
                                        Allow Registration
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="allow_registration" 
                                                   <?php echo $current_settings['allow_registration'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </span>
                                </label>
                                <small class="form-text">Allow new users to register</small>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="maintenance_message">Maintenance Message</label>
                            <textarea id="maintenance_message" name="maintenance_message" class="form-control" rows="3"
                                      placeholder="Message to display when site is in maintenance mode"><?php echo htmlspecialchars($current_settings['maintenance_message']); ?></textarea>
                        </div>
                        
                        <!-- Logo Upload -->
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Site Logo</label>
                                <div class="file-upload-area" onclick="document.getElementById('site_logo').click()">
                                    <div class="upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="upload-text">Click to upload logo</div>
                                    <small>Recommended: 200x60px, PNG or JPG</small>
                                    <?php if (isset($current_settings['site_logo'])): ?>
                                        <div class="current-file">
                                            Current: <?php echo basename($current_settings['site_logo']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" id="site_logo" name="site_logo" accept="image/*" style="display: none;">
                            </div>
                            
                            <div class="form-group">
                                <label>Favicon</label>
                                <div class="file-upload-area" onclick="document.getElementById('site_favicon').click()">
                                    <div class="upload-icon">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <div class="upload-text">Click to upload favicon</div>
                                    <small>Recommended: 32x32px, ICO or PNG</small>
                                    <?php if (isset($current_settings['site_favicon'])): ?>
                                        <div class="current-file">
                                            Current: <?php echo basename($current_settings['site_favicon']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" id="site_favicon" name="site_favicon" accept=".ico,.png" style="display: none;">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Settings -->
                    <div class="form-section" id="contact">
                        <h2 class="section-title">Contact Information</h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="contact_email">Contact Email</label>
                                <input type="email" id="contact_email" name="contact_email" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['contact_email']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_phone">Contact Phone</label>
                                <input type="tel" id="contact_phone" name="contact_phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['contact_phone']); ?>">
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="contact_address">Contact Address</label>
                                <textarea id="contact_address" name="contact_address" class="form-control" rows="3"><?php echo htmlspecialchars($current_settings['contact_address']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Localization Settings -->
                    <div class="form-section" id="localization">
                        <h2 class="section-title">Localization Settings</h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="default_currency">Default Currency</label>
                                <select id="default_currency" name="default_currency" class="form-control">
                                    <?php foreach ($currencies as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" 
                                                <?php echo $current_settings['default_currency'] === $code ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="default_language">Default Language</label>
                                <select id="default_language" name="default_language" class="form-control">
                                    <?php foreach ($languages as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" 
                                                <?php echo $current_settings['default_language'] === $code ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone" name="timezone" class="form-control">
                                    <?php foreach ($timezones as $tz => $name): ?>
                                        <option value="<?php echo $tz; ?>" 
                                                <?php echo $current_settings['timezone'] === $tz ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_format">Date Format</label>
                                <select id="date_format" name="date_format" class="form-control">
                                    <option value="Y-m-d" <?php echo $current_settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>2024-01-15</option>
                                    <option value="m/d/Y" <?php echo $current_settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>01/15/2024</option>
                                    <option value="d/m/Y" <?php echo $current_settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>15/01/2024</option>
                                    <option value="F j, Y" <?php echo $current_settings['date_format'] === 'F j, Y' ? 'selected' : ''; ?>>January 15, 2024</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="time_format">Time Format</label>
                                <select id="time_format" name="time_format" class="form-control">
                                    <option value="H:i" <?php echo $current_settings['time_format'] === 'H:i' ? 'selected' : ''; ?>>24-hour (14:30)</option>
                                    <option value="g:i A" <?php echo $current_settings['time_format'] === 'g:i A' ? 'selected' : ''; ?>>12-hour (2:30 PM)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Booking Settings -->
                    <div class="form-section" id="booking">
                        <h2 class="section-title">Booking Settings</h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <span style="display: flex; align-items: center; gap: 15px;">
                                        Auto-approve Bookings
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="auto_approve_bookings" 
                                                   <?php echo $current_settings['auto_approve_bookings'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </span>
                                </label>
                                <small class="form-text">Automatically approve new bookings</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="booking_cancellation_hours">Cancellation Period (Hours)</label>
                                <input type="number" id="booking_cancellation_hours" name="booking_cancellation_hours" 
                                       class="form-control" value="<?php echo $current_settings['booking_cancellation_hours']; ?>" min="0">
                                <small class="form-text">Hours before tour start when cancellation is allowed</small>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <span style="display: flex; align-items: center; gap: 15px;">
                                        Email Verification Required
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="email_verification" 
                                                   <?php echo $current_settings['email_verification'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </span>
                                </label>
                                <small class="form-text">Require email verification for new accounts</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Media Settings -->
                    <div class="form-section" id="media">
                        <h2 class="section-title">Media Settings</h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="max_file_size">Max File Size (MB)</label>
                                <input type="number" id="max_file_size" name="max_file_size" class="form-control" 
                                       value="<?php echo $current_settings['max_file_size']; ?>" min="1" max="100">
                            </div>
                            
                            <div class="form-group">
                                <label for="allowed_file_types">Allowed File Types</label>
                                <input type="text" id="allowed_file_types" name="allowed_file_types" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['allowed_file_types']); ?>"
                                       placeholder="jpg,jpeg,png,gif,pdf,doc,docx">
                                <small class="form-text">Comma-separated list of allowed file extensions</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="google_analytics_id">Google Analytics ID</label>
                                <input type="text" id="google_analytics_id" name="google_analytics_id" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['google_analytics_id'] ?? ''); ?>"
                                       placeholder="G-XXXXXXXXXX">
                            </div>
                            
                            <div class="form-group">
                                <label for="facebook_pixel_id">Facebook Pixel ID</label>
                                <input type="text" id="facebook_pixel_id" name="facebook_pixel_id" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['facebook_pixel_id'] ?? ''); ?>"
                                       placeholder="123456789012345">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Social Media Settings -->
                    <div class="form-section" id="social">
                        <h2 class="section-title">Social Media Links</h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="social_facebook">Facebook</label>
                                <div class="social-input-group">
                                    <div class="social-icon facebook">
                                        <i class="fab fa-facebook-f"></i>
                                    </div>
                                    <input type="url" id="social_facebook" name="social_facebook" class="form-control" 
                                           value="<?php echo htmlspecialchars($current_settings['social_facebook'] ?? ''); ?>"
                                           placeholder="https://facebook.com/yourpage">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="social_twitter">Twitter</label>
                                <div class="social-input-group">
                                    <div class="social-icon twitter">
                                        <i class="fab fa-twitter"></i>
                                    </div>
                                    <input type="url" id="social_twitter" name="social_twitter" class="form-control" 
                                           value="<?php echo htmlspecialchars($current_settings['social_twitter'] ?? ''); ?>"
                                           placeholder="https://twitter.com/yourhandle">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="social_instagram">Instagram</label>
                                <div class="social-input-group">
                                    <div class="social-icon instagram">
                                        <i class="fab fa-instagram"></i>
                                    </div>
                                    <input type="url" id="social_instagram" name="social_instagram" class="form-control" 
                                           value="<?php echo htmlspecialchars($current_settings['social_instagram'] ?? ''); ?>"
                                           placeholder="https://instagram.com/yourhandle">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="social_youtube">YouTube</label>
                                <div class="social-input-group">
                                    <div class="social-icon youtube">
                                        <i class="fab fa-youtube"></i>
                                    </div>
                                    <input type="url" id="social_youtube" name="social_youtube" class="form-control" 
                                           value="<?php echo htmlspecialchars($current_settings['social_youtube'] ?? ''); ?>"
                                           placeholder="https://youtube.com/yourchannel">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email Settings -->
                    <div class="form-section" id="email">
                        <h2 class="section-title">Email Configuration</h2>
                        
                        <div class="form-group">
                            <label>
                                <span style="display: flex; align-items: center; gap: 15px;">
                                    Enable SMTP
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="smtp_enabled" 
                                               <?php echo $current_settings['smtp_enabled'] ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </span>
                            </label>
                            <small class="form-text">Use SMTP for sending emails (recommended)</small>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="smtp_host">SMTP Host</label>
                                <input type="text" id="smtp_host" name="smtp_host" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['smtp_host']); ?>"
                                       placeholder="smtp.gmail.com">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_port">SMTP Port</label>
                                <input type="number" id="smtp_port" name="smtp_port" class="form-control" 
                                       value="<?php echo $current_settings['smtp_port']; ?>" placeholder="587">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_username">SMTP Username</label>
                                <input type="text" id="smtp_username" name="smtp_username" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['smtp_username']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_password">SMTP Password</label>
                                <input type="password" id="smtp_password" name="smtp_password" class="form-control" 
                                       value="<?php echo htmlspecialchars($current_settings['smtp_password']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_encryption">Encryption</label>
                                <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                                    <option value="tls" <?php echo $current_settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo $current_settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="none" <?php echo $current_settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="preview-section">
                            <div class="preview-title">Test Email Configuration</div>
                            <button type="button" class="btn btn-outline-primary" onclick="testEmailConfig()">
                                <i class="fas fa-envelope"></i> Send Test Email
                            </button>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <button type="submit" name="save_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionId).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                location.reload();
            }
        }
        
        function testEmailConfig() {
            const button = event.target;
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            button.disabled = true;
            
            fetch('../api/test-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo generateCSRFToken(); ?>'
                },
                body: JSON.stringify({
                    smtp_host: document.getElementById('smtp_host').value,
                    smtp_port: document.getElementById('smtp_port').value,
                    smtp_username: document.getElementById('smtp_username').value,
                    smtp_password: document.getElementById('smtp_password').value,
                    smtp_encryption: document.getElementById('smtp_encryption').value,
                    test_email: '<?php echo $_SESSION['user_email']; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Test email sent successfully!');
                } else {
                    alert('Failed to send test email: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error testing email configuration: ' + error.message);
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        // File upload preview
        document.getElementById('site_logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // You can add preview functionality here
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Auto-save draft functionality
        let saveTimeout;
        document.querySelectorAll('input, textarea, select').forEach(element => {
            element.addEventListener('change', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    // Auto-save draft functionality can be implemented here
                }, 2000);
            });
        });
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
