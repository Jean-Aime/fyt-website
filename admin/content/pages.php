<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize authentication
$auth = new SecureAuth($db);
requireLogin();
requirePermission('content.pages.view');

$page_title = 'Page Management';

// Helper function to sanitize input
// Helper function to sanitize input

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data)
    {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

// Helper function to generate slug
function generateSlug($title)
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    return $slug;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' && hasPermission('content.pages.create')) {
            $title = sanitizeInput($_POST['title'] ?? '');
            $slug = !empty($_POST['slug']) ? sanitizeInput($_POST['slug']) : generateSlug($title);
            $content = $_POST['content'] ?? '';
            $status = $_POST['status'] ?? 'active';
            $seo_title = sanitizeInput($_POST['seo_title'] ?? '');
            $seo_description = sanitizeInput($_POST['seo_description'] ?? '');
            $seo_keywords = sanitizeInput($_POST['seo_keywords'] ?? '');

            // Validate required fields
            if (empty($title)) {
                throw new Exception('Page title is required');
            }

            $stmt = $db->prepare("
                INSERT INTO pages (title, slug, content, status, seo_title, seo_description, seo_keywords, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $title,
                $slug,
                $content,
                $status,
                $seo_title,
                $seo_description,
                $seo_keywords,
                $_SESSION['user_id']
            ]);

            $auth->logActivity($_SESSION['user_id'], 'page_created', "Created page: $title");
            $_SESSION['success'] = 'Page created successfully!';
            header("Location: pages.php");
            exit;

        } elseif ($action === 'edit' && hasPermission('content.pages.edit')) {
            $page_id = (int) ($_POST['page_id'] ?? 0);
            $title = sanitizeInput($_POST['title'] ?? '');
            $slug = !empty($_POST['slug']) ? sanitizeInput($_POST['slug']) : generateSlug($title);
            $content = $_POST['content'] ?? '';
            $status = $_POST['status'] ?? 'active';
            $seo_title = sanitizeInput($_POST['seo_title'] ?? '');
            $seo_description = sanitizeInput($_POST['seo_description'] ?? '');
            $seo_keywords = sanitizeInput($_POST['seo_keywords'] ?? '');

            // Validate required fields
            if (empty($title) || $page_id < 1) {
                throw new Exception('Invalid page data');
            }

            $stmt = $db->prepare("
                UPDATE pages SET 
                    title = ?, slug = ?, content = ?, status = ?, seo_title = ?,
                    seo_description = ?, seo_keywords = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $title,
                $slug,
                $content,
                $status,
                $seo_title,
                $seo_description,
                $seo_keywords,
                $page_id
            ]);

            $auth->logActivity($_SESSION['user_id'], 'page_updated', "Updated page: $title");
            $_SESSION['success'] = 'Page updated successfully!';
            header("Location: pages.php");
            exit;

        } elseif ($action === 'delete' && hasPermission('content.pages.delete')) {
            $page_id = (int) ($_POST['page_id'] ?? 0);

            if ($page_id < 1) {
                throw new Exception('Invalid page ID');
            }

            $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
            $stmt->execute([$page_id]);

            $auth->logActivity($_SESSION['user_id'], 'page_deleted', "Deleted page ID: $page_id");
            $_SESSION['success'] = 'Page deleted successfully!';
            header("Location: pages.php");
            exit;
        }

    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get pages
try {
    $pages = $db->query("
        SELECT *
        FROM pages
        ORDER BY title
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $pages = [];
}

// Check for session messages
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);

$error = $error ?? ($_SESSION['error'] ?? null);
unset($_SESSION['error']);
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
        .booking-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #ecf0f1;
        }

        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ecf0f1;
        }

        .booking-info {
            flex: 1;
        }

        .booking-reference {
            font-size: 1.3em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .booking-date {
            color: #7f8c8d;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .booking-date i {
            color: #3498db;
        }

        .booking-status {
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .booking-status.pending {
            background: #f39c12;
            color: white;
        }

        .booking-status.confirmed {
            background: #2ecc71;
            color: white;
        }

        .booking-status.cancelled {
            background: #e74c3c;
            color: white;
        }

        .booking-status.completed {
            background: #3498db;
            color: white;
        }

        .booking-status.refunded {
            background: #95a5a6;
            color: white;
        }

        .booking-content {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 25px;
            align-items: center;
        }

        .tour-image {
            width: 100px;
            height: 75px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .tour-placeholder {
            width: 100px;
            height: 75px;
            border-radius: 10px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #95a5a6;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .booking-details {
            flex: 1;
        }

        .tour-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .customer-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 10px;
        }

        .customer-name {
            font-weight: 500;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .customer-name i {
            color: #3498db;
        }

        .customer-email {
            color: #7f8c8d;
            font-size: 0.95em;
        }

        .booking-meta {
            display: flex;
            gap: 25px;
            font-size: 0.95em;
            color: #7f8c8d;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .meta-item i {
            color: #3498db;
        }

        .booking-amount {
            text-align: right;
        }

        .total-amount {
            font-size: 1.4em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .payment-status {
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
        }

        .payment-status.paid {
            color: #2ecc71;
        }

        .payment-status.partial {
            color: #f39c12;
        }

        .payment-status.pending {
            color: #e74c3c;
        }

        .payment-status i {
            font-size: 1.1em;
        }

        .booking-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }

        .booking-actions .btn {
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .booking-actions .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
            border: 1px solid #ecf0f1;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.pending {
            border-left-color: #f39c12;
        }

        .stat-card.confirmed {
            border-left-color: #2ecc71;
        }

        .stat-card.cancelled {
            border-left-color: #e74c3c;
        }

        .stat-card.revenue {
            border-left-color: #3498db;
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

        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            border: 1px solid #ecf0f1;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .quick-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .quick-filter {
            padding: 10px 20px;
            border: 1px solid #ecf0f1;
            border-radius: 25px;
            background: white;
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.95em;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .quick-filter.active,
        .quick-filter:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
            transform: translateY(-3px);
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
                <div class="pages-container">
                    <div class="page-header">
                        <h2 class="page-title">Page Management</h2>
                        <div class="page-actions">
                            <?php if (hasPermission('content.pages.create')): ?>
                                <button onclick="openAddModal()" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Page
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

                    <ul class="page-list">
                        <?php if (empty($pages)): ?>
                            <div style="text-align: center; color: #666; padding: 30px;">
                                <i class="fas fa-file-alt" style="font-size: 3em; margin-bottom: 10px;"></i>
                                <p>No pages found</p>
                                <?php if (hasPermission('content.pages.create')): ?>
                                    <button onclick="openAddModal()" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Create Your First Page
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pages as $page): ?>
                                <li class="page-item">
                                    <div class="page-info">
                                        <div class="page-title"><?php echo htmlspecialchars($page['title']); ?></div>
                                        <div class="page-status <?php echo htmlspecialchars($page['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($page['status'])); ?>
                                        </div>
                                    </div>
                                    <div class="page-actions">
                                        <a href="../../../<?php echo htmlspecialchars($page['slug']); ?>" target="_blank">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if (hasPermission('content.pages.edit')): ?>
                                            <a href="#"
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($page)); ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        <?php endif; ?>
                                        <?php if (hasPermission('content.pages.delete')): ?>
                                            <a href="#"
                                                onclick="deletePage(<?php echo $page['id']; ?>, '<?php echo htmlspecialchars($page['title']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Page Modal -->
    <div id="pageModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">Add/Edit Page</h3>

            <form method="POST" id="pageForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="page_id" id="pageId">

                <div class="form-group">
                    <label for="title">Page Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" class="form-control">
                </div>

                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" class="form-control" rows="8"></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="seo_title">SEO Title</label>
                    <input type="text" id="seo_title" name="seo_title" class="form-control">
                </div>

                <div class="form-group">
                    <label for="seo_description">SEO Description</label>
                    <textarea id="seo_description" name="seo_description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="seo_keywords">SEO Keywords</label>
                    <input type="text" id="seo_keywords" name="seo_keywords" class="form-control">
                </div>

                <div class="form-actions">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Page
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Page';
            document.getElementById('formAction').value = 'add';
            document.getElementById('pageForm').reset();
            document.getElementById('pageModal').style.display = 'block';
        }

        function openEditModal(page) {
            document.getElementById('modalTitle').textContent = 'Edit Page: ' + page.title;
            document.getElementById('formAction').value = 'edit';
            document.getElementById('pageId').value = page.id;

            // Populate form fields
            document.getElementById('title').value = page.title;
            document.getElementById('slug').value = page.slug;
            document.getElementById('content').value = page.content;
            document.getElementById('status').value = page.status;
            document.getElementById('seo_title').value = page.seo_title;
            document.getElementById('seo_description').value = page.seo_description;
            document.getElementById('seo_keywords').value = page.seo_keywords;

            document.getElementById('pageModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('pageModal').style.display = 'none';
        }

        function deletePage(pageId, pageTitle) {
            if (confirm(`Are you sure you want to delete the page "${pageTitle}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'pages.php';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="page_id" value="${pageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('pageModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Auto-generate slug from title
        document.getElementById('title').addEventListener('input', function () {
            const title = this.value;
            const slugInput = document.getElementById('slug');

            if (!slugInput.value) { // Only auto-generate if slug is empty
                const slug = title.toLowerCase()
                    .replace(/[^a-z0-9 -]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .trim('-');
                slugInput.value = slug;
            }
        });
    </script>

    <script src="../../../assets/js/admin.js"></script>
</body>

</html>