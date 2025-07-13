<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('blog.view');

$page_title = 'Blog Management';

// Handle bulk actions
if ($_POST && isset($_POST['bulk_action']) && isset($_POST['selected_posts'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $action = $_POST['bulk_action'];
        $post_ids = array_map('intval', $_POST['selected_posts']);

        try {
            switch ($action) {
                case 'publish':
                    if (hasPermission('blog.edit')) {
                        $placeholders = str_repeat('?,', count($post_ids) - 1) . '?';
                        $stmt = $db->prepare("UPDATE blog_posts SET status = 'published', published_at = NOW() WHERE id IN ($placeholders)");
                        $stmt->execute($post_ids);
                        $success = count($post_ids) . ' posts published successfully!';
                    }
                    break;

                case 'draft':
                    if (hasPermission('blog.edit')) {
                        $placeholders = str_repeat('?,', count($post_ids) - 1) . '?';
                        $stmt = $db->prepare("UPDATE blog_posts SET status = 'draft' WHERE id IN ($placeholders)");
                        $stmt->execute($post_ids);
                        $success = count($post_ids) . ' posts moved to draft!';
                    }
                    break;

                case 'delete':
                    if (hasPermission('blog.delete')) {
                        $placeholders = str_repeat('?,', count($post_ids) - 1) . '?';
                        $stmt = $db->prepare("DELETE FROM blog_posts WHERE id IN ($placeholders)");
                        $stmt->execute($post_ids);
                        $success = count($post_ids) . ' posts deleted successfully!';
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error performing bulk action: ' . $e->getMessage();
        }
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$author_filter = $_GET['author'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(bp.title LIKE ? OR bp.content LIKE ? OR bp.excerpt LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "bp.status = ?";
    $params[] = $status_filter;
}

if ($category_filter) {
    $where_conditions[] = "bp.category_id = ?";
    $params[] = $category_filter;
}

if ($author_filter) {
    $where_conditions[] = "bp.author_id = ?";
    $params[] = $author_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*) 
    FROM blog_posts bp 
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    LEFT JOIN users u ON bp.author_id = u.id
    $where_clause
";
$total_posts = $db->prepare($count_sql);
$total_posts->execute($params);
$total_count = $total_posts->fetchColumn();
$total_pages = ceil($total_count / $per_page);

// Get blog posts
$sql = "
    SELECT bp.*, 
           bc.name as category_name, bc.color as category_color,
           u.first_name, u.last_name,
           (SELECT COUNT(*) FROM blog_comments WHERE post_id = bp.id AND status = 'approved') as comment_count
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    LEFT JOIN users u ON bp.author_id = u.id
    $where_clause
    ORDER BY $sort $order
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$categories = $db->query("SELECT id, name FROM blog_categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$authors = $db->query("
    SELECT DISTINCT u.id, u.first_name, u.last_name 
    FROM users u 
    JOIN blog_posts bp ON u.id = bp.author_id 
    ORDER BY u.first_name, u.last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_posts,
        COUNT(CASE WHEN status = 'published' THEN 1 END) as published_posts,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_posts,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as posts_30d
    FROM blog_posts
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
        .booking-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #ecf0f1;
        }
        
        .booking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
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
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .booking-status.pending { background: #f39c12; color: white; }
        .booking-status.confirmed { background: #2ecc71; color: white; }
        .booking-status.cancelled { background: #e74c3c; color: white; }
        .booking-status.completed { background: #3498db; color: white; }
        .booking-status.refunded { background: #95a5a6; color: white; }
        
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
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
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
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
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
        
        .payment-status.paid { color: #2ecc71; }
        .payment-status.partial { color: #f39c12; }
        .payment-status.pending { color: #e74c3c; }
        
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            text-align: center;
            border-left: 5px solid #3498db;
            transition: transform 0.3s ease;
            border: 1px solid #ecf0f1;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.pending { border-left-color: #f39c12; }
        .stat-card.confirmed { border-left-color: #2ecc71; }
        .stat-card.cancelled { border-left-color: #e74c3c; }
        .stat-card.revenue { border-left-color: #3498db; }
        
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
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
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
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
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
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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
                    <h1>Blog Management</h1>
                    <p>Create, edit, and manage your blog posts and content</p>
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
                <div class="blog-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_posts']); ?></div>
                        <div class="stat-label">Total Posts</div>
                    </div>
                    <div class="stat-card published">
                        <div class="stat-value"><?php echo number_format($stats['published_posts']); ?></div>
                        <div class="stat-label">Published</div>
                    </div>
                    <div class="stat-card draft">
                        <div class="stat-value"><?php echo number_format($stats['draft_posts']); ?></div>
                        <div class="stat-label">Drafts</div>
                    </div>
                    <div class="stat-card views">
                        <div class="stat-value"><?php echo number_format((float) ($stats['total_views'] ?? 0));
                        ?></div>
                        <div class="stat-label">Total Views</div>
                    </div>
                </div>
                
                <div class="content-header">
                    <div class="content-title">
                        <h2>Blog Posts</h2>
                        <p>Manage your blog content and articles</p>
                    </div>
                    <div class="content-actions">
                        <div class="view-toggle">
                            <button class="view-btn active" data-view="grid">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="view-btn" data-view="list">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                        
                        <a href="categories.php" class="btn btn-secondary">
                            <i class="fas fa-tags"></i> Categories
                        </a>
                        
                        <?php if (hasPermission('blog.create')): ?>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> New Post
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search posts..." class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category" class="form-control">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Author</label>
                                <select name="author" class="form-control">
                                    <option value="">All Authors</option>
                                    <?php foreach ($authors as $author): ?>
                                            <option value="<?php echo $author['id']; ?>" 
                                                    <?php echo $author_filter == $author['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($author['first_name'] . ' ' . $author['last_name']); ?>
                                            </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <form method="POST" id="bulkForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <span id="selectedCount">0</span> posts selected
                        
                        <select name="bulk_action" class="form-control" style="width: auto;">
                            <option value="">Choose action...</option>
                            <?php if (hasPermission('blog.edit')): ?>
                                <option value="publish">Publish</option>
                                <option value="draft">Move to Draft</option>
                            <?php endif; ?>
                            <?php if (hasPermission('blog.delete')): ?>
                                <option value="delete">Delete</option>
                            <?php endif; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to perform this action?')">
                            Apply
                        </button>
                        
                        <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                            Clear Selection
                        </button>
                    </form>
                </div>
                
                <!-- Posts List -->
                <div class="posts-container" id="postsContainer">
                    <?php if (empty($posts)): ?>
                            <div class="empty-state">
                                <i class="fas fa-blog"></i>
                                <h3>No Blog Posts Found</h3>
                                <p>No posts match your current filters. Try adjusting your search criteria.</p>
                                <?php if (hasPermission('blog.create')): ?>
                                        <a href="add.php" class="btn btn-primary">Create Your First Post</a>
                                <?php endif; ?>
                            </div>
                    <?php else: ?>
                            <div class="posts-grid" id="postsGrid">
                                <?php foreach ($posts as $post): ?>
                                        <div class="post-card">
                                            <div class="post-header">
                                                <div>
                                                    <label class="checkbox-container">
                                                        <input type="checkbox" name="selected_posts[]" value="<?php echo $post['id']; ?>" class="post-checkbox">
                                                        <span class="checkmark"></span>
                                                    </label>
                                                </div>
                                                <div class="post-status">
                                                    <span class="status-badge <?php echo $post['status']; ?>">
                                                        <?php echo ucfirst($post['status']); ?>
                                                    </span>
                                                   <?php if (!empty($post['is_featured'])): ?>

                                                            <span class="featured-indicator">Featured</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                    
                                            <?php if ($post['featured_image']): ?>
                                                    <img src="../../../<?php echo htmlspecialchars($post['featured_image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($post['title']); ?>" class="post-image">
                                            <?php endif; ?>
                                    
                                            <div class="post-content">
                                                <h3 class="post-title">
                                                    <a href="edit.php?id=<?php echo $post['id']; ?>">
                                                        <?php echo htmlspecialchars($post['title']); ?>
                                                    </a>
                                                </h3>
                                        
                                                <div class="post-meta">
                                                    <div class="meta-item">
                                                        <i class="fas fa-user"></i>
                                                        <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                                                    </div>
                                                    <div class="meta-item">
                                                        <i class="fas fa-calendar"></i>
                                                        <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                                                    </div>
                                                    <?php if ($post['category_name']): ?>
                                                        <div class="meta-item">
                                                            <span class="category-badge" style="background-color: <?php echo $post['category_color'] ?: '#667eea'; ?>">
                                                                <?php echo htmlspecialchars($post['category_name']); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="meta-item">
                                                        <i class="fas fa-eye"></i>
                                                        <?php echo number_format($post['view_count']); ?> views
                                                    </div>
                                                    <div class="meta-item">
                                                        <i class="fas fa-comments"></i>
                                                        <?php echo $post['comment_count']; ?> comments
                                                    </div>
                                                </div>
                                        
                                                <?php if ($post['excerpt']): ?>
                                                        <p class="post-excerpt"><?php echo htmlspecialchars(substr($post['excerpt'], 0, 150)); ?>...</p>
                                                <?php endif; ?>
                                            </div>
                                    
                                            <div class="post-footer">
                                                <div class="post-stats">
                                                    <?php if ($post['published_at']): ?>
                                                            <small>Published: <?php echo date('M j, Y g:i A', strtotime($post['published_at'])); ?></small>
                                                    <?php else: ?>
                                                            <small>Created: <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                        
                                                <div class="post-actions">
    <a href="../../blog/<?php echo htmlspecialchars($post['slug']); ?>" target="_blank" class="btn btn-sm btn-outline">
        <i class="fas fa-eye"></i> View
    </a>

    <?php if (hasPermission('blog.edit')): ?>
        <a href="edit.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
    <?php endif; ?>

    <?php if (hasPermission('blog.delete')): ?>
        <a href="delete.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-danger" 
           onclick="return confirm('Are you sure you want to delete this post?')">
            <i class="fas fa-trash"></i> Delete
        </a>
    <?php endif; ?>
</div>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total_count)); ?> 
                                of <?php echo number_format($total_count); ?> posts
                            </div>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                <?php endif; ?>
                            
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                           class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                <?php endfor; ?>
                            
                                <?php if ($page < $total_pages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                <?php endif; ?>
                            </div>
                        </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // View toggle functionality
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const view = this.dataset.view;
                const container = document.getElementById('postsGrid');
                
                if (view === 'list') {
                    container.classList.add('posts-list');
                    container.classList.remove('posts-grid');
                } else {
                    container.classList.add('posts-grid');
                    container.classList.remove('posts-list');
                }
            });
        });
        
        // Bulk actions functionality
        const checkboxes = document.querySelectorAll('.post-checkbox');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');
        const bulkForm = document.getElementById('bulkForm');
        
        function updateBulkActions() {
            const selected = document.querySelectorAll('.post-checkbox:checked');
            selectedCount.textContent = selected.length;
            
            if (selected.length > 0) {
                bulkActions.classList.add('show');
                
                // Add selected post IDs to bulk form
                const existingInputs = bulkForm.querySelectorAll('input[name="selected_posts[]"]');
                existingInputs.forEach(input => input.remove());
                
                selected.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_posts[]';
                    input.value = checkbox.value;
                    bulkForm.appendChild(input);
                });
            } else {
                bulkActions.classList.remove('show');
            }
        }
        
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });
        
        function clearSelection() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkActions();
        }
        
        // Select all functionality
        const selectAllBtn = document.createElement('button');
        selectAllBtn.type = 'button';
        selectAllBtn.className = 'btn btn-secondary btn-sm';
        selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
        selectAllBtn.onclick = function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateBulkActions();
        };
        
        if (checkboxes.length > 0) {
            document.querySelector('.content-actions').appendChild(selectAllBtn);
        }
    </script>
    
    <script src="../../../assets/js/admin.js"></script>
</body>
</html>
