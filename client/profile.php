<?php
// Show any PHP errors clearly (remove on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Ensure user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../login.php');
    exit;
}

// Load config and DB connection
require_once __DIR__ . '/../config/config.php';

// Get user ID
$user_id = $_SESSION['user_id'];

// Get user details
$stmt = $db->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) as booking_count,
           (SELECT COUNT(*) FROM support_tickets WHERE user_id = u.id) as ticket_count
    FROM users u
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');


    // Validate inputs
    $errors = [];

    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }

    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email is already taken by another user
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = "Email address is already in use by another account.";
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Update user profile
            $stmt = $db->prepare("
                UPDATE users 
                SET first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone = ?, 
                    address = ?, 
                    city = ?, 
                    country = ?, 
                    postal_code = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $phone,
                $address,
                $city,
                $country,
                $postal_code,
                $user_id
            ]);

            // Update session variables
            $_SESSION['user_name'] = "$first_name $last_name";
            $_SESSION['user_email'] = $email;

            $db->commit();

            $_SESSION['success_message'] = "Profile updated successfully!";
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    $errors = [];

    if (empty($current_password)) {
        $errors[] = "Current password is required.";
    }

    if (empty($new_password)) {
        $errors[] = "New password is required.";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long.";
    }

    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }

    if (empty($errors)) {
        try {
            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();

            if ($user_data && password_verify($current_password, $user_data['password'])) {
                $db->beginTransaction();

                // Update password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$new_password_hash, $user_id]);

                $db->commit();

                $_SESSION['success_message'] = "Password changed successfully!";
                header('Location: profile.php');
                exit;
            } else {
                $errors[] = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Error changing password: " . $e->getMessage();
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo']) && isset($_FILES['profile_image'])) {
    $upload_dir = '../uploads/profile_images/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB

    $file = $_FILES['profile_image'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload error: " . $file['error'];
    } elseif (!in_array($file['type'], $allowed_types)) {
        $error = "Only JPG, PNG, and GIF files are allowed.";
    } elseif ($file['size'] > $max_size) {
        $error = "File size must be less than 2MB.";
    } else {
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = "user_{$user_id}_" . time() . ".$ext";
        $destination = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            try {
                $db->beginTransaction();

                // Delete old profile image if exists
                if (!empty($user['profile_image']) && file_exists("../" . $user['profile_image'])) {
                    unlink("../" . $user['profile_image']);
                }

                // Update user record
                $profile_image_path = "uploads/profile_images/$filename";
                $stmt = $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $stmt->execute([$profile_image_path, $user_id]);

                // Update session variable
                $_SESSION['profile_image'] = $profile_image_path;

                $db->commit();

                $_SESSION['success_message'] = "Profile picture updated successfully!";
                header('Location: profile.php');
                exit;
            } catch (PDOException $e) {
                $db->rollBack();
                unlink($destination); // Clean up uploaded file
                $error = "Error updating profile picture: " . $e->getMessage();
            }
        } else {
            $error = "Failed to move uploaded file.";
        }
    }
}

// Handle profile picture removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_photo'])) {
    if (!empty($user['profile_image'])) {
        try {
            $db->beginTransaction();

            // Delete the file
            if (file_exists("../" . $user['profile_image'])) {
                unlink("../" . $user['profile_image']);
            }

            // Update user record
            $stmt = $db->prepare("UPDATE users SET profile_image = NULL WHERE id = ?");
            $stmt->execute([$user_id]);

            // Update session variable
            unset($_SESSION['profile_image']);

            $db->commit();

            $_SESSION['success_message'] = "Profile picture removed successfully!";
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Error removing profile picture: " . $e->getMessage();
        }
    }
}

// Set page title
$page_title = 'Profile Settings - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/client-portal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

</head>

<body>
    <div class="client-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <div class="page-header">
                    <h1>Profile Settings</h1>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="error-list">
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo $err; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="profile-container">
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="../<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                            <div class="profile-avatar-actions">
                                <button class="avatar-btn"
                                    onclick="document.getElementById('profile-image-input').click()">
                                    <i class="fas fa-camera"></i> Change
                                </button>
                                <?php if (!empty($user['profile_image'])): ?>
                                    <button class="avatar-btn"
                                        onclick="document.getElementById('remove-photo-form').submit()">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="profile-info">
                            <h2 class="profile-name">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </h2>
                            <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>

                            <div class="profile-stats">
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo $user['booking_count']; ?></span>
                                    <span class="stat-label">Bookings</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo $user['ticket_count']; ?></span>
                                    <span class="stat-label">Tickets</span>
                                </div>
                                <div class="stat-item">
                                    <span
                                        class="stat-value"><?php echo date('Y') - date('Y', strtotime($user['created_at'])); ?>+</span>
                                    <span class="stat-label">Years</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden forms for profile picture actions -->
                    <form id="upload-photo-form" class="photo-upload-form" method="POST" enctype="multipart/form-data">
                        <input type="file" id="profile-image-input" name="profile_image" accept="image/*"
                            onchange="this.form.submit()">
                        <input type="hidden" name="upload_photo">
                    </form>

                    <?php if (!empty($user['profile_image'])): ?>
                        <form id="remove-photo-form" method="POST">
                            <input type="hidden" name="remove_photo">
                        </form>
                    <?php endif; ?>

                    <!-- Profile Content -->
                    <div class="profile-content">
                        <!-- Personal Information -->
                        <div class="profile-section">
                            <h3 class="section-title">Personal Information</h3>

                            <form method="POST">
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
                                        value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>


                            </form>
                        </div>

                        <!-- Address Information -->
                        <div class="profile-section">
                            <h3 class="section-title">Address Information</h3>

                            <form method="POST">
                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <input type="text" id="address" name="address" class="form-control"
                                        value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">

                                </div>

                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" class="form-control"
                                        value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="country">Country</label>
                                    <input type="text" id="country" name="country" class="form-control"
                                        value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">

                                </div>

                                <div class="form-group">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code" class="form-control"
                                        value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Change Password -->
                        <div class="profile-section">
                            <h3 class="section-title">Change Password</h3>
                            <div class="security-alert">
                                <i class="fas fa-shield-alt"></i> For security reasons, please keep your password
                                confidential and change it regularly.
                            </div>

                            <form method="POST">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password"
                                        class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control"
                                        required>
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password"
                                        class="form-control" required>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Account Security -->
                        <div class="profile-section">
                            <h3 class="section-title">Account Security</h3>

                            <div class="security-info">
                                <div class="info-item">
                                    <strong>Last Login:</strong>
                                    <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                </div>

                                <div class="info-item">
                                    <strong>Account Created:</strong>
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </div>

                                <div class="info-item">
                                    <strong>Last Updated:</strong>
                                    <?php echo $user['updated_at'] ? date('M j, Y', strtotime($user['updated_at'])) : 'Never'; ?>
                                </div>
                            </div>

                            <div class="form-actions" style="margin-top: 20px;">
                                <a href="security.php" class="btn btn-outline">
                                    <i class="fas fa-shield-alt"></i> Advanced Security Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/client-portal.js"></script>
    <script>
        // Update page title in header
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('pageTitle').textContent = 'Profile Settings';
        });
    </script>
</body>

</html>