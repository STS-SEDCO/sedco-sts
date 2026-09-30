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

$payload = $_POST;
unset($payload['submit'], $payload['_csrf']);

if ($type === 'BPL') {
    $allowedKeys = [
        'nama', 'bahagian', 'jawatan', 'kursus', 'tarikh',
        'tajuk', 'penganjur', 'tarikh_mula', 'tarikh_tamat',
        'tempat', 'yuran', 'kandungan', 'tempat_tugas',
        'kenderaan', 'masa_bertolak', 'masa_kembali', 'pendahuluan'
    ];

    $payload = array_intersect_key($payload, array_flip($allowedKeys));
}

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

$userId = (int) $user['id'];
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
