<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=book.php");
    exit;
}
require_once 'config/config.php';
$user_id = $_SESSION['user_id'];

// Initialize variables
$selected_tour = null;
$error = null;

// Get available tours
$tours = $db->query("
    SELECT t.*, c.name as country_name, tc.name as category_name
    FROM tours t
    LEFT JOIN countries c ON t.country_id = c.id
    LEFT JOIN tour_categories tc ON t.category_id = tc.id
    WHERE t.status = 'active'
    ORDER BY c.name, t.title
")->fetchAll(PDO::FETCH_ASSOC);

// Get selected tour if specified
if (isset($_GET['tour'])) {
    $tour_id = (int) $_GET['tour'];
    $stmt = $db->prepare("
        SELECT t.*, c.name as country_name, tc.name as category_name
        FROM tours t
        LEFT JOIN countries c ON t.country_id = c.id
        LEFT JOIN tour_categories tc ON t.category_id = tc.id
        WHERE t.id = ? AND t.status = 'active'
    ");
    $stmt->execute([$tour_id]);
    $selected_tour = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Generate a unique booking reference
        $booking_reference = 'FY-' . strtoupper(uniqid());

        // Prepare the booking data
        $tour_id = (int) $_POST['tour_id'];
        $travel_date = $_POST['travel_date'];
        $adults = (int) $_POST['adults'];
        $children = (int) $_POST['children'];
        $infants = (int) ($_POST['infants'] ?? 0);
        $group_size = $_POST['group_size'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $country = $_POST['country'] ?? '';
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $dietary_requirements = $_POST['dietary_requirements'] ?? '';
        $medical_conditions = $_POST['medical_conditions'] ?? '';
        $special_requests = $_POST['special_requests'] ?? '';
        $emergency_name = $_POST['emergency_name'] ?? '';
        $emergency_phone = $_POST['emergency_phone'] ?? '';

        // Get tour price and capacity
        $stmt = $db->prepare("SELECT price_adult, price_child, price_infant, max_capacity FROM tours WHERE id = ?");
        $stmt->execute([$tour_id]);
        $tour_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tour_data) {
            throw new Exception("Tour not found");
        }

        // Check group size against capacity
        if ($group_size > $tour_data['max_capacity']) {
            $error = "The group size you selected exceeds the maximum capacity for this tour ({$tour_data['max_capacity']}). Please reduce your group size.";
            throw new Exception($error);
        }



        // Calculate total amount
        $total_amount = ($adults * $tour_data['price_adult']) +
            ($children * $tour_data['price_child']) +
            ($infants * $tour_data['price_infant']);


        // Insert booking
        $stmt = $db->prepare("
            INSERT INTO bookings (
                user_id, tour_id, booking_reference, tour_date, adults, children, infants, 
                group_size, first_name, last_name, email, phone, country, date_of_birth,
                dietary_requirements, medical_conditions, special_requests,
                emergency_contact_name, emergency_contact_phone, total_amount, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
            )
        ");

        $stmt->execute([
            $user_id,
            $tour_id,
            $booking_reference,
            $travel_date,
            $adults,
            $children,
            $infants,
            $group_size,
            $first_name,
            $last_name,
            $email,
            $phone,
            $country,
            $date_of_birth,
            $dietary_requirements,
            $medical_conditions,
            $special_requests,
            $emergency_name,
            $emergency_phone,
            $total_amount
        ]);

        $booking_id = $db->lastInsertId();

        // Handle payment if selected
        if (isset($_POST['payment_method'])) {
            $payment_method = $_POST['payment_method'];
            $payment_reference = 'PAY-' . strtoupper(uniqid());

            // For demo, we'll just record the payment
            $stmt = $db->prepare("
                INSERT INTO payments (
                    booking_id, payment_reference, amount, payment_method, status
                ) VALUES (?, ?, ?, ?, 'completed')
            ");

            $stmt->execute([
                $booking_id,
                $payment_reference,
                $total_amount,
                $payment_method
            ]);
        }

        $db->commit();

        // Redirect to confirmation page
        header("Location: booking-confirmation.php?id=$booking_id");
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Booking failed: " . $e->getMessage();
    }
}

$page_title = 'Book Your Tour - Forever Young Tours';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description"
        content="Book your dream tour with Forever Young Tours. Easy online booking for luxury group travel and adventure tours.">

    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    <!-- Stripe -->
    <script src="https://js.stripe.com/v3/"></script>

    <!-- PayPal -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=USD"></script>
    <style>
        /* All the CSS styles from your original code */
        .booking-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0 40px;
        }

        .booking-steps {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 40px;
            max-width: 600px;
            margin: 0 auto;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }

        .step.active {
            opacity: 1;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .step.active .step-number {
            background: #d4a574;
        }

        .step-label {
            font-size: 0.9rem;
            text-align: center;
        }

        .booking-container {
            max-width: 800px;
            margin: -20px auto 60px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .booking-form {
            padding: 40px;
        }

        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #d4a574;
        }

        .selected-tour {
            display: flex;
            gap: 20px;
            align-items: center;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #d4a574;
        }

        .tour-image {
            width: 80px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }

        .tour-info h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .tour-meta {
            font-size: 0.9rem;
            color: #666;
        }

        .tour-meta span {
            margin-right: 15px;
        }

        .booking-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .payment-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .payment-option {
            display: block;
            cursor: pointer;
        }

        .payment-option input {
            display: none;
        }

        .option-content {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .payment-option input:checked+.option-content {
            border-color: #d4a574;
            background: rgba(212, 165, 116, 0.1);
        }

        .option-content i {
            font-size: 1.5rem;
        }

        .payment-form {
            margin-bottom: 20px;
        }

        #card-element {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }

        .step-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .required {
            color: #dc3545;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            font-weight: bold;
            font-size: 1.1rem;
        }

        /* Animation for validation errors */
        .shake-animation {
            animation: shake 0.5s;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-5px);
            }

            40%,
            80% {
                transform: translateX(5px);
            }
        }

        .error-message {
            color: #dc3545;
            margin-top: 5px;
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            .booking-steps {
                gap: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .booking-form {
                padding: 20px;
            }

            .selected-tour {
                flex-direction: column;
                text-align: center;
            }

            .payment-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <?php if ($error): ?>
        <div class="alert alert-error" style="margin: 20px auto; max-width: 800px;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <section class="booking-hero">
        <div class="container">
            <div class="booking-steps">
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label"><?php echo $lang['select_tour']; ?></div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label"><?php echo $lang['personal_info']; ?></div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label"><?php echo $lang['requirements']; ?></div>
                </div>
                <div class="step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-label"><?php echo $lang['payment']; ?></div>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="booking-container">
            <form class="booking-form" id="bookingForm" method="POST">
                <!-- Step 1: Tour Selection -->
                <div class="form-step active" data-step="1">
                    <h2><?php echo $lang['select_tour']; ?></h2>

                    <div class="form-group">
                        <label class="form-label">Choose Tour Package <span class="required">*</span></label>
                        <?php if ($selected_tour): ?>
                            <div class="selected-tour">
                                <?php if (!empty($selected_tour['featured_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($selected_tour['featured_image']); ?>"
                                        alt="<?php echo htmlspecialchars($selected_tour['title']); ?>" class="tour-image">
                                <?php endif; ?>
                                <div class="tour-info">
                                    <h4><?php echo htmlspecialchars($selected_tour['title'] ?? ''); ?></h4>
                                    <div class="tour-meta">
                                        <span><i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($selected_tour['country_name'] ?? ''); ?></span>
                                        <span><i class="fas fa-clock"></i>
                                            <?php echo $selected_tour['duration_days'] ?? ''; ?> days</span>
                                        <span><i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars($selected_tour['category_name'] ?? ''); ?></span>
                                    </div>
                                </div>
                                <input type="hidden" name="tour_id" value="<?php echo $selected_tour['id'] ?? ''; ?>">
                            </div>
                        <?php else: ?>
                            <select name="tour_id" class="form-control" required id="tourSelect">
                                <option value="">Select a tour package</option>
                                <?php
                                $current_country = '';
                                foreach ($tours as $tour):
                                    if ($tour['country_name'] !== $current_country):
                                        if ($current_country !== '')
                                            echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($tour['country_name']) . '">';
                                        $current_country = $tour['country_name'];
                                    endif;
                                    ?>
                                    <option value="<?php echo $tour['id']; ?>"
                                        data-price-adult="<?php echo $tour['price_adult']; ?>"
                                        data-price-child="<?php echo $tour['price_child']; ?>"
                                        data-price-infant="<?php echo $tour['price_infant']; ?>"
                                        data-duration="<?php echo $tour['duration_days']; ?>"
                                        data-image="<?php echo htmlspecialchars($tour['featured_image']); ?>">
                                        <?php echo htmlspecialchars($tour['title']); ?> -
                                        $<?php echo number_format($tour['price_adult']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($current_country !== '')
                                    echo '</optgroup>'; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Preferred Travel Date <span class="required">*</span></label>
                            <input type="date" name="travel_date" class="form-control" required
                                min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Group Size</label>
                            <select name="group_size" class="form-control" id="groupSize">
                                <option value="1">1 traveler</option>
                                <option value="2" selected>2 travelers</option>
                                <option value="3">3 travelers</option>
                                <option value="4">4 travelers</option>
                                <option value="5">5 travelers</option>
                                <option value="6+">6+ travelers</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Adults (12+ years)</label>
                            <input type="number" name="adults" class="form-control" min="1" value="1" id="adultsCount">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Children (2-11 years)</label>
                            <input type="number" name="children" class="form-control" min="0" value="0"
                                id="childrenCount">
                        </div>
                    </div>

                    <div class="step-actions">
                        <button type="button" class="btn btn-primary next-step">Next Step</button>
                    </div>
                </div>

                <!-- Step 2: Personal Information -->
                <div class="form-step" data-step="2">
                    <h2><?php echo $lang['personal_info']; ?></h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email Address <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Country of Residence</label>
                            <select name="country" class="form-control">
                                <option value="">Select country...</option>
                                <option value="Rwanda">Rwanda</option>
                                <option value="Kenya">Kenya</option>
                                <option value="Uganda">Uganda</option>
                                <option value="Tanzania">Tanzania</option>
                                <option value="USA">United States</option>
                                <option value="UK">United Kingdom</option>
                                <option value="Canada">Canada</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control">
                        </div>
                    </div>

                    <div class="step-actions">
                        <button type="button" class="btn btn-outline prev-step">Previous</button>
                        <button type="button" class="btn btn-primary next-step">Next Step</button>
                    </div>
                </div>

                <!-- Step 3: Requirements -->
                <div class="form-step" data-step="3">
                    <h2><?php echo $lang['requirements']; ?></h2>

                    <div class="form-group">
                        <label class="form-label">Dietary Requirements</label>
                        <textarea name="dietary_requirements" class="form-control" rows="3"
                            placeholder="Please specify any dietary restrictions or preferences..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Medical Conditions</label>
                        <textarea name="medical_conditions" class="form-control" rows="3"
                            placeholder="Please mention any medical conditions we should be aware of..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Special Requests</label>
                        <textarea name="special_requests" class="form-control" rows="3"
                            placeholder="Any special requests or additional information..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_name" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="tel" name="emergency_phone" class="form-control">
                        </div>
                    </div>

                    <div class="step-actions">
                        <button type="button" class="btn btn-outline prev-step">Previous</button>
                        <button type="button" class="btn btn-primary next-step">Next Step</button>
                    </div>
                </div>

                <!-- Step 4: Payment -->
                <div class="form-step" data-step="4">
                    <h2><?php echo $lang['payment']; ?></h2>

                    <div class="booking-summary">
                        <h3><?php echo $lang['booking_summary']; ?></h3>
                        <div id="summaryContent">
                            <!-- Summary will be populated by JavaScript -->
                        </div>
                    </div>

                    <div class="payment-methods">
                        <h3>Choose Payment Method</h3>

                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="stripe" checked>
                                <div class="option-content">
                                    <i class="fab fa-cc-stripe"></i>
                                    <span>Credit/Debit Card</span>
                                </div>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="paypal">
                                <div class="option-content">
                                    <i class="fab fa-paypal"></i>
                                    <span>PayPal</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Stripe Payment Form -->
                    <div id="stripe-payment" class="payment-form">
                        <div id="card-element">
                            <!-- Stripe Elements will create form elements here -->
                        </div>
                        <div id="card-errors" role="alert"></div>
                    </div>

                    <!-- PayPal Payment Form -->
                    <div id="paypal-payment" class="payment-form" style="display: none;">
                        <div id="paypal-button-container"></div>
                    </div>

                    <div class="terms-agreement">
                        <label class="checkbox-label">
                            <input type="checkbox" name="terms_accepted" required>
                            <span>I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> and <a
                                    href="privacy.php" target="_blank">Privacy Policy</a> <span
                                    class="required">*</span></span>
                        </label>
                    </div>

                    <div class="step-actions">
                        <button type="button" class="btn btn-outline prev-step">Previous</button>
                        <button type="submit" class="btn btn-primary" id="submit-payment">
                            Complete Booking
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Step management - this is the core fix
            let currentStep = 1;
            const totalSteps = 4;

            // Initialize the form to show first step
            showStep(currentStep);

            // Next/Previous button functionality
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('next-step')) {
                    e.preventDefault();
                    if (validateCurrentStep()) {
                        if (currentStep < totalSteps) {
                            showStep(currentStep + 1);
                        }
                    }
                }

                if (e.target.classList.contains('prev-step')) {
                    e.preventDefault();
                    if (currentStep > 1) {
                        showStep(currentStep - 1);
                    }
                }
            });

            // Core function to show steps
            function showStep(step) {
                // Hide all steps
                document.querySelectorAll('.form-step').forEach(step => {
                    step.classList.remove('active');
                });

                // Show current step
                const currentStepElement = document.querySelector(`.form-step[data-step="${step}"]`);
                if (currentStepElement) {
                    currentStepElement.classList.add('active');
                }

                // Update step indicators
                document.querySelectorAll('.step').forEach(indicator => {
                    indicator.classList.remove('active');
                });

                const currentIndicator = document.querySelector(`.step[data-step="${step}"]`);
                if (currentIndicator) {
                    currentIndicator.classList.add('active');
                }

                currentStep = step;

                // Scroll to top of form for better UX
                window.scrollTo({
                    top: document.querySelector('.booking-container').offsetTop - 20,
                    behavior: 'smooth'
                });
            }

            // Basic validation function
            function validateCurrentStep() {
                const currentStepElement = document.querySelector(`.form-step[data-step="${currentStep}"]`);
                if (!currentStepElement) return false;

                let isValid = true;
                const requiredFields = currentStepElement.querySelectorAll('[required]');

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        // Mark field as invalid
                        field.style.borderColor = '#ff0000';
                        isValid = false;

                        // Add shake animation
                        field.classList.add('shake-animation');
                        setTimeout(() => {
                            field.classList.remove('shake-animation');
                        }, 500);
                    } else {
                        field.style.borderColor = '';
                    }
                });

                if (!isValid) {
                    // Scroll to first invalid field
                    const firstInvalid = currentStepElement.querySelector('[required][style*="border-color: #ff0000"]');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }

                    alert('Please fill in all required fields');
                }

                return isValid;
            }

            // Add shake animation style if not already present
            if (!document.querySelector('style.shake-animation-style')) {
                const style = document.createElement('style');
                style.classList.add('shake-animation-style');
                style.textContent = `
            .shake-animation {
                animation: shake 0.5s;
            }
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                20%, 60% { transform: translateX(-5px); }
                40%, 80% { transform: translateX(5px); }
            }
        `;
                document.head.appendChild(style);
            }
        });
    </script>
</body>

</html>