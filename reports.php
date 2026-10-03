<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
$user=current_user();

if(!$user || !in_array(normalized_role($user['role']??''),['admin','training_section','general_manager','head_of_department'],true)){
  http_response_code(403); exit('Report access is not available for this role.');
}

$role=normalized_role($user['role']??'');
$userId=(int)$user['id'];
$department=trim((string)($user['department']??''));
$status=trim((string)($_GET['status']??''));
$formType=strtoupper(trim((string)($_GET['type']??'')));
$filterDepartment=trim((string)($_GET['department']??''));
$dateFrom=trim((string)($_GET['from']??''));
$dateTo=trim((string)($_GET['to']??''));

$where=[];
$params=[];
$types='';

if($role==='head_of_department'){
  $where[]='(a.assigned_hod_id = ? OR (a.assigned_hod_id IS NULL AND (a.department = ? OR a.department IS NULL OR a.department = "")))';
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
             a.payload,a.submitted_at,a.completed_at,u.fullname
      FROM applications a
      INNER JOIN users u ON u.id=a.user_id';
if($where)$sql.=' WHERE '.implode(' AND ',$where);
$sql.=' ORDER BY a.submitted_at DESC';

$stmt=db()->prepare($sql);
if($params){
    $stmt->execute($params);
} else {
    $stmt->execute();
}
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

  $month=date('M Y',strtotime((string)$row['submitted_at']));
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
<link rel="stylesheet" href="sedco-saas.css?v=20260930-64"><link rel="stylesheet" href="sedco-shell.css?v=20260930-56">
</head>
<body class="app-page reports-page" data-page="reports" data-role="<?= e($role) ?>">
<main class="sts-page-content"><div class="sts-page-shell">
<header class="sts-page-heading">
<div><div class="sts-eyebrow">Insights</div><h1>Reports & Analytics</h1><p>Monitor training demand, approval outcomes, processing time and estimated course fees.</p></div>
<a class="sts-primary-btn" href="export_applications.php<?= $query?'?'.e($query):'' ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
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

<section class="report-kpis">
<article><span><i class="bi bi-files"></i></span><div><strong><?= count($rows) ?></strong><small>Total records</small></div></article>
<article><span><i class="bi bi-check2-circle"></i></span><div><strong><?= e((string)$approvalRate) ?>%</strong><small>Approval rate</small></div></article>
<article><span><i class="bi bi-clock-history"></i></span><div><strong><?= $avgProcessingDays===null?'Not available':e((string)$avgProcessingDays).' d' ?></strong><small>Avg. processing</small></div></article>
<article><span><i class="bi bi-cash-stack"></i></span><div><strong>RM <?= e(number_format($totalFees,2)) ?></strong><small>Submitted BPL fees</small></div></article>
</section>

<div class="reports-grid">
<section class="sts-card">
<div class="sts-card-heading"><div><span>Outcome</span><h2>Status distribution</h2></div><i class="bi bi-pie-chart"></i></div>
<div class="report-bars">
<?php $max=max(1,max($statusCounts)); foreach($statusCounts as $label=>$value): ?>
<div><div><strong><?= e(ucfirst($label)) ?></strong><span><?= (int)$value ?></span></div><i><b style="width:<?= e((string)round(($value/$max)*100,1)) ?>%"></b></i></div>
<?php endforeach; ?>
</div>
</section>
<section class="sts-card">
<div class="sts-card-heading"><div><span>Demand</span><h2>Forms submitted</h2></div><i class="bi bi-bar-chart"></i></div>
<div class="report-bars compact">
<?php $maxType=max(1,max($typeCounts)); foreach($typeCounts as $label=>$value): ?>
<div><div><strong><?= e($label) ?></strong><span><?= (int)$value ?></span></div><i><b style="width:<?= e((string)round(($value/$maxType)*100,1)) ?>%"></b></i></div>
<?php endforeach; ?>
</div>
</section>
<section class="sts-card">
<div class="sts-card-heading"><div><span>Department</span><h2>Top training demand</h2></div><i class="bi bi-building"></i></div>
<div class="report-ranking">
<?php foreach(array_slice($deptCounts,0,8,true) as $dept=>$value): ?><div><span><?= e($dept) ?></span><strong><?= (int)$value ?></strong></div><?php endforeach; ?>
<?php if(!$deptCounts): ?><p class="sts-muted-copy">No department data available.</p><?php endif; ?>
</div>
</section>
</div>

<section class="sts-card report-table-card">
<div class="sts-card-heading"><div><span>Records</span><h2>Application register</h2></div><span class="report-record-count"><?= count($rows) ?> results</span></div>
<div class="report-table-wrap"><table class="report-table"><thead><tr><th>Reference</th><th>Applicant</th><th>Department</th><th>Form</th><th>Status</th><th>Stage</th><th>Submitted</th><th></th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr>
<td><strong><?= e($row['application_no']) ?></strong></td><td><?= e($row['fullname']) ?></td><td><?= e($row['department']?:'Not available') ?></td><td><?= e($row['form_type']) ?></td>
<td><span class="status-pill status-<?= e($row['status']) ?>"><?= e(ucfirst((string)$row['status'])) ?></span></td><td><?= e(stage_label((string)$row['current_stage'])) ?></td>
<td><?= e(date('d M Y',strtotime((string)$row['submitted_at']))) ?></td><td><a href="application-detail.php?application=<?= rawurlencode((string)$row['application_no']) ?>">View <i class="bi bi-arrow-up-right"></i></a></td>
</tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center py-5 text-muted">No records match the selected filters.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
</div></main><script src="sedco-shell.js?v=20260930-56"></script></body></html>
