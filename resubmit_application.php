<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: application-status.php');
    exit;
}

verify_csrf();

$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$applicationNo = trim((string) ($_POST['application_no'] ?? ''));

if ($applicationNo === '') {
    http_response_code(400);
    exit('Invalid application.');
}

$db = db();
$db->begin_transaction();

try {
    $stmt = $db->prepare(
        'SELECT id, application_no, user_id, form_type, department, assigned_hod_id,
                payload, status, current_stage
         FROM applications
         WHERE application_no = ?
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->bind_param('s', $applicationNo);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$application || $application['form_type'] !== 'BPL') {
        throw new RuntimeException('Application not found.');
    }

    if ((int) $application['user_id'] !== (int) $user['id']) {
        http_response_code(403);
        throw new RuntimeException('You can only resubmit your own application.');
    }

    if ($application['status'] !== 'correction') {
        throw new RuntimeException('This application is not awaiting correction.');
    }

    $allowedKeys = [
        'nama', 'bahagian', 'jawatan', 'kursus', 'tarikh',
        'tajuk', 'penganjur', 'tarikh_mula', 'tarikh_tamat',
        'tempat', 'yuran', 'kandungan', 'tempat_tugas',
        'kenderaan', 'kenderaan_other', 'masa_bertolak', 'masa_kembali', 'pendahuluan'
    ];

    $payload = json_decode((string) $application['payload'], true);
    $payload = is_array($payload) ? $payload : [];

    sts_save_version(
        (int) $application['id'],
        (int) $user['id'],
        'before_correction',
        $payload
    );

    $submittedFields = array_intersect_key($_POST, array_flip($allowedKeys));

    foreach ($submittedFields as $key => $value) {
        if (is_array($value)) {
            $payload[$key] = array_values(array_map('strval', $value));
        } else {
            $payload[$key] = trim((string) $value);
        }
    }

    foreach ([
        'nama', 'bahagian', 'jawatan', 'kursus', 'tarikh',
        'tajuk', 'penganjur', 'tarikh_mula', 'tarikh_tamat',
        'tempat', 'yuran', 'kandungan'
    ] as $requiredKey) {
        if (trim((string) ($payload[$requiredKey] ?? '')) === '') {
            throw new RuntimeException('Please complete all required fields before resubmitting.');
        }
    }

    $start = trim((string) ($payload['tarikh_mula'] ?? ''));
    $end = trim((string) ($payload['tarikh_tamat'] ?? ''));
    $feeRaw = trim((string) ($payload['yuran'] ?? ''));

    $validDate = static function (string $value): bool {
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) return false;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    };

    if (!$validDate($start) || !$validDate($end)) {
        throw new RuntimeException('Please enter valid training start and end dates.');
    }

    if (strtotime($end) < strtotime($start)) {
        throw new RuntimeException('Training end date cannot be earlier than the start date.');
    }

    $cleanFee = preg_replace('/[^0-9.]/', '', $feeRaw) ?? '';
    if ($cleanFee === '' || !is_numeric($cleanFee)) {
        throw new RuntimeException('Course fee must be a valid number.');
    }

    $fee = (float) $cleanFee;
    if ($fee < 0 || $fee > 1000000) {
        throw new RuntimeException('Course fee is outside the allowed range.');
    }

    $vehicles = $payload['kenderaan'] ?? [];
    $vehicles = is_array($vehicles) ? array_map('strval', $vehicles) : [];

    if (
        in_array('Lain-lain', $vehicles, true)
        && trim((string) ($payload['kenderaan_other'] ?? '')) === ''
    ) {
        throw new RuntimeException('Please specify the other vehicle.');
    }

    unset(
        $payload['_correction_fields'],
        $payload['_correction_stage'],
        $payload['_correction_requested_at']
    );

    $payloadJson = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    $applicationId = (int) $application['id'];
    $slaDueAt = sts_review_sla_due();

    $update = $db->prepare(
        'UPDATE applications
         SET payload = ?, status = "pending", review_note = NULL,
             sla_due_at = ?, training_start = ?, training_end = ?
         WHERE id = ?'
    );

    $trainingStart = trim((string) ($payload['tarikh_mula'] ?? '')) ?: null;
    $trainingEnd = trim((string) ($payload['tarikh_tamat'] ?? '')) ?: null;

    $update->bind_param(
        'ssssi',
        $payloadJson,
        $slaDueAt,
        $trainingStart,
        $trainingEnd,
        $applicationId
    );
    $update->execute();
    $update->close();

    sts_save_version(
        $applicationId,
        (int) $user['id'],
        'correction_resubmitted',
        $payload
    );

    sts_store_attachments($applicationId, (int) $user['id']);

    $db->commit();

    sts_audit(
        'application_resubmitted',
        'application',
        $applicationNo,
        ['stage' => $application['current_stage']],
        (int) $user['id']
    );

    sts_notify_stage(
        (string) $application['current_stage'],
        $applicationNo,
        $application['department'] ?? null,
        !empty($application['assigned_hod_id'])
            ? (int) $application['assigned_hod_id']
            : null
    );

    header(
        'Location: application-status.php?resubmitted=1&application='
        . rawurlencode($applicationNo)
    );
    exit;
} catch (Throwable $error) {
    $db->rollback();

    if (http_response_code() < 400) {
        http_response_code(400);
    }

    exit(e($error->getMessage()));
}
