<?php
header('Content-Type: application/json');
require_once 'UsersBimo.php'; // Include your database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback = $_POST['feedback'] ?? '';
    
    if (!empty($feedback)) {
        $stmt = $conn->prepare("INSERT INTO feedback (message) VALUES (?)");
        $stmt->bind_param("s", $feedback);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error submitting feedback']);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Feedback cannot be empty']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>