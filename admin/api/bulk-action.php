<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

header('Content-Type: application/json');

$auth = new SecureAuth($db);
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action']) || !isset($input['ids']) || !is_array($input['ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$action = $input['action'];
$ids = array_map('intval', $input['ids']);
$type = $input['type'] ?? 'tour'; // Default to tour

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'No items selected']);
    exit;
}

try {
    $table = '';
    $permission = '';
    
    switch ($type) {
        case 'tour':
            $table = 'tours';
            $permission = $action === 'delete' ? 'tours.delete' : 'tours.edit';
            break;
        case 'booking':
            $table = 'bookings';
            $permission = $action === 'delete' ? 'bookings.delete' : 'bookings.edit';
            break;
        case 'user':
            $table = 'users';
            $permission = $action === 'delete' ? 'users.delete' : 'users.edit';
            break;
        default:
            throw new Exception('Invalid type');
    }
    
    if (!hasPermission($permission)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $affected = 0;
    
    switch ($action) {
        case 'activate':
            $stmt = $db->prepare("UPDATE {$table} SET status = 'active' WHERE id IN ({$placeholders})");
            $stmt->execute($ids);
            $affected = $stmt->rowCount();
            break;
            
        case 'deactivate':
            $stmt = $db->prepare("UPDATE {$table} SET status = 'inactive' WHERE id IN ({$placeholders})");
            $stmt->execute($ids);
            $affected = $stmt->rowCount();
            break;
            
        case 'delete':
            $stmt = $db->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})");
            $stmt->execute($ids);
            $affected = $stmt->rowCount();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    $auth->logActivity($_SESSION['user_id'], 'bulk_action', "Performed {$action} on {$affected} {$type}s", [
        'action' => $action,
        'type' => $type,
        'ids' => $ids,
        'affected' => $affected
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully {$action}d {$affected} {$type}(s)"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to perform bulk action: ' . $e->getMessage()
    ]);
}
?>
