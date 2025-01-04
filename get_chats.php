<?php
// Start session to access user data
session_start();

// Include database connection
require_once 'UsersBimo.php';

// Set up headers for API responses
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Function to check if user is properly logged in
function checkSession() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['logged_in']) && 
           isset($_SESSION['session_id']) &&
           $_SESSION['logged_in'] === true;
}

try {
    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Verify user is logged in
    if (!checkSession()) {
        echo json_encode([
            'error' => 'auth_required',
            'message' => 'Please sign in to view chats'
        ]);
        exit;
    }

    // Get user's chats
    $stmt = $conn->prepare("
        SELECT 
            chat_id,
            topic,
            created_at,
            updated_at,
            summary
        FROM votality_chats 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare database query");
    }

    $userId = $_SESSION['user_id'];
    $stmt->bind_param("i", $userId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute database query");
    }

    $result = $stmt->get_result();
    $chats = [];

    while ($row = $result->fetch_assoc()) {
        $chats[] = [
            'chat_id' => $row['chat_id'],
            'topic' => $row['topic'] ?? 'New Chat',
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'summary' => $row['summary']
        ];
    }

    echo json_encode(['chats' => $chats]);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ]);
}