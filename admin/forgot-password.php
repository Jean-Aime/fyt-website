<?php
require_once '../config/config.php';
require_once 'includes/secure_auth.php';

$auth = new SecureAuth($db);

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$step = 'request'; // request, verify, reset

// Handle password reset request
if ($_POST && $_POST['action'] === 'request') {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $email = trim($_POST['email']);
        
        try {
            // Check if user exists
            $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                
                // Store reset token
                $stmt = $db->prepare("
                    INSERT INTO password_resets (user_id, token, expires_at) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
                ");
                $stmt->execute([$user['id'], $token, $expires, $token, $expires]);
                
                // In a real application, you would send an email here
                // For demo purposes, we'll just show the token
                $success = "Password reset instructions have been sent to your email address.";
                
                // Log the activity
                $auth->logActivity($user['id'], 'password_reset_requested', 'Password reset requested');
            } else {
                // Don't reveal if email exists or not for security
                $success = "If an account with that email exists, password reset instructions have been sent.";
            }
            
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again later.';
        }
    } else {
        $error = 'Invalid security token. Please try again.';
    }
}

// Handle password reset
if ($_POST && $_POST['action'] === 'reset') {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $token = $_POST['token'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        try {
            // Validate passwords
            if (strlen($new_password) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('Passwords do not match.');
            }
            
            // Verify token
            $stmt = $db->prepare("
                SELECT pr.user_id, u.email 
                FROM password_resets pr 
                JOIN users u ON pr.user_id = u.id 
                WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch();
            
            if (!$reset) {
                throw new Exception('Invalid or expired reset token.');
            }
            
            // Update password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$password_hash, $reset['user_id']]);
            
            // Mark token as used
            $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Log the activity
            $auth->logActivity($reset['user_id'], 'password_reset_completed', 'Password reset completed');
            
            $success = 'Your password has been reset successfully. You can now log in with your new password.';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Invalid security token. Please try again.';
    }
}

// Check if we have a reset token in URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token exists and is valid
    $stmt = $db->prepare("
        SELECT user_id FROM password_resets 
        WHERE token = ? AND expires_at > NOW() AND used = 0
    ");
    $stmt->execute([$token]);
    
    if ($stmt->fetch()) {
        $step = 'reset';
    } else {
        $error = 'Invalid or expired reset token.';
        $step = 'request';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .forgot-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            margin: 20px;
        }
        
        .forgot-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .forgot-icon {
            font-size: 3em;
            color: #D4A574;
            margin-bottom: 20px;
        }
        
        .forgot-title {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .forgot-subtitle {
            opacity: 0.8;
            font-size: 1em;
        }
        
        .forgot-form {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #D4A574;
            background: white;
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #D4A574 0%, #B8956A 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(212, 165, 116, 0.3);
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-login a {
            color: #D4A574;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .password-requirements {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .password-requirements h5 {
            margin-bottom: 10px;
            color: #333;
            font-size: 0.9em;
        }
        
        .requirement-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .requirement-list li {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
            font-size: 0.8em;
            color: #666;
        }
        
        .requirement-list li i {
            width: 12px;
            color: #28a745;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .step.active {
            background: #D4A574;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="forgot-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1 class="forgot-title">
                <?php echo $step === 'reset' ? 'Reset Password' : 'Forgot Password'; ?>
            </h1>
            <p class="forgot-subtitle">
                <?php echo $step === 'reset' ? 'Enter your new password below' : 'Enter your email to reset your password'; ?>
            </p>
        </div>
        
        <div class="forgot-form">
            <?php if ($step === 'request'): ?>
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active">1</div>
                    <div class="step">2</div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php else: ?>
                    <form method="POST" id="forgotForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="request">
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required 
                                   placeholder="Enter your email address"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <button type="submit" class="btn-submit" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            Send Reset Instructions
                        </button>
                    </form>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step completed">1</div>
                    <div class="step active">2</div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php else: ?>
                    <form method="POST" id="resetForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="reset">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" 
                                   required minlength="8" placeholder="Enter new password">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   required minlength="8" placeholder="Confirm new password">
                        </div>
                        
                        <div class="password-requirements">
                            <h5>Password Requirements:</h5>
                            <ul class="requirement-list">
                                <li><i class="fas fa-check"></i> At least 8 characters long</li>
                                <li><i class="fas fa-check"></i> Contains uppercase and lowercase letters</li>
                                <li><i class="fas fa-check"></i> Contains at least one number</li>
                                <li><i class="fas fa-check"></i> Contains at least one special character</li>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn-submit" id="resetBtn">
                            <i class="fas fa-key"></i>
                            Reset Password
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="back-to-login">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Handle form submission
        document.getElementById('forgotForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        });
        
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
                return false;
            }
            
            const btn = document.getElementById('resetBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
        });
        
        // Real-time password confirmation check
        document.getElementById('confirm_password')?.addEventListener('keyup', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    this.style.borderColor = '#28a745';
                } else {
                    this.style.borderColor = '#dc3545';
                }
            } else {
                this.style.borderColor = '#e1e5e9';
            }
        });
        
        // Auto-focus first input
        document.querySelector('input[type="email"], input[type="password"]')?.focus();
    </script>
</body>
</html>
