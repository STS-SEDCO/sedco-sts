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
$parentPayload = [];
$parentId = max(0, (int) ($_GET['parent'] ?? 0));

if ($parentId > 0) {
    $parent = sts_validate_parent_bpl($parentId, $user, 'TEA');

    if (!$parent) {
        http_response_code(403);
        exit('This training record is not available for TEA follow up.');
    }

    $parentPayload = json_decode((string) $parent['payload'], true);
    $parentPayload = is_array($parentPayload) ? $parentPayload : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Training Effectiveness Assessment: STS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261005-25">
  <link rel="stylesheet" href="sedco-shell.css?v=20261005-03">
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
        <p>Improvement after attending training, evaluated in June or December of the training year.</p>
      </div>
      <span class="tea-system-owner"><i class="bi bi-person-check"></i> HOD only</span>
    </header>

    <?php if ($parent): ?>
    <div class="form-linked-training tea-system-linked">
      <span><i class="bi bi-link-45deg"></i> Linked BPL record</span>
      <strong><?= e((string) $parent['application_no']) ?> · <?= e((string) ($parent['title'] ?? 'Training')) ?></strong>
      <a href="application-detail.php?application=<?= rawurlencode((string) $parent['application_no']) ?>">View BPL <i class="bi bi-arrow-up-right"></i></a>
    </div>
    <?php endif; ?>

    <div class="form-permission-notice tea-system-permission" data-form-permission-notice></div>

    <form class="tea-official-form tea-system-form" data-form-owner="head_of_department" method="post" action="submit_application.php?type=TEA">
      <?= csrf_field() ?>
      <?php if ($parentId > 0): ?>
      <input type="hidden" name="parent_application_id" value="<?= (int) $parentId ?>">
      <?php endif; ?>

      <section class="tea-system-card">
        <div class="tea-system-section-heading">
          <span class="tea-system-section-number">01</span>
          <div><strong>Employee & Evaluation Details</strong><small>Basic employee information and evaluation period</small></div>
        </div>
        <div class="tea-system-card-body">
          <div class="tea-system-info-grid">
            <label class="tea-system-field">
              <span><i class="bi bi-person"></i> Employee Name <b>*</b></span>
              <input type="text" name="employee_name" class="form-control" required value="<?= e((string) ($parentPayload['nama'] ?? '')) ?>" placeholder="Enter employee name">
            </label>
            <label class="tea-system-field">
              <span><i class="bi bi-building"></i> Division / Section <b>*</b></span>
              <?php $selectedDivision = (string) ($parent['department'] ?? $parentPayload['bahagian'] ?? ''); ?>
              <select name="division" class="form-control sts-department-select" required>
                <option value="">Select SEDCO Department / Division</option>
                <?php foreach ($sedcoDepartments as $departmentName): ?>
                <option value="<?= e($departmentName) ?>" <?= $selectedDivision === $departmentName ? 'selected' : '' ?>><?= e($departmentName) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="tea-system-period-row">
            <div><strong>Month of evaluation</strong><span>Select the applicable evaluation period.</span></div>
            <div class="tea-system-period-options">
              <label><input type="radio" name="month" value="June" required><span><i class="bi bi-calendar3"></i> Jan / June</span></label>
              <label><input type="radio" name="month" value="December" checked><span><i class="bi bi-calendar3"></i> July / Dec</span></label>
            </div>
          </div>
        </div>
      </section>

      <section class="tea-system-card">
        <div class="tea-system-section-heading">
          <span class="tea-system-section-number">02</span>
          <div><strong>Rating Scale</strong><small>Select a score, then apply it to the active score field</small></div>
        </div>
        <div class="tea-system-card-body">
          <div class="tea-system-rating-grid" role="group" aria-label="Rating scale">
            <button type="button" data-tea-rating="1"><strong>1</strong><span>Poor</span></button>
            <button type="button" data-tea-rating="2"><strong>2</strong><span>Average</span></button>
            <button type="button" data-tea-rating="3"><strong>3</strong><span>Good</span></button>
            <button type="button" data-tea-rating="4"><strong>4</strong><span>Excellent</span></button>
          </div>
        </div>
      </section>

      <section class="tea-system-card tea-system-assessment-card">
        <div class="tea-system-section-heading">
          <span class="tea-system-section-number">03</span>
          <div><strong>Assessment Criteria</strong><small>Rate each training using the five official criteria. Add more criteria when required.</small></div>
          <span class="tea-system-owner-chip"><i class="bi bi-pencil-square"></i> Your section</span>
        </div>
        <div class="tea-system-card-body">
          <div class="tea-system-table-tools no-print">
            <button type="button" class="tea-system-tool-btn" data-add-training><i class="bi bi-plus-lg"></i> Add Training Row</button>
            <div class="tea-system-add-column">
              <input type="text" data-new-criterion placeholder="New criterion name">
              <button type="button" class="tea-system-tool-btn" data-add-criterion><i class="bi bi-layout-three-columns"></i> Add Column</button>
            </div>
          </div>
          <div class="tea-system-table-note no-print">
            <i class="bi bi-info-circle"></i>
            <span>Extra columns are additional criteria. Total Score and Competency Level continue to use the five original SEDCO criteria.</span>
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
                <tr data-training-row="0">
                  <td class="tea-training-cell">
                    <input type="text" name="training_title_0" required value="<?= e((string) ($parent['title'] ?? $parentPayload['tajuk'] ?? '')) ?>" placeholder="Training title">
                    <button type="button" class="tea-row-remove no-print" data-remove-row title="Remove row" hidden><i class="bi bi-x-lg"></i></button>
                  </td>
                  <td><input type="number" name="score_0[]" min="1" max="4" class="tea-score tea-score-standard" required placeholder="1-4"></td>
                  <td><input type="number" name="score_0[]" min="1" max="4" class="tea-score tea-score-standard" required placeholder="1-4"></td>
                  <td><input type="number" name="score_0[]" min="1" max="4" class="tea-score tea-score-standard" required placeholder="1-4"></td>
                  <td><input type="number" name="score_0[]" min="1" max="4" class="tea-score tea-score-standard" required placeholder="1-4"></td>
                  <td><input type="number" name="score_0[]" min="1" max="4" class="tea-score tea-score-standard" required placeholder="1-4"></td>
                  <td><input type="text" name="total_score_0" class="tea-total" readonly placeholder="Auto"></td>
                  <td><input type="hidden" name="competency_level_0" value=""><span class="tea-level is-auto" data-level-output="0">Auto</span></td>
                  <td><textarea name="comments_0" rows="2" placeholder="Optional comments"></textarea></td>
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
          <div><strong>Evaluator Confirmation</strong><small>Complete the official HOD evaluation details before submission</small></div>
        </div>
        <div class="tea-system-card-body">
          <div class="tea-system-confirmation-note">
            <i class="bi bi-shield-check"></i>
            <div><strong>Evaluated by</strong><span>HOD details are recorded together with this assessment.</span></div>
          </div>
          <div class="tea-system-confirmation-grid">
            <label class="tea-system-field"><span><i class="bi bi-person-badge"></i> Head of Division / Section <b>*</b></span><input type="text" name="head_division" class="form-control tea-auto-field" required readonly value="<?= e($user['fullname']) ?>"></label>
            <label class="tea-system-field"><span><i class="bi bi-calendar-check"></i> Date <b>*</b></span><input type="date" name="date" class="form-control" required></label>
            <label class="tea-system-field tea-system-field-full"><span><i class="bi bi-pen"></i> Signature / Confirmation <b>*</b></span><input type="text" name="signature" class="form-control" required placeholder="Type your name as confirmation"></label>
          </div>
        </div>
      </section>

      <div class="tea-system-actions no-print">
        <button type="button" class="btn btn-secondary form-print-button" onclick="printForm()"><i class="bi bi-printer"></i> Print</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-send-check"></i> Submit Assessment</button>
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
      <em>Improvement after attending training (to be evaluated in June or December in year of training)</em>
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
      <tr>
        <th rowspan="2" class="tea-print-training-title">Training Title</th>
        <th class="tea-print-criteria-group" data-print-criteria-group>Criteria</th>
        <th rowspan="2">Total<br>Score</th>
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
    <div><span>Head of Division/Section</span><b>:</b><em data-print-head></em></div>
    <div><span>Date</span><b>:</b><em data-print-date></em></div>
    <div><span>Signature</span><b>:</b><em data-print-signature></em></div>
  </div>
</section>
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
  const addRowBtn = form.querySelector('[data-add-training]');
  const addCriterionBtn = form.querySelector('[data-add-criterion]');
  const criterionNameInput = form.querySelector('[data-new-criterion]');

  let nextRow = 1;
  let extraCriteria = [];

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
      if (!raw) { complete = false; return 0; }
      let value = Math.round(Number(raw));
      if (!Number.isFinite(value)) { input.value=''; complete=false; return 0; }
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

    const total = values.reduce((sum,value)=>sum+value,0);
    const level = levelFor(total);
    totalInput.value = String(total);
    hiddenLevel.value = level;
    levelOutput.textContent = level;
    levelOutput.classList.add(levelClass(level));
  };

  const bindRow = row => {
    row.querySelectorAll('.tea-score').forEach(input => {
      input.min='1'; input.max='4'; input.step='1'; input.inputMode='numeric';
      ['input','change','blur'].forEach(evt => input.addEventListener(evt, () => calculateRow(row)));
      input.addEventListener('focus', () => {
        form.querySelectorAll('.tea-score').forEach(other => other.classList.toggle('is-active-score', other===input));
      });
    });
    row.querySelector('[data-remove-row]')?.addEventListener('click', () => {
      row.remove();
      syncRemoveButtons();
    });
    calculateRow(row);
  };

  const syncRemoveButtons = () => {
    const rows=[...body.querySelectorAll('[data-training-row]')];
    rows.forEach(row => {
      const btn=row.querySelector('[data-remove-row]');
      if (btn) btn.hidden=rows.length===1;
    });
  };

  const buildExtraScoreCell = (rowIndex) => {
    const td=document.createElement('td');
    td.className='tea-extra-score-cell';
    td.innerHTML='<input type="number" name="extra_score_'+rowIndex+'[]" min="1" max="4" class="tea-score tea-score-extra" aria-label="Additional criterion score">';
    return td;
  };

  const addCriterion = name => {
    name=String(name||'').trim();
    if(!name) return;
    extraCriteria.push(name);

    const th=document.createElement('th');
    th.className='tea-extra-criterion-head';
    th.innerHTML='<span></span><button type="button" class="no-print" title="Remove column"><i class="bi bi-x-lg"></i></button>';
    th.querySelector('span').textContent=name;
    criteriaHead.appendChild(th);

    const hidden=document.createElement('input');
    hidden.type='hidden';
    hidden.name='extra_criteria[]';
    hidden.value=name;
    hidden.dataset.extraCriterion=name;
    form.appendChild(hidden);

    body.querySelectorAll('[data-training-row]').forEach(row => {
      const idx=row.dataset.trainingRow;
      const totalCell=row.querySelector('.tea-total')?.closest('td');
      totalCell?.before(buildExtraScoreCell(idx));
      bindRow(row);
    });

    criteriaGroup.colSpan=5+extraCriteria.length;

    th.querySelector('button').addEventListener('click', () => {
      const extraIndex=[...criteriaHead.querySelectorAll('.tea-extra-criterion-head')].indexOf(th);
      if(extraIndex<0) return;
      th.remove();
      body.querySelectorAll('[data-training-row]').forEach(row => {
        row.querySelectorAll('.tea-extra-score-cell')[extraIndex]?.remove();
      });
      const hiddenInputs=[...form.querySelectorAll('input[name="extra_criteria[]"]')];
      hiddenInputs[extraIndex]?.remove();
      extraCriteria.splice(extraIndex,1);
      criteriaGroup.colSpan=5+extraCriteria.length;
    });
  };

  const addTrainingRow = () => {
    const idx=nextRow++;
    const tr=document.createElement('tr');
    tr.dataset.trainingRow=String(idx);

    const standardScoreCells = Array.from({ length: 5 }, (_, criterionIndex) =>
      '<td><input type="number" name="score_'+idx+'[]" min="1" max="4" step="1" inputmode="numeric" class="tea-score tea-score-standard" required placeholder="1-4" aria-label="Criteria score '+(criterionIndex+1)+'"></td>'
    ).join('');

    tr.innerHTML =
      '<td class="tea-training-cell">'+
        '<input type="text" name="training_title_'+idx+'" required placeholder="Training title">'+
        '<button type="button" class="tea-row-remove no-print" data-remove-row title="Remove row" aria-label="Remove training row"><i class="bi bi-x-lg"></i></button>'+
      '</td>'+
      standardScoreCells+
      '<td><input type="text" name="total_score_'+idx+'" class="tea-total" readonly placeholder="Auto"></td>'+
      '<td><input type="hidden" name="competency_level_'+idx+'" value=""><span class="tea-level is-auto" data-level-output="'+idx+'">Auto</span></td>'+
      '<td><textarea name="comments_'+idx+'" rows="2" placeholder="Optional comments"></textarea></td>';

    const totalCell=tr.querySelector('.tea-total')?.closest('td');
    extraCriteria.forEach(() => {
      if (totalCell) totalCell.before(buildExtraScoreCell(idx));
    });

    body.appendChild(tr);
    bindRow(tr);
    syncRemoveButtons();

    const titleInput=tr.querySelector('input[name^="training_title_"]');
    titleInput?.focus();
  };

  let activeScore=null;
  form.addEventListener('focusin', e => {
    if(e.target.classList?.contains('tea-score')) activeScore=e.target;
  });

  form.querySelectorAll('[data-tea-rating]').forEach(button => {
    button.addEventListener('click', () => {
      if(!activeScore) activeScore=form.querySelector('.tea-score');
      if(!activeScore) return;
      const value=String(button.dataset.teaRating||'');
      activeScore.value=value;
      activeScore.dispatchEvent(new Event('input',{bubbles:true}));
      form.querySelectorAll('[data-tea-rating]').forEach(other => other.classList.toggle('is-selected',other===button));
      activeScore.focus();
    });
  });

  addRowBtn?.addEventListener('click', addTrainingRow);
  addCriterionBtn?.addEventListener('click', () => {
    addCriterion(criterionNameInput?.value);
    if(criterionNameInput) { criterionNameInput.value=''; criterionNameInput.focus(); }
  });
  criterionNameInput?.addEventListener('keydown', e => {
    if(e.key==='Enter'){ e.preventDefault(); addCriterionBtn?.click(); }
  });

  form.addEventListener('submit', e => {
    const rows=[...body.querySelectorAll('[data-training-row]')];
    let firstInvalid=null;
    rows.forEach(row => {
      row.querySelectorAll('.tea-score-standard').forEach(score => {
        const v=Number(score.value);
        if(!score.value || !Number.isFinite(v) || v<1 || v>4) firstInvalid ||= score;
      });
      const title=row.querySelector('input[name^="training_title_"]');
      if(title && !title.value.trim()) firstInvalid ||= title;
    });
    if(firstInvalid){
      e.preventDefault();
      firstInvalid.focus();
      firstInvalid.scrollIntoView({behavior:'smooth',block:'center'});
    }
  });

  body.querySelectorAll('[data-training-row]').forEach(bindRow);
  syncRemoveButtons();
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
    setText('[data-print-head]', valueOf('head_division'));
    setText('[data-print-date]', formatDate(valueOf('date')));
    setText('[data-print-signature]', valueOf('signature'));

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

    if (printGroup) printGroup.colSpan = Math.max(1, criteriaNames.length);

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
        const extraScores = [...row.querySelectorAll('.tea-score-extra')].map(input => input.value || '');
        const scores = [...standardScores, ...extraScores];
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
      printTable.classList.toggle('has-extra-columns', criteriaNames.length > 5);
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
<script src="sedco-shell.js?v=20261005-03"></script>
</body>
</html>
