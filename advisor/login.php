<?php
require_once '../config/config.php';
require_once('../includes/secure_auth.php');

$auth = new SecureAuth($db);

// Redirect if already logged in
if ($auth->validateSession()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $result = $auth->login($username, $password, $remember);
        
        if ($result['success']) {
            // Check if user is an advisor
            $stmt = $db->prepare("
                SELECT ca.id, ca.status, ca.training_completed 
                FROM certified_advisors ca 
                JOIN users u ON ca.user_id = u.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $advisor = $stmt->fetch();
            
            if ($advisor && in_array($advisor['status'], ['active', 'training'])) {
                $_SESSION['advisor_id'] = $advisor['id'];
                $_SESSION['training_completed'] = $advisor['training_completed'];
                
                // Redirect to training if not completed
                if (!$advisor['training_completed'] && $advisor['status'] === 'training') {
                    header('Location: training.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            } else {
                $auth->logout();
                $error = 'Access denied. You are not an active advisor.';
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advisor Login - Forever Young Tours</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="../assets/images/logo.png" alt="Forever Young Tours" class="auth-logo">
                <h1>Advisor Portal</h1>
                <p>Certified Travel Advisor Login</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="auth-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Want to become an advisor? <a href="apply.php">Apply Now</a></p>
                <p>Need help? <a href="mailto:support@foreveryoungtours.com">Contact Support</a></p>
                <p><a href="../index.php">← Back to Main Site</a></p>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordEye = document.getElementById('password-eye');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordEye.classList.remove('fa-eye');
                passwordEye.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordEye.classList.remove('fa-eye-slash');
                passwordEye.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
