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
    exit('Invalid PKK submission.');
}

$db = db();
$db->begin_transaction();

try {
    $stmt = $db->prepare(
        'SELECT id, application_no, user_id, parent_application_id, form_type,
                title, payload, status, current_stage, department
         FROM applications
         WHERE application_no = ?
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->bind_param('s', $applicationNo);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$application
        || (string) $application['form_type'] !== 'PKK'
        || !sts_can_edit_pkk($application, $user)
    ) {
        http_response_code(403);
        throw new RuntimeException('This PKK submission can no longer be edited.');
    }

    $parentId = (int) ($application['parent_application_id'] ?? 0);

    if ($parentId <= 0) {
        throw new RuntimeException('The linked BPL record could not be found.');
    }

    $parentStmt = $db->prepare(
        'SELECT id, application_no, user_id, title, payload, status, training_end
         FROM applications
         WHERE id = ?
           AND form_type = "BPL"
         LIMIT 1
         FOR UPDATE'
    );
    $parentStmt->bind_param('i', $parentId);
    $parentStmt->execute();
    $parent = $parentStmt->get_result()->fetch_assoc();
    $parentStmt->close();

    if (
        !$parent
        || (int) $parent['user_id'] !== (int) $user['id']
        || (string) $parent['status'] !== 'approved'
    ) {
        throw new RuntimeException('The linked BPL record is no longer available.');
    }

    $parentPayload = json_decode((string) ($parent['payload'] ?? ''), true);
    $parentPayload = is_array($parentPayload) ? $parentPayload : [];

    $payload = [
        'nama' => trim((string) ($user['fullname'] ?? '')),
        'bahagian' => trim((string) ($user['department'] ?? '')),
        'jawatan' => trim((string) ($user['job_title'] ?? '')),
        'tajuk' => trim((string) (
            $parentPayload['tajuk']
            ?? $parent['title']
            ?? ''
        )),
        'tarikh' => trim((string) (
            $parentPayload['tarikh_tamat']
            ?? $parent['training_end']
            ?? ''
        )),
        'tempat' => trim((string) ($parentPayload['tempat'] ?? '')),
    ];

    $editableKeys = [
        'objektif',
        'perkara1', 'perkara2', 'perkara3', 'perkara4', 'perkara5',
        'cadangan1', 'cadangan2', 'cadangan3',
        'p1', 'p2', 'p3', 'p4', 'p5',
        'aspect0_p1', 'aspect0_p2', 'aspect0_p3', 'aspect0_p4', 'aspect0_p5',
        'aspect1_p1', 'aspect1_p2', 'aspect1_p3', 'aspect1_p4', 'aspect1_p5',
        'aspect2_p1', 'aspect2_p2', 'aspect2_p3', 'aspect2_p4', 'aspect2_p5',
        'aspect3_p1', 'aspect3_p2', 'aspect3_p3', 'aspect3_p4', 'aspect3_p5',
        'aspect4_p1', 'aspect4_p2', 'aspect4_p3', 'aspect4_p4', 'aspect4_p5',
        'tandatangan', 'tarikh_penilaian',
    ];

    foreach ($editableKeys as $key) {
        $value = $_POST[$key] ?? '';
        $payload[$key] = is_array($value)
            ? array_values(array_map('strval', $value))
            : trim((string) $value);
    }

    $required = [
        'nama', 'bahagian', 'jawatan', 'tajuk', 'tarikh', 'tempat',
        'objektif',
        'perkara1', 'perkara2', 'perkara3', 'perkara4', 'perkara5',
        'cadangan1', 'cadangan2', 'cadangan3',
        'p1',
        'aspect0_p1', 'aspect1_p1', 'aspect2_p1', 'aspect3_p1', 'aspect4_p1',
        'tandatangan', 'tarikh_penilaian',
    ];

    foreach ($required as $key) {
        $value = $payload[$key] ?? '';

        if (is_array($value) || trim((string) $value) === '') {
            http_response_code(422);
            throw new RuntimeException('Please complete all required PKK fields before updating.');
        }
    }

    for ($speaker = 1; $speaker <= 5; $speaker++) {
        $speakerName = trim((string) ($payload['p' . $speaker] ?? ''));
        $scoreKeys = [];

        for ($aspect = 0; $aspect <= 4; $aspect++) {
            $scoreKeys[] = 'aspect' . $aspect . '_p' . $speaker;
        }

        $hasAnyScore = false;

        foreach ($scoreKeys as $scoreKey) {
            if (trim((string) ($payload[$scoreKey] ?? '')) !== '') {
                $hasAnyScore = true;
                break;
            }
        }

        $activeSpeaker = $speaker === 1 || $speakerName !== '' || $hasAnyScore;

        if (!$activeSpeaker) {
            continue;
        }

        if ($speakerName === '') {
            http_response_code(422);
            throw new RuntimeException('Please enter the speaker name before submitting scores.');
        }

        foreach ($scoreKeys as $scoreKey) {
            $score = (int) ($payload[$scoreKey] ?? 0);

            if ($score < 1 || $score > 10) {
                http_response_code(422);
                throw new RuntimeException('Speaker scores must be between 1 and 10.');
            }
        }
    }

    $oldPayload = json_decode((string) ($application['payload'] ?? ''), true);
    $oldPayload = is_array($oldPayload) ? $oldPayload : [];

    $applicationId = (int) $application['id'];
    $userId = (int) $user['id'];

    sts_save_version(
        $applicationId,
        $userId,
        'before_pkk_edit',
        $oldPayload
    );

    $payloadJson = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $title = trim((string) $payload['tajuk']);
    $department = trim((string) $payload['bahagian']);

    $update = $db->prepare(
        'UPDATE applications
         SET title = ?, payload = ?, department = ?, updated_at = NOW()
         WHERE id = ?'
    );
    $update->bind_param('sssi', $title, $payloadJson, $department, $applicationId);
    $update->execute();
    $update->close();

    sts_save_version(
        $applicationId,
        $userId,
        'pkk_updated',
        $payload
    );

    $db->commit();

    sts_audit(
        'pkk_updated',
        'application',
        $applicationNo,
        ['parent_application_id' => $parentId],
        $userId
    );

    sts_notify(
        $userId,
        'PKK updated',
        $applicationNo . ' has been updated successfully.',
        'application-detail.php?application=' . rawurlencode($applicationNo),
        'success'
    );

    header(
        'Location: application-detail.php?updated=1&application='
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
