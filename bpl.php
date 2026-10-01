<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$mode = 'new';
$application = null;
$payload = [];
$currentStage = '';
$applicationStatus = 'pending';
$applicationNo = trim((string) ($_GET['application'] ?? ''));

if ($applicationNo !== '') {
    $stmt = db()->prepare(
        'SELECT a.id, a.application_no, a.user_id, a.form_type, a.title, a.payload,
                a.department, a.assigned_hod_id, a.sla_due_at,
                a.status, a.current_stage, a.submitted_at, u.fullname AS applicant_name
         FROM applications a
         INNER JOIN users u ON u.id = a.user_id
         WHERE a.application_no = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $applicationNo);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$application || $application['form_type'] !== 'BPL') {
        http_response_code(404);
        exit('Application not found.');
    }

    $isOwner = (int) $application['user_id'] === (int) $user['id'];

    if (!$isOwner && !sts_can_view_application($application, $user)) {
        http_response_code(403);
        exit('You do not have permission to view this application.');
    }

    $mode = 'review';
    $payload = json_decode((string) $application['payload'], true);
    $payload = is_array($payload) ? $payload : [];
    $currentStage = (string) $application['current_stage'];
    $applicationStatus = (string) $application['status'];
}

if ($mode === 'new') {
    $malaysiaNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'));
    $payload = [
        'nama' => (string) ($user['fullname'] ?? ''),
        'bahagian' => (string) ($user['department'] ?? ''),
        'jawatan' => (string) ($user['job_title'] ?? ''),
        'tarikh' => $malaysiaNow->format('Y-m-d'),
    ];
}

$sedcoDepartments = sts_sedco_departments();
$profileApplicantComplete = trim((string) ($user['fullname'] ?? '')) !== ''
    && trim((string) ($user['department'] ?? '')) !== ''
    && trim((string) ($user['job_title'] ?? '')) !== '';
$profileDepartmentKnown = trim((string) ($user['department'] ?? '')) === ''
    || in_array(trim((string) $user['department']), $sedcoDepartments, true);

$canResubmitCorrection = $mode === 'review'
    && $isOwner
    && $applicationStatus === 'correction';

$formAction = $mode === 'new'
    ? 'submit_application.php?type=BPL'
    : ($canResubmitCorrection ? 'resubmit_application.php' : 'review_application.php');

$canReviewCurrentStage = $mode === 'review'
    && !$canResubmitCorrection
    && !in_array($applicationStatus, ['approved', 'rejected'], true)
    && $currentStage !== 'completed'
    && sts_can_review_application($application, $user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borang Permohonan Latihan (BPL)</title>

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script>
        function printPage() {
            window.print();
        }
    </script>
    <link rel="stylesheet" href="sedco-saas.css?v=20261001-06">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-shell.css?v=20260930-56">
</head>
<body class="app-page form-page bpl-page" data-page="task" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">

    <!-- Navbar -->
    <!-- Sidebar -->
    <!-- Form Content -->
    <div class="content">
        <h2>BORANG PERMOHONAN LATIHAN (BPL)</h2>
        <?php if ($mode === 'review'): ?>
        <div class="bpl-workflow-meta">
          <span><i class="bi bi-file-earmark-check"></i><?= e($application['application_no']) ?></span>
          <span><i class="bi bi-person"></i><?= e($application['applicant_name']) ?></span>
          <span><i class="bi bi-diagram-3"></i><?= e(stage_label($currentStage)) ?></span>
          <?php if (!empty($application['sla_due_at']) && $applicationStatus === 'pending'): ?>
          <span class="<?= strtotime((string) $application['sla_due_at']) < time() ? 'is-overdue' : '' ?>">
            <i class="bi bi-alarm"></i>SLA <?= e(date('d M Y, g:i A', strtotime((string) $application['sla_due_at']))) ?>
          </span>
          <?php endif; ?>
          <a href="application-detail.php?application=<?= rawurlencode((string) $application['application_no']) ?>">
            <i class="bi bi-clock-history"></i> History
          </a>
        </div>
        <?php endif; ?>

        <div class="form-permission-notice" data-form-permission-notice></div>

        <form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <?php if ($mode === 'review'): ?>
      <input type="hidden" name="application_no" value="<?= e($application['application_no']) ?>">
      <?php endif; ?>

            <!-- A. MAKLUMAT PEMOHON -->
            <?php if ($mode === 'new'): ?>
            <div class="sts-profile-source-note <?= (!$profileApplicantComplete || !$profileDepartmentKnown) ? 'is-warning' : '' ?>">
                <i class="bi <?= (!$profileApplicantComplete || !$profileDepartmentKnown) ? 'bi-exclamation-circle' : 'bi-person-check' ?>"></i>
                <span>
                    <?php if (!$profileApplicantComplete || !$profileDepartmentKnown): ?>
                    Lengkapkan Nama, Bahagian SEDCO dan Jawatan di <a href="profile.php">Profile</a> terlebih dahulu. Maklumat Bahagian A diambil terus daripada Profile.
                    <?php else: ?>
                    Nama, Bahagian dan Jawatan diisi automatik daripada Profile. Tarikh akan disahkan semula mengikut tarikh sebenar anda menghantar borang.
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
            <table data-form-owner="staff">
                <tr><th colspan="4">A. MAKLUMAT PEMOHON</th></tr>
                <tr>
                    <td>01. Nama</td>
                    <td><input type="text" name="nama" class="input-field sts-profile-sourced" required readonly value="<?= e((string) ($payload['nama'] ?? '')) ?>" data-profile-sourced="1"></td>
                    <td>02. Bahagian</td>
                    <td>
                        <select name="bahagian" class="input-field sts-department-select sts-profile-sourced" required data-profile-sourced="1" data-profile-locked="1">
                            <option value="">Pilih nama penuh bahagian SEDCO</option>
                            <?php foreach ($sedcoDepartments as $departmentName): ?>
                            <option value="<?= e($departmentName) ?>" <?= (string) ($payload['bahagian'] ?? '') === $departmentName ? 'selected' : '' ?>>
                                <?= e($departmentName) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>03. Jawatan</td>
                    <td colspan="3"><input type="text" name="jawatan" class="input-field sts-profile-sourced" required readonly value="<?= e((string) ($payload['jawatan'] ?? '')) ?>" data-profile-sourced="1"></td>
                </tr>
                <tr>
                    <td>04. Kursus/Seminar</td>
                    <td colspan="3"><input type="text" name="kursus" class="input-field" required></td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td colspan="3"><input type="date" name="tarikh" required readonly value="<?= e((string) ($payload['tarikh'] ?? '')) ?>" data-submission-date="1"></td>
                </tr>
            </table>

            <!-- B. MAKLUMAT KURSUS/SEMINAR -->
            <table data-form-owner="staff">
                <tr><th colspan="4">B. MAKLUMAT KURSUS/SEMINAR</th></tr>
                <tr>
                    <td>01. Tajuk Kursus</td>
                    <td colspan="3"><input type="text" name="tajuk" class="input-field" required></td>
                </tr>
                <tr>
                    <td>02. Penganjur</td>
                    <td colspan="3"><input type="text" name="penganjur" class="input-field" required></td>
                </tr>
                <tr>
                    <td>03. Tarikh Mula</td>
                    <td><input type="date" name="tarikh_mula" required></td>
                    <td>04. Tarikh Tamat</td>
                    <td><input type="date" name="tarikh_tamat" required></td>
                </tr>
                <tr>
                    <td>05. Tempat Kursus</td>
                    <td colspan="3"><input type="text" name="tempat" class="input-field" required></td>
                </tr>
                <tr>
                    <td>06. Yuran (RM)</td>
                    <td colspan="3"><input type="text" name="yuran" class="input-field" required></td>
                </tr>
                <tr>
                    <td>07. Kandungan</td>
                    <td colspan="3"><textarea name="kandungan" class="input-field" rows="4" required></textarea></td>
                </tr>
            </table>

            <!-- C. MAKLUMAT TUGAS LUAR DAERAH -->
            <table data-form-owner="staff">
                <tr><th colspan="4">C. MAKLUMAT TUGAS LUAR DAERAH</th></tr>
                <tr>
                    <td>08. Tempat Bertugas</td>
                    <td colspan="3"><input type="text" name="tempat_tugas" class="input-field"></td>
                </tr>
                <tr>
                    <td>i. Kenderaan</td>
                    <td colspan="3">
                        <label><input type="checkbox" name="kenderaan[]" value="Kapal Terbang"> Kapal Terbang</label>
                        <label><input type="checkbox" name="kenderaan[]" value="Kenderaan Pejabat"> Kenderaan Pejabat</label>
                        <label><input type="checkbox" name="kenderaan[]" value="Kenderaan Sendiri"> Kenderaan sendiri</label>
                        <label><input type="checkbox" name="kenderaan[]" value="Lain-lain" data-other-trigger="kenderaan_other"> Lain-lain</label>

                        <div class="sts-other-field sts-other-inline" data-other-field="kenderaan_other" hidden>
                            <label for="kenderaan_other">
                                <span>Nyatakan kenderaan lain <b>*</b></span>
                                <input
                                    id="kenderaan_other"
                                    type="text"
                                    name="kenderaan_other"
                                    maxlength="120"
                                    placeholder="Contoh: Grab, teksi, bas..."
                                    disabled
                                >
                            </label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>ii. Masa Bertolak</td>
                    <td><input type="time" name="masa_bertolak"></td>
                    <td>iii. Masa Kembali</td>
                    <td><input type="time" name="masa_kembali"></td>
                </tr>
                <tr>
                    <td>09. Pendahuluan</td>
                    <td colspan="3">
                        <label><input type="radio" name="pendahuluan" value="Ya"> Ya</label>
                        <label><input type="radio" name="pendahuluan" value="Tidak"> Tidak</label>
                    </td>
                </tr>
            </table>

            <!-- ULASAN C - F -->
            <table data-form-owner="training_section">
                <tr><th colspan="2">C. ULASAN PENGURUS SEKSYEN LATIHAN</th></tr>
                <tr>
                    <td>Ulasan</td>
                    <td><textarea name="ulasan_latihan" class="input-field" rows="4" required></textarea></td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td><input type="date" name="tarikh_latihan" required></td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td><input type="text" name="tt_latihan" class="input-field" required></td>
                </tr>
            </table>

            <table data-form-owner="head_of_department">
                <tr><th colspan="2">D. ULASAN KETUA/PENGURUS BAHAGIAN</th></tr>
                <tr>
                    <td>Ulasan</td>
                    <td><textarea name="ulasan_bahagian" class="input-field" rows="4" required></textarea></td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td><input type="date" name="tarikh_bahagian" required></td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td><input type="text" name="tt_bahagian" class="input-field" required></td>
                </tr>
            </table>

            <table data-form-owner="general_manager">
                <tr><th colspan="2">E. ULASAN PENGURUS BESAR KUMPULAN SEDCO</th></tr>
                <tr>
                    <td>Kelulusan</td>
                    <td>
                        <label><input type="radio" name="kelulusan_pgs" value="Diluluskan" required> Diluluskan</label>
                        <label><input type="radio" name="kelulusan_pgs" value="Tidak Diluluskan"> Tidak Diluluskan</label>
                    </td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td><input type="date" name="tarikh_pgs" required></td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td><input type="text" name="tt_pgs" class="input-field" required></td>
                </tr>
            </table>

            <table data-form-owner="admin">
                <tr><th colspan="2">F. ULASAN PENGURUS SEDCO</th></tr>
                <tr>
                    <td>Kelulusan</td>
                    <td>
                        <label><input type="radio" name="kelulusan_sedco" value="Diluluskan"> Diluluskan</label>
                        <label><input type="radio" name="kelulusan_sedco" value="Tidak Diluluskan"> Tidak Diluluskan</label>
                    </td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td><input type="date" name="tarikh_sedco"></td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td><input type="text" name="tt_sedco" class="input-field"></td>
                </tr>
            </table>

            <!-- FINAL CHECKLIST -->
            <table data-form-owner="training_section">
                <tr><th colspan="2">ULASAN KEWANGAN</th></tr>
                <tr>
                    <td>a) Bayaran Kursus</td>
                    <td><input type="text" name="bayaran_kursus" required> Yuran (RM)</td>
                </tr>
                <tr>
                    <td>b) Permohonan Pendahuluan Diterima</td>
                    <td>
                        <label><input type="radio" name="pendahuluan_diterima" value="Ya" required> Ya</label>
                        <label><input type="radio" name="pendahuluan_diterima" value="Tidak"> Tidak</label>
                    </td>
                </tr>
                <tr>
                    <td>c) Telah Didaftarkan</td>
                    <td>
                        <label><input type="radio" name="telah_didaftar" value="Ya" required> Ya</label>
                        <label><input type="radio" name="telah_didaftar" value="Tidak"> Tidak</label>
                    </td>
                </tr>
            </table>

            <div class="form-actions">
                <?php if ($mode === 'new'): ?>
                <input type="submit" name="submit" value="Submit Application" class="btn-maroon">
                <?php elseif ($canResubmitCorrection): ?>
                <button type="submit" class="btn-maroon">
                  <i class="bi bi-arrow-repeat"></i> Resubmit Application
                </button>
                <?php elseif ($canReviewCurrentStage): ?>
                <button type="submit" name="decision" value="approved" class="btn-maroon btn-review-approve">
                  <i class="bi bi-check2"></i> <?= $currentStage === 'gm' ? 'Approve & Complete' : 'Approve & Continue' ?>
                </button>
                <button type="submit" name="decision" value="correction" class="btn-review-secondary">
                  <i class="bi bi-arrow-counterclockwise"></i> Needs Correction
                </button>
                <button type="submit" name="decision" value="rejected" class="btn-review-reject">
                  <i class="bi bi-x-lg"></i> Reject
                </button>
                <?php endif; ?>
                <button onclick="printPage()" type="button" class="btn-maroon form-print-button">
                  <i class="bi bi-printer"></i> Print
                </button>
            </div>

        </form>
</div>
<script>
window.SEDCO_BPL_DATA = <?= json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>;
window.SEDCO_FORM_CONTEXT = {
  role: <?= json_encode($user['role'] ?? 'staff') ?>,
  mode: <?= json_encode($mode) ?>,
  formType: 'BPL',
  currentStage: <?= json_encode($currentStage) ?>,
  status: <?= json_encode($applicationStatus) ?>
};
</script>
<script src="bpl-workflow.js?v=20261001-02"></script>
<script src="form-permissions.js?v=20260930-59"></script>
<script src="form-ux.js?v=20261001-03"></script>
<script src="form-enhancements.js?v=20260930-59"></script>
<script src="sedco-shell.js?v=20260930-56"></script>
</body>
</html>
