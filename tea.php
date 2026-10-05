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
  <title>SEDCO Training Effectiveness Assessment Form 2025</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261005-19">
  <link rel="stylesheet" href="sedco-shell.css?v=20261005-03">
  <script>function printForm(){ window.print(); }</script>
</head>
<body class="app-page form-page tea-page tea-official-page" data-page="task" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">

<main class="tea-official-content">
  <div class="tea-official-paper">
    <header class="tea-official-header">
      <div class="tea-official-mark" aria-hidden="true">
        <span>SEDCO</span>
        <small>SABAH</small>
      </div>
      <div class="tea-official-header-copy">
        <strong>PERBADANAN PEMBANGUNAN EKONOMI SABAH (SEDCO)</strong>
        <h1>TRAINING EFFECTIVENESS ASSESSMENT FORM 2025</h1>
        <p>Improvement after attending training (to be evaluated in June or December in year of training)</p>
      </div>
    </header>

    <?php if ($parent): ?>
    <div class="form-linked-training tea-official-linked">
      <span><i class="bi bi-link-45deg"></i> Rekod BPL dipilih</span>
      <strong><?= e((string) $parent['application_no']) ?> · <?= e((string) ($parent['title'] ?? 'Training')) ?></strong>
      <a href="application-detail.php?application=<?= rawurlencode((string) $parent['application_no']) ?>">Lihat BPL <i class="bi bi-arrow-up-right"></i></a>
    </div>
    <?php endif; ?>

    <div class="form-permission-notice tea-official-permission" data-form-permission-notice></div>

    <form class="tea-official-form" data-form-owner="head_of_department" method="post" action="submit_application.php?type=TEA">
      <?= csrf_field() ?>
      <?php if ($parentId > 0): ?>
      <input type="hidden" name="parent_application_id" value="<?= (int) $parentId ?>">
      <?php endif; ?>

      <section class="tea-official-info">
        <div class="tea-official-line-field">
          <label for="teaEmployee">Employee Name</label>
          <input id="teaEmployee" type="text" name="employee_name" required value="<?= e((string) ($parentPayload['nama'] ?? '')) ?>">
        </div>
        <div class="tea-official-line-field">
          <label for="teaDivision">Division/Section</label>
          <?php $selectedDivision = (string) ($parent['department'] ?? $parentPayload['bahagian'] ?? ''); ?>
          <select id="teaDivision" name="division" class="sts-department-select" required>
            <option value="">Select SEDCO Department / Division</option>
            <?php foreach ($sedcoDepartments as $departmentName): ?>
            <option value="<?= e($departmentName) ?>" <?= $selectedDivision === $departmentName ? 'selected' : '' ?>><?= e($departmentName) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="tea-official-month-row">
          <strong>Month</strong>
          <label><input type="radio" name="month" value="June" required> Jan/June</label>
          <label><input type="radio" name="month" value="December" checked> July/Dec</label>
          <span>(tick √ for the month of evaluation)</span>
        </div>
      </section>

      <section class="tea-official-rating">
        <strong>Rating</strong>
        <div class="tea-official-rating-cells" role="group" aria-label="Rating scale">
          <button type="button" data-tea-rating="1"><span>Poor</span><b>(1)</b></button>
          <button type="button" data-tea-rating="2"><span>Average</span><b>(2)</b></button>
          <button type="button" data-tea-rating="3"><span>Good</span><b>(3)</b></button>
          <button type="button" data-tea-rating="4"><span>Excellent</span><b>(4)</b></button>
        </div>
      </section>

      <section class="tea-official-assessment">
        <div class="tea-table-tools no-print">
          <div class="tea-table-tools-left">
            <button type="button" class="tea-tool-btn" data-add-training><i class="bi bi-plus-lg"></i> Add Training Row</button>
          </div>
          <div class="tea-table-tools-right">
            <input type="text" data-new-criterion placeholder="New criterion name">
            <button type="button" class="tea-tool-btn" data-add-criterion><i class="bi bi-layout-three-columns"></i> Add Column</button>
          </div>
        </div>
        <div class="tea-table-note no-print">
          <i class="bi bi-info-circle"></i>
          <span>Additional columns are saved as extra criteria. The official competency total remains based on the five original SEDCO criteria.</span>
        </div>

        <div class="tea-official-table-wrap">
          <table class="tea-official-table" data-tea-table>
            <thead>
              <tr>
                <th rowspan="2" class="tea-col-training">Training Title</th>
                <th colspan="5" class="tea-criteria-group" data-criteria-group>Criteria</th>
                <th rowspan="2" class="tea-col-total">Total<br>Score</th>
                <th rowspan="2" class="tea-col-level">Competency<br>Level <small>(please refer indicator ** below)</small></th>
                <th rowspan="2" class="tea-col-comments">Other improvements or comments <small>(please specify)</small></th>
              </tr>
              <tr data-criteria-head>
                <th>Productivity</th>
                <th>Quality<br>Of Work</th>
                <th>Skill<br>Enhancement</th>
                <th>Application<br>Of Knowledge</th>
                <th>Attitude</th>
              </tr>
            </thead>
            <tbody data-training-body>
              <tr data-training-row="0">
                <td class="tea-training-cell">
                  <input type="text" name="training_title_0" required value="<?= e((string) ($parent['title'] ?? $parentPayload['tajuk'] ?? '')) ?>" placeholder="Training title">
                  <button type="button" class="tea-row-remove no-print" data-remove-row title="Remove row" hidden><i class="bi bi-x-lg"></i></button>
                </td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="tea-score tea-score-standard" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="tea-score tea-score-standard" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="tea-score tea-score-standard" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="tea-score tea-score-standard" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="tea-score tea-score-standard" required></td>
                <td><input type="text" name="total_score_0" class="tea-total" readonly></td>
                <td>
                  <input type="hidden" name="competency_level_0" value="">
                  <span class="tea-level is-auto" data-level-output="0">Auto</span>
                </td>
                <td><textarea name="comments_0" rows="2" placeholder="Comments"></textarea></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="tea-official-ranking">
        <p><strong>** Please choose the appropriate ranking for the competency level of the employee after being trained</strong></p>
        <table>
          <thead><tr><th>Ranking</th><th>Remarks</th></tr></thead>
          <tbody>
            <tr><td>Fail</td><td>Scored 0 - 7 points. The employee has not shown any progress in his work. Re-training shall be provided on the particular topic.</td></tr>
            <tr><td>Probation</td><td>Scored 8 - 12 points. Still requires to be supervised by their immediate supervisor for another 6 month. Re-assessment required for the particular subject.</td></tr>
            <tr><td>Pass</td><td>Scored 13 - 17 points. Employee able to execute the job with minimum supervision.</td></tr>
            <tr><td>Merit</td><td>Scored 18 - 20 points. Employee to conduct training/guide other employees on the knowledge learnt on particular topic.</td></tr>
          </tbody>
        </table>
      </section>

      <section class="tea-official-evaluated">
        <h2>Evaluated by</h2>
        <div class="tea-evaluated-grid">
          <label><span>Head of Division/Section</span><input type="text" name="head_division" required readonly value="<?= e($user['fullname']) ?>"></label>
          <label><span>Date</span><input type="date" name="date" required></label>
          <label><span>Signature</span><input type="text" name="signature" required placeholder="Type your name as confirmation"></label>
        </div>
      </section>

      <div class="tea-official-actions no-print">
        <button type="button" class="btn btn-secondary form-print-button" onclick="printForm()"><i class="bi bi-printer"></i> Print</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-send-check"></i> Submit Assessment</button>
      </div>
    </form>
  </div>
</main>

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
    tr.innerHTML =
      '<td class="tea-training-cell"><input type="text" name="training_title_'+idx+'" required placeholder="Training title"><button type="button" class="tea-row-remove no-print" data-remove-row title="Remove row"><i class="bi bi-x-lg"></i></button></td>'+
      '<td><input type="number" name="score_'+idx+'[]" class="tea-score tea-score-standard" required></td>'.repeat(5)+
      '<td><input type="text" name="total_score_'+idx+'" class="tea-total" readonly></td>'+
      '<td><input type="hidden" name="competency_level_'+idx+'" value=""><span class="tea-level is-auto" data-level-output="'+idx+'">Auto</span></td>'+
      '<td><textarea name="comments_'+idx+'" rows="2" placeholder="Comments"></textarea></td>';

    const totalCell=tr.querySelector('.tea-total').closest('td');
    extraCriteria.forEach(() => totalCell.before(buildExtraScoreCell(idx)));

    body.appendChild(tr);
    bindRow(tr);
    syncRemoveButtons();
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
<script src="form-enhancements.js?v=20260930-59"></script>
<script src="sedco-shell.js?v=20261005-03"></script>
</body>
</html>
