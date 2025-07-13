<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Valid email address is required']);
    exit;
}

try {
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM newsletter_subscribers WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already subscribed']);
        exit;
    }
    
    // Add to newsletter
    $stmt = $db->prepare("
        INSERT INTO newsletter_subscribers (email, subscribed_at, status, ip_address) 
        VALUES (?, NOW(), 'active', ?)
    ");
    $stmt->execute([$email, $_SERVER['REMOTE_ADDR']]);
    
    // Send welcome email (optional)
    $subject = "Welcome to Forever Young Tours Newsletter";
    $message = "
        <h2>Welcome to Forever Young Tours!</h2>
        <p>Thank you for subscribing to our newsletter. You'll now receive:</p>
        <ul>
            <li>Exclusive travel deals and discounts</li>
            <li>Destination guides and travel tips</li>
            <li>Early access to new tour packages</li>
            <li>Special offers for group bookings</li>
        </ul>
        <p>We're excited to help you discover amazing destinations!</p>
        <p>Best regards,<br>The Forever Young Tours Team</p>
    ";
    
    sendEmail($email, $subject, $message);
    
    echo json_encode(['success' => true, 'message' => 'Successfully subscribed to newsletter']);
    
} catch (Exception $e) {
    error_log("Newsletter subscription error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
