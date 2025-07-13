<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];
    
    // Get recent notifications
    $stmt = $db->prepare("
        SELECT id, type, title, message, data, read_at, created_at,
               CASE 
                   WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_at, NOW()), ' minutes ago')
                   WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN CONCAT(TIMESTAMPDIFF(HOUR, created_at, NOW()), ' hours ago')
                   WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK) THEN CONCAT(TIMESTAMPDIFF(DAY, created_at, NOW()), ' days ago')
                   ELSE DATE_FORMAT(created_at, '%M %d, %Y')
               END as time_ago
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add icons based on notification type
    foreach ($notifications as &$notification) {
        switch ($notification['type']) {
            case 'booking_created':
                $notification['icon'] = 'calendar-plus';
                break;
            case 'payment_received':
                $notification['icon'] = 'credit-card';
                break;
            case 'user_registered':
                $notification['icon'] = 'user-plus';
                break;
            case 'system_alert':
                $notification['icon'] = 'exclamation-triangle';
                break;
            default:
                $notification['icon'] = 'info-circle';
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    error_log("Get notifications error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load notifications'
    ]);
}
?>
