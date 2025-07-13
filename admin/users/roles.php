<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('roles.view');

$page_title = 'Roles & Permissions';

// Handle role creation/update
if ($_POST && isset($_POST['action'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $action = $_POST['action'];
            $name = trim($_POST['name']);
            $display_name = trim($_POST['display_name']);
            $description = trim($_POST['description']);
            $permissions = $_POST['permissions'] ?? [];

            if ($action === 'create') {
                if (hasPermission('roles.create')) {
                    $stmt = $db->prepare("INSERT INTO user_roles (name, display_name, description, permissions) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $display_name, $description, json_encode($permissions)]);
                    $success = 'Role created successfully!';
                }
            } elseif ($action === 'update') {
                if (hasPermission('roles.edit')) {
                    $role_id = (int) $_POST['role_id'];
                    $stmt = $db->prepare("UPDATE user_roles SET name = ?, display_name = ?, description = ?, permissions = ? WHERE id = ?");
                    $stmt->execute([$name, $display_name, $description, json_encode($permissions), $role_id]);
                    $success = 'Role updated successfully!';
                }
            }
        } catch (Exception $e) {
            $error = 'Error saving role: ' . $e->getMessage();
        }
    }
}

// Handle role deletion
if (isset($_GET['delete']) && hasPermission('roles.delete')) {
    $role_id = (int) $_GET['delete'];
    try {
        // Check if role is in use
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        $stmt->execute([$role_id]);
        $users_count = $stmt->fetchColumn();

        if ($users_count > 0) {
            $error = "Cannot delete role. It is assigned to $users_count users.";
        } else {
            $stmt = $db->prepare("DELETE FROM user_roles WHERE id = ?");
            $stmt->execute([$role_id]);
            $success = 'Role deleted successfully!';
        }
    } catch (Exception $e) {
        $error = 'Error deleting role: ' . $e->getMessage();
    }
}

// Get all roles
$roles = $db->query("
    SELECT r.*, COUNT(u.id) as user_count 
    FROM roles r 
    LEFT JOIN user_roles ur ON ur.role_id = r.id
    LEFT JOIN users u ON ur.user_id = u.id
    GROUP BY r.id 
    ORDER BY r.display_name
")->fetchAll(PDO::FETCH_ASSOC);


// Available permissions
$available_permissions = [
    'Dashboard' => [
        'dashboard.view' => 'View Dashboard'
    ],
    'Tours' => [
        'tours.view' => 'View Tours',
        'tours.create' => 'Create Tours',
        'tours.edit' => 'Edit Tours',
        'tours.delete' => 'Delete Tours'
    ],
    'Bookings' => [
        'bookings.view' => 'View Bookings',
        'bookings.create' => 'Create Bookings',
        'bookings.edit' => 'Edit Bookings',
        'bookings.delete' => 'Delete Bookings'
    ],
    'Users' => [
        'users.view' => 'View Users',
        'users.create' => 'Create Users',
        'users.edit' => 'Edit Users',
        'users.delete' => 'Delete Users'
    ],
    'Roles' => [
        'roles.view' => 'View Roles',
        'roles.create' => 'Create Roles',
        'roles.edit' => 'Edit Roles',
        'roles.delete' => 'Delete Roles'
    ],
    'Destinations' => [
        'destinations.view' => 'View Destinations',
        'destinations.create' => 'Create Destinations',
        'destinations.edit' => 'Edit Destinations',
        'destinations.delete' => 'Delete Destinations'
    ],
    'Payments' => [
        'payments.view' => 'View Payments',
        'payments.create' => 'Process Payments',
        'payments.edit' => 'Edit Payments',
        'payments.delete' => 'Delete Payments'
    ],
    'Analytics' => [
        'analytics.view' => 'View Analytics',
        'analytics.export' => 'Export Reports'
    ],
    'Content' => [
        'content.view' => 'View Content',
        'content.create' => 'Create Content',
        'content.edit' => 'Edit Content',
        'content.delete' => 'Delete Content'
    ],
    'Media' => [
        'media.view' => 'View Media',
        'media.upload' => 'Upload Media',
        'media.delete' => 'Delete Media'
    ],
    'Settings' => [
        'settings.view' => 'View Settings',
        'settings.edit' => 'Edit Settings'
    ]
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
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .role-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #ecf0f1;
            position: relative;
        }

        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .role-title {
            font-size: 1.4em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .role-name {
            font-size: 0.9em;
            color: #7f8c8d;
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .role-users {
            background: linear-gradient(135deg, #D4AF37, #B8941F);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .role-description {
            color: #7f8c8d;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .role-permissions {
            margin-bottom: 20px;
        }

        .permissions-title {
            font-size: 1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .permissions-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .permission-badge {
            background: #228B22;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .role-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .permission-group {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }

        .permission-group-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .permission-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .permission-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #D4AF37;
        }

        .permission-item label {
            font-size: 0.9em;
            color: #2c3e50;
            cursor: pointer;
        }

        .modal-large {
            max-width: 900px;
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
                        <h2>Roles & Permissions</h2>
                        <p>Manage user roles and their permissions</p>
                    </div>
                    <div class="content-actions">
                        <?php if (hasPermission('roles.create')): ?>
                            <button onclick="openAddRoleModal()" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add New Role
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

                <!-- Roles Grid -->
                <?php if (empty($roles)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-shield"></i>
                        <h3>No Roles Found</h3>
                        <p>Create your first role to manage user permissions.</p>
                        <?php if (hasPermission('roles.create')): ?>
                            <button onclick="openAddRoleModal()" class="btn btn-primary">Create First Role</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="roles-grid">
                        <?php foreach ($roles as $role): ?>
                            <div class="role-card">
                                <div class="role-header">
                                    <div>
                                        <div class="role-title"><?php echo htmlspecialchars($role['display_name']); ?></div>
                                        <div class="role-name"><?php echo htmlspecialchars($role['name']); ?></div>
                                    </div>
                                    <div class="role-users">
                                        <i class="fas fa-users"></i>
                                        <?php echo number_format($role['user_count']); ?>
                                    </div>
                                </div>

                                <?php if ($role['description']): ?>
                                    <div class="role-description">
                                        <?php echo htmlspecialchars($role['description']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="role-permissions">
                                    <div class="permissions-title">Permissions</div>
                                    <div class="permissions-list">
                                        <?php
                                        $permissions = !empty($role['permissions']) && is_string($role['permissions'])
                                            ? json_decode($role['permissions'], true)
                                            : [];
                                        if (empty($permissions)):
                                            ?>
                                            <span style="color: #999; font-style: italic;">No permissions assigned</span>
                                        <?php else: ?>
                                            <?php foreach (array_slice($permissions, 0, 5) as $permission): ?>
                                                <span class="permission-badge"><?php echo htmlspecialchars($permission); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($permissions) > 5): ?>
                                                <span class="permission-badge" style="background: #7f8c8d;">
                                                    +<?php echo count($permissions) - 5; ?> more
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>


                                <div class="role-actions">
                                    <?php if (hasPermission('roles.edit')): ?>
                                        <button onclick="editRole(<?php echo $role['id']; ?>)" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    <?php endif; ?>
                                    <?php if (hasPermission('roles.delete') && $role['user_count'] == 0): ?>
                                        <button onclick="deleteRole(<?php echo $role['id']; ?>)" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Role Modal -->
    <div id="roleModal" class="modal" style="display: none;">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Role</h3>
                <button onclick="closeModal('roleModal')" class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="roleForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="role_id" id="roleId" value="">

                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label for="display_name">Display Name <span class="required">*</span></label>
                            <input type="text" id="display_name" name="display_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="name">System Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" required>
                            <div class="form-help">Lowercase, no spaces (e.g., admin, editor, viewer)</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Permissions</label>
                        <div class="permissions-grid">
                            <?php foreach ($available_permissions as $group => $perms): ?>
                                <div class="permission-group">
                                    <div class="permission-group-title">
                                        <i class="fas fa-<?php echo getGroupIcon($group); ?>"></i>
                                        <?php echo $group; ?>
                                    </div>
                                    <?php foreach ($perms as $perm_key => $perm_label): ?>
                                        <div class="permission-item">
                                            <input type="checkbox" id="perm_<?php echo $perm_key; ?>" name="permissions[]"
                                                value="<?php echo $perm_key; ?>">
                                            <label for="perm_<?php echo $perm_key; ?>"><?php echo $perm_label; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" onclick="closeModal('roleModal')"
                            class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">Create Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddRoleModal() {
            document.getElementById('modalTitle').textContent = 'Add New Role';
            document.getElementById('formAction').value = 'create';
            document.getElementById('submitBtn').textContent = 'Create Role';
            document.getElementById('roleForm').reset();
            document.getElementById('roleModal').style.display = 'flex';
        }

        function editRole(roleId) {
            // Fetch role data and populate form
            fetch(`get-role.php?id=${roleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const role = data.role;
                        document.getElementById('modalTitle').textContent = 'Edit Role';
                        document.getElementById('formAction').value = 'update';
                        document.getElementById('roleId').value = roleId;
                        document.getElementById('submitBtn').textContent = 'Update Role';

                        document.getElementById('display_name').value = role.display_name;
                        document.getElementById('name').value = role.name;
                        document.getElementById('description').value = role.description || '';

                        // Clear all checkboxes first
                        document.querySelectorAll('input[name="permissions[]"]').forEach(cb => {
                            cb.checked = false;
                        });

                        // Check permissions
                        const permissions = JSON.parse(role.permissions || '[]');
                        permissions.forEach(perm => {
                            const checkbox = document.getElementById('perm_' + perm);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });

                        document.getElementById('roleModal').style.display = 'flex';
                    }
                })
                .catch(error => {
                    console.error('Error fetching role data:', error);
                    alert('Error loading role data');
                });
        }

        function deleteRole(roleId) {
            if (confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
                window.location.href = `?delete=${roleId}`;
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Auto-generate system name from display name
        document.getElementById('display_name').addEventListener('input', function () {
            const displayName = this.value;
            const systemName = displayName.toLowerCase()
                .replace(/[^a-z0-9\s]/g, '')
                .replace(/\s+/g, '_');
            document.getElementById('name').value = systemName;
        });

        // Modal styles
        const modalStyles = `
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
            
            .modal-content {
                background: white;
                border-radius: 15px;
                width: 90%;
                max-width: 600px;
                max-height: 90vh;
                overflow-y: auto;
            }
            
            .modal-content.modal-large {
                max-width: 900px;
            }
            
            .modal-header {
                padding: 20px 25px;
                border-bottom: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .modal-header h3 {
                margin: 0;
                color: #2c3e50;
            }
            
            .close-btn {
                background: none;
                border: none;
                font-size: 1.5em;
                cursor: pointer;
                color: #999;
            }
            
            .modal-body {
                padding: 25px;
            }
            
            .modal-footer {
                padding: 20px 25px;
                border-top: 1px solid #eee;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
        `;

        const styleSheet = document.createElement('style');
        styleSheet.textContent = modalStyles;
        document.head.appendChild(styleSheet);
    </script>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>

<?php
function getGroupIcon($group)
{
    $icons = [
        'Dashboard' => 'tachometer-alt',
        'Tours' => 'map-marked-alt',
        'Bookings' => 'calendar-check',
        'Users' => 'users',
        'Roles' => 'user-shield',
        'Destinations' => 'globe-africa',
        'Payments' => 'credit-card',
        'Analytics' => 'chart-bar',
        'Content' => 'edit',
        'Media' => 'images',
        'Settings' => 'cog'
    ];

    return $icons[$group] ?? 'circle';
}
?>
