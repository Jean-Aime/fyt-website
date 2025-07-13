<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Now just use sanitizeInput() freely, since it's already defined in config.php


$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // Check user credentials
        $stmt = $db->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ? AND u.status = 'active'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Reset login attempts
            $db->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?")
                ->execute([$user['id']]);

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = $user['role_name'];

            // Update last login
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                ->execute([$user['id']]);

            // Set remember me cookie
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');

                // Store token in database
                $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?")
                    ->execute([$token, $user['id']]);
            }

            // Redirect to intended page or dashboard
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '/webtour/client/dashboard.php';
            header("Location: $redirect");
            exit;



            header("Location: $redirect");
            exit;



            header("Location: $redirect");
            exit;
        } else {
            // Increment login attempts
            if ($user) {
                $attempts = $user['login_attempts'] + 1;
                $locked_until = null;

                if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                    $locked_until = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
                }

                $db->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?")
                    ->execute([$attempts, $locked_until, $user['id']]);
            }

            $error = 'Invalid email or password';
        }
    }
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms = isset($_POST['terms']);

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!$terms) {
        $error = 'Please accept the terms and conditions';
    } else {
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'An account with this email already exists';
        } else {
            // Get client role ID
            $role_stmt = $db->prepare("SELECT id FROM roles WHERE name = 'client'");
            $role_stmt->execute();
            $client_role = $role_stmt->fetch();

            // Generate unique username
            $base_username = strtolower(preg_replace('/[^a-z0-9]/', '', $first_name . $last_name));
            $username = $base_username;
            $suffix = 1;

            $check_stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            while (true) {
                $check_stmt->execute([$username]);
                if (!$check_stmt->fetch())
                    break;
                $username = $base_username . $suffix;
                $suffix++;
            }

            // Create user account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $verification_token = bin2hex(random_bytes(32));

            $stmt = $db->prepare("
                INSERT INTO users (first_name, last_name, email, phone, username, password_hash, role_id, 
                                   email_verification_token, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");

            if (
                $stmt->execute([
                    $first_name,
                    $last_name,
                    $email,
                    $phone,
                    $username,
                    $password_hash,
                    $client_role['id'],
                    $verification_token
                ])
            ) {
                $user_id = $db->lastInsertId();

                // Send welcome email
                $subject = "Welcome to Forever Young Tours!";
                $message = "
                    <h2>Welcome to Forever Young Tours!</h2>
                    <p>Dear $first_name,</p>
                    <p>Thank you for joining our community of adventurous travelers!</p>
                    <p>Your account has been created successfully. You can now:</p>
                    <ul>
                        <li>Browse and book amazing tours</li>
                        <li>Manage your bookings</li>
                        <li>Access exclusive member offers</li>
                        <li>Connect with fellow travelers</li>
                    </ul>
                    <p>Start exploring our destinations and book your next adventure!</p>
                    <p><a href='" . SITE_URL . "/tours.php' style='background: #d4a574; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Explore Tours</a></p>
                    <p>Happy travels!<br>The Forever Young Tours Team</p>
                ";

                sendEmail($email, $subject, $message);

                // Log activity
                $db->prepare("
                    INSERT INTO user_activity_logs (user_id, action, description, ip_address, user_agent) 
                    VALUES (?, 'register', 'User registered', ?, ?)
                ")->execute([
                            $user_id,
                            $_SERVER['REMOTE_ADDR'],
                            $_SERVER['HTTP_USER_AGENT']
                        ]);

                $success = 'Account created successfully! You can now log in.';
            } else {
                $error = 'Error creating account. Please try again.';
            }
        }
    }
}

$page_title = 'Login & Register - Forever Young Tours';
?>

<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <link rel="stylesheet" href="/webtour/assets/css/style.css">
    <link rel="stylesheet" href="/webtour/assets/css/auth.css">
    <link rel="stylesheet" href="/webtour/assets/css/client-portal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">
</head>

<body class="auth-page">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-tabs">
                    <button class="tab-btn active" data-tab="login">Login</button>
                    <button class="tab-btn" data-tab="register">Register</button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <div class="tab-content active" id="login">
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="form-header">
                            <h2>Welcome Back</h2>
                            <p>Sign in to your account to continue your journey</p>
                        </div>

                        <div class="form-group">
                            <label for="login_email">Email Address</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="login_email" name="email" required
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    placeholder="Enter your email">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="login_password">Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="login_password" name="password" required
                                    placeholder="Enter your password">
                                <button type="button" class="password-toggle"
                                    onclick="togglePassword('login_password')">
                                    <i class="fas fa-eye"></i>
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

                        <button type="submit" name="login" class="btn btn-primary btn-block">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </button>

                        <div class="social-login">
                            <p>Or sign in with</p>
                            <div class="social-buttons">
                                <button type="button" class="btn btn-social btn-google">
                                    <i class="fab fa-google"></i> Google
                                </button>
                                <button type="button" class="btn btn-social btn-facebook">
                                    <i class="fab fa-facebook-f"></i> Facebook
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Register Form -->
                <div class="tab-content" id="register">
                    <form method="POST" class="auth-form"
                        action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="form-header">
                            <h2>Join Forever Young Tours</h2>
                            <p>Create your account and start exploring the world</p>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="first_name" name="first_name" required
                                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                                        placeholder="First name">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <div class="input-group">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="last_name" name="last_name" required
                                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                                        placeholder="Last name">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="register_email">Email Address</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="register_email" name="email" required
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    placeholder="Enter your email">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="phone" name="phone"
                                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                    placeholder="Enter your phone number">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="register_password">Password</label>
                                <div class="input-group">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="register_password" name="password" required
                                        placeholder="Create password">
                                    <button type="button" class="password-toggle"
                                        onclick="togglePassword('register_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <div class="input-group">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" required
                                        placeholder="Confirm password">
                                    <button type="button" class="password-toggle"
                                        onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms" required>
                                <span class="checkmark"></span>
                                I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a>
                                and <a href="privacy.php" target="_blank">Privacy Policy</a>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="newsletter">
                                <span class="checkmark"></span>
                                Subscribe to our newsletter for travel tips and exclusive offers
                            </label>
                        </div>

                        <button type="submit" name="register" class="btn btn-primary btn-block">
                            <i class="fas fa-user-plus"></i> Create Account
                        </button>

                        <div class="social-login">
                            <p>Or sign up with</p>
                            <div class="social-buttons">
                                <button type="button" class="btn btn-social btn-google">
                                    <i class="fab fa-google"></i> Google
                                </button>
                                <button type="button" class="btn btn-social btn-facebook">
                                    <i class="fab fa-facebook-f"></i> Facebook
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const tab = this.dataset.tab;

                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Update active tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tab).classList.add('active');
            });
        });

        // Password toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('.password-toggle i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Password strength checker
        document.getElementById('register_password').addEventListener('input', function () {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');

            let strength = 0;
            let feedback = [];

            if (password.length >= 8) strength++;
            else feedback.push('At least 8 characters');

            if (/[a-z]/.test(password)) strength++;
            else feedback.push('Lowercase letter');

            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('Uppercase letter');

            if (/[0-9]/.test(password)) strength++;
            else feedback.push('Number');

            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('Special character');

            const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const strengthColors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997'];

            if (password.length > 0) {
                strengthDiv.innerHTML = `
                    <div class="strength-bar">
                        <div class="strength-fill" style="width: ${(strength / 5) * 100}%; background: ${strengthColors[strength - 1] || strengthColors[0]}"></div>
                    </div>
                    <div class="strength-text" style="color: ${strengthColors[strength - 1] || strengthColors[0]}">
                        ${strengthLevels[strength - 1] || strengthLevels[0]}
                        ${feedback.length > 0 ? ' - Missing: ' + feedback.join(', ') : ''}
                    </div>
                `;
            } else {
                strengthDiv.innerHTML = '';
            }
        });

        // Form validation
        document.querySelectorAll('.auth-form').forEach(form => {
            form.addEventListener('submit', function (e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('error');
                        isValid = false;
                    } else {
                        field.classList.remove('error');
                    }
                });

                // Password confirmation check
                const password = this.querySelector('[name="password"]');
                const confirmPassword = this.querySelector('[name="confirm_password"]');

                if (password && confirmPassword && password.value !== confirmPassword.value) {
                    confirmPassword.classList.add('error');
                    isValid = false;
                    alert('Passwords do not match');
                }

                if (!isValid) {
                    e.preventDefault();
                }
            });
        });

        // Auto-switch to register tab if there's a registration error
        <?php if (isset($_POST['register']) && $error): ?>
            document.querySelector('[data-tab="register"]').click();
        <?php endif; ?>
    </script>
</body>

</html>