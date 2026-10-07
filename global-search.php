<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$role = normalized_role($user['role'] ?? '');
$userId = (int) ($user['id'] ?? 0);
$query = trim((string) ($_GET['q'] ?? ''));
$type = strtoupper(trim((string) ($_GET['type'] ?? '')));
$status = strtolower(trim((string) ($_GET['status'] ?? '')));

$results = [];

if ($query !== '') {
    $like = '%' . $query . '%';

    $sql = 'SELECT
              a.id,
              a.application_no,
              a.user_id,
              a.form_type,
              a.title,
              a.department,
              a.assigned_hod_id,
              a.status,
              a.current_stage,
              a.submitted_at,
              a.updated_at,
              u.fullname
            FROM applications a
            INNER JOIN users u ON u.id = a.user_id
            WHERE (
              a.application_no LIKE ?
              OR a.title LIKE ?
              OR COALESCE(a.department, "") LIKE ?
              OR u.fullname LIKE ?
              OR a.form_type LIKE ?
              OR a.status LIKE ?
            )';

    $params = [$like,$like,$like,$like,$like,$like];
    $types = 'ssssss';

    if (in_array($type, ['BPL','PKK','TEA'], true)) {
        $sql .= ' AND a.form_type = ?';
        $params[] = $type;
        $types .= 's';
    }

    if (in_array($status, ['pending','approved','correction','rejected','cancelled'], true)) {
        $sql .= ' AND a.status = ?';
        $params[] = $status;
        $types .= 's';
    }

    if ($role === 'staff') {
        $sql .= ' AND a.user_id = ?';
        $params[] = $userId;
        $types .= 'i';
    }

    $sql .= ' ORDER BY a.updated_at DESC, a.submitted_at DESC LIMIT 100';

    $stmt = db()->prepare($sql);
    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $isOwner = (int) $row['user_id'] === $userId;

        if (!$isOwner && !sts_can_view_application($row, $user)) {
            continue;
        }

        $results[] = $row;

        if (count($results) >= 50) {
            break;
        }
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: Search</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261007-07">
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-14">
</head>
<body class="app-page global-search-page" data-page="global-search" data-role="<?= e($role) ?>">
<main class="sts-page-content">
  <div class="sts-page-shell">
    <header class="sts-page-heading global-search-heading">
      <div>
        <div class="sts-eyebrow">Find anything</div>
        <h1>Search STS</h1>
        <p>Search application number, applicant, training title, department, form type or status.</p>
      </div>
    </header>

    <form class="global-search-form" method="get">
      <div class="global-search-input">
        <i class="bi bi-search"></i>
        <input
          type="search"
          name="q"
          value="<?= e($query) ?>"
          placeholder="Try APP-2026, staff name, course title or department..."
          autocomplete="off"
          autofocus
        >
        <?php if ($query !== ''): ?>
        <a href="global-search.php" aria-label="Clear search"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
      <select name="type" aria-label="Filter form type">
        <option value="">All forms</option>
        <?php foreach (['BPL','PKK','TEA'] as $option): ?>
        <option value="<?= e($option) ?>" <?= $type === $option ? 'selected' : '' ?>><?= e($option) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" aria-label="Filter status">
        <option value="">All statuses</option>
        <?php foreach ([
          'pending'=>'Pending',
          'approved'=>'Approved',
          'correction'=>'Needs correction',
          'rejected'=>'Rejected',
          'cancelled'=>'Cancelled',
        ] as $value=>$label): ?>
        <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="sts-primary-btn" type="submit">Search</button>
    </form>

    <?php if ($query === ''): ?>
    <section class="global-search-empty">
      <span><i class="bi bi-search"></i></span>
      <h2>Search your training workspace</h2>
      <p>Use one keyword to find an application without opening multiple pages.</p>
      <div>
        <em>Application no.</em><em>Applicant</em><em>Course</em><em>Department</em>
      </div>
    </section>
    <?php else: ?>
    <div class="global-search-meta">
      <strong><?= count($results) ?></strong>
      <span>result<?= count($results) === 1 ? '' : 's' ?> for “<?= e($query) ?>”</span>
    </div>

    <section class="global-search-results">
      <?php foreach ($results as $item): ?>
      <?php
        $itemStatus = strtolower((string) $item['status']);
        $updated = !empty($item['updated_at']) ? strtotime((string) $item['updated_at']) : false;
      ?>
      <a class="global-search-result" href="application-detail.php?application=<?= rawurlencode((string) $item['application_no']) ?>">
        <span class="global-search-result-icon"><i class="bi bi-file-earmark-text"></i></span>
        <div class="global-search-result-copy">
          <div>
            <strong><?= e((string) $item['title']) ?></strong>
            <span><?= e((string) $item['application_no']) ?></span>
          </div>
          <p>
            <?= e((string) $item['fullname']) ?> ·
            <?= e((string) ($item['department'] ?: 'No department')) ?> ·
            <?= e((string) $item['form_type']) ?>
          </p>
        </div>
        <div class="global-search-result-meta">
          <span class="status-pill status-<?= e($itemStatus) ?>"><?= e(ucfirst($itemStatus)) ?></span>
          <small><?= e(stage_label((string) $item['current_stage'])) ?></small>
          <?php if ($updated): ?><time><?= e(date('d M Y', $updated)) ?></time><?php endif; ?>
        </div>
        <i class="bi bi-chevron-right"></i>
      </a>
      <?php endforeach; ?>

      <?php if (!$results): ?>
      <div class="global-search-no-results">
        <span><i class="bi bi-search"></i></span>
        <strong>No matching records</strong>
        <p>Try a shorter keyword, remove a filter or search using the application number.</p>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
</main>
<script src="sedco-shell.js?v=20261007-05"></script>
</body>
</html>
