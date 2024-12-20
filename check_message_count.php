<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo json_encode([
        'error' => 'auth_required',
        'message' => 'Please sign in to continue'
    ]);
    exit;
}

try {
    // Check user's subscription status
    $stmt = $conn->prepare("SELECT subscription_plan FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Premium users have unlimited messages
    if ($user && in_array($user['subscription_plan'], ['premium', 'teams'])) {
        echo json_encode([
            'isLocked' => false,
            'remainingMessages' => null // null indicates unlimited
        ]);
        exit;
    }

    // Check message count for free users
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count, MAX(timestamp) as last_message 
        FROM votality_messages 
        WHERE user_id = ? AND sender = 'user' 
        AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messageData = $result->fetch_assoc();

    $messageCount = $messageData['count'];
    $lastMessageTime = strtotime($messageData['last_message'] ?? '0');
    $currentTime = time();
    $timeSinceLastMessage = $currentTime - $lastMessageTime;
    $cooldownPeriod = 7200; // 2 hours in seconds

    // Check if user is in cooldown period
    if ($messageCount >= 4 && $timeSinceLastMessage < $cooldownPeriod) {
        echo json_encode([
            'isLocked' => true,
            'remainingMessages' => 0,
            'cooldownTime' => $cooldownPeriod - $timeSinceLastMessage
        ]);
    } else {
        // Reset count if cooldown period has passed
        if ($messageCount >= 4 && $timeSinceLastMessage >= $cooldownPeriod) {
            $messageCount = 0;
        }
        
        echo json_encode([
            'isLocked' => false,
            'remainingMessages' => 4 - $messageCount
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'error' => 'server_error',
        'message' => 'An error occurred while checking message count'
    ]);
}
?>