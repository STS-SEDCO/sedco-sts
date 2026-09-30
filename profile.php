<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$displayRole = role_label($user['role'] ?? '');
$joinedAt = !empty($user['created_at']) ? strtotime((string) $user['created_at']) : false;
$memberSince = $joinedAt ? date('d M Y', $joinedAt) : '—';

$nameParts = preg_split('/\s+/', trim((string) $user['fullname'])) ?: [];
$initials = '';
foreach (array_slice(array_values(array_filter($nameParts)), 0, 2) as $part) {
    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = $initials !== '' ? $initials : 'U';

$normalizedRole = normalized_role($user['role'] ?? '');
$accessItems = match ($normalizedRole) {
    'staff' => [
        ['bi-file-earmark-plus', 'Submit training forms', 'Create BPL and PKK submissions.'],
        ['bi-clipboard-data', 'Track applications', 'Follow each submission through its approval stage.'],
    ],
    'head_of_department' => [
        ['bi-inbox', 'Review BPL submissions', 'Review applications currently assigned to HoD.'],
        ['bi-clipboard2-check', 'Complete TEA', 'Submit Training Effectiveness Assessments.'],
    ],
    'training_section' => [
        ['bi-inbox', 'Training review queue', 'Process applications forwarded by HoD.'],
        ['bi-diagram-3', 'Manage workflow', 'Forward approved BPL applications to General Manager.'],
    ],
    'general_manager' => [
        ['bi-patch-check', 'Final approval', 'Make the final decision for BPL applications.'],
        ['bi-inbox', 'Review queue', 'View applications waiting at General Manager stage.'],
    ],
    'admin' => [
        ['bi-shield-check', 'System access', 'Access and review all STS workflow stages.'],
        ['bi-people', 'User oversight', 'Support users and system administration.'],
    ],
    default => [
        ['bi-person-check', 'STS account', 'Access features assigned to your account role.'],
    ],
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Smart Training System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="sedco-saas.css?v=20260930-35">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-35">
</head>
<body class="app-page profile-page" data-page="profile" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">
<main class="profile-content">
    <div class="profile-shell">
        <header class="profile-heading">
            <div>
                <div class="profile-kicker">Account</div>
                <h1 class="profile-page-title">My Profile</h1>
                <p class="profile-page-subtitle">Your account details and access inside Smart Training System.</p>
            </div>
            <span class="profile-account-state"><span></span> Active account</span>
        </header>

        <section class="profile-hero-card">
            <div class="profile-identity">
                <div class="profile-image profile-avatar-placeholder" aria-hidden="true">
                    <span><?= e($initials) ?></span>
                </div>
                <div class="profile-identity-copy">
                    <div class="profile-role-pill"><i class="bi bi-shield-check"></i><?= e($displayRole) ?></div>
                    <h2><?= e($user['fullname']) ?></h2>
                    <p><?= e($user['email']) ?></p>
                </div>
            </div>

            <div class="profile-quick-meta">
                <div>
                    <span>Member since</span>
                    <strong><?= e($memberSince) ?></strong>
                </div>
                <div>
                    <span>Account status</span>
                    <strong class="profile-active-text"><i class="bi bi-check-circle-fill"></i> Active</strong>
                </div>
            </div>
        </section>

        <div class="profile-grid">
            <section class="profile-panel">
                <div class="profile-panel-heading">
                    <div>
                        <span class="profile-panel-kicker">Personal information</span>
                        <h3>Account details</h3>
                    </div>
                    <i class="bi bi-person-vcard profile-panel-icon"></i>
                </div>

                <div class="profile-details-grid">
                    <div class="profile-detail">
                        <span class="profile-detail-icon"><i class="bi bi-person"></i></span>
                        <div>
                            <label>Full name</label>
                            <strong><?= e($user['fullname']) ?></strong>
                        </div>
                    </div>
                    <div class="profile-detail">
                        <span class="profile-detail-icon"><i class="bi bi-envelope"></i></span>
                        <div>
                            <label>Email address</label>
                            <strong><?= e($user['email']) ?></strong>
                        </div>
                    </div>
                    <div class="profile-detail">
                        <span class="profile-detail-icon"><i class="bi bi-telephone"></i></span>
                        <div>
                            <label>Phone number</label>
                            <strong><?= e($user['phone_number'] ?: 'Not provided') ?></strong>
                        </div>
                    </div>
                    <div class="profile-detail">
                        <span class="profile-detail-icon"><i class="bi bi-person-badge"></i></span>
                        <div>
                            <label>Role</label>
                            <strong><?= e($displayRole) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="profile-panel-footer">
                    <a href="dashboard.php" class="profile-back-link">
                        <i class="bi bi-arrow-left"></i> Back to dashboard
                    </a>
                    <button type="button" class="profile-edit-button" disabled title="Profile editing will be enabled in a later update">
                        <i class="bi bi-pencil-square"></i> Edit profile
                    </button>
                </div>
            </section>

            <aside class="profile-panel profile-access-panel">
                <div class="profile-panel-heading">
                    <div>
                        <span class="profile-panel-kicker">Permissions</span>
                        <h3>Your STS access</h3>
                    </div>
                    <i class="bi bi-shield-lock profile-panel-icon"></i>
                </div>

                <div class="profile-access-list">
                    <?php foreach ($accessItems as [$icon, $title, $description]): ?>
                    <div class="profile-access-item">
                        <span><i class="bi <?= e($icon) ?>"></i></span>
                        <div>
                            <strong><?= e($title) ?></strong>
                            <p><?= e($description) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="profile-access-note">
                    <i class="bi bi-info-circle"></i>
                    Access is controlled by your assigned role.
                </div>
            </aside>
        </div>
    </div>
</main>
<script src="sedco-shell.js?v=20260930-35"></script>
</body>
</html>