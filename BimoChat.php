<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/php-error.log'); 

require_once 'logging.php';
require_once 'UsersBimo.php';
require_once 'stedi_ai_service.php';

header('Content-Type: application/json');
session_start();

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

logMessage("Received action: " . $action);

switch ($action) {
    case 'sendMessage':
        $response = handleSendMessage($data['message'], $data['chatId']);
        break;
    case 'createNewChat':
        $response = createNewChat();
        break;
    case 'getRecentChats':
        $response = getRecentChats();
        break;
    case 'loadChat':
        $response = loadChat($data['chatId']);
        break;
    default:
        $response = ['error' => 'Invalid action'];
}

echo json_encode($response);

function handleSendMessage($message, $chatId) {
    global $conn;
    logMessage("Handling send message: " . $message);

    if (empty($message)) {
        return ['error' => 'No message provided'];
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return ['error' => 'User not logged in'];
    }

    try {
        $aiService = new StediAIService();
        
        logMessage("Generating AI response");
        $aiResponse = $aiService->generateResponse($message, $chatId);
        
        logMessage("AI Response generated: " . $aiResponse);

        $stmt = $conn->prepare("INSERT INTO messages (chat_id, sender, content) VALUES (?, 'user', ?)");
        $stmt->bind_param("is", $chatId, $message);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO messages (chat_id, sender, content) VALUES (?, 'ai', ?)");
        $stmt->bind_param("is", $chatId, $aiResponse);
        $stmt->execute();

        updateChatSummary($chatId, $message);

        return ['response' => $aiResponse];
    } catch (Exception $e) {
        logMessage("Error: " . $e->getMessage());
        return ['error' => 'An error occurred: ' . $e->getMessage()];
    }
}

function createNewChat() {
    global $conn;
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return ['error' => 'User not logged in'];
    }

    $initialSummary = 'New Chat';
    $stmt = $conn->prepare("INSERT INTO chats (user_id, summary) VALUES (?, ?)");
    $stmt->bind_param("is", $userId, $initialSummary);
    $stmt->execute();
    $chatId = $conn->insert_id;

    return ['chatId' => $chatId, 'summary' => $initialSummary];
}

function getRecentChats() {
    global $conn;
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return ['error' => 'User not logged in'];
    }

    $stmt = $conn->prepare("SELECT id, summary FROM chats WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $chats = $result->fetch_all(MYSQLI_ASSOC);

    return ['chats' => $chats];
}

function loadChat($chatId) {
    global $conn;
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return ['error' => 'User not logged in'];
    }

    $stmt = $conn->prepare("SELECT sender, content FROM messages WHERE chat_id = ? ORDER BY timestamp ASC");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);

    return ['messages' => $messages];
}

function updateChatSummary($chatId, $message) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as message_count FROM messages WHERE chat_id = ?");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messageCount = $result->fetch_assoc()['message_count'];

    if ($messageCount == 1) {
        $summary = substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '');
        $stmt = $conn->prepare("UPDATE chats SET summary = ? WHERE id = ?");
        $stmt->bind_param("si", $summary, $chatId);
        $stmt->execute();
    }
}