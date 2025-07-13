<?php

class BookingManager {
    private $db;
    private $paymentProcessor;
    
    public function __construct($database) {
        $this->db = $database;
        $this->paymentProcessor = new PaymentProcessor($database);
    }
    
    /**
     * Create a new booking
     */
    public function createBooking($booking_data) {
        try {
            $this->db->beginTransaction();
            
            // Validate tour availability
            if (!$this->checkTourAvailability($booking_data['tour_id'], $booking_data['tour_date'], $booking_data['total_travelers'])) {
                throw new Exception('Tour is not available for the selected date');
            }
            
            // Generate booking reference
            $booking_reference = $this->generateBookingReference();
            
            // Calculate total amount
            $total_amount = $this->calculateBookingAmount($booking_data);
            
            // Create booking record
            $booking_id = $this->insertBooking($booking_reference, $booking_data, $total_amount);
            
            // Add travelers
            if (!empty($booking_data['travelers'])) {
                $this->addTravelers($booking_id, $booking_data['travelers']);
            }
            
            // Add add-ons
            if (!empty($booking_data['addons'])) {
                $this->addBookingAddons($booking_id, $booking_data['addons']);
            }
            
            // Update tour availability
            $this->updateTourAvailability($booking_data['tour_id'], $booking_data['tour_date'], $booking_data['total_travelers']);
            
            // Send confirmation emails
            $this->sendBookingConfirmation($booking_id);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'booking_id' => $booking_id,
                'booking_reference' => $booking_reference,
                'total_amount' => $total_amount
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Booking creation error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update booking status
     */
    public function updateBookingStatus($booking_id, $status, $reason = '') {
        try {
            $valid_statuses = ['pending', 'confirmed', 'cancelled', 'completed', 'refunded'];
            
            if (!in_array($status, $valid_statuses)) {
                throw new Exception('Invalid booking status');
            }
            
            $update_data = [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($status === 'confirmed') {
                $update_data['confirmed_at'] = date('Y-m-d H:i:s');
            } elseif ($status === 'cancelled') {
                $update_data['cancelled_at'] = date('Y-m-d H:i:s');
                $update_data['cancellation_reason'] = $reason;
            }
            
            $set_clauses = [];
            $params = [];
            
            foreach ($update_data as $key => $value) {
                $set_clauses[] = "$key = ?";
                $params[] = $value;
            }
            
            $params[] = $booking_id;
            
            $sql = "UPDATE bookings SET " . implode(', ', $set_clauses) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            // Send status update notification
            $this->sendStatusUpdateNotification($booking_id, $status);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Booking status update error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel booking
     */
    public function cancelBooking($booking_id, $reason, $refund_amount = 0) {
        try {
            $this->db->beginTransaction();
            
            // Get booking details
            $booking = $this->getBooking($booking_id);
            if (!$booking) {
                throw new Exception('Booking not found');
            }
            
            if ($booking['status'] === 'cancelled') {
                throw new Exception('Booking is already cancelled');
            }
            
            // Update booking status
            $this->updateBookingStatus($booking_id, 'cancelled', $reason);
            
            // Process refund if amount specified
            if ($refund_amount > 0) {
                $this->processBookingRefund($booking_id, $refund_amount, $reason);
            }
            
            // Restore tour availability
            $this->restoreTourAvailability($booking['tour_id'], $booking['tour_date'], $booking['adults'] + $booking['children']);
            
            $this->db->commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Booking cancellation error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get booking details
     */
    public function getBooking($booking_id) {
        $stmt = $this->db->prepare("
            SELECT b.*, 
                   u.first_name, u.last_name, u.email, u.phone,
                   t.title as tour_title, t.featured_image as tour_image,
                   c.name as country_name,
                   agent.first_name as agent_first_name, agent.last_name as agent_last_name
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN tours t ON b.tour_id = t.id
            LEFT JOIN countries c ON t.country_id = c.id
            LEFT JOIN users agent ON b.agent_id = agent.id
            WHERE b.id = ?
        ");
        $stmt->execute([$booking_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get booking by reference
     */
    public function getBookingByReference($booking_reference) {
        $stmt = $this->db->prepare("
            SELECT b.*, 
                   u.first_name, u.last_name, u.email, u.phone,
                   t.title as tour_title, t.featured_image as tour_image,
                   c.name as country_name
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN tours t ON b.tour_id = t.id
            LEFT JOIN countries c ON t.country_id = c.id
            WHERE b.booking_reference = ?
        ");
        $stmt->execute([$booking_reference]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user bookings
     */
    public function getUserBookings($user_id, $limit = 10, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT b.*, 
                   t.title as tour_title, t.featured_image as tour_image,
                   c.name as country_name,
                   COALESCE(SUM(p.amount), 0) as paid_amount
            FROM bookings b
            LEFT JOIN tours t ON b.tour_id = t.id
            LEFT JOIN countries c ON t.country_id = c.id
            LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'completed'
            WHERE b.user_id = ?
            GROUP BY b.id
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user_id, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get booking travelers
     */
    public function getBookingTravelers($booking_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM booking_travelers 
            WHERE booking_id = ? 
            ORDER BY type, id
        ");
        $stmt->execute([$booking_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get booking add-ons
     */
    public function getBookingAddons($booking_id) {
        $stmt = $this->db->prepare("
            SELECT ba.*, ta.name as addon_name, ta.description as addon_description
            FROM booking_addons ba
            JOIN tour_addons ta ON ba.addon_id = ta.id
            WHERE ba.booking_id = ?
        ");
        $stmt->execute([$booking_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get booking payments
     */
    public function getBookingPayments($booking_id) {
        $stmt = $this->db->prepare("
            SELECT p.*, pm.display_name as payment_method_name
            FROM payments p
            JOIN payment_methods pm ON p.payment_method_id = pm.id
            WHERE p.booking_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$booking_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check tour availability
     */
    private function checkTourAvailability($tour_id, $tour_date, $travelers_count) {
        // Get tour capacity
        $stmt = $this->db->prepare("SELECT max_group_size FROM tours WHERE id = ?");
        $stmt->execute([$tour_id]);
        $tour = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tour) {
            return false;
        }
        
        // Check existing bookings for this date
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(adults + children), 0) as booked_spots
            FROM bookings 
            WHERE tour_id = ? AND tour_date = ? AND status IN ('confirmed', 'pending')
        ");
        $stmt->execute([$tour_id, $tour_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $booked_spots = $result['booked_spots'] ?? 0;
        $available_spots = $tour['max_group_size'] - $booked_spots;
        
        return $available_spots >= $travelers_count;
    }
    
    /**
     * Generate unique booking reference
     */
    private function generateBookingReference() {
        do {
            $reference = 'FYT' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $this->db->prepare("SELECT id FROM bookings WHERE booking_reference = ?");
            $stmt->execute([$reference]);
            $exists = $stmt->fetch();
            
        } while ($exists);
        
        return $reference;
    }
    
    /**
     * Calculate booking total amount
     */
    private function calculateBookingAmount($booking_data) {
        // Get tour prices
        $stmt = $this->db->prepare("SELECT price_adult, price_child, price_infant FROM tours WHERE id = ?");
        $stmt->execute([$booking_data['tour_id']]);
        $tour = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tour) {
            throw new Exception('Tour not found');
        }
        
        $total = 0;
        $total += $booking_data['adults'] * $tour['price_adult'];
        $total += $booking_data['children'] * ($tour['price_child'] ?? 0);
        $total += $booking_data['infants'] * ($tour['price_infant'] ?? 0);
        
        // Add add-ons cost
        if (!empty($booking_data['addons'])) {
            foreach ($booking_data['addons'] as $addon) {
                $total += $addon['quantity'] * $addon['price'];
            }
        }
        
        return $total;
    }
    
    /**
     * Insert booking record
     */
    private function insertBooking($booking_reference, $booking_data, $total_amount) {
        $stmt = $this->db->prepare("
            INSERT INTO bookings (
                booking_reference, user_id, tour_id, agent_id, tour_date,
                adults, children, infants, total_amount, currency,
                special_requests, emergency_contact_name, emergency_contact_phone,
                emergency_contact_relationship, dietary_requirements, medical_conditions,
                travel_insurance, insurance_provider, insurance_policy_number,
                status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
            )
        ");
        
        $stmt->execute([
            $booking_reference,
            $booking_data['user_id'],
            $booking_data['tour_id'],
            $booking_data['agent_id'] ?? null,
            $booking_data['tour_date'],
            $booking_data['adults'],
            $booking_data['children'] ?? 0,
            $booking_data['infants'] ?? 0,
            $total_amount,
            $booking_data['currency'] ?? 'USD',
            $booking_data['special_requests'] ?? '',
            $booking_data['emergency_contact_name'] ?? '',
            $booking_data['emergency_contact_phone'] ?? '',
            $booking_data['emergency_contact_relationship'] ?? '',
            $booking_data['dietary_requirements'] ?? '',
            $booking_data['medical_conditions'] ?? '',
            $booking_data['travel_insurance'] ?? false,
            $booking_data['insurance_provider'] ?? '',
            $booking_data['insurance_policy_number'] ?? ''
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Add travelers to booking
     */
    private function addTravelers($booking_id, $travelers) {
        $stmt = $this->db->prepare("
            INSERT INTO booking_travelers (
                booking_id, type, first_name, last_name, date_of_birth,
                gender, nationality, passport_number, passport_expiry,
                dietary_requirements, medical_conditions
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($travelers as $traveler) {
            $stmt->execute([
                $booking_id,
                $traveler['type'],
                $traveler['first_name'],
                $traveler['last_name'],
                $traveler['date_of_birth'] ?? null,
                $traveler['gender'] ?? null,
                $traveler['nationality'] ?? '',
                $traveler['passport_number'] ?? '',
                $traveler['passport_expiry'] ?? null,
                $traveler['dietary_requirements'] ?? '',
                $traveler['medical_conditions'] ?? ''
            ]);
        }
    }
    
    /**
     * Add booking add-ons
     */
    private function addBookingAddons($booking_id, $addons) {
        $stmt = $this->db->prepare("
            INSERT INTO booking_addons (booking_id, addon_id, quantity, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($addons as $addon) {
            $total_price = $addon['quantity'] * $addon['price'];
            $stmt->execute([
                $booking_id,
                $addon['addon_id'],
                $addon['quantity'],
                $addon['price'],
                $total_price
            ]);
        }
    }
    
    /**
     * Update tour availability
     */
    private function updateTourAvailability($tour_id, $tour_date, $travelers_count) {
        // Check if availability record exists
        $stmt = $this->db->prepare("SELECT id, booked_spots FROM tour_availability WHERE tour_id = ? AND date = ?");
        $stmt->execute([$tour_id, $tour_date]);
        $availability = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($availability) {
            // Update existing record
            $new_booked_spots = $availability['booked_spots'] + $travelers_count;
            $stmt = $this->db->prepare("UPDATE tour_availability SET booked_spots = ? WHERE id = ?");
            $stmt->execute([$new_booked_spots, $availability['id']]);
        } else {
            // Create new availability record
            $stmt = $this->db->prepare("SELECT max_group_size FROM tours WHERE id = ?");
            $stmt->execute([$tour_id]);
            $tour = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tour) {
                $stmt = $this->db->prepare("
                    INSERT INTO tour_availability (tour_id, date, available_spots, booked_spots, status)
                    VALUES (?, ?, ?, ?, 'available')
                ");
                $available_spots = $tour['max_group_size'] - $travelers_count;
                $stmt->execute([$tour_id, $tour_date, $available_spots, $travelers_count]);
            }
        }
    }
    
    /**
     * Restore tour availability (for cancellations)
     */
    private function restoreTourAvailability($tour_id, $tour_date, $travelers_count) {
        $stmt = $this->db->prepare("
            UPDATE tour_availability 
            SET booked_spots = GREATEST(0, booked_spots - ?),
                available_spots = available_spots + ?
            WHERE tour_id = ? AND date = ?
        ");
        $stmt->execute([$travelers_count, $travelers_count, $tour_id, $tour_date]);
    }
    
    /**
     * Send booking confirmation
     */
    private function sendBookingConfirmation($booking_id) {
        $booking = $this->getBooking($booking_id);
        
        if ($booking) {
            // Send to customer
            $this->sendEmail(
                $booking['email'],
                'Booking Confirmation - ' . $booking['booking_reference'],
                $this->getBookingConfirmationTemplate($booking)
            );
            
            // Send to admin
            $this->sendEmail(
                ADMIN_EMAIL,
                'New Booking - ' . $booking['booking_reference'],
                $this->getAdminBookingNotificationTemplate($booking)
            );
        }
    }
    
    /**
     * Send status update notification
     */
    private function sendStatusUpdateNotification($booking_id, $status) {
        $booking = $this->getBooking($booking_id);
        
        if ($booking) {
            $subject = 'Booking ' . ucfirst($status) . ' - ' . $booking['booking_reference'];
            $template = $this->getStatusUpdateTemplate($booking, $status);
            
            $this->sendEmail($booking['email'], $subject, $template);
        }
    }
    
    /**
     * Process booking refund
     */
    private function processBookingRefund($booking_id, $refund_amount, $reason) {
        // Get completed payments for this booking
        $stmt = $this->db->prepare("
            SELECT * FROM payments 
            WHERE booking_id = ? AND status = 'completed' 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$booking_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $remaining_refund = $refund_amount;
        
        foreach ($payments as $payment) {
            if ($remaining_refund <= 0) break;
            
            $refund_for_payment = min($remaining_refund, $payment['amount']);
            
            $result = $this->paymentProcessor->processRefund(
                $payment['id'],
                $refund_for_payment,
                $reason
            );
            
            if ($result['success']) {
                $remaining_refund -= $refund_for_payment;
            }
        }
        
        // Update booking refund amount
        $stmt = $this->db->prepare("
            UPDATE bookings 
            SET refund_amount = refund_amount + ?, refund_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$refund_amount - $remaining_refund, $reason, $booking_id]);
    }
    
    /**
     * Email helper methods
     */
    private function sendEmail($to, $subject, $body) {
        // Implementation would use your email system
        // For now, just log the email
        error_log("Email to $to: $subject");
    }
    
    private function getBookingConfirmationTemplate($booking) {
        return "
            <h2>Booking Confirmation</h2>
            <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
            <p>Thank you for your booking with Forever Young Tours!</p>
            <p><strong>Booking Reference:</strong> {$booking['booking_reference']}</p>
            <p><strong>Tour:</strong> {$booking['tour_title']}</p>
            <p><strong>Date:</strong> " . date('F j, Y', strtotime($booking['tour_date'])) . "</p>
            <p><strong>Travelers:</strong> {$booking['adults']} Adults" . 
            ($booking['children'] > 0 ? ", {$booking['children']} Children" : "") .
            ($booking['infants'] > 0 ? ", {$booking['infants']} Infants" : "") . "</p>
            <p><strong>Total Amount:</strong> $" . number_format($booking['total_amount'], 2) . "</p>
            <p>We will contact you soon with payment instructions and further details.</p>
            <p>Best regards,<br>Forever Young Tours Team</p>
        ";
    }
    
    private function getAdminBookingNotificationTemplate($booking) {
        return "
            <h2>New Booking Received</h2>
            <p><strong>Booking Reference:</strong> {$booking['booking_reference']}</p>
            <p><strong>Customer:</strong> {$booking['first_name']} {$booking['last_name']}</p>
            <p><strong>Email:</strong> {$booking['email']}</p>
            <p><strong>Tour:</strong> {$booking['tour_title']}</p>
            <p><strong>Date:</strong> " . date('F j, Y', strtotime($booking['tour_date'])) . "</p>
            <p><strong>Total Amount:</strong> $" . number_format($booking['total_amount'], 2) . "</p>
            <p><a href='" . SITE_URL . "/admin/bookings/view.php?id={$booking['id']}'>View Booking Details</a></p>
        ";
    }
    
    private function getStatusUpdateTemplate($booking, $status) {
        $messages = [
            'confirmed' => 'Your booking has been confirmed! We look forward to providing you with an amazing experience.',
            'cancelled' => 'Your booking has been cancelled. If you have any questions, please contact our support team.',
            'completed' => 'Thank you for traveling with Forever Young Tours! We hope you had an amazing experience.'
        ];
        
        return "
            <h2>Booking Update</h2>
            <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
            <p>{$messages[$status]}</p>
            <p><strong>Booking Reference:</strong> {$booking['booking_reference']}</p>
            <p><strong>Tour:</strong> {$booking['tour_title']}</p>
            <p>Best regards,<br>Forever Young Tours Team</p>
        ";
    }
}
?>
