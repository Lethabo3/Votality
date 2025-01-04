<?php
// get_chats.php
session_start();

// Include the UsersBimo file for database connection
require_once 'UsersBimo.php';

// Set necessary headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Function to log debug messages - using the same format as the main system
function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . print_r($message, true) . "\n", 3, '/path/to/votality-debug.log');
}

// Function to check if user is logged in
function checkSession() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
}

// Function to get recent chats from database
function getRecentChatsFromDatabase($userId) {
    global $conn; // Using the connection from UsersBimo.php
    
    try {
        // Prepare the query to get recent chats
        $stmt = $conn->prepare("
            SELECT chat_id, topic, created_at
            FROM votality_chats 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 6
        ");
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $chats = [];
        while ($row = $result->fetch_assoc()) {
            $chats[] = [
                'chat_id' => $row['chat_id'],
                'topic' => $row['topic'] ?? 'New Chat',
                'created_at' => $row['created_at']
            ];
        }
        
        debug_log("Successfully retrieved " . count($chats) . " chats for user " . $userId);
        return ['chats' => $chats];

    } catch (Exception $e) {
        debug_log("Database error in getRecentChatsFromDatabase: " . $e->getMessage());
        return ['error' => 'Database error: ' . $e->getMessage()];
    }
}

try {
    // First check if user is logged in
    if (!checkSession()) {
        debug_log("Session check failed - user not logged in");
        echo json_encode([
            'error' => 'auth_required',
            'message' => 'Please sign in to view chats'
        ]);
        exit;
    }

    // Get user's chats using the global connection from UsersBimo.php
    debug_log("Fetching chats for user ID: " . $_SESSION['user_id']);
    $response = getRecentChatsFromDatabase($_SESSION['user_id']);
    
    debug_log("Final response: " . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    debug_log("Error in main execution: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>