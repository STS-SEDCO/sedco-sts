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
$isOwner = false;
$applicationNo = trim((string) ($_GET['application'] ?? ''));

if ($applicationNo === '') {
    $pendingPkkRequirement = sts_pending_pkk_requirement((int) ($user['id'] ?? 0));

    if ($pendingPkkRequirement) {
        header(
            'Location: task.php?pkk_required=1&parent='
            . (int) $pendingPkkRequirement['id']
        );
        exit;
    }
}

if ($applicationNo !== '') {
    $stmt = db()->prepare(
        'SELECT a.id, a.application_no, a.user_id, a.form_type, a.title, a.payload,
                a.department, a.assigned_hod_id, a.sla_due_at,
                a.status, a.current_stage, a.review_note, a.submitted_at, u.fullname AS applicant_name
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

    if ((string) $application['status'] === 'pending') {
        $application['current_stage'] = sts_sync_bpl_pending_stage($application);
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
    <link rel="stylesheet" href="sedco-saas.css?v=20261007-16">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-16">
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
        <?php if ($canResubmitCorrection && !empty($application['review_note'])): ?>
        <?php
          $correctionFieldLabels = [
              'kursus' => 'Kursus / Seminar',
              'tajuk' => 'Tajuk Kursus',
              'penganjur' => 'Penganjur',
              'tarikh_mula' => 'Tarikh Mula',
              'tarikh_tamat' => 'Tarikh Tamat',
              'tempat' => 'Tempat Kursus',
              'yuran' => 'Yuran',
              'kandungan' => 'Kandungan Kursus',
              'tempat_tugas' => 'Tempat Bertugas',
              'kenderaan' => 'Kenderaan',
              'kenderaan_other' => 'Kenderaan Lain',
              'masa_bertolak' => 'Masa Bertolak',
              'masa_kembali' => 'Masa Kembali',
              'pendahuluan' => 'Pendahuluan',
          ];
          $correctionFields = $payload['_correction_fields'] ?? [];
          $correctionFields = is_array($correctionFields) ? $correctionFields : [];
        ?>
        <div class="sts-correction-note sts-correction-target-note">
          <i class="bi bi-chat-left-text"></i>
          <div>
            <strong>Correction requested by <?= e(stage_label($currentStage)) ?></strong>
            <p><?= e((string) $application['review_note']) ?></p>
            <?php if ($correctionFields): ?>
            <div class="sts-correction-target-tags">
              <?php foreach ($correctionFields as $fieldName): ?>
              <span><i class="bi bi-pencil-square"></i><?= e($correctionFieldLabels[(string) $fieldName] ?? (string) $fieldName) ?></span>
              <?php endforeach; ?>
            </div>
            <small>Fields marked below are highlighted. Start from the first highlighted field.</small>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <?php if ($mode === 'review'): ?>
      <input type="hidden" name="application_no" value="<?= e($application['application_no']) ?>">
      <?php endif; ?>

            <!-- A. MAKLUMAT PEMOHON -->
            <?php if ($mode === 'new' && (!$profileApplicantComplete || !$profileDepartmentKnown)): ?>
            <div class="sts-profile-source-note is-warning">
                <i class="bi bi-exclamation-circle"></i>
                <span>Lengkapkan Nama, Bahagian SEDCO dan Jawatan di <a href="profile.php">Profile</a> terlebih dahulu. Maklumat Bahagian A diambil terus daripada Profile.</span>
            </div>
            <?php endif; ?>
            <section class="bpl-applicant-section" data-form-owner="staff">
                <div class="form-section-title bpl-applicant-section-title">
                    <div class="bpl-section-heading">
                        <span class="bpl-section-heading-icon"><i class="bi bi-person-vcard"></i></span>
                        <div>
                            <strong>A. MAKLUMAT PEMOHON</strong>
                            <small>Maklumat asas pemohon dan kursus yang dipohon</small>
                        </div>
                    </div>
                </div>

                <div class="bpl-applicant-grid">
                    <label class="bpl-applicant-field">
                        <span class="bpl-applicant-label"><i class="bi bi-person"></i>01. Nama</span>
                        <div class="bpl-applicant-control is-readonly">
                            <input type="text" name="nama" class="input-field sts-profile-sourced" required readonly value="<?= e((string) ($payload['nama'] ?? '')) ?>" data-profile-sourced="1">
                            <span class="bpl-field-status"><i class="bi bi-check-circle-fill"></i></span>
                        </div>
                    </label>

                    <label class="bpl-applicant-field">
                        <span class="bpl-applicant-label"><i class="bi bi-building"></i>02. Bahagian</span>
                        <div class="bpl-applicant-control is-readonly is-department">
                            <select name="bahagian" class="input-field sts-department-select sts-profile-sourced" required data-profile-sourced="1" data-profile-locked="1">
                                <option value="">Pilih nama penuh bahagian SEDCO</option>
                                <?php foreach ($sedcoDepartments as $departmentName): ?>
                                <option value="<?= e($departmentName) ?>" <?= (string) ($payload['bahagian'] ?? '') === $departmentName ? 'selected' : '' ?>>
                                    <?= e($departmentName) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </label>

                    <label class="bpl-applicant-field">
                        <span class="bpl-applicant-label"><i class="bi bi-briefcase"></i>03. Jawatan</span>
                        <div class="bpl-applicant-control is-readonly">
                            <input type="text" name="jawatan" class="input-field sts-profile-sourced" required readonly value="<?= e((string) ($payload['jawatan'] ?? '')) ?>" data-profile-sourced="1">
                            <span class="bpl-field-status"><i class="bi bi-check-circle-fill"></i></span>
                        </div>
                    </label>

                    <label class="bpl-applicant-field">
                        <span class="bpl-applicant-label"><i class="bi bi-calendar-check"></i>Tarikh Penghantaran</span>
                        <div class="bpl-applicant-control is-readonly">
                            <input type="date" name="tarikh" required readonly value="<?= e((string) ($payload['tarikh'] ?? '')) ?>" data-submission-date="1">
                            <span class="bpl-field-status"><i class="bi bi-clock-history"></i></span>
                        </div>
                    </label>

                    <label class="bpl-applicant-field bpl-applicant-field-wide">
                        <span class="bpl-applicant-label"><i class="bi bi-mortarboard"></i>04. Kursus / Seminar</span>
                        <div class="bpl-applicant-control">
                            <input type="text" name="kursus" class="input-field" required placeholder="Masukkan nama kursus atau seminar">
                        </div>
                    </label>
                </div>

                <div class="bpl-applicant-footer-note">
                    <i class="bi bi-info-circle"></i>
                    <span>Nama, Bahagian dan Jawatan diambil daripada Profile. Tarikh akan disahkan semula semasa borang dihantar.</span>
                </div>
            </section>

            <div class="bpl-quality-panel no-print" data-bpl-quality-panel hidden>
                <div class="bpl-quality-panel-head">
                    <span class="bpl-quality-icon"><i class="bi bi-shield-check"></i></span>
                    <div>
                        <strong data-bpl-quality-title>Data quality check</strong>
                        <p data-bpl-quality-copy>Checking training dates and possible schedule conflicts.</p>
                    </div>
                </div>
                <div class="bpl-quality-messages" data-bpl-quality-messages></div>
                <div class="bpl-conflict-list" data-bpl-conflict-list></div>
            </div>

            <!-- B. MAKLUMAT KURSUS/SEMINAR -->
            <table class="bpl-section-card bpl-section-course" data-form-owner="staff">
                <tr><th colspan="4"><div class="bpl-table-heading"><span class="bpl-table-heading-icon"><i class="bi bi-journal-text"></i></span><div class="bpl-table-heading-copy"><strong>B. MAKLUMAT KURSUS / SEMINAR</strong><small>Butiran kursus, penganjur, tarikh, lokasi dan yuran</small></div></div></th></tr>
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
            <table class="bpl-section-card bpl-section-travel" data-form-owner="staff">
                <tr><th colspan="4"><div class="bpl-table-heading"><span class="bpl-table-heading-icon"><i class="bi bi-geo-alt"></i></span><div class="bpl-table-heading-copy"><strong>C. MAKLUMAT TUGAS LUAR DAERAH</strong><small>Maklumat perjalanan dan urusan tugas luar daerah</small></div></div></th></tr>
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

            <!-- ULASAN D - H -->
            <table class="bpl-section-card bpl-review-card bpl-section-training-review form-section-locked" data-form-owner="training_section" inert aria-disabled="true">
                <tr><th colspan="2"><div class="bpl-table-heading"><span class="bpl-table-heading-icon"><i class="bi bi-chat-square-text"></i></span><div class="bpl-table-heading-copy"><strong>D. ULASAN PENGURUS SEKSYEN LATIHAN</strong><small>Semakan dan ulasan oleh Seksyen Latihan</small></div></div></th></tr>
                <tr>
                    <td>Ulasan</td>
                    <td><textarea name="ulasan_latihan" class="input-field" rows="4" required></textarea></td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td>
                        <div class="bpl-auto-date-field">
                            <input type="date" name="tarikh_latihan" required readonly data-auto-review-date value="<?= e((string) ($payload['tarikh_latihan'] ?? '')) ?>">
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td>
                        <div class="sts-digital-signature" data-digital-signature data-signature-required="1">
                            <div class="sts-signature-pad" data-signature-pad tabindex="0" aria-label="Tandatangan">
                                <canvas width="900" height="220" data-signature-canvas></canvas>
                                <div class="sts-signature-placeholder" data-signature-placeholder>Tandatangan di sini</div>
                            </div>
                            <input type="hidden" name="tt_latihan" data-signature-value>
                            <div class="sts-signature-actions no-print">
                                <span><i class="bi bi-shield-check"></i> Digital signature</span>
                                <button type="button" data-signature-clear><i class="bi bi-eraser"></i> Clear</button>
                            </div>
                            <div class="sts-signature-error" data-signature-error hidden>Sila tandatangan sebelum hantar.</div>
                        </div>
                    </td>
                </tr>
            </table>

            <table class="bpl-section-card bpl-review-card bpl-section-hod-review form-section-locked" data-form-owner="head_of_department" inert aria-disabled="true">
                <tr><th colspan="2"><div class="bpl-table-heading"><span class="bpl-table-heading-icon"><i class="bi bi-person-check"></i></span><div class="bpl-table-heading-copy"><strong>E. ULASAN KETUA / PENGURUS BAHAGIAN</strong><small>Semakan dan ulasan Ketua / Pengurus Bahagian</small></div></div></th></tr>
                <tr>
                    <td>Ulasan</td>
                    <td><textarea name="ulasan_bahagian" class="input-field" rows="4" required></textarea></td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td>
                        <div class="bpl-auto-date-field">
                            <input type="date" name="tarikh_bahagian" required readonly data-auto-review-date value="<?= e((string) ($payload['tarikh_bahagian'] ?? '')) ?>">
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td>
                        <div class="sts-digital-signature" data-digital-signature data-signature-required="1">
                            <div class="sts-signature-pad" data-signature-pad tabindex="0" aria-label="Tandatangan">
                                <canvas width="900" height="220" data-signature-canvas></canvas>
                                <div class="sts-signature-placeholder" data-signature-placeholder>Tandatangan di sini</div>
                            </div>
                            <input type="hidden" name="tt_bahagian" data-signature-value>
                            <div class="sts-signature-actions no-print">
                                <span><i class="bi bi-shield-check"></i> Digital signature</span>
                                <button type="button" data-signature-clear><i class="bi bi-eraser"></i> Clear</button>
                            </div>
                            <div class="sts-signature-error" data-signature-error hidden>Sila tandatangan sebelum hantar.</div>
                        </div>
                    </td>
                </tr>
            </table>

            <table class="bpl-section-card bpl-review-card bpl-section-gm-review form-section-locked" data-form-owner="general_manager" inert aria-disabled="true">
                <tr><th colspan="2"><div class="bpl-table-heading"><span class="bpl-table-heading-icon"><i class="bi bi-patch-check"></i></span><div class="bpl-table-heading-copy"><strong>F. ULASAN PENGURUS BESAR KUMPULAN SEDCO</strong><small>Keputusan Pengurus Besar Kumpulan SEDCO</small></div></div></th></tr>
                <tr>
                    <td>Kelulusan</td>
                    <td>
                        <label><input type="radio" name="kelulusan_pgs" value="Diluluskan" required> Diluluskan</label>
                        <label><input type="radio" name="kelulusan_pgs" value="Tidak Diluluskan"> Tidak Diluluskan</label>
                    </td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td>
                        <div class="bpl-auto-date-field">
                            <input type="date" name="tarikh_pgs" required readonly data-auto-review-date value="<?= e((string) ($payload['tarikh_pgs'] ?? '')) ?>">
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td>
                        <div class="sts-digital-signature" data-digital-signature data-signature-required="1">
                            <div class="sts-signature-pad" data-signature-pad tabindex="0" aria-label="Tandatangan">
                                <canvas width="900" height="220" data-signature-canvas></canvas>
                                <div class="sts-signature-placeholder" data-signature-placeholder>Tandatangan di sini</div>
                            </div>
                            <input type="hidden" name="tt_pgs" data-signature-value>
                            <div class="sts-signature-actions no-print">
                                <span><i class="bi bi-shield-check"></i> Digital signature</span>
                                <button type="button" data-signature-clear><i class="bi bi-eraser"></i> Clear</button>
                            </div>
                            <div class="sts-signature-error" data-signature-error hidden>Sila tandatangan sebelum hantar.</div>
                        </div>
                    </td>
                </tr>
            </table>

            <table class="bpl-section-card bpl-review-card bpl-section-admin-review form-section-locked" data-form-owner="pengerusi_besar" inert aria-disabled="true">
                <tr><th colspan="2"><div class="bpl-table-heading"><span class="bpl-table-heading-icon"><i class="bi bi-shield-check"></i></span><div class="bpl-table-heading-copy"><strong>G. ULASAN PENGERUSI SEDCO</strong><small>Keputusan dan pengesahan oleh Pengerusi SEDCO</small></div></div></th></tr>
                <tr>
                    <td>Kelulusan</td>
                    <td>
                        <label><input type="radio" name="kelulusan_sedco" value="Diluluskan"> Diluluskan</label>
                        <label><input type="radio" name="kelulusan_sedco" value="Tidak Diluluskan"> Tidak Diluluskan</label>
                    </td>
                </tr>
                <tr>
                    <td>Tarikh</td>
                    <td>
                        <div class="bpl-auto-date-field">
                            <input type="date" name="tarikh_sedco" readonly data-auto-review-date value="<?= e((string) ($payload['tarikh_sedco'] ?? '')) ?>">
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Tandatangan</td>
                    <td>
                        <div class="sts-digital-signature" data-digital-signature data-signature-required="1">
                            <div class="sts-signature-pad" data-signature-pad tabindex="0" aria-label="Tandatangan">
                                <canvas width="900" height="220" data-signature-canvas></canvas>
                                <div class="sts-signature-placeholder" data-signature-placeholder>Tandatangan di sini</div>
                            </div>
                            <input type="hidden" name="tt_sedco" data-signature-value>
                            <div class="sts-signature-actions no-print">
                                <span><i class="bi bi-shield-check"></i> Digital signature</span>
                                <button type="button" data-signature-clear><i class="bi bi-eraser"></i> Clear</button>
                            </div>
                            <div class="sts-signature-error" data-signature-error hidden>Sila tandatangan sebelum hantar.</div>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- FINAL CHECKLIST -->
            <table class="bpl-section-card bpl-review-card bpl-section-finance form-section-locked" data-form-owner="finance" inert aria-disabled="true">
                <tr><th colspan="2"><div class="bpl-table-heading"><span class="bpl-table-heading-icon"><i class="bi bi-cash-stack"></i></span><div class="bpl-table-heading-copy"><strong>H. ULASAN KEWANGAN</strong><small>Rekod bayaran, pendahuluan dan pendaftaran</small></div></div></th></tr>
                <tr>
                    <td>a) Bayaran Kursus</td>
                    <td><div class="bpl-money-field"><span class="bpl-money-prefix">RM</span><input type="text" name="bayaran_kursus" required inputmode="decimal" aria-label="Bayaran kursus dalam Ringgit Malaysia"></div></td>
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
                        <div class="bpl-registration-review">
                            <div class="bpl-registration-choice">
                                <label><input type="radio" name="telah_didaftar" value="Ya" required> Ya</label>
                                <label><input type="radio" name="telah_didaftar" value="Tidak"> Tidak</label>
                            </div>
                            <label class="bpl-registration-date">
                                <span>Tarikh Didaftarkan</span>
                                <input type="date" name="tarikh_didaftar" readonly data-registration-date value="<?= e((string) ($payload['tarikh_didaftar'] ?? '')) ?>">
                            </label>
                        </div>
                    </td>
                </tr>
            </table>

            <?php if ($canReviewCurrentStage && in_array($currentStage, ['training', 'hod'], true)): ?>
            <section class="sts-correction-target-panel no-print" data-correction-target-panel hidden>
              <div class="sts-correction-target-heading">
                <span><i class="bi bi-bullseye"></i></span>
                <div>
                  <strong>Correction field targeting</strong>
                  <p>If you return this BPL for correction, select exactly which applicant fields need changes.</p>
                </div>
              </div>
              <div class="sts-correction-target-grid">
                <?php foreach ([
                  'kursus' => 'Kursus / Seminar',
                  'tajuk' => 'Tajuk Kursus',
                  'penganjur' => 'Penganjur',
                  'tarikh_mula' => 'Tarikh Mula',
                  'tarikh_tamat' => 'Tarikh Tamat',
                  'tempat' => 'Tempat Kursus',
                  'yuran' => 'Yuran',
                  'kandungan' => 'Kandungan Kursus',
                  'tempat_tugas' => 'Tempat Bertugas',
                  'kenderaan' => 'Kenderaan',
                  'masa_bertolak' => 'Masa Bertolak',
                  'masa_kembali' => 'Masa Kembali',
                  'pendahuluan' => 'Pendahuluan',
                ] as $fieldName => $fieldLabel): ?>
                <label>
                  <input type="checkbox" name="correction_fields[]" value="<?= e($fieldName) ?>">
                  <span><?= e($fieldLabel) ?></span>
                </label>
                <?php endforeach; ?>
              </div>
              <div class="sts-correction-target-error" data-correction-target-error hidden>
                Select at least one field before requesting correction.
              </div>
            </section>
            <?php endif; ?>

            <div class="form-actions">
                <?php if ($mode === 'new'): ?>
                <input type="submit" name="submit" value="Submit Application" class="btn-maroon">
                <?php elseif ($canResubmitCorrection): ?>
                <button type="submit" class="btn-maroon">
                  <i class="bi bi-arrow-repeat"></i> Resubmit Application
                </button>
                <?php elseif ($canReviewCurrentStage): ?>
                <button type="submit" name="decision" value="approved" class="btn-maroon btn-review-approve">
                  <i class="bi bi-check2"></i> <?= $currentStage === 'finance' ? 'Approve & Complete' : 'Approve & Continue' ?>
                </button>
                <?php if (in_array($currentStage, ['training', 'hod'], true)): ?>
                <button type="submit" name="decision" value="correction" class="btn-review-secondary" formnovalidate>
                  <i class="bi bi-arrow-counterclockwise"></i> Request Correction
                </button>
                <?php endif; ?>
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
  status: <?= json_encode($applicationStatus) ?>,
  isOwner: <?= json_encode($mode === 'new' ? true : $isOwner) ?>
};
</script>
<script src="bpl-workflow.js?v=20261007-07"></script>
<script src="form-permissions.js?v=20261003-06"></script>
<script src="digital-signature.js?v=20261006-03"></script>
<script src="form-ux.js?v=20261006-06"></script>
<script src="form-enhancements.js?v=20260930-59"></script>
<script src="sedco-shell.js?v=20261007-06"></script>
</body>
</html>
