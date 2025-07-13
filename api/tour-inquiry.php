<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$tour_id = (int)($_POST['tour_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validation
if (!$tour_id || !$name || !$email || !$phone) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit;
}

try {
    // Get tour details
    $stmt = $db->prepare("SELECT * FROM tours WHERE id = ? AND status = 'active'");
    $stmt->execute([$tour_id]);
    $tour = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tour) {
        echo json_encode(['success' => false, 'message' => 'Tour not found']);
        exit;
    }
    
    // Save inquiry
    $stmt = $db->prepare("
        INSERT INTO tour_inquiries (tour_id, name, email, phone, message, created_at, ip_address, status) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, 'new')
    ");
    $stmt->execute([$tour_id, $name, $email, $phone, $message, $_SERVER['REMOTE_ADDR']]);
    
    // Send confirmation email to customer
    $subject = "Tour Details: " . $tour['title'];
    $customer_message = "
        <h2>Thank you for your interest in {$tour['title']}!</h2>
        <p>Dear {$name},</p>
        <p>Thank you for requesting detailed information about our tour. Here are the complete details:</p>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
            <h3>{$tour['title']}</h3>
            <p><strong>Duration:</strong> {$tour['duration_days']} days, {$tour['duration_nights']} nights</p>
            <p><strong>Group Size:</strong> {$tour['min_group_size']}-{$tour['max_group_size']} people</p>
            <p><strong>Price:</strong> From $" . number_format($tour['price_adult']) . " per adult</p>
            <p><strong>Difficulty:</strong> " . ucfirst($tour['difficulty_level']) . "</p>
        </div>
        
        <h4>Tour Description:</h4>
        <p>{$tour['short_description']}</p>
        
        " . ($tour['full_description'] ? "<div>{$tour['full_description']}</div>" : "") . "
        
        " . ($tour['includes'] ? "<h4>What's Included:</h4><div>{$tour['includes']}</div>" : "") . "
        
        " . ($tour['excludes'] ? "<h4>What's Excluded:</h4><div>{$tour['excludes']}</div>" : "") . "
        
        <p style='margin-top: 30px;'>
            <strong>Ready to book?</strong><br>
            <a href='" . SITE_URL . "/book.php?tour={$tour['id']}' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>Book This Tour</a>
        </p>
        
        <p>If you have any questions, please don't hesitate to contact us:</p>
        <ul>
            <li>Email: info@foreveryoungtours.com</li>
            <li>Phone: +250 123 456 789</li>
            <li>WhatsApp: +250 123 456 789</li>
        </ul>
        
        <p>Best regards,<br>The Forever Young Tours Team</p>
    ";
    
    sendEmail($email, $subject, $customer_message);
    
    // Send notification to admin
    $admin_subject = "New Tour Inquiry: " . $tour['title'];
    $admin_message = "
        <h2>New Tour Inquiry Received</h2>
        <p><strong>Tour:</strong> {$tour['title']}</p>
        <p><strong>Customer:</strong> {$name}</p>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Phone:</strong> {$phone}</p>
        " . ($message ? "<p><strong>Message:</strong><br>{$message}</p>" : "") . "
        <p><strong>IP Address:</strong> {$_SERVER['REMOTE_ADDR']}</p>
        <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
        
        <p><a href='" . SITE_URL . "/admin/bookings/inquiries.php'>View All Inquiries</a></p>
    ";
    
    sendEmail(ADMIN_EMAIL, $admin_subject, $admin_message);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Tour details sent to your email',
        'tour_slug' => $tour['slug']
    ]);
    
} catch (Exception $e) {
    error_log("Tour inquiry error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>
