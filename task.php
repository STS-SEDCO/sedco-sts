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
  <title>Tasks - Smart Training System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20260930-22">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-18">
</head>
<body class="app-page task-page" data-page="task">

<main class="task-content">
  <div class="task-shell">
    <header class="task-heading">
      <div>
        <div class="task-eyebrow">Training workspace</div>
        <h1>Tasks</h1>
        <p>Select the form you need and continue your training workflow.</p>
      </div>
      <div class="task-count-chip">
        <span class="task-count-dot"></span>
        3 forms available
      </div>
    </header>

    <section class="task-grid" aria-label="Training forms">
      <article class="task-form-card">
        <div class="task-card-top">
          <span class="task-form-icon"><i class="bi bi-file-earmark-text"></i></span>
          <span class="task-form-code">BPL</span>
        </div>
        <div class="task-form-copy">
          <h2>Permohonan Latihan</h2>
          <p>Submit a new training application for approval and processing.</p>
        </div>
        <div class="task-card-footer">
          <span class="task-card-status"><i class="bi bi-circle-fill"></i> Ready to apply</span>
          <a href="bpl.php" class="task-apply-btn">Apply now <i class="bi bi-arrow-up-right"></i></a>
        </div>
      </article>

      <article class="task-form-card">
        <div class="task-card-top">
          <span class="task-form-icon"><i class="bi bi-clipboard2-check"></i></span>
          <span class="task-form-code">PKK</span>
        </div>
        <div class="task-form-copy">
          <h2>Penilaian Keberkesanan Kursus</h2>
          <p>Complete the post-course effectiveness evaluation after attending training.</p>
        </div>
        <div class="task-card-footer">
          <span class="task-card-status"><i class="bi bi-circle-fill"></i> Ready to apply</span>
          <a href="pkk.php" class="task-apply-btn">Apply now <i class="bi bi-arrow-up-right"></i></a>
        </div>
      </article>

      <article class="task-form-card">
        <div class="task-card-top">
          <span class="task-form-icon"><i class="bi bi-graph-up-arrow"></i></span>
          <span class="task-form-code">TEA</span>
        </div>
        <div class="task-form-copy">
          <h2>Training Effectiveness Assessment</h2>
          <p>Assess post-training performance, competency and improvement outcomes.</p>
        </div>
        <div class="task-card-footer">
          <span class="task-card-status"><i class="bi bi-circle-fill"></i> Ready to apply</span>
          <a href="tea.php" class="task-apply-btn">Apply now <i class="bi bi-arrow-up-right"></i></a>
        </div>
      </article>
    </section>
  </div>
</main>

<script src="sedco-shell.js?v=20260930-18"></script>
</body>
</html>