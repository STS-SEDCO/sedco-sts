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

$calendarFeed = sts_calendar_feed(
    $user,
    $monthStart->format('Y-m-d'),
    $monthEnd->format('Y-m-d'),
    $filterDepartment,
    $search
);
$trainings = $calendarFeed['trainings'];
$companyEvents = $calendarFeed['company_events'];

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
$currentMonth = (new DateTimeImmutable('now',new DateTimeZone('Asia/Kuala_Lumpur')))->format('Y-m');

$calendarItems = [];

foreach ($trainings as $training) {
    $calendarItems[] = [
        'kind' => 'training',
        'title' => (string) $training['title'],
        'applicant' => (string) $training['fullname'],
        'department' => (string) ($training['department'] ?: 'Unassigned'),
        'ref' => (string) $training['application_no'],
        'start' => (string) $training['training_start'],
        'end' => (string) ($training['training_end'] ?: $training['training_start']),
        'url' => 'application-detail.php?application=' . rawurlencode((string) $training['application_no']),
    ];
}

foreach ($companyEvents as $event) {
    $calendarItems[] = [
        'kind' => 'company',
        'title' => (string) $event['title'],
        'applicant' => 'Company event',
        'department' => 'SEDCO',
        'ref' => '',
        'start' => (string) $event['event_date'],
        'end' => (string) $event['event_date'],
        'url' => '#',
    ];
}
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
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-08">
</head>
<body class="app-page training-calendar-page" data-page="training-calendar" data-role="<?= e($role) ?>" data-calendar-static="0" data-calendar-month="<?= e($month) ?>">
<main class="sts-page-content">
  <div class="sts-page-shell">
    <header class="sts-page-heading training-calendar-page-heading">
      <div>
        <div class="sts-eyebrow">Organisation schedule</div>
        <h1>Training Calendar</h1>
        <p>Approved training and company events in one clear monthly view.</p>
      </div>
      <a class="sts-secondary-btn" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    </header>

    <form class="training-calendar-reference-toolbar" method="get">
      <input type="hidden" name="month" value="<?= e($month) ?>">
      <?php if (!in_array($role,['staff','head_of_department'],true)): ?>
      <label>
        <span>Department</span>
        <select name="department" onchange="this.form.submit()">
          <option value="">All departments</option>
          <?php foreach ($departments as $department): ?>
          <option value="<?= e($department) ?>" <?= $department === $filterDepartment ? 'selected' : '' ?>><?= e($department) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>
      <label class="is-search">
        <span>Search</span>
        <div><i class="bi bi-search"></i><input type="search" name="q" value="<?= e($search) ?>" placeholder="Staff, course or reference..."></div>
      </label>
      <button class="sts-primary-btn" type="submit"><i class="bi bi-funnel"></i> Apply</button>
      <?php if ($filterDepartment !== '' || $search !== ''): ?><a class="sts-secondary-btn" href="training-calendar.php?month=<?= e($month) ?>">Reset</a><?php endif; ?>
    </form>

    <section class="training-calendar-reference-shell">
      <div class="training-calendar-reference-head">
        <div>
          <span>Calendar</span>
          <h2>Monthly training schedule</h2>
          <p>Approved training and company events in one place.</p>
        </div>
        <a href="admin-settings.php#calendar-events" class="training-calendar-reference-action"><i class="bi bi-plus-lg"></i> New event</a>
      </div>

      <div class="training-calendar-reference-layout">
        <section class="training-calendar-reference-month">
          <div class="training-calendar-reference-month-head">
            <div>
              <span class="training-calendar-reference-overline">Month view</span>
              <h3 id="calendarTitle"><?= e($monthStart->format('F')) ?> <em><?= e($monthStart->format('Y')) ?></em></h3>
              <p id="calendarMonthCount"><?= count($calendarItems) ?> scheduled item<?= count($calendarItems) === 1 ? '' : 's' ?> this month</p>
            </div>
            <div class="training-calendar-reference-nav">
              <a href="?month=<?= e($previousMonth) ?><?= $filterDepartment !== '' ? '&department='.rawurlencode($filterDepartment) : '' ?><?= $search !== '' ? '&q='.rawurlencode($search) : '' ?>" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
              <a class="is-today" href="?month=<?= e($currentMonth) ?><?= $filterDepartment !== '' ? '&department='.rawurlencode($filterDepartment) : '' ?><?= $search !== '' ? '&q='.rawurlencode($search) : '' ?>">Today</a>
              <a href="?month=<?= e($nextMonth) ?><?= $filterDepartment !== '' ? '&department='.rawurlencode($filterDepartment) : '' ?><?= $search !== '' ? '&q='.rawurlencode($search) : '' ?>" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>
            </div>
          </div>

          <div class="training-calendar-reference-legend">
            <span><i class="tone-training"></i>Training</span>
            <span><i class="tone-company"></i>Company event</span>
          </div>

          <div class="training-calendar-reference-weekdays" aria-hidden="true">
            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
          </div>
          <div class="training-calendar-reference-grid" id="calendarGrid"></div>
        </section>

        <aside class="training-selected-day" id="calendarSelectedDay" aria-live="polite"></aside>
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
<script>
window.STS_TRAINING_CALENDAR_ITEMS = <?= json_encode(
    $calendarItems,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
) ?>;
</script>
<script src="training-calendar.js?v=20261007-02"></script>
<script src="sedco-shell.js?v=20261007-04"></script>
</body>
</html>
