<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user || normalized_role($user['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = db();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (($_POST['action'] ?? '') === 'safe_repair') {
        sts_repair_pending_bpl_stages();

        try {
            $db->query(
                'UPDATE applications a
                 INNER JOIN departments d
                   ON d.name = a.department
                  AND d.is_active = 1
                 SET a.assigned_hod_id = d.hod_user_id
                 WHERE a.form_type = "BPL"
                   AND a.assigned_hod_id IS NULL
                   AND d.hod_user_id IS NOT NULL'
            );

            $db->query(
                'UPDATE applications
                 SET followup_due_at = DATE_ADD(
                       CONCAT(training_end, " 23:59:59"),
                       INTERVAL 7 DAY
                     )
                 WHERE form_type = "BPL"
                   AND status = "approved"
                   AND training_end IS NOT NULL
                   AND followup_due_at IS NULL'
            );
        } catch (Throwable) {
            // Individual checks below will show anything that still needs attention.
        }

        sts_audit(
            'system_health_safe_repair',
            'system',
            'health',
            ['source' => 'admin-system-health'],
            (int) $user['id']
        );

        $message = 'Safe repair completed. Workflow stages, HOD routing and PKK due dates were checked again.';
    }
}

function health_count(mysqli $db, string $sql): int
{
    try {
        $row = $db->query($sql)->fetch_assoc();
        return (int) ($row['total'] ?? 0);
    } catch (Throwable) {
        return -1;
    }
}

$checks = [
    [
        'key' => 'routing',
        'label' => 'BPL without HOD routing',
        'description' => 'BPL records that may not know which HOD should receive the application.',
        'count' => health_count(
            $db,
            'SELECT COUNT(*) AS total
             FROM applications a
             LEFT JOIN departments d ON d.name = a.department AND d.is_active = 1
             WHERE a.form_type = "BPL"
               AND a.status IN ("pending","approved")
               AND a.department IS NOT NULL
               AND a.department <> ""
               AND a.assigned_hod_id IS NULL
               AND (d.hod_user_id IS NULL OR d.hod_user_id = 0)'
        ),
        'icon' => 'bi-diagram-3',
        'severity' => 'warning',
    ],
    [
        'key' => 'departments',
        'label' => 'Departments without HOD',
        'description' => 'Active department records with no HOD assigned.',
        'count' => health_count(
            $db,
            'SELECT COUNT(*) AS total
             FROM departments
             WHERE is_active = 1
               AND hod_user_id IS NULL'
        ),
        'icon' => 'bi-building-exclamation',
        'severity' => 'warning',
    ],
    [
        'key' => 'orphan',
        'label' => 'Orphan PKK / TEA records',
        'description' => 'Follow-up forms whose linked BPL no longer exists or was not recorded.',
        'count' => health_count(
            $db,
            'SELECT COUNT(*) AS total
             FROM applications f
             LEFT JOIN applications b ON b.id = f.parent_application_id
             WHERE f.form_type IN ("PKK","TEA")
               AND (f.parent_application_id IS NULL OR b.id IS NULL)'
        ),
        'icon' => 'bi-link-45deg',
        'severity' => 'danger',
    ],
    [
        'key' => 'invalid_training_dates',
        'label' => 'Invalid training dates',
        'description' => 'BPL records where the training end date is earlier than the start date.',
        'count' => health_count(
            $db,
            'SELECT COUNT(*) AS total
             FROM applications
             WHERE form_type = "BPL"
               AND training_start IS NOT NULL
               AND training_end IS NOT NULL
               AND training_end < training_start'
        ),
        'icon' => 'bi-calendar2-x',
        'severity' => 'danger',
    ],
    [
        'key' => 'schedule_conflicts',
        'label' => 'Training schedule conflicts',
        'description' => 'Active BPL pairs for the same employee whose training dates overlap.',
        'count' => health_count(
            $db,
            'SELECT COUNT(*) AS total
             FROM applications a
             INNER JOIN applications b
               ON b.user_id = a.user_id
              AND b.id > a.id
              AND b.form_type = "BPL"
              AND b.status NOT IN ("rejected","cancelled")
              AND b.training_start IS NOT NULL
              AND b.training_end IS NOT NULL
              AND b.training_start <= a.training_end
              AND b.training_end >= a.training_start
             WHERE a.form_type = "BPL"
               AND a.status NOT IN ("rejected","cancelled")
               AND a.training_start IS NOT NULL
               AND a.training_end IS NOT NULL'
        ),
        'icon' => 'bi-calendar-week',
        'severity' => 'warning',
    ],
    [
        'key' => 'overdue_approval',
        'label' => 'Overdue approvals',
        'description' => 'Pending BPL records that have passed their reviewer SLA.',
        'count' => health_count(
            $db,
            'SELECT COUNT(*) AS total
             FROM applications
             WHERE form_type = "BPL"
               AND status = "pending"
               AND sla_due_at IS NOT NULL
               AND sla_due_at < NOW()'
        ),
        'icon' => 'bi-alarm',
        'severity' => 'warning',
    ],
    [
        'key' => 'pkk_required',
        'label' => 'PKK waiting',
        'description' => 'Approved training that has ended but still has no active PKK submission.',
        'count' => health_count(
            $db,
            'SELECT COUNT(*) AS total
             FROM applications b
             WHERE b.form_type = "BPL"
               AND b.status = "approved"
               AND b.current_stage = "completed"
               AND b.training_end IS NOT NULL
               AND b.training_end <= CURDATE()
               AND NOT EXISTS (
                 SELECT 1
                 FROM applications p
                 WHERE p.parent_application_id = b.id
                   AND p.form_type = "PKK"
                   AND p.status <> "cancelled"
               )'
        ),
        'icon' => 'bi-clipboard2-check',
        'severity' => 'info',
    ],
    [
        'key' => 'pkk_overdue',
        'label' => 'PKK overdue',
        'description' => 'Training ended more than 7 days ago and PKK is still incomplete.',
        'count' => health_count(
            $db,
            'SELECT COUNT(*) AS total
             FROM applications b
             WHERE b.form_type = "BPL"
               AND b.status = "approved"
               AND b.current_stage = "completed"
               AND b.training_end IS NOT NULL
               AND DATE_ADD(CONCAT(b.training_end, " 23:59:59"), INTERVAL 7 DAY) < NOW()
               AND NOT EXISTS (
                 SELECT 1
                 FROM applications p
                 WHERE p.parent_application_id = b.id
                   AND p.form_type = "PKK"
                   AND p.status <> "cancelled"
               )'
        ),
        'icon' => 'bi-exclamation-octagon',
        'severity' => 'danger',
    ],
    [
        'key' => 'email',
        'label' => 'Failed email queue',
        'description' => 'Optional outgoing email notifications that failed to send.',
        'count' => health_count(
            $db,
            'SELECT COUNT(*) AS total
             FROM email_queue
             WHERE status = "failed"'
        ),
        'icon' => 'bi-envelope-exclamation',
        'severity' => 'info',
    ],
];

$critical = 0;
$warning = 0;
$unknown = 0;

foreach ($checks as $check) {
    if ($check['count'] < 0) {
        $unknown++;
        continue;
    }

    if ($check['count'] > 0 && $check['severity'] === 'danger') {
        $critical += $check['count'];
    } elseif ($check['count'] > 0 && $check['severity'] === 'warning') {
        $warning += $check['count'];
    }
}

$overallClass = $critical > 0 ? 'danger' : ($warning > 0 ? 'warning' : ($unknown > 0 ? 'warning' : 'success'));
$overallTitle = $critical > 0
    ? 'Action required'
    : ($warning > 0 ? 'System is running with items to review' : ($unknown > 0 ? 'Some checks are unavailable' : 'System looks healthy'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: System Health</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261007-16">
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-15">
</head>
<body class="app-page admin-page system-health-page" data-page="admin-settings" data-role="admin">
<main class="sts-page-content">
  <div class="sts-page-shell">
    <header class="sts-page-heading">
      <div>
        <div class="sts-eyebrow">Administration</div>
        <h1>System Health</h1>
        <p>Quickly detect workflow, routing, PKK follow-up and data integrity issues.</p>
      </div>
      <div class="sts-heading-actions">
        <a class="sts-secondary-btn" href="admin-settings.php">
          <i class="bi bi-sliders"></i> System settings
        </a>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="safe_repair">
          <button class="sts-primary-btn" type="submit">
            <i class="bi bi-wrench-adjustable-circle"></i> Run safe repair
          </button>
        </form>
      </div>
    </header>

    <?php if ($message): ?>
    <div class="sts-alert success"><i class="bi bi-check-circle"></i><?= e($message) ?></div>
    <?php endif; ?>

    <section class="health-overview is-<?= e($overallClass) ?>">
      <span class="health-overview-icon">
        <i class="bi <?= $overallClass === 'success' ? 'bi-check2-circle' : ($overallClass === 'danger' ? 'bi-exclamation-octagon' : 'bi-exclamation-triangle') ?>"></i>
      </span>
      <div>
        <small>Current status</small>
        <strong><?= e($overallTitle) ?></strong>
        <p>Safe repair never deletes applications. It only repairs pending workflow stages, fills missing HOD routing when a department mapping exists, and restores missing 7-day PKK due dates.</p>
      </div>
      <em><?= date('d M Y, g:i A') ?></em>
    </section>

    <section class="health-grid">
      <?php foreach ($checks as $check): ?>
      <?php
        $count = (int) $check['count'];
        $state = $count < 0 ? 'unknown' : ($count === 0 ? 'success' : $check['severity']);
      ?>
      <article class="health-card is-<?= e($state) ?>">
        <span class="health-card-icon"><i class="bi <?= e($check['icon']) ?>"></i></span>
        <div>
          <span><?= e($check['label']) ?></span>
          <strong><?= $count < 0 ? '—' : $count ?></strong>
          <p><?= e($check['description']) ?></p>
        </div>
        <em><?= $count < 0 ? 'Check unavailable' : ($count === 0 ? 'Good' : 'Review') ?></em>
      </article>
      <?php endforeach; ?>
    </section>

    <section class="sts-card health-guide">
      <div class="sts-card-heading">
        <div><span>What to do</span><h2>Recommended admin checks</h2></div>
        <i class="bi bi-list-check"></i>
      </div>
      <div class="health-guide-grid">
        <div><strong>1. Routing</strong><p>Assign a HOD to every active department in System Settings.</p></div>
        <div><strong>2. Approval SLA</strong><p>Use Approval to clear overdue items. Overdue records remain visible until reviewed.</p></div>
        <div><strong>3. PKK compliance</strong><p>Staff with incomplete PKK are automatically blocked from submitting a new BPL.</p></div>
        <div><strong>4. Data integrity</strong><p>Review invalid dates and orphan PKK/TEA records before changing any data manually.</p></div>
        <div><strong>5. Schedule conflicts</strong><p>Check overlapping training records to avoid duplicate or conflicting BPL schedules.</p></div>
      </div>
    </section>
  </div>
</main>
<script src="sedco-shell.js?v=20261007-05"></script>
</body>
</html>
