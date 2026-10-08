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
$teaEvaluationMonth = (int) (
    new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'))
)->format('n');
$teaEvaluationOpen = in_array($teaEvaluationMonth, [1, 6, 7, 12], true);
$canTea = $role === 'head_of_department' && $teaEvaluationOpen;
$canPkk = false;

$userId = (int) ($user['id'] ?? 0);
$pendingPkkRequirement = sts_pending_pkk_requirement($userId);
$canNewBpl = $canStaffForms && $pendingPkkRequirement === null;
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
           AND b.training_end <= CURDATE()
         ORDER BY b.training_end DESC'
    );
    $ownStmt->bind_param('i', $userId);
    $ownStmt->execute();
    $ownResult = $ownStmt->get_result();

    while ($row = $ownResult->fetch_assoc()) {
        if (!(int) $row['has_followup']) {
            $canPkk = true;
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
                  WHERE t.parent_application_id = b.id
                    AND t.form_type = "TEA"
                    AND t.status <> "cancelled"
                ) AS has_followup
         FROM applications b
         INNER JOIN users u ON u.id = b.user_id
         WHERE b.form_type = "BPL"
           AND b.status = "approved"
           AND b.training_end IS NOT NULL
           AND b.training_end <= CURDATE()
           AND b.user_id <> ?
           AND LOWER(TRIM(COALESCE(NULLIF(b.department, ""), NULLIF(u.department, "")))) = LOWER(TRIM(?))
           AND EXISTS (
             SELECT 1 FROM applications p
             WHERE p.parent_application_id = b.id
               AND p.form_type = "PKK"
               AND p.status = "approved"
               AND p.current_stage = "completed"
           )
         ORDER BY b.training_end DESC'
    );
    $followStmt->bind_param('is', $userId, $department);
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
  <link rel="stylesheet" href="sedco-saas.css?v=20261007-04">
  <link rel="stylesheet" href="sedco-shell.css?v=20261008-05">
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

    <?php if ($pendingPkkRequirement): ?>
    <div class="task-pkk-gate<?= !empty($pendingPkkRequirement['is_overdue']) ? ' is-overdue' : '' ?>">
      <span class="task-pkk-gate-icon"><i class="bi bi-clipboard2-check"></i></span>
      <div>
        <strong>Complete PKK before submitting a new BPL</strong>
        <p>
          <?= e((string) $pendingPkkRequirement['application_no']) ?> ·
          <?= e((string) $pendingPkkRequirement['title']) ?>
          <?php if (!empty($pendingPkkRequirement['pkk_due_at'])): ?>
          · PKK due <?= e(date('d M Y', strtotime((string) $pendingPkkRequirement['pkk_due_at']))) ?>
          <?php endif; ?>
        </p>
      </div>
      <a href="pkk.php?parent=<?= (int) $pendingPkkRequirement['id'] ?>">
        Fill PKK <i class="bi bi-arrow-up-right"></i>
      </a>
    </div>
    <?php endif; ?>

    <section class="task-grid" aria-label="Training forms">
      <article class="task-form-card<?= $canNewBpl ? '' : ' task-form-card-locked' ?>">
        <div class="task-card-top">
          <span class="task-form-icon"><i class="bi bi-file-earmark-text"></i></span>
          <span class="task-form-code">BPL</span>
        </div>
        <div class="task-form-copy">
          <h2>Permohonan Latihan</h2>
          <p>Submit a training request for review and approval through the STS workflow.</p>
        </div>
        <div class="task-card-footer">
          <span class="task-card-status">
            <?= $canNewBpl
              ? '<i class="bi bi-circle-fill"></i> Ready to apply'
              : ($pendingPkkRequirement
                  ? '<i class="bi bi-lock-fill"></i> Complete PKK first'
                  : '<i class="bi bi-lock-fill"></i> Applicant access') ?>
          </span>
          <?php if ($canNewBpl): ?>
          <a href="bpl.php" class="task-apply-btn">Apply now <i class="bi bi-arrow-up-right"></i></a>
          <?php elseif ($pendingPkkRequirement): ?>
          <a href="pkk.php?parent=<?= (int) $pendingPkkRequirement['id'] ?>" class="task-apply-btn">
            Fill PKK <i class="bi bi-arrow-up-right"></i>
          </a>
          <?php else: ?>
          <span class="task-apply-btn task-apply-btn-locked"><i class="bi bi-lock-fill"></i> Locked</span>
          <?php endif; ?>
        </div>
      </article>

      <article class="task-form-card<?= $canPkk ? '' : ' task-form-card-locked' ?>">
        <div class="task-card-top">
          <span class="task-form-icon"><i class="bi bi-clipboard2-check"></i></span>
          <span class="task-form-code">PKK</span>
        </div>
        <div class="task-form-copy">
          <h2>Penilaian Keberkesanan Kursus</h2>
          <p>Complete PKK only for your approved BPL training after the course has ended.</p>
        </div>
        <div class="task-card-footer">
          <span class="task-card-status"><?= $canPkk ? '<i class="bi bi-circle-fill"></i> Approved BPL available' : '<i class="bi bi-lock-fill"></i> Requires approved BPL' ?></span>
          <?php if ($canPkk): ?>
          <a href="pkk.php" class="task-apply-btn">Fill PKK <i class="bi bi-arrow-up-right"></i></a>
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
          <span class="task-card-status"><?= $canTea ? '<i class="bi bi-circle-fill"></i> Ready to apply' : ($role === 'head_of_department' ? '<i class="bi bi-calendar-x"></i> Available Jan, Jun, Jul & Dec' : '<i class="bi bi-lock-fill"></i> HoD only') ?></span>
          <?php if ($canTea): ?>
          <a href="tea.php" class="task-apply-btn">Apply now <i class="bi bi-arrow-up-right"></i></a>
          <?php else: ?>
          <span class="task-apply-btn task-apply-btn-locked"><i class="bi bi-lock-fill"></i> <?= $role === 'head_of_department' ? 'Evaluation closed' : 'Locked' ?></span>
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
          <?php if ($followType === 'TEA' && !$teaEvaluationOpen): ?>
          <span class="task-apply-btn task-apply-btn-locked"><i class="bi bi-calendar-x"></i> Available Jan, Jun, Jul & Dec</span>
          <?php else: ?>
          <a class="task-apply-btn" href="<?= e($followUrl) ?>">Complete <?= e($followType) ?> <i class="bi bi-arrow-up-right"></i></a>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>

<script src="sedco-shell.js?v=20261008-08"></script>
</body>
</html>