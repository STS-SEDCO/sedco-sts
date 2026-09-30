<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: notifications.php');
    exit;
}

verify_csrf();

$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$userId = (int) $user['id'];
$action = (string) ($_POST['action'] ?? '');

if ($action === 'mark_all') {
    $stmt = db()->prepare(
        'UPDATE notifications
         SET is_read = 1, read_at = NOW()
         WHERE user_id = ? AND is_read = 0'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    sts_audit('notifications_marked_read', 'notification', 'all', [], $userId);
} elseif ($action === 'mark_one') {
    $notificationId = max(0, (int) ($_POST['notification_id'] ?? 0));

    $stmt = db()->prepare(
        'UPDATE notifications
         SET is_read = 1, read_at = NOW()
         WHERE id = ? AND user_id = ?'
    );
    $stmt->bind_param('ii', $notificationId, $userId);
    $stmt->execute();
    $stmt->close();
}

$redirect = trim((string) ($_POST['redirect'] ?? ''));

if (
    $redirect !== ''
    && preg_match('/^[A-Za-z0-9._-]+\.php(?:\?[A-Za-z0-9._%=&-]+)?$/', $redirect)
) {
    header('Location: ' . $redirect);
    exit;
}

header('Location: notifications.php');
exit;
