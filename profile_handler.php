<?php
session_start();
require_once 'UsersBimo.php';

function getUserData() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT fullname, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $user['initials'] = strtoupper(substr($user['fullname'], 0, 2));
    }

    return $user;
}

function handleLogout() {
    session_destroy();
    header("Location: LoginPageBimo.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'logout') {
        handleLogout();
    }
}

$userData = getUserData();
if (!$userData) {
    header("Location: LoginPageBimo.html");
    exit();
}

header('Content-Type: application/json');
echo json_encode($userData);