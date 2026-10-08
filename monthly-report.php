<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user || !in_array(
    normalized_role($user['role'] ?? ''),
    ['admin','training_section','general_manager','head_of_department','pengerusi_besar','finance'],
    true
)) {
    http_response_code(403);
    exit('Monthly report access is not available for this role.');
}

$db = db();
$role = normalized_role($user['role'] ?? '');
$userId = (int) ($user['id'] ?? 0);
$department = trim((string) ($user['department'] ?? ''));

$month = trim((string) ($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/',$month)) {
    $month = date('Y-m');
}

try {
    $periodStart = new DateTimeImmutable($month . '-01 00:00:00',new DateTimeZone('Asia/Kuala_Lumpur'));
} catch (Throwable) {
    $periodStart = new DateTimeImmutable('first day of this month 00:00:00',new DateTimeZone('Asia/Kuala_Lumpur'));
}
$periodEnd = $periodStart->modify('+1 month');
$periodLabel = $periodStart->format('F Y');

$scopeSql = '';
$scopeParams = [];
$scopeTypes = '';

if ($role === 'head_of_department') {
    $scopeSql = ' AND (
      a.assigned_hod_id = ?
      OR (
        a.assigned_hod_id IS NULL
        AND LOWER(TRIM(COALESCE(a.department,""))) = LOWER(TRIM(?))
      )
    )';
    $scopeParams = [$userId,$department];
    $scopeTypes = 'is';
}

function monthly_bind(mysqli_stmt $stmt,string $types,array &$params): void
{
    if (!$params) return;

    $args = [$types];
    foreach ($params as $index => $value) {
        $args[] = &$params[$index];
    }
    call_user_func_array([$stmt,'bind_param'],$args);
}

$startSql = $periodStart->format('Y-m-d H:i:s');
$endSql = $periodEnd->format('Y-m-d H:i:s');

$bplSql =
    'SELECT a.id,a.application_no,a.user_id,a.title,a.department,a.payload,
            a.status,a.current_stage,a.training_start,a.training_end,
            a.submitted_at,a.completed_at,u.fullname
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     WHERE a.form_type = "BPL"
       AND a.submitted_at >= ?
       AND a.submitted_at < ?'
    . $scopeSql .
    ' ORDER BY a.submitted_at ASC,a.id ASC';

$params = [$startSql,$endSql,...$scopeParams];
$types = 'ss' . $scopeTypes;
$stmt = $db->prepare($bplSql);
monthly_bind($stmt,$types,$params);
$stmt->execute();
$result = $stmt->get_result();

$bplRows = [];
$departmentCounts = [];
$statusCounts = ['pending'=>0,'approved'=>0,'correction'=>0,'rejected'=>0,'cancelled'=>0];
$totalFees = 0.0;
$processingDays = [];
$courseCounts = [];

while ($row = $result->fetch_assoc()) {
    $payload = json_decode((string) ($row['payload'] ?? ''),true);
    $payload = is_array($payload) ? $payload : [];
    $row['payload_decoded'] = $payload;
    $bplRows[] = $row;

    $status = (string) $row['status'];
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

    $dept = trim((string) ($row['department'] ?? '')) ?: 'Unassigned';
    $departmentCounts[$dept] = ($departmentCounts[$dept] ?? 0) + 1;

    $courseTitle = trim((string) ($payload['tajuk'] ?? $row['title'] ?? 'Training')) ?: 'Training';
    $courseCounts[$courseTitle] = ($courseCounts[$courseTitle] ?? 0) + 1;

    $feeText = preg_replace('/[^0-9.]/','',(string) ($payload['yuran'] ?? ''));
    if ($feeText !== '' && is_numeric($feeText)) {
        $totalFees += (float) $feeText;
    }

    if (!empty($row['completed_at'])) {
        $start = strtotime((string) $row['submitted_at']);
        $completed = strtotime((string) $row['completed_at']);
        if ($start && $completed && $completed >= $start) {
            $processingDays[] = ($completed - $start) / 86400;
        }
    }
}
$stmt->close();

arsort($departmentCounts);
arsort($courseCounts);

$totalBpl = count($bplRows);
$approvalRate = $totalBpl > 0
    ? round(((int) ($statusCounts['approved'] ?? 0) / $totalBpl) * 100,1)
    : 0.0;
$avgProcessing = $processingDays
    ? round(array_sum($processingDays) / count($processingDays),1)
    : null;

$followupSql =
    'SELECT a.id,a.parent_application_id,a.form_type,a.payload,a.status,
            a.current_stage,a.submitted_at,a.completed_at,a.department
     FROM applications a
     WHERE a.form_type IN ("PKK","TEA")
       AND a.submitted_at >= ?
       AND a.submitted_at < ?'
    . $scopeSql .
    ' ORDER BY a.submitted_at ASC,a.id ASC';

$params = [$startSql,$endSql,...$scopeParams];
$types = 'ss' . $scopeTypes;
$stmt = $db->prepare($followupSql);
monthly_bind($stmt,$types,$params);
$stmt->execute();
$result = $stmt->get_result();

$pkkSubmitted = 0;
$teaSubmitted = 0;
$competencyCounts = [];

while ($row = $result->fetch_assoc()) {
    if ((string) $row['form_type'] === 'PKK') {
        $pkkSubmitted++;
        continue;
    }

    $teaSubmitted++;
    $payload = json_decode((string) ($row['payload'] ?? ''),true);
    $payload = is_array($payload) ? $payload : [];

    foreach ($payload as $key => $value) {
        if (!str_starts_with((string) $key,'competency_level_')) continue;
        $level = trim((string) $value);
        if ($level === '') continue;
        $competencyCounts[$level] = ($competencyCounts[$level] ?? 0) + 1;
    }
}
$stmt->close();

$endedSql =
    'SELECT a.id,a.training_end
     FROM applications a
     WHERE a.form_type = "BPL"
       AND a.status = "approved"
       AND a.current_stage = "completed"
       AND a.training_end >= ?
       AND a.training_end < ?'
    . $scopeSql;

$params = [
    $periodStart->format('Y-m-d'),
    $periodEnd->format('Y-m-d'),
    ...$scopeParams
];
$types = 'ss' . $scopeTypes;
$stmt = $db->prepare($endedSql);
monthly_bind($stmt,$types,$params);
$stmt->execute();
$endedResult = $stmt->get_result();
$endedBpl = [];

while ($row = $endedResult->fetch_assoc()) {
    $endedBpl[(int) $row['id']] = (string) $row['training_end'];
}
$stmt->close();

$pkkEligible = count($endedBpl);
$pkkOnTime = 0;

if ($endedBpl) {
    $result = $db->query(
        'SELECT parent_application_id,submitted_at,status,current_stage
         FROM applications
         WHERE form_type = "PKK"
           AND status <> "cancelled"
         ORDER BY submitted_at ASC,id ASC'
    );

    $firstPkkByParent = [];
    while ($row = $result->fetch_assoc()) {
        $parentId = (int) ($row['parent_application_id'] ?? 0);
        if (!isset($endedBpl[$parentId]) || isset($firstPkkByParent[$parentId])) continue;
        $firstPkkByParent[$parentId] = $row;
    }

    foreach ($endedBpl as $parentId => $trainingEnd) {
        $pkk = $firstPkkByParent[$parentId] ?? null;
        $due = sts_followup_due($trainingEnd,'PKK');

        if (!$pkk || !$due) continue;
        $submitted = strtotime((string) ($pkk['submitted_at'] ?? ''));
        if ($submitted && $submitted <= strtotime($due)) {
            $pkkOnTime++;
        }
    }
}

$pkkCompliance = $pkkEligible > 0
    ? round(($pkkOnTime / $pkkEligible) * 100,1)
    : 0.0;

$overdueSql =
    'SELECT COUNT(*) AS total
     FROM applications a
     WHERE a.form_type = "BPL"
       AND a.status = "pending"
       AND a.sla_due_at IS NOT NULL
       AND a.sla_due_at < NOW()'
    . $scopeSql;

$params = $scopeParams;
$types = $scopeTypes;
$stmt = $db->prepare($overdueSql);
monthly_bind($stmt,$types,$params);
$stmt->execute();
$overdueApprovals = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
$stmt->close();

$generatedAt = new DateTimeImmutable('now',new DateTimeZone('Asia/Kuala_Lumpur'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SEDCO Monthly Training Report: <?= e($periodLabel) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261007-10">
  <link rel="stylesheet" href="sedco-shell.css?v=20261008-05">
</head>
<body class="app-page monthly-report-page" data-page="reports" data-role="<?= e($role) ?>">
<main class="sts-page-content">
  <div class="sts-page-shell">
    <div class="monthly-report-toolbar no-print">
      <a class="sts-secondary-btn" href="reports.php"><i class="bi bi-arrow-left"></i> Back to analytics</a>
      <form method="get">
        <label><span>Reporting month</span><input type="month" name="month" value="<?= e($periodStart->format('Y-m')) ?>" onchange="this.form.submit()"></label>
      </form>
      <button class="sts-primary-btn" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save PDF</button>
    </div>

    <article class="monthly-report-document">
      <header class="monthly-report-header">
        <div class="monthly-report-brand">
          <span><i class="bi bi-mortarboard-fill"></i></span>
          <div>
            <small>SEDCO</small>
            <strong>SMART TRAINING SYSTEM</strong>
          </div>
        </div>
        <div class="monthly-report-title">
          <span>Official Management Report</span>
          <h1>Monthly Training Report</h1>
          <p><?= e($periodLabel) ?></p>
        </div>
        <div class="monthly-report-generated">
          <span>Generated</span>
          <strong><?= e($generatedAt->format('d M Y')) ?></strong>
          <small><?= e($generatedAt->format('g:i A')) ?> · Malaysia Time</small>
        </div>
      </header>

      <section class="monthly-report-intro">
        <div>
          <span>Reporting scope</span>
          <strong><?= $role === 'head_of_department' ? e($department) : 'SEDCO Training Management' ?></strong>
        </div>
        <p>This report summarises BPL demand, approval performance, training cost, PKK compliance and TEA competency outcomes for the selected reporting month.</p>
      </section>

      <section class="monthly-report-kpis">
        <article><span>BPL submitted</span><strong><?= $totalBpl ?></strong><small><?= e($periodLabel) ?></small></article>
        <article><span>Approval rate</span><strong><?= e((string) $approvalRate) ?>%</strong><small><?= (int) ($statusCounts['approved'] ?? 0) ?> approved</small></article>
        <article><span>Estimated fees</span><strong>RM <?= e(number_format($totalFees,2)) ?></strong><small>Submitted BPL value</small></article>
        <article><span>Avg. processing</span><strong><?= $avgProcessing === null ? 'No data' : e((string) $avgProcessing) . ' d' ?></strong><small>Completed records</small></article>
        <article class="<?= $pkkEligible > 0 && $pkkCompliance < 80 ? 'is-warning' : '' ?>"><span>PKK compliance</span><strong><?= e((string) $pkkCompliance) ?>%</strong><small><?= $pkkOnTime ?>/<?= $pkkEligible ?> within 7 days</small></article>
        <article class="<?= $overdueApprovals > 0 ? 'is-danger' : '' ?>"><span>Overdue approval</span><strong><?= $overdueApprovals ?></strong><small>Current SLA backlog</small></article>
      </section>

      <div class="monthly-report-grid">
        <section class="monthly-report-section">
          <div class="monthly-report-section-heading"><span>01</span><div><strong>Application outcome</strong><small>BPL status submitted in <?= e($periodLabel) ?></small></div></div>
          <div class="monthly-report-status-list">
            <?php foreach ([
              'pending'=>'Pending',
              'approved'=>'Approved',
              'correction'=>'Correction',
              'rejected'=>'Rejected',
              'cancelled'=>'Cancelled',
            ] as $key=>$label): ?>
            <div><span><?= e($label) ?></span><strong><?= (int) ($statusCounts[$key] ?? 0) ?></strong></div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="monthly-report-section">
          <div class="monthly-report-section-heading"><span>02</span><div><strong>Training follow up</strong><small>Forms submitted during <?= e($periodLabel) ?></small></div></div>
          <div class="monthly-report-followups">
            <div><span><i class="bi bi-clipboard2-check"></i></span><strong><?= $pkkSubmitted ?></strong><small>PKK submitted</small></div>
            <div><span><i class="bi bi-graph-up"></i></span><strong><?= $teaSubmitted ?></strong><small>TEA submitted</small></div>
          </div>
        </section>
      </div>

      <section class="monthly-report-section">
        <div class="monthly-report-section-heading"><span>03</span><div><strong>Training demand by department</strong><small>Number of BPL submissions</small></div></div>
        <div class="monthly-report-department-table">
          <?php if ($departmentCounts): ?>
          <?php $rank=1; foreach ($departmentCounts as $dept=>$count): ?>
          <div><span><?= $rank++ ?></span><strong><?= e($dept) ?></strong><em><?= (int) $count ?> BPL</em></div>
          <?php endforeach; ?>
          <?php else: ?><p>No department activity for this reporting month.</p><?php endif; ?>
        </div>
      </section>

      <div class="monthly-report-grid">
        <section class="monthly-report-section">
          <div class="monthly-report-section-heading"><span>04</span><div><strong>Top training demand</strong><small>Most requested course titles</small></div></div>
          <div class="monthly-report-ranking">
            <?php if ($courseCounts): ?>
            <?php $rank=1; foreach (array_slice($courseCounts,0,8,true) as $course=>$count): ?>
            <div><span><?= $rank++ ?></span><strong><?= e($course) ?></strong><em><?= (int) $count ?></em></div>
            <?php endforeach; ?>
            <?php else: ?><p>No course demand recorded.</p><?php endif; ?>
          </div>
        </section>

        <section class="monthly-report-section">
          <div class="monthly-report-section-heading"><span>05</span><div><strong>TEA competency outcomes</strong><small>Competency ratings submitted during the month</small></div></div>
          <div class="monthly-report-competency">
            <?php if ($competencyCounts): ?>
            <?php foreach ($competencyCounts as $level=>$count): ?>
            <div><span><?= e($level) ?></span><strong><?= (int) $count ?></strong></div>
            <?php endforeach; ?>
            <?php else: ?><p>No TEA competency results recorded for this month.</p><?php endif; ?>
          </div>
        </section>
      </div>

      <section class="monthly-report-section monthly-report-register">
        <div class="monthly-report-section-heading"><span>06</span><div><strong>BPL register</strong><small>Applications submitted in <?= e($periodLabel) ?></small></div></div>
        <div class="monthly-report-table-wrap">
          <table>
            <thead><tr><th>No.</th><th>Reference</th><th>Applicant</th><th>Department</th><th>Course</th><th>Training date</th><th>Fee</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($bplRows as $index=>$row): ?>
              <?php
                $payload = $row['payload_decoded'];
                $title = trim((string) ($payload['tajuk'] ?? $row['title'] ?? 'Training'));
                $feeText = preg_replace('/[^0-9.]/','',(string) ($payload['yuran'] ?? ''));
                $fee = $feeText !== '' && is_numeric($feeText) ? (float) $feeText : 0.0;
                $trainingDate = !empty($row['training_start'])
                  ? date('d M Y',strtotime((string) $row['training_start']))
                  : 'Not recorded';
              ?>
              <tr>
                <td><?= $index+1 ?></td>
                <td><?= e((string) $row['application_no']) ?></td>
                <td><?= e((string) $row['fullname']) ?></td>
                <td><?= e((string) ($row['department'] ?: 'Not specified')) ?></td>
                <td><?= e($title) ?></td>
                <td><?= e($trainingDate) ?></td>
                <td>RM <?= e(number_format($fee,2)) ?></td>
                <td><?= e(ucfirst((string) $row['status'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$bplRows): ?><tr><td colspan="8">No BPL records for this reporting month.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <footer class="monthly-report-footer">
        <div><span>Prepared through</span><strong>SEDCO Smart Training System</strong></div>
        <div><span>Report period</span><strong><?= e($periodLabel) ?></strong></div>
        <div><span>Generated by</span><strong><?= e((string) ($user['fullname'] ?? 'STS User')) ?></strong></div>
      </footer>
    </article>
  </div>
</main>
<script src="sedco-shell.js?v=20261008-08"></script>
</body>
</html>
