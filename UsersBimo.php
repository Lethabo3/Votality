<?php
$servername = "localhost";
$username = "votalik6n1q7_Lethabo";
$password = "Lethabo1204";
$dbname = "votalik6n1q7_Votality";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
