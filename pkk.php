<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$sedcoDepartments = sts_sedco_departments();

$parent = null;
$parentPayload = [];
$parentId = max(0, (int) ($_GET['parent'] ?? 0));

if ($parentId > 0) {
    $parent = sts_validate_parent_bpl($parentId, $user, 'PKK');

    if (!$parent) {
        http_response_code(403);
        exit('This training record is not available for PKK follow-up.');
    }

    $parentPayload = json_decode((string) $parent['payload'], true);
    $parentPayload = is_array($parentPayload) ? $parentPayload : [];
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System - Borang Penilaian</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261003-02">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-56">
</head>
<body class="app-page form-page pkk-page" data-page="task" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">
  <main class="main-content">
    <div class="container pkk-form-shell">
      <header class="pkk-form-header">
        <div class="pkk-form-header-icon"><i class="bi bi-clipboard2-check"></i></div>
        <div class="pkk-form-header-copy">
          <span class="pkk-form-kicker">PENILAIAN SELEPAS LATIHAN</span>
          <h2>Borang Penilaian Keberkesanan Kursus / Seminar</h2>
          <p>Borang ini hendaklah diisi dalam masa tujuh (7) hari bekerja selepas menghadiri kursus/seminar.</p>
        </div>
      </header>

      <?php if ($parent): ?>
      <div class="form-linked-training">
        <span><i class="bi bi-link-45deg"></i> Linked approved training</span>
        <strong><?= e($parent['application_no']) ?> · <?= e($parent['title']) ?></strong>
        <a href="application-detail.php?application=<?= rawurlencode((string) $parent['application_no']) ?>">View BPL <i class="bi bi-arrow-up-right"></i></a>
      </div>
      <?php endif; ?>

      <div class="form-permission-notice" data-form-permission-notice></div>

      <form data-form-owner="staff" method="post" action="submit_application.php?type=PKK" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($parent): ?><input type="hidden" name="parent_application_id" value="<?= (int) $parent['id'] ?>"><?php endif; ?>
        <section class="pkk-form-card pkk-information-card">
          <div class="pkk-section-heading">
            <span class="pkk-section-number">01</span>
            <div><strong>Maklumat Pemohon & Kursus</strong><small>Semak maklumat asas sebelum meneruskan penilaian</small></div>
          </div>
          <div class="row g-3 pkk-info-row">
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-person"></i> Nama Pegawai/Staf</label>
            <input type="text" class="form-control" name="nama" required value="<?= e((string) ($parentPayload['nama'] ?? $user['fullname'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-building"></i> Bahagian</label>
            <?php $selectedDepartment = (string) ($parentPayload['bahagian'] ?? $user['department'] ?? ''); ?>
            <select class="form-control sts-department-select" name="bahagian" required>
              <option value="">Pilih nama penuh bahagian SEDCO</option>
              <?php foreach ($sedcoDepartments as $departmentName): ?>
              <option value="<?= e($departmentName) ?>" <?= $selectedDepartment === $departmentName ? 'selected' : '' ?>><?= e($departmentName) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-briefcase"></i> Jawatan</label>
            <input type="text" class="form-control" name="jawatan" required value="<?= e((string) ($parentPayload['jawatan'] ?? $user['job_title'] ?? '')) ?>">
          </div>
        </div>

        <div class="row g-3 pkk-info-row pkk-info-row-secondary">
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-mortarboard"></i> Tajuk Kursus/Seminar</label>
            <input type="text" class="form-control" name="tajuk" required value="<?= e((string) ($parentPayload['tajuk'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-calendar3"></i> Tarikh / Hari</label>
            <input type="date" class="form-control" name="tarikh" required value="<?= e((string) ($parentPayload['tarikh_tamat'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-geo-alt"></i> Tempat</label>
            <input type="text" class="form-control" name="tempat" required value="<?= e((string) ($parentPayload['tempat'] ?? '')) ?>">
          </div>
        </div>
        </section>

        <section class="pkk-form-card pkk-reflection-card page-break">
          <div class="pkk-section-heading">
            <span class="pkk-section-number">02</span>
            <div><strong>Refleksi & Pembelajaran</strong><small>Catat hasil pembelajaran dan cadangan penambahbaikan</small></div>
          </div>

        <div class="pkk-question-block">
          <label class="form-label fw-semibold"><span class="pkk-question-number">1</span> Objektif menghadiri kursus</label>
          <textarea class="form-control" name="objektif" rows="3" required></textarea>
        </div>

        <div class="pkk-question-block">
          <label class="form-label fw-semibold"><span class="pkk-question-number">2</span> Lima (5) perkara yang anda pelajari</label>
          <ol class="ps-3">
            <li><textarea class="form-control my-2" name="perkara1" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="perkara2" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="perkara3" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="perkara4" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="perkara5" rows="2" required></textarea></li>
          </ol>
        </div>

        <div class="pkk-question-block">
          <label class="form-label fw-semibold"><span class="pkk-question-number">3</span> Cadangan / Penambahbaikan</label>
          <ol class="ps-3">
            <li><textarea class="form-control my-2" name="cadangan1" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="cadangan2" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="cadangan3" rows="2" required></textarea></li>
          </ol>
        </div>
        </section>

        <section class="pkk-form-card pkk-speaker-card page-break">
          <div class="pkk-section-heading">
            <span class="pkk-section-number">03</span>
            <div><strong>Penilaian Penceramah</strong><small>Masukkan nama penceramah dan skor bagi setiap aspek</small></div>
          </div>

        <div class="pkk-speaker-label"><i class="bi bi-people"></i> Nama Penceramah</div>
        <div class="row g-3 pkk-speaker-grid">
          <div class="col"><input type="text" class="form-control" name="p1" placeholder="Penceramah 1" required></div>
          <div class="col"><input type="text" class="form-control" name="p2" placeholder="Penceramah 2"></div>
          <div class="col"><input type="text" class="form-control" name="p3" placeholder="Penceramah 3"></div>
          <div class="col"><input type="text" class="form-control" name="p4" placeholder="Penceramah 4"></div>
          <div class="col"><input type="text" class="form-control" name="p5" placeholder="Penceramah 5"></div>
        </div>

        <div class="pkk-scale-guide">
          <span class="pkk-scale-title"><i class="bi bi-bar-chart"></i> Skala Penilaian</span>
          <span><b>10–9</b> Cemerlang</span>
          <span><b>8–6</b> Baik</span>
          <span><b>5–4</b> Sederhana</span>
          <span><b>3–1</b> Lemah</span>
        </div>

        <div class="table-responsive pkk-score-table-wrap">
          <table class="table table-bordered align-middle text-center pkk-score-table">
            <thead class="table-dark">
              <tr>
                <th>Aspek</th>
                <th>P1</th>
                <th>P2</th>
                <th>P3</th>
                <th>P4</th>
                <th>P5</th>
              </tr>
            </thead>
            <tbody>
<tr><td class="text-start">i. Kefahaman/Penguasaan terhadap subjek</td><td><input type="text" class="form-control text-center score-input" name="aspect0_p1" required></td><td><input type="text" class="form-control text-center score-input" name="aspect0_p2"></td><td><input type="text" class="form-control text-center score-input" name="aspect0_p3"></td><td><input type="text" class="form-control text-center score-input" name="aspect0_p4"></td><td><input type="text" class="form-control text-center score-input" name="aspect0_p5"></td></tr>
<tr><td class="text-start">ii. Penyampaian</td><td><input type="text" class="form-control text-center score-input" name="aspect1_p1" required></td><td><input type="text" class="form-control text-center score-input" name="aspect1_p2"></td><td><input type="text" class="form-control text-center score-input" name="aspect1_p3"></td><td><input type="text" class="form-control text-center score-input" name="aspect1_p4"></td><td><input type="text" class="form-control text-center score-input" name="aspect1_p5"></td></tr>
<tr><td class="text-start">iii. Penyediaan bahan/slaid</td><td><input type="text" class="form-control text-center score-input" name="aspect2_p1" required></td><td><input type="text" class="form-control text-center score-input" name="aspect2_p2"></td><td><input type="text" class="form-control text-center score-input" name="aspect2_p3"></td><td><input type="text" class="form-control text-center score-input" name="aspect2_p4"></td><td><input type="text" class="form-control text-center score-input" name="aspect2_p5"></td></tr>
<tr><td class="text-start">iv. Perhubungan dan penglibatan peserta</td><td><input type="text" class="form-control text-center score-input" name="aspect3_p1" required></td><td><input type="text" class="form-control text-center score-input" name="aspect3_p2"></td><td><input type="text" class="form-control text-center score-input" name="aspect3_p3"></td><td><input type="text" class="form-control text-center score-input" name="aspect3_p4"></td><td><input type="text" class="form-control text-center score-input" name="aspect3_p5"></td></tr>
<tr><td class="text-start">v. Penggunaan contoh-contoh yang diselitkan dalam ceramah</td><td><input type="text" class="form-control text-center score-input" name="aspect4_p1" required></td><td><input type="text" class="form-control text-center score-input" name="aspect4_p2"></td><td><input type="text" class="form-control text-center score-input" name="aspect4_p3"></td><td><input type="text" class="form-control text-center score-input" name="aspect4_p4"></td><td><input type="text" class="form-control text-center score-input" name="aspect4_p5"></td></tr>
            </tbody>
          </table>
        </div>
        </section>

        <section class="pkk-form-card pkk-confirmation-card">
          <div class="pkk-section-heading">
            <span class="pkk-section-number">04</span>
            <div><strong>Pengesahan</strong><small>Lengkapkan tandatangan dan tarikh penilaian</small></div>
          </div>
        <div class="row g-3 pkk-confirmation-grid">
          <div class="col-md-6">
            <label class="form-label fw-semibold"><i class="bi bi-pen"></i> Tandatangan</label>
            <input type="text" class="form-control" name="tandatangan" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold"><i class="bi bi-calendar-check"></i> Tarikh</label>
            <input type="date" class="form-control" name="tarikh_penilaian" required>
          </div>
        </div>
        </section>

        <div class="pkk-form-actions no-print">
          <button type="button" class="btn btn-secondary form-print-button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send-check"></i> Submit</button>
        </div>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
window.SEDCO_FORM_CONTEXT = { role: <?= json_encode($user['role'] ?? 'staff') ?>, mode: 'new', formType: 'PKK' };
</script>
<script src="form-permissions.js?v=20260930-59"></script>
<script src="form-ux.js?v=20261001-09"></script>
<script src="form-enhancements.js?v=20260930-59"></script>
<script src="sedco-shell.js?v=20260930-56"></script>
</body>
</html>
