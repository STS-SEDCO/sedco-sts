<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

$user = current_user();

echo json_encode([
    'count' => $user ? sts_unread_notifications((int) $user['id']) : 0,
]);
