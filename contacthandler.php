<?php
include 'Users.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $message = $_POST['message'];
    
    // Insert the contact form data into the database
    $stmt = $conn->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $message);
    
    if ($stmt->execute()) {
        // Send email notification to admin
        $admin_email = "lethabosekoto@drivestedi.com";
        $admin_subject = "New Contact Form Submission";
        $admin_message = "Name: " . $name . "\nEmail: " . $email . "\nMessage: " . $message;
        $admin_headers = "From: support@drivestedi.com";
        mail($admin_email, $admin_subject, $admin_message, $admin_headers);
        
        // Redirect to the message received page on success
        header("Location: message_sent.html");
        exit();
    } else {
        // Redirect back to the contact page with an error parameter
        header("Location: contact.html?status=error");
        exit();
    }
    $stmt->close();
}
?>