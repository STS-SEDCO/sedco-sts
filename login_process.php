<?php
session_start();
include "config.php"; // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format!";
        header("Location: login.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $name, $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($_POST['password'], $hashed_password)) {
            session_regenerate_id(true); // Security measure

            $_SESSION['user_id'] = $id;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $role;

            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid password!";
        }
    } else {
        $_SESSION['error'] = "User not found!";
    }

    $stmt->close();
}

header("Location: login.php");
exit();
?>
