<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$user = current_user();
$attachmentId = max(0, (int) ($_GET['id'] ?? 0));

if (!$user || $attachmentId <= 0) {
    http_response_code(404);
    exit('File not found.');
}

$stmt = db()->prepare(
    'SELECT att.id, att.application_id, att.original_name, att.stored_name,
            att.mime_type, app.user_id, app.department, app.assigned_hod_id,
            app.current_stage, app.status
     FROM application_attachments att
     INNER JOIN applications app ON app.id = att.application_id
     WHERE att.id = ?
     LIMIT 1'
);
$stmt->bind_param('i', $attachmentId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !sts_can_view_application($row, $user)) {
    http_response_code(403);
    exit('You do not have permission to access this file.');
}

$path = __DIR__ . '/uploads/applications/' . basename((string) $row['stored_name']);

if (!is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

header('Content-Type: ' . ((string) $row['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header(
    'Content-Disposition: attachment; filename="' .
    rawurlencode((string) $row['original_name']) .
    '"'
);
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
