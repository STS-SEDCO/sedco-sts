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
$decisionFilter = strtolower(trim((string) ($_GET['decision'] ?? 'all')));
$allowedFilters = ['all', 'approved', 'correction', 'rejected'];

if (!in_array($decisionFilter, $allowedFilters, true)) {
    $decisionFilter = 'all';
}

$history = [];
$historyUnavailable = false;

try {
    $baseSql = '
        SELECT r.id, r.review_stage, r.decision, r.note, r.reviewed_at,
               a.application_no, a.title, a.department, a.status,
               applicant.fullname AS applicant_name,
               reviewer.fullname AS reviewer_name
        FROM application_reviews r
        INNER JOIN applications a ON a.id = r.application_id
        INNER JOIN users applicant ON applicant.id = a.user_id
        INNER JOIN users reviewer ON reviewer.id = r.reviewer_id
        WHERE a.form_type = "BPL"
    ';

    if ($role === 'admin' && $decisionFilter === 'all') {
        $stmt = db()->prepare($baseSql . ' ORDER BY r.reviewed_at DESC, r.id DESC');
    } elseif ($role === 'admin') {
        $stmt = db()->prepare(
            $baseSql . ' AND r.decision = ? ORDER BY r.reviewed_at DESC, r.id DESC'
        );
        $stmt->bind_param('s', $decisionFilter);
    } elseif ($decisionFilter === 'all') {
        $stmt = db()->prepare(
            $baseSql . ' AND r.reviewer_id = ? ORDER BY r.reviewed_at DESC, r.id DESC'
        );
        $stmt->bind_param('i', $userId);
    } else {
        $stmt = db()->prepare(
            $baseSql . ' AND r.reviewer_id = ? AND r.decision = ?
                         ORDER BY r.reviewed_at DESC, r.id DESC'
        );
        $stmt->bind_param('is', $userId, $decisionFilter);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: Approval History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261003-08">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-56">
</head>
<body class="app-page submissions-page" data-page="approval-history" data-role="<?= e($role) ?>">
<main class="submissions-content">
  <div class="submissions-shell">
    <header class="submissions-heading">
      <div>
        <div class="submissions-eyebrow">Review records</div>
        <h1>Approval History</h1>
        <p>Review your previous approval decisions, correction requests and rejection records.</p>
      </div>
      <div class="submissions-heading-actions">
        <a class="submissions-primary-action" href="submissions.php">
          <i class="bi bi-check2-square"></i>
          Approval
        </a>
      </div>
    </header>

    <div class="submissions-meta-row">
      <div>
        <span class="submissions-section-label">Decision history</span>
        <span class="submissions-section-note">Every reviewer action is retained for reference and audit</span>
      </div>
    </div>

    <section class="submissions-stats">
      <article class="submission-stat-card stat-total">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-clock-history"></i></span>
          <span class="submission-stat-caption">History</span>
        </div>
        <strong><?= (int) $stats['total'] ?></strong>
        <span>Total actions</span>
      </article>

      <article class="submission-stat-card stat-reviewed">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-check2-circle"></i></span>
          <span class="submission-stat-caption">Approved</span>
        </div>
        <strong><?= (int) $stats['approved'] ?></strong>
        <span>Approved</span>
      </article>

      <article class="submission-stat-card stat-pending">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-arrow-counterclockwise"></i></span>
          <span class="submission-stat-caption">Returned</span>
        </div>
        <strong><?= (int) $stats['correction'] ?></strong>
        <span>Correction requests</span>
      </article>

      <article class="submission-stat-card stat-week">
        <div class="submission-stat-top">
          <span class="submission-stat-icon"><i class="bi bi-x-circle"></i></span>
          <span class="submission-stat-caption">Rejected</span>
        </div>
        <strong><?= (int) $stats['rejected'] ?></strong>
        <span>Rejected</span>
      </article>
    </section>

    <?php if ($historyUnavailable): ?>
    <div class="approval-history-notice">
      <i class="bi bi-database-exclamation"></i>
      <div>
        <strong>Approval History is ready for the new database structure.</strong>
        <span>Import the full STS database sync when you are ready to enable live approval records.</span>
      </div>
    </div>
    <?php endif; ?>

    <section class="submissions-panel">
      <div class="submissions-toolbar">
        <div class="submissions-filter-group">
          <?php foreach ([
            'all' => 'All',
            'approved' => 'Approved',
            'correction' => 'Correction Requested',
            'rejected' => 'Rejected'
          ] as $value => $label): ?>
          <a
            class="submission-filter<?= $decisionFilter === $value ? ' active' : '' ?>"
            href="approval-history.php?decision=<?= e($value) ?>"
          ><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="submissions-table-wrap">
        <table class="submissions-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Applicant</th>
              <th>Department</th>
              <th>Stage</th>
              <th>Decision</th>
              <th>Review Note</th>
              <th>Reviewed</th>
              <th class="submission-actions-heading">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($history as $item): ?>
            <tr>
              <td>
                <strong><?= e((string) $item['application_no']) ?></strong>
                <small><?= e((string) $item['title']) ?></small>
              </td>
              <td><?= e((string) $item['applicant_name']) ?></td>
              <td><?= e((string) ($item['department'] ?: 'Not assigned')) ?></td>
              <td><?= e(stage_label((string) $item['review_stage'])) ?></td>
              <td>
                <span class="status-pill status-<?= e((string) $item['decision']) ?>">
                  <?= e(approval_decision_label((string) $item['decision'])) ?>
                </span>
              </td>
              <td><?= e((string) ($item['note'] ?: 'No review note')) ?></td>
              <td><?= e(date('d M Y, g:i A', strtotime((string) $item['reviewed_at']))) ?></td>
              <td class="text-end">
                <a class="view-btn" href="application-detail.php?application=<?= rawurlencode((string) $item['application_no']) ?>">
                  View <i class="bi bi-arrow-up-right"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if (!$history): ?>
      <div class="submission-empty">
        <div class="submission-empty-visual">
          <div class="submission-empty-icon"><i class="bi bi-clock-history"></i></div>
          <span class="submission-empty-orbit"></span>
        </div>
        <h3>No approval history yet</h3>
        <p>Your approval decisions will appear here after you review a BPL application.</p>
        <a href="submissions.php" class="submission-empty-action">
          Go to Approval <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <?php endif; ?>
    </section>
  </div>
</main>

<script src="sedco-shell.js?v=20261003-02"></script>
</body>
</html>
