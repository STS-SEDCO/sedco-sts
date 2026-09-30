<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: task.php');
    exit;
}

$type = strtoupper((string) ($_GET['type'] ?? ''));
$allowedTypes = ['BPL', 'PKK', 'TEA'];

if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    exit('Invalid application type.');
}

$payload = $_POST;
unset($payload['submit']);

$title = match ($type) {
    'BPL' => trim((string) ($payload['tajuk'] ?? $payload['kursus'] ?? 'Permohonan Latihan')),
    'PKK' => trim((string) ($payload['tajuk'] ?? 'Penilaian Keberkesanan Kursus')),
    'TEA' => 'Training Effectiveness Assessment',
};

if ($title === '') {
    $title = match ($type) {
        'BPL' => 'Permohonan Latihan',
        'PKK' => 'Penilaian Keberkesanan Kursus',
        'TEA' => 'Training Effectiveness Assessment',
    };
}

$applicationNo = sprintf(
    'APP-%s-%06d',
    date('Ymd'),
    random_int(0, 999999)
);

$payloadJson = json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);

$stmt = db()->prepare(
    'INSERT INTO applications
        (application_no, user_id, form_type, title, payload, status)
     VALUES (?, ?, ?, ?, ?, "pending")'
);

$userId = (int) $_SESSION['user_id'];
$stmt->bind_param(
    'sisss',
    $applicationNo,
    $userId,
    $type,
    $title,
    $payloadJson
);
$stmt->execute();
$stmt->close();

header('Location: application-status.php?submitted=1');
exit;
