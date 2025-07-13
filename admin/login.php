<?php
require_once '../config/config.php';
require_once(__DIR__ . '/../includes/secure_auth.php');


$auth = new SecureAuth($db);

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_POST) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $remember_me = isset($_POST['remember_me']);

        $result = $auth->login($username, $password, $remember_me);

        if ($result['success']) {
            $redirect = $_GET['redirect'] ?? 'dashboard.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'Invalid security token. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Forever Young Tours</title>
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

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }

        .login-brand {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .brand-logo {
            font-size: 4em;
            color: #D4A574;
            margin-bottom: 20px;
        }

        .brand-title {
            font-size: 2.2em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .brand-subtitle {
            font-size: 1.1em;
            opacity: 0.8;
            margin-bottom: 30px;
        }

        .brand-features {
            list-style: none;
            padding: 0;
        }

        .brand-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 0.95em;
        }

        .brand-features i {
            color: #D4A574;
            width: 20px;
        }

        .login-form {
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-title {
            font-size: 2em;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .form-subtitle {
            color: #666;
            font-size: 1em;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
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

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            margin-top: 12px;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }

        .form-check input {
            width: 18px;
            height: 18px;
        }

        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(212, 165, 116, 0.3);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: #D4A574;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-password a:hover {
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

        .default-credentials {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #0d47a1;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        .default-credentials h4 {
            margin: 0 0 10px 0;
            font-size: 1em;
        }

        .default-credentials p {
            margin: 5px 0;
        }

        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 400px;
                margin: 20px;
            }

            .login-brand {
                padding: 40px 30px;
            }

            .login-form {
                padding: 40px 30px;
            }

            .brand-title {
                font-size: 1.8em;
            }

            .form-title {
                font-size: 1.6em;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-brand">
            <div class="brand-logo">
                <i class="fas fa-mountain"></i>
            </div>
            <h1 class="brand-title">Forever Young Tours</h1>
            <p class="brand-subtitle">Admin Dashboard</p>

            <ul class="brand-features">
                <li>
                    <i class="fas fa-chart-line"></i>
                    <span>Advanced Analytics</span>
                </li>
                <li>
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </li>
                <li>
                    <i class="fas fa-map-marked-alt"></i>
                    <span>Tour Management</span>
                </li>
                <li>
                    <i class="fas fa-calendar-check"></i>
                    <span>Booking System</span>
                </li>
                <li>
                    <i class="fas fa-store"></i>
                    <span>E-commerce Store</span>
                </li>
                <li>
                    <i class="fas fa-handshake"></i>
                    <span>MCA Agent Portal</span>
                </li>
                <li>
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure Platform</span>
                </li>
            </ul>
        </div>

        <div class="login-form">
            <div class="form-header">
                <h2 class="form-title">Welcome Back</h2>
                <p class="form-subtitle">Sign in to your admin account</p>
            </div>

            <!-- Default Credentials Info -->
            <div class="default-credentials">
                <h4><i class="fas fa-info-circle"></i> Default Admin Credentials</h4>
                <p><strong>Email:</strong> admin@iforeveryoung.com</p>
                <p><strong>Password:</strong> 123@Admin</p>
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
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" class="form-control" required
                        value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin@iforeveryoung.com'); ?>"
                        placeholder="Enter your username or email">
                    <i class="fas fa-user input-icon"></i>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required value="123@Admin"
                        placeholder="Enter your password">
                    <i class="fas fa-lock input-icon"></i>
                </div>

                <div class="form-check">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1">
                    <label for="remember_me">Remember me for 30 days</label>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>

            <div class="forgot-password">
                <a href="forgot-password.php">Forgot your password?</a>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
        });

        // Auto-focus username field
        document.getElementById('username').focus();

        // Show/hide password
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('fa-lock')) {
                const passwordField = document.getElementById('password');
                const icon = e.target;

                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    icon.classList.remove('fa-lock');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordField.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-lock');
                }
            }
        });
    </script>
</body>

</html>