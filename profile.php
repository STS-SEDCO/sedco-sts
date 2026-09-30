<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Smart Training System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="sedco-saas.css?v=20260930-14">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-14">
</head>
<body class="app-page profile-page" data-page="profile">
<main class="profile-content">
    <div class="profile-shell">
        <div class="profile-kicker">Account</div>
        <h1 class="profile-page-title">My Profile</h1>

        <div class="profile-card">
            <div class="text-center mb-4">
                <div class="profile-image profile-avatar-placeholder d-flex align-items-center justify-content-center">
                    <i class="bi bi-person"></i>
                </div>
                <h3 class="text-primary-custom"><?= e($user['fullname']) ?></h3>
                <p class="text-muted mb-0">Smart Training System Member</p>
            </div>

            <div class="row profile-info">
                <div class="col-md-6 mb-3">
                    <label>Email</label>
                    <p><?= e($user['email']) ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Phone Number</label>
                    <p><?= e($user['phone_number'] ?: '—') ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Role</label>
                    <p><?= e(ucwords(str_replace('_', ' ', $user['role']))) ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Member Since</label>
                    <p>January 2024</p>
                </div>
            </div>

            <div class="d-flex justify-content-between gap-2 mt-2">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
                <button type="button" class="btn btn-edit" disabled title="Profile editing is not enabled yet">
                    <i class="bi bi-pencil me-1"></i> Edit Profile
                </button>
            </div>
        </div>
    </div>
</main>
<script src="sedco-shell.js?v=20260930-18"></script>
</body>
</html>