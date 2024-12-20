<?php
$requestUri = $_SERVER['REQUEST_URI'];
$parts = explode('/', trim($requestUri, '/'));
if (count($parts) >= 2 && $parts[0] === 'shared') {
    $messageId = $parts[1];
   
    // Redirect to shared_message.html with the shared message ID as a query parameter
    header("Location: /shared_message.html?id=" . urlencode($messageId));
    exit;
} else {
    // Redirect to home page if the URL format is incorrect
    header("Location: /Votality.html");
    exit;
}
?>