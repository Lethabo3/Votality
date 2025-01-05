`<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/votality-error.log');

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
   
    if (empty($message) && empty($file)) {
        return ['error' => 'No message or file provided'];
    }

    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        // Create new chat if needed
        if (!$chatId) {
            $chatId = uniqid('chat_', true);
            $chatTopic = generateChatTopic($message);
            
            // Create new chat entry with topic using our new schema
            $stmt = $conn->prepare("
                INSERT INTO votality_chats (
                    chat_id, 
                    user_id, 
                    topic, 
                    created_at, 
                    updated_at
                ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            
            $stmt->bind_param("sis", $chatId, $userId, $chatTopic);
            if (!$stmt->execute()) {
                throw new Exception("Failed to create new chat: " . $stmt->error);
            }
        }

        $aiService = new VotalityAIService();
       
        // Format time (keeping the existing time formatting)
        $formattedTime = getWorldTime($timezone);
        if (!$formattedTime) {
            $dateTime = new DateTime('now', new DateTimeZone($timezone));
            $formattedTime = $dateTime->format('l, jS g:ia');
        }

        // Generate AI response with the same prompt structure
        $aiPrompt = "Current time: {$formattedTime}. User message: {$message}";
        if ($file) {
            $aiPrompt .= " [File attached: " . $file['type'] . "]";
        }
       
        $fullResponse = $aiService->generateResponse($aiPrompt, $chatId);

        // Process response and extract topics (keeping the existing logic)
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

        // Store messages in database with our new schema
        if ($userId) {
            // Store user message
            $stmt = $conn->prepare("
                INSERT INTO votality_messages (
                    chat_id, 
                    user_id, 
                    sender, 
                    content, 
                    created_at
                ) VALUES (?, ?, 'user', ?, CURRENT_TIMESTAMP)
            ");
            
            if (!$stmt->bind_param("sis", $chatId, $userId, $message) || !$stmt->execute()) {
                throw new Exception("Failed to store user message: " . $stmt->error);
            }

            // Store AI response
            $stmt = $conn->prepare("
                INSERT INTO votality_messages (
                    chat_id, 
                    user_id, 
                    sender, 
                    content, 
                    created_at
                ) VALUES (?, ?, 'ai', ?, CURRENT_TIMESTAMP)
            ");
            
            if (!$stmt->bind_param("sis", $chatId, $userId, $aiResponse) || !$stmt->execute()) {
                throw new Exception("Failed to store AI response: " . $stmt->error);
            }

            // Update chat's updated_at timestamp and topic if needed
            $stmt = $conn->prepare("
                UPDATE votality_chats 
                SET updated_at = CURRENT_TIMESTAMP,
                    topic = COALESCE(topic, ?)
                WHERE chat_id = ?
            ");
            
            if (!$stmt->bind_param("ss", $chatTopic, $chatId) || !$stmt->execute()) {
                throw new Exception("Failed to update chat metadata: " . $stmt->error);
            }
        }

        // Keep session storage for compatibility
        if (!isset($_SESSION['chats'][$chatId])) {
            $_SESSION['chats'][$chatId] = [
                'messages' => [],
                'created_at' => time()
            ];
        }

        $_SESSION['chats'][$chatId]['messages'][] = [
            'sender' => 'user',
            'content' => $message,
            'timestamp' => time()
        ];

        $_SESSION['chats'][$chatId]['messages'][] = [
            'sender' => 'ai',
            'content' => $aiResponse,
            'timestamp' => time(),
            'relatedTopics' => $relatedTopics
        ];

        debug_log("Message handled successfully", [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'has_file' => !empty($file)
        ]);

        return [
            'response' => $aiResponse,
            'chatId' => $chatId,
            'relatedTopics' => $relatedTopics,
            'chatTopic' => $chatTopic ?? null
        ];

    } catch (Exception $e) {
        debug_log("Error in handleSendMessage", [
            'error' => $e->getMessage(),
            'chat_id' => $chatId,
            'user_id' => $userId ?? null
        ]);
        return ['error' => $e->getMessage()];
    }
}

function createNewChat() {
    global $conn;
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        return ['error' => 'User not authenticated'];
    }

    try {
        // Generate a unique chat ID with proper prefix
        $chatId = 'chat_' . uniqid();
        
        $stmt = $conn->prepare("
            INSERT INTO votality_chats (
                chat_id,
                user_id,
                topic,
                created_at,
                updated_at
            ) VALUES (?, ?, 'New Chat', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        $stmt->bind_param("si", $chatId, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create chat: " . $stmt->error);
        }

        debug_log("Created new chat", [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);

        return [
            'chatId' => $chatId,
            'topic' => 'New Chat',
            'created_at' => date('Y-m-d H:i:s')
        ];

    } catch (Exception $e) {
        debug_log("Error in createNewChat", [
            'error' => $e->getMessage(),
            'user_id' => $userId
        ]);
        return ['error' => 'Failed to create chat', 'message' => $e->getMessage()];
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
        'created_at' => time(),
        'topic' => null // Initialize topic as null
    ];

    return ['chatId' => $chatId, 'summary' => $initialSummary];
}

function updateChatTopic($chatId, $topic) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            UPDATE votality_chats 
            SET topic = ?, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE chat_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        $stmt->bind_param("ss", $topic, $chatId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update topic: " . $stmt->error);
        }

        debug_log("Updated chat topic", [
            'chat_id' => $chatId,
            'topic' => $topic
        ]);

        return [
            'success' => true,
            'topic' => $topic
        ];

    } catch (Exception $e) {
        debug_log("Error in updateChatTopic", [
            'error' => $e->getMessage(),
            'chat_id' => $chatId
        ]);
        return ['error' => 'Failed to update topic', 'message' => $e->getMessage()];
    }
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
    global $conn;
    $userId = $_SESSION['user_id'] ?? null;

    try {
        // Enhanced query that properly uses our new schema
        $stmt = $conn->prepare("
            SELECT 
                c.chat_id,
                c.topic,
                c.created_at,
                c.updated_at,
                u.username,
                u.email
            FROM votality_chats c
            INNER JOIN users u ON c.user_id = u.user_id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
            LIMIT 10
        ");

        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

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
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'username' => $row['username']
            ];
        }

        debug_log("Retrieved chats for user $userId", [
            'chat_count' => count($chats)
        ]);

        return [
            'chats' => $chats,
            'total' => count($chats)
        ];

    } catch (Exception $e) {
        debug_log("Error in getRecentChats", [
            'error' => $e->getMessage(),
            'user_id' => $userId
        ]);
        return ['error' => 'Database error', 'message' => $e->getMessage()];
    }
}

function getRecentChatsFromDatabase($userId) {
    global $conn;
    try {
        $stmt = $conn->prepare("
            SELECT chat_id, topic, created_at
            FROM votality_chats 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 6
        ");
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $chats = [];
        while ($row = $result->fetch_assoc()) {
            $chats[] = [
                'chat_id' => $row['chat_id'],
                'topic' => $row['topic'] ?? 'New Chat'
            ];
        }
        
        return ['chats' => $chats];
    } catch (Exception $e) {
        return ['error' => 'Database error', 'chats' => []];
    }
}

function saveMessageToDatabase($chatId, $sender, $content, $fileData = null, $fileType = null) {
    global $conn;
    try {
        // Get the user_id and session_id from the current session
        $userId = $_SESSION['user_id'] ?? null;
        $sessionId = $_SESSION['session_id'] ?? null;

        if (!$userId || !$sessionId) {
            throw new Exception("Invalid session data");
        }

        $stmt = $conn->prepare("
            INSERT INTO votality_messages 
            (chat_id, user_id, session_id, sender, content, file_data, file_type, timestamp) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("sssssss", 
            $chatId, 
            $userId, 
            $sessionId, 
            $sender, 
            $content, 
            $fileData, 
            $fileType
        );

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

?>