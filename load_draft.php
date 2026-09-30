<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$type = strtoupper(trim((string) ($_GET['type'] ?? '')));

if (!$user || !in_array($type, ['BPL', 'PKK', 'TEA'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'data' => null]);
    exit;
}

if (!user_can_submit_form_type($type, $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'data' => null]);
    exit;
}

$stmt = db()->prepare(
    'SELECT parent_application_id, payload, updated_at
     FROM application_drafts
     WHERE user_id = ? AND form_type = ?
     LIMIT 1'
);
$userId = (int) $user['id'];
$stmt->bind_param('is', $userId, $type);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$data = $row ? json_decode((string) $row['payload'], true) : null;

echo json_encode([
    'ok' => true,
    'data' => is_array($data) ? $data : null,
    'parentApplicationId' => $row ? (int) ($row['parent_application_id'] ?? 0) : null,
    'updatedAt' => $row['updated_at'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
