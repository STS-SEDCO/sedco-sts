<?php
include 'config.php';

$name = "Test User";
$email = "test@example.com";
$password = password_hash("123456", PASSWORD_DEFAULT); // Hash password
$role = "staff";

$stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $email, $password, $role);

if ($stmt->execute()) {
    echo "User registered successfully!";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
