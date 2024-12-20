<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'stedi_ai_config.php';
require_once 'stedi_ai_service.php';
require_once 'stedi_ai_vector_store.php';

$servername = "localhost";
$username = "drivefes_Lethabo";
$password = "Lethabo1204";
$dbname = "drivefes_Belgium_Campus";

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? '';

    if (empty($message)) {
        throw new Exception('No message provided');
    }

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    function storeMessage($conn, $message, $isUser = true) {
        $sender = $isUser ? 'user' : 'ai';
        $stmt = $conn->prepare("INSERT INTO chat_messages (sender, message) VALUES (?, ?)");
        $stmt->bind_param("ss", $sender, $message);
        $result = $stmt->execute();
        $stmt->close();
        if (!$result) {
            throw new Exception("Error storing message: " . $conn->error);
        }
    }

    storeMessage($conn, $message);

    $vectorStore = new StediAIVectorStore();
$vectorStore->storeMessage($message);
$relevantContext = $vectorStore->getRelevantContext($message);

    $aiService = new StediAIService();
    $aiResponse = $aiService->generateResponse($message, $relevantContext);

    storeMessage($conn, $aiResponse, false);

    $conn->close();

    echo json_encode(['response' => $aiResponse]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}