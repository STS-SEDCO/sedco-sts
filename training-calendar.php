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
$role = normalized_role($user['role'] ?? '');
$userId = (int) ($user['id'] ?? 0);
$userDepartment = trim((string) ($user['department'] ?? ''));

$month = trim((string) ($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/',$month)) {
    $month = date('Y-m');
}

$monthStart = new DateTimeImmutable($month . '-01',new DateTimeZone('Asia/Kuala_Lumpur'));
$monthEnd = $monthStart->modify('+1 month');
$gridStart = $monthStart->modify('-' . ((int)$monthStart->format('N') - 1) . ' days');
$gridEnd = $monthEnd->modify('+' . (7 - (int)$monthEnd->modify('-1 day')->format('N')) . ' days');

$filterDepartment = trim((string) ($_GET['department'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$where = [
    'a.form_type = "BPL"',
    'a.status = "approved"',
    'a.training_start IS NOT NULL',
    'a.training_end IS NOT NULL',
    'a.training_start < ?',
    'a.training_end >= ?',
];
$params = [$monthEnd->format('Y-m-d'),$monthStart->format('Y-m-d')];
$types = 'ss';

if ($role === 'staff') {
    $where[] = 'a.user_id = ?';
    $params[] = $userId;
    $types .= 'i';
} elseif ($role === 'head_of_department') {
    $where[] = 'LOWER(TRIM(COALESCE(NULLIF(a.department,""),NULLIF(u.department,"")))) = LOWER(TRIM(?))';
    $params[] = $userDepartment;
    $types .= 's';
} elseif ($filterDepartment !== '') {
    $where[] = 'COALESCE(NULLIF(a.department,""),NULLIF(u.department,"")) = ?';
    $params[] = $filterDepartment;
    $types .= 's';
}

if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(a.title LIKE ? OR u.fullname LIKE ? OR a.application_no LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$sql =
    'SELECT a.id,a.application_no,a.title,a.department,a.training_start,a.training_end,
            a.user_id,u.fullname,
            COALESCE(NULLIF(a.department,""),NULLIF(u.department,"")) AS resolved_department
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     WHERE ' . implode(' AND ',$where) . '
     ORDER BY a.training_start ASC,u.fullname ASC';

$stmt = $db->prepare($sql);
$bind = [$types];
foreach ($params as $index => $value) {
    $bind[] = &$params[$index];
}
call_user_func_array([$stmt,'bind_param'],$bind);
$stmt->execute();
$result = $stmt->get_result();

$trainings = [];
while ($row = $result->fetch_assoc()) {
    $row['department'] = (string) ($row['resolved_department'] ?? $row['department'] ?? '');
    $trainings[] = $row;
}
$stmt->close();

$companyEvents = [];
$eventStmt = $db->prepare(
    'SELECT id,title,event_date,event_type,description
     FROM calendar_events
     WHERE event_date >= ?
       AND event_date < ?
     ORDER BY event_date ASC,title ASC'
);
$startValue = $monthStart->format('Y-m-d');
$endValue = $monthEnd->format('Y-m-d');
$eventStmt->bind_param('ss',$startValue,$endValue);
$eventStmt->execute();
$eventResult = $eventStmt->get_result();
while ($row = $eventResult->fetch_assoc()) {
    $companyEvents[] = $row;
}
$eventStmt->close();

$departments = [];
if (!in_array($role,['staff','head_of_department'],true)) {
    $deptResult = $db->query(
        'SELECT DISTINCT department
         FROM applications
         WHERE form_type = "BPL"
           AND department IS NOT NULL
           AND department <> ""
         ORDER BY department ASC'
    );
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = (string) $row['department'];
    }
}

$eventsByDay = [];

foreach ($trainings as $training) {
    $start = new DateTimeImmutable((string)$training['training_start']);
    $end = new DateTimeImmutable((string)$training['training_end']);

    for ($date=$start; $date <= $end; $date=$date->modify('+1 day')) {
        if ($date < $monthStart || $date >= $monthEnd) continue;
        $eventsByDay[$date->format('Y-m-d')][] = [
            'kind'=>'training',
            'title'=>(string)$training['title'],
            'applicant'=>(string)$training['fullname'],
            'department'=>(string)($training['department'] ?: 'Unassigned'),
            'application_no'=>(string)$training['application_no'],
            'url'=>'application-detail.php?application=' . rawurlencode((string)$training['application_no']),
        ];
    }
}

foreach ($companyEvents as $event) {
    $eventsByDay[(string)$event['event_date']][] = [
        'kind'=>'company',
        'title'=>(string)$event['title'],
        'applicant'=>'Company event',
        'department'=>'SEDCO',
        'application_no'=>'',
        'url'=>'#',
    ];
}

$today = (new DateTimeImmutable('now',new DateTimeZone('Asia/Kuala_Lumpur')))->format('Y-m-d');
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: Training Calendar</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261007-16">
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-03">
</head>
<body class="app-page training-calendar-page" data-page="training-calendar" data-role="<?= e($role) ?>">
<main class="sts-page-content">
  <div class="sts-page-shell">
    <header class="sts-page-heading">
      <div>
        <div class="sts-eyebrow">Organisation schedule</div>
        <h1>Training Calendar</h1>
        <p>See approved training schedules, staff attendance and company events in one monthly view.</p>
      </div>
      <a class="sts-secondary-btn" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    </header>

    <form class="training-calendar-toolbar" method="get">
      <div class="training-calendar-nav">
        <a href="?month=<?= e($previousMonth) ?><?= $filterDepartment !== '' ? '&department='.rawurlencode($filterDepartment) : '' ?>"><i class="bi bi-chevron-left"></i></a>
        <label><span>Month</span><input type="month" name="month" value="<?= e($month) ?>" onchange="this.form.submit()"></label>
        <a href="?month=<?= e($nextMonth) ?><?= $filterDepartment !== '' ? '&department='.rawurlencode($filterDepartment) : '' ?>"><i class="bi bi-chevron-right"></i></a>
      </div>

      <?php if (!in_array($role,['staff','head_of_department'],true)): ?>
      <label class="training-calendar-filter">
        <span>Department</span>
        <select name="department" onchange="this.form.submit()">
          <option value="">All departments</option>
          <?php foreach ($departments as $department): ?>
          <option value="<?= e($department) ?>" <?= $department === $filterDepartment ? 'selected' : '' ?>><?= e($department) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>

      <label class="training-calendar-search">
        <span>Search</span>
        <div><i class="bi bi-search"></i><input type="search" name="q" value="<?= e($search) ?>" placeholder="Staff, course or reference..."></div>
      </label>
      <button class="sts-primary-btn" type="submit"><i class="bi bi-funnel"></i> Apply</button>
      <?php if ($filterDepartment !== '' || $search !== ''): ?><a class="sts-secondary-btn" href="training-calendar.php?month=<?= e($month) ?>">Reset</a><?php endif; ?>
    </form>

    <section class="training-calendar-summary">
      <article><span><i class="bi bi-mortarboard"></i></span><div><strong><?= count($trainings) ?></strong><small>Approved training records</small></div></article>
      <article><span><i class="bi bi-people"></i></span><div><strong><?= count(array_unique(array_map(static fn($row)=>(int)$row['user_id'],$trainings))) ?></strong><small>Employees in training</small></div></article>
      <article><span><i class="bi bi-building"></i></span><div><strong><?= count(array_unique(array_filter(array_map(static fn($row)=>(string)$row['department'],$trainings)))) ?></strong><small>Departments represented</small></div></article>
      <article><span><i class="bi bi-calendar-event"></i></span><div><strong><?= count($companyEvents) ?></strong><small>Company events</small></div></article>
    </section>

    <section class="training-calendar-board">
      <div class="training-calendar-heading">
        <div><span><?= e($monthStart->format('Y')) ?></span><h2><?= e($monthStart->format('F')) ?></h2></div>
        <div class="training-calendar-legend"><span><i class="is-training"></i> Training</span><span><i class="is-company"></i> Company event</span></div>
      </div>

      <div class="training-calendar-weekdays">
        <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?><span><?= e($day) ?></span><?php endforeach; ?>
      </div>

      <div class="training-calendar-grid">
        <?php for ($date=$gridStart; $date < $gridEnd; $date=$date->modify('+1 day')): ?>
        <?php
          $key=$date->format('Y-m-d');
          $outside=$date < $monthStart || $date >= $monthEnd;
          $dayEvents=$eventsByDay[$key] ?? [];
        ?>
        <article class="training-calendar-day<?= $outside ? ' is-outside' : '' ?><?= $key === $today ? ' is-today' : '' ?>">
          <div class="training-calendar-day-head">
            <span><?= e($date->format('j')) ?></span>
            <?php if ($key === $today): ?><em>Today</em><?php endif; ?>
          </div>
          <div class="training-calendar-day-events">
            <?php foreach (array_slice($dayEvents,0,3) as $event): ?>
            <<?= $event['url'] === '#' ? 'div' : 'a' ?>
              class="training-calendar-event is-<?= e($event['kind']) ?>"
              <?= $event['url'] !== '#' ? 'href="'.e($event['url']).'"' : '' ?>
              title="<?= e($event['title'].' · '.$event['applicant']) ?>"
            >
              <strong><?= e($event['title']) ?></strong>
              <small><?= e($event['applicant']) ?></small>
            </<?= $event['url'] === '#' ? 'div' : 'a' ?>>
            <?php endforeach; ?>
            <?php if (count($dayEvents) > 3): ?><span class="training-calendar-more">+<?= count($dayEvents)-3 ?> more</span><?php endif; ?>
          </div>
        </article>
        <?php endfor; ?>
      </div>
    </section>

    <section class="sts-card training-calendar-list">
      <div class="sts-card-heading">
        <div><span>Month overview</span><h2>Training schedule</h2></div>
        <span class="report-record-count"><?= count($trainings) ?> records</span>
      </div>
      <?php if ($trainings): ?>
      <div class="training-calendar-list-body">
        <?php foreach ($trainings as $training): ?>
        <a href="application-detail.php?application=<?= rawurlencode((string)$training['application_no']) ?>">
          <span class="training-calendar-list-date"><strong><?= e(date('d',strtotime((string)$training['training_start']))) ?></strong><small><?= e(date('M',strtotime((string)$training['training_start']))) ?></small></span>
          <div><strong><?= e((string)$training['title']) ?></strong><p><?= e((string)$training['fullname']) ?> · <?= e((string)($training['department'] ?: 'Unassigned')) ?></p></div>
          <em><?= e(date('d M',strtotime((string)$training['training_start']))) ?> → <?= e(date('d M Y',strtotime((string)$training['training_end']))) ?></em>
          <i class="bi bi-chevron-right"></i>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="training-calendar-empty"><i class="bi bi-calendar2-check"></i><strong>No approved training this month</strong><span>Change the month or filters to view another schedule.</span></div>
      <?php endif; ?>
    </section>
  </div>
</main>
<script src="sedco-shell.js?v=20261007-04"></script>
</body>
</html>
