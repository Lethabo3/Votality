<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "votalik6n1q7_Lethabo";
$password = "Lethabo1204";
$dbname = "votalik6n1q7_Votality";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

$stmt = $conn->prepare("SELECT title, symbol, category, content, author, timestamp FROM posts ORDER BY timestamp DESC LIMIT 20");
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
}

echo json_encode($posts);

$stmt->close();
$conn->close();
?>