<?php
require_once '../../config/config.php';
require_once __DIR__ . '/../../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('media.view');

$page_title = 'Media Library';

// Handle file upload
if ($_POST && isset($_FILES['files'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $folder_id = (int) ($_POST['folder_id'] ?? 0);
        $uploaded_files = [];
        $errors = [];

        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['files']['name'][$key];
                $file_size = $_FILES['files']['size'][$key];
                $file_type = $_FILES['files']['type'][$key];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                // Validate file
                $allowed_types = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOCUMENT_TYPES, ['mp4', 'mov', 'avi', 'mp3', 'wav']);
                if (!in_array($file_ext, $allowed_types)) {
                    $errors[] = "File type not allowed: $file_name";
                    continue;
                }

                if ($file_size > MAX_FILE_SIZE) {
                    $errors[] = "File too large: $file_name";
                    continue;
                }

                // Generate unique filename
                $new_filename = uniqid() . '.' . $file_ext;
                $upload_path = UPLOADS_PATH . '/media/' . $new_filename;

                // Create directory if needed
                $upload_dir = dirname($upload_path);
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($tmp_name, $upload_path)) {
                    // Determine file type
                    $media_type = 'other';
                    if (in_array($file_ext, ALLOWED_IMAGE_TYPES)) {
                        $media_type = 'image';

                        // Get image dimensions
                        $image_info = getimagesize($upload_path);
                        $dimensions = $image_info ? $image_info[0] . 'x' . $image_info[1] : null;
                    } elseif (in_array($file_ext, ['mp4', 'mov', 'avi'])) {
                        $media_type = 'video';
                    } elseif (in_array($file_ext, ['mp3', 'wav'])) {
                        $media_type = 'audio';
                    } elseif (in_array($file_ext, ALLOWED_DOCUMENT_TYPES)) {
                        $media_type = 'document';
                    }

                    // Save to database
                    $stmt = $db->prepare("
                        INSERT INTO media_files (filename, original_filename, file_path, file_size, 
                                               mime_type, file_type, dimensions, folder_id, uploaded_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $new_filename,
                        $file_name,
                        'uploads/media/' . $new_filename,
                        $file_size,
                        $file_type,
                        $media_type,
                        $dimensions ?? null,
                        $folder_id ?: null,
                        $_SESSION['user_id']
                    ]);

                    $uploaded_files[] = $file_name;
                } else {
                    $errors[] = "Failed to upload: $file_name";
                }
            }
        }

        if (!empty($uploaded_files)) {
            $success = count($uploaded_files) . ' files uploaded successfully';
        }
        if (!empty($errors)) {
            $error = implode(', ', $errors);
        }
    }
}

// Handle folder creation
if ($_POST && isset($_POST['create_folder'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $folder_name = sanitizeInput($_POST['folder_name']);
        $parent_id = (int) ($_POST['parent_id'] ?? 0);

        if (!empty($folder_name)) {
            $path = $parent_id ? '' : $folder_name; // Build path logic here

            $stmt = $db->prepare("
                INSERT INTO media_folders (name, parent_id, path, created_by) 
                VALUES (?, ?, ?, ?)
            ");

            if ($stmt->execute([$folder_name, $parent_id ?: null, $path, $_SESSION['user_id']])) {
                $success = 'Folder created successfully';
            } else {
                $error = 'Failed to create folder';
            }
        }
    }
}

// Get current folder
$current_folder_id = (int) ($_GET['folder'] ?? 0);
$current_folder = null;
if ($current_folder_id) {
    $stmt = $db->prepare("SELECT * FROM media_folders WHERE id = ?");
    $stmt->execute([$current_folder_id]);
    $current_folder = $stmt->fetch();
}

// Get filters
$file_type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$view = $_GET['view'] ?? 'grid';

// Build query conditions
$where_conditions = ['mf.status = "active"'];
$params = [];

if ($current_folder_id) {
    $where_conditions[] = 'mf.folder_id = ?';
    $params[] = $current_folder_id;
} else {
    $where_conditions[] = 'mf.folder_id IS NULL';
}

if ($file_type) {
    $where_conditions[] = 'mf.file_type = ?';
    $params[] = $file_type;
}

if ($search) {
    $where_conditions[] = '(mf.original_filename LIKE ? OR mf.alt_text LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get folders in current directory
$folders_query = "
    SELECT * FROM media_folders 
    WHERE " . ($current_folder_id ? "parent_id = $current_folder_id" : "parent_id IS NULL") . "
    ORDER BY name ASC
";
$folders = $db->query($folders_query)->fetchAll();

// Get files
$files_query = "
    SELECT mf.*, u.first_name, u.last_name
    FROM media_files mf
    LEFT JOIN users u ON mf.uploaded_by = u.id
    WHERE $where_clause
    ORDER BY mf.$sort $order
";
$files_stmt = $db->prepare($files_query);
$files_stmt->execute($params);
$files = $files_stmt->fetchAll();

// Get storage statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_files,
        SUM(file_size) as total_size,
        COUNT(CASE WHEN file_type = 'image' THEN 1 END) as image_count,
        COUNT(CASE WHEN file_type = 'video' THEN 1 END) as video_count,
        COUNT(CASE WHEN file_type = 'document' THEN 1 END) as document_count
    FROM media_files 
    WHERE status = 'active'
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
    <style>
        .media-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .media-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--admin-primary);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .media-toolbar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.9em;
        }

        .breadcrumb a {
            color: var(--admin-primary);
            text-decoration: none;
        }

        .view-toggle {
            display: flex;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .view-btn {
            padding: 8px 12px;
            border: none;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-btn.active {
            background: var(--admin-primary);
            color: white;
        }

        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-area:hover,
        .upload-area.dragover {
            border-color: var(--admin-primary);
            background: rgba(212, 165, 116, 0.05);
        }

        .upload-icon {
            font-size: 3em;
            color: #ddd;
            margin-bottom: 15px;
        }

        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .media-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .folder-item,
        .file-item {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .folder-item:hover,
        .file-item:hover {
            transform: translateY(-2px);
        }

        .folder-item {
            padding: 20px;
            text-align: center;
            border: 2px solid #f0f0f0;
        }

        .folder-icon {
            font-size: 3em;
            color: #ffc107;
            margin-bottom: 10px;
        }

        .folder-name {
            font-weight: 600;
            color: #333;
        }

        .file-preview {
            height: 150px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .file-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-preview .file-icon {
            font-size: 3em;
            color: #666;
        }

        .file-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .file-item:hover .file-actions {
            opacity: 1;
        }

        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
        }

        .file-info {
            padding: 15px;
        }

        .file-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 0.9em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-meta {
            font-size: 0.8em;
            color: #666;
            display: flex;
            justify-content: space-between;
        }

        .file-size {
            font-weight: 500;
        }

        .list-view .file-item {
            display: flex;
            align-items: center;
            padding: 15px;
        }

        .list-view .file-preview {
            width: 60px;
            height: 60px;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .list-view .file-info {
            flex: 1;
            padding: 0;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
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

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: var(--admin-primary);
            width: 0%;
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .media-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .toolbar-left,
            .toolbar-right {
                justify-content: center;
            }

            .media-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
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
                <div class="media-header">
                    <h1>Media Library</h1>
                    <p>Manage your images, videos, documents and other media files</p>
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
                <div class="media-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_files']); ?></div>
                        <div class="stat-label">Total Files</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo formatFileSize($stats['total_size']); ?></div>
                        <div class="stat-label">Storage Used</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['image_count']); ?></div>
                        <div class="stat-label">Images</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['video_count']); ?></div>
                        <div class="stat-label">Videos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['document_count']); ?></div>
                        <div class="stat-label">Documents</div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="media-toolbar">
                    <div class="toolbar-left">
                        <div class="breadcrumb">
                            <a href="index.php"><i class="fas fa-home"></i> Media Library</a>
                            <?php if ($current_folder): ?>
                                <i class="fas fa-chevron-right"></i>
                                <span><?php echo htmlspecialchars($current_folder['name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="toolbar-right">
                        <div class="search-box">
                            <form method="GET" style="display: flex; gap: 10px;">
                                <input type="hidden" name="folder" value="<?php echo $current_folder_id; ?>">
                                <input type="text" name="search" placeholder="Search files..."
                                    value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                                <select name="type" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="image" <?php echo $file_type === 'image' ? 'selected' : ''; ?>>Images
                                    </option>
                                    <option value="video" <?php echo $file_type === 'video' ? 'selected' : ''; ?>>Videos
                                    </option>
                                    <option value="document" <?php echo $file_type === 'document' ? 'selected' : ''; ?>>
                                        Documents</option>
                                    <option value="audio" <?php echo $file_type === 'audio' ? 'selected' : ''; ?>>Audio
                                    </option>
                                </select>
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </form>
                        </div>

                        <div class="view-toggle">
                            <button class="view-btn <?php echo $view === 'grid' ? 'active' : ''; ?>"
                                onclick="changeView('grid')">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="view-btn <?php echo $view === 'list' ? 'active' : ''; ?>"
                                onclick="changeView('list')">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>

                        <button class="btn btn-outline" onclick="openCreateFolderModal()">
                            <i class="fas fa-folder-plus"></i> New Folder
                        </button>

                        <button class="btn btn-primary" onclick="openUploadModal()">
                            <i class="fas fa-upload"></i> Upload Files
                        </button>
                    </div>
                </div>

                <!-- Upload Area -->
                <div class="upload-area" id="uploadArea" onclick="openUploadModal()">
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <h3>Drop files here or click to upload</h3>
                    <p>Supports images, videos, documents and audio files</p>
                </div>

                <!-- Media Content -->
                <div class="media-content">
                    <div class="media-grid <?php echo $view === 'list' ? 'media-list list-view' : ''; ?>"
                        id="mediaGrid">
                        <!-- Folders -->
                        <?php foreach ($folders as $folder): ?>
                            <div class="folder-item" onclick="openFolder(<?php echo $folder['id']; ?>)">
                                <div class="folder-icon">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <div class="folder-name"><?php echo htmlspecialchars($folder['name']); ?></div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Files -->
                        <?php foreach ($files as $file): ?>
                            <div class="file-item" data-file-id="<?php echo $file['id']; ?>">
                                <div class="file-preview">
                                    <?php if ($file['file_type'] === 'image'): ?>
                                        <img src="../../<?php echo htmlspecialchars($file['file_path']); ?>"
                                            alt="<?php echo htmlspecialchars($file['alt_text'] ?: $file['original_filename']); ?>">
                                    <?php else: ?>
                                        <div class="file-icon">
                                            <?php
                                            $icon = 'fas fa-file';
                                            switch ($file['file_type']) {
                                                case 'video':
                                                    $icon = 'fas fa-video';
                                                    break;
                                                case 'audio':
                                                    $icon = 'fas fa-music';
                                                    break;
                                                case 'document':
                                                    $icon = 'fas fa-file-alt';
                                                    break;
                                            }
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </div>
                                    <?php endif; ?>

                                    <div class="file-actions">
                                        <button class="action-btn" onclick="editFile(<?php echo $file['id']; ?>)"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn" onclick="copyFileUrl(<?php echo $file['id']; ?>)"
                                            title="Copy URL">
                                            <i class="fas fa-link"></i>
                                        </button>
                                        <button class="action-btn" onclick="deleteFile(<?php echo $file['id']; ?>)"
                                            title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="file-info">
                                    <div class="file-name"
                                        title="<?php echo htmlspecialchars($file['original_filename']); ?>">
                                        <?php echo htmlspecialchars($file['original_filename']); ?>
                                    </div>
                                    <div class="file-meta">
                                        <span class="file-type"><?php echo strtoupper($file['file_type']); ?></span>
                                        <span class="file-size"><?php echo formatFileSize($file['file_size']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($folders) && empty($files)): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h3>No files found</h3>
                            <p>Upload some files to get started</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeUploadModal()">&times;</span>
            <h3>Upload Files</h3>

            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="folder_id" value="<?php echo $current_folder_id; ?>">

                <div class="form-group">
                    <label>Select Files</label>
                    <input type="file" name="files[]" multiple
                        accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx" class="form-control" id="fileInput"
                        required>
                </div>

                <div id="uploadProgress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div id="progressText">Uploading...</div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Files</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Folder Modal -->
    <div class="modal" id="createFolderModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeCreateFolderModal()">&times;</span>
            <h3>Create New Folder</h3>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="parent_id" value="<?php echo $current_folder_id; ?>">

                <div class="form-group">
                    <label>Folder Name</label>
                    <input type="text" name="folder_name" class="form-control" required>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="closeCreateFolderModal()">Cancel</button>
                    <button type="submit" name="create_folder" class="btn btn-primary">Create Folder</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // View toggle
        function changeView(view) {
            const url = new URL(window.location);
            url.searchParams.set('view', view);
            window.location.href = url.toString();
        }

        // Folder navigation
        function openFolder(folderId) {
            window.location.href = `index.php?folder=${folderId}`;
        }

        // Upload modal
        function openUploadModal() {
            document.getElementById('uploadModal').style.display = 'block';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }

        // Create folder modal
        function openCreateFolderModal() {
            document.getElementById('createFolderModal').style.display = 'block';
        }

        function closeCreateFolderModal() {
            document.getElementById('createFolderModal').style.display = 'none';
        }

        // File operations
        function editFile(fileId) {
            // Open edit modal
            console.log('Edit file:', fileId);
        }

        function copyFileUrl(fileId) {
            // Copy file URL to clipboard
            console.log('Copy URL for file:', fileId);
        }

        function deleteFile(fileId) {
            if (confirm('Are you sure you want to delete this file?')) {
                fetch('../api/delete-media-file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ file_id: fileId })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error deleting file');
                        }
                    });
            }
        }

        // Drag and drop upload
        const uploadArea = document.getElementById('uploadArea');

        uploadArea.addEventListener('dragover', function (e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function (e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = document.getElementById('fileInput');
                fileInput.files = files;
                openUploadModal();
            }
        });

        // Upload progress
        document.getElementById('uploadForm').addEventListener('submit', function (e) {
            const progressDiv = document.getElementById('uploadProgress');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');

            progressDiv.style.display = 'block';

            // Simulate progress (in real implementation, use XMLHttpRequest for actual progress)
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                progressFill.style.width = progress + '%';
                progressText.textContent = `Uploading... ${progress}%`;

                if (progress >= 100) {
                    clearInterval(interval);
                    progressText.textContent = 'Processing...';
                }
            }, 200);
        });
    </script>

    <script src="../../assets/js/admin.js"></script>
</body>

</html>

<?php
function formatFileSize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}
?>