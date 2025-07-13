<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('mcas.create');

$page_title = 'Add New MCA';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'])) {
    $errors = [];

    // Validate inputs
    $user_id = (int) $_POST['user_id'];
    $mca_code = trim($_POST['mca_code']);
    $license_number = trim($_POST['license_number']);
    $certification_date = $_POST['certification_date'];
    $certification_expiry = $_POST['certification_expiry'];
    $commission_rate = (float) $_POST['commission_rate'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);

    if (!$user_id) $errors[] = 'Please select a user';
    if (empty($mca_code)) $errors[] = 'MCA code is required';
    if (empty($certification_date)) $errors[] = 'Certification date is required';
    if (empty($certification_expiry)) $errors[] = 'Certification expiry date is required';
    if ($commission_rate < 0 || $commission_rate > 100) $errors[] = 'Commission rate must be between 0 and 100';

    // Check if MCA code already exists
    $stmt = $db->prepare("SELECT id FROM mcas WHERE mca_code = ?");
    $stmt->execute([$mca_code]);
    if ($stmt->fetch()) $errors[] = 'MCA code already exists';

    // Check if user is already an MCA
    $stmt = $db->prepare("SELECT id FROM mcas WHERE user_id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) $errors[] = 'User is already an MCA';

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Insert MCA record
            $stmt = $db->prepare("
                INSERT INTO mcas (user_id, mca_code, license_number, certification_date, certification_expiry, 
                                 commission_rate, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id, $mca_code, $license_number, $certification_date, 
                $certification_expiry, $commission_rate, $status, $notes
            ]);

            $mca_id = $db->lastInsertId();

            // Update user role to MCA
            $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'mca'");
            $stmt->execute();
            $mca_role = $stmt->fetch();

            if ($mca_role) {
                $stmt = $db->prepare("UPDATE users SET role_id = ? WHERE id = ?");
                $stmt->execute([$mca_role['id'], $user_id]);
            }

            $db->commit();

            // Log activity
            $stmt = $db->prepare("
                INSERT INTO user_activity_logs 
                (user_id, action, description, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                'mca_create',
                "Created new MCA: {$mca_code}",
                $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['success'] = 'MCA created successfully';
            header("Location: view.php?id={$mca_id}");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Error creating MCA: ' . $e->getMessage();
        }
    }
}

// Get available users (not already MCAs or advisors)
$available_users = $db->query("
    SELECT u.id, u.first_name, u.last_name, u.email, u.username 
    FROM users u 
    LEFT JOIN mcas m ON u.id = m.user_id 
    LEFT JOIN certified_advisors ca ON u.id = ca.user_id 
    WHERE m.id IS NULL AND ca.id IS NULL AND u.status = 'active'
    ORDER BY u.first_name, u.last_name
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
</head>
<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="content">
                <div class="content-header">
                    <div class="content-title">
                        <h2>Add New MCA</h2>
                        <p>Create a new Master Certified Advisor</p>
                    </div>
                    <div class="content-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to MCAs
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
                                <label for="user_id">Select User <span class="required">*</span></label>
                                <select id="user_id" name="user_id" class="form-control" required>
                                    <option value="">Choose a user...</option>
                                    <?php foreach ($available_users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="mca_code">MCA Code <span class="required">*</span></label>
                                <input type="text" id="mca_code" name="mca_code" class="form-control" 
                                       placeholder="e.g., MCA001" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="license_number">License Number</label>
                            <input type="text" id="license_number" name="license_number" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Certification Details</h3>
                        <div class="form-grid two-col">
                            <div class="form-group">
                                <label for="certification_date">Certification Date <span class="required">*</span></label>
                                <input type="date" id="certification_date" name="certification_date" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="certification_expiry">Certification Expiry <span class="required">*</span></label>
                                <input type="date" id="certification_expiry" name="certification_expiry" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-grid two-col">
                            <div class="form-group">
                                <label for="commission_rate">Commission Rate (%) <span class="required">*</span></label>
                                <input type="number" id="commission_rate" name="commission_rate" class="form-control" 
                                       min="0" max="100" step="0.01" value="10.00" required>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="pending">Pending</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Additional Information</h3>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" class="form-control" rows="4" 
                                      placeholder="Any additional notes about this MCA..."></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create MCA
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-generate MCA code based on user selection
        document.getElementById('user_id').addEventListener('change', function() {
            if (this.value && !document.getElementById('mca_code').value) {
                const selectedOption = this.options[this.selectedIndex];
                const userName = selectedOption.text.split('(')[0].trim();
                const initials = userName.split(' ').map(name => name.charAt(0)).join('');
                const timestamp = Date.now().toString().slice(-4);
                document.getElementById('mca_code').value = 'MCA' + initials.toUpperCase() + timestamp;
            }
        });
        
        // Set default expiry date (1 year from certification date)
        document.getElementById('certification_date').addEventListener('change', function() {
            if (this.value && !document.getElementById('certification_expiry').value) {
                const certDate = new Date(this.value);
                certDate.setFullYear(certDate.getFullYear() + 1);
                document.getElementById('certification_expiry').value = certDate.toISOString().split('T')[0];
            }
        });
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
