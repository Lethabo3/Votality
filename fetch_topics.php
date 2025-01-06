<?php
// Important: This must be the very first line of the file
// No whitespace or HTML before this opening PHP tag

// Buffer all output
ob_start();

// Error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/debug.log');

// Function for logging that doesn't output to browser
function logDebug($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message);
}

// Clear any previous output buffers and headers
if (headers_sent()) {
    logDebug("Headers already sent!");
}

// Ensure we're starting fresh
ob_clean();

try {
    // Database connection details
    $servername = "localhost";
    $username = "votalik6n1q7_Lethabo";
    $password = "Lethabo1204";
    $dbname = "votalik6n1q7_Votality";
    
    logDebug("Attempting database connection");
    
    $conn = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    logDebug("Database connection successful");
    
    // Get user ID (using test ID for development)
    $userId = $_SESSION['user_id'] ?? '17360786907837';
    logDebug("Using user ID: " . $userId);
    
    $query = "SELECT chat_id, topic, created_at, updated_at 
              FROM votality_chats 
              WHERE user_id = :userId 
              ORDER BY updated_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute(['userId' => $userId]);
    $chats = $stmt->fetchAll();
    
    logDebug("Found " . count($chats) . " chats");
    
    // Prepare the response
    $response = [
        'success' => true,
        'chats' => $chats,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
} catch (PDOException $e) {
    logDebug("Database error: " . $e->getMessage());
    $response = [
        'success' => false,
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ];
} catch (Exception $e) {
    logDebug("General error: " . $e->getMessage());
    $response = [
        'success' => false,
        'error' => 'An error occurred',
        'message' => $e->getMessage()
    ];
}

// Clear any output that might have been generated
ob_clean();

// Set headers - must come before any output
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Encode and output the response
echo json_encode($response, JSON_PRETTY_PRINT);

// End the script
exit;