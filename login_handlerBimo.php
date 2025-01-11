// config.php
<?php
define('GOOGLE_CLIENT_ID', '583018952126-agtpcn1qils8bi84eu1frmri66l0tq9p.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET'); // Replace with your client secret
define('GOOGLE_REDIRECT_URI', 'https://your-domain.com/google_callback.php'); // Update with your domain

// google_auth.php
<?php
require_once 'config.php';
require_once 'UsersBimo.php';
session_start();

// Initialize Google Client
function getGoogleClient() {
    $client = new Google_Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);
    $client->addScope('email');
    $client->addScope('profile');
    return $client;
}

// google_callback.php
<?php
require_once 'config.php';
require_once 'UsersBimo.php';
require_once 'vendor/autoload.php';

session_start();

try {
    $client = getGoogleClient();
    
    if (isset($_GET['code'])) {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);
        
        // Get user info
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        $email = $google_account_info->email;
        $name = $google_account_info->name;
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT user_id, username, subscription_plan FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // User exists - log them in
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
            
        } else {
            // New user - create account
            $username = explode('@', $email)[0]; // Basic username from email
            $stmt = $conn->prepare("INSERT INTO users (email, username, subscription_plan) VALUES (?, ?, 'free')");
            $stmt->bind_param("ss", $email, $username);
            $stmt->execute();
            
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
        }
        
        // Redirect to home page or return URL
        if (isset($_SESSION['returnUrl'])) {
            $returnUrl = $_SESSION['returnUrl'];
            unset($_SESSION['returnUrl']);
            header('Location: ' . $returnUrl);
        } else {
            header('Location: index.html');
        }
        exit();
        
    } else {
        header('Location: signin.html?error=google_auth_failed');
        exit();
    }
    
} catch (Exception $e) {
    error_log('Google Auth Error: ' . $e->getMessage());
    header('Location: signin.html?error=google_auth_failed');
    exit();
}