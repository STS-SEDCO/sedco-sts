<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$stmt = db()->prepare(
    'SELECT a.id, a.application_no, a.user_id, a.parent_application_id,
            a.form_type, a.title, a.payload, a.status,
            a.current_stage, a.review_note, a.cancelled_at,
            a.cancellation_reason, a.submitted_at, a.updated_at, u.fullname
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     WHERE a.user_id = ?
     ORDER BY a.submitted_at DESC'
);
$userId = (int) $user['id'];
$stmt->bind_param('i', $userId);

$stmt->execute();
$result = $stmt->get_result();
$applications = [];

while ($row = $result->fetch_assoc()) {
    $payload = json_decode((string) $row['payload'], true);
    $payload = is_array($payload) ? $payload : [];

    $applicationDate = $payload['tarikh']
        ?? $payload['tarikh_mula']
        ?? $payload['date']
        ?? $payload['tarikh_penilaian']
        ?? $row['submitted_at'];

    $canEditPkk = sts_can_edit_pkk($row, $user);
    $canCancelPkk = sts_can_cancel_pkk($row, $user);

    $applications[] = [
        'id' => $row['application_no'],
        'type' => $row['form_type'],
        'formName' => match ($row['form_type']) {
            'BPL' => 'Permohonan Latihan',
            'PKK' => 'Penilaian Keberkesanan Kursus',
            default => 'Training Effectiveness Assessment',
        },
        'title' => $row['title'],
        'applicant' => $row['fullname'],
        'applicationDate' => $applicationDate,
        'status' => $row['status'],
        'currentStage' => $row['current_stage'],
        'stageLabel' => stage_label($row['current_stage']),
        'reviewNote' => $row['review_note'],
        'cancelledAt' => $row['cancelled_at'],
        'cancellationReason' => $row['cancellation_reason'],
        'canCancel' => (
            sts_cancel_application_supported()
            && in_array($row['status'], ['pending', 'correction'], true)
        ) || $canCancelPkk,
        'viewUrl' => 'application-detail.php?application=' . rawurlencode((string) $row['application_no']),
        'editUrl' => $row['form_type'] === 'BPL' && $row['status'] === 'correction'
            ? 'bpl.php?application=' . rawurlencode((string) $row['application_no'])
            : ($canEditPkk
                ? 'pkk.php?application=' . rawurlencode((string) $row['application_no'])
                : null),
        'editLabel' => $canEditPkk ? 'Edit submission' : 'Fix & Resubmit',
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
  <title>Smart Training System: Application Status</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261006-10">
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-02">
</head>
<body class="app-page status-page" data-page="application-status" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">

<main class="status-content">
  <div class="status-shell">
    <div class="status-heading">
      <div>
        <div class="status-eyebrow">Applications</div>
        <h1>Application Status</h1>
        <p>Track submitted training forms and their review progress in one place.</p>
      </div>

      <div class="status-heading-actions">
        <a class="status-primary-action" href="task.php">
          <i class="bi bi-plus-lg"></i>
          New application
        </a>
      </div>
    </div>

    <div class="status-section-meta">
      <div>
        <span class="status-section-label">Overview</span>
        <span class="status-section-note">Application activity at a glance</span>
      </div>
      <span class="status-live-chip"><span></span>Live status</span>
    </div>

    <section class="status-stats">
      <div class="status-stat">
        <div class="stat-icon total"><i class="bi bi-files"></i></div>
        <strong id="statTotal">0</strong>
        <span>Total applications</span>
      </div>
      <div class="status-stat">
        <div class="stat-icon pending"><i class="bi bi-clock-history"></i></div>
        <strong id="statPending">0</strong>
        <span>Pending review</span>
      </div>
      <div class="status-stat">
        <div class="stat-icon approved"><i class="bi bi-check2-circle"></i></div>
        <strong id="statApproved">0</strong>
        <span>Approved</span>
      </div>
      <div class="status-stat">
        <div class="stat-icon action"><i class="bi bi-exclamation-circle"></i></div>
        <strong id="statAction">0</strong>
        <span>Need attention</span>
      </div>
    </section>

    <section class="status-panel">
      <div class="status-toolbar">
        <div class="status-filters">
          <button class="filter-chip active" type="button" data-filter="all">All</button>
          <button class="filter-chip" type="button" data-filter="pending">Pending</button>
          <button class="filter-chip" type="button" data-filter="approved">Approved</button>
          <button class="filter-chip" type="button" data-filter="correction">Needs correction</button>
          <button class="filter-chip" type="button" data-filter="rejected">Rejected</button>
          <button class="filter-chip" type="button" data-filter="cancelled">Cancelled</button>
        </div>
        <div class="status-search">
          <i class="bi bi-search"></i>
          <input id="applicationSearch" type="search" placeholder="Search application, ID or applicant...">
        </div>
      </div>

      <div class="status-table-wrap">
        <table class="status-table">
          <thead>
            <tr>
              <th>Application ID</th>
              <th>Type</th>
              <th>Application</th>
              <th>Applicant</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="applicationRows"></tbody>
        </table>
      </div>

      <div id="emptyState" class="status-empty d-none">
        <div class="empty-icon"><i class="bi bi-clipboard2-check"></i></div>
        <h3>No applications yet</h3>
        <p>Submit a form from the Tasks page and it will automatically appear here with a pending review status.</p>
        <a class="empty-action" href="task.php">
          Go to tasks <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </section>
  </div>
</main>

<div id="successToast" class="success-toast">
  <i class="bi bi-check-circle-fill"></i>
  <span id="successToastText">Application submitted successfully and added to Application Status.</span>
</div>

<div id="errorToast" class="success-toast status-error-toast">
  <i class="bi bi-exclamation-circle-fill"></i>
  <span id="errorToastText">Cancellation reason must be at least 5 characters.</span>
</div>

<div class="modal fade status-modal" id="applicationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="modal-kicker" id="modalApplicationId">Application</div>
          <h2 class="modal-title mt-1" id="modalApplicationTitle">Application details</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span id="modalStatus" class="status-pill status-pending"><i class="bi bi-clock-history"></i>Pending Review</span>
        </div>

        <div class="modal-summary">
          <div class="modal-summary-item"><span>Type</span><strong id="modalType">Not available</strong></div>
          <div class="modal-summary-item"><span>Applicant</span><strong id="modalApplicant">Not available</strong></div>
          <div class="modal-summary-item"><span>Submitted</span><strong id="modalSubmitted">Not available</strong></div>
          <div class="modal-summary-item"><span>Application date</span><strong id="modalApplicationDate">Not available</strong></div>
          <div class="modal-summary-item"><span>Workflow stage</span><strong id="modalWorkflowStage">Not available</strong></div>
          <div class="modal-summary-item"><span>Review note</span><strong id="modalReviewNote">Not available</strong></div>
        </div>

        <div class="detail-grid" id="modalFields"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade status-modal cancel-application-modal" id="cancelApplicationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="cancelApplicationForm" method="post" action="cancel_application.php">
        <?= csrf_field() ?>
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
              <strong>This submission will be cancelled.</strong>
              <p>The record will remain in your history as Cancelled. A cancelled PKK can be submitted again from its linked BPL course.</p>
            </div>
          </div>

          <input type="hidden" name="application_no" id="cancelApplicationNo">

          <label class="cancel-reason-field">
            <span>Reason for cancellation <b>*</b></span>
            <textarea
              name="cancellation_reason"
              id="cancelReason"
              rows="4"
              minlength="5"
              maxlength="500"
              required
              placeholder="Example: Training date changed / application no longer required."
            ></textarea>
            <small>Minimum 5 characters. This reason will be kept in the application history.</small>
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="sts-secondary-btn" data-bs-dismiss="modal">Keep application</button>
          <button type="submit" class="sts-danger-btn">
            <i class="bi bi-x-circle"></i> Cancel application
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.SEDCO_APPLICATIONS = <?= json_encode(
    $applications,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>;
</script>
<script src="application-status.js?v=20261006-03"></script>
<script src="sedco-shell.js?v=20261007-02"></script>
</body>
</html>