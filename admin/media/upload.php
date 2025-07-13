<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('media.upload');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$folder_id = (int)($_POST['folder_id'] ?? 0);
$uploaded_files = [];
$errors = [];

if (!isset($_FILES['files'])) {
    echo json_encode(['success' => false, 'message' => 'No files uploaded']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = UPLOADS_PATH . '/media';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Process each uploaded file
$files = $_FILES['files'];
$file_count = count($files['name']);

for ($i = 0; $i < $file_count; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading {$files['name'][$i]}";
        continue;
    }

    $original_filename = $files['name'][$i];
    $tmp_name = $files['tmp_name'][$i];
    $file_size = $files['size'][$i];
    $mime_type = $files['type'][$i];
    
    // Validate file size
    if ($file_size > MAX_FILE_SIZE) {
        $errors[] = "{$original_filename} is too large (max " . formatFileSize(MAX_FILE_SIZE) . ")";
        continue;
    }
    
    // Determine file type
    $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $file_type = 'other';
    
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
        $file_type = 'image';
    } elseif (in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'])) {
        $file_type = 'video';
    } elseif (in_array($extension, ['mp3', 'wav', 'ogg', 'flac', 'aac'])) {
        $file_type = 'audio';
    } elseif (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'])) {
        $file_type = 'document';
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $file_path = 'media/' . $filename;
    $full_path = $upload_dir . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($tmp_name, $full_path)) {
        try {
            // Get image dimensions if it's an image
            $dimensions = null;
            if ($file_type === 'image' && function_exists('getimagesize')) {
                $image_info = getimagesize($full_path);
                if ($image_info) {
                    $dimensions = $image_info[0] . 'x' . $image_info[1];
                }
            }
            
            // Insert into database
            $stmt = $db->prepare("
                INSERT INTO media_files (
                    filename, original_filename, file_path, file_size, mime_type, 
                    file_type, dimensions, uploaded_by, folder_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $filename,
                $original_filename,
                $file_path,
                $file_size,
                $mime_type,
                $file_type,
                $dimensions,
                $_SESSION['user_id'],
                $folder_id ?: null
            ]);
            
            $uploaded_files[] = [
                'id' => $db->lastInsertId(),
                'filename' => $original_filename,
                'type' => $file_type,
                'size' => $file_size
            ];
            
        } catch (Exception $e) {
            // Delete the uploaded file if database insert fails
            unlink($full_path);
            $errors[] = "Database error for {$original_filename}: " . $e->getMessage();
        }
    } else {
        $errors[] = "Failed to move {$original_filename}";
    }
}

// Return response
if (!empty($uploaded_files)) {
    echo json_encode([
        'success' => true,
        'message' => count($uploaded_files) . ' file(s) uploaded successfully',
        'files' => $uploaded_files,
        'errors' => $errors
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No files were uploaded',
        'errors' => $errors
    ]);
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
