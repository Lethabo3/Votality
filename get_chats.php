<?php
// Start the session and include required files
session_start();
require_once 'UsersBimo.php';

// Set headers for API response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Enhanced debug logging function with file rotation
function debug_log($message, $data = null) {
    $log_file = 'chat_debug.log';
    $max_size = 5 * 1024 * 1024; // 5MB max file size
    
    // Rotate log if it's too large
    if (file_exists($log_file) && filesize($log_file) > $max_size) {
        rename($log_file, $log_file . '.old');
    }
    
    // Format the debug message
    $log_entry = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $log_entry .= ': ' . print_r($data, true);
    }
    $log_entry .= "\n";
    
    // Write to log file
    error_log($log_entry, 3, $log_file);
}

try {
    // Start debugging session information
    debug_log("Session state at start", [
        'session_id' => session_id(),
        'session_status' => session_status(),
        'session_data' => $_SESSION
    ]);

    // Verify database connection
    if (!isset($conn)) {
        debug_log("Database connection not initialized");
        throw new Exception("Database connection not initialized");
    }

    if ($conn->connect_error) {
        debug_log("Database connection error", $conn->connect_error);
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    debug_log("Database connection successful", [
        'server_info' => $conn->server_info,
        'host_info' => $conn->host_info
    ]);

    // Verify user authentication
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        debug_log("Authentication failed - missing session data");
        echo json_encode([
            'error' => 'auth_required',
            'message' => 'Please sign in to view chats'
        ]);
        exit;
    }

    $userId = $_SESSION['user_id'];
    debug_log("User authentication successful", [
        'user_id' => $userId,
        'email' => $_SESSION['email'] ?? 'not_set'
    ]);

    // Verify table existence
    $table_check = $conn->query("SHOW TABLES LIKE 'votality_chats'");
    if ($table_check->num_rows === 0) {
        debug_log("Table 'votality_chats' does not exist");
        throw new Exception("Required table 'votality_chats' not found");
    }

    // Get table structure
    $structure = $conn->query("DESCRIBE votality_chats");
    $columns = [];
    while ($col = $structure->fetch_assoc()) {
        $columns[] = $col;
    }
    debug_log("Table structure", $columns);

    // Get total chat count for user
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) as chat_count 
        FROM votality_chats 
        WHERE user_id = ?
    ");
    
    if (!$count_stmt) {
        debug_log("Count query preparation failed", $conn->error);
        throw new Exception("Failed to prepare count query: " . $conn->error);
    }

    $count_stmt->bind_param("i", $userId);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    debug_log("Total chats count", $count_row['chat_count']);

    // Main query to get chats
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
        debug_log("Main query preparation failed", $conn->error);
        throw new Exception("Failed to prepare main query: " . $conn->error);
    }

    $stmt->bind_param("i", $userId);
    
    if (!$stmt->execute()) {
        debug_log("Main query execution failed", [
            'error' => $stmt->error,
            'errno' => $stmt->errno
        ]);
        throw new Exception("Failed to execute main query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    debug_log("Query executed successfully", [
        'num_rows' => $result->num_rows,
        'field_count' => $result->field_count
    ]);

    $chats = [];
    while ($row = $result->fetch_assoc()) {
        // Log each chat row for debugging
        debug_log("Processing chat row", [
            'chat_id' => $row['chat_id'],
            'topic' => $row['topic'] ?? 'NULL',
            'topic_type' => gettype($row['topic']),
            'topic_length' => $row['topic'] ? strlen($row['topic']) : 0
        ]);

        $chats[] = [
            'chat_id' => $row['chat_id'],
            'topic' => $row['topic'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    // Prepare final response
    $response = [
        'chats' => $chats,
        'email' => $_SESSION['email'],
        'authenticated' => true,
        'debug_info' => [
            'total_chats' => count($chats),
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    debug_log("Sending final response", $response);
    echo json_encode($response);

} catch (Exception $e) {
    debug_log("Error occurred", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo json_encode([
        'error' => 'An error occurred',
        'message' => $e->getMessage(),
        'debug_info' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'session_active' => session_status() === PHP_SESSION_ACTIVE
        ]
    ]);
}