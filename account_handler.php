<?php
session_start();
header('Content-Type: application/json');

require_once 'database.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_account_info':
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT email, subscription_plan 
            FROM users 
            WHERE user_id = ?
        ");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        echo json_encode([
            'email' => $user['email'],
            'plan' => $user['subscription_plan'] ?? 'free',
            'planDisplay' => ucfirst($user['subscription_plan'] ?? 'free') . ' Plan'
        ]);
        break;

    case 'logout':
        // Clear all session data
        $_SESSION = array();

        // Destroy the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destroy the session
        session_destroy();

        echo json_encode([
            'success' => true,
            'redirect' => 'signin.html'
        ]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}