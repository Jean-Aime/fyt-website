<?php
require_once 'config/config.php';

// Get store categories
$categories = $db->query("
    SELECT * FROM store_categories 
    WHERE status = 'active' 
    ORDER BY sort_order ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get featured products
$featured_products = $db->query("
    SELECT p.*, c.name as category_name,
           AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
    FROM store_products p
    LEFT JOIN store_categories c ON p.category_id = c.id
    LEFT JOIN product_reviews r ON p.id = r.product_id
    WHERE p.status = 'active' AND p.featured = 1
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Get all products with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$where_conditions = ["p.status = 'active'"];
$params = [];

// Category filter
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $where_conditions[] = "c.slug = ?";
    $params[] = $_GET['category'];
}

// Search filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

// Price range filter
if (isset($_GET['min_price']) && !empty($_GET['min_price'])) {
    $where_conditions[] = "p.price >= ?";
    $params[] = $_GET['min_price'];
}

if (isset($_GET['max_price']) && !empty($_GET['max_price'])) {
    $where_conditions[] = "p.price <= ?";
    $params[] = $_GET['max_price'];
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "
    SELECT COUNT(DISTINCT p.id)
    FROM store_products p
    LEFT JOIN store_categories c ON p.category_id = c.id
    WHERE $where_clause
";
$total_products = $db->prepare($count_query);
$total_products->execute($params);
$total_count = $total_products->fetchColumn();

// Get products
$products_query = "
    SELECT p.*, c.name as category_name,
           AVG(r.rating) as avg_rating, COUNT(r.id) as review_count
    FROM store_products p
    LEFT JOIN store_categories c ON p.category_id = c.id
    LEFT JOIN product_reviews r ON p.id = r.product_id
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$products_stmt = $db->prepare($products_query);
$products_stmt->execute($params);
$products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_pages = ceil($total_count / $per_page);

$page_title = 'Travel Store - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="Shop travel essentials, souvenirs, and exclusive merchandise from Forever Young Tours. Quality products for your adventures.">
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/store.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="Shop travel essentials, souvenirs, and exclusive merchandise from Forever Young Tours. Quality products for your adventures.">
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/store.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Internal CSS for Enhanced Store Design -->
    <style>
        /* Color Variables */
        :root {
            --gold: #D4AF37;
            --gold-light: #F4E4A6;
            --gold-dark: #B8941F;
            --black: #000000;
            --white: #FFFFFF;
            --green: #228B22;
            --green-light: #32CD32;
            --green-dark: #006400;
            --gray-light: #F5F5F5;
            --gray-medium: #E0E0E0;
            --gray-dark: #333333;
        }
        
        /* Store Hero Enhancements */
        .store-hero {
             background: linear-gradient(135deg, rgba(212, 165, 116, 0.9), rgba(102, 234, 102, 0.9)),
                         url('https://images.unsplash.com/photo-1464037866556-6812c9d1c72e?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80') center/cover;
                color: white;
                padding: 150px 0;
                text-align: center;
                position: relative;
                overflow: hidden;
        }
        
        .store-hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/images/store-hero-pattern.png') repeat;
            opacity: 0.1;
            z-index: 1;
        }
        
        .store-hero .container {
            position: relative;
            z-index: 2;
        }
        
        .store-hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            color: var(--white);
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            letter-spacing: 1px;
        }
        
        .store-hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 4rem;
            margin-top: 3rem;
            flex-wrap: wrap;
        }
        
        
        .stat {
            text-align: center;
            padding: 1.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            min-width: 150px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(212,175,55,0.2);
            transition: all 0.3s ease;
        }
        
        .stat:hover {
            background: rgba(212,175,55,0.2);
            transform: translateY(-5px);
        }
        
        .stat-number {
            display: block;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 1rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Featured Products Section */
        .featured-products {
            padding: 100px 0;
            background: var(--white);
            position: relative;
        }
        
        .featured-products::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 20px;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
            opacity: 0.2;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-header h2 {
            font-size: 2.5rem;
            color: var(--black);
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .section-header h2::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--gold);
        }
        
        .section-header p {
            font-size: 1.1rem;
            color: var(--gray-dark);
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* Product Card Enhancements */
        .product-card {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.4s ease;
            position: relative;
            border: 1px solid var(--gray-medium);
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            border-color: var(--gold-light);
        }
        
        .product-card.featured {
            border: 2px solid var(--gold);
            box-shadow: 0 10px 30px rgba(212,175,55,0.2);
        }
        
        .product-card.featured::before {
            content: "Featured";
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--gold);
            color: var(--white);
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 3;
        }
        
        .product-image {
            position: relative;
            height: 280px;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.1);
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            z-index: 2;
            color: var(--white);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .product-badge.sale {
            background: linear-gradient(135deg, #DC3545, #C82333);
        }
        
        .product-badge.low-stock {
            background: linear-gradient(135deg, #FFC107, #FFAB00);
            color: var(--black);
        }
        
        .product-badge.out-of-stock {
            background: linear-gradient(135deg, #6C757D, #495057);
        }
        
        .product-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }
        
        .product-card:hover .product-actions {
            opacity: 1;
            transform: translateY(0);
        }
        
        .btn-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--white);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            color: var(--black);
            font-size: 1rem;
        }
        
        .btn-icon:hover {
            background: var(--gold);
            color: var(--white);
            transform: scale(1.1);
        }
        
        .product-info {
            padding: 1.75rem;
        }
        
        .product-category {
            font-size: 0.85rem;
            color: var(--gold-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        
        .product-name {
            margin-bottom: 0.75rem;
        }
        
        .product-name a {
            color: var(--black);
            text-decoration: none;
            font-size: 1.25rem;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .product-name a:hover {
            color: var(--gold);
        }
        
        .product-description {
            color: var(--gray-dark);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        
        .product-rating .fas.fa-star.active {
            color: var(--gold);
        }
        
        .product-rating .fas.fa-star {
            color: var(--gray-medium);
        }
        
        .product-rating span {
            font-size: 0.9rem;
            color: var(--gray-dark);
            margin-left: 0.25rem;
        }
        
        .product-price {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .price-original {
            text-decoration: line-through;
            color: var(--gray-medium);
            font-size: 1rem;
        }
        
        .price-sale,
        .price-current {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gold);
        }
        
        .add-to-cart {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .add-to-cart:hover {
            background: linear-gradient(135deg, var(--gold-dark) 0%, var(--gold) 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(212,175,55,0.3);
        }
        
        .add-to-cart:disabled {
            background: var(--gray-medium);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Sidebar Enhancements */
        .store-sidebar {
            background: var(--white);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 120px;
            border: 1px solid var(--gray-medium);
        }
        
        .filter-section {
            margin-bottom: 2.5rem;
            padding-bottom: 2.5rem;
            border-bottom: 1px solid var(--gray-medium);
        }
        
        .filter-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .filter-section h3 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            color: var(--black);
            position: relative;
            padding-bottom: 0.75rem;
        }
        
        .filter-section h3::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--gold);
        }
        
        .category-list {
            list-style: none;
            padding: 0;
        }
        
        .category-list li {
            margin-bottom: 0.75rem;
        }
        
        .category-list a {
            display: block;
            padding: 0.75rem 1rem;
            color: var(--gray-dark);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 8px;
            font-weight: 500;
            background: var(--gray-light);
        }
        
        .category-list a:hover,
        .category-list a.active {
            color: var(--white);
            background: var(--gold);
            padding-left: 1.25rem;
        }
        
        .price-filter {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .price-inputs {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .price-inputs input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-medium);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .price-inputs input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.2);
            outline: none;
        }
        
        .price-inputs span {
            color: var(--gray-dark);
            font-weight: 500;
        }
        
        .price-filter button {
            align-self: flex-start;
            padding: 0.75rem 1.5rem;
            background: var(--gold);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .price-filter button:hover {
            background: var(--gold-dark);
            transform: translateY(-2px);
        }
        
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        
        .tag {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            background: var(--gray-light);
            color: var(--gray-dark);
            text-decoration: none;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .tag:hover {
            background: var(--green);
            color: var(--white);
            transform: translateY(-2px);
        }
        
        /* Store Header Enhancements */
        .store-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-medium);
        }
        
        .results-info {
            color: var(--gray-dark);
            font-size: 1rem;
            font-weight: 500;
        }
        
        .store-controls {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .search-form {
            display: flex;
            align-items: center;
            background: var(--white);
            border: 1px solid var(--gray-medium);
            border-radius: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .search-form:focus-within {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.2);
        }
        
        .search-form input {
            padding: 0.875rem 1.5rem;
            border: none;
            outline: none;
            width: 250px;
            font-size: 1rem;
            background: transparent;
        }
        
        .search-form button {
            padding: 0.875rem 1.5rem;
            background: var(--gold);
            color: var(--white);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-form button:hover {
            background: var(--gold-dark);
        }
        
        .sort-options select {
            padding: 0.875rem 1.5rem 0.875rem 1rem;
            border: 1px solid var(--gray-medium);
            border-radius: 8px;
            background: var(--white) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") no-repeat right 1rem center/12px;
            appearance: none;
            font-size: 1rem;
            color: var(--gray-dark);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .sort-options select:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.2);
            outline: none;
        }
        
        .view-toggle {
            display: flex;
            border: 1px solid var(--gray-medium);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .view-btn {
            padding: 0.875rem 1.25rem;
            background: var(--white);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--gray-dark);
        }
        
        .view-btn.active,
        .view-btn:hover {
            background: var(--gold);
            color: var(--white);
        }
        
        /* Pagination Enhancements */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.75rem;
            margin-top: 4rem;
        }
        
        .page-btn {
            padding: 0.75rem 1.25rem;
            background: var(--white);
            color: var(--gray-dark);
            text-decoration: none;
            border: 1px solid var(--gray-medium);
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-width: 45px;
            text-align: center;
        }
        
        .page-btn:hover,
        .page-btn.active {
            background: var(--gold);
            color: var(--white);
            border-color: var(--gold);
        }
        
        /* Cart Sidebar Enhancements */
        .cart-sidebar {
            position: fixed;
            top: 0;
            right: -450px;
            width: 450px;
            height: 100vh;
            background: var(--white);
            box-shadow: -10px 0 30px rgba(0,0,0,0.1);
            z-index: 1100;
            transition: right 0.4s ease;
            display: flex;
            flex-direction: column;
        }
        
        .cart-sidebar.open {
            right: 0;
        }
        
        .cart-header {
            padding: 1.75rem;
            border-bottom: 1px solid var(--gray-medium);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--black);
            color: var(--white);
        }
        
        .cart-header h3 {
            margin: 0;
            color: var(--white);
        }
        
        .cart-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--white);
            transition: all 0.3s ease;
        }
        
        .cart-close:hover {
            color: var(--gold);
            transform: rotate(90deg);
        }
        
        .cart-content {
            flex: 1;
            padding: 1.75rem;
            overflow-y: auto;
        }
        
        .empty-cart {
            text-align: center;
            padding: 3rem 0;
        }
        
        .empty-cart i {
            font-size: 3.5rem;
            color: var(--gray-medium);
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }
        
        .empty-cart p {
            font-size: 1.2rem;
            color: var(--gray-dark);
            margin-bottom: 2rem;
        }
        
        .empty-cart .btn {
            padding: 0.875rem 2rem;
            background: var(--gold);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .empty-cart .btn:hover {
            background: var(--gold-dark);
            transform: translateY(-2px);
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--gray-medium);
        }
        
        .cart-item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid var(--gray-medium);
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--black);
        }
        
        .cart-item-price {
            color: var(--gold);
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 1px solid var(--gray-medium);
            background: var(--white);
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .quantity-btn:hover {
            background: var(--gold);
            color: var(--white);
            border-color: var(--gold);
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid var(--gray-medium);
            border-radius: 5px;
            padding: 0.5rem;
            font-weight: 600;
        }
        
        .remove-item {
            background: none;
            border: none;
            color: #DC3545;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .remove-item:hover {
            color: #C82333;
            transform: scale(1.1);
        }
        
        .cart-footer {
            padding: 1.75rem;
            border-top: 1px solid var(--gray-medium);
            background: var(--gray-light);
        }
        
        .cart-total {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .cart-total strong {
            color: var(--gold);
            font-size: 1.5rem;
        }
        
        .cart-actions {
            display: flex;
            gap: 1.5rem;
        }
        
        .cart-actions .btn {
            flex: 1;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        
        .cart-actions .btn-outline {
            background: transparent;
            border: 2px solid var(--gold);
            color: var(--gold);
        }
        
        .cart-actions .btn-outline:hover {
            background: var(--gold);
            color: var(--white);
        }
        
        .cart-actions .btn-primary {
            background: var(--green);
            color: var(--white);
            border: 2px solid var(--green);
        }
        
        .cart-actions .btn-primary:hover {
            background: var(--green-dark);
            border-color: var(--green-dark);
        }
        
        /* Quick View Modal Enhancements */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.85);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            backdrop-filter: blur(5px);
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--white);
            border-radius: 12px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.4s ease;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 1.75rem;
            border-bottom: 1px solid var(--gray-medium);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--black);
            color: var(--white);
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--white);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--white);
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            color: var(--gold);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .store-layout {
                grid-template-columns: 250px 1fr;
                gap: 2rem;
            }
            
            .product-image {
                height: 240px;
            }
        }
        
        @media (max-width: 992px) {
            .store-hero h1 {
                font-size: 2.8rem;
            }
            
            .store-hero p {
                font-size: 1.1rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .store-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .store-sidebar {
                position: static;
                margin-bottom: 3rem;
            }
            
            .store-header {
                flex-direction: column;
                gap: 1.5rem;
                align-items: stretch;
            }
            
            .store-controls {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .search-form input {
                width: 100%;
            }
            
            .hero-stats {
                gap: 1.5rem;
            }
            
            .stat {
                min-width: 120px;
                padding: 1rem;
            }
            
            .cart-sidebar {
                width: 100%;
                right: -100%;
            }
        }
        
        @media (max-width: 576px) {
            .store-hero {
                padding: 80px 0 50px;
            }
            
            .store-hero h1 {
                font-size: 2.2rem;
            }
            
            .section-header h2 {
                font-size: 2rem;
            }
            
            .product-card {
                margin: 0 0.5rem;
            }
            
            .modal-content {
                max-height: 95vh;
            }
        }
        /* Price Range Filter Styles */
    .price-filter {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .price-filter h3 {
        font-size: 1.25rem;
        color: var(--black);
        margin-bottom: 1rem;
        position: relative;
        padding-bottom: 0.75rem;
    }

    .price-filter h3::after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        width: 40px;
        height: 3px;
        background: var(--gold);
    }

    .price-inputs {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .price-input-group {
        flex: 1;
        position: relative;
    }

    .price-input-group label {
        display: block;
        font-size: 0.85rem;
        color: var(--gray-dark);
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    .price-input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border: 1px solid var(--gray-medium);
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background-color: var(--white);
        color: var(--black);
    }

    .price-input:focus {
        border-color: var(--gold);
        box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        outline: none;
    }

    .price-input::placeholder {
        color: var(--gray-medium);
    }

    .price-currency {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gold);
        font-weight: 600;
        pointer-events: none;
    }

    .price-separator {
        color: var(--gray-dark);
        font-weight: 500;
        padding: 0 0.5rem;
    }

    .price-filter-button {
        padding: 0.75rem 1.5rem;
        background: var(--gold);
        color: var(--white);
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        align-self: flex-start;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .price-filter-button:hover {
        background: var(--gold-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
    }

    .price-filter-button i {
        font-size: 0.9rem;
    }

    /* Price Range Slider Alternative */
    .price-slider-container {
        margin-top: 1rem;
    }

    .price-slider {
        width: 100%;
        height: 6px;
        background: var(--gray-medium);
        border-radius: 3px;
        margin: 1.5rem 0;
        position: relative;
    }

    .price-slider .track {
        height: 100%;
        background: var(--gold);
        border-radius: 3px;
        position: absolute;
    }

    .price-slider .thumb {
        width: 20px;
        height: 20px;
        background: var(--white);
        border: 2px solid var(--gold);
        border-radius: 50%;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .price-slider .thumb:hover {
        transform: translateY(-50%) scale(1.1);
    }

    .price-slider-values {
        display: flex;
        justify-content: space-between;
        font-size: 0.9rem;
        color: var(--gray-dark);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .price-inputs {
            flex-direction: column;
            gap: 1rem;
        }

        .price-input-group {
            width: 100%;
        }

        .price-separator {
            display: none;
        }

        .price-filter-button {
            width: 100%;
            justify-content: center;
        }
    }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Store Hero -->
    <section class="store-hero">
        <div class="container">
            <div class="hero-content">
                <h1>Travel Store</h1>
                <p>Discover premium travel gear, authentic souvenirs, and exclusive merchandise to enhance your adventures</p>
                
                <div class="hero-stats">
                    <div class="stat">
                        <span class="stat-number"><?php echo number_format($total_count); ?>+</span>
                        <span class="stat-label">Products</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">50+</span>
                        <span class="stat-label">Countries</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">4.8</span>
                        <span class="stat-label">Rating</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Featured Products -->
    <?php if (!empty($featured_products)): ?>
    <section class="featured-products">
        <div class="container">
            <div class="section-header">
                <h2>Featured Products</h2>
                <p>Handpicked items from our collection</p>
            </div>
            
            <div class="products-carousel">
                <?php foreach ($featured_products as $product): ?>
                <div class="product-card featured">
                    <div class="product-image">
                        <?php if ($product['featured_image']): ?>
                            <img src="<?php echo htmlspecialchars($product['featured_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <img src="/placeholder.svg?height=250&width=250&text=<?php echo urlencode($product['name']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php endif; ?>
                        
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                            <div class="product-badge sale">
                                <?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>% OFF
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-actions">
                            <button class="btn-icon" onclick="addToWishlist(<?php echo $product['id']; ?>)">
                                <i class="far fa-heart"></i>
                            </button>
                            <button class="btn-icon" onclick="quickView(<?php echo $product['id']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="product-info">
                        <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                        <h3 class="product-name">
                            <a href="product.php?id=<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h3>
                        
                        <div class="product-rating">
                            <?php 
                            $rating = $product['avg_rating'] ?: 4.5;
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="fas fa-star <?php echo $i <= $rating ? 'active' : ''; ?>"></i>
                            <?php endfor; ?>
                            <span>(<?php echo $product['review_count'] ?: rand(10, 50); ?>)</span>
                        </div>
                        
                        <div class="product-price">
                            <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                <span class="price-original"><?php echo formatCurrency($product['price']); ?></span>
                                <span class="price-sale"><?php echo formatCurrency($product['sale_price']); ?></span>
                            <?php else: ?>
                                <span class="price-current"><?php echo formatCurrency($product['price']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <button class="btn btn-primary btn-block add-to-cart" 
                                data-product-id="<?php echo $product['id']; ?>">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Store Content -->
    <section class="store-content">
        <div class="container">
            <div class="store-layout">
                <!-- Sidebar Filters -->
                <aside class="store-sidebar">
                    <div class="filter-section">
                        <h3>Categories</h3>
                        <ul class="category-list">
                            <li><a href="store.php" class="<?php echo !isset($_GET['category']) ? 'active' : ''; ?>">All Products</a></li>
                            <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="store.php?category=<?php echo $category['slug']; ?>" 
                                   class="<?php echo (isset($_GET['category']) && $_GET['category'] === $category['slug']) ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                   <div class="filter-section">
    <h3>Price Range</h3>
    <form class="price-filter" method="GET">
        <?php if (isset($_GET['category'])): ?>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($_GET['category']); ?>">
        <?php endif; ?>
        <?php if (isset($_GET['search'])): ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
        <?php endif; ?>
        
        <div class="price-inputs">
            <div class="price-input-group">
                <label for="min_price">Min</label>
                <span class="price-currency">$</span>
                <input type="number" id="min_price" name="min_price" class="price-input" 
                       placeholder="0" min="0" 
                       value="<?php echo isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : ''; ?>">
            </div>
            
            <span class="price-separator">to</span>
            
            <div class="price-input-group">
                <label for="max_price">Max</label>
                <span class="price-currency">$</span>
                <input type="number" id="max_price" name="max_price" class="price-input" 
                       placeholder="1000" min="0" 
                       value="<?php echo isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : ''; ?>">
            </div>
        </div>
        
        <!-- Optional Slider Alternative -->
        <div class="price-slider-container">
            <div class="price-slider">
                <div class="track"></div>
                <div class="thumb" id="min-thumb"></div>
                <div class="thumb" id="max-thumb"></div>
            </div>
            <div class="price-slider-values">
                <span id="min-value">$0</span>
                <span id="max-value">$1000</span>
            </div>
        </div>
        
        <button type="submit" class="price-filter-button">
            <i class="fas fa-filter"></i> Apply Filter
        </button>
    </form>
</div>
                    
                    <div class="filter-section">
                        <h3>Popular Tags</h3>
                        <div class="tag-cloud">
                            <a href="store.php?search=travel+gear" class="tag">Travel Gear</a>
                            <a href="store.php?search=souvenirs" class="tag">Souvenirs</a>
                            <a href="store.php?search=clothing" class="tag">Clothing</a>
                            <a href="store.php?search=accessories" class="tag">Accessories</a>
                            <a href="store.php?search=books" class="tag">Travel Books</a>
                            <a href="store.php?search=electronics" class="tag">Electronics</a>
                        </div>
                    </div>
                </aside>
                
                <!-- Main Content -->
                <main class="store-main">
                    <div class="store-header">
                        <div class="results-info">
                            <span>Showing <?php echo count($products); ?> of <?php echo $total_count; ?> products</span>
                        </div>
                        
                        <div class="store-controls">
                            <div class="search-box">
                                <form method="GET" class="search-form">
                                    <?php if (isset($_GET['category'])): ?>
                                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($_GET['category']); ?>">
                                    <?php endif; ?>
                                    <input type="text" name="search" placeholder="Search products..." 
                                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                    <button type="submit"><i class="fas fa-search"></i></button>
                                </form>
                            </div>
                            
                            <div class="sort-options">
                                <select onchange="sortProducts(this.value)">
                                    <option value="newest">Newest First</option>
                                    <option value="price_low">Price: Low to High</option>
                                    <option value="price_high">Price: High to Low</option>
                                    <option value="rating">Highest Rated</option>
                                    <option value="popular">Most Popular</option>
                                </select>
                            </div>
                            
                            <div class="view-toggle">
                                <button class="view-btn active" data-view="grid"><i class="fas fa-th"></i></button>
                                <button class="view-btn" data-view="list"><i class="fas fa-list"></i></button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Products Grid -->
                    <div class="products-grid" id="productsGrid">
                        <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if ($product['featured_image']): ?>
                                    <img src="<?php echo htmlspecialchars($product['featured_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <img src="/placeholder.svg?height=250&width=250&text=<?php echo urlencode($product['name']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php endif; ?>
                                
                                <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                    <div class="product-badge sale">
                                        <?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>% OFF
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($product['stock_quantity'] <= 5 && $product['stock_quantity'] > 0): ?>
                                    <div class="product-badge low-stock">Only <?php echo $product['stock_quantity']; ?> left</div>
                                <?php elseif ($product['stock_quantity'] == 0): ?>
                                    <div class="product-badge out-of-stock">Out of Stock</div>
                                <?php endif; ?>
                                
                                <div class="product-actions">
                                    <button class="btn-icon" onclick="addToWishlist(<?php echo $product['id']; ?>)">
                                        <i class="far fa-heart"></i>
                                    </button>
                                    <button class="btn-icon" onclick="quickView(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon" onclick="shareProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-info">
                                <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                <h3 class="product-name">
                                    <a href="product.php?id=<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h3>
                                
                                <p class="product-description">
                                    <?php echo htmlspecialchars(substr($product['short_description'], 0, 100)); ?>...
                                </p>
                                
                                <div class="product-rating">
                                    <?php 
                                    $rating = $product['avg_rating'] ?: 4.5;
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <i class="fas fa-star <?php echo $i <= $rating ? 'active' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span>(<?php echo $product['review_count'] ?: rand(10, 50); ?>)</span>
                                </div>
                                
                                <div class="product-price">
                                    <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                                        <span class="price-original"><?php echo formatCurrency($product['price']); ?></span>
                                        <span class="price-sale"><?php echo formatCurrency($product['sale_price']); ?></span>
                                    <?php else: ?>
                                        <span class="price-current"><?php echo formatCurrency($product['price']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($product['stock_quantity'] > 0): ?>
                                    <button class="btn btn-primary btn-block add-to-cart" 
                                            data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-shopping-cart"></i> Add to Cart
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-block" disabled>
                                        <i class="fas fa-times"></i> Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['category']) ? '&category=' . $_GET['category'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . $_GET['search'] : ''; ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo isset($_GET['category']) ? '&category=' . $_GET['category'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . $_GET['search'] : ''; ?>" 
                               class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['category']) ? '&category=' . $_GET['category'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . $_GET['search'] : ''; ?>" class="page-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </main>
            </div>
        </div>
    </section>
    
    <!-- Shopping Cart Sidebar -->
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h3>Shopping Cart</h3>
            <button class="cart-close" onclick="toggleCart()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="cart-content" id="cartContent">
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Your cart is empty</p>
                <button class="btn btn-primary" onclick="toggleCart()">Continue Shopping</button>
            </div>
        </div>
        
        <div class="cart-footer" id="cartFooter" style="display: none;">
            <div class="cart-total">
                <span>Total: <strong id="cartTotal">$0.00</strong></span>
            </div>
            <div class="cart-actions">
                <button class="btn btn-outline" onclick="viewCart()">View Cart</button>
                <button class="btn btn-primary" onclick="checkout()">Checkout</button>
            </div>
        </div>
    </div>
    
    <!-- Quick View Modal -->
    <div class="modal" id="quickViewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Quick View</h3>
                <button class="modal-close" onclick="closeQuickView()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="quickViewContent">
                <!-- Quick view content will be loaded here -->
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/store.js"></script>
</body>
</html>
