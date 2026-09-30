<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Training Effectiveness Form - Tasks</title>

  <!-- Bootstrap CSS and Font Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script>
    function printForm() {
      window.print();
    }
  </script>
    <link rel="stylesheet" href="sedco-saas.css?v=20260930-13">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-13">
</head>
<body class="app-page form-page tea-page" data-page="task">

  <!-- Navbar -->
  <!-- Sidebar -->
  <!-- Main Content -->
  <div class="form-container">
    <div class="center mb-4">
      <h5 class="mt-3 fw-bold">TRAINING EFFECTIVENESS ASSESSMENT FORM</h5>
      <em>Post-Training Evaluation – Improvement Assessment (Conducted in June or December of the Training Year)</em>
    </div>

    <form method="post" action="submit_application.php?type=TEA">
      <?= csrf_field() ?>
      <table>
        <tr>
          <td class="no-border" colspan="2">Employee Name: <input type="text" name="employee_name" class="input-field" required></td>
          <td class="no-border" colspan="2">Division/Section: <input type="text" name="division" class="input-field" required></td>
        </tr>
        <tr>
          <td class="no-border" colspan="4">
            Evaluation Period:
            <label><input type="radio" name="month" value="June"> January / June</label>
            <label><input type="radio" name="month" value="December" checked> July / December</label>
            <br><small class="text-muted">(Please tick (√) the applicable evaluation period)</small>
          </td>
        </tr>
      </table>

      <div class="center my-3">
        <strong>Rating Scale</strong><br>
        <span class="rating-info">Poor (1) &nbsp; Average (2) &nbsp; Good (3) &nbsp; Excellent (4)</span>
      </div>

      <table>
        <thead>
          <tr>
            <th rowspan="2">Training Title</th>
            <th colspan="5">Assessment Criteria</th>
            <th rowspan="2">Total Score</th>
            <th rowspan="2">Competency Level</th>
            <th rowspan="2">Additional Comments</th>
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
                <td>Bengkel Klasifikasi Sistem Fail Fungsian</td><td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small"></td><td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small"></td><td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small"></td><td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small"></td><td><input type="number" name="score_0[]" min="1" max="4" class="score-input-small"></td>
                <td><input type="text" name="total_score_0" class="score-input-small"></td>
                <td>
                  <select name='competency_level_0'>
                    <option value='Fail'>Fail</option>
                    <option value='Probation'>Probation</option>
                    <option value='Pass'>Pass</option>
                    <option value='Merit'>Merit</option>
                  </select>
                </td>
                <td><input type='text' name='comments_0'></td>
              </tr>
<tr>
                <td>Public Speaking & Presentation Skill</td><td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small"></td><td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small"></td><td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small"></td><td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small"></td><td><input type="number" name="score_1[]" min="1" max="4" class="score-input-small"></td>
                <td><input type="text" name="total_score_1" class="score-input-small"></td>
                <td>
                  <select name='competency_level_1'>
                    <option value='Fail'>Fail</option>
                    <option value='Probation'>Probation</option>
                    <option value='Pass'>Pass</option>
                    <option value='Merit'>Merit</option>
                  </select>
                </td>
                <td><input type='text' name='comments_1'></td>
              </tr>
        </tbody>
      </table>

      <p><strong>Note:</strong> Please select the appropriate competency ranking based on the employee’s performance after training.</p>

      <table>
        <thead>
          <tr><th>Ranking</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td>Fail</td><td>0 – 7 points. No significant improvement observed. Re-training recommended.</td></tr>
          <tr><td>Probation</td><td>8 – 12 points. Requires supervision for 6 months. Re-assessment needed.</td></tr>
          <tr><td>Pass</td><td>13 – 17 points. Can perform tasks with minimal supervision.</td></tr>
          <tr><td>Merit</td><td>18 points. Shows excellent competency. Can guide others.</td></tr>
        </tbody>
      </table>

      <table>
        <tr><td class="no-border">Evaluated by: <strong></strong></td></tr>
        <tr><td class="no-border">Head of Division/Section: <input type="text" name="head_division" class="input-field" required></td></tr>
        <tr><td class="no-border">Date of Evaluation: <input type="date" name="date" class="input-field" required></td></tr>
        <tr><td class="no-border">Signature: <input type="text" name="signature" class="input-field" required></td></tr>
      </table>

      <div class="center mt-4">
        <input type="submit" value="Submit Form" class="btn btn-primary">
        <button type="button" onclick="printForm()" class="btn btn-secondary ms-3">Print Form</button>
      </div>
    </form>
  </div>
<script src="sedco-shell.js?v=20260930-13"></script>
</body>
</html>
