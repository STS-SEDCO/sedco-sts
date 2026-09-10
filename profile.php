<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart College - My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: url('http://localhost/STS/v2/assets/img/sedco.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Source Sans Pro', sans-serif;
            margin: 0;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            padding: 30px;
            max-width: 700px;
            width: 90%;
            margin: auto;
        }

        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #ddd;
            display: block;
            margin: 0 auto 20px;
        }

        .profile-info label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .profile-info p {
            font-size: 16px;
            color: #555;
            margin-bottom: 20px;
        }

        .btn-edit {
            background-color: #B30E02;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .btn-edit:hover {
            background-color: #990C02;
        }

        .text-primary-custom {
            color: #B30E02 !important;
            font-weight: 700;
        }
    </style>
</head>
<body>

<section class="vh-100 d-flex align-items-center justify-content-center">
    <div class="profile-card">
        <div class="text-center mb-4">
            <img src="https://via.placeholder.com/120" alt="Profile Picture" class="profile-image">
            <h3 class="text-primary-custom"><?php echo htmlspecialchars($user['fullname']); ?></h3>
            <p class="text-muted">Smart College Member</p>
        </div>

        <div class="row profile-info">
            <div class="col-md-6 mb-3">
                <label>Email</label>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>

            <div class="col-md-6 mb-3">
                <label>Phone Number</label>
                <p><?php echo htmlspecialchars($user['phone_number']); ?></p>
            </div>

            <div class="col-md-6 mb-3">
                <label>Role</label>
                <p><?php echo htmlspecialchars($user['block']); ?></p>
            </div>

            <div class="col-md-6 mb-3">
                <label>Member Since</label>
                <p>January 2024</p> <!-- You can replace this dynamically if needed -->
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="edit_profile.php" class="btn btn-edit rounded-pill">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
