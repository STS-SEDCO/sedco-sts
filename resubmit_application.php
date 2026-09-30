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
        'SELECT id, user_id, form_type, payload, status, current_stage
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
        'kenderaan', 'masa_bertolak', 'masa_kembali', 'pendahuluan'
    ];

    $payload = json_decode((string) $application['payload'], true);
    $payload = is_array($payload) ? $payload : [];

    $submittedFields = array_intersect_key($_POST, array_flip($allowedKeys));

    foreach ($submittedFields as $key => $value) {
        if (is_array($value)) {
            $payload[$key] = array_values(array_map('strval', $value));
        } else {
            $payload[$key] = trim((string) $value);
        }
    }

    $payloadJson = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    $applicationId = (int) $application['id'];

    $update = $db->prepare(
        'UPDATE applications
         SET payload = ?, status = "pending", review_note = NULL
         WHERE id = ?'
    );
    $update->bind_param('si', $payloadJson, $applicationId);
    $update->execute();
    $update->close();

    $db->commit();

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
