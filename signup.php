<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart College Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: url('http://localhost/STS/v2/assets/img/sedco.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Source Sans Pro', sans-serif;
        }

        .card {
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            background-color: rgba(255, 255, 255, 0.96);
            width: 420px;
            margin: auto;
        }

        .form-control {
            font-size: 15px;
            border-radius: 8px;
            padding: 10px 12px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid #ccc;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #B30E02;
            box-shadow: 0 0 0 0.1rem rgba(179, 14, 2, 0.25);
        }

        .btn-primary {
            background-color: #B30E02;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #990C02;
        }

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            color: #888;
            cursor: pointer;
        }

        .form-outline {
            margin-bottom: 16px;
        }

        .form-select {
            border-radius: 8px;
        }

        .text-primary {
            color: #B30E02 !important;
            font-weight: 600;
        }

        .notification {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #28a745;
            color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }
    </style>
</head>
<body>

<section class="vh-100 d-flex align-items-center justify-content-center">
    <div class="card p-4">
        <p class="text-center h3 fw-bold mb-4">Register</p>

        <form action="signup.php" method="POST">
            <div class="form-outline">
                <input type="text" name="fullname" class="form-control bg-light" placeholder="Full Name" required>
            </div>
            <div class="form-outline">
                <input type="email" name="email" class="form-control bg-light" placeholder="Email" required>
            </div>
            <div class="form-outline">
                <input type="text" name="phone_number" class="form-control bg-light" placeholder="Phone Number" required>
            </div>
            <div class="form-outline">
                <select name="block" class="form-control bg-light" required>
                    <option value="" disabled selected>Select Role</option>
                    <option value="staff">Staff</option>
                    <option value="head of division">Head of Division</option>
                    <option value="head of department">Head of Department</option>
                    <option value="pengerusi besar">Pengerusi Besar</option>
                </select>
            </div>
            <div class="form-outline password-container">
                <input type="password" id="password" name="password" class="form-control bg-light" placeholder="Password" required>
                <i class="fa fa-eye toggle-password" onclick="togglePassword()"></i>
            </div>
            <div class="d-flex justify-content-center mb-3">
                <button type="submit" class="btn btn-primary w-100">Register</button>
            </div>
            <div class="text-center">
                <small>Already have an account? <a href="login.php" class="text-primary">Sign In</a></small>
            </div>
        </form>
    </div>
</section>

<div class="notification" id="notification">Registration successful! Redirecting...</div>

<script>
    function togglePassword() {
        const passwordField = document.getElementById("password");
        const icon = document.querySelector(".toggle-password");
        if (passwordField.type === "password") {
            passwordField.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            passwordField.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }

    document.querySelector("form").addEventListener("submit", function(event) {
        event.preventDefault();
        const notification = document.getElementById("notification");
        notification.style.display = "block";
        setTimeout(() => {
            notification.style.display = "none";
            this.submit();
        }, 2000);
    });
</script>

</body>
</html>
