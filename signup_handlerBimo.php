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
include 'UsersBimo.php';

if (!$conn) {
    log_message("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed");
}

session_start();
log_message("Session started");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    
    if ($contentType === "application/json") {
        // Handle Google Sign-In
        log_message("Handling Google Sign-In signup");
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (isset($data['userData']['email'])) {
            $email = $data['userData']['email'];
            $fullname = $data['userData']['name'];
            log_message("Processing Google Sign-In signup for email: $email");
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT user_id, username, subscription_plan FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // User exists - Send them to login
                log_message("User already exists with email: $email");
                $user = $result->fetch_assoc();
                
                // Set session for existing user
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $email;
                $_SESSION['subscription_plan'] = $user['subscription_plan'];
                $_SESSION['logged_in'] = true;
                
                // Generate new session ID
                $session_id = bin2hex(random_bytes(16));
                $update_stmt = $conn->prepare("UPDATE users SET session_id = ? WHERE user_id = ?");
                $update_stmt->bind_param("ss", $session_id, $user['user_id']);
                $update_stmt->execute();
                $_SESSION['session_id'] = $session_id;
                
                echo json_encode(['success' => true, 'redirect' => 'index.html']);
                exit();
            }
            
            // Generate IDs for new user
            $user_id = time() . rand(1000, 9999);
            $session_id = bin2hex(random_bytes(16));
            $subscription_plan = 'free'; // Default plan for new users
            
            // Create new user
            $stmt = $conn->prepare("INSERT INTO users (user_id, username, email, created_at, session_id, is_google_user, subscription_plan) VALUES (?, ?, ?, NOW(), ?, 1, ?)");
            if (!$stmt) {
                log_message("Prepare failed: " . $conn->error);
                echo json_encode(['success' => false, 'error' => 'database_error']);
                exit();
            }
            
            $stmt->bind_param("sssss", $user_id, $fullname, $email, $session_id, $subscription_plan);
            
            if ($stmt->execute()) {
                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['session_id'] = $session_id;
                $_SESSION['username'] = $fullname;
                $_SESSION['email'] = $email;
                $_SESSION['subscription_plan'] = $subscription_plan;
                $_SESSION['logged_in'] = true;
                
                log_message("Google user created successfully: $fullname");
                echo json_encode(['success' => true, 'redirect' => 'index.html']);
                exit();
            } else {
                log_message("Error creating Google user: " . $stmt->error);
                echo json_encode(['success' => false, 'error' => 'database_error']);
                exit();
            }
        } else {
            log_message("Invalid Google Sign-In data");
            echo json_encode(['success' => false, 'error' => 'invalid_data']);
            exit();
        }
    } else {
        // Handle regular form signup
        log_message("Processing regular form signup");
        $fullname = $_POST['fullname'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        log_message("Received data - Fullname: $fullname, Email: $email");
        
        if (empty($fullname) || empty($email) || empty($password)) {
            log_message("Error: Missing required fields");
            header('Location: signup.html?signup=failed&error=missing_fields');
            exit();
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Check if email exists
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
        }
        
        // Generate IDs for new user
        $user_id = time() . rand(1000, 9999);
        $session_id = bin2hex(random_bytes(16));
        $subscription_plan = 'free'; // Default plan for new users
        
        log_message("Inserting new user with user_id: $user_id");
        $stmt = $conn->prepare("INSERT INTO users (user_id, username, password, email, created_at, session_id, subscription_plan) VALUES (?, ?, ?, ?, NOW(), ?, ?)");
        if (!$stmt) {
            log_message("Prepare failed: " . $conn->error);
            die("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssss", $user_id, $fullname, $hashed_password, $email, $session_id, $subscription_plan);
        
        if ($stmt->execute()) {
            log_message("User inserted successfully");
            
            // Set session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['session_id'] = $session_id;
            $_SESSION['username'] = $fullname;
            $_SESSION['email'] = $email;
            $_SESSION['subscription_plan'] = $subscription_plan;
            $_SESSION['logged_in'] = true;
            
            log_message("Session variables set - User ID: $user_id, Session ID: $session_id");
            log_message("Redirecting to index.html");
            header('Location: index.html');
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