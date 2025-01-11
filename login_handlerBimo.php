<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start a log file
$log_file = 'login_debug.log';
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_message("Login script started");

// Include database connection
log_message("Including UsersBimo.php");
include 'UsersBimo.php';

if (!$conn) {
    log_message("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed");
}

session_start();
log_message("Session started");

// Check if this is a regular form submission or Google Sign-In
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    
    if ($contentType === "application/json") {
        // Handle Google Sign-In
        log_message("Handling Google Sign-In request");
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (isset($data['userData']['email'])) {
            $email = $data['userData']['email'];
            $name = $data['userData']['name'];
            log_message("Processing Google Sign-In for email: $email");
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT user_id, username, subscription_plan FROM users WHERE email = ?");
            if (!$stmt) {
                log_message("Prepare failed: " . $conn->error);
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit();
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Existing user
                log_message("Existing user found for email: $email");
                $user = $result->fetch_assoc();
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $email;
                $_SESSION['subscription_plan'] = $user['subscription_plan'];
                $_SESSION['logged_in'] = true;
                
                // Generate session ID
                $session_id = bin2hex(random_bytes(16));
                $update_stmt = $conn->prepare("UPDATE users SET session_id = ? WHERE user_id = ?");
                $update_stmt->bind_param("ss", $session_id, $user['user_id']);
                $update_stmt->execute();
                $_SESSION['session_id'] = $session_id;
                
                log_message("Session created for existing user: " . $user['username']);
            } else {
                // New user
                log_message("Creating new user for email: $email");
                $username = explode('@', $email)[0];
                $stmt = $conn->prepare("INSERT INTO users (email, username, subscription_plan) VALUES (?, ?, 'free')");
                $stmt->bind_param("ss", $email, $username);
                
                if (!$stmt->execute()) {
                    log_message("Error creating new user: " . $stmt->error);
                    echo json_encode(['success' => false, 'error' => 'Failed to create user']);
                    exit();
                }
                
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['subscription_plan'] = 'free';
                $_SESSION['logged_in'] = true;
                
                // Generate session ID for new user
                $session_id = bin2hex(random_bytes(16));
                $update_stmt = $conn->prepare("UPDATE users SET session_id = ? WHERE user_id = ?");
                $update_stmt->bind_param("ss", $session_id, $_SESSION['user_id']);
                $update_stmt->execute();
                $_SESSION['session_id'] = $session_id;
                
                log_message("New user created and session established for: $username");
            }
            
            echo json_encode(['success' => true, 'redirect' => 'index.html']);
            exit();
            
        } else {
            log_message("Invalid Google Sign-In data received");
            echo json_encode(['success' => false, 'error' => 'Invalid Google Sign-In data']);
            exit();
        }
    } else {
        // Handle regular form login
        log_message("Processing regular form login");
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            log_message("Error: Missing email or password");
            header('Location: signin.html?login=failed');
            exit();
        }
        
        // Update the query to include subscription_plan
        $stmt = $conn->prepare("SELECT user_id, username, password, subscription_plan FROM users WHERE email = ?");
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
        $stmt->bind_result($user_id, $username, $hashed_password, $subscription_plan);
        
        if ($stmt->num_rows > 0) {
            $stmt->fetch();
            if (password_verify($password, $hashed_password)) {
                log_message("Login successful for user: $username");
                
                // Set all required session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['subscription_plan'] = $subscription_plan;
                $_SESSION['logged_in'] = true;
                
                // Generate and store session ID
                $session_id = bin2hex(random_bytes(16));
                $update_stmt = $conn->prepare("UPDATE users SET session_id = ? WHERE user_id = ?");
                $update_stmt->bind_param("ss", $session_id, $user_id);
                $update_stmt->execute();
                $_SESSION['session_id'] = $session_id;
                
                log_message("Session variables set - User ID: $user_id, Session ID: $session_id");
                
                // Check for return URL
                if (isset($_SESSION['returnUrl'])) {
                    $returnUrl = $_SESSION['returnUrl'];
                    unset($_SESSION['returnUrl']);
                    header('Location: ' . $returnUrl);
                } else {
                    header('Location: index.html');
                }
                exit();
            } else {
                log_message("Invalid password for email: $email");
                header('Location: signin.html?login=failed');
                exit();
            }
        } else {
            log_message("No user found with email: $email");
            header('Location: signin.html?login=failed');
            exit();
        }
        $stmt->close();
    }
}
?>