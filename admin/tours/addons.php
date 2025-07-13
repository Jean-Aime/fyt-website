<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('tours.edit');

$tour_id = isset($_GET['tour_id']) ? (int) $_GET['tour_id'] : 0;

if (!$tour_id) {
    header('Location: index.php?error=Tour not found');
    exit;
}

// Get tour details
$stmt = $db->prepare("SELECT id, title FROM tours WHERE id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    header('Location: index.php?error=Tour not found');
    exit;
}

// Handle form submission
if ($_POST) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'add':
                        $name = trim($_POST['name']);
                        $description = trim($_POST['description']);
                        $price = (float) $_POST['price'];
                        $max_quantity = (int) $_POST['max_quantity'];
                        $required = isset($_POST['required']) ? 1 : 0;

                        $stmt = $db->prepare("
                            INSERT INTO tour_addons (tour_id, name, description, price, max_quantity, required)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$tour_id, $name, $description, $price, $max_quantity, $required]);

                        $success = 'Add-on created successfully!';
                        break;

                    case 'edit':
                        $addon_id = (int) $_POST['addon_id'];
                        $name = trim($_POST['name']);
                        $description = trim($_POST['description']);
                        $price = (float) $_POST['price'];
                        $max_quantity = (int) $_POST['max_quantity'];
                        $required = isset($_POST['required']) ? 1 : 0;
                        $status = $_POST['status'];

                        $stmt = $db->prepare("
                            UPDATE tour_addons 
                            SET name = ?, description = ?, price = ?, max_quantity = ?, required = ?, status = ?
                            WHERE id = ? AND tour_id = ?
                        ");
                        $stmt->execute([$name, $description, $price, $max_quantity, $required, $status, $addon_id, $tour_id]);

                        $success = 'Add-on updated successfully!';
                        break;

                    case 'delete':
                        $addon_id = (int) $_POST['addon_id'];

                        $stmt = $db->prepare("DELETE FROM tour_addons WHERE id = ? AND tour_id = ?");
                        $stmt->execute([$addon_id, $tour_id]);

                        $success = 'Add-on deleted successfully!';
                        break;
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid security token. Please try again.';
    }
}

// Get add-ons
$stmt = $db->prepare("SELECT * FROM tour_addons WHERE tour_id = ? ORDER BY name");
$stmt->execute([$tour_id]);
$addons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Manage Add-ons: ' . $tour['title'];
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
        .addon-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .addon-card:hover {
            transform: translateY(-2px);
        }

        .addon-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .addon-title {
            font-size: 1.3em;
            font-weight: 600;
            color: var(--admin-text);
            margin-bottom: 5px;
        }

        .addon-price {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--admin-primary);
        }

        .addon-description {
            color: var(--admin-text-muted);
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .addon-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }

        .addon-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--admin-text-muted);
        }

        .addon-actions {
            display: flex;
            gap: 10px;
        }

        .form-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--admin-border);
        }

        .modal-title {
            font-size: 1.4em;
            font-weight: 600;
            color: var(--admin-text);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: var(--admin-text-muted);
        }

        .close-modal:hover {
            color: var(--admin-text);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .required-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
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
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--admin-primary);
        }

        input:checked+.slider:before {
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
                        <h2>Manage Add-ons</h2>
                        <p>Tour: <?php echo htmlspecialchars($tour['title']); ?></p>
                    </div>
                    <div class="content-actions">
                        <a href="view.php?id=<?php echo $tour_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Tour
                        </a>
                        <button onclick="openModal('add')" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Add-on
                        </button>
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

                <!-- Add-ons List -->
                <?php if (empty($addons)): ?>
                    <div class="empty-state">
                        <i class="fas fa-plus-circle"></i>
                        <h3>No Add-ons Yet</h3>
                        <p>Create add-ons to offer additional services and increase tour value.</p>
                        <button onclick="openModal('add')" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create First Add-on
                        </button>
                    </div>
                <?php else: ?>
                    <div class="addons-grid">
                        <?php foreach ($addons as $addon): ?>
                            <div class="addon-card">
                                <div class="addon-header">
                                    <div>
                                        <h3 class="addon-title">
                                            <?php echo htmlspecialchars($addon['name']); ?>
                                            <?php if ($addon['required']): ?>
                                                <span class="required-badge">Required</span>
                                            <?php endif; ?>
                                        </h3>
                                        <div class="addon-price">
                                            <?php echo formatCurrency($addon['price']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="status-badge status-<?php echo $addon['status']; ?>">
                                            <?php echo ucfirst($addon['status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if ($addon['description']): ?>
                                    <div class="addon-description">
                                        <?php echo nl2br(htmlspecialchars($addon['description'])); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="addon-meta">
                                    <div class="addon-meta-item">
                                        <i class="fas fa-hashtag"></i>
                                        Max Quantity: <?php echo $addon['max_quantity']; ?>
                                    </div>
                                    <div class="addon-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        Created: <?php echo date('M j, Y', strtotime($addon['created_at'])); ?>
                                    </div>
                                </div>

                                <div class="addon-actions">
                                    <button onclick="editAddon(<?php echo htmlspecialchars(json_encode($addon)); ?>)"
                                        class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button onclick="deleteAddon(<?php echo $addon['id']; ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="addonModal" class="form-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New Add-on</h3>
                <button type="button" class="close-modal" onclick="closeModal()">&times;</button>
            </div>

            <form method="POST" id="addonForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="addon_id" id="addonId">

                <div class="form-group">
                    <label for="name">Add-on Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                        placeholder="Describe what this add-on includes..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Price <span class="required">*</span></label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="max_quantity">Max Quantity</label>
                        <input type="number" id="max_quantity" name="max_quantity" class="form-control" min="1"
                            value="1">
                    </div>
                </div>

                <div class="form-group" id="statusGroup" style="display: none;">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="required" name="required" value="1">
                    <label for="required">This add-on is required for all bookings</label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 30px;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Add-on
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(action, addon = null) {
            const modal = document.getElementById('addonModal');
            const form = document.getElementById('addonForm');
            const title = document.getElementById('modalTitle');
            const actionInput = document.getElementById('formAction');
            const statusGroup = document.getElementById('statusGroup');

            // Reset form
            form.reset();

            if (action === 'add') {
                title.textContent = 'Add New Add-on';
                actionInput.value = 'add';
                statusGroup.style.display = 'none';
            } else if (action === 'edit' && addon) {
                title.textContent = 'Edit Add-on';
                actionInput.value = 'edit';
                statusGroup.style.display = 'block';

                // Populate form
                document.getElementById('addonId').value = addon.id;
                document.getElementById('name').value = addon.name;
                document.getElementById('description').value = addon.description || '';
                document.getElementById('price').value = addon.price;
                document.getElementById('max_quantity').value = addon.max_quantity;
                document.getElementById('status').value = addon.status;
                document.getElementById('required').checked = addon.required == 1;
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('addonModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }

        function editAddon(addon) {
            openModal('edit', addon);
        }

        function deleteAddon(addonId) {
            if (confirm('Are you sure you want to delete this add-on? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="addon_id" value="${addonId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('addonModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>
