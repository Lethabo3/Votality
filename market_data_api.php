<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'MarketDataService.php';
require_once 'logging.php';
header('Content-Type: application/json');
function handleRequest() {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse JSON input: " . json_last_error_msg() . ". Input: " . $input);
        }
        $action = $data['action'] ?? '';
        $category = $data['category'] ?? '';
        if ($action !== 'getMarketDataByCategory' || $category !== 'stocks') {
            throw new Exception("Invalid action or category. Received: action=$action, category=$category");
        }
        $marketDataService = new MarketDataService();
        $marketData = $marketDataService->fetchMarketDataByCategory('stocks');
        return ['marketData' => $marketData];
    } catch (Exception $e) {
        $errorMessage = "Error in handleRequest: " . $e->getMessage() . "\n" . $e->getTraceAsString();
        logMessage($errorMessage);
        return ['error' => $errorMessage];
    }
}
try {
    $response = handleRequest();
    echo json_encode($response);
} catch (Exception $e) {
    $errorMessage = "Uncaught exception: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    logMessage($errorMessage);
    echo json_encode(['error' => $errorMessage]);
}

