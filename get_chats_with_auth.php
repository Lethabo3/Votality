<?php
// This file handles both authentication and chat retrieval
session_start();

// Include database connection
require_once 'UsersBimo.php';

// Set up API response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

// Helper function for logging
function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . print_r($message, true) . "\n", 3, 'auth_chat_debug.log');
}

// Function to handle automatic login
function performAutoLogin() {
    global $conn;
    
    try {
        // Test credentials for development
        $email = 'instinctslump@gmail.com';
        $password = 'ttt';
        
        // Query to check credentials and get user information
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? AND password = ?");
        if (!$stmt) {
            throw new Exception("Login query preparation failed");
        }
        
        $stmt->bind_param("ss", $email, $password);
        
        if (!$stmt->execute()) {
            throw new Exception("Login query execution failed");
        }
        
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Set up session for the logged in user
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['logged_in'] = true;
            $_SESSION['email'] = $user['email'];
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        debug_log("Login error: " . $e->getMessage());
        throw $e;
    }
}

// Function to get chats for the logged-in user
function getRecentChats($userId) {
    global $conn;
    
    try {
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
            throw new Exception("Chat query preparation failed");
        }
        
        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Chat query execution failed");
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
        debug_log("Error fetching chats: " . $e->getMessage());
        throw $e;
    }
}

try {
    // First verify database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Attempt automatic login if not already logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        debug_log("No active session, attempting auto-login");
        if (!performAutoLogin()) {
            throw new Exception("Auto-login failed");
        }
        debug_log("Auto-login successful");
    }

    // Now get the chats for the logged-in user
    $response = getRecentChats($_SESSION['user_id']);
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ]);
}