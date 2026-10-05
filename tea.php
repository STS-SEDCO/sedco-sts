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
  <link rel="stylesheet" href="sedco-saas.css?v=20261005-15">
  <link rel="stylesheet" href="sedco-shell.css?v=20261005-03">
  <script>
    function printForm() { window.print(); }
  </script>
</head>
<body class="app-page form-page tea-page tea-premium-page" data-page="task" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">

<main class="tea-premium-content">
  <div class="form-container tea-premium-shell">
    <header class="tea-form-header">
      <div class="tea-form-header-icon"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="tea-form-header-copy">
        <span class="tea-form-kicker">PENILAIAN KEBERKESANAN LATIHAN</span>
        <h1>Training Effectiveness Assessment</h1>
        <p>Penilaian peningkatan prestasi selepas latihan, dilaksanakan pada bulan Jun atau Disember tahun latihan.</p>
      </div>
      <span class="tea-form-owner"><i class="bi bi-person-check"></i> Head of Department</span>
    </header>

    <?php if ($parent): ?>
    <div class="form-linked-training tea-linked-training">
      <span><i class="bi bi-link-45deg"></i> Rekod BPL dipilih</span>
      <strong><?= e((string) $parent['application_no']) ?> · <?= e((string) ($parent['title'] ?? 'Training')) ?></strong>
      <a href="application-detail.php?application=<?= rawurlencode((string) $parent['application_no']) ?>">Lihat BPL <i class="bi bi-arrow-up-right"></i></a>
    </div>
    <?php endif; ?>

    <div class="form-permission-notice tea-premium-permission" data-form-permission-notice></div>

    <form class="tea-premium-form" data-form-owner="head_of_department" method="post" action="submit_application.php?type=TEA">
      <?= csrf_field() ?>

      <section class="tea-section-card tea-section-overview">
        <div class="tea-section-heading">
          <span class="tea-section-number">01</span>
          <div class="tea-section-heading-copy">
            <strong>Assessment information</strong>
            <small>Employee details and evaluation period</small>
          </div>
        </div>

        <div class="tea-info-grid">
          <label class="tea-field">
            <span>Employee name <b>*</b></span>
            <div class="tea-control-wrap">
              <i class="bi bi-person"></i>
              <input type="text" name="employee_name" class="input-field" required
                placeholder="Enter employee name"
                value="<?= e((string) ($parentPayload['nama'] ?? '')) ?>">
            </div>
          </label>

          <label class="tea-field">
            <span>Division / Section <b>*</b></span>
            <div class="tea-control-wrap">
              <i class="bi bi-building"></i>
              <?php $selectedDivision = (string) ($parent['department'] ?? $parentPayload['bahagian'] ?? ''); ?>
              <select name="division" class="input-field sts-department-select" required>
                <option value="">Select SEDCO Department / Division</option>
                <?php foreach ($sedcoDepartments as $departmentName): ?>
                <option value="<?= e($departmentName) ?>" <?= $selectedDivision === $departmentName ? 'selected' : '' ?>><?= e($departmentName) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </label>
        </div>

        <div class="tea-period-block">
          <div class="tea-period-copy">
            <strong>Evaluation period</strong>
            <span>Select the applicable assessment cycle.</span>
          </div>
          <div class="tea-period-options">
            <label class="tea-period-option">
              <input type="radio" name="month" value="June" required>
              <span><i class="bi bi-calendar3"></i> January / June</span>
            </label>
            <label class="tea-period-option">
              <input type="radio" name="month" value="December" checked>
              <span><i class="bi bi-calendar3"></i> July / December</span>
            </label>
          </div>
        </div>
      </section>

      <section class="tea-section-card tea-rating-panel">
        <div class="tea-section-heading">
          <span class="tea-section-number">02</span>
          <div class="tea-section-heading-copy">
            <strong>Rating scale</strong>
            <small>Choose a score from 1 (Poor) to 4 (Excellent)</small>
          </div>
        </div>
        <div class="tea-rating-grid" role="group" aria-label="Rating scale quick picker">
          <button type="button" class="tea-rating-choice" data-tea-rating="1" aria-pressed="false">
            <strong>1</strong><span>Poor</span>
          </button>
          <button type="button" class="tea-rating-choice" data-tea-rating="2" aria-pressed="false">
            <strong>2</strong><span>Average</span>
          </button>
          <button type="button" class="tea-rating-choice" data-tea-rating="3" aria-pressed="false">
            <strong>3</strong><span>Good</span>
          </button>
          <button type="button" class="tea-rating-choice" data-tea-rating="4" aria-pressed="false">
            <strong>4</strong><span>Excellent</span>
          </button>
        </div>
        <div class="tea-rating-helper" data-tea-rating-helper>
          <i class="bi bi-cursor"></i>
          <span>Click a score box in Assessment Criteria, then choose a rating above.</span>
        </div>
      </section>

      <section class="tea-section-card tea-assessment-section">
        <div class="tea-section-heading">
          <span class="tea-section-number">03</span>
          <div class="tea-section-heading-copy">
            <strong>Assessment criteria</strong>
            <small>Rate each performance criterion for the selected training</small>
          </div>
          <span class="tea-owner-chip"><i class="bi bi-pencil-square"></i> Your section</span>
        </div>

        <div class="tea-assessment-summary">
          <div><i class="bi bi-ui-checks-grid"></i><span>Rate each criterion from <strong>1 to 4</strong>.</span></div>
          <div><i class="bi bi-calculator"></i><span>Total score is calculated automatically.</span></div>
        </div>

        <div class="tea-table-scroll">
          <table class="tea-assessment-table">
            <thead>
              <tr>
                <th rowspan="2" class="tea-title-col">Training Title</th>
                <th colspan="5">Assessment Criteria</th>
                <th rowspan="2">Total Score</th>
                <th rowspan="2">Competency Level</th>
                <th rowspan="2" class="tea-comments-col">Additional Comments</th>
              </tr>
              <tr>
                <th>Productivity</th>
                <th>Quality of Work</th>
                <th>Skill Enhancement</th>
                <th>Application of Knowledge</th>
                <th>Attitude</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="tea-training-title"><?= e((string) ($parent['title'] ?? 'Bengkel Klasifikasi Sistem Fail Fungsian')) ?></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small" placeholder="1–4" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small" placeholder="1–4" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small" placeholder="1–4" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small" placeholder="1–4" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small" placeholder="1–4" required></td>
                <td><input type="text" name="total_score_0" class="tea-total-input" readonly placeholder="Auto"></td>
                <td>
                  <input type="hidden" name="competency_level_0" value="">
                  <span class="tea-competency-output is-auto" data-competency-output="0">
                    <i class="bi bi-stars"></i><span>Auto</span>
                  </span>
                </td>
                <td><input type="text" name="comments_0" class="tea-comment-input" placeholder="Optional comment"></td>
              </tr>
              <tr>
                <td class="tea-training-title">Public Speaking &amp; Presentation Skill</td>
                <td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small" placeholder="1–4" required></td>
                <td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small" placeholder="1–4" required></td>
                <td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small" placeholder="1–4" required></td>
                <td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small" placeholder="1–4" required></td>
                <td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small" placeholder="1–4" required></td>
                <td><input type="text" name="total_score_1" class="tea-total-input" readonly placeholder="Auto"></td>
                <td>
                  <input type="hidden" name="competency_level_1" value="">
                  <span class="tea-competency-output is-auto" data-competency-output="1">
                    <i class="bi bi-stars"></i><span>Auto</span>
                  </span>
                </td>
                <td><input type="text" name="comments_1" class="tea-comment-input" placeholder="Optional comment"></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="tea-inline-note">
          <i class="bi bi-info-circle"></i>
          <span>Complete all five criteria. The competency level will follow the total score guide below.</span>
        </div>
      </section>

      <section class="tea-section-card tea-ranking-section">
        <div class="tea-section-heading">
          <span class="tea-section-number">04</span>
          <div class="tea-section-heading-copy">
            <strong>Competency ranking guide</strong>
            <small>Reference level generated from the total assessment score</small>
          </div>
        </div>

        <div class="tea-ranking-grid">
          <article class="tea-rank-card rank-fail">
            <span class="tea-ranking-icon"><i class="bi bi-exclamation-circle"></i></span>
            <span class="tea-ranking-score">0–7</span>
            <div><strong>Fail</strong><p>No significant improvement observed. Additional training is recommended.</p></div>
          </article>
          <article class="tea-rank-card rank-probation">
            <span class="tea-ranking-icon"><i class="bi bi-hourglass-split"></i></span>
            <span class="tea-ranking-score">8–12</span>
            <div><strong>Probation</strong><p>Requires supervision for 6 months. A reassessment is required.</p></div>
          </article>
          <article class="tea-rank-card rank-pass">
            <span class="tea-ranking-icon"><i class="bi bi-check2-circle"></i></span>
            <span class="tea-ranking-score">13–17</span>
            <div><strong>Pass</strong><p>Can perform tasks with minimal supervision.</p></div>
          </article>
          <article class="tea-rank-card rank-merit">
            <span class="tea-ranking-icon"><i class="bi bi-stars"></i></span>
            <span class="tea-ranking-score">18–20</span>
            <div><strong>Merit</strong><p>Shows excellent competency and can guide others.</p></div>
          </article>
        </div>
      </section>

      <section class="tea-section-card tea-evaluator-section">
        <div class="tea-section-heading">
          <span class="tea-section-number">05</span>
          <div class="tea-section-heading-copy">
            <strong>Evaluator confirmation</strong>
            <small>Confirm HOD details, evaluation date and signature</small>
          </div>
        </div>

        <div class="tea-confirmation-grid">
          <label class="tea-field">
            <span><i class="bi bi-person-badge"></i> Head of Division / Section <b>*</b></span>
            <input type="text" name="head_division" class="input-field tea-auto-field" required readonly value="<?= e($user['fullname']) ?>">
          </label>
          <label class="tea-field">
            <span><i class="bi bi-calendar-check"></i> Date of Evaluation <b>*</b></span>
            <input type="date" name="date" class="input-field" required>
          </label>
          <label class="tea-field tea-field-full">
            <span><i class="bi bi-pen"></i> Signature / Confirmation <b>*</b></span>
            <input type="text" name="signature" class="input-field" required placeholder="Type your name as confirmation">
          </label>
        </div>
      </section>

      <div class="tea-form-actions form-actions no-print">
        <button type="button" onclick="printForm()" class="btn btn-secondary form-print-button">
          <i class="bi bi-printer"></i> Print
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-send-check"></i> Submit Assessment
        </button>
      </div>
    </form>
  </div>
</main>

<script>window.SEDCO_FORM_CONTEXT = { role: <?= json_encode($user['role'] ?? 'staff') ?>, mode: 'new', formType: 'TEA' };</script>
<script>
(() => {
  const initTeaCalculator = () => {
    const form = document.querySelector('.tea-premium-form');
    if (!form || form.dataset.teaCalculatorReady === '1') return;
    form.dataset.teaCalculatorReady = '1';

    const levelMeta = value => ({
      Fail: { icon:'bi-exclamation-circle', cls:'is-fail' },
      Probation: { icon:'bi-hourglass-split', cls:'is-probation' },
      Pass: { icon:'bi-check2-circle', cls:'is-pass' },
      Merit: { icon:'bi-stars', cls:'is-merit' }
    }[value] || { icon:'bi-stars', cls:'is-auto' });

    const updateRow = row => {
      const scores = [...form.querySelectorAll('input[name="score_' + row + '[]"]')];
      const total = form.querySelector('input[name="total_score_' + row + '"]');
      const level = form.querySelector('input[name="competency_level_' + row + '"]');
      const output = form.querySelector('[data-competency-output="' + row + '"]');

      if (!scores.length || !total || !level || !output) return;

      let complete = true;
      const values = scores.map(input => {
        const raw = String(input.value || '').trim();

        if (raw === '') {
          complete = false;
          return 0;
        }

        let value = Number(raw);
        if (!Number.isFinite(value)) {
          input.value = '';
          complete = false;
          return 0;
        }

        value = Math.round(value);
        value = Math.max(1, Math.min(4, value));

        if (String(input.value) !== String(value)) {
          input.value = String(value);
          input.classList.add('is-score-corrected');
          window.setTimeout(() => input.classList.remove('is-score-corrected'), 450);
        }

        return value;
      });

      output.classList.remove('is-auto','is-fail','is-probation','is-pass','is-merit');

      if (!complete) {
        total.value = '';
        level.value = '';
        output.classList.add('is-auto');
        output.innerHTML = '<i class="bi bi-stars"></i><span>Auto</span>';
        return;
      }

      const sum = values.reduce((acc, value) => acc + value, 0);
      total.value = String(sum);

      let competency = 'Merit';
      if (sum <= 7) competency = 'Fail';
      else if (sum <= 12) competency = 'Probation';
      else if (sum <= 17) competency = 'Pass';

      level.value = competency;
      const meta = levelMeta(competency);
      output.classList.add(meta.cls);
      output.innerHTML = '<i class="bi ' + meta.icon + '"></i><span>' + competency + '</span>';
    };

    [0,1].forEach(row => {
      const scores = [...form.querySelectorAll('input[name="score_' + row + '[]"]')];

      scores.forEach(input => {
        input.min = '1';
        input.max = '4';
        input.step = '1';
        input.inputMode = 'numeric';

        ['input','change','keyup','blur'].forEach(eventName => {
          input.addEventListener(eventName, () => updateRow(row));
        });
      });

      updateRow(row);
    });

    let activeScore = null;

    form.querySelectorAll('.score-input-small').forEach(input => {
      input.addEventListener('focus', () => {
        activeScore = input;
        form.querySelectorAll('.score-input-small').forEach(other => {
          other.classList.toggle('is-rating-target', other === input);
        });
      });
    });

    form.querySelectorAll('[data-tea-rating]').forEach(button => {
      button.addEventListener('click', () => {
        if (!activeScore) {
          const firstEmpty = [...form.querySelectorAll('.score-input-small')].find(input => !input.value);
          activeScore = firstEmpty || form.querySelector('.score-input-small');
        }

        if (!activeScore) return;

        const rating = Number(button.dataset.teaRating || 0);
        if (rating < 1 || rating > 4) return;

        activeScore.value = String(rating);
        activeScore.dispatchEvent(new Event('input', { bubbles:true }));

        const group = activeScore.name.includes('score_1') ? 1 : 0;
        updateRow(group);

        form.querySelectorAll('[data-tea-rating]').forEach(choice => {
          const selected = choice === button;
          choice.classList.toggle('is-selected', selected);
          choice.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTeaCalculator, { once:true });
  } else {
    initTeaCalculator();
  }
})();
</script>
<script src="form-permissions.js?v=20261005-04"></script>
<script src="form-ux.js?v=20261005-04"></script>
<script src="form-enhancements.js?v=20260930-59"></script>
<script src="sedco-shell.js?v=20261005-03"></script>
</body>
</html>
