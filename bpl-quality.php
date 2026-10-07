<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
header('Content-Type: application/json; charset=UTF-8');

$user = current_user();

if (!$user) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'errors'=>['Session expired.']]);
    exit;
}

$title = trim((string) ($_GET['title'] ?? ''));
$start = trim((string) ($_GET['start'] ?? ''));
$end = trim((string) ($_GET['end'] ?? ''));
$excludeNo = trim((string) ($_GET['exclude'] ?? ''));

$errors = [];
$warnings = [];
$conflicts = [];

$department = trim((string) ($user['department'] ?? ''));
if (
    normalized_role((string) ($user['role'] ?? '')) === 'staff'
    && $department !== ''
    && sts_department_hod($department) === null
) {
    $errors[] = 'No HOD is currently assigned to your department. Ask the STS administrator to configure the HOD before submitting BPL.';
}

$validDate = static function (string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};

if ($start !== '' && !$validDate($start)) {
    $errors[] = 'Training start date is invalid.';
}

if ($end !== '' && !$validDate($end)) {
    $errors[] = 'Training end date is invalid.';
}

if ($validDate($start) && $validDate($end) && strtotime($end) < strtotime($start)) {
    $errors[] = 'Training end date cannot be earlier than the start date.';
}

if ($title !== '' && $validDate($start) && $validDate($end)) {
    $sql =
        'SELECT application_no,title,training_start,training_end,status,payload
         FROM applications
         WHERE user_id = ?
           AND form_type = "BPL"
           AND status NOT IN ("rejected","cancelled")';

    $params = [(int) $user['id']];
    $types = 'i';

    if ($excludeNo !== '') {
        $sql .= ' AND application_no <> ?';
        $params[] = $excludeNo;
        $types .= 's';
    }

    $sql .= ' ORDER BY submitted_at DESC,id DESC LIMIT 50';

    $stmt = db()->prepare($sql);
    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[] = &$params[$index];
    }
    call_user_func_array([$stmt,'bind_param'],$bind);
    $stmt->execute();
    $result = $stmt->get_result();

    $normalize = static function (string $value): string {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u',' ',$value) ?? $value;
        return trim(preg_replace('/\s+/u',' ',$value) ?? $value);
    };

    $wanted = $normalize($title);

    while ($row = $result->fetch_assoc()) {
        $payload = json_decode((string) ($row['payload'] ?? ''),true);
        $payload = is_array($payload) ? $payload : [];
        $existingTitle = trim((string) (
            $payload['tajuk']
            ?? $payload['kursus']
            ?? $row['title']
            ?? ''
        ));

        $similarity = 0.0;
        $existingNormalized = $normalize($existingTitle);

        if ($wanted !== '' && $existingNormalized !== '') {
            similar_text($wanted,$existingNormalized,$similarity);
        }

        $existingStart = (string) ($row['training_start'] ?? '');
        $existingEnd = (string) ($row['training_end'] ?? '');
        $overlap = $existingStart !== ''
            && $existingEnd !== ''
            && $existingStart <= $end
            && $existingEnd >= $start;
        $similarTitle = $similarity >= 72;

        if (!$overlap && !$similarTitle) {
            continue;
        }

        $conflicts[] = [
            'application_no'=>(string)$row['application_no'],
            'title'=>$existingTitle !== '' ? $existingTitle : (string)$row['title'],
            'start'=>$existingStart,
            'end'=>$existingEnd,
            'status'=>(string)$row['status'],
            'similar_title'=>$similarTitle,
            'date_overlap'=>$overlap,
            'similarity'=>round($similarity,1),
        ];
    }
    $stmt->close();

    if ($conflicts) {
        $hasSimilar = false;
        foreach ($conflicts as $conflict) {
            if (!empty($conflict['similar_title'])) {
                $hasSimilar = true;
                break;
            }
        }

        $hasOverlap = false;
        foreach ($conflicts as $conflict) {
            if (!empty($conflict['date_overlap'])) {
                $hasOverlap = true;
                break;
            }
        }

        if ($hasSimilar && $hasOverlap) {
            $warnings[] = 'A similar course or duplicate BPL overlaps with an existing training record. Check it before submitting.';
        } elseif ($hasSimilar) {
            $warnings[] = 'A very similar course already exists in your BPL history. Confirm this is not a duplicate application.';
        } else {
            $warnings[] = 'Another training record overlaps with these dates. Confirm the schedule before submitting.';
        }
    }
}

echo json_encode([
    'ok'=>!$errors,
    'errors'=>$errors,
    'warnings'=>$warnings,
    'conflicts'=>$conflicts,
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
