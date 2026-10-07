<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
$user=current_user();

if(!$user || normalized_role($user['role']??'')!=='admin'){
  http_response_code(403); exit('Administrator access required.');
}

$result=db()->query(
 'SELECT a.action,a.entity_type,a.entity_id,a.metadata,a.ip_address,a.created_at,u.fullname
  FROM audit_logs a
  LEFT JOIN users u ON u.id=a.user_id
  ORDER BY a.created_at DESC
  LIMIT 300'
);
$logs=[]; while($row=$result->fetch_assoc())$logs[]=$row;
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Smart Training System: Audit Log</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="sedco-saas.css?v=20260930-42"><link rel="stylesheet" href="sedco-shell.css?v=20261007-16">
</head>
<body class="app-page admin-page" data-page="audit-log" data-role="admin">
<main class="sts-page-content"><div class="sts-page-shell">
<header class="sts-page-heading"><div><div class="sts-eyebrow">Security</div><h1>Audit Log</h1><p>Recent account, approval and administration activity.</p></div></header>
<section class="sts-card">
<div class="audit-table-wrap"><table class="audit-table"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach($logs as $log): ?><tr>
<td><?= e(date('d M Y, g:i A',strtotime((string)$log['created_at']))) ?></td>
<td><?= e($log['fullname']?:'System') ?></td>
<td><span class="audit-action"><?= e(ucwords(str_replace('_',' ',(string)$log['action']))) ?></span></td>
<td><?= e(($log['entity_type']?:'Not available').($log['entity_id']?' · '.$log['entity_id']:'')) ?></td>
<td><code><?= e($log['metadata']?:'Not available') ?></code></td>
<td><?= e($log['ip_address']?:'Not available') ?></td>
</tr><?php endforeach; ?>
<?php if(!$logs): ?><tr><td colspan="6" class="text-center py-5 text-muted">No audit records yet.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
</div></main><script src="sedco-shell.js?v=20261007-06"></script></body></html>
