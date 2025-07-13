<?php
require_once '../../config/config.php';
require_once('../../includes/secure_auth.php');

$auth = new SecureAuth($db);
requireLogin();
requirePermission('roles.view');

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Role ID required']);
    exit;
}

$role_id = (int) $_GET['id'];

try {
    $stmt = $db->prepare("SELECT * FROM user_roles WHERE id = ?");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'Role not found']);
        exit;
    }

    echo json_encode(['success' => true, 'role' => $role]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching role: ' . $e->getMessage()]);
}
?>
