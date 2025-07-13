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

if (!isset($input['id']) || !isset($input['type']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$id = (int)$input['id'];
$type = $input['type'];
$status = $input['status'];

// Validate status
if (!in_array($status, ['active', 'inactive'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit;
}

try {
    $table = '';
    $permission = '';
    
    switch ($type) {
        case 'tour':
            $table = 'tours';
            $permission = 'tours.edit';
            break;
        case 'booking':
            $table = 'bookings';
            $permission = 'bookings.edit';
            break;
        case 'user':
            $table = 'users';
            $permission = 'users.edit';
            break;
        case 'country':
            $table = 'countries';
            $permission = 'destinations.edit';
            break;
        default:
            throw new Exception('Invalid type');
    }
    
    if (!hasPermission($permission)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    $stmt = $db->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    if ($stmt->rowCount() > 0) {
        $auth->logActivity($_SESSION['user_id'], 'status_changed', "Changed {$type} status to {$status}", [
            'type' => $type,
            'id' => $id,
            'status' => $status
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => ucfirst($type) . ' status updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No changes made'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update status: ' . $e->getMessage()
    ]);
}
?>
