<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
sts_ensure_followup_notifications($user);
$role = normalized_role($user['role'] ?? '');
$canStaffForms = in_array(
    $role,
    ['staff', 'head_of_department', 'general_manager', 'pengerusi_besar', 'admin'],
    true
);
$canTea = $role === 'head_of_department';

$userId = (int) ($user['id'] ?? 0);
$department = trim((string) ($user['department'] ?? ''));
$followupItems = [];

$applicantRoles = ['staff', 'head_of_department', 'general_manager', 'pengerusi_besar'];

if (in_array($role, $applicantRoles, true)) {
    $ownStmt = db()->prepare(
        'SELECT b.id, b.application_no, b.title, b.training_end,
                EXISTS(
                  SELECT 1 FROM applications p
                  WHERE p.parent_application_id = b.id
                    AND p.form_type = "PKK"
                    AND p.status <> "cancelled"
                ) AS has_followup
         FROM applications b
         WHERE b.user_id = ?
           AND b.form_type = "BPL"
           AND b.status = "approved"
           AND b.training_end IS NOT NULL
         ORDER BY b.training_end DESC'
    );
    $ownStmt->bind_param('i', $userId);
    $ownStmt->execute();
    $ownResult = $ownStmt->get_result();

    while ($row = $ownResult->fetch_assoc()) {
        if (!(int) $row['has_followup']) {
            $row['follow_type'] = 'PKK';
            $followupItems[] = $row;
        }
    }
    $ownStmt->close();
}

if ($role === 'head_of_department') {
    $followStmt = db()->prepare(
        'SELECT b.id, b.application_no, b.title, b.training_end, b.department,
                EXISTS(
                  SELECT 1 FROM applications t
                  WHERE t.parent_application_id = b.id AND t.form_type = "TEA"
                ) AS has_followup
         FROM applications b
         WHERE b.form_type = "BPL"
           AND b.status = "approved"
           AND b.user_id <> ?
           AND (
             b.assigned_hod_id = ?
             OR (
               b.assigned_hod_id IS NULL
               AND (b.department = ? OR b.department IS NULL OR b.department = "")
             )
           )
         ORDER BY b.training_end DESC'
    );
    $followStmt->bind_param('iis', $userId, $userId, $department);
    $followStmt->execute();
    $followResult = $followStmt->get_result();

    while ($row = $followResult->fetch_assoc()) {
        if (!(int) $row['has_followup']) {
            $row['follow_type'] = 'TEA';
            $followupItems[] = $row;
        }
    }
    $followStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: Training Forms</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20260930-50">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-56">
</head>
<body class="app-page task-page" data-page="task" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">

<main class="task-content">
  <div class="task-shell">
    <header class="task-heading">
      <div>
        <div class="task-eyebrow">Smart Training System</div>
        <h1>Training Forms</h1>
        <p>Manage training applications and post training evaluations through the STS workflow.</p>
      </div>
      <div class="task-count-chip">
        <span class="task-count-dot"></span>
        3 forms available
      </div>
    </header>

    <section class="task-grid" aria-label="Training forms">
      <article class="task-form-card<?= $canStaffForms ? '' : ' task-form-card-locked' ?>">
        <div class="task-card-top">
          <span class="task-form-icon"><i class="bi bi-file-earmark-text"></i></span>
          <span class="task-form-code">BPL</span>
        </div>
        <div class="task-form-copy">
          <h2>Permohonan Latihan</h2>
          <p>Submit a training request for review and approval through the STS workflow.</p>
        </div>
        <div class="task-card-footer">
          <span class="task-card-status"><?= $canStaffForms ? '<i class="bi bi-circle-fill"></i> Ready to apply' : '<i class="bi bi-lock-fill"></i> Applicant access' ?></span>
          <?php if ($canStaffForms): ?>
          <a href="bpl.php" class="task-apply-btn">Apply now <i class="bi bi-arrow-up-right"></i></a>
          <?php else: ?>
          <span class="task-apply-btn task-apply-btn-locked"><i class="bi bi-lock-fill"></i> Locked</span>
          <?php endif; ?>
        </div>
      </article>

      <article class="task-form-card<?= $canStaffForms ? '' : ' task-form-card-locked' ?>">
        <div class="task-card-top">
          <span class="task-form-icon"><i class="bi bi-clipboard2-check"></i></span>
          <span class="task-form-code">PKK</span>
        </div>
        <div class="task-form-copy">
          <h2>Penilaian Keberkesanan Kursus</h2>
          <p>Complete the course effectiveness evaluation after attending training.</p>
        </div>
        <div class="task-card-footer">
          <span class="task-card-status"><?= $canStaffForms ? '<i class="bi bi-circle-fill"></i> Ready to apply' : '<i class="bi bi-lock-fill"></i> Applicant access' ?></span>
          <?php if ($canStaffForms): ?>
          <a href="pkk.php" class="task-apply-btn">Apply now <i class="bi bi-arrow-up-right"></i></a>
          <?php else: ?>
          <span class="task-apply-btn task-apply-btn-locked"><i class="bi bi-lock-fill"></i> Locked</span>
          <?php endif; ?>
        </div>
      </article>

      <article class="task-form-card<?= $canTea ? '' : ' task-form-card-locked' ?>">
        <div class="task-card-top">
          <span class="task-form-icon"><i class="bi bi-graph-up-arrow"></i></span>
          <span class="task-form-code">TEA</span>
        </div>
        <div class="task-form-copy">
          <h2>Training Effectiveness Assessment</h2>
          <p>Record post training effectiveness, competency and improvement outcomes.</p>
        </div>
        <div class="task-card-footer">
          <span class="task-card-status"><?= $canTea ? '<i class="bi bi-circle-fill"></i> Ready to apply' : '<i class="bi bi-lock-fill"></i> HoD only' ?></span>
          <?php if ($canTea): ?>
          <a href="tea.php" class="task-apply-btn">Apply now <i class="bi bi-arrow-up-right"></i></a>
          <?php else: ?>
          <span class="task-apply-btn task-apply-btn-locked"><i class="bi bi-lock-fill"></i> Locked</span>
          <?php endif; ?>
        </div>
      </article>
    </section>

    <?php if ($followupItems): ?>
    <section class="task-followup-section">
      <div class="task-followup-heading">
        <div>
          <span>Post training follow up</span>
          <h2>Continue the training lifecycle</h2>
          <p>These approved BPL records are ready for their next required form.</p>
        </div>
        <span class="task-count-chip"><span class="task-count-dot"></span><?= count($followupItems) ?> pending</span>
      </div>

      <div class="task-followup-list">
        <?php foreach ($followupItems as $item): ?>
        <?php
          $followType = (string) $item['follow_type'];
          $due = sts_followup_due((string) $item['training_end'], $followType);
          $overdue = $due && strtotime($due) < time();
          $followUrl = strtolower($followType) . '.php?parent=' . (int) $item['id'];
        ?>
        <article class="task-followup-item<?= $overdue ? ' is-overdue' : '' ?>">
          <span class="task-followup-icon"><i class="bi <?= $followType === 'PKK' ? 'bi-clipboard2-check' : 'bi-graph-up-arrow' ?>"></i></span>
          <div class="task-followup-copy">
            <div><strong><?= e($item['title']) ?></strong><span><?= e($item['application_no']) ?></span></div>
            <p><?= e($followType) ?> required · Training ended <?= e(date('d M Y', strtotime((string) $item['training_end']))) ?></p>
          </div>
          <span class="task-followup-due<?= $overdue ? ' overdue' : '' ?>">
            <?= $overdue ? 'Overdue' : 'Due ' . e(date('d M Y', strtotime((string) $due))) ?>
          </span>
          <a class="task-apply-btn" href="<?= e($followUrl) ?>">Complete <?= e($followType) ?> <i class="bi bi-arrow-up-right"></i></a>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>

<script src="sedco-shell.js?v=20260930-56"></script>
</body>
</html>