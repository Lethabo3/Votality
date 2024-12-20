<?php
// Start output buffering
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/php-error.log');

require_once 'logging.php';
require_once 'stedi_ai_service.php';

// Set up error handling to catch all errors
set_error_handler("errorHandler");
set_exception_handler("exceptionHandler");

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    logMessage("Received action: " . $action);

    switch ($action) {
        case 'getAIResponse':
            $response = handleGetAIResponse($data['message'] ?? '');
            break;
        default:
            $response = ['error' => 'Invalid action'];
    }
} catch (Exception $e) {
    $response = ['error' => 'An unexpected error occurred: ' . $e->getMessage()];
    logMessage("Unexpected error: " . $e->getMessage());
}

// Capture any output
$output = ob_get_clean();

// If there was any output, include it in the response
if (!empty($output)) {
    $response['debug_output'] = $output;
}

echo json_encode($response);

function handleGetAIResponse($message) {
    if (empty($message)) {
        return ['error' => 'No message provided'];
    }

    try {
        $aiService = new StediAIService();
        logMessage("Generating AI response for message: " . $message);

        // Generate a unique chat ID for this conversation
        $chatId = uniqid('portfolio_');

        $aiResponse = $aiService->generateResponse($message, $chatId);
        logMessage("AI Response generated: " . $aiResponse);
        return ['response' => $aiResponse];
    } catch (Exception $e) {
        logMessage("Error in handleGetAIResponse: " . $e->getMessage());
        return ['error' => 'An error occurred while generating the response: ' . $e->getMessage()];
    }
}

function errorHandler($errno, $errstr, $errfile, $errline) {
    $error = [
        'error' => 'PHP Error',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ];
    logMessage("PHP Error: " . json_encode($error));
    echo json_encode($error);
    exit;
}

function exceptionHandler($exception) {
    $error = [
        'error' => 'Uncaught Exception',
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ];
    logMessage("Uncaught Exception: " . json_encode($error));
    echo json_encode($error);
    exit;
}