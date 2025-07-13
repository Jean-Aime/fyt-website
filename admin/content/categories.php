<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('blog.view');

$page_title = 'Blog Categories';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            // Add new category
            if (isset($_POST['add_category']) && hasPermission('blog.edit')) {
                $name = trim($_POST['name']);
                $slug = trim($_POST['slug']);
                $color = $_POST['color'];
                $status = $_POST['status'];

                $stmt = $db->prepare("INSERT INTO blog_categories (name, slug, color, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $color, $status]);
                $success = "Category added successfully!";
            }

            // Update category
            if (isset($_POST['update_category']) && hasPermission('blog.edit')) {
                $id = (int) $_POST['id'];
                $name = trim($_POST['name']);
                $slug = trim($_POST['slug']);
                $color = $_POST['color'];
                $status = $_POST['status'];

                $stmt = $db->prepare("UPDATE blog_categories SET name = ?, slug = ?, color = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $color, $status, $id]);
                $success = "Category updated successfully!";
            }

            // Delete category
            if (isset($_POST['delete_category']) && hasPermission('blog.delete')) {
                $id = (int) $_POST['id'];

                // Check if category is empty
                $check = $db->prepare("SELECT COUNT(*) FROM blog_posts WHERE category_id = ?");
                $check->execute([$id]);
                $count = $check->fetchColumn();

                if ($count > 0) {
                    $error = "Cannot delete category that contains posts. Please reassign or delete the posts first.";
                } else {
                    $stmt = $db->prepare("DELETE FROM blog_categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Category deleted successfully!";
                }
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get all categories
$categories = $db->query("SELECT * FROM blog_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get category stats
$stats = $db->query("
    SELECT 
        COUNT(*) as total_categories,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_categories,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_categories
    FROM blog_categories
")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .category-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            border-left: 5px solid;
            transition: transform 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-5px);
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .category-name {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
        }

        .category-slug {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .category-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .category-status.active {
            background: #2ecc71;
            color: white;
        }

        .category-status.inactive {
            background: #95a5a6;
            color: white;
        }

        .category-color {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            text-align: center;
            border-left: 5px solid #3498db;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.active {
            border-left-color: #2ecc71;
        }

        .stat-card.inactive {
            border-left-color: #95a5a6;
        }

        .stat-value {
            font-size: 2.2em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modal-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #333;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #ddd;
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #333;
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="content">
                <div class="blog-header">
                    <h1>Blog Categories</h1>
                    <p>Manage your blog categories and organization</p>
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

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_categories']); ?></div>
                        <div class="stat-label">Total Categories</div>
                    </div>
                    <div class="stat-card active">
                        <div class="stat-value"><?php echo number_format($stats['active_categories']); ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat-card inactive">
                        <div class="stat-value"><?php echo number_format($stats['inactive_categories']); ?></div>
                        <div class="stat-label">Inactive</div>
                    </div>
                </div>

                <div class="content-header">
                    <div class="content-title">
                        <h2>All Categories</h2>
                        <p>Manage your blog categories and organization</p>
                    </div>
                    <div class="content-actions">
                        <?php if (hasPermission('blog.edit')): ?>
                            <button class="btn btn-primary" onclick="openAddModal()">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Categories List -->
                <div class="categories-container">
                    <?php if (empty($categories)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <h3>No Categories Found</h3>
                            <p>You haven't created any categories yet. Categories help organize your blog posts.</p>
                            <?php if (hasPermission('blog.edit')): ?>
                                <button class="btn btn-primary" onclick="openAddModal()">
                                    <i class="fas fa-plus"></i> Add Your First Category
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="categories-grid">
                            <?php foreach ($categories as $category): ?>
                                <div class="category-card"
                                    style="border-left-color: <?php echo htmlspecialchars($category['color']) ?: '#667eea'; ?>">
                                    <div class="category-header">
                                        <h3 class="category-name">
                                            <span class="category-color"
                                                style="background-color: <?php echo htmlspecialchars($category['color']) ?: '#667eea'; ?>"></span>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </h3>
                                        <span class="category-status <?php echo htmlspecialchars($category['status']); ?>">
                                            <?php echo ucfirst($category['status']); ?>
                                        </span>
                                    </div>

                                    <div class="category-slug">
                                        <i class="fas fa-link"></i> <?php echo htmlspecialchars($category['slug']); ?>
                                    </div>

                                    <div class="category-footer">
                                        <div class="category-actions">
                                            <a href="../../blog/category/<?php echo htmlspecialchars($category['slug']); ?>"
                                                target="_blank" class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye"></i> View
                                            </a>

                                            <?php if (hasPermission('blog.edit')): ?>
                                                <button class="btn btn-sm btn-primary" onclick="openEditModal(
                                                            <?php echo $category['id']; ?>,
                                                            '<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>',
                                                            '<?php echo htmlspecialchars($category['slug'], ENT_QUOTES); ?>',
                                                            '<?php echo htmlspecialchars($category['color'], ENT_QUOTES); ?>',
                                                            '<?php echo htmlspecialchars($category['status']); ?>'
                                                        )">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            <?php endif; ?>

                                            <?php if (hasPermission('blog.delete')): ?>
                                                <form method="POST" style="display: inline;"
                                                    onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" name="delete_category" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
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
    </div>

    <!-- Add Category Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Category</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="color" name="color" value="#667eea" class="form-control">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Category</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="id" id="editCategoryId">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" id="editCategoryName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" id="editCategorySlug" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="color" name="color" id="editCategoryColor" class="form-control">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="editCategoryStatus" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(id, name, slug, color, status) {
            document.getElementById('editCategoryId').value = id;
            document.getElementById('editCategoryName').value = name;
            document.getElementById('editCategorySlug').value = slug;
            document.getElementById('editCategoryColor').value = color;
            document.getElementById('editCategoryStatus').value = status;

            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function (event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>

    <script src="../../../assets/js/admin.js"></script>
</body>

</html>