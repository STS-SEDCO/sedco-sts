<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();
$role = normalized_role($user['role'] ?? '');

if (!$user || !in_array($role, ['admin', 'training_section'], true)) {
    http_response_code(403);
    exit('This page is available only to System Admin and Training Department.');
}

$db = db();
$summary = ['total' => 0, 'pending' => 0, 'approved' => 0, 'attention' => 0];
$stages = [];
$applications = [];
$events = [];
$members = [];
$errors = [];
$activityPage = max(1, min(100000, (int) ($_GET['activity_page'] ?? 1)));
$activityPageSize = 30;
$activityCount = 0;

try {
    $totals = $db->query(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(status = "pending"),0) AS pending,
                COALESCE(SUM(status = "approved"),0) AS approved,
                COALESCE(SUM(status IN ("correction","rejected")),0) AS attention
         FROM applications WHERE form_type = "BPL"'
    );
    $summary = $totals->fetch_assoc() ?: $summary;

    $stageRows = $db->query(
        'SELECT current_stage,COUNT(*) AS total
         FROM applications
         WHERE form_type = "BPL" AND status = "pending"
         GROUP BY current_stage'
    );
    while ($row = $stageRows->fetch_assoc()) {
        $stages[(string) $row['current_stage']] = (int) $row['total'];
    }

    $applicationRows = $db->query(
        'SELECT a.application_no,a.title,a.form_type,a.status,a.current_stage,
                a.submitted_at,a.sla_due_at,a.department,
                u.fullname AS applicant_name
         FROM applications a
         INNER JOIN users u ON u.id = a.user_id
         WHERE a.form_type = "BPL"
         ORDER BY
           CASE
             WHEN a.status = "pending" AND a.sla_due_at < NOW() THEN 0
             WHEN a.status = "pending" THEN 1 ELSE 2
           END,
           a.submitted_at DESC
         LIMIT 80'
    );
    while ($row = $applicationRows->fetch_assoc()) {
        $applications[] = $row;
    }

    $memberRows = $db->query(
        'SELECT id,fullname,job_title
         FROM users
         WHERE role = "training_section" AND is_active = 1
         ORDER BY fullname ASC'
    );
    while ($row = $memberRows->fetch_assoc()) {
        $members[] = $row;
    }
} catch (Throwable $error) {
    $errors[] = 'Operational information could not be loaded. Please check the database.';
}

try {
    $totalRow = $db->query(
        'SELECT COUNT(*) AS total FROM audit_logs WHERE entity_type = "application"'
    )->fetch_assoc();
    $activityCount = (int) ($totalRow['total'] ?? 0);
    $activityPages = max(1, (int) ceil($activityCount / $activityPageSize));
    $activityPage = min($activityPage, $activityPages);
    $offset = ($activityPage - 1) * $activityPageSize;

    $stmt = $db->prepare(
        'SELECT a.id,a.action,a.entity_id,a.metadata,a.created_at,
                COALESCE(u.fullname,"System") AS actor_name,
                COALESCE(u.role,"system") AS actor_role,
                app.title AS application_title,
                app.form_type
         FROM audit_logs a
         LEFT JOIN users u ON u.id = a.user_id
         LEFT JOIN applications app ON app.application_no = a.entity_id
         WHERE a.entity_type = "application"
         ORDER BY a.created_at DESC,a.id DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bind_param('ii', $activityPageSize, $offset);
    $stmt->execute();
    $eventRows = $stmt->get_result();
    while ($row = $eventRows->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
} catch (Throwable $error) {
    $activityPages = 1;
    $errors[] = 'Activity history could not be loaded. Check the audit_logs table.';
}

function operations_action_label(string $action): string
{
    return match ($action) {
        'application_submitted' => 'Application submitted',
        'application_resubmitted' => 'Application resubmitted',
        'application_approved' => 'Application approved',
        'application_rejected' => 'Application rejected',
        'application_correction' => 'Correction requested',
        'application_cancelled' => 'Application cancelled',
        'pkk_updated' => 'PKK updated',
        default => ucwords(str_replace('_', ' ', $action)),
    };
}

function operations_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'correction' => 'Needs correction',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        default => ucfirst($status),
    };
}

function operations_time(?string $date): string
{
    return $date ? date('d M Y, g:i A', strtotime($date)) : 'Not available';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Smart Training System: Training Operations</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20260930-42">
  <link rel="stylesheet" href="sedco-shell.css?v=20261008-05">
  <style>
    .training-owner-layout {display:grid;grid-template-columns:minmax(0,2fr) minmax(250px,1fr);gap:18px;}
    .training-owner-stats {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;}
    .training-owner-stat {padding:18px 20px;border-radius:16px;background:var(--bs-body-bg,#fff);border:1px solid rgba(122,122,122,.2)}
    .training-owner-stat span {font-size:13px;color:var(--bs-secondary-color,#777);display:block;margin-bottom:8px}
    .training-owner-stat strong {font-size:29px;line-height:1.1}
    .training-owner-table {width:100%;border-collapse:collapse;font-size:13px}
    .training-owner-table th {padding:12px 11px;text-align:left;color:var(--bs-secondary-color,#777);font-weight:600;white-space:nowrap}
    .training-owner-table td {padding:12px 11px;border-top:1px solid rgba(122,122,122,.17);vertical-align:top}
    .training-owner-table td small {display:block;color:var(--bs-secondary-color,#777);margin-top:4px}
    .training-owner-scroll {overflow-x:auto}
    .training-owner-table a {color:inherit;text-decoration:none}
    .training-owner-table a:hover {text-decoration:underline}
    .training-owner-stage {display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px solid rgba(122,122,122,.14)}
    .training-owner-stage:last-child {border:0}
    .training-owner-pill {display:inline-block;padding:5px 9px;border-radius:999px;background:rgba(132,132,132,.12);font-size:12px;white-space:nowrap}
    .training-owner-activity {display:flex;gap:12px;padding:13px 0;border-bottom:1px solid rgba(122,122,122,.14)}
    .training-owner-activity:last-child {border:0}
    .training-owner-activity > i {color:#9b1e3b;font-size:17px;margin-top:2px}
    .training-owner-activity p {margin:4px 0;color:var(--bs-secondary-color,#777);font-size:13px}
    .training-owner-activity small {color:var(--bs-secondary-color,#777)}
    .training-owner-search {max-width:280px;border:1px solid rgba(122,122,122,.3);padding:9px 12px;border-radius:10px;background:transparent;color:inherit}
    .training-owner-heading {display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}
    .training-owner-members {display:flex;flex-wrap:wrap;gap:8px}
    .training-owner-member {padding:8px 11px;border-radius:11px;background:rgba(130,130,130,.1);font-size:13px}
    .training-owner-pager {display:flex;align-items:center;gap:10px;justify-content:flex-end;margin-top:16px}
    .training-owner-pager a {text-decoration:none}
    @media(max-width:1100px){.training-owner-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.training-owner-layout{grid-template-columns:1fr}}
    @media(max-width:520px){.training-owner-stats{gap:9px}.training-owner-stat{padding:13px}.training-owner-stat strong{font-size:24px}.training-owner-stat span{font-size:11px}.training-owner-table{font-size:12px}}
  </style>
</head>
<body class="app-page training-operations-page" data-page="training-operations" data-role="<?= e($role) ?>">
<main class="sts-page-content">
  <div class="sts-page-shell">
    <header class="sts-page-heading">
      <div>
        <div class="sts-eyebrow">Training Department · System owner</div>
        <h1>Training Operations Overview</h1>
        <p>Shared visibility across applications, decisions and workflow activity. Approval authority stays with each assigned role.</p>
      </div>
      <div class="sts-heading-actions">
        <a class="sts-secondary-btn" href="approval-history.php"><i class="bi bi-clock-history"></i> All approval decisions</a>
        <a class="sts-primary-btn" href="submissions.php"><i class="bi bi-check2-square"></i> My approval queue</a>
      </div>
    </header>
    <?php foreach ($errors as $error): ?>
      <div class="alert alert-warning" role="alert"><?= e($error) ?></div>
    <?php endforeach; ?>
    <section class="training-owner-stats mb-4" aria-label="Organisation overview">
      <?php foreach ([
        ['All BPL','total'],
        ['Pending','pending'],
        ['Approved','approved'],
        ['Needs attention','attention']
      ] as [$label,$key]): ?>
      <article class="training-owner-stat">
        <span><?= e($label) ?></span><strong><?= (int) ($summary[$key] ?? 0) ?></strong>
      </article>
      <?php endforeach; ?>
    </section>
    <div class="training-owner-layout mb-4">
      <section class="sts-card">
        <div class="training-owner-heading">
          <div><span class="sts-eyebrow">End to end</span><h2>All approval stages</h2></div>
          <a href="approval-history.php">View decisions <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php foreach ([
          'training'=>'Training',
          'hod'=>'Head of Department',
          'gm'=>'General Manager',
          'chairman'=>'Pengerusi',
          'finance'=>'Kewangan'
        ] as $stage => $label): ?>
          <div class="training-owner-stage"><span><?= e($label) ?></span>
            <span class="training-owner-pill"><?= (int) ($stages[$stage] ?? 0) ?> pending</span>
          </div>
        <?php endforeach; ?>
        <div class="training-owner-stage"><span>Completed</span><span class="training-owner-pill"><?= (int) ($summary['approved'] ?? 0) ?> approved</span></div>
      </section>
      <aside class="sts-card">
        <div class="training-owner-heading"><div><span class="sts-eyebrow">Shared team</span><h2>Training Department</h2></div></div>
        <p>Both Training officers see the same operational records. Every decision keeps the name of the officer who performed it.</p>
        <div class="training-owner-members">
          <?php foreach ($members as $member): ?>
          <span class="training-owner-member"><i class="bi bi-person-check"></i> <?= e((string) $member['fullname']) ?></span>
          <?php endforeach; ?>
          <?php if (!$members): ?><span class="text-muted">No active Training accounts are assigned yet.</span><?php endif; ?>
        </div>
      </aside>
    </div>
    <section class="sts-card mb-4">
      <div class="training-owner-heading">
        <div><span class="sts-eyebrow">Organisation queue</span><h2>Applications across all stages</h2>
          <p class="mb-0">Latest 80 BPL applications, with overdue items first. Use the application detail to see each full timeline.</p>
        </div>
        <input class="training-owner-search" id="trainingOwnerSearch" type="search" placeholder="Search reference, staff, department..." aria-label="Search listed applications">
      </div>
      <div class="training-owner-scroll">
        <table class="training-owner-table">
          <thead><tr><th>Reference / Course</th><th>Applicant</th><th>Department</th><th>Current stage</th><th>Status</th><th>Submitted</th></tr></thead>
          <tbody>
          <?php foreach ($applications as $application): ?>
            <tr data-training-owner-row>
              <td><a href="application-detail.php?application=<?= rawurlencode((string) $application['application_no']) ?>"><strong><?= e((string)$application['application_no']) ?></strong><small><?= e((string)$application['title']) ?></small></a></td>
              <td><?= e((string)$application['applicant_name']) ?></td>
              <td><?= e((string)($application['department'] ?: 'Unassigned')) ?></td>
              <td><?= e(stage_label((string)$application['current_stage'])) ?></td>
              <td><span class="training-owner-pill"><?= e(operations_status_label((string)$application['status'])) ?></span>
              <?php if ($application['status'] === 'pending' && !empty($application['sla_due_at']) && strtotime((string)$application['sla_due_at']) < time()): ?><small class="text-danger">Overdue</small><?php endif; ?></td>
              <td><?= e(operations_time((string)$application['submitted_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$applications): ?><tr><td colspan="6">No applications found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="mt-3"><a href="reports.php">Explore all records in Reports & Analytics <i class="bi bi-arrow-up-right"></i></a></div>
    </section>
    <section class="sts-card">
      <div class="training-owner-heading">
        <div><span class="sts-eyebrow">Accountable decisions</span><h2>Shared activity history</h2>
          <p class="mb-0">Actions from all workflow stages, including changes made by either Training officer.</p>
        </div>
        <span class="training-owner-pill"><?= $activityCount ?> logged actions</span>
      </div>
      <?php foreach ($events as $event): ?>
        <?php
          $metadata = json_decode((string) ($event['metadata'] ?? ''), true);
          $metadata = is_array($metadata) ? $metadata : [];
          $stage = (string)($metadata['stage'] ?? '');
          $detail = $stage !== '' ? stage_label($stage) : '';
        ?>
        <div class="training-owner-activity">
          <i class="bi bi-clock-history" aria-hidden="true"></i>
          <div>
            <strong><?= e(operations_action_label((string)$event['action'])) ?></strong>
            <p>
              <b><?= e((string)$event['actor_name']) ?></b>
              <?php if ($detail !== ''): ?> · <?= e($detail) ?><?php endif; ?>
              <?php if (!empty($event['entity_id'])): ?> ·
                <a href="application-detail.php?application=<?= rawurlencode((string)$event['entity_id']) ?>"><?= e((string)$event['entity_id']) ?></a>
              <?php endif; ?>
              <?php if (!empty($event['application_title'])): ?> · <?= e((string)$event['application_title']) ?><?php endif; ?>
            </p>
            <small><?= e(operations_time((string)$event['created_at'])) ?></small>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$events): ?><p class="text-muted mb-0">No application actions have been recorded yet.</p><?php endif; ?>
      <?php if ($activityPages > 1): ?>
      <nav class="training-owner-pager" aria-label="Activity pages">
        <?php if ($activityPage > 1): ?><a class="sts-secondary-btn" href="?activity_page=<?= $activityPage-1 ?>">Previous</a><?php endif; ?>
        <span>Page <?= $activityPage ?> of <?= $activityPages ?></span>
        <?php if ($activityPage < $activityPages): ?><a class="sts-secondary-btn" href="?activity_page=<?= $activityPage+1 ?>">Next</a><?php endif; ?>
      </nav>
      <?php endif; ?>
    </section>
  </div>
</main>
<script>
(() => {
  const search = document.getElementById('trainingOwnerSearch');
  const rows = Array.from(document.querySelectorAll('[data-training-owner-row]'));
  search?.addEventListener('input', () => {
    const query = search.value.trim().toLocaleLowerCase();
    rows.forEach(row => { row.hidden = !row.textContent.toLocaleLowerCase().includes(query); });
  });
})();
</script>
<script src="sedco-shell.js?v=20261012-01"></script>
</body>
</html>
