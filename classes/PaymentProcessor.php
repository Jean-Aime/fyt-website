<?php

class PaymentProcessor {
    private $db;
    private $config;
    
    public function __construct($database, $config = []) {
        $this->db = $database;
        $this->config = array_merge([
            'stripe_secret_key' => STRIPE_SECRET_KEY ?? '',
            'stripe_publishable_key' => STRIPE_PUBLISHABLE_KEY ?? '',
            'paypal_client_id' => PAYPAL_CLIENT_ID ?? '',
            'paypal_client_secret' => PAYPAL_CLIENT_SECRET ?? '',
            'paypal_mode' => PAYPAL_MODE ?? 'sandbox',
            'mtn_api_key' => MTN_API_KEY ?? '',
            'mtn_subscription_key' => MTN_SUBSCRIPTION_KEY ?? '',
            'airtel_client_id' => AIRTEL_CLIENT_ID ?? '',
            'airtel_client_secret' => AIRTEL_CLIENT_SECRET ?? ''
        ], $config);
    }
    
    /**
     * Process payment based on method
     */
    public function processPayment($booking_id, $payment_method_id, $amount, $currency = 'USD', $payment_data = []) {
        try {
            // Validate booking
            $booking = $this->getBooking($booking_id);
            if (!$booking) {
                throw new Exception('Booking not found');
            }
            
            // Get payment method details
            $payment_method = $this->getPaymentMethod($payment_method_id);
            if (!$payment_method) {
                throw new Exception('Payment method not found');
            }
            
            // Create payment record
            $payment_id = $this->createPaymentRecord($booking_id, $payment_method_id, $amount, $currency);
            
            $result = null;
            
            switch ($payment_method['gateway_name']) {
                case 'stripe':
                    $result = $this->processStripePayment($payment_id, $amount, $currency, $payment_data);
                    break;
                    
                case 'paypal':
                    $result = $this->processPayPalPayment($payment_id, $amount, $currency, $payment_data);
                    break;
                    
                case 'mtn_mobile_money':
                    $result = $this->processMTNMobileMoneyPayment($payment_id, $amount, $currency, $payment_data);
                    break;
                    
                case 'airtel_money':
                    $result = $this->processAirtelMoneyPayment($payment_id, $amount, $currency, $payment_data);
                    break;
                    
                case 'bank_transfer':
                    $result = $this->processBankTransferPayment($payment_id, $amount, $currency, $payment_data);
                    break;
                    
                default:
                    throw new Exception('Unsupported payment method: ' . $payment_method['gateway_name']);
            }
            
            // Update payment record with result
            $this->updatePaymentRecord($payment_id, $result);
            
            // Update booking status if payment successful
            if ($result['status'] === 'completed') {
                $this->updateBookingPaymentStatus($booking_id);
                $this->sendPaymentConfirmation($booking, $result);
            }
            
            return [
                'success' => true,
                'payment_id' => $payment_id,
                'result' => $result
            ];
            
        } catch (Exception $e) {
            error_log("Payment processing error: " . $e->getMessage());
            
            if (isset($payment_id)) {
                $this->updatePaymentRecord($payment_id, [
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process Stripe payment
     */
    private function processStripePayment($payment_id, $amount, $currency, $payment_data) {
        require_once 'vendor/stripe/stripe-php/init.php';
        
        \Stripe\Stripe::setApiKey($this->config['stripe_secret_key']);
        
        try {
            if (isset($payment_data['payment_intent_id'])) {
                // Confirm existing payment intent
                $intent = \Stripe\PaymentIntent::retrieve($payment_data['payment_intent_id']);
                
                if ($intent->status === 'requires_confirmation') {
                    $intent = $intent->confirm();
                }
                
                $status = $this->mapStripeStatus($intent->status);
                
                // Log transaction
                $this->logTransaction($payment_id, 'charge', $amount, $currency, $intent->id, $intent->toArray(), $status === 'completed' ? 'success' : 'pending');
                
                return [
                    'status' => $status,
                    'gateway_transaction_id' => $intent->id,
                    'gateway_response' => $intent->toArray(),
                    'amount_received' => $intent->amount_received / 100,
                    'fees' => isset($intent->charges->data[0]->balance_transaction) ? 
                             $intent->charges->data[0]->balance_transaction->fee / 100 : 0
                ];
                
            } else {
                // Create new payment intent
                $intent = \Stripe\PaymentIntent::create([
                    'amount' => $amount * 100, // Convert to cents
                    'currency' => strtolower($currency),
                    'metadata' => [
                        'payment_id' => $payment_id,
                        'booking_id' => $payment_data['booking_id'] ?? ''
                    ],
                    'automatic_payment_methods' => [
                        'enabled' => true,
                    ],
                ]);
                
                return [
                    'status' => 'pending',
                    'client_secret' => $intent->client_secret,
                    'payment_intent_id' => $intent->id,
                    'requires_action' => true
                ];
            }
            
        } catch (\Stripe\Exception\CardException $e) {
            $this->logTransaction($payment_id, 'charge', $amount, $currency, null, ['error' => $e->getError()], 'failed', $e->getError()->code, $e->getError()->message);
            throw new Exception('Card error: ' . $e->getError()->message);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            throw new Exception('Invalid request: ' . $e->getMessage());
        } catch (\Stripe\Exception\AuthenticationException $e) {
            throw new Exception('Authentication failed');
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            throw new Exception('Network error');
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Stripe API error: ' . $e->getMessage());
        }
    }
    
    /**
     * Process PayPal payment
     */
    private function processPayPalPayment($payment_id, $amount, $currency, $payment_data) {
        $base_url = $this->config['paypal_mode'] === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        
        // Get access token
        $access_token = $this->getPayPalAccessToken();
        
        if (isset($payment_data['order_id'])) {
            // Capture existing order
            $capture_url = $base_url . '/v2/checkout/orders/' . $payment_data['order_id'] . '/capture';
            
            $response = $this->makePayPalRequest($capture_url, 'POST', [], $access_token);
            
            if ($response['http_code'] === 201) {
                $capture_data = json_decode($response['body'], true);
                $capture = $capture_data['purchase_units'][0]['payments']['captures'][0];
                
                $this->logTransaction($payment_id, 'capture', $amount, $currency, $capture['id'], $capture_data, 'success');
                
                return [
                    'status' => 'completed',
                    'gateway_transaction_id' => $capture['id'],
                    'gateway_response' => $capture_data,
                    'amount_received' => floatval($capture['amount']['value']),
                    'fees' => floatval($capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? 0)
                ];
            } else {
                $this->logTransaction($payment_id, 'capture', $amount, $currency, null, json_decode($response['body'], true), 'failed');
                throw new Exception('PayPal capture failed: ' . $response['body']);
            }
            
        } else {
            // Create new order
            $order_data = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', '')
                    ],
                    'custom_id' => $payment_id
                ]],
                'application_context' => [
                    'return_url' => SITE_URL . '/payment/paypal-return.php',
                    'cancel_url' => SITE_URL . '/payment/paypal-cancel.php'
                ]
            ];
            
            $create_url = $base_url . '/v2/checkout/orders';
            $response = $this->makePayPalRequest($create_url, 'POST', $order_data, $access_token);
            
            if ($response['http_code'] === 201) {
                $order = json_decode($response['body'], true);
                $approval_url = '';
                
                foreach ($order['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        $approval_url = $link['href'];
                        break;
                    }
                }
                
                return [
                    'status' => 'pending',
                    'order_id' => $order['id'],
                    'approval_url' => $approval_url,
                    'requires_action' => true
                ];
            } else {
                throw new Exception('PayPal order creation failed: ' . $response['body']);
            }
        }
    }
    
    /**
     * Process MTN Mobile Money payment
     */
    private function processMTNMobileMoneyPayment($payment_id, $amount, $currency, $payment_data) {
        $base_url = 'https://sandbox.momodeveloper.mtn.com'; // Use production URL for live
        
        // Generate UUID for transaction
        $transaction_id = $this->generateUUID();
        
        // Request to pay
        $request_data = [
            'amount' => strval($amount),
            'currency' => $currency,
            'externalId' => $payment_id,
            'payer' => [
                'partyIdType' => 'MSISDN',
                'partyId' => $payment_data['phone_number']
            ],
            'payerMessage' => 'Payment for Forever Young Tours booking',
            'payeeNote' => 'Booking payment - ID: ' . $payment_id
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->getMTNAccessToken(),
            'X-Reference-Id: ' . $transaction_id,
            'X-Target-Environment: sandbox', // Change to 'production' for live
            'Content-Type: application/json',
            'Ocp-Apim-Subscription-Key: ' . $this->config['mtn_subscription_key']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $base_url . '/collection/v1_0/requesttopay');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 202) {
            $this->logTransaction($payment_id, 'charge', $amount, $currency, $transaction_id, $request_data, 'pending');
            
            // Payment request accepted, now check status
            return [
                'status' => 'pending',
                'gateway_transaction_id' => $transaction_id,
                'requires_verification' => true,
                'verification_method' => 'mtn_status_check'
            ];
        } else {
            $this->logTransaction($payment_id, 'charge', $amount, $currency, null, ['error' => $response], 'failed');
            throw new Exception('MTN Mobile Money request failed: ' . $response);
        }
    }
    
    /**
     * Process Airtel Money payment
     */
    private function processAirtelMoneyPayment($payment_id, $amount, $currency, $payment_data) {
        $base_url = 'https://openapiuat.airtel.africa'; // Use production URL for live
        
        // Get access token
        $access_token = $this->getAirtelAccessToken();
        
        $request_data = [
            'reference' => 'FYT_' . $payment_id,
            'subscriber' => [
                'country' => $payment_data['country'] ?? 'RW',
                'currency' => $currency,
                'msisdn' => $payment_data['phone_number']
            ],
            'transaction' => [
                'amount' => $amount,
                'country' => $payment_data['country'] ?? 'RW',
                'currency' => $currency,
                'id' => $payment_id
            ]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
            'X-Country: ' . ($payment_data['country'] ?? 'RW'),
            'X-Currency: ' . $currency
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $base_url . '/merchant/v1/payments/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $response_data = json_decode($response, true);
            
            if ($response_data['status']['code'] === '200') {
                $this->logTransaction($payment_id, 'charge', $amount, $currency, $response_data['data']['transaction']['id'], $response_data, 'pending');
                
                return [
                    'status' => 'pending',
                    'gateway_transaction_id' => $response_data['data']['transaction']['id'],
                    'requires_verification' => true,
                    'verification_method' => 'airtel_status_check'
                ];
            } else {
                $this->logTransaction($payment_id, 'charge', $amount, $currency, null, $response_data, 'failed');
                throw new Exception('Airtel Money payment failed: ' . $response_data['status']['message']);
            }
        } else {
            throw new Exception('Airtel Money API error: ' . $response);
        }
    }
    
    /**
     * Process bank transfer payment
     */
    private function processBankTransferPayment($payment_id, $amount, $currency, $payment_data) {
        // For bank transfers, we just create a pending payment
        // The actual verification happens when admin confirms the transfer
        
        $transaction_id = 'BT_' . $payment_id . '_' . time();
        
        $this->logTransaction($payment_id, 'authorize', $amount, $currency, $transaction_id, $payment_data, 'pending');
        
        return [
            'status' => 'pending',
            'gateway_transaction_id' => $transaction_id,
            'bank_details' => [
                'account_name' => 'Forever Young Tours Ltd',
                'account_number' => '1234567890',
                'bank_name' => 'Bank of Kigali',
                'swift_code' => 'BKRWRWRW',
                'reference' => 'FYT_' . $payment_id
            ],
            'requires_verification' => true,
            'verification_method' => 'manual_verification'
        ];
    }
    
    /**
     * Verify payment status
     */
    public function verifyPayment($payment_id) {
        $payment = $this->getPaymentRecord($payment_id);
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        switch ($payment['gateway_name']) {
            case 'mtn_mobile_money':
                return $this->verifyMTNPayment($payment['gateway_transaction_id']);
                
            case 'airtel_money':
                return $this->verifyAirtelPayment($payment['gateway_transaction_id']);
                
            case 'stripe':
                return $this->verifyStripePayment($payment['gateway_transaction_id']);
                
            case 'paypal':
                return $this->verifyPayPalPayment($payment['gateway_transaction_id']);
                
            default:
                return ['status' => $payment['status']];
        }
    }
    
    /**
     * Process refund
     */
    public function processRefund($payment_id, $amount, $reason = '') {
        $payment = $this->getPaymentRecord($payment_id);
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        if ($payment['status'] !== 'completed') {
            throw new Exception('Can only refund completed payments');
        }
        
        // Create refund record
        $refund_id = $this->createRefundRecord($payment_id, $amount, $reason);
        
        try {
            switch ($payment['gateway_name']) {
                case 'stripe':
                    $result = $this->processStripeRefund($payment['gateway_transaction_id'], $amount, $reason);
                    break;
                    
                case 'paypal':
                    $result = $this->processPayPalRefund($payment['gateway_transaction_id'], $amount, $reason);
                    break;
                    
                default:
                    // For other methods, create manual refund record
                    $result = $this->createManualRefund($payment_id, $amount, $reason);
                    break;
            }
            
            // Update refund record
            $this->updateRefundRecord($refund_id, $result);
            
            return [
                'success' => true,
                'refund_id' => $refund_id,
                'result' => $result
            ];
            
        } catch (Exception $e) {
            $this->updateRefundRecord($refund_id, [
                'status' => 'failed',
                'gateway_response' => ['error' => $e->getMessage()]
            ]);
            
            throw $e;
        }
    }
    
    // Helper methods
    private function getBooking($booking_id) {
        $stmt = $this->db->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getPaymentMethod($payment_method_id) {
        $stmt = $this->db->prepare("
            SELECT pm.*, pg.name as gateway_name, pg.configuration 
            FROM payment_methods pm 
            JOIN payment_gateways pg ON pm.gateway_id = pg.id 
            WHERE pm.id = ?
        ");
        $stmt->execute([$payment_method_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function createPaymentRecord($booking_id, $payment_method_id, $amount, $currency) {
        $payment_reference = 'PAY_' . strtoupper(uniqid());
        
        $stmt = $this->db->prepare("
            INSERT INTO payments (
                booking_id, payment_reference, payment_method_id, 
                amount, currency, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([$booking_id, $payment_reference, $payment_method_id, $amount, $currency]);
        return $this->db->lastInsertId();
    }
    
    private function updatePaymentRecord($payment_id, $data) {
        $set_clauses = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $set_clauses[] = "$key = ?";
            $params[] = is_array($value) ? json_encode($value) : $value;
        }
        
        $params[] = $payment_id;
        
        $sql = "UPDATE payments SET " . implode(', ', $set_clauses) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
    
    private function getPaymentRecord($payment_id) {
        $stmt = $this->db->prepare("
            SELECT p.*, pm.name as method_name, pg.name as gateway_name 
            FROM payments p 
            JOIN payment_methods pm ON p.payment_method_id = pm.id 
            JOIN payment_gateways pg ON pm.gateway_id = pg.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$payment_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateBookingPaymentStatus($booking_id) {
        // Calculate total paid amount
        $stmt = $this->db->prepare("
            SELECT SUM(amount) as total_paid, 
                   (SELECT total_amount FROM bookings WHERE id = ?) as total_amount
            FROM payments 
            WHERE booking_id = ? AND status = 'completed'
        ");
        $stmt->execute([$booking_id, $booking_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_paid = $result['total_paid'] ?? 0;
        $total_amount = $result['total_amount'] ?? 0;
        
        if ($total_paid >= $total_amount) {
            $payment_status = 'paid';
            $booking_status = 'confirmed';
        } elseif ($total_paid > 0) {
            $payment_status = 'partial';
            $booking_status = 'pending';
        } else {
            $payment_status = 'pending';
            $booking_status = 'pending';
        }
        
        $stmt = $this->db->prepare("
            UPDATE bookings 
            SET payment_status = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$payment_status, $booking_status, $booking_id]);
    }
    
    private function logTransaction($payment_id, $type, $amount, $currency, $gateway_transaction_id, $response, $status, $error_code = null, $error_message = null) {
        $stmt = $this->db->prepare("
            INSERT INTO payment_transactions (
                payment_id, transaction_type, amount, currency, 
                gateway_transaction_id, gateway_response, status, 
                error_code, error_message, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $payment_id, $type, $amount, $currency,
            $gateway_transaction_id, json_encode($response), $status,
            $error_code, $error_message
        ]);
    }
    
    private function createRefundRecord($payment_id, $amount, $reason) {
        $refund_reference = 'REF_' . strtoupper(uniqid());
        
        $stmt = $this->db->prepare("
            INSERT INTO refunds (
                payment_id, refund_reference, amount, currency, reason, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        // Get currency from payment
        $payment = $this->getPaymentRecord($payment_id);
        $currency = $payment['currency'] ?? 'USD';
        
        $stmt->execute([$payment_id, $refund_reference, $amount, $currency, $reason]);
        return $this->db->lastInsertId();
    }
    
    private function updateRefundRecord($refund_id, $data) {
        $set_clauses = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $set_clauses[] = "$key = ?";
            $params[] = is_array($value) ? json_encode($value) : $value;
        }
        
        $params[] = $refund_id;
        
        $sql = "UPDATE refunds SET " . implode(', ', $set_clauses) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
    
    private function mapStripeStatus($stripe_status) {
        $status_map = [
            'succeeded' => 'completed',
            'processing' => 'processing',
            'requires_payment_method' => 'failed',
            'requires_confirmation' => 'pending',
            'requires_action' => 'pending',
            'canceled' => 'cancelled',
            'requires_capture' => 'pending'
        ];
        
        return $status_map[$stripe_status] ?? 'pending';
    }
    
    private function getPayPalAccessToken() {
        $base_url = $this->config['paypal_mode'] === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $base_url . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config['paypal_client_id'] . ':' . $this->config['paypal_client_secret']);
        curl_setopt($ch, CU  . ':' . $this->config['paypal_client_secret']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            return $data['access_token'];
        } else {
            throw new Exception('Failed to get PayPal access token');
        }
    }
    
    private function makePayPalRequest($url, $method, $data = [], $access_token = '') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'body' => $response,
            'http_code' => $http_code
        ];
    }
    
    private function getMTNAccessToken() {
        // Implementation for MTN access token
        // This would involve the MTN API authentication flow
        return 'mtn_access_token_here';
    }
    
    private function getAirtelAccessToken() {
        $base_url = 'https://openapiuat.airtel.africa'; // Use production URL for live
        
        $auth_data = [
            'client_id' => $this->config['airtel_client_id'],
            'client_secret' => $this->config['airtel_client_secret'],
            'grant_type' => 'client_credentials'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $base_url . '/auth/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($auth_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            return $data['access_token'];
        } else {
            throw new Exception('Failed to get Airtel access token');
        }
    }
    
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    private function sendPaymentConfirmation($booking, $payment_result) {
        // Send email confirmation
        $subject = "Payment Confirmation - Booking #" . $booking['booking_reference'];
        $message = "Your payment has been successfully processed...";
        
        // Implementation would use your email system
        sendEmail($booking['email'], $subject, $message);
    }
    
    // Verification methods for different payment providers
    private function verifyMTNPayment($transaction_id) {
        // MTN payment verification implementation
        return ['status' => 'completed'];
    }
    
    private function verifyAirtelPayment($transaction_id) {
        // Airtel payment verification implementation
        return ['status' => 'completed'];
    }
    
    private function verifyStripePayment($transaction_id) {
        // Stripe payment verification implementation
        return ['status' => 'completed'];
    }
    
    private function verifyPayPalPayment($transaction_id) {
        // PayPal payment verification implementation
        return ['status' => 'completed'];
    }
    
    // Refund methods
    private function processStripeRefund($transaction_id, $amount, $reason) {
        \Stripe\Stripe::setApiKey($this->config['stripe_secret_key']);
        
        try {
            $refund = \Stripe\Refund::create([
                'payment_intent' => $transaction_id,
                'amount' => $amount * 100,
                'reason' => 'requested_by_customer'
            ]);
            
            return [
                'success' => true,
                'gateway_refund_id' => $refund->id,
                'status' => 'completed'
            ];
        } catch (Exception $e) {
            throw new Exception('Stripe refund failed: ' . $e->getMessage());
        }
    }
    
    private function processPayPalRefund($transaction_id, $amount, $reason) {
        // PayPal refund implementation
        return [
            'success' => true,
            'gateway_refund_id' => 'PP_REFUND_' . time(),
            'status' => 'completed'
        ];
    }
    
    private function createManualRefund($payment_id, $amount, $reason) {
        // Create manual refund record for admin processing
        return [
            'success' => true,
            'status' => 'pending',
            'requires_manual_processing' => true
        ];
    }
}
?>
