<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('users.view');

header('Content-Type: application/json');

$user_id = (int)($_GET['user_id'] ?? 0);

if (!$user_id) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT action, description, ip_address, created_at
        FROM user_activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates
    foreach ($activities as &$activity) {
        $activity['created_at'] = date('M j, Y g:i A', strtotime($activity['created_at']));
    }
    
    echo json_encode($activities);
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>
