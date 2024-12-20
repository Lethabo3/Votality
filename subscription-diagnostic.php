<?php
require_once 'database.php';
require_once 'lemon-squeezy-config.php';

class SubscriptionDiagnostic {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function checkAndRepairSubscription($email) {
        try {
            // First, get the user details
            $stmt = $this->conn->prepare("
                SELECT 
                    user_id,
                    username,
                    subscription_plan,
                    subscription_status,
                    subscription_expiry
                FROM users 
                WHERE email = ?
            ");
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'User not found'
                ];
            }
            
            // Update to premium status
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET 
                    subscription_plan = 'premium',
                    subscription_status = 'active',
                    subscription_expiry = DATE_ADD(NOW(), INTERVAL 1 MONTH)
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("s", $user['user_id']);
            $success = $stmt->execute();
            
            if ($success) {
                return [
                    'status' => 'success',
                    'message' => 'Subscription updated successfully',
                    'user' => [
                        'user_id' => $user['user_id'],
                        'username' => $user['username'],
                        'new_status' => 'premium'
                    ]
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to update subscription'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
}

// Usage endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    
    if (empty($email)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Email is required'
        ]);
        exit;
    }
    
    $diagnostic = new SubscriptionDiagnostic($conn);
    $result = $diagnostic->checkAndRepairSubscription($email);
    
    echo json_encode($result);
}
?><?php
require_once 'database.php';
require_once 'lemon-squeezy-config.php';

class SubscriptionDiagnostic {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function checkAndRepairSubscription($email) {
        try {
            // First, get the user details
            $stmt = $this->conn->prepare("
                SELECT 
                    user_id,
                    username,
                    subscription_plan,
                    subscription_status,
                    subscription_expiry
                FROM users 
                WHERE email = ?
            ");
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (!$user) {
                return [
                    'status' => 'error',
                    'message' => 'User not found'
                ];
            }
            
            // Update to premium status
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET 
                    subscription_plan = 'premium',
                    subscription_status = 'active',
                    subscription_expiry = DATE_ADD(NOW(), INTERVAL 1 MONTH)
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("s", $user['user_id']);
            $success = $stmt->execute();
            
            if ($success) {
                return [
                    'status' => 'success',
                    'message' => 'Subscription updated successfully',
                    'user' => [
                        'user_id' => $user['user_id'],
                        'username' => $user['username'],
                        'new_status' => 'premium'
                    ]
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to update subscription'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
}

// Usage endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    
    if (empty($email)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Email is required'
        ]);
        exit;
    }
    
    $diagnostic = new SubscriptionDiagnostic($conn);
    $result = $diagnostic->checkAndRepairSubscription($email);
    
    echo json_encode($result);
}
?>