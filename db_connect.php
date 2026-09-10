<?php
$host = "localhost"; // Change if needed
$user = "root"; // Change if you have a different database user
$password = ""; // Change if your MySQL has a password
$database = "sts"; // The database name

$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

