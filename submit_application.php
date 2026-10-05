<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: task.php');
    exit;
}

verify_csrf();

$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$type = strtoupper((string) ($_GET['type'] ?? ''));
$allowedTypes = ['BPL', 'PKK', 'TEA'];

if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    exit('Invalid application type.');
}

if (!user_can_submit_form_type($type, $user)) {
    http_response_code(403);
    exit('You do not have permission to submit this form.');
}

$parentApplicationId = max(0, (int) ($_POST['parent_application_id'] ?? 0));

$payload = $_POST;
unset(
    $payload['submit'],
    $payload['_csrf'],
    $payload['parent_application_id']
);

// BPL Section A is sourced from the authenticated profile.
// The form date is always the actual submission date in Malaysia,
// never the date a draft was first started or autosaved.
if ($type === 'BPL') {
    $payload['nama'] = trim((string) ($user['fullname'] ?? ''));
    $payload['bahagian'] = trim((string) ($user['department'] ?? ''));
    $payload['jawatan'] = trim((string) ($user['job_title'] ?? ''));
    $payload['tarikh'] = (new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Kuala_Lumpur')
    ))->format('Y-m-d');

    if (
        $payload['nama'] === ''
        || $payload['bahagian'] === ''
        || $payload['jawatan'] === ''
    ) {
        http_response_code(422);
        exit('Please complete your Name, Department / Division, and Position in Profile before submitting the BPL form.');
    }

    if (!in_array($payload['bahagian'], sts_sedco_departments(), true)) {
        http_response_code(422);
        exit('Please select an official SEDCO Department / Division in Profile before submitting the BPL form.');
    }
}

$requiredByType = [
    'BPL' => [
        'nama', 'bahagian', 'jawatan', 'kursus', 'tarikh',
        'tajuk', 'penganjur', 'tarikh_mula', 'tarikh_tamat',
        'tempat', 'yuran', 'kandungan'
    ],
    'PKK' => [
        'nama', 'bahagian', 'jawatan', 'tajuk', 'tarikh', 'tempat',
        'objektif', 'perkara1', 'perkara2', 'perkara3', 'perkara4', 'perkara5',
        'cadangan1', 'cadangan2', 'cadangan3', 'p1',
        'aspect0_p1', 'aspect1_p1', 'aspect2_p1', 'aspect3_p1', 'aspect4_p1',
        'tandatangan', 'tarikh_penilaian'
    ],
    'TEA' => ['employee_name', 'division', 'month', 'head_division', 'date', 'signature'],
];

if ($type === 'BPL') {
    $vehicles = $payload['kenderaan'] ?? [];
    $vehicles = is_array($vehicles) ? array_map('strval', $vehicles) : [];

    if (
        in_array('Lain-lain', $vehicles, true)
        && trim((string) ($payload['kenderaan_other'] ?? '')) === ''
    ) {
        http_response_code(422);
        exit('Please specify the other vehicle.');
    }
}

if ($type === 'PKK') {
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
            exit('Please enter the speaker name before submitting scores.');
        }

        foreach ($scoreKeys as $scoreKey) {
            $score = (int) ($payload[$scoreKey] ?? 0);

            if ($score < 1 || $score > 10) {
                http_response_code(422);
                exit('Speaker scores must be between 1 and 10.');
            }
        }
    }
}

if ($type === 'TEA') {
    $teaRows = [];

    foreach ($payload as $key => $value) {
        if (preg_match('/^score_(\\d+)$/', (string) $key, $match)) {
            $teaRows[(int) $match[1]] = $value;
        }
    }

    if (!$teaRows) {
        http_response_code(422);
        exit('Please add at least one training assessment row.');
    }

    ksort($teaRows);

    foreach ($teaRows as $row => $scores) {
        $trainingTitle = trim((string) ($payload['training_title_' . $row] ?? ''));

        if ($trainingTitle === '') {
            http_response_code(422);
            exit('Please enter the training title for every assessment row.');
        }

        if (!is_array($scores) || count($scores) !== 5) {
            http_response_code(422);
            exit('Please complete the five official Training Effectiveness scores for every row.');
        }

        $total = 0;

        foreach ($scores as $score) {
            $numeric = (int) $score;

            if ($numeric < 1 || $numeric > 4) {
                http_response_code(422);
                exit('Training Effectiveness scores must be between 1 and 4.');
            }

            $total += $numeric;
        }

        $payload['total_score_' . $row] = (string) $total;
        $payload['competency_level_' . $row] = match (true) {
            $total <= 7 => 'Fail',
            $total <= 12 => 'Probation',
            $total <= 17 => 'Pass',
            default => 'Merit',
        };

        $extraScores = $payload['extra_score_' . $row] ?? [];

        if ($extraScores !== [] && !is_array($extraScores)) {
            http_response_code(422);
            exit('Invalid additional criterion scores.');
        }

        foreach ((array) $extraScores as $score) {
            if ($score === '') {
                continue;
            }

            $numeric = (int) $score;

            if ($numeric < 1 || $numeric > 4) {
                http_response_code(422);
                exit('Additional criterion scores must be between 1 and 4.');
            }
        }
    }

    $extraCriteria = $payload['extra_criteria'] ?? [];

    if ($extraCriteria !== [] && !is_array($extraCriteria)) {
        http_response_code(422);
        exit('Invalid additional criteria.');
    }

    $payload['extra_criteria'] = array_values(array_filter(
        array_map(static fn($value): string => trim((string) $value), (array) $extraCriteria),
        static fn(string $value): bool => $value !== ''
    ));
}

if ($type === 'BPL') {
    $allowedKeys = [
        'nama', 'bahagian', 'jawatan', 'kursus', 'tarikh',
        'tajuk', 'penganjur', 'tarikh_mula', 'tarikh_tamat',
        'tempat', 'yuran', 'kandungan', 'tempat_tugas',
        'kenderaan', 'kenderaan_other', 'masa_bertolak', 'masa_kembali', 'pendahuluan'
    ];

    $payload = array_intersect_key($payload, array_flip($allowedKeys));
} elseif ($type === 'PKK') {
    $allowedKeys = [
        'nama', 'bahagian', 'jawatan', 'tajuk', 'tarikh', 'tempat',
        'objektif', 'perkara1', 'perkara2', 'perkara3', 'perkara4', 'perkara5',
        'cadangan1', 'cadangan2', 'cadangan3',
        'p1', 'p2', 'p3', 'p4', 'p5',
        'aspect0_p1', 'aspect0_p2', 'aspect0_p3', 'aspect0_p4', 'aspect0_p5',
        'aspect1_p1', 'aspect1_p2', 'aspect1_p3', 'aspect1_p4', 'aspect1_p5',
        'aspect2_p1', 'aspect2_p2', 'aspect2_p3', 'aspect2_p4', 'aspect2_p5',
        'aspect3_p1', 'aspect3_p2', 'aspect3_p3', 'aspect3_p4', 'aspect3_p5',
        'aspect4_p1', 'aspect4_p2', 'aspect4_p3', 'aspect4_p4', 'aspect4_p5',
        'tandatangan', 'tarikh_penilaian'
    ];

    $payload = array_intersect_key($payload, array_flip($allowedKeys));
}

$title = match ($type) {
    'BPL' => trim((string) ($payload['tajuk'] ?? $payload['kursus'] ?? 'Permohonan Latihan')),
    'PKK' => trim((string) ($payload['tajuk'] ?? 'Penilaian Keberkesanan Kursus')),
    'TEA' => trim((string) ($payload['training_title_0'] ?? 'Training Effectiveness Assessment')),
};

if ($title === '') {
    $title = sts_form_name($type);
}

$parent = null;

if ($type === 'PKK' && $parentApplicationId <= 0) {
    http_response_code(422);
    exit('Please select a completed BPL training record before submitting PKK.');
}

if (in_array($type, ['PKK', 'TEA'], true) && $parentApplicationId > 0) {
    $parent = sts_validate_parent_bpl($parentApplicationId, $user, $type);

    if (!$parent) {
        http_response_code(403);
        exit('The selected training record is not available for this follow up form.');
    }
}

if ($type === 'PKK' && $parent) {
    $parentPayload = json_decode((string) ($parent['payload'] ?? ''), true);
    $parentPayload = is_array($parentPayload) ? $parentPayload : [];

    $payload['nama'] = trim((string) ($user['fullname'] ?? ''));
    $payload['bahagian'] = trim((string) ($user['department'] ?? ''));
    $payload['jawatan'] = trim((string) ($user['job_title'] ?? ''));
    $payload['tajuk'] = trim((string) (
        $parentPayload['tajuk']
        ?? $parent['title']
        ?? ''
    ));
    $payload['tarikh'] = trim((string) (
        $parentPayload['tarikh_tamat']
        ?? $parent['training_end']
        ?? ''
    ));
    $payload['tempat'] = trim((string) ($parentPayload['tempat'] ?? ''));

    if (
        $payload['nama'] === ''
        || $payload['bahagian'] === ''
        || $payload['jawatan'] === ''
    ) {
        http_response_code(422);
        exit('Please complete your Name, Department and Position in Profile before submitting PKK.');
    }

    if (
        $payload['tajuk'] === ''
        || $payload['tarikh'] === ''
        || $payload['tempat'] === ''
    ) {
        http_response_code(422);
        exit('The selected BPL training record is incomplete. Please contact the Training Section.');
    }
}

foreach ($requiredByType[$type] as $requiredKey) {
    $value = $payload[$requiredKey] ?? '';

    if (is_array($value) || trim((string) $value) === '') {
        http_response_code(422);
        exit('Please complete all required fields before submitting.');
    }
}

if ($type === 'PKK') {
    $title = trim((string) ($payload['tajuk'] ?? ''));

    if ($title === '') {
        $title = 'Penilaian Keberkesanan Kursus';
    }
}

$db = db();
$db->begin_transaction();

try {
    do {
        $applicationNo = sprintf(
            'APP-%s-%06d',
            date('Ymd'),
            random_int(0, 999999)
        );

        $check = $db->prepare(
            'SELECT id FROM applications WHERE application_no = ? LIMIT 1'
        );
        $check->bind_param('s', $applicationNo);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();
    } while ($exists);

    $payloadJson = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    $userId = (int) $user['id'];

    if ($type === 'BPL') {
        $department = trim((string) ($payload['bahagian'] ?? ''));
    } else {
        $department = trim((string) ($user['department'] ?? ''));

        if ($department === '') {
            $department = trim((string) (
                $payload['bahagian']
                ?? $payload['division']
                ?? ''
            ));
        }
    }
    $departmentValue = $department !== '' ? $department : null;
    $assignedHodId = null;
    $status = 'approved';
    $currentStage = 'completed';
    $slaDueAt = null;
    $trainingStart = null;
    $trainingEnd = null;
    $followupDueAt = null;
    $completedAt = date('Y-m-d H:i:s');

    if ($type === 'BPL') {
        $assignedHodId = sts_department_hod($departmentValue);
        $status = 'pending';
        $currentStage = 'training';
        $slaDueAt = sts_review_sla_due();
        $trainingStart = trim((string) ($payload['tarikh_mula'] ?? '')) ?: null;
        $trainingEnd = trim((string) ($payload['tarikh_tamat'] ?? '')) ?: null;
        $followupDueAt = sts_followup_due($trainingEnd, 'PKK');
        $completedAt = null;
    } elseif ($parent) {
        $departmentValue = trim((string) ($parent['department'] ?? '')) ?: $departmentValue;
        $assignedHodId = !empty($parent['assigned_hod_id'])
            ? (int) $parent['assigned_hod_id']
            : null;
    }

    $parentIdValue = $parent ? (int) $parent['id'] : null;

    $stmt = $db->prepare(
        'INSERT INTO applications
            (
              application_no, user_id, parent_application_id, form_type, title,
              department, assigned_hod_id, payload, status, current_stage,
              sla_due_at, training_start, training_end, followup_due_at, completed_at
            )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'siisssissssssss',
        $applicationNo,
        $userId,
        $parentIdValue,
        $type,
        $title,
        $departmentValue,
        $assignedHodId,
        $payloadJson,
        $status,
        $currentStage,
        $slaDueAt,
        $trainingStart,
        $trainingEnd,
        $followupDueAt,
        $completedAt
    );
    $stmt->execute();
    $applicationId = (int) $stmt->insert_id;
    $stmt->close();

    sts_save_version($applicationId, $userId, 'submitted', $payload);
    $attachments = sts_store_attachments($applicationId, $userId);

    $draftDelete = $db->prepare(
        'DELETE FROM application_drafts
         WHERE user_id = ? AND form_type = ?'
    );
    $draftDelete->bind_param('is', $userId, $type);
    $draftDelete->execute();
    $draftDelete->close();

    $db->commit();

    sts_audit(
        'application_submitted',
        'application',
        $applicationNo,
        [
            'form_type' => $type,
            'parent_application_id' => $parentIdValue,
            'attachments' => count($attachments),
        ],
        $userId
    );

    sts_notify(
        $userId,
        $type . ' submitted',
        $applicationNo . ' has been submitted successfully.',
        'application-detail.php?application=' . rawurlencode($applicationNo),
        'success'
    );

    if ($type === 'BPL') {
        sts_notify_stage(
            'training',
            $applicationNo,
            $departmentValue,
            $assignedHodId
        );
    } elseif ($parent) {
        $parentOwnerId = (int) $parent['user_id'];

        if ($parentOwnerId !== $userId) {
            sts_notify(
                $parentOwnerId,
                $type . ' follow up completed',
                $applicationNo . ' has been linked to ' . $parent['application_no'] . '.',
                'application-detail.php?application=' . rawurlencode($parent['application_no']),
                'success'
            );
        }
    }

    header(
        'Location: application-status.php?submitted=1&application='
        . rawurlencode($applicationNo)
    );
    exit;
} catch (Throwable $error) {
    $db->rollback();
    http_response_code(500);
    exit('Unable to submit the form. ' . e($error->getMessage()));
}
