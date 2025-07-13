<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

// Check admin permissions

$page_title = 'Add New Product';

// Get categories
$categories = $db->query("SELECT * FROM store_categories WHERE status = 'active' ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $sku = trim($_POST['sku']);
    $description = trim($_POST['description']);
    $short_description = trim($_POST['short_description']);
    $price = floatval($_POST['price']);
    $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $stock_quantity = intval($_POST['stock_quantity']);
    $min_stock_level = intval($_POST['min_stock_level']);
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';

    // Validate required fields
    $errors = [];
    if (empty($name)) $errors[] = 'Product name is required';
    if (empty($sku)) $errors[] = 'SKU is required';
    if (empty($price)) $errors[] = 'Price is required';
    if ($stock_quantity < 0) $errors[] = 'Stock quantity cannot be negative';

    if (empty($errors)) {
        try {
            // Handle image upload
            $featured_image = null;
            if (!empty($_FILES['featured_image']['name'])) {
                $upload_dir = '../../uploads/products/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                $filename = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_file)) {
                    $featured_image = 'uploads/products/' . $filename;
                }
            }

            // Insert product
            $stmt = $db->prepare("
                INSERT INTO store_products 
                (name, sku, description, short_description, price, sale_price, category_id, stock_quantity, 
                 min_stock_level, weight, featured_image, featured, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $name, $sku, $description, $short_description, $price, $sale_price, $category_id, 
                $stock_quantity, $min_stock_level, $weight, $featured_image, $featured, $status
            ]);
            
            $product_id = $db->lastInsertId();
            
            // Redirect to edit page with success message
            header("Location: edit.php?id=$product_id&success=1");
            exit;
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Reuse styles from index.php */
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

        .admin-container {
            display: flex;
            min-height: 100vh;
            background-color: #f5f7fa;
        }

        .admin-main {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }

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

        /* Form Styles */
        .form-container {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--black-light);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray);
            border-radius: 5px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 2px var(--gold-light);
            outline: none;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
        }

        .image-upload {
            border: 2px dashed var(--gray-dark);
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .image-upload:hover {
            border-color: var(--gold);
        }

        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 15px;
            display: none;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="admin-header">
                <div class="header-content">
                    <h1>Add New Product</h1>
                    <div class="header-actions">
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                    </div>
                </div>
            </div>

            <div class="form-container">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="name">Product Name *</label>
                                <input type="text" id="name" name="name" class="form-control" required 
                                    value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="sku">SKU *</label>
                                <input type="text" id="sku" name="sku" class="form-control" required 
                                    value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="short_description">Short Description</label>
                                <textarea id="short_description" name="short_description" class="form-control"><?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="description">Full Description</label>
                                <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="form-col">
                            <div class="form-group">
                                <label for="featured_image">Featured Image</label>
                                <div class="image-upload" onclick="document.getElementById('featured_image').click()">
                                    <i class="fas fa-cloud-upload-alt fa-2x"></i>
                                    <p>Click to upload product image</p>
                                    <img id="imagePreview" class="image-preview" src="#" alt="Preview">
                                    <input type="file" id="featured_image" name="featured_image" accept="image/*" style="display: none;" onchange="previewImage(this)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select id="category_id" name="category_id" class="form-control">
                                    <option value="">Uncategorized</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                            <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-col">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="out_of_stock" <?php echo (isset($_POST['status']) && $_POST['status'] === 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="price">Price *</label>
                                <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required 
                                    value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-col">
                            <div class="form-group">
                                <label for="sale_price">Sale Price</label>
                                <input type="number" id="sale_price" name="sale_price" class="form-control" step="0.01" min="0" 
                                    value="<?php echo htmlspecialchars($_POST['sale_price'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="stock_quantity">Stock Quantity *</label>
                                <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" min="0" required 
                                    value="<?php echo htmlspecialchars($_POST['stock_quantity'] ?? '0'); ?>">
                            </div>
                        </div>

                        <div class="form-col">
                            <div class="form-group">
                                <label for="min_stock_level">Minimum Stock Level</label>
                                <input type="number" id="min_stock_level" name="min_stock_level" class="form-control" min="0" 
                                    value="<?php echo htmlspecialchars($_POST['min_stock_level'] ?? '5'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="weight">Weight (kg)</label>
                                <input type="number" id="weight" name="weight" class="form-control" step="0.01" min="0" 
                                    value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-col">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" id="featured" name="featured" class="form-check-input" 
                                        <?php echo (isset($_POST['featured']) && $_POST['featured']) ? 'checked' : ''; ?>>
                                    <label for="featured">Featured Product</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Product
                        </button>
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function previewImage(input) {
                const preview = document.getElementById('imagePreview');
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }
        </script>
    </div>
</body>
</html>