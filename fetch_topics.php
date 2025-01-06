<?php
// At the very top of the file
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/error.log');

function logDebug($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message);
    // Also output for immediate visibility
    echo "<!-- Debug: $message -->\n";
}

// Start logging
logDebug("Script started");

try {
    // Database connection details
    $servername = "localhost";
    $username = "votalik6n1q7_Lethabo";
    $password = "Lethabo1204";
    $dbname = "votalik6n1q7_Votality";
    
    logDebug("Attempting database connection");
    
    // Create connection with error reporting
    $conn = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    logDebug("Database connection successful");
    
    // Simple test query
    $testQuery = $conn->query("SELECT 1");
    logDebug("Test query successful");
    
    // Your actual query
    $userId = $_SESSION['user_id'] ?? '17360786907837';
    logDebug("Using user ID: " . $userId);
    
    $query = "SELECT chat_id, topic, created_at, updated_at 
              FROM votality_chats 
              WHERE user_id = :userId 
              ORDER BY updated_at DESC";
    
    $stmt = $conn->prepare($query);
    logDebug("Query prepared");
    
    $stmt->execute(['userId' => $userId]);
    logDebug("Query executed");
    
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logDebug("Found " . count($chats) . " chats");
    
    // Send success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'chats' => $chats,
        'debug' => 'Query completed successfully'
    ]);
    
} catch (PDOException $e) {
    logDebug("Database error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'debug_message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    logDebug("General error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'General error occurred',
        'debug_message' => $e->getMessage()
    ]);
}