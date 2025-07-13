<?php
require_once '../config/config.php';
require_once '../classes/PaymentProcessor.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $paymentProcessor = new PaymentProcessor($db);
    
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $payment_method_id = (int)($_POST['payment_method_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';
    
    // Collect payment-specific data
    $payment_data = [
        'booking_id' => $booking_id
    ];
    
    // Add method-specific data
    switch ($_POST['payment_method'] ?? '') {
        case 'stripe':
            $payment_data['payment_intent_id'] = $_POST['payment_intent_id'] ?? '';
            break;
            
        case 'paypal':
            $payment_data['order_id'] = $_POST['order_id'] ?? '';
            break;
            
        case 'mtn_mobile_money':
        case 'airtel_money':
            $payment_data['phone_number'] = $_POST['phone_number'] ?? '';
            $payment_data['country'] = $_POST['country'] ?? 'RW';
            break;
            
        case 'bank_transfer':
            $payment_data['bank_reference'] = $_POST['bank_reference'] ?? '';
            break;
    }
    
    // Validation
    if (!$booking_id || !$payment_method_id || !$amount) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    // Verify booking exists and belongs to user
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }
    
    // Check if user has permission to pay for this booking
    if (isset($_SESSION['user_id']) && $booking['user_id'] != $_SESSION['user_id']) {
        // Check if user is admin or agent
        if (!hasPermission('payments.process')) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    }
    
    // Process payment
    $result = $paymentProcessor->processPayment($booking_id, $payment_method_id, $amount, $currency, $payment_data);
    
    if ($result['success']) {
        // Log activity
        if (isset($_SESSION['user_id'])) {
            $auth = new SecureAuth($db);
            $auth->logActivity($_SESSION['user_id'], 'payment_initiated', "Payment initiated for booking: {$booking['booking_reference']}");
        }
        
        echo json_encode([
            'success' => true,
            'payment_id' => $result['payment_id'],
            'result' => $result['result']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['error']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Payment API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment processing failed. Please try again.']);
}
?>
