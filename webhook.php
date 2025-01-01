<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("Webhook received at " . date('Y-m-d H:i:s'));

// Your secret key
$secret = "iloveyou";

// Get headers
$githubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
$githubEvent = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';

// Log the event type
error_log("Event Type: " . $githubEvent);

// Get the payload
$payload = file_get_contents('php://input');
error_log("Payload received: " . $payload);

// Verify signature
$calculatedSignature = 'sha1=' . hash_hmac('sha1', $payload, $secret);
if (!hash_equals($githubSignature, $calculatedSignature)) {
    error_log("Signature verification failed");
    http_response_code(401);
    die("Signature verification failed");
}

// Check if it's a push event
if ($githubEvent !== 'push') {
    error_log("Non-push event received: " . $githubEvent);
    die("Only push events are handled");
}

// Execute git pull
$output = shell_exec('cd /home/votalik6n1q7/public_html && git pull origin main 2>&1');
error_log("Git pull output: " . $output);

echo "Deploy script executed\n";
echo "Output: " . $output;
?>