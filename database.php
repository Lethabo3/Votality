<?php
class Database {
    private $host = "localhost";
    private $username = "votalik6n1q7_Lethabo";
    private $password = "Lethabo1204";
    private $database = "votalik6n1q7_Votality";
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
            
        } catch(Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
        
        return $this->conn;
    }
}

// Create connection instance
$database = new Database();
$conn = $database->getConnection();