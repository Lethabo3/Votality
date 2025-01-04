<?php
// This file handles only retrieving chats, expecting an existing session
session_start();

// Include the database connection
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

// Helper function for logging debug information
function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . print_r($message, true) . "\n", 3, 'chat_debug.log');
}

// Check if user is logged in
function checkSession() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
}

// Main function to retrieve chats from database
function getRecentChatsFromDatabase($userId) {
    global $conn;
    
    try {
        // Query matches your database structure as shown in the screenshot
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
            throw new Exception("Database query preparation failed");
        }
        
        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Database query execution failed");
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
        
        return ['chats' => $chats];
    } catch (Exception $e) {
        debug_log("Database error: " . $e->getMessage());
        throw $e;
    }
}

try {
    // Verify database connection first
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Check if user is logged in
    if (!checkSession()) {
        echo json_encode([
            'error' => 'auth_required',
            'message' => 'Please sign in to view chats'
        ]);
        exit;
    }

    // Get and return the chats
    $response = getRecentChatsFromDatabase($_SESSION['user_id']);
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ]);
}