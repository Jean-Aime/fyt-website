<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('users.edit');

$page_title = 'Edit User';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$user_id) {
    header('Location: index.php');
    exit;
}

// Fetch user details
$stmt = $db->prepare("
    SELECT u.*, r.id AS role_id
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'])) {
    $errors = [];

    // Validate inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $role_id = $_POST['role_id'] ?? null;

    if (empty($first_name))
        $errors[] = 'First name is required';
    if (empty($last_name))
        $errors[] = 'Last name is required';
    if (empty($email))
        $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email format';
    if (empty($username))
        $errors[] = 'Username is required';

    // Check if email is already taken by another user
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch())
        $errors[] = 'Email is already in use';

    // Check if username is already taken by another user
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $user_id]);
    if ($stmt->fetch())
        $errors[] = 'Username is already in use';

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Update user
            $stmt = $db->prepare("
                UPDATE users SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    username = ?, 
                    phone = ?, 
                    status = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $username,
                $phone,
                $status,
                $user_id
            ]);

            // Update role if changed
            if ($role_id) {
                // First remove existing role
                $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
                $stmt->execute([$user_id]);

                // Add new role
                $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $role_id]);
            }

            $db->commit();

            // Log activity
            $stmt = $db->prepare("
                INSERT INTO user_activity 
                (user_id, activity_type, description, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                'profile_update',
                "Updated user profile for {$first_name} {$last_name}",
                $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['success'] = 'User updated successfully';
            header("Location: view.php?id={$user_id}");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Error updating user: ' . $e->getMessage();
        }
    }
}

// Fetch all roles
$roles = $db->query("SELECT id, display_name FROM roles ORDER BY display_name")->fetchAll(PDO::FETCH_ASSOC);


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
                        <h2>Edit User</h2>
                        <p>Update user account information</p>
                    </div>
                    <div class="content-actions">
                        <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to View
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo implode('<br>', $errors); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="user-edit-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="form-section">
                        <h3>Basic Information</h3>
                        <div class="form-grid two-col">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" id="first_name" name="first_name" class="form-control"
                                    value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" id="last_name" name="last_name" class="form-control"
                                    value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="form-grid two-col">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" class="form-control"
                                    value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="username">Username <span class="required">*</span></label>
                                <input type="text" id="username" name="username" class="form-control"
                                    value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Account Settings</h3>
                        <div class="form-grid two-col">
                            <div class="form-group">
                                <label for="role_id">Role</label>
                                <select id="role_id" name="role_id" class="form-control">
                                    <option value="">No Role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" <?php echo ($user['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['display_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>
                                        Active</option>
                                    <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>
                                        Inactive</option>
                                    <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Password Reset</h3>
                        <div class="form-note">
                            <p>Leave these fields blank to keep the current password</p>
                        </div>
                        <div class="form-grid two-col">
                            <div class="form-group">
                                <label for="password">New Password</label>
                                <input type="password" id="password" name="password" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password"
                                    class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>

</html>
