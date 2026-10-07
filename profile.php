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
$hasProfilePhoto = isset($userColumns['profile_image']);
$existingProfileImage = '';

if ($hasProfilePhoto) {
    $photoStmt = $db->prepare(
        'SELECT profile_image
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $profileUserId = (int) $user['id'];
    $photoStmt->bind_param('i', $profileUserId);
    $photoStmt->execute();
    $photoRow = $photoStmt->get_result()->fetch_assoc();
    $photoStmt->close();

    $existingProfileImage = trim((string) ($photoRow['profile_image'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $jobTitle = trim((string) ($_POST['job_title'] ?? ''));

        if ($fullname === '') {
            $profileError = 'Full name is required.';
        } elseif (mb_strlen($fullname) > 120) {
            $profileError = 'Full name is too long.';
        } elseif ($hasExtendedProfile && mb_strlen($department) > 120) {
            $profileError = 'Department is too long.';
        } elseif (
            $hasExtendedProfile
            && $department !== ''
            && !in_array($department, array_merge(['Training'], sts_sedco_departments()), true)
        ) {
            $profileError = 'Please select a valid Department / Division.';
        } elseif ($hasExtendedProfile && mb_strlen($jobTitle) > 120) {
            $profileError = 'Job title is too long.';
        } else {
            $newProfileImage = $existingProfileImage;
            $uploadedProfilePath = null;
            $oldProfilePathToDelete = null;

            if ($hasProfilePhoto) {
                $removePhoto = isset($_POST['remove_profile_image']);

                if ($removePhoto) {
                    $newProfileImage = '';
                    if ($existingProfileImage !== '') {
                        $oldProfilePathToDelete = __DIR__ . '/uploads/profiles/' . basename($existingProfileImage);
                    }
                }

                if (
                    isset($_FILES['profile_image'])
                    && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
                ) {
                    $uploadError = (int) ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE);
                    $uploadSize = (int) ($_FILES['profile_image']['size'] ?? 0);
                    $tmpName = (string) ($_FILES['profile_image']['tmp_name'] ?? '');

                    if ($uploadError !== UPLOAD_ERR_OK) {
                        $profileError = 'Unable to upload the profile photo.';
                    } elseif ($uploadSize <= 0 || $uploadSize > 3 * 1024 * 1024) {
                        $profileError = 'Profile photo must be 3 MB or smaller.';
                    } elseif (!is_uploaded_file($tmpName)) {
                        $profileError = 'Invalid profile photo upload.';
                    } else {
                        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
                        $allowedImages = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp',
                        ];

                        if (!isset($allowedImages[$mime])) {
                            $profileError = 'Use a JPG, PNG, or WebP image.';
                        } else {
                            $uploadDir = __DIR__ . '/uploads/profiles';

                            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                                $profileError = 'Unable to prepare the profile photo folder.';
                            } else {
                                $storedName = 'user-' . (int) $user['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $allowedImages[$mime];
                                $destination = $uploadDir . '/' . $storedName;

                                if (!move_uploaded_file($tmpName, $destination)) {
                                    $profileError = 'Unable to save the profile photo.';
                                } else {
                                    $uploadedProfilePath = $destination;
                                    $newProfileImage = $storedName;

                                    if ($existingProfileImage !== '') {
                                        $oldProfilePathToDelete = __DIR__ . '/uploads/profiles/' . basename($existingProfileImage);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($profileError === null) {
                $userId = (int) $user['id'];

                if ($hasExtendedProfile) {
                    $departmentValue = $department !== '' ? $department : null;
                    $jobTitleValue = $jobTitle !== '' ? $jobTitle : null;

                    if ($hasProfilePhoto) {
                        $profileImageValue = $newProfileImage !== '' ? $newProfileImage : null;

                        $stmt = $db->prepare(
                            'UPDATE users
                             SET fullname = ?, department = ?, job_title = ?, profile_image = ?
                             WHERE id = ?'
                        );
                        $stmt->bind_param(
                            'ssssi',
                            $fullname,
                            $departmentValue,
                            $jobTitleValue,
                            $profileImageValue,
                            $userId
                        );
                    } else {
                        $stmt = $db->prepare(
                            'UPDATE users
                             SET fullname = ?, department = ?, job_title = ?
                             WHERE id = ?'
                        );
                        $stmt->bind_param(
                            'sssi',
                            $fullname,
                            $departmentValue,
                            $jobTitleValue,
                            $userId
                        );
                    }
                } else {
                    $stmt = $db->prepare(
                        'UPDATE users
                         SET fullname = ?
                         WHERE id = ?'
                    );
                    $stmt->bind_param('si', $fullname, $userId);
                }

                $stmt->execute();
                $stmt->close();

                if (
                    $oldProfilePathToDelete
                    && is_file($oldProfilePathToDelete)
                    && (!$uploadedProfilePath || realpath($oldProfilePathToDelete) !== realpath($uploadedProfilePath))
                ) {
                    @unlink($oldProfilePathToDelete);
                }

                $_SESSION['fullname'] = $fullname;

                sts_audit(
                    'profile_updated',
                    'user',
                    (int) $user['id'],
                    [
                        'photo_updated' => $hasProfilePhoto && $newProfileImage !== $existingProfileImage,
                        'department' => $department,
                        'job_title' => $jobTitle,
                    ],
                    (int) $user['id']
                );

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

$sedcoDepartments = array_merge(['Training'], sts_sedco_departments());

$profileDetails = [
    'staff_id' => null,
    'department' => null,
    'job_title' => null,
    'profile_image' => $existingProfileImage ?: null,
];

if ($hasExtendedProfile) {
    $detailsStmt = $db->prepare(
        'SELECT staff_id, department, job_title' . ($hasProfilePhoto ? ', profile_image' : '') . '
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
$memberSince = $joinedAt ? date('d M Y', $joinedAt) : 'Not available';

$nameParts = preg_split('/\s+/', trim((string) $user['fullname'])) ?: [];
$initials = '';

foreach (array_slice(array_values(array_filter($nameParts)), 0, 2) as $part) {
    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}

$initials = $initials !== '' ? $initials : 'U';

$profileImageName = trim((string) ($profileDetails['profile_image'] ?? $existingProfileImage));
$profileImageUrl = null;

if ($profileImageName !== '') {
    $profileImagePath = __DIR__ . '/uploads/profiles/' . basename($profileImageName);

    if (is_file($profileImagePath)) {
        $profileImageUrl = 'uploads/profiles/' . rawurlencode(basename($profileImageName));
    }
}

$profileCompletionFields = [
    $user['fullname'] ?? '',
    $user['email'] ?? '',
    $profileDetails['department'] ?? '',
    $profileDetails['job_title'] ?? '',
    $profileImageUrl ?? '',
];
$profileCompleted = count(array_filter(
    $profileCompletionFields,
    static fn ($value): bool => trim((string) $value) !== ''
));
$profileCompletion = (int) round(($profileCompleted / count($profileCompletionFields)) * 100);

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
    <title>Smart Training System: My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="sedco-saas.css?v=20261007-09">
    <link rel="stylesheet" href="sedco-shell.css?v=20261007-03">
</head>
<body class="app-page profile-page" data-page="profile" data-role="<?= e($normalizedRole) ?>">
<script>
try {
  const current = JSON.parse(localStorage.getItem('sedcoPreviewUser') || '{}');
  localStorage.setItem('sedcoPreviewUser', JSON.stringify({
    ...current,
    email: <?= json_encode((string) ($user['email'] ?? '')) ?>,
    fullname: <?= json_encode((string) ($user['fullname'] ?? '')) ?>,
    department: <?= json_encode((string) ($profileDetails['department'] ?? '')) ?>,
    job_title: <?= json_encode((string) ($profileDetails['job_title'] ?? '')) ?>,
    role: <?= json_encode((string) ($normalizedRole ?? 'staff')) ?>
  }));
} catch {}
</script>
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

        <section class="profile-hero-card profile-hero-v3">
            <div class="profile-hero-decoration" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>

            <div class="profile-identity">
                <div class="profile-avatar-wrap">
                    <div class="profile-image profile-avatar-placeholder">
                        <?php if ($profileImageUrl): ?>
                        <img src="<?= e($profileImageUrl) ?>" alt="<?= e($user['fullname']) ?> profile photo">
                        <?php else: ?>
                        <span><?= e($initials) ?></span>
                        <?php endif; ?>
                    </div>
                    <button
                        type="button"
                        class="profile-avatar-edit"
                        data-bs-toggle="modal"
                        data-bs-target="#editProfileModal"
                        aria-label="Change profile photo"
                        title="Change profile photo"
                    >
                        <i class="bi bi-camera-fill"></i>
                    </button>
                </div>

                <div class="profile-identity-copy">
                    <div class="profile-identity-badges">
                        <span class="profile-role-pill"><i class="bi bi-shield-check"></i><?= e($displayRole) ?></span>
                        <span class="profile-verified-pill"><i class="bi bi-check-circle-fill"></i> Active</span>
                    </div>
                    <h2><?= e($user['fullname']) ?></h2>
                    <p><?= e($user['email']) ?></p>
                    <div class="profile-identity-meta">
                        <?php if (!empty($profileDetails['department'])): ?>
                        <span><i class="bi bi-building"></i><?= e($profileDetails['department']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($profileDetails['job_title'])): ?>
                        <span><i class="bi bi-briefcase"></i><?= e($profileDetails['job_title']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="profile-hero-side">
                <div class="profile-quick-meta">
                    <div>
                        <span>Member since</span>
                        <strong><?= e($memberSince) ?></strong>
                    </div>
                    <div>
                        <span>Account status</span>
                        <strong class="profile-active-text"><i class="bi bi-check-circle-fill"></i> Active</strong>
                    </div>
                    <div class="profile-completion-card">
                        <span>Profile completeness</span>
                        <strong><?= $profileCompletion ?>%</strong>
                        <i><b style="width:<?= $profileCompletion ?>%"></b></i>
                    </div>
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
                        <span class="profile-detail-icon"><i class="bi bi-person-badge"></i></span>
                        <div>
                            <label>Role</label>
                            <strong><?= e($displayRole) ?></strong>
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
                        <a href="employee-training-history.php?employee=<?= (int)$user['id'] ?>" class="profile-security-button">
                            <i class="bi bi-mortarboard"></i> Training history
                        </a>
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
            <form method="post" action="profile.php" enctype="multipart/form-data">
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
                    <div class="profile-photo-editor">
                        <div class="profile-photo-preview" id="profilePhotoPreview">
                            <?php if ($profileImageUrl): ?>
                            <img src="<?= e($profileImageUrl) ?>" alt="Current profile photo">
                            <?php else: ?>
                            <span><?= e($initials) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="profile-photo-corner-icon" aria-hidden="true"><i class="bi bi-camera-fill"></i></span>

                        <div class="profile-photo-editor-copy">
                            <strong>Profile photo</strong>
                            <p>Use a clear square photo. JPG, PNG or WebP, maximum 3 MB.</p>

                            <?php if ($hasProfilePhoto): ?>
                            <div class="profile-photo-actions">
                                <label class="profile-photo-upload">
                                    <i class="bi bi-camera"></i>
                                    Choose photo
                                    <input
                                        id="profilePhotoInput"
                                        type="file"
                                        name="profile_image"
                                        accept="image/jpeg,image/png,image/webp"
                                    >
                                </label>

                                <?php if ($profileImageUrl): ?>
                                <label class="profile-photo-remove">
                                    <input type="checkbox" name="remove_profile_image" value="1">
                                    <span>Remove current photo</span>
                                </label>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="profile-schema-note compact">
                                <i class="bi bi-database-add"></i>
                                Import <strong>PROFILE_PHOTO_UPGRADE.sql</strong> once to enable profile photos.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-form-grid">
                        <label class="profile-field profile-field-name">
                            <span>Full name</span>
                            <div class="profile-input-shell profile-input-name">
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <input type="text" name="fullname" required maxlength="120" value="<?= e($user['fullname']) ?>">
                            </div>
                        </label>

                        <label class="profile-field profile-field-email">
                            <span>Email address</span>
                            <div class="profile-input-shell profile-input-email">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <input type="email" value="<?= e($user['email']) ?>" disabled>
                            </div>
                            <small>Email is used for login and cannot be changed here.</small>
                        </label>

                        <label class="profile-field profile-field-department">
                            <span>Department / Division</span>
                            <select name="department" <?= !$hasExtendedProfile ? 'disabled' : '' ?>>
                                <option value="">Select SEDCO Department / Division</option>
                                <?php foreach ($sedcoDepartments as $departmentName): ?>
                                <option value="<?= e($departmentName) ?>" <?= (string) ($profileDetails['department'] ?? '') === $departmentName ? 'selected' : '' ?>>
                                    <?= e($departmentName) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small>This is used automatically in BPL Section A and for HoD routing.</small>
                        </label>

                        <label class="profile-field profile-field-job">
                            <span>Position / Job title</span>
                            <div class="profile-input-shell profile-input-job">
                                <i class="bi bi-briefcase" aria-hidden="true"></i>
                                <input type="text" name="job_title" maxlength="120" value="<?= e((string) ($profileDetails['job_title'] ?? '')) ?>" <?= !$hasExtendedProfile ? 'disabled' : '' ?>>
                            </div>
                        </label>
                    </div>

                    <?php if (!$hasExtendedProfile): ?>
                    <div class="profile-schema-note">
                        <i class="bi bi-database-add"></i>
                        Import <strong>PROFILE_UPGRADE.sql</strong> once to enable Department and Job Title.
                    </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="profile-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="profile-modal-save"><i class="bi bi-check2"></i><span>Save changes</span></button>
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
<script src="sedco-shell.js?v=20261007-03"></script>
<script>
(() => {
  const input = document.getElementById('profilePhotoInput');
  const preview = document.getElementById('profilePhotoPreview');

  if (!input || !preview) return;

  input.addEventListener('change', () => {
    const file = input.files?.[0];
    if (!file || !file.type.startsWith('image/')) return;

    const url = URL.createObjectURL(file);
    preview.innerHTML = '';
    const image = document.createElement('img');
    image.src = url;
    image.alt = 'Profile photo preview';
    image.onload = () => URL.revokeObjectURL(url);
    preview.appendChild(image);
  });
})();
</script>
<?php if ($profileError !== null): ?>
<script>bootstrap.Modal.getOrCreateInstance(document.getElementById('editProfileModal')).show();</script>
<?php elseif ($passwordError !== null): ?>
<script>bootstrap.Modal.getOrCreateInstance(document.getElementById('passwordModal')).show();</script>
<?php endif; ?>
<script>
(() => {
  /* profile department premium dropdown */
  document.querySelectorAll('.profile-modal select[name="department"]').forEach(select => {
    if (select.dataset.profileCustom === '1') return;
    select.dataset.profileCustom = '1';

    const wrap = document.createElement('div');
    wrap.className = 'profile-department-combobox';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'profile-department-trigger';
    trigger.setAttribute('aria-haspopup','listbox');
    trigger.setAttribute('aria-expanded','false');

    const leading = document.createElement('i');
    leading.className = 'bi bi-buildings profile-department-leading';

    const text = document.createElement('span');
    text.className = 'profile-department-text';

    const icon = document.createElement('i');
    icon.className = 'bi bi-chevron-down profile-department-chevron';

    trigger.append(leading,text,icon);

    const menu = document.createElement('div');
    menu.className = 'profile-department-menu';
    menu.setAttribute('role','listbox');
    menu.hidden = true;

    const menuHead = document.createElement('div');
    menuHead.className = 'profile-department-menu-head';
    menuHead.innerHTML = '<span>Choose department</span><small>Select one</small>';
    menu.appendChild(menuHead);

    const sync = () => {
      const option = select.options[select.selectedIndex] || null;
      const hasValue = Boolean(select.value);

      text.textContent = hasValue
        ? (option?.textContent?.trim() || 'Select department')
        : 'Select SEDCO Department / Division';

      trigger.classList.toggle('is-placeholder', !hasValue);
      trigger.disabled = select.disabled;

      menu.querySelectorAll('.profile-department-option').forEach(item => {
        const active = item.dataset.value === select.value;
        item.classList.toggle('is-selected', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
      });
    };

    [...select.options].forEach(option => {
      if (!option.value) return;

      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'profile-department-option';
      item.dataset.value = option.value;
      item.setAttribute('role','option');

      const optionIcon = document.createElement('i');
      optionIcon.className = option.value === 'Training'
        ? 'bi bi-mortarboard profile-department-option-icon'
        : 'bi bi-building profile-department-option-icon';

      const optionText = document.createElement('span');
      optionText.className = 'profile-department-option-text';
      optionText.textContent = option.textContent.trim();

      const optionCheck = document.createElement('i');
      optionCheck.className = 'bi bi-check2 profile-department-option-check';
      optionCheck.setAttribute('aria-hidden','true');

      item.append(optionIcon, optionText, optionCheck);

      item.addEventListener('click', () => {
        select.value = option.value;
        select.dispatchEvent(new Event('change',{bubbles:true}));
        menu.hidden = true;
        trigger.setAttribute('aria-expanded','false');
        sync();
        trigger.focus();
      });

      menu.appendChild(item);
    });

    trigger.addEventListener('click', () => {
      const open = menu.hidden;

      document.querySelectorAll('.profile-department-menu:not([hidden])').forEach(other => {
        if (other !== menu) other.hidden = true;
      });
      document.querySelectorAll('.profile-department-trigger[aria-expanded="true"]').forEach(other => {
        if (other !== trigger) other.setAttribute('aria-expanded','false');
      });

      if (open) {
        const rect = trigger.getBoundingClientRect();
        const estimatedMenuHeight = Math.min(238, Math.max(150, menu.scrollHeight || 238));
        const spaceBelow = window.innerHeight - rect.bottom - 16;
        const spaceAbove = rect.top - 16;
        const shouldOpenUp = spaceBelow < estimatedMenuHeight && spaceAbove > spaceBelow;

        menu.classList.toggle('opens-up', shouldOpenUp);
        menu.scrollTop = 0;
      }

      menu.hidden = !open;
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    select.addEventListener('change', sync);

    select.parentNode.insertBefore(wrap,select);
    wrap.append(trigger,menu,select);
    select.classList.add('profile-department-native');
    sync();
  });

  document.addEventListener('click', event => {
    if (event.target.closest('.profile-department-combobox')) return;
    document.querySelectorAll('.profile-department-menu:not([hidden])').forEach(menu => menu.hidden = true);
    document.querySelectorAll('.profile-department-trigger[aria-expanded="true"]').forEach(trigger => trigger.setAttribute('aria-expanded','false'));
  });
})();
</script>
</body>
</html>
