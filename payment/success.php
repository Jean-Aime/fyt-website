<?php
require_once '../config/config.php';
require_once '../classes/PaymentGateway.php';

$booking_id = (int)($_GET['booking_id'] ?? 0);
$payment_intent_id = $_GET['payment_intent'] ?? '';
$paypal_order_id = $_GET['token'] ?? '';

if (!$booking_id) {
    header('Location: ../index.php?error=Invalid payment');
    exit;
}

// Get booking details
$stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: ../index.php?error=Booking not found');
    exit;
}

$payment_gateway = new PaymentGateway($db);
$payment_success = false;
$transaction_id = '';
$payment_method = '';

try {
    if ($payment_intent_id) {
        // Stripe payment
        $payment_method = 'stripe';
        $transaction_id = $payment_intent_id;
        $payment_success = true; // Stripe payment is already confirmed
        
    } elseif ($paypal_order_id) {
        // PayPal payment
        $payment_method = 'paypal';
        $result = $payment_gateway->capturePayPalOrder($paypal_order_id);
        
        if ($result['success']) {
            $transaction_id = $result['capture_id'];
            $payment_success = true;
        }
    }
    
    if ($payment_success) {
        // Record payment
        $payment_result = $payment_gateway->recordPayment(
            $booking_id,
            $payment_method,
            $booking['total_amount'],
            'USD',
            $transaction_id,
            'completed'
        );
        
        if ($payment_result['success']) {
            // Update booking status
            $stmt = $db->prepare("UPDATE bookings SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?");
            $stmt->execute([$booking_id]);
            
            // Send confirmation email
            $subject = "Payment Confirmed - " . $booking['booking_reference'];
            $message = "
                <h2>Payment Confirmed!</h2>
                <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
                <p>Your payment has been successfully processed and your booking is now confirmed.</p>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                    <h3>Booking Details</h3>
                    <p><strong>Booking Reference:</strong> {$booking['booking_reference']}</p>
                    <p><strong>Amount Paid:</strong> $" . number_format($booking['total_amount'], 2) . "</p>
                    <p><strong>Payment Method:</strong> " . ucfirst($payment_method) . "</p>
                    <p><strong>Transaction ID:</strong> {$transaction_id}</p>
                </div>
                
                <p>You will receive your travel documents and detailed itinerary 7 days before your departure date.</p>
                <p>Thank you for choosing Forever Young Tours!</p>
            ";
            
            sendEmail($booking['email'], $subject, $message);
            
            $success_message = "Payment successful! Your booking has been confirmed.";
        }
    }
} catch (Exception $e) {
    error_log("Payment success processing error: " . $e->getMessage());
    $error_message = "There was an error processing your payment confirmation.";
}

$page_title = 'Payment Success - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <section class="payment-result">
        <div class="container">
            <div class="result-card">
                <?php if (isset($success_message)): ?>
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1>Payment Successful!</h1>
                    <p><?php echo $success_message; ?></p>
                    
                    <div class="booking-summary">
                        <h3>Booking Summary</h3>
                        <div class="summary-item">
                            <span>Booking Reference:</span>
                            <span><?php echo htmlspecialchars($booking['booking_reference']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Amount Paid:</span>
                            <span>$<?php echo number_format($booking['total_amount'], 2); ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Payment Method:</span>
                            <span><?php echo ucfirst($payment_method); ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Transaction ID:</span>
                            <span><?php echo htmlspecialchars($transaction_id); ?></span>
                        </div>
                    </div>
                    
                    <div class="next-steps">
                        <h3>What's Next?</h3>
                        <ul>
                            <li>You'll receive a confirmation email shortly</li>
                            <li>Travel documents will be sent 7 days before departure</li>
                            <li>Our team will contact you with any additional information</li>
                        </ul>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="../client/dashboard.php" class="btn btn-primary">View My Bookings</a>
                        <a href="../index.php" class="btn btn-outline">Back to Home</a>
                    </div>
                <?php else: ?>
                    <div class="error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h1>Payment Error</h1>
                    <p><?php echo $error_message ?? 'There was an error processing your payment.'; ?></p>
                    
                    <div class="action-buttons">
                        <a href="../book.php?tour=<?php echo $booking['tour_id']; ?>" class="btn btn-primary">Try Again</a>
                        <a href="../contact.php" class="btn btn-outline">Contact Support</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
