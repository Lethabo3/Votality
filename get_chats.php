<?php
// get_chats_with_auth.php
session_start();

// Include necessary files
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

// Function for debug logging
function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . print_r($message, true) . "\n", 3, 'chat_debug.log');
}

// Function to handle login
function performLogin($email, $password) {
    global $conn;
    
    try {
        // Using prepared statement for security
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? AND password = ?");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Set session variables
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['logged_in'] = true;
            $_SESSION['email'] = $row['email'];
            
            debug_log("Login successful for user: " . $email);
            return true;
        }
        
        debug_log("Login failed for user: " . $email);
        return false;
    } catch (Exception $e) {
        debug_log("Login error: " . $e->getMessage());
        throw $e;
    }
}

// Function to get recent chats
function getRecentChats($userId) {
    global $conn;
    
    try {
        // Query to get chats matching the database structure
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
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
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
        debug_log("Error fetching chats: " . $e->getMessage());
        throw $e;
    }
}

try {
    // Verify database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Test credentials
    $email = 'instinctslump@gmail.com';
    $password = 'ttt';

    debug_log("Attempting login for: " . $email);
    
    // Perform login
    if (!performLogin($email, $password)) {
        throw new Exception("Login failed");
    }

    // After successful login, get chats
    $response = getRecentChats($_SESSION['user_id']);
    debug_log("Retrieved chats: " . json_encode($response));
    
    echo json_encode($response);

} catch (Exception $e) {
    $errorResponse = [
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ];
    debug_log("Error occurred: " . $e->getMessage());
    echo json_encode($errorResponse);
}

exit;
?>