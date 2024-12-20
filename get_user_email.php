<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['email']) && isset($_SESSION['fullname'])) {
    echo json_encode([
        'email' => $_SESSION['email'],
        'fullname' => $_SESSION['fullname']
    ]);
} else {
    echo json_encode(['email' => 'Not logged in', 'fullname' => '']);
}
?>