<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Training Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="sedco-saas.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="app-page profile-page">
<nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-0">
            <span class="navbar-brand">
                <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
                TRAINING MANAGEMENT SYSTEM
            </span>
            <div class="collapse navbar-collapse justify-content-end">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Contact Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> Log out</a></li>
                </ul>
            </div>
        </div>
    </nav>

<aside class="sidebar">
    <h4>Workspace</h4>
    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
    <a href="task.php"><i class="fas fa-tasks"></i> Tasks</a>
    <a href="#"><i class="fas fa-clipboard-check"></i> Application status</a>
    <a href="#"><i class="fas fa-inbox"></i> Submissions</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</aside>

<main class="profile-content">
    <div class="profile-shell">
        <div class="profile-kicker">Account</div>
        <h1 class="profile-page-title">My Profile</h1>

        <div class="profile-card">
            <div class="text-center mb-4">
                <div class="profile-image d-flex align-items-center justify-content-center" style="background:#f4f4f5;color:#8f1010;font-size:34px;">
                    <i class="fas fa-user"></i>
                </div>
                <h3 class="text-primary-custom"><?php echo isset($user['fullname']) ? htmlspecialchars($user['fullname']) : 'Guest'; ?></h3>
                <p class="text-muted mb-0">Training Management System Member</p>
            </div>

            <div class="row profile-info">
                <div class="col-md-6 mb-3">
                    <label>Email</label>
                    <p><?php echo isset($user['email']) ? htmlspecialchars($user['email']) : '—'; ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Phone Number</label>
                    <p><?php echo isset($user['phone_number']) ? htmlspecialchars($user['phone_number']) : '—'; ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Role</label>
                    <p><?php echo isset($user['block']) ? htmlspecialchars($user['block']) : '—'; ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Member Since</label>
                    <p>January 2024</p>
                </div>
            </div>

            <div class="d-flex justify-content-between gap-2 mt-2">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <a href="#" class="btn btn-edit">
                    <i class="fas fa-edit me-1"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>