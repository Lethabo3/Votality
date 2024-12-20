<?php
class Auth {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getUserEmail() {
        return $_SESSION['email'] ?? null;
    }
    
    public function getUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    user_id,
                    username,
                    email,
                    subscription_plan,
                    subscription_status,
                    subscription_expiry
                FROM users 
                WHERE user_id = ?
            ");
            
            $userId = $this->getCurrentUserId();
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Auth error: " . $e->getMessage());
            return null;
        }
    }
}