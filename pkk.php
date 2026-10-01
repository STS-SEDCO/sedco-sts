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
  <link rel="stylesheet" href="sedco-saas.css?v=20261001-21">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-56">
</head>
<body class="app-page form-page pkk-page" data-page="task" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">
  <main class="main-content">
    <div class="container shadow-lg p-5 bg-white rounded-4">
      <h2 class="text-center mb-3 text-uppercase fw-bold">Borang Penilaian Keberkesanan Kursus / Seminar</h2>
      <p class="text-center text-muted mb-4">
        <em>Borang ini hendaklah diisi dalam masa tujuh (7) hari bekerja selepas menghadiri kursus/seminar.</em>
      </p>

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
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Nama Pegawai/Staf</label>
            <input type="text" class="form-control" name="nama" required value="<?= e((string) ($parentPayload['nama'] ?? $user['fullname'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Bahagian</label>
            <?php $selectedDepartment = (string) ($parentPayload['bahagian'] ?? $user['department'] ?? ''); ?>
            <select class="form-control sts-department-select" name="bahagian" required>
              <option value="">Pilih nama penuh bahagian SEDCO</option>
              <?php foreach ($sedcoDepartments as $departmentName): ?>
              <option value="<?= e($departmentName) ?>" <?= $selectedDepartment === $departmentName ? 'selected' : '' ?>><?= e($departmentName) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Jawatan</label>
            <input type="text" class="form-control" name="jawatan" required value="<?= e((string) ($parentPayload['jawatan'] ?? $user['job_title'] ?? '')) ?>">
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Tajuk Kursus/Seminar</label>
            <input type="text" class="form-control" name="tajuk" required value="<?= e((string) ($parentPayload['tajuk'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Tarikh / Hari</label>
            <input type="date" class="form-control" name="tarikh" required value="<?= e((string) ($parentPayload['tarikh_tamat'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Tempat</label>
            <input type="text" class="form-control" name="tempat" required value="<?= e((string) ($parentPayload['tempat'] ?? '')) ?>">
          </div>
        </div>

        <hr class="my-4">

        <div class="mb-3 page-break">
          <label class="form-label fw-semibold">1. Objektif menghadiri kursus:</label>
          <textarea class="form-control" name="objektif" rows="3" required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">2. Lima (5) perkara yang anda pelajari:</label>
          <ol class="ps-3">
            <li><textarea class="form-control my-2" name="perkara1" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="perkara2" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="perkara3" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="perkara4" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="perkara5" rows="2" required></textarea></li>
          </ol>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold">3. Cadangan/Penambahbaikan:</label>
          <ol class="ps-3">
            <li><textarea class="form-control my-2" name="cadangan1" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="cadangan2" rows="2" required></textarea></li>
            <li><textarea class="form-control my-2" name="cadangan3" rows="2" required></textarea></li>
          </ol>
        </div>

        <hr class="my-4">

        <h5 class="fw-bold page-break">Penceramah</h5>
        <div class="row g-3 mb-4">
          <div class="col"><input type="text" class="form-control" name="p1" placeholder="Penceramah 1" required></div>
          <div class="col"><input type="text" class="form-control" name="p2" placeholder="Penceramah 2"></div>
          <div class="col"><input type="text" class="form-control" name="p3" placeholder="Penceramah 3"></div>
          <div class="col"><input type="text" class="form-control" name="p4" placeholder="Penceramah 4"></div>
          <div class="col"><input type="text" class="form-control" name="p5" placeholder="Penceramah 5"></div>
        </div>

        <p class="text-muted">
          Skala penilaian: <strong>10–9: Cemerlang, 8–6: Baik, 5–4: Sederhana, 3–1: Lemah</strong>
        </p>

        <div class="table-responsive">
          <table class="table table-bordered align-middle text-center">
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

        <div class="row g-3 mt-4">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Tandatangan</label>
            <input type="text" class="form-control" name="tandatangan" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Tarikh</label>
            <input type="date" class="form-control" name="tarikh_penilaian" required>
          </div>
        </div>

        <div class="text-end mt-4 no-print">
          <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">Submit</button>
          <button type="button" class="btn btn-secondary px-4 py-2 fw-semibold ms-2 form-print-button" onclick="window.print()">Print</button>
        </div>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
window.SEDCO_FORM_CONTEXT = { role: <?= json_encode($user['role'] ?? 'staff') ?>, mode: 'new', formType: 'PKK' };
</script>
<script src="form-permissions.js?v=20260930-59"></script>
<script src="form-ux.js?v=20261001-05"></script>
<script src="form-enhancements.js?v=20260930-59"></script>
<script src="sedco-shell.js?v=20260930-56"></script>
</body>
</html>
