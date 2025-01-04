<?php
// get_chats.php
session_start();

// Include the UsersBimo file for database connection
require_once 'UsersBimo.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'chat_error.log');

// Set necessary headers
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

// Function to log debug messages
function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . print_r($message, true) . "\n", 3, 'chat_debug.log');
}

// Function to check if user is logged in
function checkSession() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
}

// Function to get recent chats from database
function getRecentChatsFromDatabase($userId) {
    global $conn;
    
    try {
        debug_log("Starting database query for user: " . $userId);
        
        // Modified query to match your database structure
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
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
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
        
        debug_log("Successfully retrieved " . count($chats) . " chats");
        return ['chats' => $chats];

    } catch (Exception $e) {
        debug_log("Database error: " . $e->getMessage());
        throw $e;
    }
}

try {
    // Verify database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    debug_log("Checking session status");
    
    // Check authentication
    if (!checkSession()) {
        echo json_encode([
            'error' => 'auth_required',
            'message' => 'Please sign in to view chats'
        ]);
        exit;
    }

    debug_log("Session valid, fetching chats for user: " . $_SESSION['user_id']);
    
    // Get chats
    $response = getRecentChatsFromDatabase($_SESSION['user_id']);
    
    debug_log("Sending response: " . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    $errorResponse = [
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ];
    debug_log("Error occurred: " . $e->getMessage());
    echo json_encode($errorResponse);
}

// Ensure no additional output
exit;
?>