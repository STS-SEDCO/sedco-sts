<?php
session_start();
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, fullname, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password'])) {
            // Start session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['fullname'] = $row['fullname'];
            $_SESSION['role'] = $row['role'];

            

            echo "<script>alert('Login Successful! Redirecting to dashboard...'); window.location='dashboard.php';</script>";
        } else {
            echo "<script>alert('Invalid password!'); window.location='login.php';</script>";
        }
    } else {
        echo "<script>alert('User not found! Please register first.'); window.location='register.html';</script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart College Login</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: url('http://localhost/STS/v2/assets/img/sedco.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Source Sans Pro', sans-serif;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: rgba(255, 255, 255, 0.9);
        }
        .btn-primary {
            background-color: #B30E02;
            border: none;
            border-radius: 25px;
        }
        .btn-primary:hover {
            background-color: #B30E02;
        }
        .password-container {
            position: relative;
        }
        .password-container .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
</head>
<body>
    <section class="vh-100">
        <div class="container h-100">
            <div class="row d-flex justify-content-center align-items-center h-100">
                <div class="col-lg-12 col-xl-10">
                    <div class="card text-black">
                        <div class="card-body p-md-5">
                            <div class="row justify-content-center">
                                <div class="col-md-10 col-lg-7 col-xl-6 order-2 order-lg-1">
                                    <p class="text-center h1 fw-bold mb-5">TRAINING MANAGEMENT SYSTEM</p>
                                    
                                    <?php if (isset($_SESSION['error'])): ?>
                                        <div class="alert alert-danger"> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?> </div>
                                    <?php endif; ?>

                                    <form action="login_process.php" method="post">
                                        <div class="form-outline mb-4">
                                            <input type="text" name="email" class="form-control form-control-sm bg-light" placeholder="Email address" required value="<?php echo isset($_COOKIE['email']) ? $_COOKIE['email'] : ''; ?>">
                                        </div>

                                        <div class="form-outline mb-4 password-container">
                                            <input type="password" id="password" name="password" class="form-control form-control-sm bg-light" placeholder="Password" required>
                                            <i class="fa fa-eye toggle-password" onclick="togglePassword()"></i>
                                        </div>

                                        <div class="form-check mb-4">
                                            <input class="form-check-input" type="checkbox" name="remember" id="remember" <?php if(isset($_COOKIE['email'])) echo "checked"; ?>>
                                            <label class="form-check-label" for="remember">Remember Me</label>
                                        </div>

                                        <div class="d-flex justify-content-center mb-4">
                                            <button type="submit" class="btn btn-primary">Login</button>
                                        </div>
                                        
                                        <div class="text-center">
                                            <small>New to Training Management System? 
                                                <a href="signup.php" class="text-primary text-decoration-none fw-bold">Sign Up</a>
                                            </small>
                                        </div>
                                    </form>
                                </div>
                                <div class="col-md-10 col-lg-5 col-xl-6 d-flex align-items-center order-1 order-lg-2">
                                    <img src="https://tmr.scione.com/login-assetts/images/scione-login-.svg" class="img-fluid rounded-start" alt="College Campus">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        function togglePassword() {
            var passwordField = document.getElementById("password");
            var toggleIcon = document.querySelector(".toggle-password");
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>


