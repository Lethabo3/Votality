<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "localhost";
$username = "votalik6n1q7_Lethabo";
$password = "Lethabo1204";
$dbname = "votalik6n1q7_Votality";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $title = $_POST['title'] ?? '';
    $symbol = $_POST['symbol'] ?? '';
    $category = $_POST['category'] ?? '';
    $content = $_POST['content'] ?? '';
    
    // Get the user's ID from the session
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        error_log("User not logged in");
        die("Error: User not logged in");
    }
    
    // Get the user's username from the database
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Error: " . $conn->error);
    }
    
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $author = $row['username'];
    } else {
        error_log("User not found: " . $user_id);
        die("Error: User not found");
    }
    
    $stmt->close();
    
    // Insert the post into the database
    $stmt = $conn->prepare("INSERT INTO posts (title, symbol, category, content, author, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Error: " . $conn->error);
    }
    
    $stmt->bind_param("ssssss", $title, $symbol, $category, $content, $author, $user_id);
    
    if ($stmt->execute()) {
        // Redirect back to the posts page
        header("Location: postv2.html");
        exit();
    } else {
        error_log("Error inserting post: " . $stmt->error);
        die("Error: " . $stmt->error);
    }
    
    $stmt->close();
}

$conn->close();
?>