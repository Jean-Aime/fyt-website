<?php
require_once '../config/config.php';
require_once '../classes/BookingManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $bookingManager = new BookingManager($db);
    
    // Collect and validate form data
    $booking_data = [
        'user_id' => $_SESSION['user_id'] ?? null,
        'tour_id' => (int)($_POST['tour_id'] ?? 0),
        'tour_date' => $_POST['tour_date'] ?? '',
        'adults' => (int)($_POST['adults'] ?? 0),
        'children' => (int)($_POST['children'] ?? 0),
        'infants' => (int)($_POST['infants'] ?? 0),
        'special_requests' => trim($_POST['special_requests'] ?? ''),
        'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?? ''),
        'emergency_contact_relationship' => trim($_POST['emergency_contact_relationship'] ?? ''),
        'dietary_requirements' => trim($_POST['dietary_requirements'] ?? ''),
        'medical_conditions' => trim($_POST['medical_conditions'] ?? ''),
        'travel_insurance' => isset($_POST['travel_insurance']),
        'insurance_provider' => trim($_POST['insurance_provider'] ?? ''),
        'insurance_policy_number' => trim($_POST['insurance_policy_number'] ?? ''),
        'currency' => $_POST['currency'] ?? 'USD'
    ];
    
    // If user is not logged in, collect guest information
    if (!$booking_data['user_id']) {
        $guest_data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL),
            'phone' => trim($_POST['phone'] ?? ''),
            'nationality' => trim($_POST['nationality'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?? null
        ];
        
        // Create guest user or find existing
        $booking_data['user_id'] = createOrFindGuestUser($guest_data);
    }
    
    // Add travelers information
    $travelers = [];
    if (isset($_POST['travelers']) && is_array($_POST['travelers'])) {
        foreach ($_POST['travelers'] as $traveler) {
            $travelers[] = [
                'type' => $traveler['type'] ?? 'adult',
                'first_name' => trim($traveler['first_name'] ?? ''),
                'last_name' => trim($traveler['last_name'] ?? ''),
                'date_of_birth' => $traveler['date_of_birth'] ?? null,
                'gender' => $traveler['gender'] ?? null,
                'nationality' => trim($traveler['nationality'] ?? ''),
                'passport_number' => trim($traveler['passport_number'] ?? ''),
                'passport_expiry' => $traveler['passport_expiry'] ?? null,
                'dietary_requirements' => trim($traveler['dietary_requirements'] ?? ''),
                'medical_conditions' => trim($traveler['medical_conditions'] ?? '')
            ];
        }
    }
    $booking_data['travelers'] = $travelers;
    
    // Add add-ons
    $addons = [];
    if (isset($_POST['addons']) && is_array($_POST['addons'])) {
        foreach ($_POST['addons'] as $addon) {
            if ($addon['quantity'] > 0) {
                $addons[] = [
                    'addon_id' => (int)$addon['addon_id'],
                    'quantity' => (int)$addon['quantity'],
                    'price' => (float)$addon['price']
                ];
            }
        }
    }
    $booking_data['addons'] = $addons;
    
    // Calculate total travelers
    $booking_data['total_travelers'] = $booking_data['adults'] + $booking_data['children'];
    
    // Validation
    $errors = [];
    
    if (!$booking_data['tour_id']) $errors[] = 'Tour selection is required';
    if (!$booking_data['tour_date']) $errors[] = 'Travel date is required';
    if ($booking_data['adults'] < 1) $errors[] = 'At least one adult is required';
    if (!$booking_data['user_id']) $errors[] = 'User information is required';
    
    // Validate tour date is in the future
    if ($booking_data['tour_date'] && strtotime($booking_data['tour_date']) <= time()) {
        $errors[] = 'Tour date must be in the future';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }
    
    // Create booking
    $result = $bookingManager->createBooking($booking_data);
    
    if ($result['success']) {
        // Log activity
        if (isset($_SESSION['user_id'])) {
            $auth = new SecureAuth($db);
            $auth->logActivity($_SESSION['user_id'], 'booking_created', "Created booking: {$result['booking_reference']}");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Booking created successfully',
            'booking_id' => $result['booking_id'],
            'booking_reference' => $result['booking_reference'],
            'total_amount' => $result['total_amount']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['error']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Booking API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

/**
 * Create or find guest user
 */
function createOrFindGuestUser($guest_data) {
    global $db;
    
    // Check if user exists by email
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$guest_data['email']]);
    $existing_user = $stmt->fetch();
    
    if ($existing_user) {
        return $existing_user['id'];
    }
    
    // Create new guest user
    $username = 'guest_' . uniqid();
    $password_hash = password_hash(uniqid(), PASSWORD_DEFAULT); // Random password for guest
    
    $stmt = $db->prepare("
        INSERT INTO users (
            username, email, password_hash, first_name, last_name, 
            phone, nationality, date_of_birth, role_id, status, email_verified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 6, 'active', FALSE)
    ");
    
    $stmt->execute([
        $username,
        $guest_data['email'],
        $password_hash,
        $guest_data['first_name'],
        $guest_data['last_name'],
        $guest_data['phone'],
        $guest_data['nationality'],
        $guest_data['date_of_birth']
    ]);
    
    return $db->lastInsertId();
}
?>
