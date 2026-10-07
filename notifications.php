<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$userId = (int) $user['id'];
$notifications = [];
$notificationsUnavailable = false;

sts_ensure_followup_notifications($user);
sts_ensure_sla_escalations();

try {
    $stmt = db()->prepare(
        'SELECT id, type, title, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 100'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();
} catch (Throwable $error) {
    $notificationsUnavailable = true;
}

$unread = $notificationsUnavailable ? 0 : sts_unread_notifications($userId);

function notification_icon(string $type): string
{
    return match ($type) {
        'success' => 'bi-check2-circle',
        'warning' => 'bi-exclamation-triangle',
        'danger' => 'bi-x-octagon',
        'review' => 'bi-inbox',
        default => 'bi-bell',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Training System: Notifications</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="sedco-saas.css?v=20261006-12">
  <link rel="stylesheet" href="sedco-shell.css?v=20261007-16">
</head>
<body class="app-page notifications-page" data-page="notifications" data-role="<?= e(normalized_role($user['role'] ?? '')) ?>">
<main class="sts-page-content">
  <div class="sts-page-shell">
    <header class="sts-page-heading">
      <div>
        <div class="sts-eyebrow">Inbox</div>
        <h1>Notifications</h1>
        <p>Approval updates, correction requests and training follow up reminders in one place.</p>
      </div>
      <?php if ($unread > 0): ?>
      <form method="post" action="notification-action.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all">
        <button class="sts-secondary-btn" type="submit">
          <i class="bi bi-check2-all"></i> Mark all as read
        </button>
      </form>
      <?php endif; ?>
    </header>

    <section class="notification-summary">
      <div>
        <span>Unread</span>
        <strong><?= $unread ?></strong>
      </div>
      <div>
        <span>Total</span>
        <strong><?= count($notifications) ?></strong>
      </div>
    </section>

    <?php if ($notificationsUnavailable || isset($_GET['error'])): ?>
    <div class="notification-system-alert" role="status">
      <i class="bi bi-exclamation-triangle"></i>
      <div>
        <strong>Notifications could not be loaded.</strong>
        <span>Please refresh the page. Other STS functions can still be used normally.</span>
      </div>
    </div>
    <?php endif; ?>

    <section class="notification-panel">
      <?php if (!$notifications): ?>
      <div class="sts-empty-state">
        <i class="bi bi-bell"></i>
        <h3>No notifications yet</h3>
        <p>Workflow updates will appear here automatically.</p>
      </div>
      <?php else: ?>
      <div class="notification-list">
        <?php foreach ($notifications as $notification): ?>
        <article class="notification-item<?= (int) $notification['is_read'] === 0 ? ' is-unread' : '' ?>">
          <span class="notification-icon notification-<?= e($notification['type']) ?>">
            <i class="bi <?= e(notification_icon((string) $notification['type'])) ?>"></i>
          </span>

          <div class="notification-copy">
            <div class="notification-title-row">
              <strong><?= e($notification['title']) ?></strong>
              <?php if ((int) $notification['is_read'] === 0): ?>
              <span class="notification-new">New</span>
              <?php endif; ?>
            </div>
            <p><?= e($notification['message']) ?></p>
            <time><?= e(date('d M Y, g:i A', strtotime((string) $notification['created_at']))) ?></time>
          </div>

          <div class="notification-actions">
            <?php if (!empty($notification['link'])): ?>
            <form method="post" action="notification-action.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="mark_one">
              <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
              <input type="hidden" name="redirect" value="<?= e($notification['link']) ?>">
              <button type="submit" class="notification-open-btn">
                Open <i class="bi bi-arrow-up-right"></i>
              </button>
            </form>
            <?php elseif ((int) $notification['is_read'] === 0): ?>
            <form method="post" action="notification-action.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="mark_one">
              <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
              <button type="submit" class="notification-open-btn">Mark read</button>
            </form>
            <?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  </div>
</main>
<script src="sedco-shell.js?v=20261007-06"></script>
</body>
</html>
