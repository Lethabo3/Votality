<?php
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

// Custom error handler
function votalityErrorHandler($errno, $errstr, $errfile, $errline) {
    $message = date('[Y-m-d H:i:s] ') . "Error ($errno): $errstr in $errfile on line $errline\n";
    error_log($message, 3, '/path/to/votality-error.log');
    
    // Don't execute PHP's internal error handler
    return true;
}

// Set the custom error handler
set_error_handler("votalityErrorHandler");

// Custom exception handler
function votalityExceptionHandler($exception) {
    $message = date('[Y-m-d H:i:s] ') . 
        "Uncaught Exception: " . $exception->getMessage() . "\n" .
        "Stack trace: " . $exception->getTraceAsString() . "\n";
    error_log($message, 3, '/path/to/votality-error.log');
}

// Set the custom exception handler
set_exception_handler("votalityExceptionHandler");

function debug_log($message) {
    $formatted_message = date('[Y-m-d H:i:s] ') . print_r($message, true) . "\n";
    error_log($formatted_message, 3, '/path/to/votality-debug.log');
}

debug_log("Processing file with name: " . $fileName);

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
    
    try {
        error_log("Starting handleSendMessage");
        error_log("Message: " . $message);
        error_log("File data received: " . ($file ? json_encode($file) : 'No file'));

        // Input validation
        if (empty($message) && empty($file)) {
            throw new Exception('No message or file provided');
        }

        // Initialize chat if needed
        if (!$chatId) {
            $chatId = uniqid('chat_', true);
        }

        // Get user info
        $userId = $_SESSION['user_id'] ?? null;
        $sessionId = $_SESSION['session_id'] ?? null;

        // Process file if present
        $fileContent = null;
        $fileType = null;
        $fileName = null;
        $fileSize = null;

        if ($file) {
            try {
                error_log("Processing file...");
                
                // Extract file metadata
                $fileData = $file['data'];
                $fileType = $file['type'];
                $fileName = $file['name'];
                $fileSize = $file['size'];

                // Validate file size
                $maxSize = 10 * 1024 * 1024; // 10MB
                if ($fileSize > $maxSize) {
                    throw new Exception("File size exceeds limit of 10MB");
                }

                // Process base64 data
                if (preg_match('/^data:([^;]+);base64,/', $fileData, $matches)) {
                    $fileType = $matches[1];
                    $fileData = substr($fileData, strpos($fileData, ',') + 1);
                }

                $fileContent = base64_decode($fileData);
                if ($fileContent === false) {
                    throw new Exception("Failed to decode file data");
                }

                error_log("File processed successfully. Type: $fileType, Name: $fileName");
            } catch (Exception $e) {
                error_log("File processing error: " . $e->getMessage());
                throw new Exception("File processing failed: " . $e->getMessage());
            }
        }

        // Create AI Service instance
        $aiService = new VotalityAIService();

        // Prepare AI prompt
        $aiPrompt = "Current time: " . getWorldTime($timezone) . "\nUser message: $message";
        
        if ($fileContent) {
            $aiPrompt .= "\n\nFile Information:";
            $aiPrompt .= "\nFilename: " . $fileName;
            $aiPrompt .= "\nFile type: " . $fileType;
            $aiPrompt .= "\nFile size: " . $fileSize . " bytes";
            $aiPrompt .= "\n\nFile Content:\n";
            
            // Process file content based on type
            $processedContent = processFileContent($fileContent, $fileType);
            $aiPrompt .= $processedContent;
        }

        // Get AI response
        error_log("Sending to AI service. Prompt length: " . strlen($aiPrompt));
        $fullResponse = $aiService->generateResponse($aiPrompt, $chatId);

        // Process AI response
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

        // Store in database
        if ($userId && $sessionId) {
            // Ensure chat exists
            $stmt = $conn->prepare("INSERT IGNORE INTO votality_chats (chat_id, user_id) VALUES (?, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare chat insert: " . $conn->error);
            }
            $stmt->bind_param("ss", $chatId, $userId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to create chat: " . $stmt->error);
            }

            // Store user message
            $stmt = $conn->prepare("
                INSERT INTO votality_messages 
                (chat_id, user_id, session_id, sender, content, file_content, file_type, file_name, file_size) 
                VALUES (?, ?, ?, 'user', ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                throw new Exception("Failed to prepare message insert: " . $conn->error);
            }
            
            $stmt->bind_param("sssssssi", 
                $chatId, 
                $userId, 
                $sessionId, 
                $message,
                $fileContent,
                $fileType,
                $fileName,
                $fileSize
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to store user message: " . $stmt->error);
            }

            // Store AI response
            $stmt = $conn->prepare("
                INSERT INTO votality_messages 
                (chat_id, user_id, session_id, sender, content) 
                VALUES (?, ?, ?, 'ai', ?)
            ");
            if (!$stmt) {
                throw new Exception("Failed to prepare AI response insert: " . $conn->error);
            }
            
            $stmt->bind_param("ssss", $chatId, $userId, $sessionId, $aiResponse);
            if (!$stmt->execute()) {
                throw new Exception("Failed to store AI response: " . $stmt->error);
            }
        }

        // Prepare response
        $response = [
            'response' => $aiResponse,
            'chatId' => $chatId,
            'relatedTopics' => $relatedTopics,
            'chatTopic' => generateChatTopic($message),
            'fileProcessed' => $fileContent ? [
                'name' => $fileName,
                'type' => $fileType,
                'size' => $fileSize
            ] : null
        ];

        error_log("Sending response: " . json_encode($response));
        return $response;

    } catch (Exception $e) {
        error_log("Error in handleSendMessage: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return ['error' => $e->getMessage()];
    }
}

function processFileContent($content, $type) {
    try {
        // Handle different file types appropriately
        switch ($type) {
            case 'text/csv':
                return processCsvContent($content);
            case 'application/json':
                return processJsonContent($content);
            case 'text/plain':
                return $content;
            case 'image/png':
            case 'image/jpeg':
            case 'image/gif':
                return "[Image data available for analysis]";
            default:
                return "File content of type $type";
        }
    } catch (Exception $e) {
        error_log("Error processing file content: " . $e->getMessage());
        return "Error processing file: " . $e->getMessage();
    }
}

function processCsvContent($content) {
    $lines = explode("\n", $content);
    if (empty($lines)) {
        return "Empty CSV file";
    }
    
    $headers = str_getcsv(array_shift($lines));
    $data = [];
    
    foreach ($lines as $line) {
        if (trim($line)) {
            $row = str_getcsv($line);
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }
    }
    
    return json_encode($data, JSON_PRETTY_PRINT);
}

function processJsonContent($content) {
    $data = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($data, JSON_PRETTY_PRINT);
    } else {
        throw new Exception("Invalid JSON content");
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

    global $conn;
    try {
        // Verify chats exist for this user
        $checkQuery = "SELECT COUNT(*) as chat_count FROM votality_chats WHERE user_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['chat_count'];
        
        debug_log("Number of chats found in database for user $userId: $count");

        if ($userId) {
            $result = getRecentChatsFromDatabase($userId);
        } else {
            $result = getRecentChatsFromSession();
        }

        debug_log("Final result being returned: " . print_r($result, true));
        return $result;
    } catch (Exception $e) {
        debug_log("Error in getRecentChats: " . $e->getMessage());
        return ['error' => 'Failed to fetch recent chats', 'chats' => []];
    }
}

function getRecentChatsFromDatabase($userId) {
    global $conn;
    try {
        $query = "
            SELECT 
                c.chat_id,
                c.created_at,
                (SELECT m.content 
                 FROM votality_messages m 
                 WHERE m.chat_id = c.chat_id 
                 AND m.sender = 'user'
                 ORDER BY m.timestamp ASC 
                 LIMIT 1) as first_message
            FROM votality_chats c
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
            LIMIT 10
        ";
        
        debug_log("Executing query for user $userId: $query");

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            debug_log("Prepare failed: " . $conn->error);
            return ['error' => 'Database error', 'chats' => []];
        }

        $stmt->bind_param("i", $userId);
        
        if (!$stmt->execute()) {
            debug_log("Execute failed: " . $stmt->error);
            return ['error' => 'Database error', 'chats' => []];
        }

        $result = $stmt->get_result();
        $chats = [];
        
        while ($row = $result->fetch_assoc()) {
            // Generate a topic from the first user message
            $firstMessage = $row['first_message'] ?? '';
            $topic = generateTopicFromMessage($firstMessage);
            
            $chats[] = [
                'chat_id' => $row['chat_id'],
                'topic' => $topic,
                'created_at' => $row['created_at']
            ];
        }

        debug_log("Retrieved chats: " . print_r($chats, true));
        
        return ['chats' => $chats];
    } catch (Exception $e) {
        debug_log("Database error in getRecentChatsFromDatabase: " . $e->getMessage());
        return ['error' => 'Database error', 'chats' => []];
    }
}

// Helper function to generate concise topics
function generateTopicFromMessage($message) {
    if (empty($message)) {
        return 'New Chat';
    }

    // Remove punctuation and extra spaces
    $message = preg_replace('/[^\w\s]/', ' ', $message);
    $message = trim(preg_replace('/\s+/', ' ', $message));

    // Get first few significant words (3-5 words)
    $words = explode(' ', $message);
    $words = array_slice($words, 0, 5);
    $topic = implode(' ', $words);

    // If topic is too long, trim it
    if (strlen($topic) > 40) {
        $topic = substr($topic, 0, 37) . '...';
    }

    return $topic;
}

function getRecentChatsFromSession() {
    $chats = [];
    if (isset($_SESSION['chats'])) {
        foreach ($_SESSION['chats'] as $chatId => $chatData) {
            $messages = $chatData['messages'] ?? [];
            $lastMessage = !empty($messages) ? end($messages)['content'] : null;
            
            $chats[] = [
                'chat_id' => $chatId,
                'topic' => $chatData['topic'] ?? 'New Chat',
                'last_message' => $lastMessage,
                'created_at' => date('Y-m-d H:i:s', $chatData['created_at'])
            ];
        }
    }
    
    usort($chats, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return ['chats' => array_slice($chats, 0, 10)];
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