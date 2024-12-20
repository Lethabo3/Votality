<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$servername = "localhost";
$username = "drivefes_Lethabo";
$password = "Lethabo1204";
$dbname = "drivefes_Belgium Campus";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $message = $input['message'] ?? '';

    if (empty($email) || empty($message)) {
        throw new Exception('Email and message are required');
    }

    // Prepare and execute the SQL statement
    $stmt = $conn->prepare("INSERT INTO contact_messages (email, message) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $message);
    $result = $stmt->execute();

    if (!$result) {
        throw new Exception("Error storing message: " . $conn->error);
    }

    $stmt->close();

    // Send email to admin
    $admin_email = "lethabosekoto@drivestedi.com";
    $admin_subject = "New Contact Form Submission";
    $admin_message = "A new contact form submission has been received:\n\n";
    $admin_message .= "From: " . $email . "\n\n";
    $admin_message .= "Message:\n" . $message;
    $admin_headers = "From: support@drivestedi.com";

    if (!mail($admin_email, $admin_subject, $admin_message, $admin_headers)) {
        throw new Exception("Failed to send admin notification email");
    }

    // Send confirmation email to user
    $user_subject = "Thank you for contacting Lethabo Sekoto";
    $user_message = "Hello,\n\nThank you for reaching out. Your message has been received, and Lethabo will get back to you soon.\n\nBest regards,\nLethabo Sekoto";
    $user_headers = "From: lethabosekoto@drivestedi.com";

    if (!mail($email, $user_subject, $user_message, $user_headers)) {
        throw new Exception("Failed to send user confirmation email");
    }

    $conn->close();

    echo json_encode(['success' => true, 'message' => 'Your message has been sent successfully.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>