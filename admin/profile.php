<?php
require_once '../config/config.php';
require_once 'includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();

$page_title = 'My Profile';
$current_user = getCurrentUser();

// Get user details
$stmt = $db->prepare("
    SELECT u.*, r.name as role_name, r.display_name as role_display 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.id = ?
");
$stmt->execute([$current_user['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_POST) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $action = $_POST['action'];
            
            if ($action === 'update_profile') {
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $bio = trim($_POST['bio']);
                
                // Check if email is already taken by another user
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $current_user['id']]);
                if ($stmt->fetch()) {
                    throw new Exception('Email address is already in use by another user.');
                }
                
                $stmt = $db->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, bio = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $bio, $current_user['id']]);
                
                // Update session data
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['email'] = $email;
                
                $auth->logActivity($current_user['id'], 'profile_updated', 'User updated profile information');
                $success = 'Profile updated successfully!';
                
                // Refresh user data
                $stmt = $db->prepare("
                    SELECT u.*, r.name as role_name, r.display_name as role_display 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE u.id = ?
                ");
                $stmt->execute([$current_user['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } elseif ($action === 'change_password') {
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Verify current password
                if (!password_verify($current_password, $user['password_hash'])) {
                    throw new Exception('Current password is incorrect.');
                }
                
                // Validate new password
                if (strlen($new_password) < 8) {
                    throw new Exception('New password must be at least 8 characters long.');
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception('New password and confirmation do not match.');
                }
                
                // Update password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$password_hash, $current_user['id']]);
                
                $auth->logActivity($current_user['id'], 'password_changed', 'User changed password');
                $success = 'Password changed successfully!';
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Invalid security token. Please try again.';
    }
}

// Get user activity logs
$stmt = $db->prepare("
    SELECT * FROM user_activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$current_user['id']]);
$activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-primary-dark) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            font-weight: bold;
            position: relative;
            flex-shrink: 0;
        }
        
        .avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 35px;
            height: 35px;
            background: white;
            border: 2px solid var(--admin-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--admin-primary);
            transition: all 0.3s ease;
        }
        
        .avatar-upload:hover {
            background: var(--admin-primary);
            color: white;
        }
        
        .profile-info h1 {
            font-size: 2em;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.9em;
        }
        
        .meta-item i {
            color: var(--admin-primary);
            width: 16px;
        }
        
        .profile-stats {
            display: flex;
            gap: 30px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--admin-primary);
        }
        
        .stat-label {
            font-size: 0.8em;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .profile-tabs {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .tab-nav {
            display: flex;
            border-bottom: 1px solid #eee;
        }
        
        .tab-btn {
            flex: 1;
            padding: 20px;
            background: none;
            border: none;
            font-size: 1em;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .tab-btn.active {
            color: var(--admin-primary);
            background: #f8f9fa;
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--admin-primary);
        }
        
        .tab-content {
            padding: 30px;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .activity-log {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--admin-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--admin-primary);
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-action {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .activity-description {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .activity-time {
            color: #999;
            font-size: 0.8em;
        }
        
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-bar {
            height: 4px;
            background: #eee;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-text {
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .strength-weak .strength-fill { width: 25%; background: #dc3545; }
        .strength-weak .strength-text { color: #dc3545; }
        
        .strength-fair .strength-fill { width: 50%; background: #ffc107; }
        .strength-fair .strength-text { color: #ffc107; }
        
        .strength-good .strength-fill { width: 75%; background: #17a2b8; }
        .strength-good .strength-text { color: #17a2b8; }
        
        .strength-strong .strength-fill { width: 100%; background: #28a745; }
        .strength-strong .strength-text { color: #28a745; }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .tab-nav {
                flex-direction: column;
            }
            
            .profile-stats {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content">
                <div class="profile-container">
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
                    
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            <div class="avatar-upload" title="Change Avatar">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        
                        <div class="profile-info">
                            <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                            
                            <div class="profile-meta">
                                <div class="meta-item">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-user-tag"></i>
                                    <?php echo htmlspecialchars($user['role_display']); ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    Joined <?php echo date('M Y', strtotime($user['created_at'])); ?>
                                </div>
                                <?php if ($user['last_login']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    Last login <?php echo timeAgo($user['last_login']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <div class="stat-value">
                                        <?php 
                                        $stmt = $db->prepare("SELECT COUNT(*) FROM user_activity_logs WHERE user_id = ?");
                                        $stmt->execute([$user['id']]);
                                        echo number_format($stmt->fetchColumn());
                                        ?>
                                    </div>
                                    <div class="stat-label">Activities</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">
                                        <?php echo $user['login_attempts']; ?>
                                    </div>
                                    <div class="stat-label">Login Attempts</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">
                                        <?php echo ucfirst($user['status']); ?>
                                    </div>
                                    <div class="stat-label">Status</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Tabs -->
                    <div class="profile-tabs">
                        <div class="tab-nav">
                            <button class="tab-btn active" onclick="switchTab('profile')">
                                <i class="fas fa-user"></i> Profile Information
                            </button>
                            <button class="tab-btn" onclick="switchTab('security')">
                                <i class="fas fa-shield-alt"></i> Security
                            </button>
                            <button class="tab-btn" onclick="switchTab('activity')">
                                <i class="fas fa-history"></i> Activity Log
                            </button>
                        </div>
                        
                        <div class="tab-content">
                            <!-- Profile Information Tab -->
                            <div class="tab-pane active" id="profile-tab">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="first_name">First Name</label>
                                            <input type="text" id="first_name" name="first_name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="last_name">Last Name</label>
                                            <input type="text" id="last_name" name="last_name" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="email">Email Address</label>
                                            <input type="email" id="email" name="email" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="phone">Phone Number</label>
                                            <input type="tel" id="phone" name="phone" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="form-group full-width">
                                            <label for="bio">Bio</label>
                                            <textarea id="bio" name="bio" class="form-control" rows="4" 
                                                      placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div style="text-align: right; margin-top: 30px;">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Security Tab -->
                            <div class="tab-pane" id="security-tab">
                                <form method="POST" id="passwordForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="form-grid">
                                        <div class="form-group full-width">
                                            <label for="current_password">Current Password</label>
                                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="new_password">New Password</label>
                                            <input type="password" id="new_password" name="new_password" class="form-control" 
                                                   required minlength="8" onkeyup="checkPasswordStrength()">
                                            <div class="password-strength" id="passwordStrength" style="display: none;">
                                                <div class="strength-bar">
                                                    <div class="strength-fill"></div>
                                                </div>
                                                <div class="strength-text"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="confirm_password">Confirm New Password</label>
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                                   required minlength="8">
                                        </div>
                                    </div>
                                    
                                    <div style="text-align: right; margin-top: 30px;">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-key"></i> Change Password
                                        </button>
                                    </div>
                                </form>
                                
                                <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #eee;">
                                    <h4>Security Information</h4>
                                    <div class="form-grid" style="margin-top: 20px;">
                                        <div class="meta-item">
                                            <i class="fas fa-calendar"></i>
                                            <strong>Account Created:</strong> <?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <strong>Last Updated:</strong> <?php echo $user['updated_at'] ? date('M j, Y g:i A', strtotime($user['updated_at'])) : 'Never'; ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-sign-in-alt"></i>
                                            <strong>Last Login:</strong> <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <strong>Failed Login Attempts:</strong> <?php echo $user['login_attempts']; ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-lock"></i>
                                            <strong>Account Status:</strong> 
                                            <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-shield-alt"></i>
                                            <strong>Two-Factor Auth:</strong> 
                                            <span class="badge badge-warning">Not Enabled</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Activity Log Tab -->
                            <div class="tab-pane" id="activity-tab">
                                <div class="activity-log">
                                    <?php if (empty($activity_logs)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-history"></i>
                                            <h3>No Activity Found</h3>
                                            <p>Your activity log is empty.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($activity_logs as $log): ?>
                                            <div class="activity-item">
                                                <div class="activity-icon">
                                                    <?php
                                                    $icon = 'info-circle';
                                                    switch ($log['action']) {
                                                        case 'login': $icon = 'sign-in-alt'; break;
                                                        case 'logout': $icon = 'sign-out-alt'; break;
                                                        case 'password_changed': $icon = 'key'; break;
                                                        case 'profile_updated': $icon = 'user-edit'; break;
                                                        case 'failed_login': $icon = 'exclamation-triangle'; break;
                                                        default: $icon = 'info-circle';
                                                    }
                                                    ?>
                                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                                </div>
                                                <div class="activity-content">
                                                    <div class="activity-action"><?php echo ucwords(str_replace('_', ' ', $log['action'])); ?></div>
                                                    <?php if ($log['description']): ?>
                                                        <div class="activity-description"><?php echo htmlspecialchars($log['description']); ?></div>
                                                    <?php endif; ?>
                                                    <div class="activity-time">
                                                        <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                                        <?php if ($log['ip_address']): ?>
                                                            • IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Remove active class from all tabs and buttons
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            // Add active class to clicked button and corresponding pane
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.style.display = 'none';
                return;
            }
            
            strengthDiv.style.display = 'block';
            
            let strength = 0;
            let strengthText = '';
            let strengthClass = '';
            
            // Length check
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Character variety checks
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                strengthClass = 'strength-weak';
                strengthText = 'Weak';
            } else if (strength <= 3) {
                strengthClass = 'strength-fair';
                strengthText = 'Fair';
            } else if (strength <= 4) {
                strengthClass = 'strength-good';
                strengthText = 'Good';
            } else {
                strengthClass = 'strength-strong';
                strengthText = 'Strong';
            }
            
            strengthDiv.className = 'password-strength ' + strengthClass;
            strengthDiv.querySelector('.strength-text').textContent = strengthText;
        }
        
        // Password confirmation validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation do not match.');
                return false;
            }
        });
        
        // Real-time password confirmation check
        document.getElementById('confirm_password').addEventListener('keyup', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    this.style.borderColor = '#28a745';
                } else {
                    this.style.borderColor = '#dc3545';
                }
            } else {
                this.style.borderColor = '#ddd';
            }
        });
    </script>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>
