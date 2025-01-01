<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start a log file
$log_file = 'signup_debug.log';
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_message("Signup script started");

// Include database connection
log_message("Including UsersBimo.php");
include 'UsersBimo.php';

if (!$conn) {
    log_message("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed");
}

session_start();
log_message("Session started");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    log_message("POST request received");
   
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
   
    log_message("Received data - Fullname: $fullname, Email: $email");

    if (empty($fullname) || empty($email) || empty($password)) {
        log_message("Error: Missing required fields");
        header('Location: signup.html?signup=failed&error=missing_fields');
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
   
    log_message("Checking if email already exists");
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    if (!$stmt) {
        log_message("Prepare failed: " . $conn->error);
        die("Prepare failed: " . $conn->error);
    }
   
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        log_message("Execute failed: " . $stmt->error);
        die("Execute failed: " . $stmt->error);
    }
   
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        log_message("Email already exists");
        header('Location: signup.html?signup=failed&error=email_exists');
        exit();
    } else {
        // Generate a unique user_id
        $user_id = time() . rand(1000, 9999);
        $session_id = bin2hex(random_bytes(16)); // Generate a random session ID
       
        log_message("Inserting new user with user_id: $user_id");
        $stmt = $conn->prepare("INSERT INTO users (user_id, username, password, email, created_at, session_id) VALUES (?, ?, ?, ?, NOW(), ?)");
        if (!$stmt) {
            log_message("Prepare failed: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
       
        $stmt->bind_param("sssss", $user_id, $fullname, $hashed_password, $email, $session_id);
        if ($stmt->execute()) {
            log_message("User inserted successfully");
            
            // Set session variables to log the user in
            $_SESSION['user_id'] = $user_id;
            $_SESSION['session_id'] = $session_id;
            $_SESSION['username'] = $fullname;
            $_SESSION['email'] = $email;
            $_SESSION['fullname'] = $fullname;
            $_SESSION['logged_in'] = true; // Set this to indicate the user is logged in

            log_message("Session variables set - User ID: $user_id, Session ID: $session_id");
            
            log_message("Redirecting to index.html");
            header('Location: index.html'); // Redirect to the Votality page
            exit();
        } else {
            log_message("Error inserting user: " . $stmt->error);
            header('Location: signup.html?signup=failed&error=database_error');
            exit();
        }
    }
    $stmt->close();
}

$conn->close();
log_message("Signup script ended");
?>