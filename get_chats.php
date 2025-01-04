<?php
// Include only necessary dependencies
require_once 'UsersBimo.php';

// Set basic headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Our enhanced debug logging function
function debug_log($message, $data = null) {
    $log_file = 'chat_debug.log';
    $log_entry = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $log_entry .= ': ' . print_r($data, true);
    }
    $log_entry .= "\n";
    error_log($log_entry, 3, $log_file);
}

try {
    // Verify database connection
    if (!isset($conn)) {
        throw new Exception("Database connection not initialized");
    }

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Query to get recent chats without user filtering
    $stmt = $conn->prepare("
        SELECT 
            c.chat_id,
            c.topic,
            c.created_at,
            c.updated_at,
            u.username,  -- Including username for context
            u.email     -- Including email for context
        FROM votality_chats c
        LEFT JOIN users u ON c.user_id = u.user_id
        ORDER BY c.created_at DESC
        LIMIT 50  -- Limiting to recent chats
    ");

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $chats = [];

    while ($row = $result->fetch_assoc()) {
        $chats[] = [
            'chat_id' => $row['chat_id'],
            'topic' => $row['topic'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'username' => $row['username'],
            'email' => $row['email']
        ];
    }

    // Send response
    echo json_encode([
        'chats' => $chats,
        'total_chats' => count($chats),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    debug_log("Error occurred", $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

// Clean up
if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();