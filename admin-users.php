<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user || normalized_role($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $targetId = max(0, (int) ($_POST['user_id'] ?? 0));
    $action = (string) ($_POST['action'] ?? '');

    if ($targetId <= 0) {
        $error = 'Invalid user.';
    } elseif ($action === 'update_user') {
        $role = (string) ($_POST['role'] ?? 'staff');
        $department = trim((string) ($_POST['department'] ?? ''));
        $jobTitle = trim((string) ($_POST['job_title'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $allowedRoles = ['admin','staff','training_section','head_of_department','general_manager','pengerusi_besar','finance'];

        if (!in_array($role, $allowedRoles, true)) {
            $error = 'Invalid role.';
        } elseif ($targetId === (int) $user['id'] && $isActive === 0) {
            $error = 'You cannot deactivate your own administrator account.';
        } else {
            $stmt = db()->prepare(
                'UPDATE users
                 SET role = ?, department = ?, job_title = ?, is_active = ?
                 WHERE id = ?'
            );
            $stmt->bind_param('sssii', $role, $department, $jobTitle, $isActive, $targetId);
            $stmt->execute();
            $stmt->close();

            sts_audit(
                'admin_user_updated',
                'user',
                $targetId,
                ['role' => $role, 'department' => $department, 'active' => $isActive],
                (int) $user['id']
            );

            $message = 'User access updated.';
        }
    } elseif ($action === 'reset_password') {
        $temporaryPassword = (string) ($_POST['temporary_password'] ?? '');

        if (strlen($temporaryPassword) < 8) {
            $error = 'Temporary password must be at least 8 characters.';
        } else {
            $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $targetId);
            $stmt->execute();
            $stmt->close();

            sts_audit(
                'admin_password_reset',
                'user',
                $targetId,
                [],
                (int) $user['id']
            );

            sts_notify(
                $targetId,
                'Password reset by administrator',
                'Your STS password was reset by an administrator. Sign in using the temporary password provided to you.',
                'login.php',
                'warning'
            );

            $message = 'Temporary password saved.';
        }
    }
}

$result = db()->query(
    'SELECT id, fullname, email, phone_number, staff_id, department, job_title,
            role, is_active, created_at
     FROM users
     ORDER BY is_active DESC, fullname ASC'
);
$users = [];
while ($row = $result->fetch_assoc()) $users[] = $row;

$departmentResult = db()->query(
    'SELECT name FROM departments WHERE is_active = 1 ORDER BY name ASC'
);
$departments = [];
while ($row = $departmentResult->fetch_assoc()) $departments[] = $row['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Training System: User Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="sedco-saas.css?v=20261007-10">
<link rel="stylesheet" href="sedco-shell.css?v=20261007-03">
</head>
<body class="app-page admin-page" data-page="admin-users" data-role="admin">
<main class="sts-page-content">
<div class="sts-page-shell">
<header class="sts-page-heading">
  <div><div class="sts-eyebrow">Administration</div><h1>User Management</h1><p>Manage roles, departments, account access and temporary passwords.</p></div>
  <a class="sts-secondary-btn" href="admin-settings.php"><i class="bi bi-sliders"></i> System settings</a>
</header>
<?php if ($message): ?><div class="sts-alert success"><i class="bi bi-check-circle"></i><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="sts-alert danger"><i class="bi bi-exclamation-circle"></i><?= e($error) ?></div><?php endif; ?>

<section class="sts-card">
<div class="admin-user-toolbar">
  <div class="sts-search"><i class="bi bi-search"></i><input type="search" id="userSearch" placeholder="Search name, email, department or role..."></div>
  <span><?= count($users) ?> accounts</span>
</div>
<div class="admin-user-list" id="adminUserList">
<?php foreach ($users as $account): ?>
<article class="admin-user-card" data-user-search="<?= e(strtolower(implode(' ', [
    (string) $account['fullname'], (string) $account['email'], (string) $account['department'], (string) $account['role']
]))) ?>">
  <div class="admin-user-main">
    <span class="admin-user-avatar"><?= e(mb_strtoupper(mb_substr((string) $account['fullname'],0,1))) ?></span>
    <div>
      <div class="admin-user-name-row">
        <strong><?= e($account['fullname']) ?></strong>
        <span class="admin-user-status <?= (int) $account['is_active'] ? 'active' : 'inactive' ?>"><?= (int) $account['is_active'] ? 'Active' : 'Inactive' ?></span>
      </div>
      <p><?= e($account['email']) ?></p>
      <div class="admin-user-meta">
        <span><i class="bi bi-person-badge"></i><?= e(role_label((string) $account['role'])) ?></span>
        <span><i class="bi bi-building"></i><?= e($account['department'] ?: 'No department') ?></span>
        <span><i class="bi bi-hash"></i><?= e($account['staff_id'] ?: 'No Staff ID') ?></span>
        <a class="admin-user-history-link" href="employee-training-history.php?employee=<?= (int)$account['id'] ?>"><i class="bi bi-mortarboard"></i> Training history</a>
      </div>
    </div>
  </div>

  <details class="admin-user-editor">
    <summary>Edit access <i class="bi bi-chevron-down"></i></summary>
    <div class="admin-user-editor-body">
      <form method="post" action="admin-users.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
        <div class="admin-form-grid">
          <label><span>Role</span><select name="role">
            <?php foreach (['staff'=>'Staff / Applicant','training_section'=>'Training Department','head_of_department'=>'Head of Department','general_manager'=>'General Manager','pengerusi_besar'=>'Pengerusi','finance'=>'Kewangan','admin'=>'System Administrator'] as $value=>$label): ?>
            <option value="<?= e($value) ?>" <?= normalized_role((string) $account['role']) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select></label>
          <label><span>Department / Division</span><input type="text" name="department" list="departmentOptions" value="<?= e($account['department'] ?? '') ?>"></label>
          <label><span>Job title</span><input type="text" name="job_title" value="<?= e($account['job_title'] ?? '') ?>"></label>
          <label class="admin-toggle-label"><input type="checkbox" name="is_active" value="1" <?= (int) $account['is_active'] ? 'checked' : '' ?>><span>Account active</span></label>
        </div>
        <button class="sts-primary-btn" type="submit"><i class="bi bi-check2"></i> Save access</button>
      </form>

      <form method="post" action="admin-users.php" class="admin-password-reset">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
        <label><span>Temporary password</span><input type="password" name="temporary_password" minlength="8" required placeholder="Minimum 8 characters"></label>
        <button class="sts-secondary-btn" type="submit"><i class="bi bi-key"></i> Reset password</button>
      </form>
    </div>
  </details>
</article>
<?php endforeach; ?>
</div>
</section>

<datalist id="departmentOptions">
<?php foreach ($departments as $name): ?><option value="<?= e((string) $name) ?>"><?php endforeach; ?>
</datalist>
</div>
</main>
<script>
document.getElementById('userSearch')?.addEventListener('input', event => {
  const query=event.target.value.trim().toLowerCase();
  document.querySelectorAll('.admin-user-card').forEach(card => {
    card.hidden=query && !card.dataset.userSearch.includes(query);
  });
});
</script>
<script src="sedco-shell.js?v=20261007-03"></script>
</body>
</html>
