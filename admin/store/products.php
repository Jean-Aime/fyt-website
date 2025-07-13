<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('store.view');

$page_title = 'Product Management';

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'])) {
    try {
        $action = $_POST['action'];
        
        if ($action === 'add' && hasPermission('store.create')) {
            $name = sanitizeInput($_POST['name']);
            $slug = !empty($_POST['slug']) ? sanitizeInput($_POST['slug']) : generateSlug($name);
            $description = $_POST['description'];
            $short_description = sanitizeInput($_POST['short_description']);
            $sku = sanitizeInput($_POST['sku']);
            $price = (float)$_POST['price'];
            $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
            $cost_price = !empty($_POST['cost_price']) ? (float)$_POST['cost_price'] : null;
            $stock_quantity = (int)$_POST['stock_quantity'];
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $status = $_POST['status'];
            $featured = isset($_POST['featured']) ? 1 : 0;
            $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
            $dimensions = sanitizeInput($_POST['dimensions']);
            
            // Handle featured image upload
            $featured_image = null;
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['featured_image'], UPLOADS_PATH . '/products', ALLOWED_IMAGE_TYPES);
                if ($upload_result['success']) {
                    $featured_image = 'uploads/products/' . $upload_result['filename'];
                }
            }
            
            // Handle gallery images
            $gallery = [];
            if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
                for ($i = 0; $i < count($_FILES['gallery']['name']); $i++) {
                    if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['gallery']['name'][$i],
                            'type' => $_FILES['gallery']['type'][$i],
                            'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                            'error' => $_FILES['gallery']['error'][$i],
                            'size' => $_FILES['gallery']['size'][$i]
                        ];
                        $upload_result = uploadFile($file, UPLOADS_PATH . '/products', ALLOWED_IMAGE_TYPES);
                        if ($upload_result['success']) {
                            $gallery[] = 'uploads/products/' . $upload_result['filename'];
                        }
                    }
                }
            }
            
            $stmt = $db->prepare("
                INSERT INTO store_products (name, slug, description, short_description, sku, price, sale_price, cost_price, 
                                          stock_quantity, category_id, featured_image, gallery, status, featured, weight, 
                                          dimensions, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name, $slug, $description, $short_description, $sku, $price, $sale_price, $cost_price,
                $stock_quantity, $category_id, $featured_image, json_encode($gallery), $status, $featured,
                $weight, $dimensions, $_SESSION['user_id']
            ]);
            
            $auth->logActivity($_SESSION['user_id'], 'product_created', "Created product: $name");
            $success = 'Product created successfully!';
            
        } elseif ($action === 'edit' && hasPermission('store.edit')) {
            $product_id = (int)$_POST['product_id'];
            $name = sanitizeInput($_POST['name']);
            $slug = !empty($_POST['slug']) ? sanitizeInput($_POST['slug']) : generateSlug($name);
            $description = $_POST['description'];
            $short_description = sanitizeInput($_POST['short_description']);
            $sku = sanitizeInput($_POST['sku']);
            $price = (float)$_POST['price'];
            $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
            $cost_price = !empty($_POST['cost_price']) ? (float)$_POST['cost_price'] : null;
            $stock_quantity = (int)$_POST['stock_quantity'];
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $status = $_POST['status'];
            $featured = isset($_POST['featured']) ? 1 : 0;
            $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
            $dimensions = sanitizeInput($_POST['dimensions']);
            
            // Get current product data
            $stmt = $db->prepare("SELECT featured_image, gallery FROM store_products WHERE id = ?");
            $stmt->execute([$product_id]);
            $current_product = $stmt->fetch();
            
            $featured_image = $current_product['featured_image'];
            $gallery = json_decode($current_product['gallery'], true) ?: [];
            
            // Handle featured image upload
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['featured_image'], UPLOADS_PATH . '/products', ALLOWED_IMAGE_TYPES);
                if ($upload_result['success']) {
                    // Delete old image
                    if ($featured_image && file_exists('../../' . $featured_image)) {
                        unlink('../../' . $featured_image);
                    }
                    $featured_image = 'uploads/products/' . $upload_result['filename'];
                }
            }
            
            // Handle gallery images
            if (isset($_FILES['gallery']) && is_array($_FILES['gallery']['name'])) {
                for ($i = 0; $i < count($_FILES['gallery']['name']); $i++) {
                    if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['gallery']['name'][$i],
                            'type' => $_FILES['gallery']['type'][$i],
                            'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                            'error' => $_FILES['gallery']['error'][$i],
                            'size' => $_FILES['gallery']['size'][$i]
                        ];
                        $upload_result = uploadFile($file, UPLOADS_PATH . '/products', ALLOWED_IMAGE_TYPES);
                        if ($upload_result['success']) {
                            $gallery[] = 'uploads/products/' . $upload_result['filename'];
                        }
                    }
                }
            }
            
            $stmt = $db->prepare("
                UPDATE store_products SET 
                    name = ?, slug = ?, description = ?, short_description = ?, sku = ?, price = ?, 
                    sale_price = ?, cost_price = ?, stock_quantity = ?, category_id = ?, 
                    featured_image = ?, gallery = ?, status = ?, featured = ?, weight = ?, 
                    dimensions = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name, $slug, $description, $short_description, $sku, $price, $sale_price, $cost_price,
                $stock_quantity, $category_id, $featured_image, json_encode($gallery), $status, $featured,
                $weight, $dimensions, $product_id
            ]);
            
            $auth->logActivity($_SESSION['user_id'], 'product_updated', "Updated product: $name");
            $success = 'Product updated successfully!';
            
        } elseif ($action === 'delete' && hasPermission('store.delete')) {
            $product_id = (int)$_POST['product_id'];
            
            // Get product images
            $stmt = $db->prepare("SELECT featured_image, gallery FROM store_products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            // Delete product
            $stmt = $db->prepare("DELETE FROM store_products WHERE id = ?");
            $stmt->execute([$product_id]);
            
            // Delete images
            if ($product['featured_image'] && file_exists('../../' . $product['featured_image'])) {
                unlink('../../' . $product['featured_image']);
            }
            
            $gallery = json_decode($product['gallery'], true) ?: [];
            foreach ($gallery as $image) {
                if (file_exists('../../' . $image)) {
                    unlink('../../' . $image);
                }
            }
            
            $auth->logActivity($_SESSION['user_id'], 'product_deleted', "Deleted product ID: $product_id");
            $success = 'Product deleted successfully!';
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get filters
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = ['1=1'];
$params = [];

if ($category_filter) {
    $where_conditions[] = 'p.category_id = ?';
    $params[] = $category_filter;
}

if ($status_filter) {
    $where_conditions[] = 'p.status = ?';
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)';
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "
    SELECT COUNT(*)
    FROM store_products p
    LEFT JOIN store_categories c ON p.category_id = c.id
    WHERE $where_clause
";
$total_stmt = $db->prepare($count_query);
$total_stmt->execute($params);
$total_products = $total_stmt->fetchColumn();

// Get products
$products_query = "
    SELECT p.*, c.name as category_name,
           (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count,
           (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
    FROM store_products p
    LEFT JOIN store_categories c ON p.category_id = c.id
    WHERE $where_clause
    ORDER BY p.$sort $order
    LIMIT $per_page OFFSET $offset
";
$products_stmt = $db->prepare($products_query);
$products_stmt->execute($params);
$products = $products_stmt->fetchAll();

$total_pages = ceil($total_products / $per_page);

// Get categories
$categories = $db->query("SELECT * FROM store_categories WHERE status = 'active' ORDER BY name")->fetchAll();

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_products,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_products,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_products,
        COUNT(CASE WHEN stock_quantity <= 5 THEN 1 END) as low_stock_products,
        COUNT(CASE WHEN featured = 1 THEN 1 END) as featured_products
    FROM store_products
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        .store-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .store-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card.active { border-left-color: #28a745; }
        .stat-card.inactive { border-left-color: #dc3545; }
        .stat-card.low-stock { border-left-color: #ffc107; }
        .stat-card.featured { border-left-color: #17a2b8; }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .products-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .product-row {
            display: grid;
            grid-template-columns: 80px 1fr 120px 100px 100px 120px 100px 150px;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
            transition: background 0.3s ease;
        }
        
        .product-row:hover {
            background: #f8f9fa;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .product-placeholder {
            width: 60px;
            height: 60px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .product-name {
            font-weight: 600;
            color: #333;
        }
        
        .product-sku {
            font-size: 0.8em;
            color: #666;
        }
        
        .product-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .product-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .product-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .product-status.draft {
            background: #fff3cd;
            color: #856404;
        }
        
        .stock-info {
            text-align: center;
        }
        
        .stock-quantity {
            font-weight: 600;
            color: #333;
        }
        
        .stock-quantity.low {
            color: #dc3545;
        }
        
        .price-info {
            text-align: right;
        }
        
        .price-current {
            font-weight: 600;
            color: #333;
        }
        
        .price-sale {
            font-weight: 600;
            color: #dc3545;
        }
        
        .price-original {
            text-decoration: line-through;
            color: #999;
            font-size: 0.9em;
        }
        
        .product-actions {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            padding: 6px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.3s ease;
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-edit {
            background: #28a745;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group-full {
            grid-column: 1 / -1;
        }
        
        .image-upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .image-upload-item {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .image-upload-item:hover {
            border-color: var(--primary-color);
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 100px;
            border-radius: 5px;
        }
        
        @media (max-width: 768px) {
            .product-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="content">
                <!-- Store Header -->
                <div class="store-header">
                    <h1>Product Management</h1>
                    <p>Manage your travel store products, inventory, and pricing</p>
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
                <div class="store-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
                        <div class="stat-label">Total Products</div>
                    </div>
                    <div class="stat-card active">
                        <div class="stat-value"><?php echo number_format($stats['active_products']); ?></div>
                        <div class="stat-label">Active Products</div>
                    </div>
                    <div class="stat-card low-stock">
                        <div class="stat-value"><?php echo number_format($stats['low_stock_products']); ?></div>
                        <div class="stat-label">Low Stock</div>
                    </div>
                    <div class="stat-card featured">
                        <div class="stat-value"><?php echo number_format($stats['featured_products']); ?></div>
                        <div class="stat-label">Featured Products</div>
                    </div>
                </div>
                
                <!-- Products Table -->
                <div class="products-table">
                    <div class="table-header">
                        <h2>Products (<?php echo number_format($total_products); ?>)</h2>
                        <div class="table-actions">
                            <button class="btn btn-outline" onclick="exportProducts()">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="btn btn-secondary" onclick="openCategoryModal()">
                                <i class="fas fa-tags"></i> Categories
                            </button>
                            <button class="btn btn-primary" onclick="openProductModal()">
                                <i class="fas fa-plus"></i> Add Product
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="filters-section" style="padding: 20px; border-bottom: 1px solid #eee;">
                        <form method="GET" class="filters-form" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search products..." class="form-control" style="width: 200px;">
                            
                            <select name="category" class="form-control" style="width: 150px;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select name="status" class="form-control" style="width: 120px;">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            </select>
                            
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="products.php" class="btn btn-outline">Reset</a>
                        </form>
                    </div>
                    
                    <div class="table-content">
                        <!-- Table Header -->
                        <div class="product-row" style="background: #f8f9fa; font-weight: 600;">
                            <div>Image</div>
                            <div>Product Details</div>
                            <div>Category</div>
                            <div>Price</div>
                            <div>Stock</div>
                            <div>Status</div>
                            <div>Rating</div>
                            <div>Actions</div>
                        </div>
                        
                        <!-- Product Rows -->
                        <?php foreach ($products as $product): ?>
                        <div class="product-row">
                            <div>
                                <?php if ($product['featured_image']): ?>
                                    <img src="../../<?php echo htmlspecialchars($product['featured_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="product-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-sku">SKU: <?php echo htmlspecialchars($product['sku']); ?></div>
                                <?php if ($product['featured']): ?>
                                    <span style="background: #ffc107; color: #212529; padding: 2px 6px; border-radius: 10px; font-size: 0.7em;">Featured</span>
                                <?php endif; ?>
                            </div>
                            
                            <div><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></div>
                            
                            <div class="price-info">
                                <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                    <div class="price-original"><?php echo formatCurrency($product['price']); ?></div>
                                    <div class="price-sale"><?php echo formatCurrency($product['sale_price']); ?></div>
                                <?php else: ?>
                                    <div class="price-current"><?php echo formatCurrency($product['price']); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="stock-info">
                                <div class="stock-quantity <?php echo $product['stock_quantity'] <= 5 ? 'low' : ''; ?>">
                                    <?php echo $product['stock_quantity']; ?>
                                </div>
                                <?php if ($product['stock_quantity'] <= 5): ?>
                                    <small style="color: #dc3545;">Low Stock</small>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <span class="product-status <?php echo $product['status']; ?>">
                                    <?php echo ucfirst($product['status']); ?>
                                </span>
                            </div>
                            
                            <div style="text-align: center;">
                                <?php if ($product['review_count'] > 0): ?>
                                    <div style="color: #ffc107;">
                                        <?php
                                        $rating = round($product['avg_rating']);
                                        for ($i = 1; $i <= 5; $i++):
                                        ?>
                                            <i class="fas fa-star <?php echo $i <= $rating ? '' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <small>(<?php echo $product['review_count']; ?>)</small>
                                <?php else: ?>
                                    <span class="text-muted">No reviews</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-actions">
                                <button class="action-btn btn-view" onclick="viewProduct(<?php echo $product['id']; ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn btn-edit" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($products)): ?>
                        <div class="empty-state" style="text-align: center; padding: 60px 20px; color: #666;">
                            <i class="fas fa-box-open" style="font-size: 4em; margin-bottom: 20px; color: #ddd;"></i>
                            <h3>No products found</h3>
                            <p>No products match your current filters</p>
                            <button class="btn btn-primary" onclick="openProductModal()">Add Your First Product</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="padding: 20px; text-align: center;">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="page-link <?php echo $i === $page ? 'active' : ''; ?>" 
                               style="padding: 8px 12px; margin: 0 2px; text-decoration: none; border-radius: 4px; <?php echo $i === $page ? 'background: var(--primary-color); color: white;' : 'background: #f8f9fa; color: #333;'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Modal -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Product</h3>
                <span class="close" onclick="closeProductModal()">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="product_id" id="productId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Name <span class="required">*</span></label>
                        <input type="text" name="name" id="productName" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>SKU</label>
                        <input type="text" name="sku" id="productSku" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" id="productCategory" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="productStatus" class="form-control">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Regular Price <span class="required">*</span></label>
                        <input type="number" name="price" id="productPrice" class="form-control" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Sale Price</label>
                        <input type="number" name="sale_price" id="productSalePrice" class="form-control" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label>Cost Price</label>
                        <input type="number" name="cost_price" id="productCostPrice" class="form-control" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label>Stock Quantity</label>
                        <input type="number" name="stock_quantity" id="productStock" class="form-control" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Weight (kg)</label>
                        <input type="number" name="weight" id="productWeight" class="form-control" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label>Dimensions</label>
                        <input type="text" name="dimensions" id="productDimensions" class="form-control" placeholder="L x W x H">
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label>Short Description</label>
                        <textarea name="short_description" id="productShortDesc" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label>Full Description</label>
                        <textarea name="description" id="productDescription" class="form-control" rows="6"></textarea>
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label>Featured Image</label>
                        <input type="file" name="featured_image" id="featuredImage" class="form-control" accept="image/*">
                        <div id="featuredImagePreview" style="margin-top: 10px;"></div>
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label>Gallery Images</label>
                        <input type="file" name="gallery[]" id="galleryImages" class="form-control" accept="image/*" multiple>
                        <div id="galleryPreview" class="image-upload-grid" style="margin-top: 10px;"></div>
                    </div>
                    
                    <div class="form-group form-group-full">
                        <label>
                            <input type="checkbox" name="featured" id="productFeatured"> Featured Product
                        </label>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 30px;">
                    <button type="button" onclick="closeProductModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Initialize TinyMCE for description
        tinymce.init({
            selector: '#productDescription',
            height: 200,
            menubar: false,
            plugins: ['lists', 'link', 'image', 'code'],
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code'
        });
        
        function openProductModal() {
            document.getElementById('modalTitle').textContent = 'Add Product';
            document.getElementById('formAction').value = 'add';
            document.getElementById('productForm').reset();
            document.getElementById('productModal').style.display = 'block';
        }
        
        function editProduct(product) {
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('productId').value = product.id;
            
            // Populate form fields
            document.getElementById('productName').value = product.name;
            document.getElementById('productSku').value = product.sku || '';
            document.getElementById('productCategory').value = product.category_id || '';
            document.getElementById('productStatus').value = product.status;
            document.getElementById('productPrice').value = product.price;
            document.getElementById('productSalePrice').value = product.sale_price || '';
            document.getElementById('productCostPrice').value = product.cost_price || '';
            document.getElementById('productStock').value = product.stock_quantity;
            document.getElementById('productWeight').value = product.weight || '';
            document.getElementById('productDimensions').value = product.dimensions || '';
            document.getElementById('productShortDesc').value = product.short_description || '';
            document.getElementById('productFeatured').checked = product.featured == 1;
            
            // Set TinyMCE content
            if (tinymce.get('productDescription')) {
                tinymce.get('productDescription').setContent(product.description || '');
            }
            
            document.getElementById('productModal').style.display = 'block';
        }
        
        function closeProductModal() {
            document.getElementById('productModal').style.display = 'none';
        }
        
        function viewProduct(productId) {
            window.open(`../../product.php?id=${productId}`, '_blank');
        }
        
        function deleteProduct(productId, productName) {
            if (confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" value="${productId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function exportProducts() {
            const params = new URLSearchParams(window.location.search);
            window.open(`../api/export-products.php?${params.toString()}`, '_blank');
        }
        
        // Image preview functionality
        document.getElementById('featuredImage').addEventListener('change', function() {
            const file = this.files[0];
            const preview = document.getElementById('featuredImagePreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" style="max-width: 200px; max-height: 150px; border-radius: 5px;">`;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });
        
        document.getElementById('galleryImages').addEventListener('change', function() {
            const files = this.files;
            const preview = document.getElementById('galleryPreview');
            preview.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.innerHTML = `<img src="${e.target.result}" style="width: 100px; height: 100px; object-fit: cover; border-radius: 5px;">`;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Auto-generate SKU from product name
        document.getElementById('productName').addEventListener('input', function() {
            const name = this.value;
            const sku = name.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 10);
            if (!document.getElementById('productSku').value) {
                document.getElementById('productSku').value = sku;
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('productModal');
            if (event.target === modal) {
                closeProductModal();
            }
        }
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
