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
<link rel="stylesheet" href="sts-print.css?v=20260930-52">
</head>
<body>
<div class="print-actions"><button onclick="window.print()">Print / Save as PDF</button></div>
<header class="print-header">
  <div><h1>SMART TRAINING SYSTEM</h1><strong><?= e(sts_form_name((string) $app['form_type'])) ?></strong></div>
  <div><strong><?= e($applicationNo) ?></strong><br><?= e(ucfirst((string) $app['status'])) ?></div>
</header>
<section class="print-meta">
  <div><span>Applicant</span><strong><?= e($app['applicant_name']) ?></strong></div>
  <div><span>Department</span><strong><?= e($app['department'] ?: '—') ?></strong></div>
  <div><span>Submitted</span><strong><?= e(date('d M Y', strtotime((string) $app['submitted_at']))) ?></strong></div>
</section>
<h2 class="print-section-title">Form Information</h2>
<div class="print-grid">
<?php foreach ($payload as $key => $value): ?>
<?php $display=is_array($value)?implode(', ',array_map('strval',$value)):(string)$value; if(trim($display)==='')continue; ?>
<div class="print-field"><span><?= e(ucwords(str_replace('_',' ',(string)$key))) ?></span><strong><?= e($display) ?></strong></div>
<?php endforeach; ?>
</div>
<h2 class="print-section-title">Approval History</h2>
<?php if (!$reviews): ?><p>No approval history recorded.</p><?php endif; ?>
<?php foreach ($reviews as $review): ?>
<div class="print-review">
  <strong><?= e(stage_label((string) $review['review_stage'])) ?> — <?= e(ucfirst((string) $review['decision'])) ?></strong><br>
  <?= e($review['fullname']) ?> · <?= e(date('d M Y, g:i A', strtotime((string) $review['reviewed_at']))) ?>
  <?php if (!empty($review['note'])): ?><br><?= e($review['note']) ?><?php endif; ?>
</div>
<?php endforeach; ?>
<script>if(new URLSearchParams(location.search).get('print')==='1')window.print();</script>
</body>
</html>
