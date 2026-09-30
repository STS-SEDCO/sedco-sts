<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();
$applicationNo = trim((string) ($_GET['application'] ?? ''));

$stmt = db()->prepare(
    'SELECT a.*, u.fullname AS applicant_name
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     WHERE a.application_no = ?
     LIMIT 1'
);
$stmt->bind_param('s', $applicationNo);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !$app || !sts_can_view_application($app, $user)) {
    http_response_code(403);
    exit('Application unavailable.');
}

$payload = json_decode((string) $app['payload'], true);
$payload = is_array($payload) ? $payload : [];

$reviewsStmt = db()->prepare(
    'SELECT r.review_stage, r.decision, r.note, r.reviewed_at, u.fullname
     FROM application_reviews r
     INNER JOIN users u ON u.id = r.reviewer_id
     WHERE r.application_id = ?
     ORDER BY r.reviewed_at ASC'
);
$appId = (int) $app['id'];
$reviewsStmt->bind_param('i', $appId);
$reviewsStmt->execute();
$reviewRows = $reviewsStmt->get_result();
$reviews = [];
while ($row = $reviewRows->fetch_assoc()) $reviews[] = $row;
$reviewsStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($applicationNo) ?> - Print</title>
<style>
body{font-family:Arial,sans-serif;color:#222;margin:32px;font-size:12px}
header{display:flex;justify-content:space-between;border-bottom:3px solid #7b1010;padding-bottom:16px;margin-bottom:22px}
h1{font-size:22px;margin:0 0 5px}h2{font-size:14px;margin:24px 0 10px;color:#7b1010}
.meta{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px}
.meta div,.field{border:1px solid #ddd;padding:10px;border-radius:6px}
.meta span,.field span{display:block;color:#777;font-size:9px;text-transform:uppercase;margin-bottom:4px}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
.review{border-left:3px solid #7b1010;padding:7px 10px;margin:8px 0;background:#fafafa}
.actions{position:fixed;right:20px;top:20px}.actions button{background:#7b1010;color:white;border:0;border-radius:7px;padding:10px 14px}
@media print{.actions{display:none}body{margin:12mm}.grid{break-inside:auto}.field{break-inside:avoid}}
</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Print / Save as PDF</button></div>
<header>
  <div><h1>SMART TRAINING SYSTEM</h1><strong><?= e(sts_form_name((string) $app['form_type'])) ?></strong></div>
  <div><strong><?= e($applicationNo) ?></strong><br><?= e(ucfirst((string) $app['status'])) ?></div>
</header>
<section class="meta">
  <div><span>Applicant</span><strong><?= e($app['applicant_name']) ?></strong></div>
  <div><span>Department</span><strong><?= e($app['department'] ?: '—') ?></strong></div>
  <div><span>Submitted</span><strong><?= e(date('d M Y', strtotime((string) $app['submitted_at']))) ?></strong></div>
</section>
<h2>Form Information</h2>
<div class="grid">
<?php foreach ($payload as $key => $value): ?>
<?php $display=is_array($value)?implode(', ',array_map('strval',$value)):(string)$value; if(trim($display)==='')continue; ?>
<div class="field"><span><?= e(ucwords(str_replace('_',' ',(string)$key))) ?></span><strong><?= e($display) ?></strong></div>
<?php endforeach; ?>
</div>
<h2>Approval History</h2>
<?php if (!$reviews): ?><p>No approval history recorded.</p><?php endif; ?>
<?php foreach ($reviews as $review): ?>
<div class="review">
  <strong><?= e(stage_label((string) $review['review_stage'])) ?> — <?= e(ucfirst((string) $review['decision'])) ?></strong><br>
  <?= e($review['fullname']) ?> · <?= e(date('d M Y, g:i A', strtotime((string) $review['reviewed_at']))) ?>
  <?php if (!empty($review['note'])): ?><br><?= e($review['note']) ?><?php endif; ?>
</div>
<?php endforeach; ?>
<script>if(new URLSearchParams(location.search).get('print')==='1')window.print();</script>
</body>
</html>
