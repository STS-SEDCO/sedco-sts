<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
$user=current_user();

if(!$user || !in_array(
    normalized_role($user['role']??''),
    ['admin','training_section','general_manager','head_of_department','pengerusi_besar','finance'],
    true
)){
  http_response_code(403); exit('Report access is not available for this role.');
}

$role=normalized_role($user['role']??'');
$userId=(int)$user['id'];

sts_repair_pending_bpl_stages();
$department=trim((string)($user['department']??''));
$status=trim((string)($_GET['status']??''));
$formType=strtoupper(trim((string)($_GET['type']??'')));
$filterDepartment=trim((string)($_GET['department']??''));
$dateFrom=trim((string)($_GET['from']??''));
$dateTo=trim((string)($_GET['to']??''));

// Management snapshot is always based on the viewer's allowed scope.
// It stays independent from date/status filters so the current month remains comparable.
$snapshotWhere = [];
$snapshotParams = [];
$snapshotTypes = '';

if ($role === 'head_of_department') {
  $snapshotWhere[] = '(
    a.assigned_hod_id = ?
    OR (
      a.assigned_hod_id IS NULL
      AND LOWER(TRIM(COALESCE(a.department, ""))) = LOWER(TRIM(?))
    )
  )';
  $snapshotParams[] = $userId; $snapshotTypes .= 'i';
  $snapshotParams[] = $department; $snapshotTypes .= 's';
} elseif ($filterDepartment !== '') {
  $snapshotWhere[] = 'a.department = ?';
  $snapshotParams[] = $filterDepartment; $snapshotTypes .= 's';
}

$snapshotSql = 'SELECT
  SUM(a.form_type = "BPL" AND a.submitted_at >= DATE_FORMAT(CURRENT_DATE, "%Y-%m-01")) AS bpl_this_month,
  SUM(a.form_type = "BPL"
      AND a.status = "pending"
      AND a.sla_due_at IS NOT NULL
      AND a.sla_due_at < NOW()) AS overdue_approvals,
  SUM(a.form_type = "BPL"
      AND a.status = "approved"
      AND a.current_stage = "completed"
      AND a.training_end IS NOT NULL
      AND a.training_end <= CURRENT_DATE) AS pkk_eligible,
  SUM(a.form_type = "BPL"
      AND a.status = "approved"
      AND a.current_stage = "completed"
      AND a.training_end IS NOT NULL
      AND a.training_end <= CURRENT_DATE
      AND EXISTS (
        SELECT 1
        FROM applications p
        WHERE p.parent_application_id = a.id
          AND p.form_type = "PKK"
          AND p.status = "approved"
          AND p.current_stage = "completed"
          AND p.submitted_at <= DATE_ADD(CONCAT(a.training_end, " 23:59:59"), INTERVAL 7 DAY)
      )) AS pkk_on_time,
  SUM(a.form_type = "PKK" AND a.submitted_at >= DATE_FORMAT(CURRENT_DATE, "%Y-%m-01")) AS pkk_this_month,
  SUM(a.form_type = "TEA" AND a.submitted_at >= DATE_FORMAT(CURRENT_DATE, "%Y-%m-01")) AS tea_this_month
FROM applications a';

if ($snapshotWhere) {
  $snapshotSql .= ' WHERE ' . implode(' AND ', $snapshotWhere);
}

$snapshotStmt = db()->prepare($snapshotSql);
if ($snapshotParams) {
  $snapshotBind = [$snapshotTypes];
  foreach ($snapshotParams as $snapshotIndex => $snapshotValue) {
    $snapshotBind[] = &$snapshotParams[$snapshotIndex];
  }
  call_user_func_array([$snapshotStmt, 'bind_param'], $snapshotBind);
}
$snapshotStmt->execute();
$snapshot = $snapshotStmt->get_result()->fetch_assoc() ?: [];
$snapshotStmt->close();

$pkkEligible = (int) ($snapshot['pkk_eligible'] ?? 0);
$pkkOnTime = (int) ($snapshot['pkk_on_time'] ?? 0);
$pkkCompliance = $pkkEligible > 0 ? round(($pkkOnTime / $pkkEligible) * 100, 1) : 0;
$followupsThisMonth = (int) ($snapshot['pkk_this_month'] ?? 0) + (int) ($snapshot['tea_this_month'] ?? 0);

$where=[];
$params=[];
$types='';

if($role==='head_of_department'){
  $where[]='(
    a.assigned_hod_id = ?
    OR (
      a.assigned_hod_id IS NULL
      AND LOWER(TRIM(COALESCE(a.department, ""))) = LOWER(TRIM(?))
    )
  )';
  $params[]=$userId; $types.='i';
  $params[]=$department; $types.='s';
}
if(in_array($status,['pending','approved','correction','rejected','cancelled'],true)){
  $where[]='a.status = ?'; $params[]=$status; $types.='s';
}
if(in_array($formType,['BPL','PKK','TEA'],true)){
  $where[]='a.form_type = ?'; $params[]=$formType; $types.='s';
}
if($filterDepartment!=='' && $role!=='head_of_department'){
  $where[]='a.department = ?'; $params[]=$filterDepartment; $types.='s';
}
if($dateFrom!==''){
  $where[]='DATE(a.submitted_at) >= ?'; $params[]=$dateFrom; $types.='s';
}
if($dateTo!==''){
  $where[]='DATE(a.submitted_at) <= ?'; $params[]=$dateTo; $types.='s';
}

$sql='SELECT a.application_no,a.form_type,a.title,a.department,a.status,a.current_stage,
             a.payload,a.submitted_at,a.completed_at,u.id AS employee_id,u.fullname
      FROM applications a
      INNER JOIN users u ON u.id=a.user_id';
if($where)$sql.=' WHERE '.implode(' AND ',$where);
$sql.=' ORDER BY a.submitted_at DESC';

$stmt=db()->prepare($sql);
if($params){
    $bindArgs = [$types];
    foreach($params as $index => $value){
        $bindArgs[] = &$params[$index];
    }
    call_user_func_array([$stmt,'bind_param'],$bindArgs);
}
$stmt->execute();
$result=$stmt->get_result();
$rows=[];

$totalFees=0.0;
$statusCounts=['pending'=>0,'approved'=>0,'correction'=>0,'rejected'=>0,'cancelled'=>0];
$typeCounts=['BPL'=>0,'PKK'=>0,'TEA'=>0];
$deptCounts=[];
$monthCounts=[];

while($row=$result->fetch_assoc()){
  $payload=json_decode((string)$row['payload'],true);
  $payload=is_array($payload)?$payload:[];
  $row['payload_decoded']=$payload;
  $rows[]=$row;

  $statusCounts[$row['status']] = ($statusCounts[$row['status']]??0)+1;
  $typeCounts[$row['form_type']] = ($typeCounts[$row['form_type']]??0)+1;

  $dept=(string)($row['department']?:'Unassigned');
  $deptCounts[$dept]=($deptCounts[$dept]??0)+1;

  $month=date('Y-m',strtotime((string)$row['submitted_at']));
  $monthCounts[$month]=($monthCounts[$month]??0)+1;

  if($row['form_type']==='BPL'){
    $fee=preg_replace('/[^0-9.]/','',(string)($payload['yuran']??''));
    if($fee!=='' && is_numeric($fee))$totalFees+=(float)$fee;
  }
}
$stmt->close();

arsort($deptCounts);
$approvalRate=count($rows)>0?round(($statusCounts['approved']/count($rows))*100,1):0;
$avgProcessingDays=null;
$processing=[];
foreach($rows as $row){
  if(!empty($row['completed_at'])){
    $start=strtotime((string)$row['submitted_at']); $end=strtotime((string)$row['completed_at']);
    if($start&&$end&&$end>=$start)$processing[]=($end-$start)/86400;
  }
}
if($processing)$avgProcessingDays=round(array_sum($processing)/count($processing),1);

$departmentResult=db()->query('SELECT DISTINCT department FROM applications WHERE department IS NOT NULL AND department <> "" ORDER BY department ASC');
$departmentOptions=[]; while($r=$departmentResult->fetch_assoc())$departmentOptions[]=$r['department'];

$query=http_build_query(array_filter([
 'status'=>$status,'type'=>$formType,'department'=>$filterDepartment,'from'=>$dateFrom,'to'=>$dateTo
],fn($v)=>$v!==''));
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Smart Training System: Reports and Analytics</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="sedco-saas.css?v=20261007-17"><link rel="stylesheet" href="sedco-shell.css?v=20261007-16">
</head>
<body class="app-page reports-page" data-page="reports" data-role="<?= e($role) ?>">
<main class="sts-page-content"><div class="sts-page-shell">
<header class="sts-page-heading">
<div><div class="sts-eyebrow">Insights</div><h1>Reports & Analytics</h1><p>Monitor training demand, approval outcomes, processing time and estimated course fees.</p></div>
<div class="sts-heading-actions">
  <a class="sts-secondary-btn" href="employee-training-history.php"><i class="bi bi-person-lines-fill"></i> Training history</a>
  <a class="sts-secondary-btn" href="monthly-report.php"><i class="bi bi-file-earmark-bar-graph"></i> Monthly report</a>
  <a class="sts-primary-btn" href="export_applications.php<?= $query?'?'.e($query):'' ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
</div>
</header>

<form class="report-filter-bar" method="get">
<select name="status"><option value="">All statuses</option><?php foreach(['pending'=>'Pending','approved'=>'Approved','correction'=>'Needs correction','rejected'=>'Rejected','cancelled'=>'Cancelled'] as $v=>$l): ?><option value="<?= e($v) ?>" <?= $status===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select>
<select name="type"><option value="">All forms</option><?php foreach(['BPL','PKK','TEA'] as $v): ?><option value="<?= e($v) ?>" <?= $formType===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select>
<?php if($role!=='head_of_department'): ?><select name="department"><option value="">All departments</option><?php foreach($departmentOptions as $d): ?><option value="<?= e((string)$d) ?>" <?= $filterDepartment===$d?'selected':'' ?>><?= e((string)$d) ?></option><?php endforeach; ?></select><?php endif; ?>
<label><span>From</span><input type="date" name="from" value="<?= e($dateFrom) ?>"></label>
<label><span>To</span><input type="date" name="to" value="<?= e($dateTo) ?>"></label>
<button class="sts-primary-btn" type="submit"><i class="bi bi-funnel"></i> Apply</button>
<a class="sts-secondary-btn" href="reports.php">Reset</a>
</form>

<section class="management-snapshot">
  <div class="management-snapshot-heading">
    <div>
      <span>Current month</span>
      <h2>Management snapshot</h2>
      <p>Fast indicators for demand, compliance and approval workload.</p>
    </div>
    <strong><?= e(date('F Y')) ?></strong>
  </div>
  <div class="management-snapshot-grid">
    <article>
      <span><i class="bi bi-file-earmark-plus"></i></span>
      <div><strong><?= (int) ($snapshot['bpl_this_month'] ?? 0) ?></strong><small>BPL submitted this month</small></div>
    </article>
    <article class="<?= $pkkCompliance < 80 && $pkkEligible > 0 ? 'is-warning' : '' ?>">
      <span><i class="bi bi-clipboard2-check"></i></span>
      <div>
        <strong><?= e((string) $pkkCompliance) ?>%</strong>
        <small>PKK within 7 days · <?= $pkkOnTime ?>/<?= $pkkEligible ?></small>
      </div>
    </article>
    <article class="<?= (int) ($snapshot['overdue_approvals'] ?? 0) > 0 ? 'is-danger' : '' ?>">
      <span><i class="bi bi-alarm"></i></span>
      <div><strong><?= (int) ($snapshot['overdue_approvals'] ?? 0) ?></strong><small>Overdue approvals now</small></div>
    </article>
    <article>
      <span><i class="bi bi-arrow-repeat"></i></span>
      <div><strong><?= $followupsThisMonth ?></strong><small>PKK + TEA submitted this month</small></div>
    </article>
  </div>
</section>

<section class="report-kpis">
<article><span><i class="bi bi-files"></i></span><div><strong><?= count($rows) ?></strong><small>Total records</small></div></article>
<article><span><i class="bi bi-check2-circle"></i></span><div><strong><?= e((string)$approvalRate) ?>%</strong><small>Approval rate</small></div></article>
<article><span><i class="bi bi-clock-history"></i></span><div><strong><?= $avgProcessingDays===null?'No data yet':e((string)$avgProcessingDays).' d' ?></strong><small>Avg. processing</small></div></article>
<article><span><i class="bi bi-cash-stack"></i></span><div><strong>RM <?= e(number_format($totalFees,2)) ?></strong><small>Submitted BPL fees</small></div></article>
</section>


<section class="report-graphic-grid" aria-label="Visual analytics">
  <article class="sts-card report-chart-card report-status-chart-card">
    <div class="report-chart-heading">
      <div><span>Outcome</span><h2>Status distribution</h2><p>Application outcome at a glance.</p></div>
      <i class="bi bi-pie-chart"></i>
    </div>
    <div class="report-chart-body" id="reportStatusDonut"></div>
  </article>

  <article class="sts-card report-chart-card report-forms-chart-card">
    <div class="report-chart-heading">
      <div><span>Demand</span><h2>Forms submitted</h2><p>Volume by BPL, PKK and TEA.</p></div>
      <i class="bi bi-bar-chart"></i>
    </div>
    <div class="report-chart-body" id="reportFormsChart"></div>
  </article>

  <article class="sts-card report-chart-card report-trend-chart-card">
    <div class="report-chart-heading">
      <div><span>Trend</span><h2>Monthly submission trend</h2><p>Application activity across the latest six months.</p></div>
      <i class="bi bi-graph-up-arrow"></i>
    </div>
    <div class="report-chart-body report-trend-body" id="reportMonthlyTrend"></div>
  </article>

  <article class="sts-card report-chart-card report-department-chart-card">
    <div class="report-chart-heading">
      <div><span>Department</span><h2>Top training demand</h2><p>Departments generating the most applications.</p></div>
      <i class="bi bi-building"></i>
    </div>
    <div class="report-chart-body" id="reportDepartmentChart"></div>
  </article>
</section>

<section class="sts-card report-table-card">
<div class="sts-card-heading"><div><span>Records</span><h2>Application register</h2></div><span class="report-record-count"><?= count($rows) ?> results</span></div>
<div class="report-table-wrap"><table class="report-table"><thead><tr><th>Reference</th><th>Applicant</th><th>Department</th><th>Form</th><th>Status</th><th>Stage</th><th>Submitted</th><th></th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr>
<td><strong><?= e($row['application_no']) ?></strong></td><td><a class="report-employee-link" href="employee-training-history.php?employee=<?= (int)$row['employee_id'] ?>"><?= e($row['fullname']) ?></a></td><td><?= e($row['department']?:'Not available') ?></td><td><?= e($row['form_type']) ?></td>
<td><span class="status-pill status-<?= e($row['status']) ?>"><?= e(ucfirst((string)$row['status'])) ?></span></td><td><?= e(stage_label((string)$row['current_stage'])) ?></td>
<td><?= e(date('d M Y',strtotime((string)$row['submitted_at']))) ?></td><td><a href="application-detail.php?application=<?= rawurlencode((string)$row['application_no']) ?>">View <i class="bi bi-arrow-up-right"></i></a></td>
</tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center py-5 text-muted">No records match the selected filters.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
</div></main>
<script>
window.STS_REPORT_SERVER_ANALYTICS = <?= json_encode([
  'statusCounts' => $statusCounts,
  'typeCounts' => $typeCounts,
  'deptCounts' => $deptCounts,
  'monthCounts' => $monthCounts,
  'total' => count($rows),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="reports.js?v=20261007-04"></script>
<script src="sedco-shell.js?v=20261007-06"></script></body></html>
