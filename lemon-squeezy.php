<?php
require_once 'database.php';
require_once 'lemon-squeezy-config.php';

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/lemon-squeezy-webhook.log');

function logWebhook($message) {
    error_log(date('[Y-m-d H:i:s] ') . print_r($message, true) . "\n");
}

try {
    // Get the payload
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    
    // Verify webhook signature
    $computedSignature = hash_hmac('sha256', $payload, LemonSqueezyConfig::getApiKey());
    if (!hash_equals($computedSignature, $signature)) {
        http_response_code(401);
        exit('Invalid signature');
    }
    
    // Decode the payload
    $data = json_decode($payload, true);
    $event = $data['meta']['event_name'] ?? '';
    $customData = $data['meta']['custom_data'] ?? [];
    
    // Get the user ID from custom data
    $userId = $customData['user_id'] ?? null;
    if (!$userId) {
        throw new Exception('No user ID provided in webhook');
    }
    
    // Handle different webhook events
    switch ($event) {
        case 'order_created':
            // Initial order created
            $orderId = $data['data']['id'] ?? '';
            $variantId = $data['data']['attributes']['variant_id'] ?? '';
            
            // Determine the plan from the variant ID
            $plan = null;
            if ($variantId == '570057') { // Premium plan variant ID
                $plan = 'premium';
            } else if ($variantId == 'TEAMS_VARIANT_ID') { // Replace with actual Teams variant ID
                $plan = 'teams';
            }
            
            if ($plan) {
                // Update user's subscription in database
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET subscription_plan = ?,
                        subscription_status = 'active',
                        subscription_expiry = DATE_ADD(NOW(), INTERVAL 1 MONTH),
                        lemon_squeezy_customer_id = ?,
                        lemon_squeezy_order_id = ?
                    WHERE user_id = ?
                ");
                
                $customerId = $data['data']['attributes']['customer_id'] ?? '';
                $stmt->bind_param("ssss", $plan, $customerId, $orderId, $userId);
                $stmt->execute();
                
                logWebhook("Updated subscription for user $userId to $plan plan");
            }
            break;
            
        case 'subscription_payment_success':
            // Renewal payment successful
            $stmt = $conn->prepare("
                UPDATE users 
                SET subscription_status = 'active',
                    subscription_expiry = DATE_ADD(NOW(), INTERVAL 1 MONTH)
                WHERE user_id = ?
            ");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            break;
            
        case 'subscription_payment_failed':
            // Payment failed
            $stmt = $conn->prepare("
                UPDATE users 
                SET subscription_status = 'payment_failed'
                WHERE user_id = ?
            ");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            break;
            
        case 'subscription_cancelled':
            // Subscription cancelled
            $stmt = $conn->prepare("
                UPDATE users 
                SET subscription_status = 'cancelled'
                WHERE user_id = ?
            ");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            break;
    }
    
    http_response_code(200);
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    logWebhook("Error processing webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}