<?php
require_once '../config/config.php';
require_once '../classes/PaymentGateway.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$booking_id = (int)($_POST['booking_id'] ?? 0);
$payment_method = $_POST['payment_method'] ?? '';
$amount = (float)($_POST['amount'] ?? 0);

if (!$booking_id || !$payment_method || !$amount) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get booking details
$stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

$payment_gateway = new PaymentGateway($db);

try {
    switch ($payment_method) {
        case 'stripe':
            $result = $payment_gateway->createStripePaymentIntent(
                $amount,
                'USD',
                [
                    'booking_id' => $booking_id,
                    'booking_reference' => $booking['booking_reference']
                ]
            );
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'client_secret' => $result['client_secret'],
                    'payment_intent_id' => $result['payment_intent_id']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['error']]);
            }
            break;
            
        case 'paypal':
            $return_url = SITE_URL . '/payment/success.php?booking_id=' . $booking_id;
            $cancel_url = SITE_URL . '/payment/cancel.php?booking_id=' . $booking_id;
            
            $result = $payment_gateway->createPayPalOrder($amount, 'USD', $return_url, $cancel_url);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'order_id' => $result['order_id'],
                    'approval_url' => $result['approval_url']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['error']]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
            break;
    }
} catch (Exception $e) {
    error_log("Payment processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment processing failed']);
}
?>
