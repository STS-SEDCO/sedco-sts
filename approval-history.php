<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user || !user_can_review_applications($user)) {
    header('Location: dashboard.php');
    exit;
}

$role = normalized_role($user['role'] ?? '');
$userId = (int) ($user['id'] ?? 0);
$history = [];
$historyUnavailable = false;

try {
    $baseSql = '
        SELECT r.id, r.review_stage, r.decision, r.note, r.reviewed_at,
               a.application_no, a.title, a.department, a.status, a.current_stage,
               applicant.fullname AS applicant_name,
               reviewer.fullname AS reviewer_name
        FROM application_reviews r
        INNER JOIN applications a ON a.id = r.application_id
        INNER JOIN users applicant ON applicant.id = a.user_id
        INNER JOIN users reviewer ON reviewer.id = r.reviewer_id
        WHERE a.form_type = "BPL"
    ';

    if ($role === 'admin') {
        $stmt = db()->prepare($baseSql . ' ORDER BY r.reviewed_at DESC, r.id DESC');
    } else {
        $stmt = db()->prepare(
            $baseSql . ' AND r.reviewer_id = ? ORDER BY r.reviewed_at DESC, r.id DESC'
        );
        $stmt->bind_param('i', $userId);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    $stmt->close();
} catch (Throwable $error) {
    $historyUnavailable = true;
}

$stats = [
    'total' => count($history),
    'approved' => 0,
    'correction' => 0,
    'rejected' => 0,
];

foreach ($history as $item) {
    $decision = (string) ($item['decision'] ?? '');
    if (isset($stats[$decision])) {
        $stats[$decision]++;
    }
}

function approval_decision_label(string $decision): string
{
    return match ($decision) {
        'approved' => 'Approved',
        'correction' => 'Correction Requested',
        'rejected' => 'Rejected',
        default => ucfirst($decision),
    };
}

function approval_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'approved' => 'Approved',
        'correction' => 'Needs Correction',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        default => ucfirst($status),
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: Approval History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261006-06">
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-02">
</head>
<body class="app-page submissions-page approval-history-page" data-page="approval-history" data-role="<?= e($role) ?>">
<main class="submissions-content">
  <div class="submissions-shell">
    <header class="submissions-heading approval-history-heading">
      <div>
        <div class="submissions-eyebrow">Review records</div>
        <h1>Approval History</h1>
        <p>Search and audit every approval, correction request and rejection made from your review workspace.</p>
      </div>
      <div class="submissions-heading-actions">
        <a class="submissions-primary-action" href="submissions.php">
          <i class="bi bi-inbox"></i>
          Open Approval Queue
        </a>
        <a class="submissions-secondary-action" href="dashboard.php">
          <i class="bi bi-grid-1x2"></i>
          Dashboard
        </a>
      </div>
    </header>

    <div class="submissions-meta-row">
      <div>
        <span class="submissions-section-label">Decision history</span>
        <span class="submissions-section-note">Reviewer actions are retained for audit and remain linked to the application record.</span>
      </div>
      <span class="submissions-sync-chip"><span></span>Live records</span>
    </div>

    <section class="submissions-stats approval-history-stats">
      <article class="submission-stat-card stat-total" data-history-stat="all">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-clock-history"></i></span>
          <span class="submission-stat-caption">History</span>
        </div>
        <strong><?= (int) $stats['total'] ?></strong>
        <span>Total review actions</span>
      </article>

      <article class="submission-stat-card stat-reviewed" data-history-stat="approved">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-check2-circle"></i></span>
          <span class="submission-stat-caption">Approved</span>
        </div>
        <strong><?= (int) $stats['approved'] ?></strong>
        <span>Approval decisions</span>
      </article>

      <article class="submission-stat-card stat-pending" data-history-stat="correction">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-arrow-counterclockwise"></i></span>
          <span class="submission-stat-caption">Correction</span>
        </div>
        <strong><?= (int) $stats['correction'] ?></strong>
        <span>Returned for correction</span>
      </article>

      <article class="submission-stat-card stat-week" data-history-stat="rejected">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-x-circle"></i></span>
          <span class="submission-stat-caption">Rejected</span>
        </div>
        <strong><?= (int) $stats['rejected'] ?></strong>
        <span>Rejected applications</span>
      </article>
    </section>

    <?php if ($historyUnavailable): ?>
    <div class="approval-history-notice">
      <i class="bi bi-database-exclamation"></i>
      <div>
        <strong>Approval history is temporarily unavailable.</strong>
        <span>Please check the STS database sync before using the audit view.</span>
      </div>
    </div>
    <?php endif; ?>

    <section class="submissions-panel approval-history-panel">
      <div class="submissions-toolbar approval-history-toolbar">
        <div class="submissions-filter-group" aria-label="Filter by decision">
          <button class="submission-filter active" type="button" data-history-decision="all">All</button>
          <button class="submission-filter" type="button" data-history-decision="approved">Approved</button>
          <button class="submission-filter" type="button" data-history-decision="correction">Correction</button>
          <button class="submission-filter" type="button" data-history-decision="rejected">Rejected</button>
        </div>

        <div class="submissions-advanced-filters approval-history-advanced">
          <?php if ($role === 'admin'): ?>
          <select id="historyStage" aria-label="Filter by approval stage">
            <option value="all">All stages</option>
            <option value="training">Training Department</option>
            <option value="hod">Head of Department</option>
            <option value="gm">General Manager</option>
            <option value="chairman">Pengerusi</option>
            <option value="finance">Kewangan</option>
          </select>
          <?php endif; ?>

          <?php if ($role !== 'head_of_department'): ?>
          <select id="historyDepartment" aria-label="Filter by department">
            <option value="all">All departments</option>
          </select>
          <?php endif; ?>

          <input id="historyDate" type="date" aria-label="Filter by review date">

          <button type="button" class="approval-filter-reset" id="historyReset">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
          </button>
        </div>

        <div class="submissions-search approval-history-search">
          <i class="bi bi-search"></i>
          <input id="historySearch" type="search" placeholder="Search reference, applicant, reviewer or note...">
        </div>
      </div>

      <div class="approval-history-resultbar">
        <div>
          <strong id="historyResultCount"><?= count($history) ?></strong>
          <span>records shown</span>
        </div>
        <small>Tip: correction records stay linked even after the applicant resubmits.</small>
      </div>

      <div class="submissions-table-wrap">
        <table class="submissions-table approval-history-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Applicant</th>
              <th>Department</th>
              <th>Review Stage</th>
              <th>Decision</th>
              <th>Current Status</th>
              <th>Review Note</th>
              <th>Reviewer</th>
              <th>Reviewed</th>
              <th class="submission-actions-heading">Actions</th>
            </tr>
          </thead>
          <tbody id="historyRows">
          <?php foreach ($history as $item): ?>
            <?php
              $reviewDate = date('Y-m-d', strtotime((string) $item['reviewed_at']));
              $searchText = strtolower(trim(implode(' ', [
                  (string) $item['application_no'],
                  (string) $item['title'],
                  (string) $item['applicant_name'],
                  (string) ($item['department'] ?? ''),
                  (string) $item['reviewer_name'],
                  (string) ($item['note'] ?? ''),
                  stage_label((string) $item['review_stage']),
                  approval_decision_label((string) $item['decision']),
              ])));
            ?>
            <tr
              data-history-row
              data-decision="<?= e((string) $item['decision']) ?>"
              data-stage="<?= e((string) $item['review_stage']) ?>"
              data-department="<?= e((string) ($item['department'] ?: 'Not assigned')) ?>"
              data-date="<?= e($reviewDate) ?>"
              data-search="<?= e($searchText) ?>"
            >
              <td>
                <strong><?= e((string) $item['application_no']) ?></strong>
                <small><?= e((string) $item['title']) ?></small>
              </td>
              <td>
                <strong><?= e((string) $item['applicant_name']) ?></strong>
                <small>Applicant</small>
              </td>
              <td><?= e((string) ($item['department'] ?: 'Not assigned')) ?></td>
              <td>
                <span class="submission-stage-pill stage-<?= e((string) $item['review_stage']) ?>">
                  <i class="bi bi-diagram-3"></i><?= e(stage_label((string) $item['review_stage'])) ?>
                </span>
              </td>
              <td>
                <span class="status-pill status-<?= e((string) $item['decision']) ?>">
                  <?= e(approval_decision_label((string) $item['decision'])) ?>
                </span>
              </td>
              <td>
                <span class="status-pill status-<?= e((string) $item['status']) ?>">
                  <?= e(approval_status_label((string) $item['status'])) ?>
                </span>
              </td>
              <td class="approval-history-note-cell" title="<?= e((string) ($item['note'] ?: 'No review note')) ?>">
                <?= e((string) ($item['note'] ?: 'No review note')) ?>
              </td>
              <td>
                <strong><?= e((string) $item['reviewer_name']) ?></strong>
                <small><?= e(stage_label((string) $item['review_stage'])) ?></small>
              </td>
              <td class="is-history-date">
                <strong><?= e(date('d M Y', strtotime((string) $item['reviewed_at']))) ?></strong>
                <small><?= e(date('g:i A', strtotime((string) $item['reviewed_at']))) ?></small>
              </td>
              <td class="text-end">
                <div class="approval-history-actions">
                  <a class="view-btn" href="application-detail.php?application=<?= rawurlencode((string) $item['application_no']) ?>">
                    <i class="bi bi-eye"></i> View
                  </a>
                  <a class="view-btn is-print" href="application-print.php?application=<?= rawurlencode((string) $item['application_no']) ?>" target="_blank">
                    <i class="bi bi-printer"></i> Print
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div id="historyEmpty" class="submission-empty<?= $history ? ' d-none' : '' ?>">
        <div class="submission-empty-visual">
          <div class="submission-empty-icon"><i class="bi bi-clock-history"></i></div>
          <span class="submission-empty-orbit"></span>
        </div>
        <h3 id="historyEmptyTitle"><?= $history ? 'No records match your filters' : 'No approval history yet' ?></h3>
        <p id="historyEmptyCopy"><?= $history ? 'Try clearing one or more filters to see other review records.' : 'Your approval decisions will appear here after you review a BPL application.' ?></p>
        <a href="submissions.php" class="submission-empty-action">
          Go to Approval Queue <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </section>
  </div>
</main>

<script>
(() => {
  const rows = [...document.querySelectorAll('[data-history-row]')];
  const decisionButtons = [...document.querySelectorAll('[data-history-decision]')];
  const stage = document.getElementById('historyStage');
  const department = document.getElementById('historyDepartment');
  const date = document.getElementById('historyDate');
  const search = document.getElementById('historySearch');
  const reset = document.getElementById('historyReset');
  const count = document.getElementById('historyResultCount');
  const empty = document.getElementById('historyEmpty');
  const emptyTitle = document.getElementById('historyEmptyTitle');
  const emptyCopy = document.getElementById('historyEmptyCopy');
  let decision = 'all';

  if (department) {
    [...new Set(rows.map(row => row.dataset.department).filter(Boolean))]
      .sort((a,b) => a.localeCompare(b))
      .forEach(value => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        department.appendChild(option);
      });
  }

  const apply = () => {
    const q = String(search?.value || '').trim().toLowerCase();
    const stageValue = stage?.value || 'all';
    const departmentValue = department?.value || 'all';
    const dateValue = date?.value || '';
    let visible = 0;

    rows.forEach(row => {
      const show =
        (decision === 'all' || row.dataset.decision === decision)
        && (stageValue === 'all' || row.dataset.stage === stageValue)
        && (departmentValue === 'all' || row.dataset.department === departmentValue)
        && (!dateValue || row.dataset.date === dateValue)
        && (!q || String(row.dataset.search || '').includes(q));

      row.hidden = !show;
      if (show) visible++;
    });

    if (count) count.textContent = String(visible);
    if (empty) {
      empty.classList.toggle('d-none', visible > 0);
      if (visible === 0 && rows.length) {
        if (emptyTitle) emptyTitle.textContent = 'No records match your filters';
        if (emptyCopy) emptyCopy.textContent = 'Try changing the decision, stage, department, date or search filter.';
      }
    }
  };

  decisionButtons.forEach(button => {
    button.addEventListener('click', () => {
      decision = button.dataset.historyDecision || 'all';
      decisionButtons.forEach(item => item.classList.toggle('active', item === button));
      apply();
    });
  });

  [stage, department, date].forEach(control => control?.addEventListener('change', apply));
  search?.addEventListener('input', apply);

  reset?.addEventListener('click', () => {
    decision = 'all';
    decisionButtons.forEach(button => button.classList.toggle('active', button.dataset.historyDecision === 'all'));
    if (stage) stage.value = 'all';
    if (department) department.value = 'all';
    if (date) date.value = '';
    if (search) search.value = '';
    apply();
    search?.focus();
  });

  document.querySelectorAll('[data-history-stat]').forEach(card => {
    card.addEventListener('click', () => {
      const value = card.dataset.historyStat || 'all';
      const button = decisionButtons.find(item => item.dataset.historyDecision === value);
      if (button) button.click();
    });
  });

  apply();
})();
</script>
<script src="sedco-shell.js?v=20261007-02"></script>
</body>
</html>
