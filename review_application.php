<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: submissions.php');
    exit;
}

verify_csrf();

$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$applicationNo = trim((string) ($_POST['application_no'] ?? ''));
$decision = strtolower(trim((string) ($_POST['decision'] ?? '')));

if ($applicationNo === '' || !in_array($decision, ['approved', 'rejected', 'correction'], true)) {
    http_response_code(400);
    exit('Invalid review request.');
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

    $stage = (string) $application['current_stage'];

    if (!user_can_review_stage($stage, $user)) {
        http_response_code(403);
        throw new RuntimeException('This application is not at your review stage.');
    }

    if (in_array($application['status'], ['approved', 'rejected'], true) || $stage === 'completed') {
        throw new RuntimeException('This application has already been completed.');
    }

    $allowedByStage = [
        'hod' => ['ulasan_bahagian', 'tarikh_bahagian', 'tt_bahagian'],
        'training' => [
            'ulasan_latihan', 'tarikh_latihan', 'tt_latihan',
            'bayaran_kursus', 'pendahuluan_diterima', 'telah_didaftar'
        ],
        'gm' => ['kelulusan_pgs', 'tarikh_pgs', 'tt_pgs'],
    ];

    $payload = json_decode((string) $application['payload'], true);
    $payload = is_array($payload) ? $payload : [];

    $allowedKeys = $allowedByStage[$stage] ?? [];
    $submittedFields = array_intersect_key($_POST, array_flip($allowedKeys));

    foreach ($submittedFields as $key => $value) {
        if (is_array($value)) {
            $payload[$key] = array_values(array_map('strval', $value));
        } else {
            $payload[$key] = trim((string) $value);
        }
    }

    if ($stage === 'gm') {
        if ($decision === 'approved') {
            $payload['kelulusan_pgs'] = 'Diluluskan';
        } elseif ($decision === 'rejected') {
            $payload['kelulusan_pgs'] = 'Tidak Diluluskan';
        }
    }

    $note = match ($stage) {
        'hod' => trim((string) ($payload['ulasan_bahagian'] ?? '')),
        'training' => trim((string) ($payload['ulasan_latihan'] ?? '')),
        'gm' => trim((string) ($payload['kelulusan_pgs'] ?? '')),
        default => '',
    };

    $payloadJson = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    $reviewerId = (int) $user['id'];
    $applicationId = (int) $application['id'];

    $reviewStmt = $db->prepare(
        'INSERT INTO application_reviews
            (application_id, reviewer_id, review_stage, decision, note)
         VALUES (?, ?, ?, ?, ?)'
    );
    $reviewStmt->bind_param('iisss', $applicationId, $reviewerId, $stage, $decision, $note);
    $reviewStmt->execute();
    $reviewStmt->close();

    if ($decision === 'approved') {
        $nextStage = match ($stage) {
            'hod' => 'training',
            'training' => 'gm',
            'gm' => 'completed',
            default => 'completed',
        };

        $nextStatus = $nextStage === 'completed' ? 'approved' : 'pending';
    } elseif ($decision === 'rejected') {
        $nextStage = 'completed';
        $nextStatus = 'rejected';
    } else {
        $nextStage = $stage;
        $nextStatus = 'correction';
    }

    $update = $db->prepare(
        'UPDATE applications
         SET payload = ?, status = ?, current_stage = ?, review_note = ?
         WHERE id = ?'
    );
    $update->bind_param('ssssi', $payloadJson, $nextStatus, $nextStage, $note, $applicationId);
    $update->execute();
    $update->close();

    $db->commit();

    header(
        'Location: submissions.php?reviewed=1&decision='
        . rawurlencode($decision)
        . '&application='
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
