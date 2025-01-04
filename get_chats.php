<?php
// Initialize session and include core dependencies
session_start();
require_once 'UsersBimo.php';

// Configure response headers for security and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));

// Handle CORS preflight requests for browser security
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// Enhanced logging function with file rotation and structured output
function debug_log($message, $data = null) {
    $log_file = 'chat_debug.log';
    $max_size = 5 * 1024 * 1024; // 5MB size limit
    
    // Implement log rotation to manage file size
    if (file_exists($log_file) && filesize($log_file) > $max_size) {
        rename($log_file, $log_file . '.' . date('Y-m-d-H-i-s'));
    }
    
    // Create structured log entry
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data,
        'request_id' => $_SERVER['REQUEST_TIME_FLOAT'] ?? '',
        'user_id' => $_SESSION['user_id'] ?? 'not_set'
    ];
    
    // Write formatted log entry
    error_log(
        json_encode($log_entry, JSON_PRETTY_PRINT) . "\n",
        3,
        $log_file
    );
}

// Wrapper function for standardized JSON responses
function send_response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Error handler function for consistent error responses
function handle_error($message, $code = 500, $additional_info = null) {
    debug_log('Error occurred', [
        'message' => $message,
        'code' => $code,
        'additional_info' => $additional_info
    ]);
    
    send_response([
        'error' => true,
        'message' => $message,
        'code' => $code,
        'debug_info' => $additional_info
    ], $code);
}

try {
    // Log initial request information
    debug_log('New request initiated', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'session_id' => session_id(),
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);

    // Verify database connection
    if (!isset($conn)) {
        throw new Exception("Database connection not initialized");
    }

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    debug_log('Database connection verified', [
        'server_info' => $conn->server_info,
        'host_info' => $conn->host_info
    ]);

    // Authentication check
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        send_response([
            'error' => 'auth_required',
            'message' => 'Please sign in to view chats',
            'authenticated' => false
        ], 401);
    }

    $userId = $_SESSION['user_id'];
    
    // Verify user exists in database
    $user_check = $conn->prepare("
        SELECT user_id, email 
        FROM users 
        WHERE user_id = ?
    ");
    
    if (!$user_check) {
        throw new Exception("Failed to prepare user verification query");
    }

    $user_check->bind_param("i", $userId);
    $user_check->execute();
    $user_result = $user_check->get_result();

    if ($user_result->num_rows === 0) {
        throw new Exception("User not found in database");
    }

    $user_data = $user_result->fetch_assoc();
    debug_log('User verified', [
        'user_id' => $userId,
        'email' => $user_data['email']
    ]);

    // Get total chat count for pagination planning
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) as chat_count 
        FROM votality_chats 
        WHERE user_id = ?
    ");
    
    if (!$count_stmt) {
        throw new Exception("Failed to prepare count query: " . $conn->error);
    }

    $count_stmt->bind_param("i", $userId);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_data = $count_result->fetch_assoc();
    
    debug_log('Chat count retrieved', [
        'total_chats' => $count_data['chat_count']
    ]);

    // Main query to fetch chat data
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
        throw new Exception("Failed to prepare main query: " . $conn->error);
    }

    $stmt->bind_param("i", $userId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute main query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $chats = [];

    while ($row = $result->fetch_assoc()) {
        // Validate and sanitize each chat entry
        $chat_entry = [
            'chat_id' => htmlspecialchars($row['chat_id']),
            'topic' => $row['topic'] ? htmlspecialchars($row['topic']) : null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];

        debug_log('Processing chat row', [
            'chat_id' => $row['chat_id'],
            'topic_length' => $row['topic'] ? strlen($row['topic']) : 0
        ]);

        $chats[] = $chat_entry;
    }

    // Prepare and send the final response
    $response = [
        'chats' => $chats,
        'email' => $user_data['email'],
        'authenticated' => true,
        'debug_info' => [
            'total_chats' => count($chats),
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    debug_log('Sending successful response', [
        'chat_count' => count($chats)
    ]);

    send_response($response);

} catch (Exception $e) {
    handle_error(
        $e->getMessage(),
        500,
        [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    );
}

// Ensure database resources are properly closed
finally {
    if (isset($stmt)) $stmt->close();
    if (isset($count_stmt)) $count_stmt->close();
    if (isset($user_check)) $user_check->close();
    if (isset($conn)) $conn->close();
}
?>