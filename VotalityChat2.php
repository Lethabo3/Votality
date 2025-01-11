<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/votality-error.log');

debug_log("Session started. Session data: " . print_r($_SESSION, true));

require_once 'logging.php';
require_once 'UsersBimo.php';
require_once 'votality_ai_service2.php';
require_once 'MarketDataService.php';

// Set headers after session_start
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

function debug_log($message) {
    error_log(date('[Y-m-d H:i:s] ') . print_r($message, true) . "\n", 3, '/path/to/votality-debug.log');
}

function checkSession() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
        return false;
    }
    return true;
}

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
            // Get stayLoggedOut flag from request
            $stayLoggedOut = $data['stayLoggedOut'] ?? false;
            
            // Skip authentication check for guest users
            if (!$stayLoggedOut && !checkSession()) {
                $response = [
                    'error' => 'auth_required',
                    'message' => 'Please sign in to continue chatting'
                ];
            } else {
                $response = handleSendMessage(
                    $data['message'], 
                    $data['chatId'], 
                    $data['file'] ?? null, 
                    $data['timezone'] ?? 'UTC'
                );
            }
            break;
            
        case 'createNewChat':
            if (!checkSession()) {
                $response = [
                    'error' => 'auth_required',
                    'message' => 'Please sign in to continue'
                ];
            } else {
                $response = createNewChat();
            }
            break;
            
        case 'getRecentChats':
            if (!checkSession()) {
                $response = [
                    'error' => 'auth_required',
                    'message' => 'Please sign in to view chats'
                ];
            } else {
                $response = getRecentChats();
            }
            break;
            
        case 'loadChat':
            if (!checkSession()) {
                $response = [
                    'error' => 'auth_required',
                    'message' => 'Please sign in to load chat'
                ];
            } else {
                $response = loadChat($data['chatId']);
            }
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
            
        case 'saveSharedContent':
            if (!checkSession()) {
                $response = [
                    'error' => 'auth_required',
                    'message' => 'Please sign in to share content'
                ];
            } else {
                $response = saveSharedContent($data['id'], $data['content'], $data['topic']);
            }
            break;
            
        case 'getSharedContent':
            $response = getSharedContent($data['id']);
            break;
            
        case 'checkMessageLimits':
            if (!checkSession()) {
                $response = [
                    'error' => 'auth_required',
                    'message' => 'Please sign in to check limits'
                ];
            } else {
                $response = checkMessageLimits($_SESSION['user_id']);
            }
            break;
    }
} catch (Exception $e) {
    debug_log("Error: " . $e->getMessage());
    $response = ['error' => 'An error occurred: ' . $e->getMessage()];
}

// Add a new function to check message limits
function checkMessageLimits($userId) {  
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                message_count,
                last_message_time,
                cooldown_until
            FROM user_message_limits 
            WHERE user_id = ?
        ");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $limits = $result->fetch_assoc();

        if (!$limits) {
            return [
                'isLocked' => false,
                'remainingMessages' => 4
            ];
        }

        $currentTime = new DateTime();
        $cooldownTime = $limits['cooldown_until'] ? new DateTime($limits['cooldown_until']) : null;

        if ($cooldownTime && $currentTime < $cooldownTime) {
            return [
                'isLocked' => true,
                'cooldownTime' => $cooldownTime->getTimestamp() - $currentTime->getTimestamp()
            ];
        }

        if ($cooldownTime && $currentTime > $cooldownTime) {
            // Reset message count if cooldown has passed
            $stmt = $conn->prepare("
                UPDATE user_message_limits 
                SET message_count = 0, cooldown_until = NULL 
                WHERE user_id = ?
            ");
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            return [
                'isLocked' => false,
                'remainingMessages' => 4
            ];
        }

        return [
            'isLocked' => false,
            'remainingMessages' => 4 - $limits['message_count']
        ];
    } catch (Exception $e) {
        logMessage("Error checking message limits: " . $e->getMessage());
        return ['error' => 'Failed to check message limits'];
    }
}

debug_log("Final response: " . json_encode($response));
echo json_encode($response);
exit;

// Helper function to handle the recent chats request
function handleGetRecentChats() {
    try {
        // Get the user ID from the session
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            error_log("No user ID found in session");
            return [
                'success' => false,
                'error' => 'No authenticated user found'
            ];
        }

        // Get the chats from the database
        $result = getRecentChatsFromDatabase($userId);
        
        // Log the result for debugging
        error_log("handleGetRecentChats result: " . json_encode($result));
        
        return $result;

    } catch (Exception $e) {
        error_log("Error in handleGetRecentChats: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to process recent chats request',
            'details' => $e->getMessage()
        ];
    }
}

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
    global $conn;
   
    // Validate input
    if (empty($message) && empty($file)) {
        return ['error' => 'No message or file provided'];
    }

    try {
        $userId = $_SESSION['user_id'] ?? null;
        $sessionId = $_SESSION['session_id'] ?? null;
        $isLoggedIn = ($userId && $sessionId);

        // Begin transaction for logged-in users
        if ($isLoggedIn) {
            $conn->begin_transaction();
        }

        // Handle chat creation
        if (!$chatId) {
            $chatId = uniqid('chat_', true);
            
            if ($isLoggedIn) {
                $chatTopic = generateChatTopic($message);
                
                $stmt = $conn->prepare("
                    INSERT INTO votality_chats 
                        (chat_id, user_id, topic, summary) 
                    VALUES (?, ?, ?, ?)
                ");
                $summary = null;
                $stmt->bind_param("siss", $chatId, $userId, $chatTopic, $summary);
                $stmt->execute();

                if ($stmt->error) {
                    throw new Exception("Failed to create new chat: " . $stmt->error);
                }
            }
        }

        // Verify chat access
        if ($isLoggedIn && $chatId) {
            $checkStmt = $conn->prepare("
                SELECT 1 FROM votality_chats 
                WHERE chat_id = ? AND user_id = ?
            ");
            $checkStmt->bind_param("si", $chatId, $userId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) {
                throw new Exception("Chat not found or access denied");
            }
        }

        // Process file if present
        $fileData = null;
        $fileType = null;
        if ($file) {
            // Validate file type
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain', 'text/csv',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ];

            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception("File type not allowed");
            }

            $fileData = base64_decode(preg_replace('#^data:.*?;base64,#', '', $file['data']));
            $fileType = $file['type'];
        }

        // Generate AI response
        $aiService = new VotalityAIService();
        $formattedTime = getWorldTime($timezone) ?? 
            (new DateTime('now', new DateTimeZone($timezone)))->format('l, jS g:ia');
        
        $aiPrompt = "Current time: {$formattedTime}. User message: {$message}";
        if ($file) {
            $aiPrompt .= " [File attached: " . $file['type'] . "]";
        }
        
        $fullResponse = $aiService->generateResponse($aiPrompt, $chatId);

        // Process response and extract topics
        $parts = explode("\nRelated Topics:", $fullResponse, 2);
        $aiResponse = trim($parts[0]);
        $relatedTopics = [];

        if (isset($parts[1])) {
            $topicsText = trim($parts[1]);
            $topicsLines = preg_split('/\r\n|\r|\n/', $topicsText);
            
            foreach ($topicsLines as $line) {
                $line = trim($line);
                if (preg_match('/^(\d+[\.\)]|\*|\-)\s*(.+)$/', $line, $matches)) {
                    $topic = trim($matches[2]);
                    if (!empty($topic) && strlen($topic) > 3) {
                        $relatedTopics[] = $topic;
                    }
                }
            }
        }

        // Store messages for logged-in users
        if ($isLoggedIn) {
            // Store user message with file
            $stmt = $conn->prepare("
                INSERT INTO votality_messages 
                    (chat_id, user_id, session_id, sender, content, file_data, file_type) 
                VALUES (?, ?, ?, 'user', ?, ?, ?)
            ");
            
            $stmt->bind_param("sisssb", $chatId, $userId, $sessionId, $message, $fileData, $fileType);
            if (!$stmt->execute()) {
                throw new Exception("Failed to save user message: " . $stmt->error);
            }

            // Store AI response
            $stmt = $conn->prepare("
                INSERT INTO votality_messages 
                    (chat_id, user_id, session_id, sender, content) 
                VALUES (?, ?, ?, 'ai', ?)
            ");
            $stmt->bind_param("siss", $chatId, $userId, $sessionId, $aiResponse);
            if (!$stmt->execute()) {
                throw new Exception("Failed to save AI response: " . $stmt->error);
            }

            // Update chat topic
            $updateTopicStmt = $conn->prepare("
                UPDATE votality_chats 
                SET topic = COALESCE(topic, ?) 
                WHERE chat_id = ? AND (topic IS NULL OR topic = '')
            ");
            $chatTopic = generateChatTopic($message);
            $updateTopicStmt->bind_param("ss", $chatTopic, $chatId);
            $updateTopicStmt->execute();
        }

        // Maintain session state
        if (!isset($_SESSION['chats'][$chatId])) {
            $_SESSION['chats'][$chatId] = [
                'messages' => [],
                'created_at' => time()
            ];
        }

        $_SESSION['chats'][$chatId]['messages'][] = [
            'sender' => 'user',
            'content' => $message,
            'timestamp' => time(),
            'file_type' => $fileType
        ];

        $_SESSION['chats'][$chatId]['messages'][] = [
            'sender' => 'ai',
            'content' => $aiResponse,
            'timestamp' => time(),
            'relatedTopics' => $relatedTopics
        ];

        // Commit transaction for logged-in users
        if ($isLoggedIn) {
            $conn->commit();
        }

        return [
            'response' => $aiResponse,
            'chatId' => $chatId,
            'relatedTopics' => $relatedTopics,
            'chatTopic' => $_SESSION['chats'][$chatId]['topic'] ?? null
        ];

    } catch (Exception $e) {
        if ($isLoggedIn && $conn->inTransaction()) {
            $conn->rollback();
        }
        logMessage("Error in handleSendMessage: " . $e->getMessage());
        return ['error' => $e->getMessage()];
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

function checkChatBelongsToUser($userId, $chatId) {
    global $conn;
    $stmt = $conn->prepare("SELECT 1 FROM votality_chats WHERE chat_id = ? AND user_id = ?");
    $stmt->bind_param("si", $chatId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function createNewChatInDatabase($userId, $chatId, $initialTopic) {
    global $conn;
    try {
        // First, ensure we have a valid topic
        if (empty($initialTopic)) {
            $initialTopic = 'New Chat'; // Default topic since it's required
        }
        
        $stmt = $conn->prepare("
            INSERT INTO votality_chats (
                chat_id, 
                user_id, 
                topic,
                summary
            ) VALUES (?, ?, ?, ?)
        ");
        
        $summary = null; // Optional field
        $stmt->bind_param("siss", $chatId, $userId, $initialTopic, $summary);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create chat: " . $stmt->error);
        }
        
        return [
            'chatId' => $chatId,
            'topic' => $initialTopic
        ];
    } catch (Exception $e) {
        logMessage("Error creating new chat: " . $e->getMessage());
        throw $e;
    }
}

function createNewChatInSession($chatId, $initialSummary) {
    $_SESSION['chats'][$chatId] = [
        'summary' => $initialSummary,
        'messages' => [],
        'created_at' => time(),
        'topic' => null // Initialize topic as null
    ];

    return ['chatId' => $chatId, 'summary' => $initialSummary];
}

function updateChatTopic($chatId, $message) {
    $aiService = new VotalityAIService();
    $topicPrompt = "Based on this user message, generate a concise chat topic (max 5 words) that captures the main subject:\n\n" . $message;
    $topic = $aiService->generateResponse($topicPrompt, $chatId);
    
    $topic = substr(trim($topic), 0, 50); // Ensure it's not too long
    
    $_SESSION['chats'][$chatId]['topic'] = $topic;
    
    logMessage("Updated topic for chat $chatId: $topic");
}

function generateChatTopic($message) {
    $aiService = new VotalityAIService();
    $topicPrompt = "Generate a very brief topic (3-5 words max) that captures the essence of this message: " . $message;
    $topic = $aiService->generateResponse($topicPrompt, 'topic_generation');
    
    // Clean up the topic
    $topic = preg_replace('/^(Topic:|Subject:|Re:|\s)+/i', '', $topic);
    $topic = trim($topic);
    
    // Limit length and add ellipsis if needed
    if (strlen($topic) > 40) {
        $topic = substr($topic, 0, 37) . '...';
    }
    
    return $topic;
}

// Updated function to save chat topic in database
function updateChatTopicInDatabase($chatId, $topic) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            INSERT INTO votality_chats (chat_id, topic, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                topic = VALUES(topic),
                updated_at = NOW()
        ");
        
        $stmt->bind_param("ss", $chatId, $topic);
        $stmt->execute();
        
        if ($stmt->error) {
            logMessage("Error updating chat topic: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        logMessage("Error updating chat topic in database: " . $e->getMessage());
    }
}

function saveSharedContent($id, $content, $topic) {
    global $conn;
    
    $id = mysqli_real_escape_string($conn, $id);
    $content = mysqli_real_escape_string($conn, $content);
    $topic = mysqli_real_escape_string($conn, $topic);
    
    $stmt = $conn->prepare("INSERT INTO shared_content (id, content, topic) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $id, $content, $topic);
    
    if ($stmt->execute()) {
        return ['success' => true];
    } else {
        return ['error' => 'Error saving shared content: ' . $stmt->error];
    }
}

function getSharedContent($id) {
    global $conn;
    
    $id = mysqli_real_escape_string($conn, $id);
    
    $stmt = $conn->prepare("SELECT content, topic FROM shared_content WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return [
            'content' => $row['content'],
            'topic' => $row['topic']
        ];
    } else {
        return ['error' => 'Shared content not found'];
    }
}

function getRecentChats() {
    debug_log("getRecentChats called. Session data: " . print_r($_SESSION, true));
    
    $userId = $_SESSION['user_id'] ?? null;
    debug_log("User ID from session: " . ($userId ?? 'null'));

    if (!$userId) {
        debug_log("No user ID found in session");
        return [
            'success' => false,
            'error' => 'Not authenticated',
            'debug_info' => [
                'session_active' => session_status() === PHP_SESSION_ACTIVE,
                'session_data' => $_SESSION
            ]
        ];
    }

    try {
        global $conn;
        
        // Verify database connection
        if (!$conn) {
            debug_log("Database connection failed");
            throw new Exception("Database connection not available");
        }

        $stmt = $conn->prepare("
            SELECT c.chat_id, c.topic, c.created_at
            FROM votality_chats c
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
            LIMIT 10
        ");
        
        if (!$stmt) {
            debug_log("Failed to prepare statement: " . $conn->error);
            throw new Exception("Failed to prepare database statement");
        }

        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            debug_log("Failed to execute statement: " . $stmt->error);
            throw new Exception("Failed to execute database query");
        }

        $result = $stmt->get_result();
        $chats = [];

        while ($row = $result->fetch_assoc()) {
            $chats[] = [
                'chat_id' => $row['chat_id'],
                'topic' => $row['topic'] ?? 'New Chat',
                'created_at' => $row['created_at']
            ];
        }

        debug_log("Found " . count($chats) . " chats for user $userId");
        debug_log("Chats data: " . print_r($chats, true));

        return [
            'success' => true,
            'chats' => $chats,
            'debug_info' => [
                'user_id' => $userId,
                'chat_count' => count($chats)
            ]
        ];

    } catch (Exception $e) {
        debug_log("Error in getRecentChats: " . $e->getMessage());
        debug_log("Stack trace: " . $e->getTraceAsString());
        
        return [
            'success' => false,
            'error' => 'Failed to fetch recent chats',
            'debug_info' => [
                'error_message' => $e->getMessage(),
                'user_id' => $userId
            ]
        ];
    }
}

function getRecentChatsFromDatabase($userId) {
    global $conn;
    try {
        // Simple query to get user's recent chats
        $stmt = $conn->prepare("
            SELECT c.chat_id, c.topic, c.created_at
            FROM votality_chats c
            JOIN users u ON c.user_id = u.id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
            LIMIT 10
        ");
        
        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $chats = [];

        while ($row = $result->fetch_assoc()) {
            $chats[] = [
                'chat_id' => $row['chat_id'],
                'topic' => $row['topic'] ?? 'New Chat',
                'created_at' => $row['created_at']
            ];
        }

        return [
            'success' => true,
            'chats' => $chats
        ];

    } catch (Exception $e) {
        error_log("Error fetching recent chats: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to fetch recent chats'
        ];
    }
}

function saveMessageToDatabase($chatId, $sender, $content, $fileData = null, $fileType = null) {
    global $conn;
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $sessionId = $_SESSION['session_id'] ?? null;

        if (!$userId || !$sessionId) {
            throw new Exception("Invalid session data");
        }

        // Start transaction to ensure data consistency
        $conn->begin_transaction();

        // First verify the chat exists and belongs to the user
        $checkStmt = $conn->prepare("
            SELECT 1 FROM votality_chats 
            WHERE chat_id = ? AND user_id = ?
        ");
        $checkStmt->bind_param("si", $chatId, $userId);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows === 0) {
            throw new Exception("Chat not found or access denied");
        }

        // Then insert the message
        $stmt = $conn->prepare("
            INSERT INTO votality_messages 
            (chat_id, user_id, session_id, sender, content, file_data, file_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("sisssss", 
            $chatId, 
            $userId, 
            $sessionId, 
            $sender, 
            $content, 
            $fileData, 
            $fileType
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to save message");
        }

        // Commit transaction
        $conn->commit();
        return true;

    } catch (Exception $e) {
        $conn->rollback();
        logMessage("Error saving message: " . $e->getMessage());
        throw $e;
    }
}

function getTopStories() {
    try {
        $aiService = new VotalityAIService();
        $stories = $aiService->fetchTopStories(20);  // Fetch top 20 stories
        return ['stories' => $stories];
    } catch (Exception $e) {
        logMessage("Error fetching top stories: " . $e->getMessage());
        return ['error' => 'An error occurred while fetching top stories: ' . $e->getMessage()];
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

// Function to get just the active topics for the nav bar
function getActiveTopics($userId, $daysActive = 7) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            SELECT DISTINCT
                c.chat_id,
                c.topic,
                c.updated_at
            FROM votality_chats c
            WHERE c.user_id = ?
                AND c.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY c.updated_at DESC
            LIMIT 5
        ");
        
        $stmt->bind_param("ii", $userId, $daysActive);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $topics = [];
        while ($row = $result->fetch_assoc()) {
            $topics[] = [
                'id' => $row['chat_id'],
                'topic' => $row['topic'],
                'lastActive' => $row['updated_at']
            ];
        }
        
        return [
            'success' => true,
            'topics' => $topics
        ];
        
    } catch (Exception $e) {
        logMessage("Error fetching active topics: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to fetch active topics'
        ];
    }
}


// Helper function to format chat preview text
function formatChatPreview($message, $maxLength = 100) {
    if (empty($message)) {
        return 'No messages yet';
    }
    
    $message = strip_tags($message);
    if (mb_strlen($message) <= $maxLength) {
        return $message;
    }
    
    return mb_substr($message, 0, $maxLength - 3) . '...';
}

function loadChat($chatId) {
    global $conn;
    try {
        // Verify chat exists and user has access
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            return [
                'success' => false,
                'error' => 'Not authenticated'
            ];
        }

        $stmt = $conn->prepare("
            SELECT 
                m.content,
                m.sender,
                m.timestamp,
                c.topic
            FROM votality_messages m
            JOIN votality_chats c ON m.chat_id = c.chat_id
            WHERE m.chat_id = ? AND c.user_id = ?
            ORDER BY m.timestamp ASC
        ");
        
        $stmt->bind_param("si", $chatId, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to load chat messages");
        }

        $result = $stmt->get_result();
        $messages = [];

        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'content' => $row['content'],
                'sender' => $row['sender'],
                'timestamp' => $row['timestamp']
            ];
        }

        return [
            'success' => true,
            'messages' => $messages,
            'topic' => $result->fetch_assoc()['topic'] ?? 'Chat'
        ];

    } catch (Exception $e) {
        error_log("Error loading chat: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to load chat messages'
        ];
    }
}

function processUploadedFile($file) {
    $uploadDir = 'uploads/';
    $fileName = uniqid() . '_' . basename($file['name']);
    $filePath = $uploadDir . $fileName;
    
    move_uploaded_file($file['tmp_name'], $filePath);
    
    return [
        'name' => $fileName,
        'type' => $file['type'],
        'path' => $filePath
    ];
}

function storeFileReferences($chatId, $files) {
    global $conn;
    
    foreach ($files as $file) {
        $stmt = $conn->prepare("
            INSERT INTO message_attachments 
                (chat_id, file_name, file_type, file_path) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssss", $chatId, $file['name'], $file['type'], $file['path']);
        $stmt->execute();
    }
}
?>