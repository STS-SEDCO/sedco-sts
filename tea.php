<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

if (normalized_role($user['role'] ?? '') !== 'head_of_department') {
    http_response_code(403);
    exit('Training Effectiveness Assessment is available to HOD accounts only.');
}

$sedcoDepartments = sts_sedco_departments();

$parent = null;
$parentId = max(0, (int) ($_GET['parent'] ?? 0));
$hodId = (int) ($user['id'] ?? 0);
$hodDepartment = trim((string) ($user['department'] ?? ''));
$eligibleEvaluations = [];
$eligibleByEmployee = [];

$eligibleStmt = db()->prepare(
    'SELECT
        b.id,
        b.application_no,
        b.user_id,
        b.department,
        b.assigned_hod_id,
        b.title,
        b.payload,
        b.training_end,
        u.fullname,
        u.department AS user_department,
        p.application_no AS pkk_application_no,
        p.payload AS pkk_payload,
        p.completed_at AS pkk_completed_at
     FROM applications b
     INNER JOIN users u
       ON u.id = b.user_id
     INNER JOIN applications p
       ON p.parent_application_id = b.id
      AND p.form_type = "PKK"
      AND p.status = "approved"
      AND p.current_stage = "completed"
     WHERE b.form_type = "BPL"
       AND b.status = "approved"
       AND b.training_end IS NOT NULL
       AND b.training_end <= CURDATE()
       AND b.user_id <> ?
       AND LOWER(TRIM(COALESCE(NULLIF(b.department, ""), NULLIF(u.department, "")))) = LOWER(TRIM(?))
     ORDER BY u.fullname ASC, b.training_end DESC, b.id DESC'
);
$eligibleStmt->bind_param('is', $hodId, $hodDepartment);
$eligibleStmt->execute();
$eligibleResult = $eligibleStmt->get_result();

while ($record = $eligibleResult->fetch_assoc()) {
    $recordId = (int) ($record['id'] ?? 0);

    if ($recordId <= 0 || sts_bpl_has_downstream_tea($recordId)) {
        continue;
    }

    $bplPayload = json_decode((string) ($record['payload'] ?? ''), true);
    $bplPayload = is_array($bplPayload) ? $bplPayload : [];

    $pkkPayload = json_decode((string) ($record['pkk_payload'] ?? ''), true);
    $pkkPayload = is_array($pkkPayload) ? $pkkPayload : [];

    $record['employee_name'] = trim((string) (
        $pkkPayload['nama']
        ?? $record['fullname']
        ?? $bplPayload['nama']
        ?? ''
    ));
    $record['division'] = trim((string) (
        $pkkPayload['bahagian']
        ?? $record['department']
        ?? $record['user_department']
        ?? $bplPayload['bahagian']
        ?? ''
    ));
    $record['training_title'] = trim((string) (
        $pkkPayload['tajuk']
        ?? $bplPayload['tajuk']
        ?? $record['title']
        ?? ''
    ));
    $record['training_date'] = trim((string) (
        $pkkPayload['tarikh']
        ?? $bplPayload['tarikh_tamat']
        ?? $record['training_end']
        ?? ''
    ));

    $eligibleEvaluations[$recordId] = $record;

    $employeeUserId = (int) ($record['user_id'] ?? 0);

    if ($employeeUserId <= 0) {
        continue;
    }

    if (!isset($eligibleByEmployee[$employeeUserId])) {
        $eligibleByEmployee[$employeeUserId] = [
            'user_id' => $employeeUserId,
            'employee_name' => $record['employee_name'],
            'division' => $record['division'],
            'courses' => [],
        ];
    }

    $eligibleByEmployee[$employeeUserId]['courses'][] = [
        'parent_id' => $recordId,
        'bpl_no' => (string) ($record['application_no'] ?? ''),
        'pkk_no' => (string) ($record['pkk_application_no'] ?? ''),
        'training_title' => (string) ($record['training_title'] ?? ''),
        'training_date' => (string) ($record['training_date'] ?? ''),
    ];
}
$eligibleStmt->close();

$selectedEmployeeId = 0;

if ($parentId > 0) {
    if (!isset($eligibleEvaluations[$parentId])) {
        http_response_code(403);
        exit('This training record is not pending TEA evaluation.');
    }

    $parent = $eligibleEvaluations[$parentId];
    $selectedEmployeeId = (int) ($parent['user_id'] ?? 0);
}

$eligibleEmployeeGroups = array_values($eligibleByEmployee);

$evaluationMonthNumber = (int) (
    new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'))
)->format('n');
$evaluationOpen = in_array($evaluationMonthNumber, [1, 6, 7, 12], true);
$autoEvaluationPeriod = match ($evaluationMonthNumber) {
    1, 6 => 'June',
    7, 12 => 'December',
    default => '',
};
$submissionDatePreview = (new DateTimeImmutable(
    'now',
    new DateTimeZone('Asia/Kuala_Lumpur')
))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Training Effectiveness Assessment: STS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261006-05">
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-14">
  <script>function printForm(){ window.print(); }</script>
</head>
<body class="app-page form-page tea-page tea-system-page" data-page="task" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">
<main class="tea-system-content">
  <div class="tea-system-shell">
    <header class="tea-system-header">
      <div class="tea-system-header-icon"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="tea-system-header-copy">
        <span class="tea-system-kicker">TRAINING EFFECTIVENESS ASSESSMENT FORM 2025</span>
        <h1>Training Effectiveness Assessment</h1>
        <p>Improvement after attending training, evaluated only in January, June, July or December.</p>
      </div>
      <span class="tea-system-owner"><i class="bi bi-person-check"></i> HOD only</span>
    </header>

    <?php if ($selectedEmployeeId > 0 && isset($eligibleByEmployee[$selectedEmployeeId])): ?>
    <div class="form-linked-training tea-system-linked">
      <span><i class="bi bi-person-check"></i> Employee selected</span>
      <strong><?= e((string) $eligibleByEmployee[$selectedEmployeeId]['employee_name']) ?> · <?= count($eligibleByEmployee[$selectedEmployeeId]['courses']) ?> course(s) pending evaluation</strong>
    </div>
    <?php endif; ?>

    <div class="form-permission-notice tea-system-permission" data-form-permission-notice></div>

    <?php if (!$evaluationOpen): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3 tea-evaluation-window-notice" role="status">
      <i class="bi bi-calendar-x mt-1"></i>
      <div>
        <strong>TEA evaluation is closed this month.</strong>
        <div>Assessments can only be completed in January, June, July and December.</div>
      </div>
    </div>
    <?php endif; ?>

    <form class="tea-official-form tea-system-form" data-form-owner="head_of_department" method="post" action="submit_application.php?type=TEA">
      <?= csrf_field() ?>
      <input type="hidden" name="parent_application_id" id="teaParentApplicationId" value="<?= $parentId > 0 ? (int) $parentId : '' ?>">

      <section class="tea-system-card">
        <div class="tea-system-section-heading">
          <span class="tea-system-section-number">01</span>
          <div><strong>Employee & Evaluation Details</strong><small>Basic employee information and evaluation period</small></div>
        </div>
        <div class="tea-system-card-body">
          <div class="tea-system-info-grid">
            <label class="tea-system-field tea-employee-picker-field">
              <span><i class="bi bi-person-check"></i> Employee Name <b>*</b></span>
              <select name="employee_name" id="teaEligibleEmployee" class="form-control tea-employee-picker" required <?= !$evaluationOpen ? 'disabled' : '' ?>>
                <option value="">Select employee pending evaluation</option>
                <?php foreach ($eligibleByEmployee as $employeeId => $employeeGroup): ?>
                <option
                  value="<?= e((string) $employeeGroup['employee_name']) ?>"
                  data-user-id="<?= (int) $employeeId ?>"
                  <?= $selectedEmployeeId === (int) $employeeId ? 'selected' : '' ?>
                ><?= e((string) $employeeGroup['employee_name']) ?> — <?= e((string) $employeeGroup['division']) ?> (<?= count($employeeGroup['courses']) ?> course<?= count($employeeGroup['courses']) === 1 ? '' : 's' ?>)</option>
                <?php endforeach; ?>
                <?php if (!$eligibleByEmployee): ?>
                <option value="" disabled>No employee pending TEA in your department</option>
                <?php endif; ?>
              </select>
              <small class="tea-source-hint">
                <i class="bi bi-database-check"></i>
                Shows employees from your department with completed training records that are ready for TEA.
              </small>
            </label>
            <label class="tea-system-field">
              <span><i class="bi bi-building-check"></i> Division / Section <b>*</b></span>
              <input
                type="text"
                name="division"
                id="teaSelectedDivision"
                class="form-control tea-auto-field"
                required
                readonly
                value="<?= e((string) ($selectedEmployeeId > 0 ? ($eligibleByEmployee[$selectedEmployeeId]['division'] ?? '') : '')) ?>"
                placeholder="Auto-filled from BPL / PKK"
              >
              <small class="tea-source-hint"><i class="bi bi-link-45deg"></i> Auto-filled from the selected employee's department and training records.</small>
            </label>
          </div>
          <div class="tea-selected-training-meta" data-selected-training-meta <?= $selectedEmployeeId > 0 ? '' : 'hidden' ?>>
            <div><span>Courses pending</span><strong data-selected-course-count><?= $selectedEmployeeId > 0 ? count($eligibleByEmployee[$selectedEmployeeId]['courses']) : 0 ?></strong></div>
            <div><span>Source</span><strong>BPL + PKK</strong></div>
            <div><span>Status</span><strong>Ready to evaluate</strong></div>
          </div>

          <div class="tea-system-period-row">
            <div><strong>Month of evaluation</strong><span><?= $evaluationOpen ? 'Automatically selected for the current evaluation month.' : 'Available only in January, June, July and December.' ?></span></div>
            <div class="tea-system-period-options" data-auto-evaluation-period>
              <label>
                <input type="radio" name="month" value="June" required <?= $autoEvaluationPeriod === 'June' ? 'checked' : 'disabled' ?>>
                <span><i class="bi bi-calendar3"></i> Jan / June</span>
              </label>
              <label>
                <input type="radio" name="month" value="December" required <?= $autoEvaluationPeriod === 'December' ? 'checked' : 'disabled' ?>>
                <span><i class="bi bi-calendar3"></i> July / Dec</span>
              </label>
            </div>
          </div>
        </div>
      </section>

      <section class="tea-system-card">
        <div class="tea-system-section-heading">
          <span class="tea-system-section-number">02</span>
          <div><strong>Rating Scale</strong><small>Reference only — enter a score from 1 to 4 in each assessment field</small></div>
        </div>
        <div class="tea-system-card-body">
          <div class="tea-system-rating-grid" role="list" aria-label="Rating scale reference">
            <div class="tea-system-rating-item" role="listitem"><strong>1</strong><span>Poor</span></div>
            <div class="tea-system-rating-item" role="listitem"><strong>2</strong><span>Average</span></div>
            <div class="tea-system-rating-item" role="listitem"><strong>3</strong><span>Good</span></div>
            <div class="tea-system-rating-item" role="listitem"><strong>4</strong><span>Excellent</span></div>
          </div>
        </div>
      </section>

      <section class="tea-system-card tea-system-assessment-card">
        <div class="tea-system-section-heading">
          <span class="tea-system-section-number">03</span>
          <div><strong>Assessment Criteria</strong><small>Select an employee above, then rate each completed course from 1 to 4.</small></div>
          <span class="tea-system-owner-chip"><i class="bi bi-pencil-square"></i> Your section</span>
        </div>
        <div class="tea-system-card-body">
          <div class="tea-assessment-selected no-print" data-assessment-selected hidden>
            <div class="tea-assessment-selected-icon"><i class="bi bi-person-check"></i></div>
            <div>
              <span>Employee selected</span>
              <strong data-assessment-employee>—</strong>
              <small data-assessment-course-count>0 courses ready for evaluation</small>
            </div>
          </div>
          <div class="tea-system-table-note no-print">
            <i class="bi bi-info-circle"></i>
            <span>Each score must be between 1 and 4. Total Score and Competency Level are calculated automatically.</span>
          </div>
          <div class="tea-system-table-wrap">
            <table class="tea-official-table tea-system-table" data-tea-table>
              <thead>
                <tr>
                  <th rowspan="2" class="tea-col-training">Training Title</th>
                  <th colspan="5" class="tea-criteria-group" data-criteria-group>Criteria</th>
                  <th rowspan="2" class="tea-col-total">Total Score</th>
                  <th rowspan="2" class="tea-col-level">Competency Level</th>
                  <th rowspan="2" class="tea-col-comments">Other Improvements / Comments</th>
                </tr>
                <tr data-criteria-head>
                  <th>Productivity</th><th>Quality of Work</th><th>Skill Enhancement</th><th>Application of Knowledge</th><th>Attitude</th>
                </tr>
              </thead>
              <tbody data-training-body>
                <tr class="tea-empty-training-row" data-empty-training-row>
                  <td colspan="9">
                    <div class="tea-empty-training-state">
                      <i class="bi bi-person-check"></i>
                      <strong>Select an employee above</strong>
                      <span>All courses pending TEA evaluation will appear here automatically.</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="tea-system-card">
        <div class="tea-system-section-heading">
          <span class="tea-system-section-number">04</span>
          <div><strong>Competency Ranking Guide</strong><small>Reference level based on the official assessment score</small></div>
        </div>
        <div class="tea-system-card-body">
          <div class="tea-system-ranking-grid">
            <article class="rank-fail"><span class="tea-system-rank-icon"><i class="bi bi-exclamation-circle"></i></span><span class="tea-system-rank-score">0–7</span><div><strong>Fail</strong><p>The employee has not shown progress. Re-training shall be provided on the particular topic.</p></div></article>
            <article class="rank-probation"><span class="tea-system-rank-icon"><i class="bi bi-hourglass-split"></i></span><span class="tea-system-rank-score">8–12</span><div><strong>Probation</strong><p>Requires supervision for another 6 months and reassessment for the particular subject.</p></div></article>
            <article class="rank-pass"><span class="tea-system-rank-icon"><i class="bi bi-check2-circle"></i></span><span class="tea-system-rank-score">13–17</span><div><strong>Pass</strong><p>Employee is able to execute the job with minimum supervision.</p></div></article>
            <article class="rank-merit"><span class="tea-system-rank-icon"><i class="bi bi-stars"></i></span><span class="tea-system-rank-score">18–20</span><div><strong>Merit</strong><p>Employee can conduct training or guide others using the knowledge learnt.</p></div></article>
          </div>
        </div>
      </section>

      <section class="tea-system-card">
        <div class="tea-system-section-heading">
          <span class="tea-system-section-number">05</span>
          <div><strong>Evaluator Confirmation</strong><small>Evaluator details come from your Profile. Submission date is recorded automatically.</small></div>
        </div>
        <div class="tea-system-card-body">
          <div class="tea-system-confirmation-note">
            <i class="bi bi-shield-check"></i>
            <div>
              <strong>Evaluated by</strong>
              <span><?= e((string) ($user['fullname'] ?? '')) ?></span>
            </div>
          </div>
          <div class="tea-system-confirmation-grid">
            <label class="tea-system-field">
              <span><i class="bi bi-person-check"></i> Evaluator Name <b>*</b></span>
              <input type="text" name="evaluated_by" class="form-control tea-auto-field" required readonly value="<?= e((string) ($user['fullname'] ?? '')) ?>">
              <small class="tea-source-hint"><i class="bi bi-person-vcard"></i> Auto-filled from Profile.</small>
            </label>
            <label class="tea-system-field">
              <span><i class="bi bi-building-check"></i> Head of Division / Section <b>*</b></span>
              <input type="text" name="head_division" class="form-control tea-auto-field" required readonly value="<?= e((string) ($user['department'] ?? '')) ?>">
              <small class="tea-source-hint"><i class="bi bi-person-vcard"></i> Auto-filled from Profile.</small>
            </label>
            <label class="tea-system-field tea-system-date-field">
              <span><i class="bi bi-calendar-check"></i> Submission Date <b>*</b></span>
              <input type="date" name="date" class="form-control tea-auto-field" required readonly value="<?= e($submissionDatePreview) ?>" data-tea-submit-date>
              <small class="tea-source-hint"><i class="bi bi-clock-history"></i> Final date is set automatically when you submit.</small>
            </label>
            <div class="tea-system-field tea-system-field-full tea-signature-field">
              <span><i class="bi bi-pen"></i> Digital Signature <b>*</b></span>
              <div class="tea-signature-pad" data-signature-pad tabindex="0" aria-label="Digital signature pad">
                <canvas width="900" height="220" data-signature-canvas></canvas>
                <div class="tea-signature-placeholder" data-signature-placeholder>Sign here using your mouse, stylus or finger</div>
              </div>
              <input type="hidden" name="signature" value="" data-signature-value>
              <div class="tea-signature-actions">
                <small><i class="bi bi-shield-check"></i> Your signature will be stored together with this assessment.</small>
                <button type="button" class="tea-signature-clear" data-signature-clear><i class="bi bi-eraser"></i> Clear signature</button>
              </div>
              <div class="tea-signature-error" data-signature-error hidden>Please provide your digital signature before submitting.</div>
            </div>
          </div>
        </div>
      </section>

      <div class="tea-system-actions no-print">
        <button type="button" class="btn btn-secondary form-print-button" onclick="printForm()"><i class="bi bi-printer"></i> Print</button>
        <button type="submit" class="btn btn-primary" <?= !$evaluationOpen ? 'disabled' : '' ?>><i class="bi <?= $evaluationOpen ? 'bi-send-check' : 'bi-lock' ?>"></i> <?= $evaluationOpen ? 'Submit Assessment' : 'Evaluation unavailable this month' ?></button>
      </div>
    </form>
  </div>
</main>

<section class="tea-print-sheet" aria-hidden="true">
  <header class="tea-print-header">
    <div class="tea-print-logo" aria-hidden="true">
      <div class="tea-print-logo-grid">
        <span>S</span><span>E</span><span>D</span><span>C</span><span>O</span>
      </div>
      <small>Sabah Economic Development Corporation</small>
    </div>
    <div class="tea-print-title">
      <strong>PERBADANAN PEMBANGUNAN EKONOMI SABAH (SEDCO)</strong>
      <span>TRAINING EFFECTIVENESS ASSESSMENT FORM 2025</span>
      <em>Improvement after attending training (evaluation available in January, June, July or December)</em>
    </div>
    <div class="tea-print-header-spacer"></div>
  </header>

  <div class="tea-print-details">
    <div><span>Employee Name</span><b>:</b><strong data-print-employee></strong></div>
    <div><span>Division/Section</span><b>:</b><strong data-print-division></strong></div>
    <div class="tea-print-month-line">
      <span>Month</span><b>:</b>
      <label>Jan/June <i data-print-month-june></i></label>
      <label>July/Dec <i data-print-month-dec></i></label>
      <em>(tick √ for the month of evaluation)</em>
    </div>
  </div>

  <div class="tea-print-rating">
    <strong>Rating</strong>
    <div><span>Poor (1)</span><span>Average (2)</span><span>Good (3)</span><span>Excellent (4)</span></div>
  </div>

  <table class="tea-print-assessment-table" data-print-assessment>
    <thead>
      <tr data-print-main-head>
        <th rowspan="2" class="tea-print-training-title">Training Title</th>
        <th class="tea-print-criteria-group" data-print-criteria-group>Criteria</th>
        <th rowspan="2" data-print-total-head>Total<br>Score</th>
        <th rowspan="2">Competency<br>Level <small>(please refer indicator ** below)</small></th>
        <th rowspan="2" class="tea-print-comments-head">Other improvements or comments <small>(please specify)</small></th>
      </tr>
      <tr data-print-criteria-head></tr>
    </thead>
    <tbody data-print-training-body></tbody>
  </table>

  <div class="tea-print-ranking">
    <p><strong>** Please choose the appropriate ranking for the competency level of the employee after being trained</strong></p>
    <table>
      <thead><tr><th>Ranking</th><th>Remarks</th></tr></thead>
      <tbody>
        <tr><td>Fail</td><td>Scored 0 - 7 points. The employee has not shown any progress in his work. Re-training shall be provided on the particular topic.</td></tr>
        <tr><td>Probation</td><td>Scored 8 - 12 points. Still requires to be supervised by their immediate supervisor for another 6 month. Re-assessment required for the particular subject.</td></tr>
        <tr><td>Pass</td><td>Scored 13 - 17 points. Employee able to execute the job with minimum supervision.</td></tr>
        <tr><td>Merit</td><td>Scored 18 points. Employee to conduct training/guide other employees on the knowledge learnt on particular topic.</td></tr>
      </tbody>
    </table>
  </div>

  <div class="tea-print-evaluated">
    <strong>Evaluated by</strong>
    <div><span>Evaluator Name</span><b>:</b><em data-print-evaluator></em></div>
    <div><span>Head of Division/Section</span><b>:</b><em data-print-head></em></div>
    <div><span>Date</span><b>:</b><em data-print-date></em></div>
    <div class="tea-print-signature-row"><span>Signature</span><b>:</b><span class="tea-print-signature-box"><img data-print-signature alt="Digital signature"></span></div>
  </div>
</section>
<script>
window.TEA_ELIGIBLE_GROUPS = <?= json_encode(
    $eligibleEmployeeGroups,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>;
window.TEA_PRESELECT_USER_ID = <?= (int) $selectedEmployeeId ?>;
</script>
<script>
window.SEDCO_FORM_CONTEXT = { role: <?= json_encode($user['role'] ?? 'staff') ?>, mode: 'new', formType: 'TEA' };
</script>
<script src="form-permissions.js?v=20261005-04"></script>

<script>
(() => {
  const form = document.querySelector('.tea-official-form');
  if (!form) return;

  const table = form.querySelector('[data-tea-table]');
  const body = form.querySelector('[data-training-body]');
  const criteriaHead = form.querySelector('[data-criteria-head]');
  const criteriaGroup = form.querySelector('[data-criteria-group]');
  const picker = document.getElementById('teaEligibleEmployee');
  const parentInput = document.getElementById('teaParentApplicationId');
  const divisionInput = document.getElementById('teaSelectedDivision');
  const meta = document.querySelector('[data-selected-training-meta]');
  const courseCountOutput = document.querySelector('[data-selected-course-count]');
  const assessmentSelected = form.querySelector('[data-assessment-selected]');
  const assessmentEmployee = form.querySelector('[data-assessment-employee]');
  const assessmentCourseCount = form.querySelector('[data-assessment-course-count]');
  const groups = Array.isArray(window.TEA_ELIGIBLE_GROUPS) ? window.TEA_ELIGIBLE_GROUPS : [];

  let activeScore = null;

  const levelFor = total => {
    if (total <= 7) return 'Fail';
    if (total <= 12) return 'Probation';
    if (total <= 17) return 'Pass';
    return 'Merit';
  };

  const levelClass = value => 'is-' + String(value || 'auto').toLowerCase();

  const calculateRow = row => {
    const standardScores = [...row.querySelectorAll('.tea-score-standard')];
    const totalInput = row.querySelector('.tea-total');
    const hiddenLevel = row.querySelector('input[name^="competency_level_"]');
    const levelOutput = row.querySelector('[data-level-output]');
    if (!standardScores.length || !totalInput || !hiddenLevel || !levelOutput) return;

    let complete = true;
    const values = standardScores.map(input => {
      const raw = String(input.value || '').trim();
      if (!raw) {
        complete = false;
        return 0;
      }

      let value = Math.round(Number(raw));
      if (!Number.isFinite(value)) {
        input.value = '';
        complete = false;
        return 0;
      }

      value = Math.max(1, Math.min(4, value));
      input.value = String(value);
      return value;
    });

    levelOutput.classList.remove('is-auto','is-fail','is-probation','is-pass','is-merit');

    if (!complete) {
      totalInput.value = '';
      hiddenLevel.value = '';
      levelOutput.textContent = 'Auto';
      levelOutput.classList.add('is-auto');
      return;
    }

    const total = values.reduce((sum, value) => sum + value, 0);
    const level = levelFor(total);

    totalInput.value = String(total);
    hiddenLevel.value = level;
    levelOutput.textContent = level;
    levelOutput.classList.add(levelClass(level));
  };

  const bindRow = row => {
    row.querySelectorAll('.tea-score').forEach(input => {
      input.min = '1';
      input.max = '4';
      input.step = '1';
      input.inputMode = 'numeric';

      ['input','change','blur'].forEach(eventName => {
        input.addEventListener(eventName, () => calculateRow(row));
      });

      input.addEventListener('focus', () => {
        activeScore = input;
        form.querySelectorAll('.tea-score').forEach(other => {
          other.classList.toggle('is-active-score', other === input);
        });
      });
    });

    calculateRow(row);
  };

  const makeTrainingRow = (course, rowIndex) => {
    const tr = document.createElement('tr');
    tr.dataset.trainingRow = String(rowIndex);
    tr.dataset.parentId = String(course.parent_id || '');

    const standardScoreCells = Array.from({ length: 5 }, (_, criterionIndex) =>
      '<td><select name="score_'+rowIndex+'[]" class="tea-score tea-score-standard" required aria-label="Criteria score '+(criterionIndex+1)+'">'+
        '<option value="">—</option>'+
        '<option value="1">1</option>'+
        '<option value="2">2</option>'+
        '<option value="3">3</option>'+
        '<option value="4">4</option>'+
      '</select></td>'
    ).join('');

    tr.innerHTML =
      '<td class="tea-training-cell tea-training-cell-auto">'+
        '<input type="hidden" name="course_parent_id_'+rowIndex+'" value="'+String(course.parent_id || '')+'">'+
        '<input type="hidden" name="evaluated_parent_ids[]" value="'+String(course.parent_id || '')+'">'+
        '<input type="text" name="training_title_'+rowIndex+'" required readonly class="tea-auto-training-title" value="">'+
        '<small class="tea-course-source"><i class="bi bi-link-45deg"></i> <span></span></small>'+
      '</td>'+
      standardScoreCells+
      '<td><input type="text" name="total_score_'+rowIndex+'" class="tea-total" readonly placeholder="Auto"></td>'+
      '<td><input type="hidden" name="competency_level_'+rowIndex+'" value=""><span class="tea-level is-auto" data-level-output="'+rowIndex+'">Auto</span></td>'+
      '<td><textarea name="comments_'+rowIndex+'" rows="2" placeholder="Optional comments"></textarea></td>';

    const titleInput = tr.querySelector('.tea-auto-training-title');
    const sourceText = tr.querySelector('.tea-course-source span');

    if (titleInput) titleInput.value = String(course.training_title || 'Training');
    if (sourceText) {
      sourceText.textContent = [course.bpl_no, course.pkk_no, course.training_date]
        .filter(Boolean)
        .join(' · ');
    }

    bindRow(tr);
    return tr;
  };

  const renderCourses = group => {
    body.innerHTML = '';
    activeScore = null;

    const courses = Array.isArray(group?.courses) ? group.courses : [];

    if (!courses.length) {
      const empty = document.createElement('tr');
      empty.className = 'tea-empty-training-row';
      empty.innerHTML =
        '<td colspan="'+'9'+'">'+
          '<div class="tea-empty-training-state">'+
            '<i class="bi bi-person-check"></i>'+
            '<strong>Select an employee above</strong>'+
            '<span>All courses pending TEA evaluation will appear here automatically.</span>'+
          '</div>'+
        '</td>';
      body.appendChild(empty);

      if (parentInput) parentInput.value = '';
      if (divisionInput) divisionInput.value = '';
      if (meta) meta.hidden = true;
      if (courseCountOutput) courseCountOutput.textContent = '0';
      if (assessmentSelected) assessmentSelected.hidden = true;
      if (assessmentEmployee) assessmentEmployee.textContent = '—';
      if (assessmentCourseCount) assessmentCourseCount.textContent = '0 courses ready for evaluation';
      return;
    }

    courses.forEach((course, rowIndex) => {
      body.appendChild(makeTrainingRow(course, rowIndex));
    });

    if (parentInput) parentInput.value = String(courses[0]?.parent_id || '');
    if (divisionInput) divisionInput.value = String(group?.division || '');
    if (meta) meta.hidden = false;
    if (courseCountOutput) courseCountOutput.textContent = String(courses.length);
    if (assessmentSelected) assessmentSelected.hidden = false;
    if (assessmentEmployee) assessmentEmployee.textContent = String(group?.employee_name || '—');
    if (assessmentCourseCount) assessmentCourseCount.textContent = String(courses.length) + ' course' + (courses.length === 1 ? '' : 's') + ' ready for evaluation';
  };

  const selectedGroup = () => {
    const option = picker?.selectedOptions?.[0];
    const userId = Number(option?.dataset?.userId || 0);
    return groups.find(group => Number(group.user_id) === userId) || null;
  };

  picker?.addEventListener('change', () => renderCourses(selectedGroup()));

  form.addEventListener('submit', event => {
    const rows = [...body.querySelectorAll('[data-training-row]')];

    if (!picker?.value || !rows.length) {
      event.preventDefault();
      picker?.focus();
      picker?.scrollIntoView({ behavior:'smooth', block:'center' });
      return;
    }

    let firstInvalid = null;

    rows.forEach(row => {
      row.querySelectorAll('.tea-score-standard').forEach(score => {
        const value = Number(score.value);
        if (!score.value || !Number.isFinite(value) || value < 1 || value > 4) {
          firstInvalid ||= score;
        }
      });
    });

    if (firstInvalid) {
      event.preventDefault();
      firstInvalid.focus();
      firstInvalid.scrollIntoView({ behavior:'smooth', block:'center' });
    }
  });

  const preselectUserId = Number(window.TEA_PRESELECT_USER_ID || 0);
  if (preselectUserId > 0 && picker) {
    const option = [...picker.options].find(item => Number(item.dataset.userId || 0) === preselectUserId);
    if (option) option.selected = true;
  }

  renderCourses(selectedGroup());
})();
</script>

<script>
(() => {
  const form = document.querySelector('.tea-official-form');
  const canvas = form?.querySelector('[data-signature-canvas]');
  const pad = form?.querySelector('[data-signature-pad]');
  const signatureInput = form?.querySelector('[data-signature-value]');
  const clearButton = form?.querySelector('[data-signature-clear]');
  const placeholder = form?.querySelector('[data-signature-placeholder]');
  const error = form?.querySelector('[data-signature-error]');
  const dateInput = form?.querySelector('[data-tea-submit-date]');

  if (!form || !canvas || !signatureInput) return;

  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.strokeStyle = '#1f2937';
  ctx.lineWidth = 4;

  let drawing = false;
  let signed = false;

  const malaysiaDate = () => {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Kuala_Lumpur',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    }).formatToParts(new Date());
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
    return values.year + '-' + values.month + '-' + values.day;
  };

  const syncDate = () => {
    if (dateInput) dateInput.value = malaysiaDate();
  };

  const pointFor = event => {
    const rect = canvas.getBoundingClientRect();
    return {
      x: (event.clientX - rect.left) * (canvas.width / rect.width),
      y: (event.clientY - rect.top) * (canvas.height / rect.height)
    };
  };

  const start = event => {
    if (event.button !== undefined && event.button !== 0) return;
    event.preventDefault();
    const point = pointFor(event);
    drawing = true;
    canvas.setPointerCapture?.(event.pointerId);
    ctx.beginPath();
    ctx.moveTo(point.x, point.y);
  };

  const move = event => {
    if (!drawing) return;
    event.preventDefault();
    const point = pointFor(event);
    ctx.lineTo(point.x, point.y);
    ctx.stroke();
    signed = true;
    signatureInput.value = canvas.toDataURL('image/png');
    if (placeholder) placeholder.hidden = true;
    if (error) error.hidden = true;
    pad?.classList.add('has-signature');
  };

  const stop = event => {
    if (!drawing) return;
    drawing = false;
    canvas.releasePointerCapture?.(event.pointerId);
    if (signed) signatureInput.value = canvas.toDataURL('image/png');
  };

  canvas.addEventListener('pointerdown', start);
  canvas.addEventListener('pointermove', move);
  canvas.addEventListener('pointerup', stop);
  canvas.addEventListener('pointercancel', stop);
  canvas.addEventListener('pointerleave', event => {
    if (event.buttons === 0) stop(event);
  });

  clearButton?.addEventListener('click', () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    signed = false;
    signatureInput.value = '';
    if (placeholder) placeholder.hidden = false;
    if (error) error.hidden = true;
    pad?.classList.remove('has-signature', 'is-invalid');
  });

  form.addEventListener('submit', event => {
    syncDate();

    if (!signatureInput.value || !signed) {
      event.preventDefault();
      if (error) error.hidden = false;
      pad?.classList.add('is-invalid');
      pad?.scrollIntoView({ behavior:'smooth', block:'center' });
      pad?.focus({ preventScroll:true });
    }
  });

  syncDate();
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) syncDate();
  });
})();
</script>

<script>
(() => {
  const form = document.querySelector('.tea-official-form');
  const sheet = document.querySelector('.tea-print-sheet');
  if (!form || !sheet) return;

  const valueOf = name => {
    const field = form.querySelector('[name="' + name + '"]');
    if (!field) return '';
    if (field.tagName === 'SELECT') {
      return field.selectedOptions?.[0]?.textContent?.trim() || field.value || '';
    }
    return String(field.value || '').trim();
  };

  const formatDate = value => {
    if (!value) return '';
    const parts = String(value).split('-');
    if (parts.length !== 3) return value;
    return parts[2] + '/' + parts[1] + '/' + parts[0];
  };

  const setText = (selector, value) => {
    const node = sheet.querySelector(selector);
    if (node) node.textContent = value || '';
  };

  const buildPrintSheet = () => {
    setText('[data-print-employee]', valueOf('employee_name'));
    setText('[data-print-division]', valueOf('division'));
    setText('[data-print-evaluator]', valueOf('evaluated_by'));
    setText('[data-print-head]', valueOf('head_division'));
    setText('[data-print-date]', formatDate(valueOf('date')));

    const signatureValue = valueOf('signature');
    const signatureImage = sheet.querySelector('[data-print-signature]');
    if (signatureImage) {
      if (/^data:image\/png;base64,/i.test(signatureValue)) {
        signatureImage.src = signatureValue;
        signatureImage.hidden = false;
      } else {
        signatureImage.removeAttribute('src');
        signatureImage.hidden = true;
      }
    }

    const month = form.querySelector('input[name="month"]:checked')?.value || '';
    const juneBox = sheet.querySelector('[data-print-month-june]');
    const decBox = sheet.querySelector('[data-print-month-dec]');
    if (juneBox) juneBox.textContent = month === 'June' ? '√' : '';
    if (decBox) decBox.textContent = month === 'December' ? '√' : '';

    const liveCriteria = [...form.querySelectorAll('[data-criteria-head] > th')];
    const criteriaNames = liveCriteria.map(th => {
      const clone = th.cloneNode(true);
      clone.querySelectorAll('button').forEach(btn => btn.remove());
      return clone.textContent.replace(/\s+/g,' ').trim();
    }).filter(Boolean);

    const printGroup = sheet.querySelector('[data-print-criteria-group]');
    const printHead = sheet.querySelector('[data-print-criteria-head]');
    const printBody = sheet.querySelector('[data-print-training-body]');
    const printTable = sheet.querySelector('[data-print-assessment]');

    if (printGroup) printGroup.colSpan = 5;

    if (printHead) {
      printHead.innerHTML = '';
      criteriaNames.forEach(name => {
        const th = document.createElement('th');
        th.textContent = name;
        printHead.appendChild(th);
      });
    }

    if (printBody) {
      printBody.innerHTML = '';
      [...form.querySelectorAll('[data-training-row]')].forEach(row => {
        const tr = document.createElement('tr');
        const title = row.querySelector('input[name^="training_title_"]')?.value?.trim() || '';
        const standardScores = [...row.querySelectorAll('.tea-score-standard')].map(input => input.value || '');
        const scores = standardScores;
        const total = row.querySelector('.tea-total')?.value || '';
        const level = row.querySelector('input[name^="competency_level_"]')?.value
          || row.querySelector('[data-level-output]')?.textContent?.trim()
          || '';
        const comments = row.querySelector('textarea')?.value?.trim() || '';

        const cells = [title, ...scores, total, level, comments];
        cells.forEach((value, index) => {
          const td = document.createElement('td');
          td.textContent = value;
          if (index === 0) td.className = 'tea-print-training-cell';
          if (index === cells.length - 1) td.className = 'tea-print-comment-cell';
          tr.appendChild(td);
        });
        printBody.appendChild(tr);
      });
    }

    if (printTable) {
      printTable.classList.remove('has-extra-columns');
      printTable.dataset.criteriaCount = String(criteriaNames.length);
    }
  };

  window.addEventListener('beforeprint', buildPrintSheet);
  document.querySelectorAll('.form-print-button').forEach(button => {
    button.addEventListener('click', buildPrintSheet, { capture:true });
  });
})();
</script>
<script src="form-enhancements.js?v=20261005-01"></script>
<script src="sedco-shell.js?v=20261007-05"></script>
</body>
</html>
