<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$viewer = current_user();

if (!$viewer) {
    header('Location: login.php');
    exit;
}

$db = db();
$viewerRole = normalized_role($viewer['role'] ?? '');
$viewerId = (int) ($viewer['id'] ?? 0);
$viewerDepartment = trim((string) ($viewer['department'] ?? ''));

function training_history_can_view(array $viewer, array $employee): bool
{
    $role = normalized_role($viewer['role'] ?? '');

    if ((int) ($viewer['id'] ?? 0) === (int) ($employee['id'] ?? 0)) {
        return true;
    }

    if ($role === 'staff') {
        return false;
    }

    if ($role === 'head_of_department') {
        $viewerDepartment = trim((string) ($viewer['department'] ?? ''));
        $employeeDepartment = trim((string) ($employee['department'] ?? ''));

        return $viewerDepartment !== ''
            && $employeeDepartment !== ''
            && strcasecmp($viewerDepartment, $employeeDepartment) === 0;
    }

    return in_array(
        $role,
        ['admin','training_section','general_manager','pengerusi_besar','finance'],
        true
    );
}

$employeeSql =
    'SELECT u.id,u.fullname,u.email,u.department,u.job_title,u.staff_id,u.role,u.is_active
     FROM users u
     WHERE u.is_active = 1
       AND EXISTS (
         SELECT 1
         FROM applications a
         WHERE a.user_id = u.id
           AND a.form_type = "BPL"
       )';

$employeeParams = [];
$employeeTypes = '';

if ($viewerRole === 'staff') {
    $employeeSql .= ' AND u.id = ?';
    $employeeParams[] = $viewerId;
    $employeeTypes .= 'i';
} elseif ($viewerRole === 'head_of_department') {
    $employeeSql .= ' AND LOWER(TRIM(COALESCE(u.department,""))) = LOWER(TRIM(?))';
    $employeeParams[] = $viewerDepartment;
    $employeeTypes .= 's';
}

$employeeSql .= ' ORDER BY u.fullname ASC';

$employeeStmt = $db->prepare($employeeSql);
if ($employeeParams) {
    $employeeBind = [$employeeTypes];
    foreach ($employeeParams as $index => $value) {
        $employeeBind[] = &$employeeParams[$index];
    }
    call_user_func_array([$employeeStmt,'bind_param'],$employeeBind);
}
$employeeStmt->execute();
$employeeResult = $employeeStmt->get_result();
$employees = [];

while ($row = $employeeResult->fetch_assoc()) {
    $employees[] = $row;
}
$employeeStmt->close();

$requestedEmployeeId = max(0, (int) ($_GET['employee'] ?? 0));

if ($viewerRole === 'staff') {
    $requestedEmployeeId = $viewerId;
} elseif ($requestedEmployeeId === 0 && count($employees) === 1) {
    $requestedEmployeeId = (int) $employees[0]['id'];
}

$employee = null;
foreach ($employees as $candidate) {
    if ((int) $candidate['id'] === $requestedEmployeeId) {
        $employee = $candidate;
        break;
    }
}

if ($requestedEmployeeId > 0 && !$employee) {
    http_response_code(403);
    exit('This employee training history is not available to your role.');
}

$records = [];
$stats = [
    'training' => 0,
    'cost' => 0.0,
    'pkk_completed' => 0,
    'pkk_compliance' => 0.0,
    'tea_completed' => 0,
];
$eligiblePkk = 0;
$onTimePkk = 0;

if ($employee && training_history_can_view($viewer, $employee)) {
    $bplStmt = $db->prepare(
        'SELECT id,application_no,title,payload,status,current_stage,
                training_start,training_end,submitted_at,completed_at
         FROM applications
         WHERE user_id = ?
           AND form_type = "BPL"
         ORDER BY COALESCE(training_start,submitted_at) DESC, id DESC'
    );
    $employeeId = (int) $employee['id'];
    $bplStmt->bind_param('i',$employeeId);
    $bplStmt->execute();
    $bplResult = $bplStmt->get_result();

    $bpls = [];
    $bplIds = [];

    while ($row = $bplResult->fetch_assoc()) {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        $row['payload_decoded'] = is_array($payload) ? $payload : [];
        $bpls[] = $row;
        $bplIds[(int) $row['id']] = true;
    }
    $bplStmt->close();

    $linked = [];
    if ($bplIds) {
        $result = $db->query(
            'SELECT id,user_id,parent_application_id,form_type,title,payload,status,
                    current_stage,submitted_at,completed_at
             FROM applications
             WHERE form_type IN ("PKK","TEA")
               AND status <> "cancelled"
             ORDER BY submitted_at ASC,id ASC'
        );

        while ($row = $result->fetch_assoc()) {
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            $payload = is_array($payload) ? $payload : [];

            $parentIds = [];
            $primaryParent = (int) ($row['parent_application_id'] ?? 0);
            if ($primaryParent > 0) {
                $parentIds[] = $primaryParent;
            }

            if ((string) $row['form_type'] === 'TEA') {
                $extraParents = $payload['evaluated_parent_ids'] ?? [];
                if (!is_array($extraParents)) {
                    $extraParents = [$extraParents];
                }
                foreach ($extraParents as $extraParent) {
                    $extraParent = (int) $extraParent;
                    if ($extraParent > 0) $parentIds[] = $extraParent;
                }
            }

            foreach (array_unique($parentIds) as $parentId) {
                if (!isset($bplIds[$parentId])) continue;
                $row['payload_decoded'] = $payload;
                $linked[$parentId][(string) $row['form_type']][] = $row;
            }
        }
    }

    foreach ($bpls as $bpl) {
        $id = (int) $bpl['id'];
        $payload = $bpl['payload_decoded'];
        $status = (string) $bpl['status'];
        $title = trim((string) ($payload['tajuk'] ?? $bpl['title'] ?? 'Training'));
        $start = trim((string) ($bpl['training_start'] ?? $payload['tarikh_mula'] ?? ''));
        $end = trim((string) ($bpl['training_end'] ?? $payload['tarikh_tamat'] ?? ''));
        $feeText = preg_replace('/[^0-9.]/','',(string) ($payload['yuran'] ?? ''));
        $fee = $feeText !== '' && is_numeric($feeText) ? (float) $feeText : 0.0;

        $pkkList = $linked[$id]['PKK'] ?? [];
        $teaList = $linked[$id]['TEA'] ?? [];
        $pkk = $pkkList ? end($pkkList) : null;
        $tea = $teaList ? end($teaList) : null;

        if ($status === 'approved' && (string) $bpl['current_stage'] === 'completed') {
            $stats['training']++;
            $stats['cost'] += $fee;
        }

        if ($pkk && (string) $pkk['status'] === 'approved' && (string) $pkk['current_stage'] === 'completed') {
            $stats['pkk_completed']++;
        }

        if ($tea && (string) $tea['status'] === 'approved' && (string) $tea['current_stage'] === 'completed') {
            $stats['tea_completed']++;
        }

        $pkkDue = $end !== '' ? sts_followup_due($end,'PKK') : null;
        $endTs = $end !== '' ? strtotime($end . ' 23:59:59') : false;
        $todayTs = strtotime(date('Y-m-d 23:59:59'));

        if (
            $status === 'approved'
            && (string) $bpl['current_stage'] === 'completed'
            && $endTs
            && $endTs <= $todayTs
        ) {
            $eligiblePkk++;

            if ($pkk && !empty($pkk['submitted_at']) && $pkkDue) {
                $pkkSubmittedTs = strtotime((string) $pkk['submitted_at']);
                if ($pkkSubmittedTs && $pkkSubmittedTs <= strtotime($pkkDue)) {
                    $onTimePkk++;
                }
            }
        }

        $lifecycle = 'Application';
        $tone = 'neutral';

        if ($status === 'rejected') {
            $lifecycle = 'Rejected';
            $tone = 'danger';
        } elseif ($status === 'cancelled') {
            $lifecycle = 'Cancelled';
            $tone = 'neutral';
        } elseif ($status === 'correction') {
            $lifecycle = 'Correction required';
            $tone = 'warning';
        } elseif ($status === 'pending') {
            $lifecycle = 'Approval in progress';
            $tone = 'warning';
        } elseif ($start !== '' && strtotime($start) > time()) {
            $lifecycle = 'Upcoming';
            $tone = 'brand';
        } elseif ($end !== '' && strtotime($end . ' 23:59:59') >= time()) {
            $lifecycle = 'In training';
            $tone = 'brand';
        } elseif (!$pkk) {
            $lifecycle = $pkkDue && strtotime($pkkDue) < time() ? 'PKK overdue' : 'PKK due';
            $tone = $pkkDue && strtotime($pkkDue) < time() ? 'danger' : 'warning';
        } elseif (!$tea) {
            $lifecycle = 'Awaiting TEA cycle';
            $tone = 'warning';
        } else {
            $lifecycle = 'Completed';
            $tone = 'success';
        }

        $competency = '';
        if ($tea) {
            $teaPayload = $tea['payload_decoded'];
            foreach ($teaPayload as $key => $value) {
                if (!preg_match('/^training_title_(\d+)$/',$key,$match)) continue;
                if (strcasecmp(trim((string) $value),$title) !== 0) continue;
                $competency = trim((string) ($teaPayload['competency_level_' . $match[1]] ?? ''));
                if ($competency !== '') break;
            }

            if ($competency === '') {
                foreach ($teaPayload as $key => $value) {
                    if (str_starts_with((string) $key,'competency_level_') && trim((string) $value) !== '') {
                        $competency = trim((string) $value);
                        break;
                    }
                }
            }
        }

        $records[] = [
            'id' => $id,
            'application_no' => (string) $bpl['application_no'],
            'title' => $title,
            'organiser' => trim((string) ($payload['penganjur'] ?? '')),
            'location' => trim((string) ($payload['tempat'] ?? '')),
            'start' => $start,
            'end' => $end,
            'fee' => $fee,
            'status' => $status,
            'stage' => (string) $bpl['current_stage'],
            'lifecycle' => $lifecycle,
            'tone' => $tone,
            'pkk' => $pkk,
            'pkk_due' => $pkkDue,
            'tea' => $tea,
            'competency' => $competency,
        ];
    }

    $stats['pkk_compliance'] = $eligiblePkk > 0
        ? round(($onTimePkk / $eligiblePkk) * 100,1)
        : 0.0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: Employee Training History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261007-10">
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-15">
</head>
<body class="app-page employee-history-page" data-page="reports" data-role="<?= e($viewerRole) ?>">
<main class="sts-page-content">
  <div class="sts-page-shell">
    <header class="sts-page-heading">
      <div>
        <div class="sts-eyebrow">Learning record</div>
        <h1>Employee Training History</h1>
        <p>One timeline for courses, BPL, PKK, TEA, training cost and competency outcomes.</p>
      </div>
      <div class="sts-heading-actions">
        <a class="sts-secondary-btn" href="reports.php"><i class="bi bi-bar-chart-line"></i> Reports</a>
        <?php if ($employee): ?>
        <button class="sts-primary-btn" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print history</button>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($viewerRole !== 'staff'): ?>
    <form class="employee-history-picker" method="get">
      <label>
        <span>Employee</span>
        <select name="employee" onchange="this.form.submit()">
          <option value="">Select employee with training records</option>
          <?php foreach ($employees as $option): ?>
          <option value="<?= (int) $option['id'] ?>" <?= $employee && (int) $employee['id'] === (int) $option['id'] ? 'selected' : '' ?>>
            <?= e((string) $option['fullname']) ?><?= !empty($option['department']) ? ' · ' . e((string) $option['department']) : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
    <?php endif; ?>

    <?php if (!$employee): ?>
    <section class="employee-history-empty">
      <span><i class="bi bi-person-lines-fill"></i></span>
      <h2>Select an employee</h2>
      <p>Choose an employee above to open their complete training lifecycle.</p>
    </section>
    <?php else: ?>
    <section class="employee-history-profile">
      <span class="employee-history-avatar"><?= e(mb_strtoupper(mb_substr((string) $employee['fullname'],0,1))) ?></span>
      <div class="employee-history-identity">
        <span>Employee training record</span>
        <h2><?= e((string) $employee['fullname']) ?></h2>
        <p><?= e((string) ($employee['job_title'] ?: 'Position not set')) ?> · <?= e((string) ($employee['department'] ?: 'Department not set')) ?></p>
      </div>
      <div class="employee-history-meta">
        <?php if (!empty($employee['staff_id'])): ?><span><i class="bi bi-hash"></i><?= e((string) $employee['staff_id']) ?></span><?php endif; ?>
        <span><i class="bi bi-envelope"></i><?= e((string) $employee['email']) ?></span>
      </div>
    </section>

    <section class="employee-history-stats">
      <article><span><i class="bi bi-mortarboard"></i></span><div><strong><?= (int) $stats['training'] ?></strong><small>Approved trainings</small></div></article>
      <article><span><i class="bi bi-cash-stack"></i></span><div><strong>RM <?= e(number_format((float) $stats['cost'],2)) ?></strong><small>Approved training cost</small></div></article>
      <article class="<?= $eligiblePkk > 0 && (float) $stats['pkk_compliance'] < 80 ? 'is-warning' : '' ?>"><span><i class="bi bi-clipboard2-check"></i></span><div><strong><?= e((string) $stats['pkk_compliance']) ?>%</strong><small>PKK within 7 days · <?= $onTimePkk ?>/<?= $eligiblePkk ?></small></div></article>
      <article><span><i class="bi bi-graph-up-arrow"></i></span><div><strong><?= (int) $stats['tea_completed'] ?></strong><small>TEA completed</small></div></article>
    </section>

    <section class="sts-card employee-training-timeline">
      <div class="sts-card-heading">
        <div><span>Lifecycle</span><h2>Course timeline</h2></div>
        <span class="report-record-count"><?= count($records) ?> records</span>
      </div>

      <?php if ($records): ?>
      <div class="employee-course-list">
        <?php foreach ($records as $record): ?>
        <article class="employee-course-card tone-<?= e($record['tone']) ?>">
          <div class="employee-course-date">
            <strong><?= $record['start'] ? e(date('d',strtotime($record['start']))) : '—' ?></strong>
            <span><?= $record['start'] ? e(date('M Y',strtotime($record['start']))) : 'No date' ?></span>
          </div>
          <div class="employee-course-copy">
            <div class="employee-course-title-row">
              <div>
                <span><?= e($record['application_no']) ?></span>
                <h3><?= e($record['title']) ?></h3>
              </div>
              <em><?= e($record['lifecycle']) ?></em>
            </div>
            <p>
              <?= e($record['organiser'] ?: 'Organiser not set') ?>
              <?php if ($record['location']): ?> · <?= e($record['location']) ?><?php endif; ?>
              <?php if ($record['end']): ?> · <?= e(date('d M Y',strtotime($record['end']))) ?><?php endif; ?>
            </p>
            <div class="employee-course-flow">
              <span class="is-done"><i class="bi bi-file-earmark-check"></i>BPL <?= e(ucfirst($record['status'])) ?></span>
              <i class="bi bi-chevron-right"></i>
              <span class="<?= $record['pkk'] ? 'is-done' : 'is-pending' ?>"><i class="bi bi-clipboard2-check"></i>PKK <?= $record['pkk'] ? e(ucfirst((string) $record['pkk']['status'])) : 'Pending' ?></span>
              <i class="bi bi-chevron-right"></i>
              <span class="<?= $record['tea'] ? 'is-done' : 'is-pending' ?>"><i class="bi bi-graph-up"></i>TEA <?= $record['tea'] ? e(ucfirst((string) $record['tea']['status'])) : 'Pending' ?></span>
            </div>
            <div class="employee-course-footer">
              <span><i class="bi bi-cash"></i>RM <?= e(number_format((float) $record['fee'],2)) ?></span>
              <?php if (!$record['pkk'] && $record['pkk_due']): ?>
              <span class="<?= strtotime((string) $record['pkk_due']) < time() ? 'is-overdue' : '' ?>"><i class="bi bi-alarm"></i>PKK due <?= e(date('d M Y',strtotime((string) $record['pkk_due']))) ?></span>
              <?php endif; ?>
              <?php if ($record['competency']): ?><span><i class="bi bi-award"></i><?= e($record['competency']) ?></span><?php endif; ?>
              <a href="application-detail.php?application=<?= rawurlencode($record['application_no']) ?>">View record <i class="bi bi-arrow-up-right"></i></a>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="employee-history-empty compact">
        <span><i class="bi bi-mortarboard"></i></span>
        <h2>No training records</h2>
        <p>No BPL training record is available for this employee yet.</p>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
</main>
<script src="sedco-shell.js?v=20261007-05"></script>
</body>
</html>
