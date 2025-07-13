<?php
require_once '../config/config.php';

// Check if payment was completed
if (!isset($_SESSION['payment_success']) || !isset($_SESSION['new_mca_code'])) {
    header('Location: registration.php');
    exit;
}

$mca_code = $_SESSION['new_mca_code'];

// Clear session data
unset($_SESSION['payment_success']);
unset($_SESSION['new_mca_code']);

$page_title = 'MCA Registration Complete - Forever Young Tours';
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
        .success-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .success-icon {
            font-size: 4em;
            color: #27ae60;
            margin-bottom: 20px;
        }
        
        .mca-code {
            background: linear-gradient(135deg, #D4AF37, #B8941F);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            font-size: 1.5em;
            font-weight: bold;
            font-family: monospace;
        }
        
        .next-steps {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin: 30px 0;
            text-align: left;
        }
        
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            padding: 10px;
            border-left: 3px solid #D4AF37;
            background: white;
            border-radius: 8px;
        }
        
        .step-number {
            background: #D4AF37;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .login-btn {
            background: linear-gradient(135deg, #D4AF37, #B8941F);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 20px 10px;
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }
        
        .secondary-btn {
            background: #6c757d;
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 20px 10px;
        }
        
        .contact-info {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <img src="../assets/images/logo.png" alt="Forever Young Tours" class="logo">
            <h1>Welcome to Forever Young Tours!</h1>
            <p>Your MCA Registration is Complete</p>
        </div>
        
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h2>Congratulations!</h2>
            <p>Your MCA (Marketing & Client Advisor) registration has been successfully completed and your account is now active.</p>
            
            <div class="mca-code">
                Your MCA Code: <?php echo htmlspecialchars($mca_code); ?>
            </div>
            
            <div class="next-steps">
                <h3><i class="fas fa-list-check"></i> Next Steps</h3>
                
                <div class="step">
                    <div class="step-number">1</div>
                    <div>
                        <strong>Access Your MCA Dashboard</strong><br>
                        Log in to your MCA portal to access your dashboard, track performance, and manage your advisor network.
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div>
                        <strong>Complete FYT Academy Training</strong><br>
                        Complete the required training modules to fully understand FYT products, sales techniques, and brand guidelines.
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div>
                        <strong>Start Recruiting Advisors</strong><br>
                        Begin building your network by recruiting and training certified advisors. Your goal is 100+ advisors.
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div>
                        <strong>Join Weekly Training Calls</strong><br>
                        Attend weekly Jitsi training sessions for ongoing education, updates, and networking with other MCAs.
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">5</div>
                    <div>
                        <strong>Access Marketing Materials</strong><br>
                        Download brochures, social media content, and other marketing materials to promote FYT tours.
                    </div>
                </div>
            </div>
            
            <div class="contact-info">
                <h4><i class="fas fa-headset"></i> Need Help?</h4>
                <p><strong>Email:</strong> support@foreveryoungtours.com</p>
                <p><strong>WhatsApp:</strong> +250 788 123 456</p>
                <p><strong>Training Schedule:</strong> Every Wednesday at 2:00 PM EAT</p>
            </div>
            
            <div>
                <a href="login.php" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login to MCA Portal
                </a>
                <a href="../index.php" class="secondary-btn">
                    <i class="fas fa-home"></i> Visit Main Website
                </a>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 10px;">
                <h4><i class="fas fa-envelope"></i> Check Your Email</h4>
                <p>We've sent you a welcome email with your login credentials and important information. Please check your inbox and spam folder.</p>
            </div>
        </div>
    </div>
</body>
</html>
