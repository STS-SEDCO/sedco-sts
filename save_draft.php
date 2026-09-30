<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

verify_csrf();

$user = current_user();
$type = strtoupper(trim((string) ($_POST['form_type'] ?? '')));
$payloadJson = (string) ($_POST['payload'] ?? '{}');
$parentApplicationId = max(0, (int) ($_POST['parent_application_id'] ?? 0));

if (!$user || !in_array($type, ['BPL', 'PKK', 'TEA'], true) || !user_can_submit_form_type($type, $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$decoded = json_decode($payloadJson, true);

if (!is_array($decoded)) {
    http_response_code(422);
    echo json_encode(['ok' => false]);
    exit;
}

unset($decoded['_csrf'], $decoded['submit'], $decoded['decision']);

$cleanJson = json_encode(
    $decoded,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);

$userId = (int) $user['id'];
$parentValue = $parentApplicationId > 0 ? $parentApplicationId : null;

$stmt = db()->prepare(
    'INSERT INTO application_drafts
        (user_id, form_type, parent_application_id, payload)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       parent_application_id = VALUES(parent_application_id),
       payload = VALUES(payload),
       updated_at = CURRENT_TIMESTAMP'
);
$stmt->bind_param('isis', $userId, $type, $parentValue, $cleanJson);
$stmt->execute();
$stmt->close();

echo json_encode([
    'ok' => true,
    'savedAt' => date(DATE_ATOM),
]);
