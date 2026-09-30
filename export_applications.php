<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
$user=current_user();
$role=normalized_role($user['role']??'');

if(!$user || !in_array($role,['admin','training_section','general_manager','head_of_department'],true)){
  http_response_code(403); exit('Report access is not available for this role.');
}

$where=[];$params=[];$types='';
$status=trim((string)($_GET['status']??''));
$formType=strtoupper(trim((string)($_GET['type']??'')));
$departmentFilter=trim((string)($_GET['department']??''));
$dateFrom=trim((string)($_GET['from']??''));
$dateTo=trim((string)($_GET['to']??''));

if($role==='head_of_department'){
  $where[]='(a.assigned_hod_id=? OR (a.assigned_hod_id IS NULL AND (a.department=? OR a.department IS NULL OR a.department="")))';
  $params[]=(int)$user['id'];$types.='i';$params[]=trim((string)($user['department']??''));$types.='s';
}
if(in_array($status,['pending','approved','correction','rejected'],true)){$where[]='a.status=?';$params[]=$status;$types.='s';}
if(in_array($formType,['BPL','PKK','TEA'],true)){$where[]='a.form_type=?';$params[]=$formType;$types.='s';}
if($departmentFilter!==''&&$role!=='head_of_department'){$where[]='a.department=?';$params[]=$departmentFilter;$types.='s';}
if($dateFrom!==''){$where[]='DATE(a.submitted_at)>=?';$params[]=$dateFrom;$types.='s';}
if($dateTo!==''){$where[]='DATE(a.submitted_at)<=?';$params[]=$dateTo;$types.='s';}

$sql='SELECT a.application_no,u.fullname,a.department,a.form_type,a.title,a.status,a.current_stage,a.submitted_at,a.completed_at,a.payload
FROM applications a INNER JOIN users u ON u.id=a.user_id';
if($where)$sql.=' WHERE '.implode(' AND ',$where);
$sql.=' ORDER BY a.submitted_at DESC';

$stmt=db()->prepare($sql);
if($params)$stmt->bind_param($types,...$params);
$stmt->execute();$result=$stmt->get_result();

sts_audit('applications_exported','report','csv',['filters'=>$_GET],(int)$user['id']);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="sts-applications-'.date('Ymd-His').'.csv"');
$out=fopen('php://output','w');
fwrite($out,"\xEF\xBB\xBF");
fputcsv($out,['Application ID','Applicant','Department','Form','Title','Status','Stage','Submitted','Completed','Course Fee (RM)','Training Start','Training End']);
while($row=$result->fetch_assoc()){
  $payload=json_decode((string)$row['payload'],true);$payload=is_array($payload)?$payload:[];
  fputcsv($out,[
    $row['application_no'],$row['fullname'],$row['department'],$row['form_type'],$row['title'],$row['status'],
    stage_label((string)$row['current_stage']),$row['submitted_at'],$row['completed_at'],
    $payload['yuran']??'',$payload['tarikh_mula']??'',$payload['tarikh_tamat']??''
  ]);
}
fclose($out);$stmt->close();exit;
