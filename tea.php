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
  <link rel="stylesheet" href="sedco-saas.css?v=20261005-07">
  <link rel="stylesheet" href="sedco-shell.css?v=20261005-03">
  <script>
    function printForm() { window.print(); }
  </script>
</head>
<body class="app-page form-page tea-page tea-premium-page" data-page="task" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">

<main class="tea-premium-content">
  <div class="form-container tea-premium-shell">
    <header class="tea-premium-hero">
      <div class="tea-premium-hero-copy">
        <span class="tea-premium-kicker"><i class="bi bi-graph-up-arrow"></i> Post-training evaluation</span>
        <h1>Training Effectiveness Assessment</h1>
        <p>Improvement assessment conducted in June or December of the training year.</p>
      </div>
      <div class="tea-premium-hero-badge">
        <i class="bi bi-person-check"></i>
        <div>
          <span>Form owner</span>
          <strong>Head of Department</strong>
        </div>
      </div>
    </header>

    <div class="form-permission-notice tea-premium-permission" data-form-permission-notice></div>

    <form class="tea-premium-form" data-form-owner="head_of_department" method="post" action="submit_application.php?type=TEA">
      <?= csrf_field() ?>

      <section class="tea-section-card tea-section-overview">
        <div class="tea-section-heading">
          <div>
            <span class="tea-section-eyebrow">Employee & evaluation</span>
            <h2>Assessment information</h2>
          </div>
          <span class="tea-section-number">01</span>
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
        <div class="tea-section-heading compact">
          <div>
            <span class="tea-section-eyebrow">Scoring guide</span>
            <h2>Rating scale</h2>
          </div>
          <span class="tea-section-number">02</span>
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
          <div>
            <span class="tea-section-eyebrow">Performance review</span>
            <h2>Assessment criteria</h2>
          </div>
          <span class="tea-section-number">03</span>
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
                <td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small" required></td>
                <td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small" required></td>
                <td><input type="text" name="total_score_0" class="tea-total-input"></td>
                <td>
                  <select name="competency_level_0" class="tea-competency-select">
                    <option value="Fail">Fail</option>
                    <option value="Probation">Probation</option>
                    <option value="Pass">Pass</option>
                    <option value="Merit">Merit</option>
                  </select>
                </td>
                <td><input type="text" name="comments_0" class="tea-comment-input" placeholder="Add comment"></td>
              </tr>
              <tr>
                <td class="tea-training-title">Public Speaking &amp; Presentation Skill</td>
                <td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small" required></td>
                <td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small" required></td>
                <td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small" required></td>
                <td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small" required></td>
                <td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small" required></td>
                <td><input type="text" name="total_score_1" class="tea-total-input"></td>
                <td>
                  <select name="competency_level_1" class="tea-competency-select">
                    <option value="Fail">Fail</option>
                    <option value="Probation">Probation</option>
                    <option value="Pass">Pass</option>
                    <option value="Merit">Merit</option>
                  </select>
                </td>
                <td><input type="text" name="comments_1" class="tea-comment-input" placeholder="Add comment"></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="tea-inline-note">
          <i class="bi bi-info-circle"></i>
          <span>Select the competency ranking that best reflects the employee's performance after training.</span>
        </div>
      </section>

      <section class="tea-section-card tea-ranking-section">
        <div class="tea-section-heading">
          <div>
            <span class="tea-section-eyebrow">Reference</span>
            <h2>Competency ranking guide</h2>
          </div>
          <span class="tea-section-number">04</span>
        </div>

        <div class="tea-ranking-grid">
          <article>
            <span class="tea-ranking-score">0–7</span>
            <div><strong>Fail</strong><p>No significant improvement observed. Additional training is recommended.</p></div>
          </article>
          <article>
            <span class="tea-ranking-score">8–12</span>
            <div><strong>Probation</strong><p>Requires supervision for 6 months. A reassessment is required.</p></div>
          </article>
          <article>
            <span class="tea-ranking-score">13–17</span>
            <div><strong>Pass</strong><p>Can perform tasks with minimal supervision.</p></div>
          </article>
          <article>
            <span class="tea-ranking-score">18</span>
            <div><strong>Merit</strong><p>Shows excellent competency and can guide others.</p></div>
          </article>
        </div>
      </section>

      <section class="tea-section-card tea-evaluator-section">
        <div class="tea-section-heading">
          <div>
            <span class="tea-section-eyebrow">Confirmation</span>
            <h2>Evaluator details</h2>
          </div>
          <span class="tea-section-number">05</span>
        </div>

        <div class="tea-info-grid tea-evaluator-grid">
          <label class="tea-field">
            <span>Head of Division / Section <b>*</b></span>
            <div class="tea-control-wrap">
              <i class="bi bi-person-badge"></i>
              <input type="text" name="head_division" class="input-field" required value="<?= e($user['fullname']) ?>">
            </div>
          </label>
          <label class="tea-field">
            <span>Date of Evaluation <b>*</b></span>
            <div class="tea-control-wrap">
              <i class="bi bi-calendar-check"></i>
              <input type="date" name="date" class="input-field" required>
            </div>
          </label>
          <label class="tea-field tea-field-full">
            <span>Signature / Confirmation <b>*</b></span>
            <div class="tea-control-wrap">
              <i class="bi bi-pen"></i>
              <input type="text" name="signature" class="input-field" required placeholder="Enter evaluator confirmation">
            </div>
          </label>
        </div>
      </section>

      <div class="tea-action-bar form-actions">
        <button type="button" onclick="printForm()" class="tea-action-secondary form-print-button">
          <i class="bi bi-printer"></i> Print form
        </button>
        <button type="submit" class="tea-action-primary">
          <i class="bi bi-check2-circle"></i> Submit assessment
        </button>
      </div>
    </form>
  </div>
</main>

<script>window.SEDCO_FORM_CONTEXT = { role: <?= json_encode($user['role'] ?? 'staff') ?>, mode: 'new', formType: 'TEA' };</script>
<script src="form-permissions.js?v=20261005-04"></script>
<script src="form-ux.js?v=20261005-01"></script>
<script src="form-enhancements.js?v=20260930-59"></script>
<script src="sedco-shell.js?v=20261005-03"></script>
</body>
</html>
