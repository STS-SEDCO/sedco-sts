<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

sts_ensure_followup_notifications($user);

$db = db();
$userId = (int) $user['id'];

sts_repair_pending_bpl_stages();
$role = normalized_role($user['role'] ?? '');
$department = trim((string) ($user['department'] ?? ''));

$stats = [
    ['label' => 'Total', 'value' => 0, 'icon' => 'bi-files', 'tone' => 'neutral'],
    ['label' => 'Pending', 'value' => 0, 'icon' => 'bi-clock-history', 'tone' => 'warning'],
    ['label' => 'Approved', 'value' => 0, 'icon' => 'bi-check2-circle', 'tone' => 'success'],
    ['label' => 'Needs attention', 'value' => 0, 'icon' => 'bi-exclamation-circle', 'tone' => 'danger'],
];

$queue = [];
$followups = [];
$calendarEvents = [];
$dashboardTitle = 'Your training workspace';
$dashboardSubtitle = 'Track applications, follow ups and your training schedule.';

if ($role === 'staff') {
    $countStmt = $db->prepare(
        'SELECT
           COUNT(*) AS total,
           SUM(status = "pending") AS pending,
           SUM(status = "approved") AS approved,
           SUM(status IN ("correction","rejected")) AS attention
         FROM applications
         WHERE user_id = ?'
    );
    $countStmt->bind_param('i', $userId);
    $countStmt->execute();
    $counts = $countStmt->get_result()->fetch_assoc() ?: [];
    $countStmt->close();

    $stats[0]['value'] = (int) ($counts['total'] ?? 0);
    $stats[1]['value'] = (int) ($counts['pending'] ?? 0);
    $stats[2]['value'] = (int) ($counts['approved'] ?? 0);
    $stats[3]['value'] = (int) ($counts['attention'] ?? 0);

    $queueStmt = $db->prepare(
        'SELECT application_no, form_type, title, status, current_stage,
                review_note, submitted_at, sla_due_at
         FROM applications
         WHERE user_id = ?
         ORDER BY submitted_at DESC
         LIMIT 6'
    );
    $queueStmt->bind_param('i', $userId);
    $queueStmt->execute();
    $queueResult = $queueStmt->get_result();
    while ($row = $queueResult->fetch_assoc()) $queue[] = $row;
    $queueStmt->close();

    $followStmt = $db->prepare(
        'SELECT b.id, b.application_no, b.title, b.training_end,
                EXISTS(
                  SELECT 1 FROM applications p
                  WHERE p.parent_application_id = b.id AND p.form_type = "PKK"
                ) AS has_pkk
         FROM applications b
         WHERE b.user_id = ?
           AND b.form_type = "BPL"
           AND b.status = "approved"
           AND b.training_end IS NOT NULL
         ORDER BY b.training_end DESC
         LIMIT 8'
    );
    $followStmt->bind_param('i', $userId);
    $followStmt->execute();
    $followResult = $followStmt->get_result();
    while ($row = $followResult->fetch_assoc()) {
        if (!(int) $row['has_pkk']) $followups[] = $row + ['follow_type' => 'PKK'];
    }
    $followStmt->close();
} else {
    $dashboardTitle = match ($role) {
        'head_of_department' => 'Department review workspace',
        'training_section' => 'Training review workspace',
        'general_manager' => 'General Manager approval workspace',
        'pengerusi_besar' => 'Pengerusi approval workspace',
        'finance' => 'Kewangan processing workspace',
        'admin' => 'System overview',
        default => 'Review workspace',
    };

    $dashboardSubtitle = match ($role) {
        'head_of_department' => 'Review your department queue and complete post training assessments.',
        'training_section' => 'Review the Training section before applications continue to the Head of Department.',
        'general_manager' => 'Review applications approved by the Head of Department.',
        'pengerusi_besar' => 'Review applications approved by the General Manager.',
        'finance' => 'Complete the final financial processing for approved training applications.',
        'admin' => 'Monitor users, workflow activity, approvals and system health.',
        default => 'Review applications assigned to your role.',
    };

    $stage = review_stage_for_role($role);

    if ($role === 'admin') {
        $countStmt = $db->prepare(
            'SELECT
               COUNT(*) AS total,
               SUM(status = "pending") AS pending,
               SUM(status = "approved") AS approved,
               SUM(status IN ("correction","rejected")) AS attention
             FROM applications'
        );
        $countStmt->execute();
        $counts = $countStmt->get_result()->fetch_assoc() ?: [];
        $countStmt->close();

        $stats[0] = ['label' => 'Applications', 'value' => (int) ($counts['total'] ?? 0), 'icon' => 'bi-files', 'tone' => 'neutral'];
        $stats[1] = ['label' => 'Pending review', 'value' => (int) ($counts['pending'] ?? 0), 'icon' => 'bi-clock-history', 'tone' => 'warning'];
        $stats[2] = ['label' => 'Approved', 'value' => (int) ($counts['approved'] ?? 0), 'icon' => 'bi-check2-circle', 'tone' => 'success'];

        $userCount = $db->query('SELECT COUNT(*) AS total FROM users WHERE is_active = 1')->fetch_assoc();
        $stats[3] = ['label' => 'Active users', 'value' => (int) ($userCount['total'] ?? 0), 'icon' => 'bi-people', 'tone' => 'brand'];

        $queueResult = $db->query(
            'SELECT application_no, form_type, title, status, current_stage,
                    review_note, submitted_at, sla_due_at
             FROM applications
             WHERE status IN ("pending","correction")
             ORDER BY COALESCE(sla_due_at, submitted_at) ASC
             LIMIT 8'
        );
        while ($row = $queueResult->fetch_assoc()) $queue[] = $row;
    } elseif ($stage !== null) {
        $sqlBase = 'FROM applications
                    WHERE form_type = "BPL"
                      AND current_stage = ?
                      AND status IN ("pending","correction")';

        if ($role === 'head_of_department') {
            $sqlBase .= ' AND (
              assigned_hod_id = ?
              OR (
                assigned_hod_id IS NULL
                AND (department = ? OR department IS NULL OR department = "")
              )
            )';

            $countStmt = $db->prepare(
                'SELECT
                   COUNT(*) AS pending,
                   SUM(sla_due_at IS NOT NULL AND sla_due_at < NOW() AND status = "pending") AS overdue
                 ' . $sqlBase
            );
            $countStmt->bind_param('sis', $stage, $userId, $department);
        } else {
            $countStmt = $db->prepare(
                'SELECT
                   COUNT(*) AS pending,
                   SUM(sla_due_at IS NOT NULL AND sla_due_at < NOW() AND status = "pending") AS overdue
                 ' . $sqlBase
            );
            $countStmt->bind_param('s', $stage);
        }

        $countStmt->execute();
        $counts = $countStmt->get_result()->fetch_assoc() ?: [];
        $countStmt->close();

        $reviewedStmt = $db->prepare(
            'SELECT COUNT(*) AS total
             FROM application_reviews
             WHERE reviewer_id = ?
               AND reviewed_at >= DATE_FORMAT(CURRENT_DATE, "%Y-%m-01")'
        );
        $reviewedStmt->bind_param('i', $userId);
        $reviewedStmt->execute();
        $reviewed = $reviewedStmt->get_result()->fetch_assoc();
        $reviewedStmt->close();

        $approvedStmt = $db->prepare(
            'SELECT COUNT(*) AS total
             FROM application_reviews
             WHERE reviewer_id = ?
               AND decision = "approved"'
        );
        $approvedStmt->bind_param('i', $userId);
        $approvedStmt->execute();
        $approved = $approvedStmt->get_result()->fetch_assoc();
        $approvedStmt->close();

        $stats[0] = ['label' => 'My queue', 'value' => (int) ($counts['pending'] ?? 0), 'icon' => 'bi-inbox', 'tone' => 'neutral'];
        $stats[1] = ['label' => 'Overdue SLA', 'value' => (int) ($counts['overdue'] ?? 0), 'icon' => 'bi-alarm', 'tone' => 'danger'];
        $stats[2] = ['label' => 'Reviewed this month', 'value' => (int) ($reviewed['total'] ?? 0), 'icon' => 'bi-calendar-check', 'tone' => 'success'];
        $stats[3] = ['label' => 'Approved by me', 'value' => (int) ($approved['total'] ?? 0), 'icon' => 'bi-check2-circle', 'tone' => 'brand'];

        $queueSql = 'SELECT application_no, form_type, title, status, current_stage,
                            review_note, submitted_at, sla_due_at
                     ' . $sqlBase . '
                     ORDER BY
                       CASE WHEN sla_due_at IS NOT NULL AND sla_due_at < NOW() THEN 0 ELSE 1 END,
                       COALESCE(sla_due_at, submitted_at) ASC
                     LIMIT 8';

        $queueStmt = $db->prepare($queueSql);

        if ($role === 'head_of_department') {
            $queueStmt->bind_param('sis', $stage, $userId, $department);
        } else {
            $queueStmt->bind_param('s', $stage);
        }

        $queueStmt->execute();
        $queueResult = $queueStmt->get_result();
        while ($row = $queueResult->fetch_assoc()) $queue[] = $row;
        $queueStmt->close();

        if ($role === 'head_of_department') {
            $followStmt = $db->prepare(
                'SELECT b.id, b.application_no, b.title, b.training_end, b.department,
                        EXISTS(
                          SELECT 1 FROM applications t
                          WHERE t.parent_application_id = b.id AND t.form_type = "TEA"
                        ) AS has_tea
                 FROM applications b
                 WHERE b.form_type = "BPL"
                   AND b.status = "approved"
                   AND (
                     b.assigned_hod_id = ?
                     OR (
                       b.assigned_hod_id IS NULL
                       AND (b.department = ? OR b.department IS NULL OR b.department = "")
                     )
                   )
                 ORDER BY b.training_end DESC
                 LIMIT 10'
            );
            $followStmt->bind_param('is', $userId, $department);
            $followStmt->execute();
            $followResult = $followStmt->get_result();

            while ($row = $followResult->fetch_assoc()) {
                if (!(int) $row['has_tea']) $followups[] = $row + ['follow_type' => 'TEA'];
            }

            $followStmt->close();
        }
    }
}

$calendarStmt = $db->prepare(
    'SELECT application_no, title, training_start, training_end
     FROM applications
     WHERE form_type = "BPL"
       AND status = "approved"
       AND training_start IS NOT NULL
       AND (? = "admin" OR user_id = ? OR ? <> "staff")
     ORDER BY training_start ASC
     LIMIT 100'
);
$calendarStmt->bind_param('sis', $role, $userId, $role);
$calendarStmt->execute();
$calendarResult = $calendarStmt->get_result();

while ($row = $calendarResult->fetch_assoc()) {
    $calendarEvents[] = [
        'date' => $row['training_start'],
        'endDate' => $row['training_end'],
        'title' => $row['title'],
        'ref' => $row['application_no'],
        'type' => 'training',
    ];
}
$calendarStmt->close();

$manualEvents = $db->query(
    'SELECT title, event_date, event_type
     FROM calendar_events
     WHERE event_date >= DATE_SUB(CURRENT_DATE, INTERVAL 60 DAY)
       AND event_date <= DATE_ADD(CURRENT_DATE, INTERVAL 365 DAY)
     ORDER BY event_date ASC'
);

while ($row = $manualEvents->fetch_assoc()) {
    $calendarEvents[] = [
        'date' => $row['event_date'],
        'endDate' => $row['event_date'],
        'title' => $row['title'],
        'ref' => '',
        'type' => $row['event_type'],
    ];
}

$unreadNotifications = sts_unread_notifications($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20260930-70">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-56">
</head>
<body class="app-page dashboard-page dashboard-v4" data-page="dashboard" data-role="<?= e($role) ?>">
<main class="content">
  <div class="dashboard-shell">
    <section class="dashboard-hero-v4">
      <div class="dashboard-hero-copy">
        <div class="dashboard-role-chip">
          <span></span>
          <?= e(role_label($user['role'] ?? '')) ?>
        </div>
        <h1>Welcome back, <?= e($user['fullname']) ?></h1>
        <p><?= e($dashboardSubtitle) ?></p>

        <div class="dashboard-hero-actions">
          <a href="<?= $role === 'staff' ? 'task.php' : 'submissions.php' ?>" class="dashboard-primary-action">
            <i class="bi <?= $role === 'staff' ? 'bi-plus-lg' : 'bi-inbox' ?>"></i>
            <?= $role === 'staff' ? 'New application' : 'Open review queue' ?>
          </a>
          <a href="<?= $role === 'staff' ? 'application-status.php' : 'reports.php' ?>" class="dashboard-secondary-action">
            <i class="bi <?= $role === 'staff' ? 'bi-clipboard-check' : 'bi-bar-chart-line' ?>"></i>
            <?= $role === 'staff' ? 'Track application' : 'View reports' ?>
          </a>
          <?php if ($unreadNotifications > 0): ?>
          <a href="notifications.php" class="dashboard-alert-chip">
            <i class="bi bi-bell-fill"></i>
            <?= $unreadNotifications ?> unread
          </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="dashboard-hero-visual" aria-hidden="true">
        <div class="dashboard-orbit dashboard-orbit-one"></div>
        <div class="dashboard-orbit dashboard-orbit-two"></div>
        <div class="dashboard-hero-icon"><i class="bi bi-mortarboard-fill"></i></div>
        <span class="dashboard-floating-chip chip-one"><i class="bi bi-check2-circle"></i> Approval</span>
        <span class="dashboard-floating-chip chip-two"><i class="bi bi-file-earmark-text"></i> Training</span>
      </div>
    </section>

    <section class="dashboard-stats-v4">
      <?php foreach ($stats as $stat): ?>
      <article class="dashboard-stat-v4">
        <span class="dashboard-stat-icon-v4 tone-<?= e($stat['tone']) ?>"><i class="bi <?= e($stat['icon']) ?>"></i></span>
        <div>
          <span class="dashboard-stat-label"><?= e($stat['label']) ?></span>
          <strong><?= (int) $stat['value'] ?></strong>
          <small><?= match ($stat['label']) {
            'Applications', 'Total' => 'Training records in your workspace',
            'Pending', 'Pending review', 'My queue' => 'Waiting for workflow action',
            'Approved', 'Approved by me' => 'Successfully processed records',
            'Needs attention', 'Overdue SLA' => 'Items requiring attention',
            'Reviewed this month' => 'Completed review activity',
            'Active users' => 'Enabled STS accounts',
            default => 'Current workspace activity',
          } ?></small>
        </div>
      </article>
      <?php endforeach; ?>
    </section>

    <div class="dashboard-v4-grid">
      <section class="dashboard-main-card">
        <div class="dashboard-card-heading-v4">
          <div>
            <span class="dashboard-card-kicker"><?= $role === 'staff' ? 'Recent activity' : 'Priority queue' ?></span>
            <h2><?= e($dashboardTitle) ?></h2>
            <p><?= $role === 'staff' ? 'Your latest training records and workflow updates.' : 'Items that need your attention first.' ?></p>
          </div>
          <a href="<?= $role === 'staff' ? 'application-status.php' : 'submissions.php' ?>">
            View all <i class="bi bi-arrow-right"></i>
          </a>
        </div>

        <?php if (!$queue): ?>
        <div class="dashboard-empty-v3">
          <i class="bi bi-check2-circle"></i>
          <strong>Nothing waiting right now</strong>
          <span>Your latest workflow activity will appear here.</span>
        </div>
        <?php else: ?>
        <div class="dashboard-queue">
          <?php foreach ($queue as $item): ?>
          <?php
            $overdue = !empty($item['sla_due_at'])
                && strtotime((string) $item['sla_due_at']) < time()
                && $item['status'] === 'pending';
            $href = $item['form_type'] === 'BPL'
                ? 'application-detail.php?application=' . rawurlencode((string) $item['application_no'])
                : 'application-detail.php?application=' . rawurlencode((string) $item['application_no']);
          ?>
          <a class="dashboard-queue-item<?= $overdue ? ' is-overdue' : '' ?>" href="<?= e($href) ?>">
            <span class="dashboard-queue-icon"><i class="bi bi-file-earmark-text"></i></span>
            <div class="dashboard-queue-copy">
              <div>
                <strong><?= e($item['title']) ?></strong>
                <span><?= e($item['application_no']) ?></span>
              </div>
              <p>
                <?= e($item['form_type']) ?> ·
                <?= e(ucfirst((string) $item['status'])) ?> ·
                <?= e(stage_label((string) $item['current_stage'])) ?>
              </p>
            </div>
            <span class="dashboard-queue-meta">
              <?php if ($overdue): ?><em>Overdue</em><?php endif; ?>
              <i class="bi bi-chevron-right"></i>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>

      <aside class="dashboard-calendar-card-v4">
        <div class="dashboard-card-heading-v4">
          <div><span class="dashboard-card-kicker">Schedule</span><h2>Training calendar</h2><p>Approved training and company events.</p></div>
          <span class="dashboard-calendar-badge"><i class="bi bi-calendar3"></i></span>
        </div>
        <div class="calendar-header" id="calendar-header"></div>
        <table class="calendar-table" id="calendar"></table>
        <div id="selected-date" class="dashboard-calendar-detail"></div>
      </aside>
    </div>

    <?php if ($followups): ?>
    <section class="dashboard-followup-panel">
      <div class="dashboard-card-heading-v4">
        <div>
          <span class="dashboard-card-kicker">Post training</span>
          <h2>Follow up actions</h2>
          <p>Complete the next required training lifecycle step.</p>
        </div>
        <span class="dashboard-followup-count"><?= count($followups) ?> pending</span>
      </div>
      <div class="dashboard-followup-grid">
        <?php foreach ($followups as $item): ?>
        <?php
          $due = sts_followup_due((string) $item['training_end'], (string) $item['follow_type']);
          $late = $due && strtotime($due) < time();
          $url = strtolower((string) $item['follow_type']) . '.php?parent=' . (int) $item['id'];
        ?>
        <article class="dashboard-followup-card<?= $late ? ' is-overdue' : '' ?>">
          <span class="dashboard-followup-type"><?= e($item['follow_type']) ?></span>
          <div>
            <strong><?= e($item['title']) ?></strong>
            <p><?= e($item['application_no']) ?> · Training ended <?= e(date('d M Y', strtotime((string) $item['training_end']))) ?></p>
          </div>
          <div class="dashboard-followup-footer">
            <span><?= $late ? 'Overdue' : 'Due ' . e(date('d M Y', strtotime((string) $due))) ?></span>
            <a href="<?= e($url) ?>">Complete <i class="bi bi-arrow-up-right"></i></a>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>

<script>
window.STS_CALENDAR_EVENTS = <?= json_encode(
    $calendarEvents,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
) ?>;
</script>
<script src="dashboard.js?v=20260930-70"></script>
<script src="sedco-shell.js?v=20260930-70"></script>
</body>
</html>
