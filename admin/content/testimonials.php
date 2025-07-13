<?php
require_once '../../../config/config.php';
require_once '../../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('content.testimonials.view');

$page_title = 'Testimonial Management';

// Handle form submissions
if ($_POST && verifyCSRFToken($_POST['csrf_token'])) {
    try {
        $action = $_POST['action'];
        
        if ($action === 'add' && hasPermission('content.testimonials.create')) {
            $name = trim($_POST['name']);
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $rating = (int)$_POST['rating'];
            $status = $_POST['status'];
            
            // Handle image upload
            $image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['image'], UPLOADS_PATH . '/testimonials', ALLOWED_IMAGE_TYPES);
                if ($upload_result['success']) {
                    $image = 'uploads/testimonials/' . $upload_result['filename'];
                } else {
                    throw new Exception($upload_result['message']);
                }
            }
            
            $stmt = $db->prepare("
                INSERT INTO testimonials (name, title, content, rating, image, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name, $title, $content, $rating, $image, $status, $_SESSION['user_id']
            ]);
            
            $auth->logActivity($_SESSION['user_id'], 'testimonial_created', "Created testimonial: $name");
            $success = 'Testimonial created successfully!';
            
        } elseif ($action === 'edit' && hasPermission('content.testimonials.edit')) {
            $testimonial_id = (int)$_POST['testimonial_id'];
            $name = trim($_POST['name']);
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $rating = (int)$_POST['rating'];
            $status = $_POST['status'];
            
            // Get current testimonial data
            $stmt = $db->prepare("SELECT image FROM testimonials WHERE id = ?");
            $stmt->execute([$testimonial_id]);
            $current_testimonial = $stmt->fetch(PDO::FETCH_ASSOC);
            $image = $current_testimonial['image'];
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['image'], UPLOADS_PATH . '/testimonials', ALLOWED_IMAGE_TYPES);
                if ($upload_result['success']) {
                    // Delete old image
                    if ($image && file_exists('../../../' . $image)) {
                        unlink('../../../' . $image);
                    }
                    $image = 'uploads/testimonials/' . $upload_result['filename'];
                } else {
                    throw new Exception($upload_result['message']);
                }
            }
            
            $stmt = $db->prepare("
                UPDATE testimonials SET 
                    name = ?, title = ?, content = ?, rating = ?, image = ?,
                    status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name, $title, $content, $rating, $image, $status, $testimonial_id
            ]);
            
            $auth->logActivity($_SESSION['user_id'], 'testimonial_updated', "Updated testimonial: $name");
            $success = 'Testimonial updated successfully!';
            
        } elseif ($action === 'delete' && hasPermission('content.testimonials.delete')) {
            $testimonial_id = (int)$_POST['testimonial_id'];
            
            // Get testimonial image
            $stmt = $db->prepare("SELECT image FROM testimonials WHERE id = ?");
            $stmt->execute([$testimonial_id]);
            $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);
            $image = $testimonial['image'];
            
            // Delete testimonial
            $stmt = $db->prepare("DELETE FROM testimonials WHERE id = ?");
            $stmt->execute([$testimonial_id]);
            
            // Delete image
            if ($image && file_exists('../../../' . $image)) {
                unlink('../../../' . $image);
            }
            
            $auth->logActivity($_SESSION['user_id'], 'testimonial_deleted', "Deleted testimonial ID: $testimonial_id");
            $success = 'Testimonial deleted successfully!';
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get testimonials
$testimonials = $db->query("
    SELECT *
    FROM testimonials
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .testimonials-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
        }
        
        .testimonial-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .testimonial-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #333;
        }
        
        .testimonial-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .testimonial-item {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }
        
        .testimonial-item:hover {
            background: #f8f9fa;
        }
        
        .testimonial-item:last-child {
            border-bottom: none;
        }
        
        .testimonial-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .testimonial-info {
            flex: 1;
        }
        
        .testimonial-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .testimonial-title {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 10px;
        }
        
        .testimonial-content {
            color: #666;
            line-height: 1.5;
        }
        
        .testimonial-rating {
            color: #ffc107;
            font-size: 0.9em;
        }
        
        .testimonial-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .testimonial-status.active { background: #d4edda; color: #155724; }
        .testimonial-status.inactive { background: #f8d7da; color: #721c24; }
        
        .testimonial-actions a {
            color: var(--admin-primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .testimonial-actions a:hover {
            color: #B8956A;
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
            max-width: 700px;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 1.5em;
            cursor: pointer;
            color: #999;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9em;
            outline: none;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
        }
        
        .form-actions {
            margin-top: 30px;
            text-align: right;
        }
        
        .form-actions button {
            margin-left: 15px;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../../includes/header.php'; ?>
            
            <div class="content">
                <div class="testimonials-container">
                    <div class="testimonial-header">
                        <h2 class="testimonial-title">Testimonial Management</h2>
                        <div class="testimonial-actions">
                            <?php if (hasPermission('content.testimonials.create')): ?>
                            <button onclick="openAddModal()" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Testimonial
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
                    
                    <ul class="testimonial-list">
                        <?php if (empty($testimonials)): ?>
                            <div style="text-align: center; color: #666; padding: 30px;">
                                <i class="fas fa-quote-left" style="font-size: 3em; margin-bottom: 10px;"></i>
                                <p>No testimonials found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($testimonials as $testimonial): ?>
                                <li class="testimonial-item">
                                    <div class="testimonial-image">
                                        <?php if ($testimonial['image']): ?>
                                            <img src="../../../<?php echo htmlspecialchars($testimonial['image']); ?>" alt="<?php echo htmlspecialchars($testimonial['name']); ?>" class="testimonial-image">
                                        <?php else: ?>
                                            <div class="testimonial-image" style="background: #ddd; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-user" style="color: #999;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="testimonial-info">
                                        <div class="testimonial-name"><?php echo htmlspecialchars($testimonial['name']); ?></div>
                                        <div class="testimonial-title"><?php echo htmlspecialchars($testimonial['title']); ?></div>
                                        <div class="testimonial-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $testimonial['rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="testimonial-content"><?php echo htmlspecialchars($testimonial['content']); ?></div>
                                        <div class="testimonial-status <?php echo $testimonial['status']; ?>">
                                            <?php echo ucfirst($testimonial['status']); ?>
                                        </div>
                                        <div class="testimonial-actions">
                                            <?php if (hasPermission('content.testimonials.edit')): ?>
                                            <a href="#" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($testimonial)); ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php endif; ?>
                                            <?php if (hasPermission('content.testimonials.delete')): ?>
                                            <a href="#" onclick="deleteTestimonial(<?php echo $testimonial['id']; ?>, '<?php echo htmlspecialchars($testimonial['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Testimonial Modal -->
    <div id="testimonialModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h3>Add/Edit Testimonial</h3>
            
            <form method="POST" enctype="multipart/form-data" id="testimonialForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="testimonial_id" id="testimonialId">
                
                <div class="form-group">
                    <label for="name">Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="title">Title/Position</label>
                    <input type="text" id="title" name="title" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="content">Testimonial Content <span class="required">*</span></label>
                    <textarea id="content" name="content" class="form-control" rows="5" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="rating">Rating</label>
                    <select id="rating" name="rating" class="form-control">
                        <option value="5">5 Stars</option>
                        <option value="4">4 Stars</option>
                        <option value="3">3 Stars</option>
                        <option value="2">2 Stars</option>
                        <option value="1">1 Star</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="image">Photo</label>
                    <input type="file" id="image" name="image" class="form-control" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Testimonial
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Testimonial';
            document.getElementById('formAction').value = 'add';
            document.getElementById('testimonialForm').reset();
            document.getElementById('testimonialModal').style.display = 'block';
        }
        
        function openEditModal(testimonial) {
            document.getElementById('modalTitle').textContent = 'Edit Testimonial';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('testimonialId').value = testimonial.id;
            
            // Populate form fields
            document.getElementById('name').value = testimonial.name;
            document.getElementById('title').value = testimonial.title;
            document.getElementById('content').value = testimonial.content;
            document.getElementById('rating').value = testimonial.rating;
            document.getElementById('status').value = testimonial.status;
            
            document.getElementById('testimonialModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('testimonialModal').style.display = 'none';
        }
        
        function deleteTestimonial(testimonialId, testimonialName) {
            if (confirm(`Are you sure you want to delete the testimonial from "${testimonialName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="testimonial_id" value="${testimonialId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('testimonialModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
    
    <script src="../../../assets/js/admin.js"></script>
</body>
</html>
