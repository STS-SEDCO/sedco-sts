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
$reviewerStage = review_stage_for_role($user['role'] ?? '');
$reviewerStageLabel = $reviewerStage ? stage_label($reviewerStage) : 'All approval stages';
$approvalStageOrder = ['training', 'hod', 'gm', 'chairman', 'finance'];
$reviewerStageIndex = $reviewerStage !== null
    ? array_search($reviewerStage, $approvalStageOrder, true)
    : false;

if (!user_can_review_applications($user)) {
    header('Location: application-status.php');
    exit;
}

sts_repair_pending_bpl_stages();

$stmt = db()->prepare(
    'SELECT a.id, a.application_no, a.user_id, a.form_type, a.title, a.payload, a.status,
            a.department, a.assigned_hod_id, a.current_stage, a.review_note,
            a.sla_due_at, a.submitted_at, a.updated_at, u.fullname
     FROM applications a
     INNER JOIN users u ON u.id = a.user_id
     WHERE a.form_type = "BPL"
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
    if (!sts_can_review_application($row, $user)) {
        continue;
    }

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
        'department' => $row['department'] ?: 'Unassigned',
        'currentStage' => $row['current_stage'],
        'stageLabel' => stage_label($row['current_stage']),
        'reviewNote' => $row['review_note'],
        'slaDueAt' => $row['sla_due_at'],
        'overdue' => !empty($row['sla_due_at'])
            && strtotime((string) $row['sla_due_at']) < time()
            && $row['status'] === 'pending',
        'canReview' => $row['form_type'] === 'BPL'
            && sts_can_review_application($row, $user)
            && !in_array($row['status'], ['approved', 'rejected', 'cancelled'], true)
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
  <title>Smart Training System: Approval</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261005-01">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-56">
</head>
<body class="app-page submissions-page" data-page="submissions" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">

<main class="submissions-content">
  <div class="submissions-shell">
    <header class="submissions-heading">
      <div>
        <div class="submissions-eyebrow">Approval workspace</div>
        <h1>Approval</h1>
        <p>Review, approve or return BPL applications assigned to your current approval stage.</p>
      </div>

      <div class="submissions-heading-actions">
        <a class="submissions-secondary-action" href="export_applications.php?type=BPL">
          <i class="bi bi-file-earmark-spreadsheet"></i>
          Export CSV
        </a>
        <a class="submissions-secondary-action" href="approval-history.php">
          <i class="bi bi-clock-history"></i>
          Approval History
        </a>
      </div>
    </header>

    <section class="approval-lifecycle" aria-label="BPL approval lifecycle">
      <div class="approval-lifecycle-intro">
        <span>Approval lifecycle</span>
        <strong>Sequential governance</strong>
        <small>Every application advances only after the previous required stage is approved.</small>
      </div>

      <div class="approval-lifecycle-track">
        <?php foreach ([
          'training' => ['Training', 'bi-briefcase'],
          'hod' => ['HOD', 'bi-person-check'],
          'gm' => ['GM', 'bi-person-badge'],
          'chairman' => ['Pengerusi', 'bi-award'],
          'finance' => ['Kewangan', 'bi-cash-stack'],
        ] as $stageKey => [$stageName, $stageIcon]): ?>
          <?php
            $stageIndex = array_search($stageKey, $approvalStageOrder, true);
            $stageState = '';

            if ($reviewerStageIndex !== false && $stageIndex !== false) {
                if ($stageIndex < $reviewerStageIndex) {
                    $stageState = ' is-passed';
                } elseif ($stageIndex === $reviewerStageIndex) {
                    $stageState = ' is-current';
                } else {
                    $stageState = ' is-upcoming';
                }
            }

            $stageCaption = match ($stageState) {
                ' is-passed' => 'Passed',
                ' is-current' => 'Your stage',
                ' is-upcoming' => 'Upcoming',
                default => 'Stage',
            };
          ?>
          <div class="approval-flow-step<?= e($stageState) ?>">
            <span class="approval-flow-icon">
              <i class="bi <?= $stageState === ' is-passed' ? 'bi-check2' : e($stageIcon) ?>"></i>
            </span>
            <div>
              <small><?= e($stageCaption) ?></small>
              <strong><?= e($stageName) ?></strong>
            </div>
          </div>
          <?php if ($stageKey !== 'finance'): ?>
            <span class="approval-flow-arrow"><i class="bi bi-chevron-right"></i></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <div class="approval-lifecycle-scope">
        <i class="bi bi-shield-lock"></i>
        <div>
          <span>Current queue scope</span>
          <strong><?= e($reviewerStageLabel) ?></strong>
        </div>
      </div>
    </section>

    <div class="submissions-meta-row">
      <div>
        <span class="submissions-section-label">Approval queue</span>
        <span class="submissions-section-note">Applications currently assigned to you</span>
      </div>
      <span class="submissions-sync-chip"><span></span>Synced</span>
    </div>

    <section class="submissions-stats">
      <article class="submission-stat-card stat-total">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-inbox"></i></span>
          <span class="submission-stat-caption">Assigned</span>
        </div>
        <strong id="submissionTotal">0</strong>
        <span>Assigned to you</span>
      </article>

      <article class="submission-stat-card stat-pending">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-hourglass-split"></i></span>
          <span class="submission-stat-caption">Pending</span>
        </div>
        <strong id="submissionPending">0</strong>
        <span>Pending review</span>
      </article>

      <article class="submission-stat-card stat-reviewed">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-check2-circle"></i></span>
          <span class="submission-stat-caption">SLA</span>
        </div>
        <strong id="submissionReviewed">0</strong>
        <span>Overdue review</span>
      </article>

      <article class="submission-stat-card stat-week">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-calendar3"></i></span>
          <span class="submission-stat-caption">Recent</span>
        </div>
        <strong id="submissionWeek">0</strong>
        <span>Received this week</span>
      </article>
    </section>

    <section class="submissions-panel">
      <div class="submissions-toolbar">
        <div class="submissions-filter-group">
          <button class="submission-filter active" type="button" data-submission-filter="all">All</button>
          <button class="submission-filter" type="button" data-submission-filter="pending">Pending</button>
        </div>

        <div class="submissions-advanced-filters">
          <select id="submissionStage" aria-label="Filter by stage">
            <option value="all">All stages</option>
            <option value="training">Training Department</option>
            <option value="hod">Head of Department</option>
            <option value="gm">General Manager</option>
            <option value="chairman">Pengerusi</option>
            <option value="finance">Kewangan</option>
            <option value="completed">Completed</option>
          </select>
          <select id="submissionDepartment" aria-label="Filter by department">
            <option value="all">All departments</option>
          </select>
          <input id="submissionDate" type="date" aria-label="Filter by submitted date">
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
              <th class="submission-actions-heading">Actions</th>
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
        <h3>No approvals waiting</h3>
        <p>There are currently no BPL applications waiting for your review.</p>
        <a href="approval-history.php" class="submission-empty-action">
          View approval history <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </section>
  </div>
</main>

<div class="modal fade submissions-modal approval-detail-modal" id="submissionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header approval-detail-header">
        <div class="approval-detail-heading">
          <div class="submission-modal-kicker" id="submissionModalRef">Submission</div>
          <h2 class="modal-title" id="submissionModalTitle">Submission details</h2>
          <p>Review the application information before making a decision.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body approval-detail-body">
        <div class="approval-detail-topbar">
          <div class="approval-detail-badges">
            <span id="submissionModalStatus" class="status-pill status-pending">
              <i class="bi bi-clock-history"></i>Pending review
            </span>
            <span id="submissionModalStageBadge" class="submission-stage-pill stage-none">
              <i class="bi bi-diagram-3"></i><span id="submissionModalStage">Not available</span>
            </span>
          </div>
          <span class="approval-detail-sync"><i class="bi bi-shield-check"></i> Ready for review</span>
        </div>

        <div class="submission-modal-summary approval-detail-summary">
          <div>
            <span><i class="bi bi-person"></i> Applicant</span>
            <strong id="submissionModalApplicant">Not available</strong>
          </div>
          <div>
            <span><i class="bi bi-file-earmark-text"></i> Form Type</span>
            <strong id="submissionModalType">Not available</strong>
          </div>
          <div>
            <span><i class="bi bi-calendar3"></i> Submitted</span>
            <strong id="submissionModalDate">Not available</strong>
          </div>
          <div>
            <span><i class="bi bi-building"></i> Department</span>
            <strong id="submissionModalDepartment">Not available</strong>
          </div>
        </div>

        <div class="approval-detail-section-heading">
          <div>
            <span>Application details</span>
            <h3>Submitted information</h3>
          </div>
          <small>Information provided by the applicant</small>
        </div>

        <div id="submissionModalFields" class="submission-detail-grid approval-detail-grid"></div>
      </div>

      <div class="modal-footer approval-detail-footer">
        <a id="submissionModalOpenForm" class="approval-detail-open-form" href="#">
          <i class="bi bi-box-arrow-up-right"></i>
          <span>Open Full Form</span>
        </a>

        <div class="approval-detail-actions" id="submissionModalActions">
          <button type="button" class="approval-detail-action is-approve" data-detail-review-decision="approved">
            <i class="bi bi-check2"></i><span>Approve</span>
          </button>
          <button type="button" class="approval-detail-action is-correction" data-detail-review-decision="correction">
            <i class="bi bi-arrow-counterclockwise"></i><span>Request Correction</span>
          </button>
          <button type="button" class="approval-detail-action is-reject" data-detail-review-decision="rejected">
            <i class="bi bi-x-lg"></i><span>Reject</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade submissions-modal quick-review-modal" id="quickReviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="quickReviewForm" method="post" action="review_application.php">
        <?= csrf_field() ?>
        <input type="hidden" name="application_no" id="quickReviewApplication">
        <input type="hidden" name="decision" id="quickReviewDecision">

        <div class="modal-header">
          <div>
            <div class="submission-modal-kicker" id="quickReviewRef">BPL Review</div>
            <h2 class="modal-title mt-1" id="quickReviewTitle">Review application</h2>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="quick-review-context">
            <div>
              <span>Applicant</span>
              <strong id="quickReviewApplicant">Not available</strong>
            </div>
            <div>
              <span>Current stage</span>
              <strong id="quickReviewStage">Not available</strong>
            </div>
          </div>

          <div class="quick-review-decision" id="quickReviewDecisionBadge"></div>

          <div id="quickReviewFields" class="quick-review-fields"></div>

          <div class="quick-review-note-wrap">
            <label for="quickReviewComment">Additional review note <span>Optional</span></label>
            <textarea
              id="quickReviewComment"
              name="review_comment"
              rows="3"
              maxlength="1000"
              placeholder="Add a short note for this decision if needed"
            ></textarea>
          </div>

          <div class="quick-review-hint" id="quickReviewHint"></div>
        </div>

        <div class="modal-footer">
          <a id="quickReviewOpenForm" class="quick-review-open-form" href="#">
            <i class="bi bi-box-arrow-up-right"></i> View full form
          </a>
          <div class="quick-review-footer-actions">
            <button type="button" class="quick-review-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="quick-review-submit" id="quickReviewSubmit">
              Confirm
            </button>
          </div>
        </div>
      </form>
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
<script src="submissions.js?v=20261003-05"></script>
<script src="sedco-shell.js?v=20261003-02"></script>
</body>
</html>