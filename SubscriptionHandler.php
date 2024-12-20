<?php
require_once 'database.php';
require_once 'lemon-squeezy-config.php';

class SubscriptionHandler {
    private $conn;
    private $secret_key;
    private const SUBSCRIPTION_TYPES = ['free', 'premium', 'teams'];
    private const WEBHOOK_EVENTS = [
        'order_created',
        'order_refunded',
        'subscription_created',
        'subscription_updated',
        'subscription_payment_success',
        'subscription_payment_failed',
        'subscription_cancelled'
    ];
    
    public function __construct($conn) {
        if (!$conn) {
            throw new Exception('Database connection required');
        }
        $this->conn = $conn;
        $this->secret_key = LemonSqueezyConfig::getApiKey();
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function createCheckout($userId, $plan = 'premium') {
        try {
            if (!in_array($plan, self::SUBSCRIPTION_TYPES)) {
                throw new Exception('Invalid subscription plan');
            }
            
            // Validate user and get details
            $stmt = $this->conn->prepare("
                SELECT email, subscription_plan, subscription_status 
                FROM users 
                WHERE user_id = ?
            ");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Check if user already has an active subscription
            if ($user['subscription_status'] === 'active' && $user['subscription_plan'] === $plan) {
                throw new Exception('User already has an active subscription to this plan');
            }
            
            // Get plan details
            $variantId = LemonSqueezyConfig::getVariantId($plan);
            if (!$variantId) {
                throw new Exception('Invalid plan configuration');
            }
            
            // Build checkout URL
            $checkoutUrl = "https://votality.lemonsqueezy.com/checkout/buy/{$variantId}?";
            $params = [
                'checkout[custom][user_id]' => $userId,
                'checkout[email]' => $user['email'],
                'checkout[success_url]' => 'https://votalityai.com/subscription/success',
                'checkout[cancel_url]' => 'https://votalityai.com/subscription/cancel'
            ];
            
            // Log the checkout initiation
            $this->logSubscriptionEvent($userId, 'checkout_initiated', [
                'plan' => $plan,
                'variant_id' => $variantId,
                'user_email' => $user['email']
            ]);
            
            return $checkoutUrl . http_build_query($params);
            
        } catch (Exception $e) {
            $this->logError('create_checkout', $e->getMessage());
            throw $e;
        }
    }
    
    public function handleWebhook() {
        try {
            // Get webhook payload
            $payload = file_get_contents('php://input');
            if (!$payload) {
                throw new Exception('Empty webhook payload');
            }
            
            // Verify signature
            $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
            if (!$signature || !$this->verifyWebhookSignature($payload, $signature)) {
                throw new Exception('Invalid webhook signature');
            }
            
            // Parse and validate payload
            $data = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON payload');
            }
            
            $event = $data['meta']['event_name'] ?? '';
            if (!in_array($event, self::WEBHOOK_EVENTS)) {
                throw new Exception('Unsupported webhook event: ' . $event);
            }
            
            $customData = $data['meta']['custom_data'] ?? [];
            $userId = $customData['user_id'] ?? null;
            
            if (!$userId) {
                throw new Exception('No user ID in webhook data');
            }
            
            // Verify user exists
            if (!$this->userExists($userId)) {
                throw new Exception('User not found: ' . $userId);
            }
            
            // Log webhook receipt
            $this->logSubscriptionEvent($userId, 'webhook_received', [
                'event' => $event,
                'data' => $data
            ]);
            
            // Handle different webhook events
            switch ($event) {
                case 'order_created':
                case 'subscription_created':
                    $this->handleOrderCreated($userId, $data);
                    break;
                    
                case 'subscription_payment_success':
                    $this->handlePaymentSuccess($userId, $data);
                    break;
                    
                case 'subscription_payment_failed':
                    $this->handlePaymentFailed($userId, $data);
                    break;
                    
                case 'subscription_cancelled':
                    $this->handleSubscriptionCancelled($userId, $data);
                    break;
                    
                case 'order_refunded':
                    $this->handleOrderRefunded($userId, $data);
                    break;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logError('webhook_handler', $e->getMessage());
            throw $e;
        }
    }
    
    public function verifySubscription($userId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    subscription_plan,
                    subscription_status,
                    subscription_expiry,
                    lemon_squeezy_customer_id
                FROM users 
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $subscription = $result->fetch_assoc();
            
            if (!$subscription) {
                return false;
            }
            
            return [
                'isActive' => $subscription['subscription_status'] === 'active',
                'plan' => $subscription['subscription_plan'],
                'expiryDate' => $subscription['subscription_expiry'],
                'customerId' => $subscription['lemon_squeezy_customer_id']
            ];
            
        } catch (Exception $e) {
            $this->logError('verify_subscription', $e->getMessage());
            return false;
        }
    }
    
    private function handleOrderCreated($userId, $data) {
        try {
            $variantId = $data['data']['attributes']['variant_id'] ?? '';
            $customerId = $data['data']['attributes']['customer_id'] ?? '';
            $orderId = $data['data']['id'] ?? '';
            
            // Determine plan from variant ID
            $plan = $variantId == '570057' ? 'premium' : 'teams';
            
            // Begin transaction
            $this->conn->begin_transaction();
            
            // Update user subscription
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET subscription_plan = ?,
                    subscription_status = 'active',
                    subscription_expiry = DATE_ADD(NOW(), INTERVAL 1 MONTH),
                    lemon_squeezy_customer_id = ?,
                    lemon_squeezy_order_id = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("ssss", $plan, $customerId, $orderId, $userId);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Failed to update user subscription');
            }
            
            // Update session if user is logged in
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $userId) {
                $_SESSION['subscription_plan'] = $plan;
                $_SESSION['subscription_status'] = 'active';
            }
            
            // Log the event
            $this->logSubscriptionEvent($userId, 'subscription_activated', [
                'plan' => $plan,
                'customer_id' => $customerId,
                'order_id' => $orderId
            ]);
            
            // Commit transaction
            $this->conn->commit();
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    private function handlePaymentSuccess($userId, $data) {
        try {
            $this->conn->begin_transaction();
            
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET subscription_status = 'active',
                    subscription_expiry = DATE_ADD(NOW(), INTERVAL 1 MONTH),
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Failed to update subscription status');
            }
            
            $this->logSubscriptionEvent($userId, 'payment_success', $data);
            
            $this->conn->commit();
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    private function handlePaymentFailed($userId, $data) {
        try {
            $this->conn->begin_transaction();
            
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET subscription_status = 'payment_failed',
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            
            $this->logSubscriptionEvent($userId, 'payment_failed', $data);
            
            $this->conn->commit();
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    private function handleSubscriptionCancelled($userId, $data) {
        try {
            $this->conn->begin_transaction();
            
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET subscription_status = 'cancelled',
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            
            $this->logSubscriptionEvent($userId, 'subscription_cancelled', $data);
            
            $this->conn->commit();
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    private function handleOrderRefunded($userId, $data) {
        try {
            $this->conn->begin_transaction();
            
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET subscription_status = 'refunded',
                    subscription_plan = 'free',
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            
            $this->logSubscriptionEvent($userId, 'order_refunded', $data);
            
            $this->conn->commit();
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    private function verifyWebhookSignature($payload, $signature) {
        if (empty($this->secret_key)) {
            throw new Exception('Missing API key');
        }
        $computedSignature = hash_hmac('sha256', $payload, $this->secret_key);
        return hash_equals($computedSignature, $signature);
    }
    
    private function logSubscriptionEvent($userId, $event, $data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO subscription_logs 
                (user_id, event_type, event_data, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            
            $eventData = json_encode($data);
            $stmt->bind_param("sss", $userId, $event, $eventData);
            $stmt->execute();
            
        } catch (Exception $e) {
            $this->logError('log_event', $e->getMessage());
        }
    }
    
    private function logError($context, $message) {
        $timestamp = date('Y-m-d H:i:s');
        error_log("[{$timestamp}] Subscription Error [{$context}]: {$message}");
    }
    
    private function userExists($userId) {
        $stmt = $this->conn->prepare("SELECT 1 FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
}
?>