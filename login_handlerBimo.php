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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    log_message("POST request received");
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
            $_SESSION['logged_in'] = true;  // Add this line
            
            // Generate and store session ID
            $session_id = bin2hex(random_bytes(16));
            $update_stmt = $conn->prepare("UPDATE users SET session_id = ? WHERE user_id = ?");
            $update_stmt->bind_param("ss", $session_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();

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
?>