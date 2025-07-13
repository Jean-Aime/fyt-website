<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('roles.view');

header('Content-Type: application/json');

$role_id = (int)($_GET['role_id'] ?? 0);

if (!$role_id) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$role_id]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($permissions);
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>
