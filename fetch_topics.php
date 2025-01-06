<?php
/**
 * fetch_topics.php
 * Purpose: Retrieves chat topics for authenticated users from the database
 * Includes error handling, security measures, and detailed logging
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set timezone - important for consistent timestamps
date_default_timezone_set('Africa/Johannesburg');

// Start session securely
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
session_start();

// Initialize logging function
function logMessage($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] [$type] $message");
}

// Database configuration
$config = [
    'servername' => 'localhost',
    'username'   => 'votalik6n1q7_Lethabo',
    'password'   => 'Lethabo1204',
    'dbname'     => 'votalik6n1q7_Votality'
];

// Function to validate user authentication
function validateUser() {
    // For development, allow test user ID
    if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
        return '17360786907837';
    }
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    return $_SESSION['user_id'];
}

try {
    logMessage("Starting chat topics retrieval process");
    
    // Create database connection with error handling
    $dsn = "mysql:host={$config['servername']};dbname={$config['dbname']};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    $conn = new PDO($dsn, $config['username'], $config['password'], $options);
    logMessage("Database connection established successfully");
    
    // Get and validate user ID
    $userId = validateUser();
    logMessage("Processing request for user ID: $userId");
    
    // Prepare the query with proper indexing hints
    $query = "
        SELECT 
            chat_id,
            topic,
            created_at,
            updated_at
        FROM votality_chats USE INDEX (idx_user_updated)
        WHERE user_id = :userId
        ORDER BY updated_at DESC
        LIMIT 100
    ";
    
    // Execute query with proper error handling
    $stmt = $conn->prepare($query);
    $stmt->execute(['userId' => $userId]);
    $chats = $stmt->fetchAll();
    
    // Log query results
    $chatCount = count($chats);
    logMessage("Retrieved $chatCount chats for user");
    
    // Process and sanitize the output
    $processedChats = array_map(function($chat) {
        return [
            'chat_id'    => htmlspecialchars($chat['chat_id']),
            'topic'      => htmlspecialchars($chat['topic'] ?? 'No Topic'),
            'created_at' => (new DateTime($chat['created_at']))->format('Y-m-d H:i:s'),
            'updated_at' => (new DateTime($chat['updated_at']))->format('Y-m-d H:i:s')
        ];
    }, $chats);
    
    // Prepare success response
    $response = [
        'success'   => true,
        'chats'     => $processedChats,
        'metadata'  => [
            'total_count' => $chatCount,
            'timestamp'   => date('Y-m-d H:i:s'),
            'user_id'     => $userId
        ]
    ];
    
    logMessage("Successfully prepared response with $chatCount chats");
    
} catch (PDOException $e) {
    logMessage("Database error: " . $e->getMessage(), 'ERROR');
    $response = [
        'success' => false,
        'error'   => 'A database error occurred',
        'code'    => 'DB_ERROR'
    ];
    http_response_code(500);
    
} catch (Exception $e) {
    logMessage("Application error: " . $e->getMessage(), 'ERROR');
    $response = [
        'success' => false,
        'error'   => 'An application error occurred',
        'code'    => 'APP_ERROR'
    ];
    http_response_code(400);
}

// Set security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Output the response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Close the connection
$conn = null;
logMessage("Request completed");