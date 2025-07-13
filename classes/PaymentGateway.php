<?php

class PaymentGateway {
    private $db;
    private $stripe_secret_key;
    private $paypal_client_id;
    private $paypal_client_secret;
    
    public function __construct($database) {
        $this->db = $database;
        $this->stripe_secret_key = STRIPE_SECRET_KEY;
        $this->paypal_client_id = PAYPAL_CLIENT_ID;
        $this->paypal_client_secret = PAYPAL_CLIENT_SECRET;
    }
}
    
    public function createStripePaymentIntent($amount, $currency = 'USD', $metadata = []) {
        try {
            $stripe = new \Stripe\StripeClient($this->stripe_secret_key);
            
            $intent = $stripe->paymentIntents->create([
                'amount' => $amount * 100, // Stripe uses cents
                'currency' => strtolower($currency),
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);
            
            return [
                'success' => true,
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function createPayPalOrder($amount, $currency = 'USD', $return_url, $cancel_url) {
        try {
            $access_token = $this->getPayPalAccessToken();
            
            $order_data = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', '')
                    ]
                ]],
                'application_context' => [
                    'return_url' => $return_url,
                    'cancel_url' => $cancel_url
                ]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->getPayPalApiUrl() . '/v2/checkout/orders');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 201) {
                $order = json_decode($response, true);
                return [
                    'success' => true,
                    'order_id' => $order['id'],
                    'approval_url' => $this->getApprovalUrl($order['links'])
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to create PayPal order'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function capturePayPalOrder($order_id) {
        try {
            $access_token = $this->getPayPalAccessToken();
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->getPayPalApiUrl() . '/v2/checkout/orders/' . $order_id . '/capture');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 201) {
                $capture = json_decode($response, true);
                return [
                    'success' => true,
                    'capture_id' => $capture['purchase_units'][0]['payments']['captures'][0]['id'],
                    'amount' => $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to capture PayPal payment'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function recordPayment($booking_id, $payment_method, $amount, $currency, $transaction_id, $status = 'completed') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payments (
                    booking_id, payment_method, amount, currency, 
                    transaction_id, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $booking_id, $payment_method, $amount, $currency,
                $transaction_id, $status
            ]);
            
            return [
                'success' => true,
                'payment_id' => $this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function processRefund($payment_id, $amount, $reason = '') {
        try {
            // Get payment details
            $stmt = $this->db->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch();
            
            if (!$payment) {
                return ['success' => false, 'error' => 'Payment not found'];
            }
            
            $refund_result = null;
            
            if ($payment['payment_method'] === 'stripe') {
                $refund_result = $this->processStripeRefund($payment['transaction_id'], $amount);
            } elseif ($payment['payment_method'] === 'paypal') {
                $refund_result = $this->processPayPalRefund($payment['transaction_id'], $amount);
            }
            
            if ($refund_result && $refund_result['success']) {
                // Record refund
                $stmt = $this->db->prepare("
                    INSERT INTO refunds (
                        payment_id, amount, reason, refund_id, status, created_at
                    ) VALUES (?, ?, ?, ?, 'completed', NOW())
                ");
                
                $stmt->execute([
                    $payment_id, $amount, $reason, $refund_result['refund_id']
                ]);
                
                return ['success' => true, 'refund_id' => $refund_result['refund_id']];
            }
            
            return $refund_result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getPayPalAccessToken() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getPayPalApiUrl() . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->paypal_client_id . ':' . $this->paypal_client_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $token_data = json_decode($response, true);
        return $token_data['access_token'];
    }
    
    private function getPayPalApiUrl() {
        return PAYPAL_MODE === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
    }
    
    private function getApprovalUrl($links) {
        foreach ($links as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        
        foreach ($links as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }
        return null;
    }
    
    private function processStripeRefund($payment_intent_id, $amount) {
        try {
            $stripe = new \Stripe\StripeClient($this->stripe_secret_key);
            
            $refund = $stripe->refunds->create([
                'payment_intent' => $payment_intent_id,
                'amount' => $amount * 100, // Stripe uses cents
            ]);
            
            return [
                'success' => true,
                'refund_id' => $refund->id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function processPayPalRefund($capture_id, $amount) {
        try {
            $access_token = $this->getPayPalAccessToken();
            
            $refund_data = [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => 'USD'
                ]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->getPayPalApiUrl() . '/v2/payments/captures/' . $capture_id . '/refund');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($refund_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 201) {
                $refund = json_decode($response, true);
                return [
                    'success' => true,
                    'refund_id' => $refund['id']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to process PayPal refund'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

?>
