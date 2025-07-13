<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

// Check admin permissions


// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = ['1=1'];
$params = [];

if ($category_filter) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
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

// Get categories for filter
$categories = $db->query("SELECT * FROM store_categories WHERE status = 'active' ORDER BY name")->fetchAll();

$total_pages = ceil($total_products / $per_page);

$page_title = 'Store Management';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<style>
    :root {
        --gold: #d4af37;
        --gold-light: #f8e8c1;
        --gold-dark: #b8941f;
        --white: #ffffff;
        --black: #2c3e50;
        --black-light: #34495e;
        --green: #27ae60;
        --green-light: #2ecc71;
        --gray: #ecf0f1;
        --gray-dark: #bdc3c7;
    }

    /* Admin Layout */
    .admin-container {
        display: flex;
        min-height: 100vh;
        background-color: #f5f7fa;
    }

    .admin-sidebar {
        width: 250px;
        background-color: var(--black);
        color: var(--white);
        padding: 20px 0;
        position: fixed;
        height: 100vh;
        transition: all 0.3s;
        z-index: 1000;
    }

    .sidebar-brand {
        padding: 0 20px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 20px;
    }

    .sidebar-brand h2 {
        color: var(--gold);
        font-size: 1.3rem;
        margin: 0;
    }

    .sidebar-menu {
        padding: 0 15px;
    }

    .menu-item {
        margin-bottom: 5px;
    }

    .menu-item a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: var(--white);
        text-decoration: none;
        border-radius: 5px;
        transition: all 0.3s;
    }

    .menu-item a:hover,
    .menu-item.active a {
        background-color: rgba(212, 175, 55, 0.2);
        color: var(--gold);
    }

    .menu-item i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }

    .admin-main {
        flex: 1;
        margin-left: 250px;
        padding: 20px;
        transition: all 0.3s;
    }

    /* Header Styles */
    .admin-header {
        background: linear-gradient(135deg, var(--gold), var(--gold-dark));
        color: var(--white);
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header-content h1 {
        margin: 0;
        font-size: 1.8em;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    /* Button Styles */
    .btn {
        padding: 10px 20px;
        border-radius: 5px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
    }

    .btn-primary {
        background: var(--gold);
        color: var(--white);
    }

    .btn-primary:hover {
        background: var(--gold-dark);
    }

    .btn-outline {
        background: transparent;
        border: 1px solid var(--white);
        color: var(--white);
    }

    .btn-outline:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    /* Table Styles */
    .table-container {
        background: var(--white);
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: var(--white);
        border-radius: 10px;
        overflow: hidden;
    }

    .data-table th {
        background: var(--gold);
        color: var(--white);
        font-weight: 600;
        padding: 15px;
        text-align: left;
        text-transform: uppercase;
        font-size: 0.85em;
        letter-spacing: 0.5px;
    }

    .data-table td {
        padding: 15px;
        border-bottom: 1px solid var(--gray);
        vertical-align: middle;
    }

    .data-table tr:last-child td {
        border-bottom: none;
    }

    .data-table tr:hover td {
        background-color: var(--gold-light);
    }

    /* Product Info */
    .product-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .product-image {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        overflow: hidden;
        background: var(--gray);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .image-placeholder {
        color: var(--gold-dark);
        font-size: 1.5em;
    }

    .product-details h3 {
        color: var(--black);
        margin: 0 0 5px 0;
        font-size: 1em;
    }

    .product-details p {
        color: var(--black-light);
        margin: 0;
        font-size: 0.85em;
        line-height: 1.4;
    }

    /* Badges */
    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.75em;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-featured {
        background: var(--gold);
        color: var(--white);
    }

    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
        text-transform: capitalize;
    }

    .status-active {
        background: rgba(39, 174, 96, 0.1);
        color: var(--green);
        border: 1px solid var(--green);
    }

    .status-inactive {
        background: rgba(231, 76, 60, 0.1);
        color: #e74c3c;
        border: 1px solid #e74c3c;
    }

    .status-out_of_stock {
        background: rgba(241, 196, 15, 0.1);
        color: #f1c40f;
        border: 1px solid #f1c40f;
    }

    /* Price Styles */
    .price-info {
        display: flex;
        flex-direction: column;
    }

    .price-current,
    .price-sale {
        font-weight: 600;
        color: var(--black);
    }

    .price-original {
        text-decoration: line-through;
        color: var(--gray-dark);
        font-size: 0.85em;
    }

    .price-sale {
        color: var(--green);
    }

    /* Stock Styles */
    .stock-info {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .stock-quantity {
        font-weight: 600;
    }

    .low-stock {
        color: #e74c3c;
    }

    /* Rating Styles */
    .rating-info {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .stars {
        color: var(--gold-dark);
    }

    .stars .active {
        color: var(--gold);
    }

    .rating-count {
        color: var(--black-light);
        font-size: 0.85em;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 5px;
    }

    .btn-sm {
        padding: 6px 10px;
        font-size: 0.85em;
        border-radius: 5px;
        min-width: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-outline {
        background: transparent;
        border: 1px solid var(--gray-dark);
        color: var(--black-light);
    }

    .btn-outline:hover {
        background: var(--gray);
    }

    .btn-primary {
        background: var(--gold);
        border: 1px solid var(--gold);
        color: var(--white);
    }

    .btn-primary:hover {
        background: var(--gold-dark);
        border-color: var(--gold-dark);
    }

    .btn-secondary {
        background: var(--black-light);
        border: 1px solid var(--black-light);
        color: var(--white);
    }

    .btn-secondary:hover {
        background: var(--black);
        border-color: var(--black);
    }

    .btn-danger {
        background: #e74c3c;
        border: 1px solid #e74c3c;
        color: var(--white);
    }

    .btn-danger:hover {
        background: #c0392b;
        border-color: #c0392b;
    }

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
    }

    .page-btn {
        padding: 8px 15px;
        border-radius: 5px;
        background: var(--white);
        border: 1px solid var(--gray);
        color: var(--black-light);
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .page-btn:hover,
    .page-btn.active {
        background: var(--gold);
        border-color: var(--gold);
        color: var(--white);
    }

    /* Filters Section */
    .filters-section {
        background: var(--white);
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .filters-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .filter-group label {
        font-size: 0.85em;
        color: var(--black-light);
        font-weight: 500;
    }

    .filter-group input,
    .filter-group select {
        padding: 10px 15px;
        border-radius: 5px;
        border: 1px solid var(--gray);
        background: var(--white);
        min-width: 180px;
    }

    .filter-group input:focus,
    .filter-group select:focus {
        outline: none;
        border-color: var(--gold);
        box-shadow: 0 0 0 2px var(--gold-light);
    }

    /* Bulk Actions */
    .bulk-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        background: var(--white);
        padding: 15px;
        border-radius: 5px;
        margin-top: 15px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    /* Responsive Adjustments */
    @media (max-width: 1200px) {
        .admin-sidebar {
            width: 70px;
            overflow: hidden;
        }

        .admin-sidebar .menu-item span {
            display: none;
        }

        .admin-sidebar .sidebar-brand h2 {
            font-size: 0;
        }

        .admin-sidebar .sidebar-brand h2::before {
            content: "S";
            font-size: 1.5rem;
        }

        .admin-main {
            margin-left: 70px;
        }
    }

    @media (max-width: 992px) {
        .filters-form {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
        }

        .header-content {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }
    }

    @media (max-width: 768px) {
        .data-table {
            display: block;
            overflow-x: auto;
        }

        .product-info {
            flex-direction: column;
            align-items: flex-start;
        }

        .action-buttons {
            flex-wrap: wrap;
        }

        .admin-sidebar {
            width: 100%;
            height: auto;
            position: relative;
            padding: 10px;
        }

        .admin-sidebar .menu-item span {
            display: inline;
        }

        .admin-main {
            margin-left: 0;
        }

        .sidebar-menu {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .menu-item {
            margin-bottom: 0;
        }

        .menu-item a {
            padding: 8px 12px;
        }
    }
</style>

<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="admin-header">
                <div class="header-content">
                    <h1>Store Management</h1>
                    <div class="header-actions">
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Product
                        </a>
                        <a href="categories.php" class="btn btn-outline">
                            <i class="fas fa-tags"></i> Categories
                        </a>
                        <a href="orders.php" class="btn btn-outline">
                            <i class="fas fa-shopping-cart"></i> Orders
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Search products..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active
                            </option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>
                                Inactive</option>
                            <option value="out_of_stock" <?php echo $status_filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="sort">
                            <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date
                                Created</option>
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="price" <?php echo $sort === 'price' ? 'selected' : ''; ?>>Price</option>
                            <option value="stock_quantity" <?php echo $sort === 'stock_quantity' ? 'selected' : ''; ?>>
                                Stock</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="order">
                            <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending
                            </option>
                            <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="index.php" class="btn btn-outline">Reset</a>
                </form>
            </div>

            <!-- Products Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <h2>Products (<?php echo number_format($total_products); ?>)</h2>
                    </div>
                    <div class="table-actions">
                        <button class="btn btn-outline btn-sm" onclick="toggleView()">
                            <i class="fas fa-th"></i> Grid View
                        </button>
                        <div class="bulk-actions">
                            <select id="bulkAction">
                                <option value="">Bulk Actions</option>
                                <option value="activate">Activate</option>
                                <option value="deactivate">Deactivate</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button type="button" onclick="executeBulkAction()"
                                class="btn btn-outline btn-sm">Apply</button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>SKU</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="product-checkbox"
                                            value="<?php echo $product['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="product-info">
                                            <div class="product-image">
                                                <?php if ($product['featured_image']): ?>
                                                    <img src="<?php echo htmlspecialchars($product['featured_image']); ?>"
                                                        alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                <?php else: ?>
                                                    <div class="image-placeholder">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-details">
                                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                                <p><?php echo htmlspecialchars(substr($product['short_description'], 0, 100)); ?>...
                                                </p>
                                                <?php if ($product['featured']): ?>
                                                    <span class="badge badge-featured">Featured</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                    <td>
                                        <div class="price-info">
                                            <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                                <span
                                                    class="price-original"><?php echo formatCurrency($product['price']); ?></span>
                                                <span
                                                    class="price-sale"><?php echo formatCurrency($product['sale_price']); ?></span>
                                            <?php else: ?>
                                                <span
                                                    class="price-current"><?php echo formatCurrency($product['price']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="stock-info">
                                            <span
                                                class="stock-quantity <?php echo $product['stock_quantity'] <= $product['min_stock_level'] ? 'low-stock' : ''; ?>">
                                                <?php echo $product['stock_quantity']; ?>
                                            </span>
                                            <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                                <i class="fas fa-exclamation-triangle text-warning" title="Low stock"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $product['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $product['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($product['review_count'] > 0): ?>
                                            <div class="rating-info">
                                                <div class="stars">
                                                    <?php
                                                    $rating = round($product['avg_rating']);
                                                    for ($i = 1; $i <= 5; $i++):
                                                        ?>
                                                        <i class="fas fa-star <?php echo $i <= $rating ? 'active' : ''; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="rating-count">(<?php echo $product['review_count']; ?>)</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No reviews</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view.php?id=<?php echo $product['id']; ?>"
                                                class="btn btn-sm btn-outline" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $product['id']; ?>"
                                                class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button
                                                onclick="toggleStatus(<?php echo $product['id']; ?>, '<?php echo $product['status']; ?>')"
                                                class="btn btn-sm btn-secondary" title="Toggle Status">
                                                <i
                                                    class="fas fa-toggle-<?php echo $product['status'] === 'active' ? 'on' : 'off'; ?>"></i>
                                            </button>
                                            <button onclick="deleteProduct(<?php echo $product['id']; ?>)"
                                                class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function ($k) {
                                     return $k !== 'page';
                                 }, ARRAY_FILTER_USE_KEY)); ?>" class="page-btn">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function ($k) {
                                   return $k !== 'page';
                               }, ARRAY_FILTER_USE_KEY)); ?>"
                                class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, function ($k) {
                                     return $k !== 'page';
                                 }, ARRAY_FILTER_USE_KEY)); ?>" class="page-btn">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script src="../../assets/js/admin.js"></script>
        <script>
            // Select all functionality
            document.getElementById('selectAll').addEventListener('change', function () {
                const checkboxes = document.querySelectorAll('.product-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });

            // Toggle product status
            function toggleStatus(productId, currentStatus) {
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

                fetch('../api/toggle-product-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        status: newStatus
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error updating product status');
                        }
                    });
            }

            // Delete product
            function deleteProduct(productId) {
                if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                    fetch('../api/delete-product.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            product_id: productId
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Error deleting product');
                            }
                        });
                }
            }

            // Bulk actions
            function executeBulkAction() {
                const action = document.getElementById('bulkAction').value;
                const selectedProducts = Array.from(document.querySelectorAll('.product-checkbox:checked'))
                    .map(checkbox => checkbox.value);

                if (!action) {
                    alert('Please select an action');
                    return;
                }

                if (selectedProducts.length === 0) {
                    alert('Please select at least one product');
                    return;
                }

                if (action === 'delete' && !confirm('Are you sure you want to delete the selected products?')) {
                    return;
                }

                fetch('../api/bulk-product-action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: action,
                        product_ids: selectedProducts
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error executing bulk action');
                        }
                    });
            }
        </script>
</body>

</html>