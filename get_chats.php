<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Database connection details
$servername = "localhost";
$username = "votalik6n1q7_Lethabo";
$password = "Lethabo1204";
$dbname = "votalik6n1q7_Votality";

try {
    // Create database connection
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Log connection success
    error_log("Database connection established");
    
    // Get user ID from session (for testing, you can hardcode a user ID)
    $userId = $_SESSION['user_id'] ?? '17360786907837'; // Replace with your test user ID
    
    // Prepare and execute query to get chat topics
    $query = "
        SELECT 
            chat_id,
            topic,
            created_at,
            updated_at
        FROM votality_chats 
        WHERE user_id = :userId 
        ORDER BY updated_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute(['userId' => $userId]);
    
    // Fetch all chats
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log the number of chats found
    error_log("Found " . count($chats) . " chats for user $userId");
    
    // If we found chats, log some details
    if (!empty($chats)) {
        error_log("First chat topic: " . ($chats[0]['topic'] ?? 'No topic'));
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'chats' => $chats,
        'userId' => $userId,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $response = [
        'success' => false,
        'error' => 'Database error occurred',
        'details' => $e->getMessage()
    ];
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    $response = [
        'success' => false,
        'error' => 'An error occurred',
        'details' => $e->getMessage()
    ];
}

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Send response
echo json_encode($response, JSON_PRETTY_PRINT);