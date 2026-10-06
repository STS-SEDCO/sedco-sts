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

    if ((string) $application['status'] === 'pending') {
        $application['current_stage'] = sts_sync_bpl_pending_stage($application);
    }

    if (!sts_can_review_application($application, $user)) {
        http_response_code(403);
        throw new RuntimeException('This application is not assigned to your current review stage.');
    }

    $stage = (string) $application['current_stage'];

    if ($decision === 'correction' && !in_array($stage, ['training', 'hod'], true)) {
        http_response_code(403);
        throw new RuntimeException('Correction requests are available only at the Training Department or Head of Department stage.');
    }
    $allowedByStage = [
        'training' => ['ulasan_latihan', 'tarikh_latihan', 'tt_latihan'],
        'hod' => ['ulasan_bahagian', 'tarikh_bahagian', 'tt_bahagian'],
        'gm' => ['kelulusan_pgs', 'tarikh_pgs', 'tt_pgs'],
        'chairman' => ['kelulusan_sedco', 'tarikh_sedco', 'tt_sedco'],
        'finance' => ['bayaran_kursus', 'pendahuluan_diterima', 'telah_didaftar'],
    ];

    $payload = json_decode((string) $application['payload'], true);
    $payload = is_array($payload) ? $payload : [];

    sts_save_version(
        (int) $application['id'],
        (int) $user['id'],
        'before_' . $stage . '_review',
        $payload
    );

    $allowedKeys = $allowedByStage[$stage] ?? [];
    $submittedFields = array_intersect_key($_POST, array_flip($allowedKeys));

    $requiredReviewFields = match ($stage) {
        'training' => $decision === 'correction'
            ? ['ulasan_latihan']
            : ['ulasan_latihan', 'tarikh_latihan', 'tt_latihan'],
        'hod' => $decision === 'correction'
            ? ['ulasan_bahagian']
            : ['ulasan_bahagian', 'tarikh_bahagian', 'tt_bahagian'],
        'gm' => ['tarikh_pgs', 'tt_pgs'],
        'chairman' => ['tarikh_sedco', 'tt_sedco'],
        'finance' => ['bayaran_kursus', 'pendahuluan_diterima', 'telah_didaftar'],
        default => [],
    };

    foreach ($requiredReviewFields as $requiredField) {
        $value = $_POST[$requiredField] ?? '';

        if (is_array($value) || trim((string) $value) === '') {
            http_response_code(422);
            throw new RuntimeException('Please complete all fields in your review section.');
        }
    }

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

    if ($stage === 'chairman') {
        if ($decision === 'approved') {
            $payload['kelulusan_sedco'] = 'Diluluskan';
        } elseif ($decision === 'rejected') {
            $payload['kelulusan_sedco'] = 'Tidak Diluluskan';
        }
    }

    $note = trim((string) ($_POST['review_comment'] ?? ''));

    if ($decision === 'rejected' && $note === '') {
        http_response_code(422);
        throw new RuntimeException('Please provide a clear reason before rejecting the application.');
    }

    if ($note === '') {
        $note = match ($stage) {
            'training' => trim((string) ($payload['ulasan_latihan'] ?? '')),
            'hod' => trim((string) ($payload['ulasan_bahagian'] ?? '')),
            'gm' => trim((string) ($payload['kelulusan_pgs'] ?? '')),
            'chairman' => trim((string) ($payload['kelulusan_sedco'] ?? '')),
            'finance' => 'Financial processing completed',
            default => '',
        };
    }

    if ($decision === 'correction') {
        $correctionNote = match ($stage) {
            'training' => trim((string) ($payload['ulasan_latihan'] ?? '')),
            'hod' => trim((string) ($payload['ulasan_bahagian'] ?? '')),
            default => '',
        };

        if ($correctionNote === '') {
            http_response_code(422);
            throw new RuntimeException(
                $stage === 'training'
                    ? 'Please write the correction instructions in Section D before sending the form back to the applicant.'
                    : 'Please write the correction instructions in Section E before sending the form back to the applicant.'
            );
        }
    }

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
        $nextStage = sts_next_bpl_stage($application, $stage);
        $nextStatus = $nextStage === 'completed' ? 'approved' : 'pending';
    } elseif ($decision === 'rejected') {
        $nextStage = 'completed';
        $nextStatus = 'rejected';
    } else {
        $nextStage = $stage;
        $nextStatus = 'correction';
    }

    $slaDueAt = $nextStatus === 'pending' ? sts_review_sla_due() : null;
    $completedAt = in_array($nextStatus, ['approved', 'rejected'], true)
        ? date('Y-m-d H:i:s')
        : null;

    $update = $db->prepare(
        'UPDATE applications
         SET payload = ?, status = ?, current_stage = ?, review_note = ?,
             sla_due_at = ?, completed_at = ?
         WHERE id = ?'
    );
    $update->bind_param(
        'ssssssi',
        $payloadJson,
        $nextStatus,
        $nextStage,
        $note,
        $slaDueAt,
        $completedAt,
        $applicationId
    );
    $update->execute();
    $update->close();

    sts_save_version(
        $applicationId,
        $reviewerId,
        $stage . '_' . $decision,
        $payload
    );

    $db->commit();

    sts_audit(
        'application_' . $decision,
        'application',
        $applicationNo,
        ['stage' => $stage, 'next_stage' => $nextStage],
        $reviewerId
    );

    $applicantId = (int) $application['user_id'];
    $applicantLink = 'application-detail.php?application=' . rawurlencode($applicationNo);

    if ($decision === 'correction') {
        sts_notify(
            $applicantId,
            'Correction requested',
            $applicationNo . ' needs correction at ' . stage_label($stage) . ' stage.'
                . ($note !== '' ? ' Note: ' . $note : ''),
            $applicantLink,
            'warning'
        );
    } elseif ($decision === 'rejected') {
        sts_notify(
            $applicantId,
            'Application rejected',
            $applicationNo . ' was rejected at ' . stage_label($stage) . ' stage.'
                . ($note !== '' ? ' Note: ' . $note : ''),
            $applicantLink,
            'danger'
        );
    } elseif ($nextStage === 'completed') {
        sts_notify(
            $applicantId,
            'Application approved',
            $applicationNo . ' has completed the approval workflow.',
            $applicantLink,
            'success'
        );
    } else {
        sts_notify(
            $applicantId,
            'Application progressed',
            $applicationNo . ' was approved by ' . stage_label($stage)
                . ' and moved to ' . stage_label($nextStage) . '.',
            $applicantLink,
            'success'
        );

        sts_notify_stage(
            $nextStage,
            $applicationNo,
            $application['department'] ?? null,
            !empty($application['assigned_hod_id'])
                ? (int) $application['assigned_hod_id']
                : null
        );
    }

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
