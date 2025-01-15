<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/votality-error.log');

require_once 'logging.php';
require_once 'UsersBimo.php';
require_once 'votality_ai_service.php';
require_once 'MarketDataService.php';

function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . print_r($message, true) . "\n", 3, '/path/to/votality-debug.log');
}

header('Content-Type: application/json');
session_start();

$response = ['error' => 'Invalid action'];

try {
    global $conn;
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    debug_log("Received action: " . $action);

    switch ($action) {
        case 'sendMessage':
            $response = handleSendMessage($data['message'], $data['chatId'], $data['file'] ?? null, $data['timezone'] ?? 'UTC');
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
        case 'getTopStories':
            $response = getTopStories();
            break;
        case 'getRecentPosts':
            $response = getRecentPosts();
            break;
        case 'getMarketData':
            $response = getMarketData();
            break;
        case 'getMarketDataByCategory':
            $response = getMarketDataByCategory($data['category']);
            break;
        case 'saveSharedMessage':
            $response = saveSharedMessage($data['messageId'], $data['messageContent'], $data['aiResponse']);
            break;
        case 'getSharedMessage':
            $response = getSharedMessage($data['messageId']);
            break;
    }
} catch (Exception $e) {
    debug_log("Error: " . $e->getMessage());
    $response = ['error' => 'An error occurred: ' . $e->getMessage()];
}

debug_log("Final response: " . json_encode($response));
echo json_encode($response);
exit;

function getWorldTime($timezone) {
    $url = "http://worldtimeapi.org/api/timezone/" . urlencode($timezone);
    $response = @file_get_contents($url);
    if ($response === FALSE) {
        logMessage("Error fetching time data for timezone: " . $timezone);
        return null;
    }
    $timeData = json_decode($response, true);
    if ($timeData && isset($timeData['datetime'])) {
        $dateTime = new DateTime($timeData['datetime']);
        return $dateTime->format('l, jS g:ia'); // Format: Wednesday, 19th 12:30pm
    }
    return null;
}

function handleSendMessage($message, $chatId, $file = null, $timezone = 'UTC') {

    $userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    logMessage("No user ID found in session. User might not be logged in.");
    // Consider returning an error or handling this case
}
    logMessage("Handling send message: " . $message . " for chat ID: " . $chatId);

    if (empty($message) && empty($file)) {
        return ['error' => 'No message or file provided'];
    }

    if (!isset($_SESSION['chats'][$chatId])) {
        logMessage("Chat with ID " . $chatId . " does not exist. Creating new chat.");
        $newChatResult = createNewChat();
        if (isset($newChatResult['error'])) {
            return $newChatResult;
        }
        $chatId = $newChatResult['chatId'];
    }

    try {
        $aiService = new VotalityAIService();
        
        $fileData = null;
        $fileType = null;
        if ($file) {
            $fileData = base64_decode(explode(',', $file['data'])[1]);
            $fileType = $file['type'];
        }

        $formattedTime = getWorldTime($timezone);
        if (!$formattedTime) {
            $dateTime = new DateTime('now', new DateTimeZone($timezone));
            $formattedTime = $dateTime->format('l, jS g:ia');
        }

        $userMessage = [
            'sender' => 'user',
            'content' => $message,
            'file' => $file ? ['data' => $fileData, 'type' => $fileType] : null,
            'timestamp' => time()
        ];
        $_SESSION['chats'][$chatId]['messages'][] = $userMessage;

        $userMessageSaved = saveMessageToDatabase($chatId, 'user', $message, $fileData, $fileType);
        if (!$userMessageSaved) {
            logMessage("Failed to save user message to database");
        }

        $aiPrompt = "Current time: {$formattedTime}. User message: {$message}";
        if ($file) {
            $aiPrompt .= " [File attached: " . $file['type'] . "]";
        }
        logMessage("Generating AI response for chat ID: " . $chatId);
        $aiResponse = $aiService->generateResponse($aiPrompt, $chatId);
        logMessage("AI Response generated: " . $aiResponse);

        $aiMessage = [
            'sender' => 'ai',
            'content' => $aiResponse,
            'timestamp' => time()
        ];
        $_SESSION['chats'][$chatId]['messages'][] = $aiMessage;

        $aiMessageSaved = saveMessageToDatabase($chatId, 'ai', $aiResponse);
        if (!$aiMessageSaved) {
            logMessage("Failed to save AI message to database");
        }

        if (count($_SESSION['chats'][$chatId]['messages']) > 3) {
            $_SESSION['chats'][$chatId]['messages'] = array_slice($_SESSION['chats'][$chatId]['messages'], -3);
        }

        updateChatSummary($chatId, $message);

        return ['response' => $aiResponse, 'chatId' => $chatId];
    } catch (Exception $e) {
        logMessage("Error: " . $e->getMessage());
        return ['error' => 'An error occurred: ' . $e->getMessage()];
    }
}

function createNewChat() {
    $userId = $_SESSION['user_id'] ?? null;
    $chatId = uniqid('votality_', true);
    $initialSummary = 'New Votality Chat';
    
    logMessage("Creating new chat. User ID: " . ($userId ?? 'null') . ", Chat ID: " . $chatId);
    
    if ($userId) {
        $result = createNewChatInDatabase($userId, $chatId, $initialSummary);
    } else {
        $result = createNewChatInSession($chatId, $initialSummary);
    }
    
    logMessage("Create new chat result: " . print_r($result, true));
    return $result;
}

if ($userId) {
    $chatBelongsToUser = checkChatBelongsToUser($userId, $chatId);
    if (!$chatBelongsToUser) {
        return ['error' => 'Chat does not belong to this user'];
    }
}

function checkChatBelongsToUser($userId, $chatId) {
    global $conn;
    $stmt = $conn->prepare("SELECT 1 FROM votality_chats WHERE chat_id = ? AND user_id = ?");
    $stmt->bind_param("si", $chatId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function createNewChatInDatabase($userId, $chatId, $initialSummary) {
    global $conn;
    try {
        $stmt = $conn->prepare("INSERT INTO votality_chats (chat_id, user_id, summary, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sis", $chatId, $userId, $initialSummary);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            debug_log("New chat created in database. Chat ID: $chatId");
            return ['chatId' => $chatId, 'summary' => $initialSummary];
        } else {
            throw new Exception("Failed to create new chat in database");
        }
    } catch (Exception $e) {
        debug_log("Error creating new chat in database: " . $e->getMessage());
        return ['error' => 'Database error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function createNewChatInSession($chatId, $initialSummary) {
    $_SESSION['chats'][$chatId] = [
        'summary' => $initialSummary,
        'messages' => [],
        'created_at' => time()
    ];

    return ['chatId' => $chatId, 'summary' => $initialSummary];
}

function getRecentChats() {
    debug_log("getRecentChats called. Session data: " . print_r($_SESSION, true));
    $userId = $_SESSION['user_id'] ?? null;
    debug_log("User ID from session: " . ($userId ?? 'null'));

    if ($userId) {
        $result = getRecentChatsFromDatabase($userId);
    } else {
        $result = getRecentChatsFromSession();
    }

    debug_log("getRecentChats result: " . print_r($result, true));
    return $result;
}

function getRecentChatsFromDatabase($userId) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT chat_id, summary, created_at FROM votality_chats WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $chats = [];
        while ($row = $result->fetch_assoc()) {
            $chats[] = [
                'chat_id' => $row['chat_id'],
                'summary' => $row['summary'],
                'created_at' => $row['created_at']
            ];
        }
        
        debug_log("Chats fetched from database: " . print_r($chats, true));
        return ['chats' => $chats];
    } catch (Exception $e) {
        debug_log("Error fetching recent chats from database: " . $e->getMessage());
        return ['error' => 'Database error: ' . $e->getMessage(), 'chats' => []];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function getRecentChatsFromSession() {
    $chats = [];
    if (isset($_SESSION['chats'])) {
        foreach ($_SESSION['chats'] as $chatId => $chatData) {
            $chats[] = [
                'chat_id' => $chatId,
                'summary' => $chatData['summary'],
                'created_at' => date('Y-m-d H:i:s', $chatData['created_at'])
            ];
        }
    }
    usort($chats, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    debug_log("Chats fetched from session: " . print_r($chats, true));
    return ['chats' => array_slice($chats, 0, 5)];
}

function saveMessageToDatabase($chatId, $sender, $content, $fileData = null, $fileType = null) {
    global $conn;
    try {
        $stmt = $conn->prepare("INSERT INTO votality_messages (chat_id, sender, content, file_data, file_type, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("sssss", $chatId, $sender, $content, $fileData, $fileType);
        $result = $stmt->execute();
        if (!$result) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        return $stmt->affected_rows > 0;
    } catch (Exception $e) {
        logMessage("Error saving message to database: " . $e->getMessage());
        return false;
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}


function getTopStories() {
    try {
        $aiService = new VotalityAIService();
        $allStories = $aiService->fetchTopStories(50);  // Fetch more stories to filter from
        
        // Filter for financial and economic news
        $financialStories = array_filter($allStories, function($story) {
            $financialKeywords = ['finance', 'economic', 'market', 'stock', 'investment', 'trade', 'currency', 'bank', 'fiscal', 'monetary'];
            foreach ($financialKeywords as $keyword) {
                if (stripos($story['title'], $keyword) !== false || stripos($story['summary'], $keyword) !== false) {
                    return true;
                }
            }
            return false;
        });
        
        // Limit to top 20 financial stories
        $financialStories = array_slice($financialStories, 0, 21);
        
        return ['stories' => $financialStories];
    } catch (Exception $e) {
        logMessage("Error fetching top financial stories: " . $e->getMessage());
        return ['error' => 'An error occurred while fetching top financial stories: ' . $e->getMessage()];
    }
}

function getMarketData() {
    try {
        $marketDataService = new MarketDataService();
        $marketData = $marketDataService->fetchMarketDataForWatchlist();
        return ['marketData' => $marketData];
    } catch (Exception $e) {
        logMessage("Error fetching market data: " . $e->getMessage());
        return ['error' => 'An error occurred while fetching market data: ' . $e->getMessage()];
    }
}

function getMarketDataByCategory($category) {
    try {
        $marketDataService = new MarketDataService();
        $marketData = $marketDataService->fetchMarketDataByCategory($category);
        return ['marketData' => $marketData];
    } catch (Exception $e) {
        logMessage("Error fetching market data for category $category: " . $e->getMessage());
        return ['error' => 'An error occurred while fetching market data: ' . $e->getMessage()];
    }
}

function getRecentPosts() {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM posts ORDER BY timestamp DESC LIMIT 10");
        $stmt->execute();
        $result = $stmt->get_result();
        $posts = $result->fetch_all(MYSQLI_ASSOC);
        return ['posts' => $posts];
    } catch (Exception $e) {
        logMessage("Error fetching recent posts: " . $e->getMessage());
        return ['error' => 'An error occurred while fetching recent posts'];
    }
}

function saveSharedMessage($messageId, $messageContent, $aiResponse) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO shared_messages (message_id, message_content, ai_response) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $messageId, $messageContent, $aiResponse);
    
    if ($stmt->execute()) {
        return ['success' => true];
    } else {
        return ['error' => 'Failed to save shared message'];
    }
}

function getSharedMessage($messageId) {
    global $conn;
    $stmt = $conn->prepare("SELECT message_content, ai_response FROM shared_messages WHERE message_id = ?");
    $stmt->bind_param("s", $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return [
            'messageContent' => $row['message_content'],
            'aiResponse' => $row['ai_response']
        ];
    } else {
        return ['error' => 'Shared message not found'];
    }
}

function updateChatSummary($chatId, $message) {
    if (!isset($_SESSION['chats'][$chatId])) {
        return;
    }
    
    $messages = $_SESSION['chats'][$chatId]['messages'];
    $conversationText = implode("\n", array_column($messages, 'content'));
    
    $aiService = new VotalityAIService();
    $summaryPrompt = "Please provide a brief summary (max 20 characters) of the main topic in this conversation:\n\n" . $conversationText;
    $summary = $aiService->generateResponse($summaryPrompt, $chatId);
    
    $summary = substr($summary, 0, 20);
    if (strlen($summary) == 20) {
        $summary = substr($summary, 0, strrpos($summary, ' ')) . '...';
    }
    
    $_SESSION['chats'][$chatId]['summary'] = $summary;
    
    logMessage("Updated summary for chat $chatId: $summary");
}
?>