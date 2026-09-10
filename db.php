<?php
$servername = "localhost";  // Change if using a different server
$username = "root";         // Default XAMPP username
$password = "";             // Default XAMPP password (empty)
$database = "sts"; // Ensure this matches your database name

// Create connection
$conn = mysqli_connect($servername, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
