<?php
header('Content-Type: application/json');

require_once '../includes/database.php';
require_once '../includes/Auth.php';

try {
    // Initialize Auth
    $auth = new Auth($conn);
    
    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        echo json_encode([
            'status' => 'success',
            'plan' => 'free',
            'isActive' => true
        ]);
        exit;
    }

    // Get user data including subscription
    $user = $auth->getUser();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    echo json_encode([
        'status' => 'success',
        'plan' => $user['subscription_plan'],
        'isActive' => $user['subscription_status'] === 'active',
        'expiryDate' => $user['subscription_expiry']
    ]);

} catch (Exception $e) {
    error_log("Subscription error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch subscription status',
        'plan' => 'free'
    ]);
}