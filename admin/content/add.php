<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('blog.create');

$page_title = 'Add New Blog Post';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'])) {
    try {
        // Validate and sanitize inputs
        $title = sanitizeInput($_POST['title']);
        $slug = !empty($_POST['slug']) ? sanitizeInput($_POST['slug']) : generateSlug($title);
        $excerpt = sanitizeInput($_POST['excerpt']);
        $content = $_POST['content']; // JSON content from block editor
        $content_type = $_POST['content_type'] ?? 'blocks'; // blocks, markdown, or html
        $category_id = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
        $status = in_array($_POST['status'], ['draft', 'published', 'scheduled']) ? $_POST['status'] : 'draft';
        $featured = isset($_POST['featured']) ? 1 : 0;
        $seo_title = sanitizeInput($_POST['seo_title']);
        $seo_description = sanitizeInput($_POST['seo_description']);
        $seo_keywords = sanitizeInput($_POST['seo_keywords']);
        $tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];
        $region = sanitizeInput($_POST['region']);
        $post_type = $_POST['post_type'] ?? 'blog'; // blog, travel_tip, destination_guide, itinerary

        // Validate required fields
        if (empty($title)) {
            throw new Exception("Title is required");
        }

        if (empty($content)) {
            throw new Exception("Content is required");
        }

        // Handle featured image upload
        $featured_image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = UPLOADS_PATH . '/blog/';

            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Validate image
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $_FILES['featured_image']['tmp_name']);
            finfo_close($file_info);

            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Invalid image type. Only JPG, PNG, GIF, and WebP are allowed.");
            }

            // Generate unique filename
            $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_path)) {
                $featured_image = 'uploads/blog/' . $filename;
            } else {
                throw new Exception("Failed to upload featured image");
            }
        }

        // Handle gallery images
        $gallery_images = [];
        if (isset($_FILES['gallery_images'])) {
            foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $ext;
                    $target_path = $upload_dir . $filename;

                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $gallery_images[] = 'uploads/blog/' . $filename;
                    }
                }
            }
        }

        // Process content based on type
        if ($content_type === 'blocks') {
            // Content is already in JSON format from block editor
            $processed_content = $content;
        } elseif ($content_type === 'markdown') {
            // Convert markdown to HTML
            $processed_content = markdownToHtml($content);
        } else {
            // HTML content
            $processed_content = $content;
        }
        function calculateReadingTime($text)
        {
            $words_per_minute = 200; // average adult reading speed
            $word_count = str_word_count(strip_tags($text));
            $minutes = ceil($word_count / $words_per_minute);
            return $minutes;
        }
        // Calculate reading time
        $reading_time = calculateReadingTime($processed_content);



        // Prepare published_at date
        $published_at = null;
        if ($status === 'published') {
            $published_at = date('Y-m-d H:i:s');
        } elseif ($status === 'scheduled' && !empty($_POST['scheduled_date'])) {
            $published_at = $_POST['scheduled_date'] . ' ' . ($_POST['scheduled_time'] ?? '09:00:00');
        }

        // Insert into database
        $stmt = $db->prepare("
            INSERT INTO blog_posts (
                title, slug, content, content_type, excerpt, category_id, author_id, 
                status, featured, featured_image, gallery_images, reading_time, 
                seo_title, seo_description, seo_keywords, published_at, region, post_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $title,
            $slug,
            $processed_content,
            $content_type,
            $excerpt,
            $category_id,
            $_SESSION['user_id'],
            $status,
            $featured,
            $featured_image,
            json_encode($gallery_images),
            $reading_time,
            $seo_title,
            $seo_description,
            $seo_keywords,
            $published_at,
            $region,
            $post_type
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

        // Log activity
        $auth->logActivity($_SESSION['user_id'], 'blog_post_created', "Created blog post: $title");

        // Redirect to edit page with success message
        $_SESSION['success_message'] = 'Blog post created successfully!';
        header("Location: edit.php?id=$post_id");
        exit;

    } catch (Exception $e) {
        $error = 'Error creating blog post: ' . $e->getMessage();
    }
}

// Get categories
$categories = $db->query("SELECT id, name FROM blog_categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get regions
$regions = ['Africa', 'Asia', 'Europe', 'North America', 'South America', 'Oceania', 'Middle East'];

// Get popular tags
$popular_tags = $db->query("
    SELECT bt.name, COUNT(bpt.post_id) as usage_count
    FROM blog_tags bt
    LEFT JOIN blog_post_tags bpt ON bt.id = bpt.tag_id
    GROUP BY bt.id
    ORDER BY usage_count DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet">
    <style>
        .content-editor-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            margin-top: 20px;
        }

        .main-editor {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
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
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .panel-title {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }

        /* Content Editor Styles */
        .editor-mode-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .mode-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mode-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .editor-content {
            min-height: 400px;
        }

        /* Block Editor Styles */
        .block-editor {
            border: 1px solid #ddd;
            border-radius: 8px;
            min-height: 500px;
            padding: 20px;
        }

        .block-toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            flex-wrap: wrap;
        }

        .block-btn {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .block-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .content-blocks {
            min-height: 400px;
        }

        .content-block {
            margin-bottom: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            position: relative;
        }

        .content-block:hover {
            border-color: var(--primary-color);
        }

        .block-controls {
            position: absolute;
            top: -10px;
            right: 10px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .content-block:hover .block-controls {
            opacity: 1;
        }

        .block-control-btn {
            width: 24px;
            height: 24px;
            border: none;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            cursor: pointer;
            font-size: 0.8em;
        }

        /* Markdown Editor Styles */
        .markdown-editor {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: 500px;
        }

        .markdown-input {
            width: 100%;
            height: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            resize: none;
        }

        .markdown-preview {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #f9f9f9;
            overflow-y: auto;
        }

        /* Visual Editor Styles */
        .visual-editor {
            border: 1px solid #ddd;
            border-radius: 8px;
            min-height: 500px;
        }

        .visual-toolbar {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            background: #f8f9fa;
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .visual-btn {
            padding: 6px 10px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .visual-btn:hover {
            background: #e9ecef;
        }

        .visual-content {
            padding: 15px;
            min-height: 400px;
            outline: none;
        }

        /* Image Upload Styles */
        .image-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .image-upload-area:hover {
            border-color: var(--primary-color);
            background-color: rgba(212, 165, 116, 0.05);
        }

        .image-upload-area.has-image {
            border: none;
            padding: 0;
            background: transparent;
        }

        .image-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 5px;
        }

        .gallery-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .gallery-item {
            position: relative;
            border-radius: 5px;
            overflow: hidden;
        }

        .gallery-item img {
            width: 100%;
            height: 80px;
            object-fit: cover;
        }

        .gallery-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
            font-size: 0.8em;
        }

        /* Post Type Styles */
        .post-type-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .post-type-option {
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .post-type-option:hover {
            border-color: var(--primary-color);
        }

        .post-type-option.selected {
            border-color: var(--primary-color);
            background: rgba(212, 165, 116, 0.1);
        }

        .post-type-icon {
            font-size: 2em;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        /* Character counters */
        .character-count {
            font-size: 0.8em;
            color: #666;
            text-align: right;
            margin-top: 5px;
        }

        .character-count.warning {
            color: #ffc107;
        }

        .character-count.danger {
            color: #dc3545;
        }

        /* SEO Preview */
        .seo-preview {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            background: #f9f9f9;
        }

        .seo-title {
            color: #1a0dab;
            font-size: 1.1em;
            margin-bottom: 5px;
            cursor: pointer;
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

        /* Tags Input */
        .tags-input {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-height: 40px;
            cursor: text;
        }

        .tag-item {
            background: var(--primary-color);
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .tag-remove {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 0.8em;
        }

        .tag-input {
            border: none;
            outline: none;
            flex: 1;
            min-width: 100px;
        }

        .popular-tags {
            margin-top: 10px;
        }

        .popular-tag {
            display: inline-block;
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 4px 8px;
            margin: 2px;
            border-radius: 15px;
            font-size: 0.8em;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .popular-tag:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-editor-container {
                grid-template-columns: 1fr;
            }

            .markdown-editor {
                grid-template-columns: 1fr;
                height: auto;
            }

            .markdown-input {
                height: 300px;
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
                        <h2>Create New Content</h2>
                        <p>Choose your content type and start creating</p>
                    </div>
                    <div class="content-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Posts
                        </a>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="contentForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="content_type" id="contentType" value="blocks">
                    <input type="hidden" name="content" id="contentData">

                    <div class="content-editor-container">
                        <!-- Main Editor -->
                        <div class="main-editor">
                            <!-- Post Type Selection -->
                            <div class="form-group">
                                <label class="form-label">Content Type</label>
                                <div class="post-type-selector">
                                    <div class="post-type-option selected" data-type="blog">
                                        <div class="post-type-icon"><i class="fas fa-blog"></i></div>
                                        <div>Blog Post</div>
                                    </div>
                                    <div class="post-type-option" data-type="travel_tip">
                                        <div class="post-type-icon"><i class="fas fa-lightbulb"></i></div>
                                        <div>Travel Tip</div>
                                    </div>
                                    <div class="post-type-option" data-type="destination_guide">
                                        <div class="post-type-icon"><i class="fas fa-map-marked-alt"></i></div>
                                        <div>Destination Guide</div>
                                    </div>
                                    <div class="post-type-option" data-type="itinerary">
                                        <div class="post-type-icon"><i class="fas fa-route"></i></div>
                                        <div>Itinerary</div>
                                    </div>
                                </div>
                                <input type="hidden" name="post_type" id="postType" value="blog">
                            </div>

                            <div class="form-group">
                                <input type="text" name="title" id="postTitle" class="form-control" required
                                    placeholder="Enter your content title..."
                                    style="font-size: 1.5em; font-weight: 600; border: none; padding: 0; margin-bottom: 20px;">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Permalink</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span><?php echo SITE_URL; ?>/blog/</span>
                                    <input type="text" name="slug" id="postSlug" class="form-control" style="flex: 1;">
                                </div>
                            </div>

                            <!-- Editor Mode Selector -->
                            <div class="editor-mode-selector">
                                <button type="button" class="mode-btn active" data-mode="blocks">
                                    <i class="fas fa-th-large"></i> Block Editor
                                </button>
                                <button type="button" class="mode-btn" data-mode="markdown">
                                    <i class="fab fa-markdown"></i> Markdown
                                </button>
                                <button type="button" class="mode-btn" data-mode="visual">
                                    <i class="fas fa-edit"></i> Visual Editor
                                </button>
                            </div>

                            <!-- Content Editor -->
                            <div class="editor-content">
                                <!-- Block Editor -->
                                <div id="blockEditor" class="block-editor">
                                    <div class="block-toolbar">
                                        <button type="button" class="block-btn" data-block="paragraph">
                                            <i class="fas fa-paragraph"></i> Paragraph
                                        </button>
                                        <button type="button" class="block-btn" data-block="heading">
                                            <i class="fas fa-heading"></i> Heading
                                        </button>
                                        <button type="button" class="block-btn" data-block="image">
                                            <i class="fas fa-image"></i> Image
                                        </button>
                                        <button type="button" class="block-btn" data-block="gallery">
                                            <i class="fas fa-images"></i> Gallery
                                        </button>
                                        <button type="button" class="block-btn" data-block="quote">
                                            <i class="fas fa-quote-left"></i> Quote
                                        </button>
                                        <button type="button" class="block-btn" data-block="list">
                                            <i class="fas fa-list"></i> List
                                        </button>
                                        <button type="button" class="block-btn" data-block="code">
                                            <i class="fas fa-code"></i> Code
                                        </button>
                                        <button type="button" class="block-btn" data-block="video">
                                            <i class="fas fa-video"></i> Video
                                        </button>
                                        <button type="button" class="block-btn" data-block="map">
                                            <i class="fas fa-map"></i> Map
                                        </button>
                                    </div>
                                    <div class="content-blocks" id="contentBlocks">
                                        <div class="content-block" data-type="paragraph">
                                            <div class="block-controls">
                                                <button type="button" class="block-control-btn"
                                                    onclick="moveBlockUp(this)">
                                                    <i class="fas fa-arrow-up"></i>
                                                </button>
                                                <button type="button" class="block-control-btn"
                                                    onclick="moveBlockDown(this)">
                                                    <i class="fas fa-arrow-down"></i>
                                                </button>
                                                <button type="button" class="block-control-btn"
                                                    onclick="deleteBlock(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            <div contenteditable="true" class="block-content"
                                                placeholder="Start writing..."></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Markdown Editor -->
                                <div id="markdownEditor" class="markdown-editor" style="display: none;">
                                    <textarea id="markdownInput" class="markdown-input" placeholder="# Your Title

Write your content in Markdown...

## Subheading

- List item 1
- List item 2

**Bold text** and *italic text*

[Link text](https://example.com)

![Image alt text](image-url.jpg)"></textarea>
                                    <div id="markdownPreview" class="markdown-preview">
                                        <p>Preview will appear here...</p>
                                    </div>
                                </div>

                                <!-- Visual Editor -->
                                <div id="visualEditor" class="visual-editor" style="display: none;">
                                    <div class="visual-toolbar">
                                        <button type="button" class="visual-btn" onclick="formatText('bold')">
                                            <i class="fas fa-bold"></i>
                                        </button>
                                        <button type="button" class="visual-btn" onclick="formatText('italic')">
                                            <i class="fas fa-italic"></i>
                                        </button>
                                        <button type="button" class="visual-btn" onclick="formatText('underline')">
                                            <i class="fas fa-underline"></i>
                                        </button>
                                        <button type="button" class="visual-btn"
                                            onclick="formatText('insertOrderedList')">
                                            <i class="fas fa-list-ol"></i>
                                        </button>
                                        <button type="button" class="visual-btn"
                                            onclick="formatText('insertUnorderedList')">
                                            <i class="fas fa-list-ul"></i>
                                        </button>
                                        <button type="button" class="visual-btn" onclick="insertLink()">
                                            <i class="fas fa-link"></i>
                                        </button>
                                        <button type="button" class="visual-btn" onclick="insertImage()">
                                            <i class="fas fa-image"></i>
                                        </button>
                                    </div>
                                    <div id="visualContent" class="visual-content" contenteditable="true">
                                        <p>Start writing your content here...</p>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Excerpt</label>
                                <textarea name="excerpt" id="postExcerpt" class="form-control" rows="3"
                                    placeholder="Brief description of your content..." maxlength="300"></textarea>
                                <div class="character-count" id="excerptCount">0/300 characters</div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div class="editor-sidebar">
                            <!-- Publish Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">Publish</h3>

                                <div class="form-group">
                                    <label>Status:</label>
                                    <select name="status" class="form-control">
                                        <option value="draft">Draft</option>
                                        <option value="published">Publish Now</option>
                                        <option value="scheduled">Schedule</option>
                                    </select>
                                </div>

                                <div class="form-group" id="scheduleFields" style="display: none;">
                                    <label>Publish Date:</label>
                                    <input type="date" name="scheduled_date" class="form-control"
                                        style="margin-bottom: 10px;">
                                    <input type="time" name="scheduled_time" class="form-control" value="09:00">
                                </div>

                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="featured"> Featured Content
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-save"></i> Save Content
                                </button>
                            </div>

                            <!-- Category & Region Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">Classification</h3>

                                <div class="form-group">
                                    <label>Category:</label>
                                    <select name="category_id" class="form-control">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Region:</label>
                                    <select name="region" class="form-control">
                                        <option value="">Select Region</option>
                                        <?php foreach ($regions as $region): ?>
                                            <option value="<?php echo $region; ?>"><?php echo $region; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Tags Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">Tags</h3>

                                <div class="tags-input" id="tagsInput">
                                    <input type="text" class="tag-input" placeholder="Add tags..." id="tagInputField">
                                </div>
                                <input type="hidden" name="tags" id="tagsData">

                                <div class="popular-tags">
                                    <strong>Popular Tags:</strong><br>
                                    <?php foreach ($popular_tags as $tag): ?>
                                        <span class="popular-tag"
                                            onclick="addTag('<?php echo htmlspecialchars($tag['name']); ?>')">
                                            <?php echo htmlspecialchars($tag['name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Featured Image Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">Featured Image</h3>

                                <div class="image-upload-area"
                                    onclick="document.getElementById('featuredImage').click()">
                                    <input type="file" name="featured_image" id="featuredImage" accept="image/*"
                                        style="display: none;">
                                    <div id="imagePreview">
                                        <i class="fas fa-cloud-upload-alt"
                                            style="font-size: 2em; margin-bottom: 10px;"></i>
                                        <p>Click to upload featured image</p>
                                        <small>Recommended size: 1200x630px</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Gallery Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">Image Gallery</h3>

                                <input type="file" name="gallery_images[]" id="galleryImages" accept="image/*" multiple
                                    style="display: none;">
                                <button type="button" class="btn btn-outline btn-block"
                                    onclick="document.getElementById('galleryImages').click()">
                                    <i class="fas fa-images"></i> Add Gallery Images
                                </button>

                                <div class="gallery-images" id="galleryPreview"></div>
                            </div>

                            <!-- SEO Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">SEO Settings</h3>

                                <div class="form-group">
                                    <label>SEO Title:</label>
                                    <input type="text" name="seo_title" id="seoTitle" class="form-control"
                                        maxlength="60">
                                    <div class="character-count" id="seoTitleCount">0/60 characters</div>
                                </div>

                                <div class="form-group">
                                    <label>Meta Description:</label>
                                    <textarea name="seo_description" id="seoDescription" class="form-control" rows="3"
                                        maxlength="160"></textarea>
                                    <div class="character-count" id="seoDescCount">0/160 characters</div>
                                </div>

                                <div class="form-group">
                                    <label>Keywords:</label>
                                    <input type="text" name="seo_keywords" class="form-control"
                                        placeholder="keyword1, keyword2, keyword3">
                                </div>

                                <div class="seo-preview">
                                    <div class="seo-title" id="previewTitle">Your Content Title</div>
                                    <div class="seo-url" id="previewUrl"><?php echo SITE_URL; ?>/blog/your-content-slug
                                    </div>
                                    <div class="seo-description" id="previewDescription">Your meta description will
                                        appear here...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.3.0/marked.min.js"></script>
    <script>
        // Global variables
        let currentMode = 'blocks';
        let contentBlocks = [];
        let tags = [];

        // Initialize
        document.addEventListener('DOMContentLoaded', function () {
            initializeEditor();
            setupEventListeners();
        });

        function initializeEditor() {
            // Initialize with a default paragraph block
            contentBlocks = [{
                type: 'paragraph',
                content: ''
            }];
            updateBlockEditor();
        }

        function setupEventListeners() {
            // Post type selection
            document.querySelectorAll('.post-type-option').forEach(option => {
                option.addEventListener('click', function () {
                    document.querySelectorAll('.post-type-option').forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    document.getElementById('postType').value = this.dataset.type;
                });
            });

            // Editor mode switching
            document.querySelectorAll('.mode-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    switchEditorMode(this.dataset.mode);
                });
            });

            // Block toolbar buttons
            document.querySelectorAll('.block-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    addBlock(this.dataset.block);
                });
            });

            // Title to slug generation
            document.getElementById('postTitle').addEventListener('input', function () {
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
            setupCharacterCounter('postExcerpt', 'excerptCount', 300);
            setupCharacterCounter('seoTitle', 'seoTitleCount', 60);
            setupCharacterCounter('seoDescription', 'seoDescCount', 160);

            // Status change handler
            document.querySelector('select[name="status"]').addEventListener('change', function () {
                const scheduleFields = document.getElementById('scheduleFields');
                if (this.value === 'scheduled') {
                    scheduleFields.style.display = 'block';
                } else {
                    scheduleFields.style.display = 'none';
                }
            });

            // Tag input
            document.getElementById('tagInputField').addEventListener('keypress', function (e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    const tagName = this.value.trim();
                    if (tagName) {
                        addTag(tagName);
                        this.value = '';
                    }
                }
            });

            // Markdown editor
            document.getElementById('markdownInput').addEventListener('input', function () {
                updateMarkdownPreview();
            });

            // Image uploads
            document.getElementById('featuredImage').addEventListener('change', handleFeaturedImageUpload);
            document.getElementById('galleryImages').addEventListener('change', handleGalleryImagesUpload);

            // Form submission
            document.getElementById('contentForm').addEventListener('submit', function (e) {
                prepareContentForSubmission();
            });
        }

        function switchEditorMode(mode) {
            // Update active button
            document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-mode="${mode}"]`).classList.add('active');

            // Hide all editors
            document.getElementById('blockEditor').style.display = 'none';
            document.getElementById('markdownEditor').style.display = 'none';
            document.getElementById('visualEditor').style.display = 'none';

            // Show selected editor
            currentMode = mode;
            document.getElementById('contentType').value = mode;

            switch (mode) {
                case 'blocks':
                    document.getElementById('blockEditor').style.display = 'block';
                    break;
                case 'markdown':
                    document.getElementById('markdownEditor').style.display = 'block';
                    updateMarkdownPreview();
                    break;
                case 'visual':
                    document.getElementById('visualEditor').style.display = 'block';
                    break;
            }
        }

        // Block Editor Functions
        function addBlock(type) {
            const block = {
                type: type,
                content: '',
                id: Date.now()
            };
            contentBlocks.push(block);
            updateBlockEditor();
        }

        function updateBlockEditor() {
            const container = document.getElementById('contentBlocks');
            container.innerHTML = '';

            contentBlocks.forEach((block, index) => {
                const blockElement = createBlockElement(block, index);
                container.appendChild(blockElement);
            });
        }

        function createBlockElement(block, index) {
            const div = document.createElement('div');
            div.className = 'content-block';
            div.dataset.type = block.type;
            div.dataset.index = index;

            let content = '';
            switch (block.type) {
                case 'paragraph':
                    content = `<div contenteditable="true" class="block-content" placeholder="Write your paragraph...">${block.content}</div>`;
                    break;
                case 'heading':
                    content = `
                        <select class="form-control" style="margin-bottom: 10px;" onchange="updateBlockData(${index}, 'level', this.value)">
                            <option value="h2" ${block.level === 'h2' ? 'selected' : ''}>Heading 2</option>
                            <option value="h3" ${block.level === 'h3' ? 'selected' : ''}>Heading 3</option>
                            <option value="h4" ${block.level === 'h4' ? 'selected' : ''}>Heading 4</option>
                        </select>
                        <div contenteditable="true" class="block-content" placeholder="Enter heading text...">${block.content}</div>
                    `;
                    break;
                case 'image':
                    content = `
                        <input type="file" accept="image/*" onchange="handleBlockImageUpload(this, ${index})" style="margin-bottom: 10px;">
                        <input type="text" class="form-control" placeholder="Image caption..." value="${block.caption || ''}" onchange="updateBlockData(${index}, 'caption', this.value)" style="margin-bottom: 10px;">
                        ${block.src ? `<img src="${block.src}" style="max-width: 100%; height: auto;">` : '<p>No image selected</p>'}
                    `;
                    break;
                case 'quote':
                    content = `
                        <div contenteditable="true" class="block-content" placeholder="Enter quote text..." style="font-style: italic; border-left: 4px solid var(--primary-color); padding-left: 15px;">${block.content}</div>
                        <input type="text" class="form-control" placeholder="Quote author..." value="${block.author || ''}" onchange="updateBlockData(${index}, 'author', this.value)" style="margin-top: 10px;">
                    `;
                    break;
                case 'list':
                    content = `
                        <select class="form-control" style="margin-bottom: 10px;" onchange="updateBlockData(${index}, 'listType', this.value)">
                            <option value="ul" ${block.listType === 'ul' ? 'selected' : ''}>Unordered List</option>
                            <option value="ol" ${block.listType === 'ol' ? 'selected' : ''}>Ordered List</option>
                        </select>
                        <div contenteditable="true" class="block-content" placeholder="Enter list items (one per line)...">${block.content}</div>
                    `;
                    break;
                case 'code':
                    content = `
                        <input type="text" class="form-control" placeholder="Language (e.g., javascript, php)..." value="${block.language || ''}" onchange="updateBlockData(${index}, 'language', this.value)" style="margin-bottom: 10px;">
                        <textarea class="form-control" placeholder="Enter your code..." style="font-family: monospace;" onchange="updateBlockData(${index}, 'content', this.value)">${block.content}</textarea>
                    `;
                    break;
                case 'video':
                    content = `
                        <input type="text" class="form-control" placeholder="Video URL (YouTube, Vimeo, etc.)..." value="${block.url || ''}" onchange="updateBlockData(${index}, 'url', this.value)" style="margin-bottom: 10px;">
                        <input type="text" class="form-control" placeholder="Video caption..." value="${block.caption || ''}" onchange="updateBlockData(${index}, 'caption', this.value)">
                    `;
                    break;
                case 'map':
                    content = `
                        <input type="text" class="form-control" placeholder="Location or coordinates..." value="${block.location || ''}" onchange="updateBlockData(${index}, 'location', this.value)" style="margin-bottom: 10px;">
                        <input type="number" class="form-control" placeholder="Zoom level (1-20)..." value="${block.zoom || 10}" onchange="updateBlockData(${index}, 'zoom', this.value)">
                    `;
                    break;
            }

            div.innerHTML = `
                <div class="block-controls">
                    <button type="button" class="block-control-btn" onclick="moveBlockUp(${index})">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                    <button type="button" class="block-control-btn" onclick="moveBlockDown(${index})">
                        <i class="fas fa-arrow-down"></i>
                    </button>
                    <button type="button" class="block-control-btn" onclick="deleteBlock(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                ${content}
            `;

            // Add event listeners for contenteditable elements
            const editableElements = div.querySelectorAll('[contenteditable="true"]');
            editableElements.forEach(element => {
                element.addEventListener('input', function () {
                    updateBlockData(index, 'content', this.innerHTML);
                });
            });

            return div;
        }

        function updateBlockData(index, property, value) {
            if (contentBlocks[index]) {
                contentBlocks[index][property] = value;
            }
        }

        function moveBlockUp(index) {
            if (index > 0) {
                [contentBlocks[index], contentBlocks[index - 1]] = [contentBlocks[index - 1], contentBlocks[index]];
                updateBlockEditor();
            }
        }

        function moveBlockDown(index) {
            if (index < contentBlocks.length - 1) {
                [contentBlocks[index], contentBlocks[index + 1]] = [contentBlocks[index + 1], contentBlocks[index]];
                updateBlockEditor();
            }
        }

        function deleteBlock(index) {
            if (contentBlocks.length > 1) {
                contentBlocks.splice(index, 1);
                updateBlockEditor();
            }
        }

        function handleBlockImageUpload(input, index) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    updateBlockData(index, 'src', e.target.result);
                    updateBlockEditor();
                };
                reader.readAsDataURL(file);
            }
        }

        // Markdown Functions
        function updateMarkdownPreview() {
            const markdown = document.getElementById('markdownInput').value;
            const html = marked.parse(markdown);
            document.getElementById('markdownPreview').innerHTML = html;
        }

        // Visual Editor Functions
        function formatText(command) {
            document.execCommand(command, false, null);
        }

        function insertLink() {
            const url = prompt('Enter URL:');
            if (url) {
                document.execCommand('createLink', false, url);
            }
        }

        function insertImage() {
            const url = prompt('Enter image URL:');
            if (url) {
                document.execCommand('insertImage', false, url);
            }
        }

        // Tag Functions
        function addTag(tagName) {
            if (!tags.includes(tagName)) {
                tags.push(tagName);
                updateTagsDisplay();
            }
        }

        function removeTag(tagName) {
            tags = tags.filter(tag => tag !== tagName);
            updateTagsDisplay();
        }

        function updateTagsDisplay() {
            const container = document.getElementById('tagsInput');
            const input = container.querySelector('.tag-input');

            // Remove existing tag items
            container.querySelectorAll('.tag-item').forEach(item => item.remove());

            // Add tag items
            tags.forEach(tag => {
                const tagElement = document.createElement('div');
                tagElement.className = 'tag-item';
                tagElement.innerHTML = `
                    ${tag}
                    <button type="button" class="tag-remove" onclick="removeTag('${tag}')">×</button>
                `;
                container.insertBefore(tagElement, input);
            });

            // Update hidden input
            document.getElementById('tagsData').value = tags.join(',');
        }

        // Image Upload Functions
        function handleFeaturedImageUpload() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('imagePreview').innerHTML =
                        `<img src="${e.target.result}" class="image-preview" alt="Preview">`;
                };
                reader.readAsDataURL(file);
            }
        }

        function handleGalleryImagesUpload() {
            const files = this.files;
            const preview = document.getElementById('galleryPreview');

            Array.from(files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const div = document.createElement('div');
                    div.className = 'gallery-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="Gallery image">
                        <button type="button" class="gallery-remove" onclick="this.parentElement.remove()">×</button>
                    `;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }

        // Character Counter
        function setupCharacterCounter(inputId, counterId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);

            input.addEventListener('input', function () {
                const length = this.value.length;
                counter.textContent = `${length}/${maxLength} characters`;

                counter.className = 'character-count';
                if (length > maxLength * 0.9) {
                    counter.classList.add('danger');
                } else if (length > maxLength * 0.7) {
                    counter.classList.add('warning');
                }

                updateSEOPreview();
            });
        }

        // SEO Preview
        function updateSEOPreview() {
            const title = document.getElementById('seoTitle').value || document.getElementById('postTitle').value || 'Your Content Title';
            const slug = document.getElementById('postSlug').value || 'your-content-slug';
            const description = document.getElementById('seoDescription').value || 'Your meta description will appear here...';

            document.getElementById('previewTitle').textContent = title;
            document.getElementById('previewUrl').textContent = `<?php echo SITE_URL; ?>/blog/${slug}`;
            document.getElementById('previewDescription').textContent = description;
        }

        // Form Submission
        function prepareContentForSubmission() {
            let content = '';

            switch (currentMode) {
                case 'blocks':
                    content = JSON.stringify(contentBlocks);
                    break;
                case 'markdown':
                    content = document.getElementById('markdownInput').value;
                    break;
                case 'visual':
                    content = document.getElementById('visualContent').innerHTML;
                    break;
            }

            document.getElementById('contentData').value = content;
        }

        // Initialize SEO preview
        updateSEOPreview();
    </script>
</body>

</html>