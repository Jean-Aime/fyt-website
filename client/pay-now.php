<?php
// Show any PHP errors clearly (remove on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Ensure user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../login.php');
    exit;
}

// Load config and DB connection
require_once __DIR__ . '/../config/config.php';

// Get user ID
$user_id = $_SESSION['user_id'];

// Check if booking_id or payment_id is provided
$booking_id = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
$payment_id = isset($_GET['payment_id']) ? (int) $_GET['payment_id'] : 0;

// Validate the booking or payment belongs to the user
if ($booking_id > 0) {
    $stmt = $db->prepare("
        SELECT b.*, t.title AS tour_title, 
               COALESCE(SUM(p.amount), 0) AS paid_amount
        FROM bookings b
        LEFT JOIN tours t ON b.tour_id = t.id
        LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'completed'
        WHERE b.id = ? AND b.user_id = ?
        GROUP BY b.id
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $_SESSION['error_message'] = 'Invalid booking or you do not have permission to access it.';
        header('Location: bookings.php');
        exit;
    }

    // Calculate remaining balance
    $remaining_balance = $booking['total_amount'] - $booking['paid_amount'];
} elseif ($payment_id > 0) {
    $stmt = $db->prepare("
        SELECT p.*, b.booking_number, b.total_amount, 
               t.title AS tour_title, 
               COALESCE(SUM(p2.amount), 0) AS paid_amount
        FROM payments p
        JOIN bookings b ON p.booking_id = b.id
        LEFT JOIN tours t ON b.tour_id = t.id
        LEFT JOIN payments p2 ON b.id = p2.booking_id AND p2.status = 'completed'
        WHERE p.id = ? AND b.user_id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$payment_id, $user_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $_SESSION['error_message'] = 'Invalid payment or you do not have permission to access it.';
        header('Location: payments.php');
        exit;
    }

    // Calculate remaining balance for the payment
    $remaining_balance = $payment['amount'];
    $booking_id = $payment['booking_id'];
} else {
    $_SESSION['error_message'] = 'No booking or payment specified.';
    header('Location: bookings.php');
    exit;
}

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate payment amount
    $payment_amount = (float) $_POST['payment_amount'];
    $payment_method = $_POST['payment_method'];

    if ($payment_amount <= 0) {
        $error = "Payment amount must be greater than zero.";
    } elseif (
        ($booking_id && $payment_amount > $remaining_balance) ||
        ($payment_id && $payment_amount > $remaining_balance)
    ) {
        $error = "Payment amount cannot exceed the remaining balance.";
    } else {
        try {
            $db->beginTransaction();

            // Generate payment number
            $payment_number = 'PAY-' . strtoupper(uniqid());

            // Insert payment record
            $stmt = $db->prepare("
                INSERT INTO payments (
                    booking_id, 
                    payment_number, 
                    amount, 
                    payment_method, 
                    status, 
                    payment_date
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $booking_id,
                $payment_number,
                $payment_amount,
                $payment_method
            ]);

            // Get the new payment ID
            $new_payment_id = $db->lastInsertId();

            // Process payment with payment gateway (simulated here)
            // In a real application, you would integrate with Stripe, PayPal, etc.
            $payment_success = true; // Simulate successful payment

            if ($payment_success) {
                // Update payment status to completed
                $stmt = $db->prepare("
                    UPDATE payments 
                    SET status = 'completed', 
                        receipt_url = '/receipts/' . $payment_number . '.pdf'
                    WHERE id = ?
                ");
                $stmt->execute([$new_payment_id]);

                // Update booking status if fully paid
                $stmt = $db->prepare("
                    SELECT COALESCE(SUM(amount), 0) AS total_paid
                    FROM payments
                    WHERE booking_id = ? AND status = 'completed'
                ");
                $stmt->execute([$booking_id]);
                $total_paid = $stmt->fetchColumn();

                $stmt = $db->prepare("
                    SELECT total_amount FROM bookings WHERE id = ?
                ");
                $stmt->execute([$booking_id]);
                $total_amount = $stmt->fetchColumn();

                if ($total_paid >= $total_amount) {
                    $stmt = $db->prepare("
                        UPDATE bookings 
                        SET status = 'confirmed' 
                        WHERE id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$booking_id]);
                }

                $db->commit();

                // Create notification
                $stmt = $db->prepare("
                    INSERT INTO notifications (
                        user_id, 
                        title, 
                        message, 
                        type
                    ) VALUES (?, 'Payment Received', ?, 'payment')
                ");
                $stmt->execute([
                    $user_id,
                    "Your payment of $" . number_format($payment_amount, 2) . " has been received for booking #" . ($payment_id ? $payment['booking_number'] : $booking['booking_number'])
                ]);

                $_SESSION['success_message'] = "Payment of $" . number_format($payment_amount, 2) . " processed successfully!";
                header('Location: ' . ($payment_id ? 'payment-details.php?id=' . $payment_id : 'booking-details.php?id=' . $booking_id));
                exit;
            } else {
                // Simulate payment failure
                $db->rollBack();
                $error = "Payment processing failed. Please try again or contact support.";
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Set page title
$page_title = 'Make Payment - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/client-portal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="client-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <div class="page-header">
                    <h1>Make Payment</h1>
                    <div class="page-actions">
                        <a href="<?php echo $payment_id ? 'payments.php' : 'bookings.php'; ?>" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>

                <div class="payment-container">
                    <div class="payment-summary">
                        <div class="summary-card">
                            <h3>Payment Summary</h3>

                            <div class="summary-item">
                                <span class="summary-label">Booking Reference:</span>
                                <span
                                    class="summary-value">#<?php echo $payment_id ? $payment['booking_number'] : $booking['booking_number']; ?></span>
                            </div>

                            <?php if (isset($booking['tour_title'])): ?>
                                <div class="summary-item">
                                    <span class="summary-label">Tour:</span>
                                    <span
                                        class="summary-value"><?php echo htmlspecialchars($booking['tour_title']); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="summary-item">
                                <span class="summary-label">Total Amount:</span>
                                <span
                                    class="summary-value">$<?php echo number_format($payment_id ? $payment['amount'] : $booking['total_amount'], 2); ?></span>
                            </div>

                            <div class="summary-item">
                                <span class="summary-label">Amount Paid:</span>
                                <span
                                    class="summary-value">$<?php echo number_format($payment_id ? ($payment['amount'] - $remaining_balance) : $booking['paid_amount'], 2); ?></span>
                            </div>

                            <div class="summary-item highlight">
                                <span class="summary-label">Remaining Balance:</span>
                                <span class="summary-value">$<?php echo number_format($remaining_balance, 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="payment-form">
                        <form method="post">
                            <h3>Payment Details</h3>

                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="payment_amount">Payment Amount ($)</label>
                                <input type="number" id="payment_amount" name="payment_amount" min="0.01" step="0.01"
                                    max="<?php echo $remaining_balance; ?>"
                                    value="<?php echo isset($_POST['payment_amount']) ? htmlspecialchars($_POST['payment_amount']) : number_format($remaining_balance, 2); ?>"
                                    required>
                                <small>Maximum: $<?php echo number_format($remaining_balance, 2); ?></small>
                            </div>

                            <div class="form-group">
                                <label for="payment_method">Payment Method</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="">Select payment method</option>
                                    <option value="credit_card" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'credit_card') ? 'selected' : ''; ?>>Credit Card
                                    </option>
                                    <option value="paypal" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'paypal') ? 'selected' : ''; ?>>PayPal</option>
                                    <option value="bank_transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer
                                    </option>
                                </select>
                            </div>

                            <div class="payment-method-details" id="credit_card_details" style="display: none;">
                                <div class="form-group">
                                    <label for="card_number">Card Number</label>
                                    <input type="text" id="card_number" name="card_number"
                                        placeholder="1234 5678 9012 3456">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="expiry_date">Expiry Date</label>
                                        <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY">
                                    </div>

                                    <div class="form-group">
                                        <label for="cvv">CVV</label>
                                        <input type="text" id="cvv" name="cvv" placeholder="123">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="card_name">Name on Card</label>
                                    <input type="text" id="card_name" name="card_name" placeholder="John Doe">
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-credit-card"></i> Submit Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/client-portal.js"></script>
    <script>
        // Update page title in header
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('pageTitle').textContent = 'Make Payment';

            // Show/hide payment method details
            const paymentMethod = document.getElementById('payment_method');
            const creditCardDetails = document.getElementById('credit_card_details');

            paymentMethod.addEventListener('change', function () {
                if (this.value === 'credit_card') {
                    creditCardDetails.style.display = 'block';
                } else {
                    creditCardDetails.style.display = 'none';
                }
            });

            // Trigger change event to show correct details on page load
            paymentMethod.dispatchEvent(new Event('change'));

            // Format payment amount input
            const paymentAmount = document.getElementById('payment_amount');
            paymentAmount.addEventListener('blur', function () {
                const value = parseFloat(this.value);
                if (!isNaN(value)) {
                    this.value = value.toFixed(2);
                }
            });
        });
    </script>
</body>

</html>