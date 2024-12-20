<?php
session_start();
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['stayLoggedOut']) && $data['stayLoggedOut'] === true) {
    $_SESSION['stayLoggedOut'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>