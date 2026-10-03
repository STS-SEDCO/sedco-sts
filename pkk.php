<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$parent = null;
$parentPayload = [];
$editApplication = null;
$editPayload = [];
$isEditMode = false;
$editApplicationNo = trim((string) ($_GET['application'] ?? ''));
$userId = (int) ($user['id'] ?? 0);
$parentId = max(0, (int) ($_GET['parent'] ?? 0));

if ($editApplicationNo !== '') {
    $editStmt = db()->prepare(
        'SELECT id, application_no, user_id, parent_application_id, form_type,
                title, payload, status, current_stage
         FROM applications
         WHERE application_no = ?
         LIMIT 1'
    );
    $editStmt->bind_param('s', $editApplicationNo);
    $editStmt->execute();
    $editApplication = $editStmt->get_result()->fetch_assoc();
    $editStmt->close();

    if (
        !$editApplication
        || (string) $editApplication['form_type'] !== 'PKK'
        || !sts_can_edit_pkk($editApplication, $user)
    ) {
        http_response_code(403);
        exit('This PKK submission can no longer be edited.');
    }

    $isEditMode = true;
    $parentId = (int) ($editApplication['parent_application_id'] ?? 0);
    $editPayload = json_decode((string) ($editApplication['payload'] ?? ''), true);
    $editPayload = is_array($editPayload) ? $editPayload : [];
}

$malaysiaToday = (new DateTimeImmutable(
    'today',
    new DateTimeZone('Asia/Kuala_Lumpur')
))->format('Y-m-d');
$eligibleTrainings = [];
$includeParentId = $isEditMode ? $parentId : 0;

$trainingStmt = db()->prepare(
    'SELECT b.id, b.application_no, b.title, b.payload, b.training_end
     FROM applications b
     WHERE b.user_id = ?
       AND b.form_type = "BPL"
       AND b.status = "approved"
       AND b.training_end IS NOT NULL
       AND b.training_end <= ?
       AND (
         b.id = ?
         OR NOT EXISTS (
           SELECT 1 FROM applications p
           WHERE p.parent_application_id = b.id
             AND p.form_type = "PKK"
             AND p.status <> "cancelled"
         )
       )
     ORDER BY b.training_end DESC, b.id DESC'
);
$trainingStmt->bind_param('isi', $userId, $malaysiaToday, $includeParentId);
$trainingStmt->execute();
$trainingResult = $trainingStmt->get_result();

while ($training = $trainingResult->fetch_assoc()) {
    $trainingPayload = json_decode((string) ($training['payload'] ?? ''), true);
    $trainingPayload = is_array($trainingPayload) ? $trainingPayload : [];

    $training['course_title'] = trim((string) (
        $trainingPayload['tajuk']
        ?? $training['title']
        ?? ''
    ));
    $training['course_date'] = trim((string) (
        $trainingPayload['tarikh_tamat']
        ?? $training['training_end']
        ?? ''
    ));
    $training['course_place'] = trim((string) (
        $trainingPayload['tempat']
        ?? ''
    ));

    $eligibleTrainings[] = $training;

    if ($parentId > 0 && (int) $training['id'] === $parentId) {
        $parent = $training;
        $parentPayload = $trainingPayload;
    }
}
$trainingStmt->close();

if ($parentId > 0 && !$parent) {
    http_response_code(403);
    exit('This training record is not available for PKK evaluation.');
}

$profileName = trim((string) ($user['fullname'] ?? ''));
$profileDepartment = trim((string) ($user['department'] ?? ''));
$profileJobTitle = trim((string) ($user['job_title'] ?? ''));
$profileComplete = $profileName !== '' && $profileDepartment !== '' && $profileJobTitle !== '';
$formAction = $isEditMode ? 'update_pkk.php' : 'submit_application.php?type=PKK';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: Borang Penilaian</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261003-04">
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
        <span><i class="bi bi-link-45deg"></i> Rekod BPL dipilih</span>
        <strong><?= e((string) $parent['application_no']) ?>: <?= e((string) ($parent['course_title'] ?? $parent['title'])) ?></strong>
        <a href="application-detail.php?application=<?= rawurlencode((string) $parent['application_no']) ?>">Lihat BPL <i class="bi bi-arrow-up-right"></i></a>
      </div>
      <?php endif; ?>

      <div class="form-permission-notice" data-form-permission-notice></div>

      <form data-form-owner="staff" method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($isEditMode): ?>
        <input type="hidden" name="application_no" value="<?= e((string) $editApplication['application_no']) ?>">
        <?php endif; ?>
        <?php if (!$profileComplete): ?>
        <div class="sts-profile-source-note is-warning">
          <i class="bi bi-exclamation-circle"></i>
          <span>Lengkapkan Nama, Bahagian dan Jawatan di <a href="profile.php">Profile</a> sebelum mengisi PKK.</span>
        </div>
        <?php endif; ?>
        <section class="pkk-form-card pkk-information-card">
          <div class="pkk-section-heading">
            <span class="pkk-section-number">01</span>
            <div><strong>Maklumat Pemohon & Kursus</strong><small>Semak maklumat asas sebelum meneruskan penilaian</small></div>
          </div>
          <div class="row g-3 pkk-info-row">
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-person"></i> Nama Pegawai/Staf</label>
            <input type="text" class="form-control pkk-auto-field" name="nama" required readonly value="<?= e($profileName) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-building"></i> Bahagian</label>
            <input type="text" class="form-control pkk-auto-field" name="bahagian" required readonly value="<?= e($profileDepartment) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-briefcase"></i> Jawatan</label>
            <input type="text" class="form-control pkk-auto-field" name="jawatan" required readonly value="<?= e($profileJobTitle) ?>">
          </div>
        </div>

        <div class="row g-3 pkk-info-row pkk-info-row-secondary">
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-mortarboard"></i> Tajuk Kursus/Seminar</label>
            <?php if ($isEditMode): ?>
            <input type="hidden" name="parent_application_id" value="<?= (int) $parentId ?>">
            <?php endif; ?>
            <select class="form-control pkk-training-select" <?= $isEditMode ? '' : 'name="parent_application_id"' ?> id="pkkTrainingSelect" required <?= $eligibleTrainings && !$isEditMode ? '' : 'disabled' ?>>
              <option value=""><?= $eligibleTrainings ? 'Pilih kursus daripada BPL yang telah selesai' : 'Tiada kursus BPL yang tersedia untuk dinilai' ?></option>
              <?php foreach ($eligibleTrainings as $training): ?>
              <option
                value="<?= (int) $training['id'] ?>"
                data-title="<?= e((string) $training['course_title']) ?>"
                data-date="<?= e((string) $training['course_date']) ?>"
                data-place="<?= e((string) $training['course_place']) ?>"
                <?= $parent && (int) $parent['id'] === (int) $training['id'] ? 'selected' : '' ?>
              >
                <?= e((string) $training['course_title']) ?> (<?= e((string) $training['application_no']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-calendar3"></i> Tarikh Tamat Kursus</label>
            <input type="date" class="form-control pkk-auto-field" name="tarikh" id="pkkCourseDate" required readonly value="<?= e((string) ($parent['course_date'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold"><i class="bi bi-geo-alt"></i> Tempat</label>
            <input type="text" class="form-control pkk-auto-field" name="tempat" id="pkkCoursePlace" required readonly value="<?= e((string) ($parent['course_place'] ?? '')) ?>">
          </div>
          <input type="hidden" name="tajuk" id="pkkCourseTitle" value="<?= e((string) ($parent['course_title'] ?? '')) ?>">
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
          <span><b>9 hingga 10</b> Cemerlang</span>
          <span><b>6 hingga 8</b> Baik</span>
          <span><b>4 hingga 5</b> Sederhana</span>
          <span><b>1 hingga 3</b> Lemah</span>
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
          <button type="submit" class="btn btn-primary"><i class="bi <?= $isEditMode ? 'bi-check2-circle' : 'bi-send-check' ?>"></i> <?= $isEditMode ? 'Update Submission' : 'Submit' ?></button>
        </div>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
window.SEDCO_PKK_DATA = <?= json_encode(
    $editPayload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>;
(() => {
  const data = window.SEDCO_PKK_DATA || {};
  const form = document.querySelector('.pkk-page form');
  if (!form) return;

  Object.entries(data).forEach(([name, value]) => {
    if (['nama','bahagian','jawatan','tajuk','tarikh','tempat'].includes(name)) return;

    const fields = [...form.querySelectorAll('[name="' + CSS.escape(name) + '"], [name="' + CSS.escape(name) + '[]"]')];
    fields.forEach(field => {
      if (field.type === 'checkbox' || field.type === 'radio') {
        const values = Array.isArray(value) ? value.map(String) : [String(value)];
        field.checked = values.includes(String(field.value));
      } else if (value !== null && value !== undefined) {
        field.value = String(value);
      }
    });
  });
})();
</script>
  <script>
(() => {
  const select = document.getElementById('pkkTrainingSelect');
  const title = document.getElementById('pkkCourseTitle');
  const date = document.getElementById('pkkCourseDate');
  const place = document.getElementById('pkkCoursePlace');

  if (!select || !title || !date || !place) return;

  const syncTraining = () => {
    const option = select.options[select.selectedIndex];
    const hasSelection = Boolean(select.value && option);

    title.value = hasSelection ? (option.dataset.title || '') : '';
    date.value = hasSelection ? (option.dataset.date || '') : '';
    place.value = hasSelection ? (option.dataset.place || '') : '';
  };

  select.addEventListener('change', syncTraining);
  syncTraining();
})();
</script>
  <script>
window.SEDCO_FORM_CONTEXT = { role: <?= json_encode($user['role'] ?? 'staff') ?>, mode: <?= json_encode($isEditMode ? 'edit' : 'new') ?>, formType: 'PKK' };
</script>
<script src="form-permissions.js?v=20261003-06"></script>
<script src="form-ux.js?v=20261001-09"></script>
<script src="form-enhancements.js?v=20260930-59"></script>
<script src="sedco-shell.js?v=20260930-56"></script>
</body>
</html>
