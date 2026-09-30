<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$normalizedRole = normalized_role($user['role'] ?? '');

if (!user_can_review_applications($user)) {
    header('Location: application-status.php');
    exit;
}

$stmt = db()->prepare(
    'SELECT a.application_no, a.form_type, a.title, a.payload, a.status,
            a.current_stage, a.review_note, a.submitted_at, a.updated_at, u.fullname
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     ORDER BY
       CASE
         WHEN a.form_type = "BPL" AND a.status = "pending" THEN 0
         WHEN a.form_type = "BPL" AND a.status = "correction" THEN 1
         ELSE 2
       END,
       a.submitted_at DESC'
);

$stmt->execute();
$result = $stmt->get_result();
$submissions = [];

while ($row = $result->fetch_assoc()) {
    $payload = json_decode((string) $row['payload'], true);
    $payload = is_array($payload) ? $payload : [];

    $submissions[] = [
        'id' => $row['application_no'],
        'type' => $row['form_type'],
        'formName' => match ($row['form_type']) {
            'BPL' => 'Permohonan Latihan',
            'PKK' => 'Penilaian Keberkesanan Kursus',
            default => 'Training Effectiveness Assessment',
        },
        'title' => $row['title'],
        'applicant' => $row['fullname'],
        'status' => $row['status'],
        'currentStage' => $row['current_stage'],
        'stageLabel' => stage_label($row['current_stage']),
        'reviewNote' => $row['review_note'],
        'canReview' => $row['form_type'] === 'BPL'
            && user_can_review_stage((string) $row['current_stage'], $user)
            && !in_array($row['status'], ['approved', 'rejected'], true)
            && $row['current_stage'] !== 'completed',
        'reviewUrl' => $row['form_type'] === 'BPL'
            ? 'bpl.php?application=' . rawurlencode((string) $row['application_no'])
            : null,
        'submittedAt' => $row['submitted_at'],
        'updatedAt' => $row['updated_at'],
        'data' => $payload,
    ];
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submissions - Smart Training System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20260930-34">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-15">
</head>
<body class="app-page submissions-page" data-page="submissions" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">

<main class="submissions-content">
  <div class="submissions-shell">
    <header class="submissions-heading">
      <div>
        <div class="submissions-eyebrow">Review workspace</div>
        <h1>Submissions</h1>
        <p>Review submitted training forms, follow their progress, and keep every application organised.</p>
      </div>

      <div class="submissions-heading-actions">
        <a class="submissions-secondary-action" href="application-status.php">
          <i class="bi bi-clipboard-data"></i>
          Application status
        </a>
        <a class="submissions-primary-action" href="task.php">
          <i class="bi bi-plus-lg"></i>
          New submission
        </a>
      </div>
    </header>

    <div class="submissions-meta-row">
      <div>
        <span class="submissions-section-label">Submission overview</span>
        <span class="submissions-section-note">Latest activity across submitted forms</span>
      </div>
      <span class="submissions-sync-chip"><span></span>Synced</span>
    </div>

    <section class="submissions-stats">
      <article class="submission-stat-card stat-total">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-inbox"></i></span>
          <span class="submission-stat-caption">All time</span>
        </div>
        <strong id="submissionTotal">0</strong>
        <span>Total received</span>
      </article>

      <article class="submission-stat-card stat-pending">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-hourglass-split"></i></span>
          <span class="submission-stat-caption">Queue</span>
        </div>
        <strong id="submissionPending">0</strong>
        <span>Awaiting review</span>
      </article>

      <article class="submission-stat-card stat-reviewed">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-check2-circle"></i></span>
          <span class="submission-stat-caption">Processed</span>
        </div>
        <strong id="submissionReviewed">0</strong>
        <span>Reviewed</span>
      </article>

      <article class="submission-stat-card stat-week">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-calendar3"></i></span>
          <span class="submission-stat-caption">Recent</span>
        </div>
        <strong id="submissionWeek">0</strong>
        <span>This week</span>
      </article>
    </section>

    <section class="submissions-panel">
      <div class="submissions-toolbar">
        <div class="submissions-filter-group">
          <button class="submission-filter active" type="button" data-submission-filter="all">All</button>
          <button class="submission-filter" type="button" data-submission-filter="pending">Pending</button>
          <button class="submission-filter" type="button" data-submission-filter="approved">Approved</button>
          <button class="submission-filter" type="button" data-submission-filter="correction">Needs correction</button>
          <button class="submission-filter" type="button" data-submission-filter="rejected">Rejected</button>
        </div>

        <div class="submissions-search">
          <i class="bi bi-search"></i>
          <input id="submissionSearch" type="search" placeholder="Search reference, applicant or form...">
        </div>
      </div>

      <div class="submissions-table-wrap">
        <table class="submissions-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Applicant</th>
              <th>Type</th>
              <th>Submission</th>
              <th>Status</th>
              <th>Stage</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="submissionRows"></tbody>
        </table>
      </div>

      <div id="submissionEmpty" class="submission-empty d-none">
        <div class="submission-empty-visual">
          <div class="submission-empty-icon"><i class="bi bi-inbox"></i></div>
          <span class="submission-empty-orbit"></span>
        </div>
        <h3>No submissions yet</h3>
        <p>Once a training form is submitted, it will appear here automatically for easy tracking and review.</p>
        <a href="task.php" class="submission-empty-action">
          Create submission <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </section>
  </div>
</main>

<div class="modal fade submissions-modal" id="submissionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="submission-modal-kicker" id="submissionModalRef">Submission</div>
          <h2 class="modal-title mt-1" id="submissionModalTitle">Submission details</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="submission-modal-status-row">
          <span id="submissionModalStatus" class="status-pill status-pending">
            <i class="bi bi-clock-history"></i>Pending review
          </span>
        </div>

        <div class="submission-modal-summary">
          <div>
            <span>Applicant</span>
            <strong id="submissionModalApplicant">—</strong>
          </div>
          <div>
            <span>Form type</span>
            <strong id="submissionModalType">—</strong>
          </div>
          <div>
            <span>Submitted</span>
            <strong id="submissionModalDate">—</strong>
          </div>
          <div>
            <span>Current stage</span>
            <strong id="submissionModalStage">—</strong>
          </div>
        </div>

        <div id="submissionModalFields" class="submission-detail-grid"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.SEDCO_SUBMISSIONS = <?= json_encode(
    $submissions,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>;
</script>
<script src="submissions.js?v=20260930-34"></script>
<script src="sedco-shell.js?v=20260930-34"></script>
</body>
</html>