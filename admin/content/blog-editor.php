<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('content.create');

$page_title = 'Advanced Blog Editor';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'])) {
    try {
        $action = $_POST['action'];
        
        if ($action === 'save_draft' || $action === 'publish' || $action === 'schedule') {
            $title = sanitizeInput($_POST['title']);
            $slug = !empty($_POST['slug']) ? sanitizeInput($_POST['slug']) : generateSlug($title);
            $content = $_POST['content'];
            $excerpt = sanitizeInput($_POST['excerpt']);
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $tags = isset($_POST['tags']) ? $_POST['tags'] : [];
            $featured = isset($_POST['featured']) ? 1 : 0;
            $seo_title = sanitizeInput($_POST['seo_title']);
            $seo_description = sanitizeInput($_POST['seo_description']);
            $seo_keywords = sanitizeInput($_POST['seo_keywords']);
            
            // Determine status and publish date
            $status = 'draft';
            $published_at = null;
            
            if ($action === 'publish') {
                $status = 'published';
                $published_at = date('Y-m-d H:i:s');
            } elseif ($action === 'schedule') {
                $status = 'scheduled';
                $published_at = $_POST['scheduled_date'] . ' ' . $_POST['scheduled_time'];
            }
            
            // Handle featured image upload
            $featured_image = null;
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['featured_image'], UPLOADS_PATH . '/blog', ALLOWED_IMAGE_TYPES);
                if ($upload_result['success']) {
                    $featured_image = 'uploads/blog/' . $upload_result['filename'];
                }
            }
            
            // Insert blog post
            $stmt = $db->prepare("
                INSERT INTO blog_posts (title, slug, content, excerpt, category_id, author_id, status, featured, featured_image, seo_title, seo_description, seo_keywords, published_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title, $slug, $content, $excerpt, $category_id, $_SESSION['user_id'],
                $status, $featured, $featured_image, $seo_title, $seo_description, $seo_keywords, $published_at
            ]);
            
            $post_id = $db->lastInsertId();
            
            // Handle tags
            if (!empty($tags)) {
                foreach ($tags as $tag_name) {
                    $tag_name = trim($tag_name);
                    if (!empty($tag_name)) {
                        // Check if tag exists
                        $stmt = $db->prepare("SELECT id FROM blog_tags WHERE name = ?");
                        $stmt->execute([$tag_name]);
                        $tag = $stmt->fetch();
                        
                        if (!$tag) {
                            // Create new tag
                            $stmt = $db->prepare("INSERT INTO blog_tags (name, slug) VALUES (?, ?)");
                            $stmt->execute([$tag_name, generateSlug($tag_name)]);
                            $tag_id = $db->lastInsertId();
                        } else {
                            $tag_id = $tag['id'];
                        }
                        
                        // Link tag to post
                        $stmt = $db->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
                        $stmt->execute([$post_id, $tag_id]);
                    }
                }
            }
            
            $auth->logActivity($_SESSION['user_id'], 'blog_post_created', "Created blog post: $title");
            $success = 'Blog post saved successfully!';
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get categories
$categories = $db->query("SELECT * FROM blog_categories WHERE status = 'active' ORDER BY name")->fetchAll();

// Get popular tags
$popular_tags = $db->query("
    SELECT bt.name, COUNT(bpt.post_id) as usage_count
    FROM blog_tags bt
    LEFT JOIN blog_post_tags bpt ON bt.id = bpt.tag_id
    GROUP BY bt.id
    ORDER BY usage_count DESC
    LIMIT 20
")->fetchAll();

// Get guest posts pending approval
$guest_posts = $db->query("
    SELECT bp.*, u.first_name, u.last_name
    FROM blog_posts bp
    JOIN users u ON bp.author_id = u.id
    WHERE bp.status = 'pending_approval'
    ORDER BY bp.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tagify/4.17.9/tagify.css" rel="stylesheet">
    <style>
        .editor-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            margin-top: 20px;
        }
        
        .main-editor {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .editor-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .sidebar-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .panel-title {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }
        
        .publish-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .schedule-section {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .schedule-section.show {
            display: block;
        }
        
        .image-upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .image-upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(212, 165, 116, 0.05);
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .seo-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .seo-title {
            color: #1a0dab;
            font-size: 1.1em;
            margin-bottom: 5px;
        }
        
        .seo-url {
            color: #006621;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .seo-description {
            color: #545454;
            font-size: 0.9em;
        }
        
        .guest-posts-section {
            margin-top: 30px;
        }
        
        .guest-post-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .guest-post-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .word-count {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .auto-save-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8em;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .auto-save-indicator.show {
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .editor-container {
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
                <div class="content-header">
                    <div class="content-title">
                        <h1>Advanced Blog Editor</h1>
                        <p>Create and manage blog posts with advanced features</p>
                    </div>
                    <div class="content-actions">
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Posts
                        </a>
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
                
                <form method="POST" enctype="multipart/form-data" id="blogForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="formAction" value="save_draft">
                    
                    <div class="editor-container">
                        <!-- Main Editor -->
                        <div class="main-editor">
                            <div class="form-group">
                                <input type="text" name="title" id="postTitle" class="form-control" 
                                       placeholder="Enter your blog post title..." required
                                       style="font-size: 1.5em; font-weight: 600; border: none; padding: 0; margin-bottom: 20px;">
                            </div>
                            
                            <div class="form-group">
                                <label>Permalink:</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span><?php echo SITE_URL; ?>/blog/</span>
                                    <input type="text" name="slug" id="postSlug" class="form-control" style="flex: 1;">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <textarea id="postContent" name="content" class="form-control" rows="20"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Excerpt:</label>
                                <textarea name="excerpt" id="postExcerpt" class="form-control" rows="3" 
                                          placeholder="Brief description of your post..." maxlength="300"></textarea>
                                <div class="character-count" id="excerptCount">0/300 characters</div>
                            </div>
                        </div>
                        
                        <!-- Sidebar -->
                        <div class="editor-sidebar">
                            <!-- Publish Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">Publish</h3>
                                
                                <div class="publish-actions">
                                    <button type="button" class="btn btn-outline" onclick="saveDraft()">
                                        <i class="fas fa-save"></i> Save Draft
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="publishNow()">
                                        <i class="fas fa-globe"></i> Publish Now
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="toggleSchedule()">
                                        <i class="fas fa-clock"></i> Schedule
                                    </button>
                                </div>
                                
                                <div class="schedule-section" id="scheduleSection">
                                    <label>Publish Date:</label>
                                    <input type="date" name="scheduled_date" class="form-control" style="margin-bottom: 10px;">
                                    <input type="time" name="scheduled_time" class="form-control" style="margin-bottom: 10px;">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="schedulePost()">
                                        Schedule Post
                                    </button>
                                </div>
                                
                                <div style="margin-top: 15px;">
                                    <label>
                                        <input type="checkbox" name="featured"> Featured Post
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Category Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">Category</h3>
                                <select name="category_id" class="form-control">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline btn-sm" style="margin-top: 10px;" onclick="addNewCategory()">
                                    <i class="fas fa-plus"></i> Add New Category
                                </button>
                            </div>
                            
                            <!-- Tags Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">Tags</h3>
                                <input type="text" name="tags" id="tagsInput" class="form-control" 
                                       placeholder="Add tags...">
                                
                                <div style="margin-top: 15px;">
                                    <strong>Popular Tags:</strong>
                                    <div style="margin-top: 10px;">
                                        <?php foreach ($popular_tags as $tag): ?>
                                            <button type="button" class="btn btn-outline btn-sm" 
                                                    style="margin: 2px;" onclick="addTag('<?php echo htmlspecialchars($tag['name']); ?>')">
                                                <?php echo htmlspecialchars($tag['name']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Featured Image Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">Featured Image</h3>
                                <div class="image-upload-area" onclick="document.getElementById('featuredImage').click()">
                                    <input type="file" name="featured_image" id="featuredImage" accept="image/*" style="display: none;">
                                    <div id="imagePreview">
                                        <i class="fas fa-cloud-upload-alt" style="font-size: 2em; margin-bottom: 10px;"></i>
                                        <p>Click to upload featured image</p>
                                        <small>Recommended size: 1200x630px</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SEO Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">SEO Settings</h3>
                                
                                <div class="form-group">
                                    <label>SEO Title:</label>
                                    <input type="text" name="seo_title" id="seoTitle" class="form-control" maxlength="60">
                                    <div class="character-count" id="seoTitleCount">0/60 characters</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Meta Description:</label>
                                    <textarea name="seo_description" id="seoDescription" class="form-control" 
                                              rows="3" maxlength="160"></textarea>
                                    <div class="character-count" id="seoDescCount">0/160 characters</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Keywords:</label>
                                    <input type="text" name="seo_keywords" class="form-control" 
                                           placeholder="keyword1, keyword2, keyword3">
                                </div>
                                
                                <div class="seo-preview">
                                    <div class="seo-title" id="previewTitle">Your Post Title</div>
                                    <div class="seo-url" id="previewUrl"><?php echo SITE_URL; ?>/blog/your-post-slug</div>
                                    <div class="seo-description" id="previewDescription">Your meta description will appear here...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Guest Posts Section -->
                <?php if (!empty($guest_posts)): ?>
                <div class="guest-posts-section">
                    <h2>Guest Posts Pending Approval</h2>
                    <?php foreach ($guest_posts as $guest_post): ?>
                        <div class="guest-post-item">
                            <h4><?php echo htmlspecialchars($guest_post['title']); ?></h4>
                            <p>By: <?php echo htmlspecialchars($guest_post['first_name'] . ' ' . $guest_post['last_name']); ?></p>
                            <p><?php echo htmlspecialchars(substr($guest_post['excerpt'], 0, 200)); ?>...</p>
                            <div class="guest-post-actions">
                                <button class="btn btn-success btn-sm" onclick="approveGuestPost(<?php echo $guest_post['id']; ?>)">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="editGuestPost(<?php echo $guest_post['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="rejectGuestPost(<?php echo $guest_post['id']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Auto-save indicator -->
    <div class="auto-save-indicator" id="autoSaveIndicator">
        <i class="fas fa-check"></i> Auto-saved
    </div>
    
    <!-- Word count -->
    <div class="word-count" id="wordCount">
        Words: 0
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tagify/4.17.9/tagify.min.js"></script>
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#postContent',
            height: 500,
            menubar: 'file edit view insert format tools table help',
            plugins: [
                'advlist autolink lists link image charmap print preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste code help wordcount',
                'autoresize template autosave'
            ],
            toolbar: 'undo redo | formatselect | bold italic backcolor | \
                      alignleft aligncenter alignright alignjustify | \
                      bullist numlist outdent indent | removeformat | help | image | template',
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
            autosave_interval: '30s',
            autosave_prefix: 'blog_post_',
            autosave_restore_when_empty: true,
            setup: function (editor) {
                editor.on('keyup', function () {
                    updateWordCount();
                    autoSave();
                });
            }
        });
        
        // Initialize Tagify
        const tagsInput = document.querySelector('#tagsInput');
        const tagify = new Tagify(tagsInput, {
            whitelist: <?php echo json_encode(array_column($popular_tags, 'name')); ?>,
            dropdown: {
                maxItems: 20,
                classname: "tags-look",
                enabled: 0,
                closeOnSelect: false
            }
        });
        
        // Auto-generate slug
        document.getElementById('postTitle').addEventListener('input', function() {
            const title = this.value;
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9 -]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim('-');
            document.getElementById('postSlug').value = slug;
            updateSEOPreview();
        });
        
        // Character counters
        function setupCharacterCounter(inputId, counterId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            
            input.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = length + '/' + maxLength + ' characters';
                
                if (length > maxLength * 0.9) {
                    counter.style.color = '#dc3545';
                } else if (length > maxLength * 0.7) {
                    counter.style.color = '#ffc107';
                } else {
                    counter.style.color = '#666';
                }
                
                updateSEOPreview();
            });
        }
        
        setupCharacterCounter('postExcerpt', 'excerptCount', 300);
        setupCharacterCounter('seoTitle', 'seoTitleCount', 60);
        setupCharacterCounter('seoDescription', 'seoDescCount', 160);
        
        // SEO Preview
        function updateSEOPreview() {
            const title = document.getElementById('seoTitle').value || document.getElementById('postTitle').value || 'Your Post Title';
            const slug = document.getElementById('postSlug').value || 'your-post-slug';
            const description = document.getElementById('seoDescription').value || 'Your meta description will appear here...';
            
            document.getElementById('previewTitle').textContent = title;
            document.getElementById('previewUrl').textContent = '<?php echo SITE_URL; ?>/blog/' + slug;
            document.getElementById('previewDescription').textContent = description;
        }
        
        // Word count
        function updateWordCount() {
            const content = tinymce.get('postContent').getContent({format: 'text'});
            const words = content.trim().split(/\s+/).length;
            document.getElementById('wordCount').textContent = 'Words: ' + words;
        }
        
        // Auto-save
        let autoSaveTimeout;
        function autoSave() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                // Implement auto-save logic here
                showAutoSaveIndicator();
            }, 2000);
        }
        
        function showAutoSaveIndicator() {
            const indicator = document.getElementById('autoSaveIndicator');
            indicator.classList.add('show');
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
        }
        
        // Publish actions
        function saveDraft() {
            document.getElementById('formAction').value = 'save_draft';
            document.getElementById('blogForm').submit();
        }
        
        function publishNow() {
            document.getElementById('formAction').value = 'publish';
            document.getElementById('blogForm').submit();
        }
        
        function toggleSchedule() {
            const section = document.getElementById('scheduleSection');
            section.classList.toggle('show');
        }
        
        function schedulePost() {
            document.getElementById('formAction').value = 'schedule';
            document.getElementById('blogForm').submit();
        }
        
        // Tag functions
        function addTag(tagName) {
            tagify.addTags([tagName]);
        }
        
        function addNewCategory() {
            const categoryName = prompt('Enter new category name:');
            if (categoryName) {
                // Add new category via AJAX
                fetch('../api/add-category.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        name: categoryName,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error adding category');
                    }
                });
            }
        }
        
        // Guest post functions
        function approveGuestPost(postId) {
            if (confirm('Approve this guest post?')) {
                fetch('../api/approve-guest-post.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        post_id: postId,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error approving post');
                    }
                });
            }
        }
        
        function rejectGuestPost(postId) {
            const reason = prompt('Reason for rejection:');
            if (reason) {
                fetch('../api/reject-guest-post.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        post_id: postId,
                        reason: reason,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error rejecting post');
                    }
                });
            }
        }
        
        function editGuestPost(postId) {
            window.location.href = 'edit.php?id=' + postId;
        }
        
        // Image upload preview
        document.getElementById('featuredImage').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').innerHTML = 
                        '<img src="' + e.target.result + '" class="image-preview" alt="Preview">';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Initialize
        updateSEOPreview();
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
