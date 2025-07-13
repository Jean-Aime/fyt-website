<?php
require_once '../config/config.php';
require_once '../includes/secure_auth.php';

$page_title = 'Certified Advisor Registration - Forever Young Tours';

// Get MCA from referral if provided
$referring_mca = null;
if (isset($_GET['mca'])) {
    $stmt = $db->prepare("SELECT * FROM mca_agents WHERE mca_code = ? AND status = 'active'");
    $stmt->execute([$_GET['mca']]);
    $referring_mca = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_registration'])) {
    $errors = [];
    
    // Validate required fields
    $required_fields = [
        'first_name', 'last_name', 'email', 'phone', 'country',
        'region_city', 'date_of_birth', 'place_of_birth', 'mailing_address',
        'id_type', 'id_number', 'issuing_authority', 'id_expiration_date',
        'password', 'confirm_password'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Validate email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Validate password match
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $errors[] = 'Passwords do not match';
    }
    
    // Validate password strength
    if (strlen($_POST['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    // Check required checkboxes
    if (!isset($_POST['certify_info'])) {
        $errors[] = 'You must certify that the information is true and complete';
    }
    
    if (!isset($_POST['agree_terms'])) {
        $errors[] = 'You must agree to the Terms and Conditions';
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Email address already registered';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate tracking codes
            $country_code = strtoupper(substr($_POST['country'], 0, 2));
            $advisor_code = 'ADV' . $country_code . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $tracking_number = 'ADV-' . $country_code . '-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Create user account
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $username = strtolower($_POST['first_name'] . '.' . $_POST['last_name'] . rand(100, 999));
            
            // Get Advisor role ID
            $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'certified_advisor'");
            $stmt->execute();
            $advisor_role = $stmt->fetch();
            
            if (!$advisor_role) {
                throw new Exception("Certified Advisor role not found in system");
            }
            
            $stmt = $db->prepare("
                INSERT INTO users (first_name, last_name, email, username, password_hash, role_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $username,
                $password_hash,
                $advisor_role['id']
            ]);
            
            $user_id = $db->lastInsertId();
            
            // Get MCA ID if referred
            $mca_id = null;
            if (!empty($_POST['mca_code'])) {
                $stmt = $db->prepare("SELECT id FROM mca_agents WHERE mca_code = ?");
                $stmt->execute([$_POST['mca_code']]);
                $mca = $stmt->fetch();
                $mca_id = $mca['id'] ?? null;
            }
            
            // Create Certified Advisor record
            $stmt = $db->prepare("
                INSERT INTO certified_advisors (
                    user_id, mca_id, advisor_code, tracking_number, first_name, last_name, 
                    email, phone, country, country_code, region_city, date_of_birth, 
                    place_of_birth, mailing_address, id_type, id_number, issuing_authority, 
                    id_expiration_date, taxpayer_id, proof_of_address_provided, 
                    previous_experience, how_heard_about_fyt, status, registration_fee_amount,
                    annual_renewal_date, advisor_rank, commission_rate
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 59.00, ?, 'certified_advisor', 30.00)
            ");
            
            $annual_renewal = date('Y-m-d', strtotime('+1 year'));
            
            $stmt->execute([
                $user_id, $mca_id, $advisor_code, $tracking_number, $_POST['first_name'], 
                $_POST['last_name'], $_POST['email'], $_POST['phone'], $_POST['country'], 
                $country_code, $_POST['region_city'], $_POST['date_of_birth'], 
                $_POST['place_of_birth'], $_POST['mailing_address'], $_POST['id_type'], 
                $_POST['id_number'], $_POST['issuing_authority'], $_POST['id_expiration_date'], 
                $_POST['taxpayer_id'] ?? '', isset($_POST['proof_of_address']) ? 1 : 0, 
                $_POST['previous_experience'] ?? '', $_POST['how_heard_about_fyt'] ?? '', 
                'pending', $annual_renewal
            ]);
            
            $advisor_id = $db->lastInsertId();
            
            // Update MCA's advisor count if referred
            if ($mca_id) {
                $stmt = $db->prepare("
                    UPDATE mca_agents 
                    SET current_advisor_count = current_advisor_count + 1 
                    WHERE id = ?
                ");
                $stmt->execute([$mca_id]);
            }
            
            $db->commit();
            
            // Store registration data in session for payment processing
            $_SESSION['advisor_registration_success'] = true;
            $_SESSION['advisor_code'] = $advisor_code;
            $_SESSION['advisor_tracking_number'] = $tracking_number;
            $_SESSION['advisor_user_id'] = $user_id;
            $_SESSION['advisor_registration_fee'] = 59.00;
            
            header('Location: registration-payment.php');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

// Get countries for dropdown
$countries = [
    'Rwanda', 'Uganda', 'Kenya', 'Tanzania', 'Burundi', 'Democratic Republic of Congo',
    'United States', 'Canada', 'United Kingdom', 'Germany', 'France', 'Netherlands',
    'Belgium', 'Sweden', 'Norway', 'Denmark', 'Australia', 'New Zealand', 'South Africa',
    'Nigeria', 'Ghana', 'Ethiopia', 'India', 'China', 'Japan', 'South Korea'
];
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
        .registration-form {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #228B22;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-grid.single {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #228B22;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-top: 3px;
            flex-shrink: 0;
        }
        
        .mca-referral {
            background: linear-gradient(135deg, #228B22, #1e7e1e);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .fee-info {
            background: linear-gradient(135deg, #228B22, #1e7e1e);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .fee-amount {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
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
        
        .password-strength {
            margin-top: 5px;
            font-size: 0.9em;
        }
        
        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .registration-form {
                padding: 20px;
                margin: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <img src="../assets/images/logo.png" alt="Forever Young Tours" class="logo">
            <h1>Certified Advisor Registration</h1>
            <p>Join our network of travel advisors</p>
        </div>
        
        <?php if ($referring_mca): ?>
            <div class="mca-referral">
                <h3><i class="fas fa-user-tie"></i> Referred by MCA</h3>
                <p><strong><?php echo htmlspecialchars($referring_mca['first_name'] . ' ' . $referring_mca['last_name']); ?></strong></p>
                <p>MCA Code: <?php echo htmlspecialchars($referring_mca['mca_code']); ?></p>
                <p>Country: <?php echo htmlspecialchars($referring_mca['country_code']); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo implode('<br>', $errors); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="registration-form">
            <!-- Personal Details -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-user"></i>
                    1. Personal Details
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="country">Country <span class="required">*</span></label>
                        <select id="country" name="country" class="form-control" required>
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?php echo $country; ?>" 
                                        <?php echo (($_POST['country'] ?? '') == $country) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($country); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="region_city">Region/City <span class="required">*</span></label>
                        <input type="text" id="region_city" name="region_city" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['region_city'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                               value="<?php echo $_POST['date_of_birth'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="place_of_birth">Place of Birth <span class="required">*</span></label>
                        <input type="text" id="place_of_birth" name="place_of_birth" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['place_of_birth'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-phone"></i>
                    2. Contact Information
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="mailing_address">Mailing Address <span class="required">*</span></label>
                    <textarea id="mailing_address" name="mailing_address" class="form-control" rows="3" required><?php echo htmlspecialchars($_POST['mailing_address'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- KYC Verification -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-id-card"></i>
                    3. KYC Verification
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_type">ID Type <span class="required">*</span></label>
                        <select id="id_type" name="id_type" class="form-control" required>
                            <option value="">Select ID Type</option>
                            <option value="passport" <?php echo (($_POST['id_type'] ?? '') == 'passport') ? 'selected' : ''; ?>>Passport</option>
                            <option value="national_id" <?php echo (($_POST['id_type'] ?? '') == 'national_id') ? 'selected' : ''; ?>>National ID</option>
                            <option value="drivers_license" <?php echo (($_POST['id_type'] ?? '') == 'drivers_license') ? 'selected' : ''; ?>>Driver's License</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_number">ID Number <span class="required">*</span></label>
                        <input type="text" id="id_number" name="id_number" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="issuing_authority">Issuing Authority <span class="required">*</span></label>
                        <input type="text" id="issuing_authority" name="issuing_authority" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['issuing_authority'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_expiration_date">Expiration Date <span class="required">*</span></label>
                        <input type="date" id="id_expiration_date" name="id_expiration_date" class="form-control" 
                               value="<?php echo $_POST['id_expiration_date'] ?? ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="taxpayer_id">Taxpayer ID or National Number (if applicable)</label>
                    <input type="text" id="taxpayer_id" name="taxpayer_id" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['taxpayer_id'] ?? ''); ?>">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="proof_of_address" name="proof_of_address" 
                           <?php echo isset($_POST['proof_of_address']) ? 'checked' : ''; ?>>
                    <label for="proof_of_address">Proof of Address Provided</label>
                </div>
            </div>
            
            <!-- Experience & Interests -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-briefcase"></i>
                    4. Experience & Interests
                </h2>
                
                <div class="form-group">
                    <label for="previous_experience">Previous Travel Industry Experience (if any)</label>
                    <textarea id="previous_experience" name="previous_experience" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['previous_experience'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="how_heard_about_fyt">How did you hear about Forever Young Tours?</label>
                    <textarea id="how_heard_about_fyt" name="how_heard_about_fyt" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['how_heard_about_fyt'] ?? ''); ?></textarea>
                </div>
                
                <?php if ($referring_mca): ?>
                <input type="hidden" name="mca_code" value="<?php echo htmlspecialchars($referring_mca['mca_code']); ?>">
                <?php else: ?>
                <div class="form-group">
                    <label for="mca_code">MCA Referral Code (if applicable)</label>
                    <input type="text" id="mca_code" name="mca_code" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['mca_code'] ?? ''); ?>"
                           placeholder="Enter MCA code if you were referred">
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Account Setup -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-key"></i>
                    5. Account Setup
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" class="form-control" required onkeyup="checkPasswordStrength()">
                        <div id="password-strength" class="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required onkeyup="checkPasswordMatch()">
                        <div id="password-match" class="password-strength"></div>
                    </div>
                </div>
            </div>
            
            <!-- Registration Fee -->
            <div class="fee-info">
                <h3><i class="fas fa-dollar-sign"></i> Advisor Registration Fee</h3>
                <div class="fee-amount">USD $59.00</div>
                <p>Annual registration fee - includes access to FYT Academy, marketing materials, and commission system</p>
            </div>
            
            <!-- Certification & Declarations -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-certificate"></i>
                    6. Certification & Declarations
                </h2>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="certify_info" name="certify_info" required>
                    <label for="certify_info">I certify that the information given is true and complete to the best of my knowledge. <span class="required">*</span></label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="agree_terms" name="agree_terms" required>
                    <label for="agree_terms">I agree to the Forever Young Tours Advisor Terms and Conditions & Agreement. <span class="required">*</span></label>
                </div>
            </div>
            
            <button type="submit" name="submit_registration" class="submit-btn">
                <i class="fas fa-user-plus"></i> Complete Registration & Proceed to Payment
            </button>
        </form>
    </div>
    
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthDiv = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength < 3) {
                strengthDiv.innerHTML = '<span class="strength-weak">Weak password</span>';
            } else if (strength < 4) {
                strengthDiv.innerHTML = '<span class="strength-medium">Medium password</span>';
            } else {
                strengthDiv.innerHTML = '<span class="strength-strong">Strong password</span>';
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            
            if (confirmPassword.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<span class="strength-strong">Passwords match</span>';
            } else {
                matchDiv.innerHTML = '<span class="strength-weak">Passwords do not match</span>';
            }
        }
    </script>
</body>
</html>
