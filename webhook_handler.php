<?php
// webhook_handler.php
require_once 'database.php';

// Get the webhook payload
$payload = @file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

// Verify webhook signature
$computedSignature = hash_hmac('sha256', $payload, LEMON_SQUEEZY_WEBHOOK_SECRET);
if (!hash_equals($signature, $computedSignature)) {
    http_response_code(401);
    exit('Invalid signature');
}

$data = json_decode($payload, true);
$event = $data['meta']['event_name'] ?? '';
$customData = $data['meta']['custom_data'] ?? [];

switch ($event) {
    case 'order_created':
        handleOrderCreated($data);
        break;
    case 'subscription_created':
        handleSubscriptionCreated($data);
        break;
    case 'subscription_cancelled':
        handleSubscriptionCancelled($data);
        break;
    case 'subscription_resumed':
        handleSubscriptionResumed($data);
        break;
    case 'subscription_expired':
        handleSubscriptionExpired($data);
        break;
}

function handleOrderCreated($data) {
    global $conn;
    
    $orderId = $data['data']['id'];
    $userId = $data['meta']['custom_data']['user_id'] ?? null;
    $amount = $data['data']['attributes']['total'];
    
    $stmt = $conn->prepare("
        INSERT INTO invoices (
            invoice_id, 
            user_id, 
            amount, 
            status, 
            lemon_squeezy_order_id
        ) VALUES (?, ?, ?, 'paid', ?)
    ");
    
    $invoiceId = generateInvoiceId();
    $stmt->bind_param("ssds", $invoiceId, $userId, $amount, $orderId);
    $stmt->execute();
}

function handleSubscriptionCreated($data) {
    global $conn;
    
    $subscriptionId = $data['data']['id'];
    $userId = $data['meta']['custom_data']['user_id'] ?? null;
    $planType = determinePlanType($data['data']['attributes']['variant_id']);
    
    $stmt = $conn->prepare("
        INSERT INTO subscription_history (
            user_id,
            subscription_id,
            plan_type,
            status
        ) VALUES (?, ?, ?, 'active')
    ");
    
    $stmt->bind_param("sss", $userId, $subscriptionId, $planType);
    $stmt->execute();
    
    // Update user's subscription plan
    $stmt = $conn->prepare("
        UPDATE users 
        SET subscription_plan = ?, 
            subscription_status = 'active',
            lemon_squeezy_subscription_id = ?
        WHERE user_id = ?
    ");
    
    $stmt->bind_param("sss", $planType, $subscriptionId, $userId);
    $stmt->execute();
}

function handleSubscriptionCancelled($data) {
    global $conn;
    
    $subscriptionId = $data['data']['id'];
    
    // Update subscription history
    $stmt = $conn->prepare("
        UPDATE subscription_history 
        SET status = 'cancelled',
            ended_at = CURRENT_TIMESTAMP 
        WHERE subscription_id = ? 
        AND status = 'active'
    ");
    
    $stmt->bind_param("s", $subscriptionId);
    $stmt->execute();
    
    // Update user's subscription status
    $stmt = $conn->prepare("
        UPDATE users 
        SET subscription_status = 'cancelled' 
        WHERE lemon_squeezy_subscription_id = ?
    ");
    
    $stmt->bind_param("s", $subscriptionId);
    $stmt->execute();
}

function generateInvoiceId() {
    return 'INV-' . strtoupper(uniqid());
}

function determinePlanType($variantId) {
    // Map your Lemon Squeezy variant IDs to plan types
    $planMap = [
        'variant_id_1' => 'premium',
        'variant_id_2' => 'teams'
    ];
    
    return $planMap[$variantId] ?? 'free';
}