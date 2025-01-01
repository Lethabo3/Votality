<?php
// Log all webhook requests
error_log("Webhook received at " . date('Y-m-d H:i:s'));

// Your secret key - make this something strong and random
$secret = "iloveyou";

// Get GitHub's signature
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

// Get the payload
$payload = file_get_contents('php://input');

// Verify the signature
$expected = 'sha1=' . hash_hmac('sha1', $payload, $secret);
if (!hash_equals($expected, $signature)) {
    error_log("Invalid signature received");
    die("Invalid signature");
}

// Verify it's a push event
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
if ($event !== 'push') {
    error_log("Non-push event received: $event");
    die("Only push events are handled");
}

// Execute the deployment script
$output = shell_exec('sh /home/votalik6n1q7/deploy.sh 2>&1');

// Log the result
error_log("Deployment output: " . $output);

// Return success response
echo "Deployment initiated successfully";
?>