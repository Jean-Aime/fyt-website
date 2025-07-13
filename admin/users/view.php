<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('users.view');

$page_title = 'View User';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$user_id) {
    header('Location: index.php');
    exit;
}

// Fetch user details
$stmt = $db->prepare("
    SELECT u.*, r.display_name AS role_display
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("
    SELECT p.name, p.display_name 
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    JOIN role_permissions rp ON r.id = rp.role_id
    JOIN permissions p ON rp.permission_id = p.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch activity logs
$stmt = $db->prepare("
    SELECT * FROM user_activity 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");

$stmt->execute([$user_id]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .user-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            border: 1px solid #ecf0f1;
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .user-header {
            background: linear-gradient(135deg, #D4AF37, #B8941F);
            padding: 25px;
            text-align: center;
            position: relative;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2em;
            font-weight: bold;
            color: white;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .user-name {
            color: white;
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .user-email {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9em;
        }

        .user-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .user-status.active {
            background: #228B22;
            color: white;
        }

        .user-status.inactive {
            background: #dc3545;
            color: white;
        }

        .user-status.suspended {
            background: #f39c12;
            color: white;
        }

        .user-content {
            padding: 25px;
        }

        .user-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .user-info-item {
            text-align: center;
        }

        .user-info-label {
            font-size: 0.8em;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .user-info-value {
            font-weight: 600;
            color: #2c3e50;
        }

        .user-role {
            background: linear-gradient(135deg, #000000, #333333);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
            text-align: center;
            margin-bottom: 20px;
        }

        .user-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }

        .user-actions .btn {
            flex: 1;
            padding: 10px;
            font-size: 0.9em;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .user-checkbox {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 20px;
            height: 20px;
            accent-color: #D4AF37;
        }

        .view-toggle {
            display: flex;
            gap: 5px;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 8px;
        }

        .view-toggle button {
            padding: 8px 12px;
            border: none;
            background: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-toggle button.active {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            color: #D4AF37;
        }

        .table-view {
            display: none;
        }

        .table-view.active {
            display: block;
        }

        .grid-view.active {
            display: block;
        }

        .grid-view {
            display: none;
        }

        .bulk-actions {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: none;
        }

        .bulk-actions.show {
            display: block;
        }

        .bulk-actions-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .selected-count {
            font-weight: 600;
            color: #333;
        }

        /* User Details Page */
        .user-details-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .user-profile-section {
            padding: 30px;
        }

        .user-profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3em;
            font-weight: bold;
            color: #555;
            border: 5px solid #D4AF37;
        }

        .user-name {
            font-size: 1.8em;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .user-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .user-status.active {
            background: #228B22;
            color: white;
        }

        .user-status.inactive {
            background: #dc3545;
            color: white;
        }

        .user-status.suspended {
            background: #f39c12;
            color: white;
        }

        .user-role {
            background: linear-gradient(135deg, #000000, #333333);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
            display: inline-block;
            margin-top: 10px;
        }

        .user-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .detail-card {
            background: #f9f9f9;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #D4AF37;
        }

        .detail-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .detail-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #2c3e50;
        }

        .permissions-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .permission-tag {
            background: #e8f4fd;
            color: #2c7be5;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
        }

        .user-activity-section {
            padding: 30px;
            border-top: 1px solid #eee;
        }

        .activity-timeline {
            margin-top: 20px;
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
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #D4AF37;
            flex-shrink: 0;
        }

        .activity-content {
            flex-grow: 1;
        }

        .activity-description {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .activity-meta {
            display: flex;
            gap: 15px;
            font-size: 0.8em;
            color: #7f8c8d;
        }

        /* Edit Form */
        .user-edit-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
        }

        .form-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .form-section h3 {
            margin-top: 0;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-note {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #555;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
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
                        <h2>User Details</h2>
                        <p>View and manage user account information</p>
                    </div>
                    <div class="content-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                        <?php if (hasPermission('users.edit')): ?>
                            <a href="edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit User
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="user-details-container">
                    <div class="user-profile-section">
                        <div class="user-profile-header">
                            <div class="user-avatar">
                                <?php if ($user['profile_image']): ?>
                                    <img src="../../<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile"
                                        style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="user-name">
                                <?php echo htmlspecialchars($user['first_name']) . ' ' . htmlspecialchars($user['last_name']); ?>
                                <span class="user-status <?php echo $user['status']; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                            <div class="user-role">
                                <?php echo htmlspecialchars($user['role_display'] ?? 'No Role'); ?>
                            </div>
                        </div>

                        <div class="user-details-grid">
                            <div class="detail-card">
                                <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                                <div class="detail-item">
                                    <span class="detail-label">Email:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Username:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($user['username']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone:</span>
                                    <span
                                        class="detail-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></span>
                                </div>
                            </div>

                            <div class="detail-card">
                                <h3><i class="fas fa-calendar-alt"></i> Account Information</h3>
                                <div class="detail-item">
                                    <span class="detail-label">Created:</span>
                                    <span
                                        class="detail-value"><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Last Login:</span>
                                    <span class="detail-value">
                                        <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Last Updated:</span>
                                    <span class="detail-value">
                                        <?php echo $user['updated_at'] ? date('M j, Y g:i A', strtotime($user['updated_at'])) : 'Never'; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="detail-card">
                                <h3><i class="fas fa-shield-alt"></i> Permissions</h3>
                                <?php if ($permissions): ?>
                                    <div class="permissions-list">
                                        <?php foreach ($permissions as $perm): ?>
                                            <span
                                                class="permission-tag"><?php echo htmlspecialchars($perm['display_name']); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p>No special permissions assigned</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="user-activity-section">
                        <h3><i class="fas fa-history"></i> Recent Activity</h3>
                        <?php if ($activities): ?>
                            <div class="activity-timeline">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-<?php echo getActivityIcon($activity['activity_type']); ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-description">
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            </div>
                                            <div class="activity-meta">
                                                <span class="activity-time">
                                                    <?php echo isset($activity['created_at']) ? date('M j, Y g:i A', strtotime($activity['created_at'])) : 'N/A'; ?>

                                                </span>
                                                <span class="activity-ip">
                                                    <?php echo htmlspecialchars($activity['ip_address']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No recent activity found</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    function getActivityIcon($type)
    {
        $icons = [
            'login' => 'sign-in-alt',
            'logout' => 'sign-out-alt',
            'profile_update' => 'user-edit',
            'password_change' => 'key',
            'settings_update' => 'cog'
        ];

        return $icons[$type] ?? 'info-circle';
    }

    ?>
</body>

</html>
