<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN'] ?? '*');

$response = [
    'isLoggedIn' => false,
    'username' => null
];

if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    require_once 'database.php';
    
    // Verify the session is still valid
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $response = [
            'isLoggedIn' => true,
            'username' => $row['username']
        ];
    } else {
        // Invalid session, clear it
        session_destroy();
    }
}

echo json_encode($response);
?>