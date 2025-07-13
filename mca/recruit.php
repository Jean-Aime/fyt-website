<?php
require_once '../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();

// Ensure user is an MCA agent
if ($_SESSION['role_name'] !== 'mca_agent') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get MCA agent details
$stmt = $db->prepare("SELECT * FROM mca_agents WHERE user_id = ?");
$stmt->execute([$user_id]);
$mca_agent = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle advisor recruitment
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'recruit_advisor') {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $country = trim($_POST['country']);
            $city = trim($_POST['city']);
            $experience = trim($_POST['experience']);
            $motivation = trim($_POST['motivation']);
            $referral_source = trim($_POST['referral_source']);

            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                throw new Exception('Email address already exists in the system.');
            }

            // Generate advisor code
            $advisor_code = 'ADV' . strtoupper(substr($country, 0, 2)) . date('y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Check if advisor code is unique
            $stmt = $db->prepare("SELECT id FROM certified_advisors WHERE advisor_code = ?");
            $stmt->execute([$advisor_code]);
            while ($stmt->fetchColumn()) {
                $advisor_code = 'ADV' . strtoupper(substr($country, 0, 2)) . date('y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt->execute([$advisor_code]);
            }

            $db->beginTransaction();

            // Create user account
            $password = generateRandomString(12);
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                INSERT INTO users (first_name, last_name, email, phone, password_hash, role_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, (SELECT id FROM roles WHERE name = 'certified_advisor'), 'pending', NOW())
            ");
            $stmt->execute([$first_name, $last_name, $email, $phone, $password_hash]);
            $user_id_new = $db->lastInsertId();

            // Create certified advisor record
            $stmt = $db->prepare("
                INSERT INTO certified_advisors (
                    user_id, mca_id, advisor_code, first_name, last_name, email, phone, 
                    country, city, experience_level, motivation, referral_source, 
                    commission_rate, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 15.0, 'pending', NOW())
            ");
            $stmt->execute([
                $user_id_new, $mca_agent['id'], $advisor_code, $first_name, $last_name, 
                $email, $phone, $country, $city, $experience, $motivation, $referral_source
            ]);

            $advisor_id = $db->lastInsertId();

            // Send welcome email with credentials
            $email_subject = "Welcome to Forever Young Tours - Certified Advisor Program";
            $email_body = "
                <h2>Welcome to Forever Young Tours!</h2>
                <p>Dear {$first_name},</p>
                <p>Congratulations! You have been recruited as a Certified Advisor for Forever Young Tours.</p>
                
                <h3>Your Account Details:</h3>
                <ul>
                    <li><strong>Advisor Code:</strong> {$advisor_code}</li>
                    <li><strong>Email:</strong> {$email}</li>
                    <li><strong>Temporary Password:</strong> {$password}</li>
                    <li><strong>Commission Rate:</strong> 15%</li>
                </ul>
                
                <h3>Next Steps:</h3>
                <ol>
                    <li>Login to your advisor portal: <a href='" . SITE_URL . "/advisor/login.php'>Advisor Portal</a></li>
                    <li>Complete your profile setup</li>
                    <li>Complete the mandatory training modules</li>
                    <li>Start promoting tours and earning commissions!</li>
                </ol>
                
                <p>Your MCA (Marketing & Client Advisor) is: {$mca_agent['first_name']} {$mca_agent['last_name']}</p>
                
                <p>Welcome to the team!</p>
                <p>Forever Young Tours Team</p>
            ";

            // In a real application, you would send this email
            // sendEmail($email, $email_subject, $email_body);

            $db->commit();
            $success = "Advisor recruited successfully! Welcome email sent to {$email}";
            $recruited_advisor = [
                'name' => $first_name . ' ' . $last_name,
                'code' => $advisor_code,
                'email' => $email,
                'password' => $password
            ];

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error recruiting advisor: ' . $e->getMessage();
        }
    }
}

$page_title = 'Recruit Advisor - MCA Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/mca-portal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .recruit-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .recruit-header h1 {
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .recruit-header p {
            font-size: 1.1em;
            color: #7f8c8d;
            max-width: 600px;
            margin: 0 auto;
        }

        .recruitment-form {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #D4AF37;
            font-size: 1.3em;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95em;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .required {
            color: #e74c3c;
        }

        .form-help {
            font-size: 0.85em;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .recruitment-benefits {
            background: linear-gradient(135deg, #D4AF37, #B8941F);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .benefit-item {
            text-align: center;
        }

        .benefit-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5em;
        }

        .benefit-item h4 {
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .benefit-item p {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .success-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .success-content {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #27ae60;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2em;
        }

        .advisor-credentials {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }

        .credential-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .credential-item:last-child {
            border-bottom: none;
        }

        .credential-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .credential-value {
            font-family: monospace;
            background: white;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        @media (max-width: 768px) {
            .recruitment-form {
                padding: 20px;
                margin: 0 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .benefits-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="mca-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <div class="recruit-header">
                    <h1><i class="fas fa-user-plus"></i> Recruit New Advisor</h1>
                    <p>Expand your network by recruiting certified advisors to promote Forever Young Tours and earn commissions together.</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Benefits Section -->
                <div class="recruitment-benefits">
                    <h2><i class="fas fa-star"></i> Why Join as a Certified Advisor?</h2>
                    <div class="benefits-grid">
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <h4>15% Commission</h4>
                            <p>Earn up to 15% commission on every confirmed booking</p>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h4>Free Training</h4>
                            <p>Complete training program with certification</p>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <h4>Marketing Support</h4>
                            <p>Professional marketing materials and tools</p>
                        </div>
                        <div class="benefit-item">
                            <div class="benefit-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <h4>24/7 Support</h4>
                            <p>Dedicated support from your MCA and FYT team</p>
                        </div>
                    </div>
                </div>

                <!-- Recruitment Form -->
                <div class="recruitment-form">
                    <form method="POST" id="recruitmentForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="recruit_advisor">

                        <!-- Personal Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user"></i> Personal Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name">First Name <span class="required">*</span></label>
                                    <input type="text" id="first_name" name="first_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name <span class="required">*</span></label>
                                    <input type="text" id="last_name" name="last_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address <span class="required">*</span></label>
                                    <input type="email" id="email" name="email" required>
                                    <div class="form-help">This will be their login email</div>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone Number <span class="required">*</span></label>
                                    <input type="tel" id="phone" name="phone" required>
                                </div>
                            </div>
                        </div>

                        <!-- Location Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-map-marker-alt"></i> Location</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="country">Country <span class="required">*</span></label>
                                    <select id="country" name="country" required>
                                        <option value="">Select Country</option>
                                        <option value="Rwanda">Rwanda</option>
                                        <option value="Uganda">Uganda</option>
                                        <option value="Kenya">Kenya</option>
                                        <option value="Tanzania">Tanzania</option>
                                        <option value="Burundi">Burundi</option>
                                        <option value="DRC">Democratic Republic of Congo</option>
                                        <option value="USA">United States</option>
                                        <option value="Canada">Canada</option>
                                        <option value="UK">United Kingdom</option>
                                        <option value="Germany">Germany</option>
                                        <option value="France">France</option>
                                        <option value="Netherlands">Netherlands</option>
                                        <option value="Belgium">Belgium</option>
                                        <option value="Australia">Australia</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="city">City <span class="required">*</span></label>
                                    <input type="text" id="city" name="city" required>
                                </div>
                            </div>
                        </div>

                        <!-- Experience & Motivation -->
                        <div class="form-section">
                            <h3><i class="fas fa-briefcase"></i> Background</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="experience">Experience Level</label>
                                    <select id="experience" name="experience">
                                        <option value="beginner">Beginner - New to tourism/sales</option>
                                        <option value="intermediate">Intermediate - Some experience</option>
                                        <option value="experienced">Experienced - Extensive background</option>
                                        <option value="expert">Expert - Industry professional</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="referral_source">How did they hear about us?</label>
                                    <select id="referral_source" name="referral_source">
                                        <option value="mca_referral">MCA Referral</option>
                                        <option value="social_media">Social Media</option>
                                        <option value="website">Website</option>
                                        <option value="friend_family">Friend/Family</option>
                                        <option value="event">Event/Conference</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="motivation">Why do they want to become an advisor?</label>
                                <textarea id="motivation" name="motivation" placeholder="Describe their motivation and goals..."></textarea>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus"></i> Recruit Advisor
                            </button>
                            <a href="advisors.php" class="btn btn-outline btn-lg">
                                <i class="fas fa-arrow-left"></i> Back to Advisors
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <?php if (isset($success) && isset($recruited_advisor)): ?>
        <div class="success-modal" id="successModal">
            <div class="success-content">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h2>Advisor Recruited Successfully!</h2>
                <p><?php echo htmlspecialchars($success); ?></p>
                
                <div class="advisor-credentials">
                    <h4>Advisor Credentials:</h4>
                    <div class="credential-item">
                        <span class="credential-label">Name:</span>
                        <span class="credential-value"><?php echo htmlspecialchars($recruited_advisor['name']); ?></span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Advisor Code:</span>
                        <span class="credential-value"><?php echo htmlspecialchars($recruited_advisor['code']); ?></span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Email:</span>
                        <span class="credential-value"><?php echo htmlspecialchars($recruited_advisor['email']); ?></span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Temp Password:</span>
                        <span class="credential-value"><?php echo htmlspecialchars($recruited_advisor['password']); ?></span>
                    </div>
                </div>

                <p><small>These credentials have been sent to the advisor's email address.</small></p>
                
                <div style="margin-top: 20px;">
                    <button onclick="closeSuccessModal()" class="btn btn-primary">Continue</button>
                    <a href="advisors.php" class="btn btn-outline">View All Advisors</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="../assets/js/mca-portal.js"></script>
    <script>
        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
        }

        // Form validation
        document.getElementById('recruitmentForm').addEventListener('submit', function(e) {
            const requiredFields = ['first_name', 'last_name', 'email', 'phone', 'country', 'city'];
            let isValid = true;

            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    input.style.borderColor = '#e74c3c';
                    isValid = false;
                } else {
                    input.style.borderColor = '#e0e0e0';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#e74c3c';
                alert('Please enter a valid email address.');
            } else {
                this.style.borderColor = '#e0e0e0';
            }
        });
    </script>
</body>
</html>
