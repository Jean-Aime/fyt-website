<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

// In edit.php, at the top of the file:
$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$post_id) {
    $_SESSION['error_message'] = 'Missing post ID.';
    header("Location: index.php");
    exit;
}

// Fetch the post data
$stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    $_SESSION['error_message'] = 'Post not found.';
    header("Location: index.php");
    exit;
}


$auth = new SecureAuth($db);
requireLogin();

// DEBUG: Show current user info if needed
// echo 'Logged in as user ID: ' . $_SESSION['user_id'];

// Check permission for editing own posts or all
if (!$auth->hasPermission($_SESSION['user_id'], 'blog.edit')) {
    $_SESSION['error_message'] = 'Access denied. You do not have the required permission: blog.edit.';
    header("Location: ../../edit.php"); // or use a custom access denied page
    exit;
}

$page_title = 'Edit Blog Post';

// Get post ID from URL
$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$post_id) {
    $_SESSION['error_message'] = 'Missing post ID.';
    header("Location: index.php");
    exit;
}

// Fetch the post data
$stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    $_SESSION['error_message'] = 'Post not found.';
    header("Location: index.php");
    exit;
}

// ✅ Author vs. editor permission check
if (
    $post['author_id'] != $_SESSION['user_id']
    && !$auth->hasPermission($_SESSION['user_id'], 'blog.edit_all')
) {
    $_SESSION['error_message'] = 'You do not have permission to edit this post.';
    header("Location: index.php");
    exit;
}

// Reading time function
function calculateReadingTime($text)
{
    $words_per_minute = 200;
    $word_count = str_word_count(strip_tags($text));
    return ceil($word_count / $words_per_minute);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'])) {
    try {
        $title = sanitizeInput($_POST['title']);
        $slug = !empty($_POST['slug']) ? sanitizeInput($_POST['slug']) : generateSlug($title);
        $excerpt = sanitizeInput($_POST['excerpt']);
        $content = $_POST['content'];
        $content_type = $_POST['content_type'] ?? 'blocks';
        $category_id = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
        $status = in_array($_POST['status'], ['draft', 'published', 'scheduled']) ? $_POST['status'] : 'draft';
        $is_featured = isset($_POST['featured']) ? 1 : 0;
        $seo_title = sanitizeInput($_POST['seo_title']);
        $seo_description = sanitizeInput($_POST['seo_description']);
        $seo_keywords = sanitizeInput($_POST['seo_keywords']);
        $tags = isset($_POST['tags']) ? explode(',', $_POST['tags']) : [];
        $region = sanitizeInput($_POST['region']);
        $post_type = $_POST['post_type'] ?? 'blog';

        if (empty($title))
            throw new Exception("Title is required.");
        if (empty($content))
            throw new Exception("Content is required.");

        // Handle featured image
        $featured_image = $post['featured_image'];
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = UPLOADS_PATH . '/blog/';
            if (!file_exists($upload_dir))
                mkdir($upload_dir, 0755, true);

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $_FILES['featured_image']['tmp_name']);
            finfo_close($file_info);

            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Invalid image type.");
            }

            $ext = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $target_path)) {
                if ($featured_image && file_exists('../../' . $featured_image)) {
                    unlink('../../' . $featured_image);
                }
                $featured_image = 'uploads/blog/' . $filename;
            } else {
                throw new Exception("Failed to upload featured image.");
            }
        }

        // Handle gallery images
        $gallery_images = json_decode($post['gallery_images'], true) ?? [];
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

        $processed_content = ($content_type === 'markdown') ? markdownToHtml($content) : $content;
        $reading_time = calculateReadingTime($processed_content);

        $published_at = $post['published_at'];
        if ($status === 'published' && $post['status'] !== 'published') {
            $published_at = date('Y-m-d H:i:s');
        } elseif ($status === 'scheduled' && !empty($_POST['scheduled_date'])) {
            $published_at = $_POST['scheduled_date'] . ' ' . ($_POST['scheduled_time'] ?? '09:00:00');
        } elseif ($status === 'draft') {
            $published_at = null;
        }

        $stmt = $db->prepare("
            UPDATE blog_posts SET
                title = ?, slug = ?, content = ?, content_type = ?, excerpt = ?, category_id = ?, 
                status = ?, is_featured = ?, featured_image = ?, gallery_images = ?, reading_time = ?, 
                seo_title = ?, seo_description = ?, seo_keywords = ?, published_at = ?, 
                region = ?, post_type = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $title,
            $slug,
            $processed_content,
            $content_type,
            $excerpt,
            $category_id,
            $status,
            $is_featured,
            $featured_image,
            json_encode($gallery_images),
            $reading_time,
            $seo_title,
            $seo_description,
            $seo_keywords,
            $published_at,
            $region,
            $post_type,
            $post_id
        ]);

        // Update tags
        $db->prepare("DELETE FROM blog_post_tags WHERE post_id = ?")->execute([$post_id]);

        if (!empty($tags)) {
            foreach ($tags as $tag_name) {
                $tag_name = trim($tag_name);
                if (!empty($tag_name)) {
                    $stmt = $db->prepare("SELECT id FROM blog_tags WHERE name = ?");
                    $stmt->execute([$tag_name]);
                    $tag = $stmt->fetch();

                    $tag_id = $tag ? $tag['id'] : null;
                    if (!$tag_id) {
                        $stmt = $db->prepare("INSERT INTO blog_tags (name, slug) VALUES (?, ?)");
                        $stmt->execute([$tag_name, generateSlug($tag_name)]);
                        $tag_id = $db->lastInsertId();
                    }

                    $stmt = $db->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
                    $stmt->execute([$post_id, $tag_id]);
                }
            }
        }

        $auth->logActivity($_SESSION['user_id'], 'blog_post_updated', "Updated blog post: $title");

        $_SESSION['success_message'] = 'Blog post updated successfully!';
        header("Location: edit.php?id=$post_id");
        exit;

    } catch (Exception $e) {
        $error = 'Error updating blog post: ' . $e->getMessage();
    }
}

// Fetch post tags
$stmt = $db->prepare("
    SELECT bt.name 
    FROM blog_tags bt
    JOIN blog_post_tags bpt ON bt.id = bpt.tag_id
    WHERE bpt.post_id = ?
");
$stmt->execute([$post_id]);
$post_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch categories
$categories = $db->query("SELECT id, name FROM blog_categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Regions
$regions = ['Africa', 'Asia', 'Europe', 'North America', 'South America', 'Oceania', 'Middle East'];

// Popular tags
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
        /* Same CSS as in create.php */
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

        /* Rest of the CSS from create.php */
        /* ... */
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
                        <h2>Edit Content</h2>
                        <p>Editing: <?php echo htmlspecialchars($post['title']); ?></p>
                    </div>
                    <div class="content-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Posts
                        </a>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="contentForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="content_type" id="contentType"
                        value="<?php echo htmlspecialchars($post['content_type']); ?>">
                    <input type="hidden" name="content" id="contentData">

                    <div class="content-editor-container">
                        <!-- Main Editor -->
                        <div class="main-editor">
                            <!-- Post Type Selection -->
                            <div class="form-group">
                                <label class="form-label">Content Type</label>
                                <div class="post-type-selector">
                                    <div class="post-type-option <?php echo $post['post_type'] === 'blog' ? 'selected' : ''; ?>"
                                        data-type="blog">
                                        <div class="post-type-icon"><i class="fas fa-blog"></i></div>
                                        <div>Blog Post</div>
                                    </div>
                                    <div class="post-type-option <?php echo $post['post_type'] === 'travel_tip' ? 'selected' : ''; ?>"
                                        data-type="travel_tip">
                                        <div class="post-type-icon"><i class="fas fa-lightbulb"></i></div>
                                        <div>Travel Tip</div>
                                    </div>
                                    <div class="post-type-option <?php echo $post['post_type'] === 'destination_guide' ? 'selected' : ''; ?>"
                                        data-type="destination_guide">
                                        <div class="post-type-icon"><i class="fas fa-map-marked-alt"></i></div>
                                        <div>Destination Guide</div>
                                    </div>
                                    <div class="post-type-option <?php echo $post['post_type'] === 'itinerary' ? 'selected' : ''; ?>"
                                        data-type="itinerary">
                                        <div class="post-type-icon"><i class="fas fa-route"></i></div>
                                        <div>Itinerary</div>
                                    </div>
                                </div>
                                <input type="hidden" name="post_type" id="postType"
                                    value="<?php echo htmlspecialchars($post['post_type']); ?>">
                            </div>

                            <div class="form-group">
                                <input type="text" name="title" id="postTitle" class="form-control" required
                                    placeholder="Enter your content title..."
                                    value="<?php echo htmlspecialchars($post['title']); ?>"
                                    style="font-size: 1.5em; font-weight: 600; border: none; padding: 0; margin-bottom: 20px;">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Permalink</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span><?php echo SITE_URL; ?>/blog/</span>
                                    <input type="text" name="slug" id="postSlug" class="form-control" style="flex: 1;"
                                        value="<?php echo htmlspecialchars($post['slug']); ?>">
                                </div>
                            </div>

                            <!-- Editor Mode Selector -->
                            <div class="editor-mode-selector">
                                <button type="button"
                                    class="mode-btn <?php echo $post['content_type'] === 'blocks' ? 'active' : ''; ?>"
                                    data-mode="blocks">
                                    <i class="fas fa-th-large"></i> Block Editor
                                </button>
                                <button type="button"
                                    class="mode-btn <?php echo $post['content_type'] === 'markdown' ? 'active' : ''; ?>"
                                    data-mode="markdown">
                                    <i class="fab fa-markdown"></i> Markdown
                                </button>
                                <button type="button"
                                    class="mode-btn <?php echo $post['content_type'] === 'visual' ? 'active' : ''; ?>"
                                    data-mode="visual">
                                    <i class="fas fa-edit"></i> Visual Editor
                                </button>
                            </div>

                            <!-- Content Editor -->
                            <div class="editor-content">
                                <!-- Block Editor -->
                                <div id="blockEditor" class="block-editor"
                                    style="<?php echo $post['content_type'] !== 'blocks' ? 'display: none;' : ''; ?>">
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
                                        <?php if ($post['content_type'] === 'blocks'): ?>
                                            <?php
                                            $blocks = json_decode($post['content'], true);
                                            if (empty($blocks)) {
                                                $blocks = [['type' => 'paragraph', 'content' => '']];
                                            }
                                            ?>
                                            <?php foreach ($blocks as $index => $block): ?>
                                                <div class="content-block"
                                                    data-type="<?php echo htmlspecialchars($block['type']); ?>">
                                                    <div class="block-controls">
                                                        <button type="button" class="block-control-btn"
                                                            onclick="moveBlockUp(<?php echo $index; ?>)">
                                                            <i class="fas fa-arrow-up"></i>
                                                        </button>
                                                        <button type="button" class="block-control-btn"
                                                            onclick="moveBlockDown(<?php echo $index; ?>)">
                                                            <i class="fas fa-arrow-down"></i>
                                                        </button>
                                                        <button type="button" class="block-control-btn"
                                                            onclick="deleteBlock(<?php echo $index; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                    <?php if ($block['type'] === 'paragraph'): ?>
                                                        <div contenteditable="true" class="block-content"
                                                            placeholder="Start writing..."><?php echo $block['content']; ?></div>
                                                    <?php elseif ($block['type'] === 'heading'): ?>
                                                        <select class="form-control" style="margin-bottom: 10px;"
                                                            onchange="updateBlockData(<?php echo $index; ?>, 'level', this.value)">
                                                            <option value="h2" <?php echo ($block['level'] ?? 'h2') === 'h2' ? 'selected' : ''; ?>>Heading 2</option>
                                                            <option value="h3" <?php echo ($block['level'] ?? 'h2') === 'h3' ? 'selected' : ''; ?>>Heading 3</option>
                                                            <option value="h4" <?php echo ($block['level'] ?? 'h2') === 'h4' ? 'selected' : ''; ?>>Heading 4</option>
                                                        </select>
                                                        <div contenteditable="true" class="block-content"
                                                            placeholder="Enter heading text..."><?php echo $block['content']; ?>
                                                        </div>
                                                    <?php elseif ($block['type'] === 'image'): ?>
                                                        <input type="file" accept="image/*"
                                                            onchange="handleBlockImageUpload(this, <?php echo $index; ?>)"
                                                            style="margin-bottom: 10px;">
                                                        <input type="text" class="form-control" placeholder="Image caption..."
                                                            value="<?php echo htmlspecialchars($block['caption'] ?? ''); ?>"
                                                            onchange="updateBlockData(<?php echo $index; ?>, 'caption', this.value)"
                                                            style="margin-bottom: 10px;">
                                                        <?php if (!empty($block['src'])): ?>
                                                            <img src="<?php echo htmlspecialchars($block['src']); ?>"
                                                                style="max-width: 100%; height: auto;">
                                                        <?php else: ?>
                                                            <p>No image selected</p>
                                                        <?php endif; ?>
                                                    <?php elseif ($block['type'] === 'quote'): ?>
                                                        <div contenteditable="true" class="block-content"
                                                            placeholder="Enter quote text..."
                                                            style="font-style: italic; border-left: 4px solid var(--primary-color); padding-left: 15px;">
                                                            <?php echo $block['content']; ?>
                                                        </div>
                                                        <input type="text" class="form-control" placeholder="Quote author..."
                                                            value="<?php echo htmlspecialchars($block['author'] ?? ''); ?>"
                                                            onchange="updateBlockData(<?php echo $index; ?>, 'author', this.value)"
                                                            style="margin-top: 10px;">
                                                    <?php elseif ($block['type'] === 'list'): ?>
                                                        <select class="form-control" style="margin-bottom: 10px;"
                                                            onchange="updateBlockData(<?php echo $index; ?>, 'listType', this.value)">
                                                            <option value="ul" <?php echo ($block['listType'] ?? 'ul') === 'ul' ? 'selected' : ''; ?>>Unordered List</option>
                                                            <option value="ol" <?php echo ($block['listType'] ?? 'ul') === 'ol' ? 'selected' : ''; ?>>Ordered List</option>
                                                        </select>
                                                        <div contenteditable="true" class="block-content"
                                                            placeholder="Enter list items (one per line)...">
                                                            <?php echo $block['content']; ?>
                                                        </div>
                                                    <?php elseif ($block['type'] === 'code'): ?>
                                                        <input type="text" class="form-control"
                                                            placeholder="Language (e.g., javascript, php)..."
                                                            value="<?php echo htmlspecialchars($block['language'] ?? ''); ?>"
                                                            onchange="updateBlockData(<?php echo $index; ?>, 'language', this.value)"
                                                            style="margin-bottom: 10px;">
                                                        <textarea class="form-control" placeholder="Enter your code..."
                                                            style="font-family: monospace;"
                                                            onchange="updateBlockData(<?php echo $index; ?>, 'content', this.value)"><?php echo htmlspecialchars($block['content']); ?></textarea>
                                                    <?php elseif ($block['type'] === 'video'): ?>
                                                        <input type="text" class="form-control"
                                                            placeholder="Video URL (YouTube, Vimeo, etc.)..."
                                                            value="<?php echo htmlspecialchars($block['url'] ?? ''); ?>"
                                                            onchange="updateBlockData(<?php echo $index; ?>, 'url', this.value)"
                                                            style="margin-bottom: 10px;">
                                                        <input type="text" class="form-control" placeholder="Video caption..."
                                                            value="<?php echo htmlspecialchars($block['caption'] ?? ''); ?>"
                                                            onchange="updateBlockData(<?php echo $index; ?>, 'caption', this.value)">
                                                    <?php elseif ($block['type'] === 'map'): ?>
                                                        <input type="text" class="form-control"
                                                            placeholder="Location or coordinates..."
                                                            value="<?php echo htmlspecialchars($block['location'] ?? ''); ?>"
                                                            onchange="updateBlockData(<?php echo $index; ?>, 'location', this.value)"
                                                            style="margin-bottom: 10px;">
                                                        <input type="number" class="form-control" placeholder="Zoom level (1-20)..."
                                                            value="<?php echo htmlspecialchars($block['zoom'] ?? 10); ?>"
                                                            onchange="updateBlockData(<?php echo $index; ?>, 'zoom', this.value)">
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Markdown Editor -->
                                <div id="markdownEditor" class="markdown-editor"
                                    style="<?php echo $post['content_type'] !== 'markdown' ? 'display: none;' : ''; ?>">
                                    <textarea id="markdownInput" class="markdown-input"
                                        placeholder="Write your content in Markdown..."><?php echo $post['content_type'] === 'markdown' ? htmlspecialchars($post['content']) : ''; ?></textarea>
                                    <div id="markdownPreview" class="markdown-preview">
                                        <p>Preview will appear here...</p>
                                    </div>
                                </div>

                                <!-- Visual Editor -->
                                <div id="visualEditor" class="visual-editor"
                                    style="<?php echo $post['content_type'] !== 'visual' ? 'display: none;' : ''; ?>">
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
                                        <?php echo $post['content_type'] === 'visual' ? $post['content'] : '<p>Start writing your content here...</p>'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Excerpt</label>
                                <textarea name="excerpt" id="postExcerpt" class="form-control" rows="3"
                                    placeholder="Brief description of your content..."
                                    maxlength="300"><?php echo htmlspecialchars($post['excerpt']); ?></textarea>
                                <div class="character-count" id="excerptCount">
                                    <?php echo strlen($post['excerpt']); ?>/300 characters
                                </div>
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
                                        <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo $post['status'] === 'published' ? 'selected' : ''; ?>>Publish Now</option>
                                        <option value="scheduled" <?php echo $post['status'] === 'scheduled' ? 'selected' : ''; ?>>Schedule</option>
                                    </select>
                                </div>

                                <div class="form-group" id="scheduleFields"
                                    style="<?php echo $post['status'] !== 'scheduled' ? 'display: none;' : ''; ?>">
                                    <label>Publish Date:</label>
                                    <?php
                                    $scheduled_date = $post['published_at'] ? date('Y-m-d', strtotime($post['published_at'])) : '';
                                    $scheduled_time = $post['published_at'] ? date('H:i', strtotime($post['published_at'])) : '09:00';
                                    ?>
                                    <input type="date" name="scheduled_date" class="form-control"
                                        value="<?php echo $scheduled_date; ?>" style="margin-bottom: 10px;">
                                    <input type="time" name="scheduled_time" class="form-control"
                                        value="<?php echo $scheduled_time; ?>">
                                </div>

                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="featured" <?php echo $post['is_featured'] ? 'checked' : ''; ?>> Featured Content
                                    </label>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-save"></i> Update Content
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
                                            <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $post['category_id'] ? 'selected' : ''; ?>>
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
                                            <option value="<?php echo $region; ?>" <?php echo $region === $post['region'] ? 'selected' : ''; ?>><?php echo $region; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Tags Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">Tags</h3>

                                <div class="tags-input" id="tagsInput">
                                    <?php foreach ($post_tags as $tag): ?>
                                        <div class="tag-item">
                                            <?php echo htmlspecialchars($tag); ?>
                                            <button type="button" class="tag-remove"
                                                onclick="removeTag('<?php echo htmlspecialchars($tag); ?>')">×</button>
                                        </div>
                                    <?php endforeach; ?>
                                    <input type="text" class="tag-input" placeholder="Add tags..." id="tagInputField">
                                </div>
                                <input type="hidden" name="tags" id="tagsData"
                                    value="<?php echo htmlspecialchars(implode(',', $post_tags)); ?>">

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

                                <div class="image-upload-area <?php echo $post['featured_image'] ? 'has-image' : ''; ?>"
                                    onclick="document.getElementById('featuredImage').click()">
                                    <input type="file" name="featured_image" id="featuredImage" accept="image/*"
                                        style="display: none;">
                                    <div id="imagePreview">
                                        <?php if ($post['featured_image']): ?>
                                            <img src="../../<?php echo htmlspecialchars($post['featured_image']); ?>"
                                                class="image-preview" alt="Preview">
                                        <?php else: ?>
                                            <i class="fas fa-cloud-upload-alt"
                                                style="font-size: 2em; margin-bottom: 10px;"></i>
                                            <p>Click to upload featured image</p>
                                            <small>Recommended size: 1200x630px</small>
                                        <?php endif; ?>
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

                                <div class="gallery-images" id="galleryPreview">
                                    <?php
                                    $gallery_images = json_decode($post['gallery_images'], true) ?? [];
                                    foreach ($gallery_images as $image): ?>
                                        <div class="gallery-item">
                                            <img src="../../<?php echo htmlspecialchars($image); ?>" alt="Gallery image">
                                            <button type="button" class="gallery-remove"
                                                onclick="removeGalleryImage('<?php echo htmlspecialchars($image); ?>', this.parentElement)">×</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- SEO Panel -->
                            <div class="sidebar-panel">
                                <h3 class="panel-title">SEO Settings</h3>

                                <div class="form-group">
                                    <label>SEO Title:</label>
                                    <input type="text" name="seo_title" id="seoTitle" class="form-control"
                                        maxlength="60" value="<?php echo htmlspecialchars($post['seo_title']); ?>">
                                    <div class="character-count" id="seoTitleCount">
                                        <?php echo strlen($post['seo_title']); ?>/60 characters
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Meta Description:</label>
                                    <textarea name="seo_description" id="seoDescription" class="form-control" rows="3"
                                        maxlength="160"><?php echo htmlspecialchars($post['seo_description']); ?></textarea>
                                    <div class="character-count" id="seoDescCount">
                                        <?php echo strlen($post['seo_description']); ?>/160 characters
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Keywords:</label>
                                    <input type="text" name="seo_keywords" class="form-control"
                                        placeholder="keyword1, keyword2, keyword3"
                                        value="<?php echo htmlspecialchars($post['seo_keywords']); ?>">
                                </div>

                                <div class="seo-preview">
                                    <div class="seo-title" id="previewTitle">
                                        <?php echo htmlspecialchars($post['seo_title'] ?: $post['title']); ?>
                                    </div>
                                    <div class="seo-url" id="previewUrl">
                                        <?php echo SITE_URL; ?>/blog/<?php echo htmlspecialchars($post['slug']); ?>
                                    </div>
                                    <div class="seo-description" id="previewDescription">
                                        <?php echo htmlspecialchars($post['seo_description'] ?: 'Your meta description will appear here...'); ?>
                                    </div>
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
        let currentMode = '<?php echo $post['content_type']; ?>';
        let contentBlocks = <?php echo $post['content_type'] === 'blocks' ? $post['content'] : '[{"type":"paragraph","content":""}]'; ?>;
        let tags = <?php echo json_encode($post_tags); ?>;
        let galleryImages = <?php echo json_encode($gallery_images); ?>;

        // Initialize
        document.addEventListener('DOMContentLoaded', function () {
            initializeEditor();
            setupEventListeners();
        });

        function initializeEditor() {
            if (currentMode === 'blocks') {
                updateBlockEditor();
            } else if (currentMode === 'markdown') {
                updateMarkdownPreview();
            }
            updateTagsDisplay();
            updateSEOPreview();
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

        function removeGalleryImage(imagePath, element) {
            // In a real implementation, you would send an AJAX request to delete the image from the server
            // For now, just remove it from the UI
            element.remove();

            // Remove from galleryImages array
            galleryImages = galleryImages.filter(img => img !== imagePath);

            // Update the hidden field or send via AJAX
            // This is a simplified version - you'd need to implement proper handling
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

        // Delete confirmation
        function confirmDelete() {
            if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                window.location.href = `delete.php?id=<?php echo $post_id; ?>&csrf_token=<?php echo generateCSRFToken(); ?>`;
            }
        }

        // Initialize SEO preview
        updateSEOPreview();
    </script>
</body>

</html>