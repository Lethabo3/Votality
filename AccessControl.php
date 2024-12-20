<?php
class AccessControl {
    private $conn;
    private $freeMsgLimit = 4; // Free user message limit
    private $premiumMsgLimit = 20; // Premium user message limit
    private $freeCooldown = 7200; // 2 hours in seconds for free users
    private $premiumCooldown = 3600; // 1 hour in seconds for premium users

    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    public function canSendMessage($userId) {
        if (!$userId) {
            return ['allowed' => false, 'reason' => 'auth_required'];
        }

        // Check user's subscription status
        $stmt = $this->conn->prepare("SELECT subscription_plan FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $isPremium = $user && ($user['subscription_plan'] === 'premium' || $user['subscription_plan'] === 'teams');
        $messageLimit = $isPremium ? $this->premiumMsgLimit : $this->freeMsgLimit;
        $cooldownPeriod = $isPremium ? $this->premiumCooldown : $this->freeCooldown;

        // Check message count and cooldown
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as message_count,
                MAX(timestamp) as last_message_time
            FROM votality_messages 
            WHERE user_id = ? 
            AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $usage = $result->fetch_assoc();

        $currentTime = time();
        $lastMessageTime = strtotime($usage['last_message_time'] ?? '');
        $timeSinceLastMessage = $currentTime - $lastMessageTime;

        // Check if user has exceeded message limit
        if ($usage['message_count'] >= $messageLimit) {
            // If in cooldown period
            if ($timeSinceLastMessage < $cooldownPeriod) {
                $remainingTime = $cooldownPeriod - $timeSinceLastMessage;
                return [
                    'allowed' => false,
                    'reason' => 'cooldown',
                    'remainingTime' => $remainingTime,
                    'formattedTime' => $this->formatRemainingTime($remainingTime)
                ];
            }
            
            // Reset count after cooldown
            $stmt = $this->conn->prepare("
                DELETE FROM votality_messages 
                WHERE user_id = ? 
                AND timestamp <= DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $cooldownHours = $cooldownPeriod / 3600;
            $stmt->bind_param("si", $userId, $cooldownHours);
            $stmt->execute();
            
            return ['allowed' => true];
        }

        return [
            'allowed' => true,
            'remainingMessages' => $messageLimit - $usage['message_count'],
            'isPremium' => $isPremium
        ];
    }

    private function formatRemainingTime($seconds) {
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        return sprintf("%02d:%02d", $minutes, $seconds);
    }
}