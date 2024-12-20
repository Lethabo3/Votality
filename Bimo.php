<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'stedi_ai_vector_store.php';
require_once 'stedi_ai_service.php';

header('Content-Type: application/json');

$message = $_GET['message'] ?? '';

file_put_contents('debug.log', "Received message: " . $message . "\n", FILE_APPEND);

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'No message provided']);
    exit;
}

try {
    $vectorStore = new StediAIVectorStore();

    $vectorStore->storeMessage($message);

    $relevantContext = $vectorStore->getRelevantContext($message);

    $aiService = new StediAIService();

    $response = $aiService->generateResponse($message, $relevantContext);

    $vectorStore->storeMessage($response, false);

    file_put_contents('debug.log', "Sending AI response: " . $response . "\n", FILE_APPEND);
    
    echo json_encode(['response' => $response]);

} catch (Exception $e) {
    file_put_contents('debug.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}