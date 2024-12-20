<?php
$servername = "localhost";
$username = "drivefes_Lethabo";
$password = "Lethabo1204";
$dbname = "drivefes_Belgium Campus";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$name = $_POST['name'];
$email = $_POST['email'];

$sql = "INSERT INTO votality_waitlist (name, email) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $name, $email);

if ($stmt->execute()) {
    header("Location: waitlistcomplete.html");
    exit();
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>