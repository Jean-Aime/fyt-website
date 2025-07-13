<?php
require_once 'config/config.php';


$page_title = 'Contact Us';
$meta_description = 'Get in touch with Forever Young Tours. Contact our travel experts for personalized assistance, tour inquiries, and travel planning services.';

// Handle contact form submission
if ($_POST && isset($_POST['submit_contact'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $name = sanitizeInput($_POST['name']);
            $email = sanitizeInput($_POST['email']);
            $phone = sanitizeInput($_POST['phone']);
            $subject = sanitizeInput($_POST['subject']);
            $inquiry_type = sanitizeInput($_POST['inquiry_type']);
            $message = sanitizeInput($_POST['message']);
            $preferred_contact = $_POST['preferred_contact'] ?? 'email';

            // Validate required fields
            if (empty($name) || empty($email) || empty($subject) || empty($message)) {
                throw new Exception('Please fill in all required fields.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }

            // Save to database
            $stmt = $db->prepare("
                INSERT INTO contact_inquiries (name, email, phone, subject, inquiry_type, message, 
                                             preferred_contact, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $email,
                $phone,
                $subject,
                $inquiry_type,
                $message,
                $preferred_contact,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $inquiry_id = $db->lastInsertId();

            // Send notification email to admin
            $admin_email = ADMIN_EMAIL;
            $email_subject = "New Contact Inquiry: " . $subject;
            $email_body = "
                <h2>New Contact Inquiry</h2>
                <p><strong>Name:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Phone:</strong> $phone</p>
                <p><strong>Subject:</strong> $subject</p>
                <p><strong>Inquiry Type:</strong> $inquiry_type</p>
                <p><strong>Preferred Contact:</strong> $preferred_contact</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                <hr>
                <p><small>Inquiry ID: $inquiry_id | IP: {$_SERVER['REMOTE_ADDR']}</small></p>
            ";

            sendEmail($admin_email, $email_subject, $email_body);

            // Send confirmation email to user
            $user_subject = "Thank you for contacting Forever Young Tours";
            $user_body = "
                <h2>Thank you for your inquiry!</h2>
                <p>Dear $name,</p>
                <p>We have received your message and will get back to you within 24 hours.</p>
                <p><strong>Your inquiry details:</strong></p>
                <p><strong>Subject:</strong> $subject</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                <hr>
                <p>Best regards,<br>Forever Young Tours Team</p>
                <p><strong>Reference ID:</strong> FYT-" . str_pad($inquiry_id, 6, '0', STR_PAD_LEFT) . "</p>
            ";

            sendEmail($email, $user_subject, $user_body);

            $success = 'Thank you for your message! We will get back to you within 24 hours.';

            // Clear form data
            $_POST = [];

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Security token validation failed. Please try again.';
    }
}

// Get office locations
$offices = $db->query("
    SELECT * FROM office_locations 
    WHERE status = 'active' 
    ORDER BY name
")->fetchAll();


// Get FAQ items
$faqs = $db->query("
    SELECT * FROM faqs 
    WHERE status = 'active' AND category = 'contact' 
    ORDER BY sort_order, created_at 
    LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours</title>
    <meta name="description" content="<?php echo $meta_description; ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo $meta_description; ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo getCurrentUrl(); ?>">

    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .contact-hero {
            background: linear-gradient(135deg, rgba(212, 165, 116, 0.9), rgba(102, 126, 234, 0.9)),
                url('/placeholder.svg?height=400&width=1200') center/cover;
            color: white;
            padding: 100px 0;
            text-align: center;
        }

        .contact-hero h1 {
            font-size: 3.5em;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .contact-hero p {
            font-size: 1.3em;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.9;
        }

        .contact-info-section {
            padding: 80px 0;
            background: #f8f9fa;
        }

        .contact-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .contact-info-card {
            background: white;
            padding: 40px 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .contact-info-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .contact-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-gold), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2em;
            color: white;
        }

        .contact-info-title {
            font-size: 1.3em;
            font-weight: 700;
            color: var(--primary-black);
            margin-bottom: 15px;
        }

        .contact-info-details {
            color: var(--black-lighter);
            line-height: 1.8;
        }

        .contact-info-details a {
            color: var(--primary-gold);
            text-decoration: none;
            font-weight: 600;
        }

        .contact-info-details a:hover {
            text-decoration: underline;
        }

        .contact-form-section {
            padding: 80px 0;
        }

        .contact-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 60px;
            align-items: start;
        }

        .contact-form {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary-black);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e8e9ea;
            border-radius: 8px;
            font-size: 1em;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group.required label::after {
            content: ' *';
            color: #dc3545;
        }

        .contact-sidebar {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .sidebar-widget {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .widget-title {
            font-size: 1.3em;
            font-weight: 700;
            color: var(--primary-black);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary-gold);
        }

        .office-item {
            padding: 20px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .office-item:last-child {
            border-bottom: none;
        }

        .office-name {
            font-weight: 700;
            color: var(--primary-black);
            margin-bottom: 10px;
        }

        .office-address {
            color: var(--black-lighter);
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .office-contact {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 0.9em;
        }

        .office-contact a {
            color: var(--primary-gold);
            text-decoration: none;
        }

        .office-contact a:hover {
            text-decoration: underline;
        }

        .social-links {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .social-link {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-gold), var(--gold-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-size: 1.2em;
            transition: var(--transition);
        }

        .social-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .quick-contact {
            background: linear-gradient(135deg, var(--primary-gold), var(--gold-dark));
            color: white;
            text-align: center;
        }

        .quick-contact-item {
            margin-bottom: 20px;
        }

        .quick-contact-item:last-child {
            margin-bottom: 0;
        }

        .quick-contact-label {
            font-size: 0.9em;
            opacity: 0.8;
            margin-bottom: 5px;
        }

        .quick-contact-value {
            font-size: 1.1em;
            font-weight: 600;
        }

        .quick-contact-value a {
            color: white;
            text-decoration: none;
        }

        .map-section {
            padding: 80px 0;
            background: #f8f9fa;
        }

        .map-container {
            height: 400px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-top: 50px;
        }

        .map-placeholder {
            width: 100%;
            height: 100%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 1.2em;
        }

        .faq-section {
            padding: 80px 0;
        }

        .faq-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .faq-item {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .faq-question {
            padding: 25px;
            background: #f8f9fa;
            font-weight: 700;
            color: var(--primary-black);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .faq-question:hover {
            background: #e9ecef;
        }

        .faq-question i {
            transition: transform 0.3s ease;
        }

        .faq-question.active i {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 25px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-answer.active {
            padding: 25px;
            max-height: 200px;
        }

        .faq-answer p {
            color: var(--black-lighter);
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 768px) {
            .contact-hero h1 {
                font-size: 2.5em;
            }

            .contact-layout {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .contact-info-grid,
            .faq-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="contact-hero">
        <div class="container">
            <h1>Get in Touch</h1>
            <p>Ready to start your next adventure? Our travel experts are here to help you plan the perfect journey</p>
        </div>
    </section>

    <!-- Contact Information -->
    <section class="contact-info-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>How to Reach Us</h2>
                <p>Multiple ways to connect with our travel experts</p>
            </div>

            <div class="contact-info-grid">
                <div class="contact-info-card">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h3 class="contact-info-title">Call Us</h3>
                    <div class="contact-info-details">
                        <a href="tel:+250788123456">+250 788 123 456</a><br>
                        <a href="tel:+250788654321">+250 788 654 321</a><br>
                        <small>Mon-Fri: 8AM-6PM EAT<br>Sat: 9AM-4PM EAT</small>
                    </div>
                </div>

                <div class="contact-info-card">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3 class="contact-info-title">Email Us</h3>
                    <div class="contact-info-details">
                        <a href="mailto:info@foreveryoungtours.com">info@foreveryoungtours.com</a><br>
                        <a href="mailto:bookings@foreveryoungtours.com">bookings@foreveryoungtours.com</a><br>
                        <small>We respond within 24 hours</small>
                    </div>
                </div>

                <div class="contact-info-card">
                    <div class="contact-icon">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <h3 class="contact-info-title">WhatsApp</h3>
                    <div class="contact-info-details">
                        <a href="https://wa.me/250788123456" target="_blank">+250 788 123 456</a><br>
                        <small>Quick responses<br>Available 24/7</small>
                    </div>
                </div>

                <div class="contact-info-card">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3 class="contact-info-title">Visit Us</h3>
                    <div class="contact-info-details">
                        KG 123 St, Kigali<br>
                        Rwanda, East Africa<br>
                        <small>Mon-Fri: 8AM-6PM<br>Sat: 9AM-4PM</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Form -->
    <section class="contact-form-section">
        <div class="container">
            <div class="contact-layout">
                <div class="main-content">
                    <div class="section-header">
                        <h2>Send Us a Message</h2>
                        <p>Fill out the form below and we'll get back to you as soon as possible</p>
                    </div>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form class="contact-form" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="form-row">
                            <div class="form-group required">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name"
                                    value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group required">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email"
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone"
                                    value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>

                            <div class="form-group required">
                                <label for="inquiry_type">Inquiry Type</label>
                                <select id="inquiry_type" name="inquiry_type" required>
                                    <option value="">Select inquiry type...</option>
                                    <option value="tour_booking" <?php echo ($_POST['inquiry_type'] ?? '') === 'tour_booking' ? 'selected' : ''; ?>>Tour Booking</option>
                                    <option value="custom_tour" <?php echo ($_POST['inquiry_type'] ?? '') === 'custom_tour' ? 'selected' : ''; ?>>Custom Tour Request</option>
                                    <option value="group_booking" <?php echo ($_POST['inquiry_type'] ?? '') === 'group_booking' ? 'selected' : ''; ?>>Group Booking</option>
                                    <option value="travel_info" <?php echo ($_POST['inquiry_type'] ?? '') === 'travel_info' ? 'selected' : ''; ?>>Travel Information</option>
                                    <option value="partnership" <?php echo ($_POST['inquiry_type'] ?? '') === 'partnership' ? 'selected' : ''; ?>>Partnership</option>
                                    <option value="support" <?php echo ($_POST['inquiry_type'] ?? '') === 'support' ? 'selected' : ''; ?>>Customer Support</option>
                                    <option value="other" <?php echo ($_POST['inquiry_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group required">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject"
                                value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group required">
                            <label for="message">Message</label>
                            <textarea id="message" name="message"
                                placeholder="Tell us about your travel plans, questions, or how we can help you..."
                                required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="preferred_contact">Preferred Contact Method</label>
                            <select id="preferred_contact" name="preferred_contact">
                                <option value="email" <?php echo ($_POST['preferred_contact'] ?? 'email') === 'email' ? 'selected' : ''; ?>>Email</option>
                                <option value="phone" <?php echo ($_POST['preferred_contact'] ?? '') === 'phone' ? 'selected' : ''; ?>>Phone Call</option>
                                <option value="whatsapp" <?php echo ($_POST['preferred_contact'] ?? '') === 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option>
                            </select>
                        </div>

                        <button type="submit" name="submit_contact" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>

                <!-- Sidebar -->
                <div class="contact-sidebar">
                    <!-- Quick Contact -->
                    <div class="sidebar-widget quick-contact">
                        <h3 class="widget-title">Quick Contact</h3>
                        <div class="quick-contact-item">
                            <div class="quick-contact-label">Emergency Line</div>
                            <div class="quick-contact-value">
                                <a href="tel:+250788999888">+250 788 999 888</a>
                            </div>
                        </div>
                        <div class="quick-contact-item">
                            <div class="quick-contact-label">WhatsApp</div>
                            <div class="quick-contact-value">
                                <a href="https://wa.me/250788123456" target="_blank">Chat with us</a>
                            </div>
                        </div>
                        <div class="quick-contact-item">
                            <div class="quick-contact-label">Email</div>
                            <div class="quick-contact-value">
                                <a href="mailto:urgent@foreveryoungtours.com">urgent@foreveryoungtours.com</a>
                            </div>
                        </div>
                    </div>

                    <!-- Office Locations -->
                    <?php if (!empty($offices)): ?>
                        <div class="sidebar-widget">
                            <h3 class="widget-title">Our Offices</h3>
                            <?php foreach ($offices as $office): ?>
                                <div class="office-item">
                                    <div class="office-name">
                                        <?php echo htmlspecialchars($office['name']); ?>
                                        <?php if ($office['is_primary']): ?>
                                            <span style="color: var(--primary-gold); font-size: 0.8em;">(Main)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="office-address"><?php echo htmlspecialchars($office['address']); ?></div>
                                    <div class="office-contact">
                                        <?php if ($office['phone']): ?>
                                            <a
                                                href="tel:<?php echo $office['phone']; ?>"><?php echo htmlspecialchars($office['phone']); ?></a>
                                        <?php endif; ?>
                                        <?php if ($office['email']): ?>
                                            <a
                                                href="mailto:<?php echo $office['email']; ?>"><?php echo htmlspecialchars($office['email']); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Social Media -->
                    <div class="sidebar-widget">
                        <h3 class="widget-title">Follow Us</h3>
                        <div class="social-links">
                            <a href="#" class="social-link" title="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="social-link" title="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="social-link" title="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link" title="LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="#" class="social-link" title="YouTube">
                                <i class="fab fa-youtube"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="map-section">
        <div class="container">
            <div class="section-header text-center">
                <h2>Find Us</h2>
                <p>Visit our main office in the heart of Kigali</p>
            </div>

            <div class="map-container">
                <div class="map-placeholder">
                    <div>
                        <i class="fas fa-map-marker-alt" style="font-size: 2em; margin-bottom: 10px;"></i><br>
                        Interactive Map Coming Soon<br>
                        <small>KG 123 St, Kigali, Rwanda</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <?php if (!empty($faqs)): ?>
        <section class="faq-section">
            <div class="container">
                <div class="section-header text-center">
                    <h2>Frequently Asked Questions</h2>
                    <p>Quick answers to common questions about contacting us</p>
                </div>

                <div class="faq-grid">
                    <?php foreach ($faqs as $faq): ?>
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                <?php echo htmlspecialchars($faq['question']); ?>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                <p><?php echo htmlspecialchars($faq['answer']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <script>
        function toggleFaq(element) {
            const answer = element.nextElementSibling;
            const isActive = element.classList.contains('active');

            // Close all other FAQs
            document.querySelectorAll('.faq-question.active').forEach(q => {
                q.classList.remove('active');
                q.nextElementSibling.classList.remove('active');
            });

            // Toggle current FAQ
            if (!isActive) {
                element.classList.add('active');
                answer.classList.add('active');
            }
        }

        // Form validation
        document.querySelector('.contact-form').addEventListener('submit', function (e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    field.style.borderColor = '#e8e9ea';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Auto-resize textarea
        document.getElementById('message').addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>

    <script src="assets/js/main.js"></script>
</body>

</html>