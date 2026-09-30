<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$db = db();
$profileError = null;
$passwordError = null;

$columnResult = $db->query('SHOW COLUMNS FROM users');
$userColumns = [];

while ($column = $columnResult->fetch_assoc()) {
    $userColumns[(string) $column['Field']] = true;
}

$hasExtendedProfile = isset(
    $userColumns['staff_id'],
    $userColumns['department'],
    $userColumns['job_title']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
        $staffId = trim((string) ($_POST['staff_id'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $jobTitle = trim((string) ($_POST['job_title'] ?? ''));

        if ($fullname === '') {
            $profileError = 'Full name is required.';
        } elseif (mb_strlen($fullname) > 120) {
            $profileError = 'Full name is too long.';
        } elseif (mb_strlen($phoneNumber) > 30) {
            $profileError = 'Phone number is too long.';
        } elseif ($hasExtendedProfile && mb_strlen($staffId) > 50) {
            $profileError = 'Staff ID is too long.';
        } elseif ($hasExtendedProfile && mb_strlen($department) > 120) {
            $profileError = 'Department is too long.';
        } elseif ($hasExtendedProfile && mb_strlen($jobTitle) > 120) {
            $profileError = 'Job title is too long.';
        } else {
            if ($hasExtendedProfile && $staffId !== '') {
                $check = $db->prepare(
                    'SELECT id FROM users WHERE staff_id = ? AND id <> ? LIMIT 1'
                );
                $userId = (int) $user['id'];
                $check->bind_param('si', $staffId, $userId);
                $check->execute();
                $duplicate = $check->get_result()->fetch_assoc();
                $check->close();

                if ($duplicate) {
                    $profileError = 'That Staff ID is already in use.';
                }
            }

            if ($profileError === null) {
                $userId = (int) $user['id'];

                if ($hasExtendedProfile) {
                    $staffIdValue = $staffId !== '' ? $staffId : null;
                    $departmentValue = $department !== '' ? $department : null;
                    $jobTitleValue = $jobTitle !== '' ? $jobTitle : null;

                    $stmt = $db->prepare(
                        'UPDATE users
                         SET fullname = ?, phone_number = ?, staff_id = ?,
                             department = ?, job_title = ?
                         WHERE id = ?'
                    );
                    $stmt->bind_param(
                        'sssssi',
                        $fullname,
                        $phoneNumber,
                        $staffIdValue,
                        $departmentValue,
                        $jobTitleValue,
                        $userId
                    );
                } else {
                    $stmt = $db->prepare(
                        'UPDATE users
                         SET fullname = ?, phone_number = ?
                         WHERE id = ?'
                    );
                    $stmt->bind_param('ssi', $fullname, $phoneNumber, $userId);
                }

                $stmt->execute();
                $stmt->close();

                $_SESSION['fullname'] = $fullname;

                header('Location: profile.php?updated=1');
                exit;
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $passwordStmt = $db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $userId = (int) $user['id'];
        $passwordStmt->bind_param('i', $userId);
        $passwordStmt->execute();
        $passwordRow = $passwordStmt->get_result()->fetch_assoc();
        $passwordStmt->close();

        if (!$passwordRow || !password_verify($currentPassword, (string) $passwordRow['password'])) {
            $passwordError = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $passwordError = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'New password and confirmation do not match.';
        } elseif (password_verify($newPassword, (string) $passwordRow['password'])) {
            $passwordError = 'New password must be different from your current password.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $updatePassword = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
            $updatePassword->bind_param('si', $newHash, $userId);
            $updatePassword->execute();
            $updatePassword->close();

            header('Location: profile.php?password_changed=1');
            exit;
        }
    }
}

$profileDetails = [
    'staff_id' => null,
    'department' => null,
    'job_title' => null,
];

if ($hasExtendedProfile) {
    $detailsStmt = $db->prepare(
        'SELECT staff_id, department, job_title
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $userId = (int) $user['id'];
    $detailsStmt->bind_param('i', $userId);
    $detailsStmt->execute();
    $profileDetails = $detailsStmt->get_result()->fetch_assoc() ?: $profileDetails;
    $detailsStmt->close();
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

$activities = [];
$userId = (int) $user['id'];

if ($normalizedRole === 'staff') {
    $activityStmt = $db->prepare(
        'SELECT application_no, form_type, status, current_stage, submitted_at AS activity_at
         FROM applications
         WHERE user_id = ?
         ORDER BY submitted_at DESC
         LIMIT 5'
    );
    $activityStmt->bind_param('i', $userId);
    $activityStmt->execute();
    $activityResult = $activityStmt->get_result();

    while ($row = $activityResult->fetch_assoc()) {
        $activities[] = [
            'icon' => 'bi-file-earmark-text',
            'title' => 'Submitted ' . $row['form_type'],
            'meta' => $row['application_no'] . ' · ' . ucfirst((string) $row['status']),
            'time' => $row['activity_at'],
        ];
    }

    $activityStmt->close();
} elseif (user_can_review_applications($user)) {
    $activityStmt = $db->prepare(
        'SELECT r.review_stage, r.decision, r.reviewed_at AS activity_at,
                a.application_no, a.form_type
         FROM application_reviews r
         INNER JOIN applications a ON a.id = r.application_id
         WHERE r.reviewer_id = ?
         ORDER BY r.reviewed_at DESC
         LIMIT 5'
    );
    $activityStmt->bind_param('i', $userId);
    $activityStmt->execute();
    $activityResult = $activityStmt->get_result();

    while ($row = $activityResult->fetch_assoc()) {
        $activities[] = [
            'icon' => 'bi-check2-circle',
            'title' => ucfirst((string) $row['decision']) . ' ' . $row['form_type'],
            'meta' => $row['application_no'] . ' · ' . stage_label((string) $row['review_stage']),
            'time' => $row['activity_at'],
        ];
    }

    $activityStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Smart Training System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="sedco-saas.css?v=20260930-36">
    <link rel="stylesheet" href="sedco-shell.css?v=20260930-56">
</head>
<body class="app-page profile-page" data-page="profile" data-role="<?= e($normalizedRole) ?>">
<main class="profile-content">
    <div class="profile-shell">
        <header class="profile-heading">
            <div>
                <div class="profile-kicker">Account</div>
                <h1 class="profile-page-title">My Profile</h1>
                <p class="profile-page-subtitle">Your account details, security, and access inside Smart Training System.</p>
            </div>
            <span class="profile-account-state"><span></span> Active account</span>
        </header>

        <?php if (isset($_GET['updated'])): ?>
        <div class="profile-alert profile-alert-success">
            <i class="bi bi-check-circle-fill"></i>
            Profile updated successfully.
        </div>
        <?php elseif (isset($_GET['password_changed'])): ?>
        <div class="profile-alert profile-alert-success">
            <i class="bi bi-shield-check"></i>
            Password changed successfully.
        </div>
        <?php elseif ($profileError !== null): ?>
        <div class="profile-alert profile-alert-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= e($profileError) ?>
        </div>
        <?php elseif ($passwordError !== null): ?>
        <div class="profile-alert profile-alert-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= e($passwordError) ?>
        </div>
        <?php endif; ?>

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
                    <div class="profile-detail">
                        <span class="profile-detail-icon"><i class="bi bi-hash"></i></span>
                        <div>
                            <label>Staff ID</label>
                            <strong><?= e((string) ($profileDetails['staff_id'] ?: 'Not provided')) ?></strong>
                        </div>
                    </div>
                    <div class="profile-detail">
                        <span class="profile-detail-icon"><i class="bi bi-building"></i></span>
                        <div>
                            <label>Department / Division</label>
                            <strong><?= e((string) ($profileDetails['department'] ?: 'Not provided')) ?></strong>
                        </div>
                    </div>
                    <div class="profile-detail">
                        <span class="profile-detail-icon"><i class="bi bi-briefcase"></i></span>
                        <div>
                            <label>Position / Job title</label>
                            <strong><?= e((string) ($profileDetails['job_title'] ?: 'Not provided')) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="profile-panel-footer">
                    <a href="dashboard.php" class="profile-back-link">
                        <i class="bi bi-arrow-left"></i> Back to dashboard
                    </a>
                    <div class="profile-action-group">
                        <button type="button" class="profile-security-button" data-bs-toggle="modal" data-bs-target="#passwordModal">
                            <i class="bi bi-key"></i> Change password
                        </button>
                        <button type="button" class="profile-edit-button is-enabled" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="bi bi-pencil-square"></i> Edit profile
                        </button>
                    </div>
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

        <section class="profile-panel profile-activity-panel">
            <div class="profile-panel-heading">
                <div>
                    <span class="profile-panel-kicker">Account activity</span>
                    <h3>Recent activity</h3>
                </div>
                <i class="bi bi-clock-history profile-panel-icon"></i>
            </div>

            <?php if ($activities): ?>
            <div class="profile-activity-list">
                <?php foreach ($activities as $activity): ?>
                <div class="profile-activity-item">
                    <span class="profile-activity-icon"><i class="bi <?= e($activity['icon']) ?>"></i></span>
                    <div class="profile-activity-copy">
                        <strong><?= e($activity['title']) ?></strong>
                        <span><?= e($activity['meta']) ?></span>
                    </div>
                    <time><?= e(date('d M Y, g:i A', strtotime((string) $activity['time']))) ?></time>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="profile-empty-activity">
                <i class="bi bi-clock-history"></i>
                <div>
                    <strong>No activity yet</strong>
                    <span>Your latest STS activity will appear here.</span>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<div class="modal fade profile-modal" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="profile.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="modal-header">
                    <div>
                        <span class="profile-modal-kicker">Personal information</span>
                        <h2 class="modal-title">Edit profile</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="profile-form-grid">
                        <label>
                            <span>Full name</span>
                            <input type="text" name="fullname" required maxlength="120" value="<?= e($user['fullname']) ?>">
                        </label>

                        <label>
                            <span>Email address</span>
                            <input type="email" value="<?= e($user['email']) ?>" disabled>
                            <small>Email is used for login and cannot be changed here.</small>
                        </label>

                        <label>
                            <span>Phone number</span>
                            <input type="tel" name="phone_number" maxlength="30" value="<?= e($user['phone_number'] ?? '') ?>">
                        </label>

                        <label>
                            <span>Staff ID</span>
                            <input type="text" name="staff_id" maxlength="50" value="<?= e((string) ($profileDetails['staff_id'] ?? '')) ?>" <?= !$hasExtendedProfile ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            <span>Department / Division</span>
                            <input type="text" name="department" maxlength="120" value="<?= e((string) ($profileDetails['department'] ?? '')) ?>" <?= !$hasExtendedProfile ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            <span>Position / Job title</span>
                            <input type="text" name="job_title" maxlength="120" value="<?= e((string) ($profileDetails['job_title'] ?? '')) ?>" <?= !$hasExtendedProfile ? 'disabled' : '' ?>>
                        </label>
                    </div>

                    <?php if (!$hasExtendedProfile): ?>
                    <div class="profile-schema-note">
                        <i class="bi bi-database-add"></i>
                        Import <strong>PROFILE_UPGRADE.sql</strong> once to enable Staff ID, Department, and Job Title.
                    </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="profile-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="profile-modal-save">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade profile-modal" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="profile.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="modal-header">
                    <div>
                        <span class="profile-modal-kicker">Account security</span>
                        <h2 class="modal-title">Change password</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="profile-form-grid profile-password-grid">
                        <label>
                            <span>Current password</span>
                            <input type="password" name="current_password" autocomplete="current-password" required>
                        </label>

                        <label>
                            <span>New password</span>
                            <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
                            <small>Use at least 8 characters.</small>
                        </label>

                        <label>
                            <span>Confirm new password</span>
                            <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="profile-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="profile-modal-save">Update password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="sedco-shell.js?v=20260930-56"></script>
<?php if ($profileError !== null): ?>
<script>bootstrap.Modal.getOrCreateInstance(document.getElementById('editProfileModal')).show();</script>
<?php elseif ($passwordError !== null): ?>
<script>bootstrap.Modal.getOrCreateInstance(document.getElementById('passwordModal')).show();</script>
<?php endif; ?>
</body>
</html>
