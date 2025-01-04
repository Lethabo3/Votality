<?php
session_start();
require_once 'UsersBimo.php';

// Set headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Debug function to log variables
function debug_log($message, $data = null) {
    error_log(date('[Y-m-d H:i:s] ') . $message . ($data ? ': ' . print_r($data, true) : '') . "\n", 3, 'chat_debug.log');
}

try {
    // Verify we have a database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Log session data for debugging
    debug_log("Session data", $_SESSION);

    // Verify user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        echo json_encode([
            'error' => 'auth_required',
            'message' => 'Please sign in to view chats'
        ]);
        exit;
    }

    $userId = $_SESSION['user_id'];
    debug_log("Fetching chats for user ID", $userId);

    // Query to get chats - note we're explicitly selecting the fields we saw in your database
    $stmt = $conn->prepare("
        SELECT 
            chat_id,
            topic,
            created_at,
            updated_at
        FROM votality_chats 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");

    if (!$stmt) {
        debug_log("Query preparation failed", $conn->error);
        throw new Exception("Failed to prepare database query: " . $conn->error);
    }

    $stmt->bind_param("i", $userId);
    
    if (!$stmt->execute()) {
        debug_log("Query execution failed", $stmt->error);
        throw new Exception("Failed to execute database query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $chats = [];

    // Fetch all chats and include debug logging
    while ($row = $result->fetch_assoc()) {
        $chats[] = [
            'chat_id' => $row['chat_id'],
            'topic' => $row['topic'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    debug_log("Found chats", $chats);

    // Return both authentication status and chats
    $response = [
        'chats' => $chats,
        'email' => $_SESSION['email'],
        'authenticated' => true
    ];

    debug_log("Sending response", $response);
    echo json_encode($response);

} catch (Exception $e) {
    debug_log("Error occurred", $e->getMessage());
    echo json_encode([
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ]);
}