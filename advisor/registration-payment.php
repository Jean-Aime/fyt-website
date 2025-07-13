<?php
require_once '../config/config.php';
require_once '../includes/secure_auth.php';

// Check if registration was completed
if (!isset($_SESSION['advisor_registration_success']) || !isset($_SESSION['advisor_code'])) {
    header('Location: registration.php');
    exit;
}

$advisor_code = $_SESSION['advisor_code'];
$tracking_number = $_SESSION['advisor_tracking_number'];
$user_id = $_SESSION['advisor_user_id'];
$registration_fee = $_SESSION['advisor_registration_fee'] ?? 59.00;

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $payment_method = $_POST['payment_method'] ?? '';
    $payment_reference = $_POST['payment_reference'] ?? '';
    
    if (empty($payment_method)) {
        $error = 'Please select a payment method';
    } elseif (empty($payment_reference)) {
        $error = 'Please provide payment reference/transaction ID';
    } else {
        try {
            $db->beginTransaction();
            
            // Update Certified Advisor with payment information
            $stmt = $db->prepare("
                UPDATE certified_advisors 
                SET registration_fee_paid = 1, 
                    registration_payment_date = CURDATE(),
                    status = 'active'
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            
            // Update user status
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Create payment record
            $stmt = $db->prepare("
                INSERT INTO payment_transactions (
                    user_id, transaction_type, amount, payment_method, 
                    payment_reference, status, description
                ) VALUES (?, 'advisor_registration', ?, ?, ?, 'completed', ?)
            ");
            $stmt->execute([
                $user_id, $registration_fee, $payment_method, $payment_reference,
                "Advisor Registration Fee - Code: {$advisor_code}"
            ]);
            
            $db->commit();
            
            // Clear session data
            unset($_SESSION['advisor_registration_success']);
            unset($_SESSION['advisor_code']);
            unset($_SESSION['advisor_tracking_number']);
            unset($_SESSION['advisor_user_id']);
            unset($_SESSION['advisor_registration_fee']);
            
            // Set success message
            $_SESSION['advisor_payment_success'] = true;
            $_SESSION['new_advisor_code'] = $advisor_code;
            
            header('Location: registration-complete.php');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Payment processing failed: ' . $e->getMessage();
        }
    }
}

$page_title = 'Advisor Registration Payment - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .registration-summary {
            background: linear-gradient(135deg, #228B22, #1e7e1e);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .advisor-code {
            font-size: 1.5em;
            font-weight: bold;
            margin: 10px 0;
            font-family: monospace;
        }
        
        .payment-methods {
            margin: 30px 0;
        }
        
        .payment-method {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method:hover {
            border-color: #228B22;
        }
        
        .payment-method.selected {
            border-color: #228B22;
            background: #f0f8f0;
        }
        
        .payment-method input[type="radio"] {
            margin-right: 10px;
        }
        
        .payment-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: none;
        }
        
        .payment-details.active {
            display: block;
        }
        
        .amount-breakdown {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .amount-total {
            border-top: 2px solid #228B22;
            padding-top: 10px;
            font-weight: bold;
            font-size: 1.2em;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #228B22;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #228B22, #1e7e1e);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 139, 34, 0.3);
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <img src="../assets/images/logo.png" alt="Forever Young Tours" class="logo">
            <h1>Complete Your Advisor Registration</h1>
            <p>Payment Required</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="payment-container">
            <div class="registration-summary">
                <h2><i class="fas fa-certificate"></i> Registration Successful!</h2>
                <p>Your Advisor Code:</p>
                <div class="advisor-code"><?php echo htmlspecialchars($advisor_code); ?></div>
                <p>Tracking Number: <?php echo htmlspecialchars($tracking_number); ?></p>
            </div>
            
            <div class="amount-breakdown">
                <h3><i class="fas fa-receipt"></i> Payment Summary</h3>
                <div class="amount-row amount-total">
                    <span>Advisor Registration Fee:</span>
                    <span>$<?php echo number_format($registration_fee, 2); ?></span>
                </div>
            </div>
            
            <form method="POST">
                <div class="payment-methods">
                    <h3><i class="fas fa-credit-card"></i> Select Payment Method</h3>
                    
                    <div class="payment-method" onclick="selectPaymentMethod('bank_transfer')">
                        <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer">
                        <label for="bank_transfer">
                            <strong><i class="fas fa-university"></i> Bank Transfer</strong><br>
                            <small>Direct bank transfer to FYT account</small>
                        </label>
                    </div>
                    
                    <div class="payment-method" onclick="selectPaymentMethod('mobile_money')">
                        <input type="radio" name="payment_method" value="mobile_money" id="mobile_money">
                        <label for="mobile_money">
                            <strong><i class="fas fa-mobile-alt"></i> Mobile Money</strong><br>
                            <small>MTN Mobile Money, Airtel Money, etc.</small>
                        </label>
                    </div>
                    
                    <div class="payment-method" onclick="selectPaymentMethod('paypal')">
                        <input type="radio" name="payment_method" value="paypal" id="paypal">
                        <label for="paypal">
                            <strong><i class="fab fa-paypal"></i> PayPal</strong><br>
                            <small>Pay securely with PayPal</small>
                        </label>
                    </div>
                    
                    <div class="payment-method" onclick="selectPaymentMethod('crypto')">
                        <input type="radio" name="payment_method" value="crypto" id="crypto">
                        <label for="crypto">
                            <strong><i class="fab fa-bitcoin"></i> Cryptocurrency</strong><br>
                            <small>Bitcoin, USDT, or other cryptocurrencies</small>
                        </label>
                    </div>
                </div>
                
                <!-- Payment Details Sections -->
                <div id="bank_transfer_details" class="payment-details">
                    <h4>Bank Transfer Details</h4>
                    <p><strong>Bank:</strong> Bank of Kigali</p>
                    <p><strong>Account Name:</strong> Forever Young Tours Ltd</p>
                    <p><strong>Account Number:</strong> 00012345678</p>
                    <p><strong>SWIFT Code:</strong> BKRWRWRW</p>
                    <p><strong>Reference:</strong> ADV-<?php echo $advisor_code; ?></p>
                </div>
                
                <div id="mobile_money_details" class="payment-details">
                    <h4>Mobile Money Details</h4>
                    <p><strong>MTN Mobile Money:</strong> *182*8*1*<?php echo $registration_fee; ?>*250788123456#</p>
                    <p><strong>Airtel Money:</strong> *185*9*<?php echo $registration_fee; ?>*250733123456#</p>
                    <p><strong>Reference:</strong> ADV-<?php echo $advisor_code; ?></p>
                </div>
                
                <div id="paypal_details" class="payment-details">
                    <h4>PayPal Payment</h4>
                    <p><strong>PayPal Email:</strong> payments@foreveryoungtours.com</p>
                    <p><strong>Amount:</strong> $<?php echo number_format($registration_fee, 2); ?> USD</p>
                    <p><strong>Reference:</strong> ADV-<?php echo $advisor_code; ?></p>
                </div>
                
                <div id="crypto_details" class="payment-details">
                    <h4>Cryptocurrency Payment</h4>
                    <p><strong>Bitcoin Address:</strong> 1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa</p>
                    <p><strong>USDT (TRC20):</strong> TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE</p>
                    <p><strong>Amount:</strong> $<?php echo number_format($registration_fee, 2); ?> USD equivalent</p>
                    <p><strong>Reference:</strong> ADV-<?php echo $advisor_code; ?></p>
                </div>
                
                <div class="form-group">
                    <label for="payment_reference">Payment Reference/Transaction ID <span style="color: red;">*</span></label>
                    <input type="text" id="payment_reference" name="payment_reference" class="form-control" 
                           placeholder="Enter your payment reference or transaction ID" required>
                    <small>Please provide the transaction ID or reference number from your payment</small>
                </div>
                
                <button type="submit" name="confirm_payment" class="submit-btn">
                    <i class="fas fa-check-circle"></i> Confirm Payment & Activate Account
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="registration.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Registration
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function selectPaymentMethod(method) {
            // Remove selected class from all methods
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Hide all payment details
            document.querySelectorAll('.payment-details').forEach(el => {
                el.classList.remove('active');
            });
            
            // Select the clicked method
            document.querySelector(`input[value="${method}"]`).checked = true;
            document.querySelector(`input[value="${method}"]`).closest('.payment-method').classList.add('selected');
            
            // Show corresponding payment details
            document.getElementById(`${method}_details`).classList.add('active');
        }
    </script>
</body>
</html>
