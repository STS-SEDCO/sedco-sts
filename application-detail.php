<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$applicationNo = trim((string) ($_GET['application'] ?? ''));

if ($applicationNo === '') {
    http_response_code(404);
    exit('Application not found.');
}

$stmt = db()->prepare(
    'SELECT a.*, u.fullname AS applicant_name, u.email AS applicant_email
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     WHERE a.application_no = ?
     LIMIT 1'
);
$stmt->bind_param('s', $applicationNo);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$application || !sts_can_view_application($application, $user)) {
    http_response_code(403);
    exit('You do not have permission to view this application.');
}

$payload = json_decode((string) $application['payload'], true);
$payload = is_array($payload) ? $payload : [];

$reviewStmt = db()->prepare(
    'SELECT r.review_stage, r.decision, r.note, r.reviewed_at, u.fullname
     FROM application_reviews r
     INNER JOIN users u ON u.id = r.reviewer_id
     WHERE r.application_id = ?
     ORDER BY r.reviewed_at ASC'
);
$applicationId = (int) $application['id'];
$reviewStmt->bind_param('i', $applicationId);
$reviewStmt->execute();
$reviewResult = $reviewStmt->get_result();
$reviews = [];

while ($row = $reviewResult->fetch_assoc()) {
    $reviews[] = $row;
}

$reviewStmt->close();

$attachmentStmt = db()->prepare(
    'SELECT id, original_name, mime_type, file_size, uploaded_at
     FROM application_attachments
     WHERE application_id = ?
     ORDER BY uploaded_at ASC'
);
$attachmentStmt->bind_param('i', $applicationId);
$attachmentStmt->execute();
$attachmentResult = $attachmentStmt->get_result();
$attachments = [];

while ($row = $attachmentResult->fetch_assoc()) {
    $attachments[] = $row;
}

$attachmentStmt->close();

$linkedStmt = db()->prepare(
    'SELECT application_no, form_type, title, status, submitted_at
     FROM applications
     WHERE parent_application_id = ?
     ORDER BY CASE WHEN status = "cancelled" THEN 1 ELSE 0 END ASC,
              submitted_at DESC'
);
$linkedStmt->bind_param('i', $applicationId);
$linkedStmt->execute();
$linkedResult = $linkedStmt->get_result();
$linked = [];

while ($row = $linkedResult->fetch_assoc()) {
    $linked[] = $row;
}

$linkedStmt->close();

$parentApplicationNo = null;
if (!empty($application['parent_application_id'])) {
    $parentStmt = db()->prepare(
        'SELECT application_no
         FROM applications
         WHERE id = ?
         LIMIT 1'
    );
    $parentId = (int) $application['parent_application_id'];
    $parentStmt->bind_param('i', $parentId);
    $parentStmt->execute();
    $parentRow = $parentStmt->get_result()->fetch_assoc();
    $parentStmt->close();
    $parentApplicationNo = $parentRow['application_no'] ?? null;
}

$versionStmt = db()->prepare(
    'SELECT v.event_type, v.created_at, u.fullname
     FROM application_versions v
     LEFT JOIN users u ON u.id = v.actor_user_id
     WHERE v.application_id = ?
     ORDER BY v.created_at DESC
     LIMIT 12'
);
$versionStmt->bind_param('i', $applicationId);
$versionStmt->execute();
$versionResult = $versionStmt->get_result();
$versions = [];

while ($row = $versionResult->fetch_assoc()) {
    $versions[] = $row;
}

$versionStmt->close();

$canEditPkk = sts_can_edit_pkk($application, $user);

$canCancelApplication =
    (
        sts_cancel_application_supported()
        && (int) $application['user_id'] === (int) $user['id']
        && in_array((string) $application['status'], ['pending', 'correction'], true)
    )
    || sts_can_cancel_pkk($application, $user);

$statusClass = match ((string) $application['status']) {
    'approved' => 'approved',
    'rejected' => 'rejected',
    'correction' => 'correction',
    'cancelled' => 'cancelled',
    default => 'pending',
};

function detail_value_label(string $key): string
{
    return ucwords(str_replace('_', ' ', $key));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: <?= e($applicationNo) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261006-10">
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-03">
</head>
<body class="app-page application-detail-page" data-page="application-status" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">
<main class="sts-page-content">
  <div class="sts-page-shell">
    <header class="sts-page-heading">
      <div>
        <div class="sts-eyebrow"><?= e($application['form_type']) ?> · <?= e($applicationNo) ?></div>
        <h1><?= e($application['title']) ?></h1>
        <p>Complete workflow history, linked forms, attachments and application information.</p>
      </div>
      <div class="sts-heading-actions">
        <?php if ($application['form_type'] === 'BPL'): ?>
        <a class="sts-secondary-btn" href="bpl.php?application=<?= rawurlencode($applicationNo) ?>">
          <i class="bi bi-file-earmark-text"></i> Open form
        </a>
        <?php elseif ($canEditPkk): ?>
        <a class="sts-secondary-btn" href="pkk.php?application=<?= rawurlencode($applicationNo) ?>">
          <i class="bi bi-pencil-square"></i> Edit submission
        </a>
        <?php endif; ?>
        <a class="sts-primary-btn" href="application-print.php?application=<?= rawurlencode($applicationNo) ?>" target="_blank">
          <i class="bi bi-printer"></i> Print / PDF
        </a>
        <?php if ($canCancelApplication): ?>
        <button class="sts-danger-btn" type="button" data-bs-toggle="modal" data-bs-target="#cancelApplicationModal">
          <i class="bi bi-x-circle"></i> Cancel application
        </button>
        <?php endif; ?>
      </div>
    </header>

    <section class="application-detail-hero">
      <div>
        <span>Applicant</span>
        <strong><?= e($application['applicant_name']) ?></strong>
      </div>
      <div>
        <span>Status</span>
        <strong class="status-pill status-<?= e($statusClass) ?>"><?= e(ucfirst((string) $application['status'])) ?></strong>
      </div>
      <div>
        <span>Current stage</span>
        <strong><?= e(stage_label((string) $application['current_stage'])) ?></strong>
      </div>
      <div>
        <span>Department</span>
        <strong><?= e($application['department'] ?: 'Not available') ?></strong>
      </div>
      <div>
        <span>Submitted</span>
        <strong><?= e(date('d M Y, g:i A', strtotime((string) $application['submitted_at']))) ?></strong>
      </div>
      <div>
        <span>SLA due</span>
        <strong><?= !empty($application['sla_due_at']) ? e(date('d M Y, g:i A', strtotime((string) $application['sla_due_at']))) : 'Not assigned' ?></strong>
      </div>
    </section>

    <div class="application-detail-grid">
      <section class="sts-card">
        <div class="sts-card-heading">
          <div>
            <span>Workflow</span>
            <h2>Approval timeline</h2>
          </div>
          <i class="bi bi-diagram-3"></i>
        </div>

        <div class="workflow-timeline">
          <div class="workflow-step is-done">
            <span><i class="bi bi-check"></i></span>
            <div>
              <strong>Application submitted</strong>
              <p><?= e($application['applicant_name']) ?> · <?= e(date('d M Y, g:i A', strtotime((string) $application['submitted_at']))) ?></p>
            </div>
          </div>

          <?php foreach ($reviews as $review): ?>
          <div class="workflow-step is-<?= e($review['decision']) ?>">
            <span><i class="bi <?= $review['decision'] === 'approved' ? 'bi-check' : ($review['decision'] === 'rejected' ? 'bi-x' : 'bi-arrow-counterclockwise') ?>"></i></span>
            <div>
              <strong><?= e(stage_label((string) $review['review_stage'])) ?> · <?= e(ucfirst((string) $review['decision'])) ?></strong>
              <p><?= e($review['fullname']) ?> · <?= e(date('d M Y, g:i A', strtotime((string) $review['reviewed_at']))) ?></p>
              <?php if (!empty($review['note'])): ?><blockquote><?= e($review['note']) ?></blockquote><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <?php if ($application['status'] === 'approved'): ?>
          <div class="workflow-step is-done">
            <span><i class="bi bi-check2-all"></i></span>
            <div><strong>Workflow completed</strong><p>Final approval completed.</p></div>
          </div>
          <?php elseif ($application['status'] === 'cancelled'): ?>
          <div class="workflow-step is-cancelled">
            <span><i class="bi bi-slash-circle"></i></span>
            <div>
              <strong>Application cancelled</strong>
              <p>
                <?= !empty($application['cancelled_at']) ? e(date('d M Y, g:i A', strtotime((string) $application['cancelled_at']))) : 'Cancelled by applicant' ?>
              </p>
              <?php if (!empty($application['cancellation_reason'])): ?>
              <blockquote><?= e($application['cancellation_reason']) ?></blockquote>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </section>

      <aside class="sts-card">
        <div class="sts-card-heading">
          <div>
            <span>Lifecycle</span>
            <h2>BPL → PKK → TEA</h2>
          </div>
          <i class="bi bi-link-45deg"></i>
        </div>

        <?php if ($application['form_type'] === 'BPL'): ?>
        <div class="lifecycle-list">
          <div class="lifecycle-item is-complete">
            <span>BPL</span>
            <div><strong><?= e($applicationNo) ?></strong><small><?= e(ucfirst((string) $application['status'])) ?></small></div>
          </div>
          <?php foreach (['PKK','TEA'] as $followupType): ?>
          <?php
            $match = null;
            foreach ($linked as $item) {
                if ($item['form_type'] === $followupType) {
                    $match = $item;
                    break;
                }
            }
          ?>
          <div class="lifecycle-item<?= $match ? ' is-complete' : '' ?>">
            <span><?= e($followupType) ?></span>
            <div>
              <?php if ($match): ?>
              <strong><?= e($match['application_no']) ?></strong>
              <small><?= e(ucfirst((string) $match['status'])) ?></small>
              <?php else: ?>
              <strong>Not submitted</strong>
              <small>Follow up pending</small>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php elseif (!empty($application['parent_application_id'])): ?>
        <?php if ($parentApplicationNo): ?>
        <a class="lifecycle-parent-link" href="application-detail.php?application=<?= rawurlencode((string) $parentApplicationNo) ?>">
          <i class="bi bi-arrow-left"></i> View linked BPL <?= e($parentApplicationNo) ?>
        </a>
        <?php endif; ?>
        <?php else: ?>
        <p class="sts-muted-copy">This form is not linked to a BPL training record.</p>
        <?php endif; ?>
      </aside>
    </div>

    <div class="application-detail-grid">
      <section class="sts-card">
        <div class="sts-card-heading">
          <div><span>Form data</span><h2>Submitted information</h2></div>
          <i class="bi bi-list-check"></i>
        </div>
        <div class="application-field-grid">
          <?php foreach ($payload as $key => $value): ?>
          <?php
            $display = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
            if (trim($display) === '') continue;
          ?>
          <div>
            <span><?= e(detail_value_label((string) $key)) ?></span>
            <?php if (str_starts_with($display, 'data:image/png;base64,')): ?>
            <strong class="application-signature-preview"><img src="<?= e($display) ?>" alt="Digital signature"></strong>
            <?php else: ?>
            <strong><?= e($display) ?></strong>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <aside class="sts-card">
        <div class="sts-card-heading">
          <div><span>Documents</span><h2>Attachments</h2></div>
          <i class="bi bi-paperclip"></i>
        </div>
        <?php if ($attachments): ?>
        <div class="attachment-list">
          <?php foreach ($attachments as $attachment): ?>
          <a href="attachment.php?id=<?= (int) $attachment['id'] ?>">
            <i class="bi bi-file-earmark-arrow-down"></i>
            <span>
              <strong><?= e($attachment['original_name']) ?></strong>
              <small><?= e(number_format(((int) $attachment['file_size']) / 1024, 1)) ?> KB</small>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="sts-muted-copy">No supporting files attached.</p>
        <?php endif; ?>

        <div class="version-history">
          <span class="version-heading">Change history</span>
          <?php foreach ($versions as $version): ?>
          <div>
            <strong><?= e(ucwords(str_replace('_', ' ', (string) $version['event_type']))) ?></strong>
            <small><?= e($version['fullname'] ?: 'System') ?> · <?= e(date('d M Y, g:i A', strtotime((string) $version['created_at']))) ?></small>
          </div>
          <?php endforeach; ?>
        </div>
      </aside>
    </div>
  </div>
</main>

<?php if ($canCancelApplication): ?>
<div class="modal fade status-modal cancel-application-modal" id="cancelApplicationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="cancel_application.php">
        <?= csrf_field() ?>
        <input type="hidden" name="application_no" value="<?= e($applicationNo) ?>">
        <div class="modal-header">
          <div>
            <div class="modal-kicker">Withdraw application</div>
            <h2 class="modal-title mt-1">Cancel application?</h2>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="cancel-warning-card">
            <span><i class="bi bi-exclamation-triangle"></i></span>
            <div>
              <strong><?= $application['form_type'] === 'PKK' ? 'This PKK submission will be cancelled.' : 'This will stop the current approval workflow.' ?></strong>
              <p><?= $application['form_type'] === 'PKK' ? 'The linked BPL course will become available for a new PKK submission.' : 'The record will remain in Application Status as Cancelled for audit and reference.' ?></p>
            </div>
          </div>
          <label class="cancel-reason-field">
            <span>Reason for cancellation <b>*</b></span>
            <textarea name="cancellation_reason" rows="4" minlength="5" maxlength="500" required placeholder="Tell us why this application is being cancelled..."></textarea>
            <small>Minimum 5 characters.</small>
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="sts-secondary-btn" data-bs-dismiss="modal">Keep application</button>
          <button type="submit" class="sts-danger-btn"><i class="bi bi-x-circle"></i> Confirm cancellation</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="sedco-shell.js?v=20261007-03"></script>
</body>
</html>
