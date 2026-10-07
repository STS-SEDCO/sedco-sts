<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if (!$user || normalized_role($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

$message = null;
$error = null;
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_settings') {
        $values = [
            'review_sla_days' => (string) max(1, min(30, (int) ($_POST['review_sla_days'] ?? 3))),
            'pkk_due_days' => '7',
            'tea_due_days' => (string) max(1, min(180, (int) ($_POST['tea_due_days'] ?? 30))),
            'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
            'mail_from' => trim((string) ($_POST['mail_from'] ?? 'noreply@sts.local')),
        ];

        foreach ($values as $key => $value) {
            $stmt = db()->prepare(
                'INSERT INTO system_settings (setting_key, setting_value, updated_by)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   setting_value = VALUES(setting_value),
                   updated_by = VALUES(updated_by)'
            );
            $stmt->bind_param('ssi', $key, $value, $userId);
            $stmt->execute();
            $stmt->close();
        }

        sts_audit('system_settings_updated', 'settings', 'workflow', $values, $userId);
        $message = 'System settings saved.';
    } elseif ($action === 'save_department') {
        $name = trim((string) ($_POST['department_name'] ?? ''));
        $hodId = max(0, (int) ($_POST['hod_user_id'] ?? 0));

        if ($name === '') {
            $error = 'Department name is required.';
        } else {
            $hodValue = $hodId > 0 ? $hodId : null;
            $stmt = db()->prepare(
                'INSERT INTO departments (name, hod_user_id, is_active)
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE hod_user_id = VALUES(hod_user_id), is_active = 1'
            );
            $stmt->bind_param('si', $name, $hodValue);
            $stmt->execute();
            $stmt->close();

            if ($hodId > 0) {
                $updateUser = db()->prepare(
                    'UPDATE users SET department = ?, role = "head_of_department" WHERE id = ?'
                );
                $updateUser->bind_param('si', $name, $hodId);
                $updateUser->execute();
                $updateUser->close();
            }

            sts_audit('department_saved', 'department', $name, ['hod_user_id' => $hodId], $userId);
            $message = 'Department routing saved.';
        }
    } elseif ($action === 'add_event') {
        $title = trim((string) ($_POST['event_title'] ?? ''));
        $eventDate = trim((string) ($_POST['event_date'] ?? ''));
        $eventType = trim((string) ($_POST['event_type'] ?? 'company'));
        $description = trim((string) ($_POST['event_description'] ?? ''));

        if ($title === '' || !$eventDate) {
            $error = 'Event title and date are required.';
        } else {
            $stmt = db()->prepare(
                'INSERT INTO calendar_events
                    (title, event_date, event_type, description, created_by)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ssssi', $title, $eventDate, $eventType, $description, $userId);
            $stmt->execute();
            $stmt->close();

            sts_audit('calendar_event_created', 'calendar_event', $title, ['date' => $eventDate], $userId);
            $message = 'Calendar event added.';
        }
    }
}

$settings = [
    'review_sla_days' => sts_setting('review_sla_days','3'),
    'pkk_due_days' => sts_setting('pkk_due_days','7'),
    'tea_due_days' => sts_setting('tea_due_days','30'),
    'email_notifications' => sts_setting('email_notifications','0'),
    'mail_from' => sts_setting('mail_from','noreply@sts.local'),
];

$hodResult = db()->query(
    'SELECT id, fullname, department
     FROM users
     WHERE is_active = 1
       AND role IN ("head_of_department","head_of_division","staff")
     ORDER BY fullname ASC'
);
$hodUsers = [];
while ($row = $hodResult->fetch_assoc()) $hodUsers[] = $row;

$departmentResult = db()->query(
    'SELECT d.id, d.name, d.is_active, d.hod_user_id, u.fullname AS hod_name
     FROM departments d
     LEFT JOIN users u ON u.id = d.hod_user_id
     ORDER BY d.name ASC'
);
$departments = [];
while ($row = $departmentResult->fetch_assoc()) $departments[] = $row;

$eventsResult = db()->query(
    'SELECT title, event_date, event_type, description
     FROM calendar_events
     WHERE event_date >= CURRENT_DATE
     ORDER BY event_date ASC
     LIMIT 12'
);
$events = [];
while ($row = $eventsResult->fetch_assoc()) $events[] = $row;

$emailCounts = db()->query(
    'SELECT
       SUM(status = "sent") AS sent,
       SUM(status = "failed") AS failed,
       SUM(status = "queued") AS queued
     FROM email_queue'
)->fetch_assoc() ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Training System: System Settings</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="sedco-saas.css?v=20261007-06">
<link rel="stylesheet" href="sedco-shell.css?v=20261007-02">
</head>
<body class="app-page admin-page" data-page="admin-settings" data-role="admin">
<main class="sts-page-content"><div class="sts-page-shell">
<header class="sts-page-heading">
<div><div class="sts-eyebrow">Administration</div><h1>System Settings</h1><p>Configure approval timing, department routing, notifications and calendar events.</p></div>
<div class="sts-heading-actions">
  <a class="sts-secondary-btn" href="admin-system-health.php"><i class="bi bi-heart-pulse"></i> System health</a>
  <a class="sts-secondary-btn" href="admin-users.php"><i class="bi bi-people"></i> User management</a>
</div>
</header>
<?php if ($message): ?><div class="sts-alert success"><i class="bi bi-check-circle"></i><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="sts-alert danger"><i class="bi bi-exclamation-circle"></i><?= e($error) ?></div><?php endif; ?>

<div class="settings-grid">
<section class="sts-card">
<div class="sts-card-heading"><div><span>Workflow</span><h2>Approval and follow up timing</h2></div><i class="bi bi-stopwatch"></i></div>
<form method="post" class="settings-form">
<?= csrf_field() ?><input type="hidden" name="action" value="save_settings">
<label><span>Reviewer SLA</span><div class="settings-number"><input type="number" name="review_sla_days" min="1" max="30" value="<?= e($settings['review_sla_days']) ?>"><em>days</em></div><small>Time allowed for each approval stage before it is marked overdue.</small></label>
<label><span>PKK follow up</span><div class="settings-number"><input type="number" value="7" readonly><em>days</em></div><small>Fixed policy: PKK must be completed within 1 week after training ends before a new BPL can be submitted.</small></label>
<label><span>TEA follow up</span><div class="settings-number"><input type="number" name="tea_due_days" min="1" max="180" value="<?= e($settings['tea_due_days']) ?>"><em>days</em></div><small>Target after training ends for HoD to complete TEA.</small></label>
<label><span>Email sender</span><input type="email" name="mail_from" value="<?= e($settings['mail_from']) ?>"><small>Requires PHP mail / SMTP configuration on the hosting server.</small></label>
<label class="settings-switch"><input type="checkbox" name="email_notifications" value="1" <?= $settings['email_notifications']==='1'?'checked':'' ?>><span><strong>Email notifications</strong><small>System notifications always remain enabled.</small></span></label>
<button class="sts-primary-btn" type="submit"><i class="bi bi-check2"></i> Save settings</button>
</form>
<div class="email-health">
<span><strong><?= (int) ($emailCounts['sent'] ?? 0) ?></strong>Sent</span>
<span><strong><?= (int) ($emailCounts['failed'] ?? 0) ?></strong>Failed</span>
<span><strong><?= (int) ($emailCounts['queued'] ?? 0) ?></strong>Queued</span>
</div>
</section>

<section class="sts-card">
<div class="sts-card-heading"><div><span>Routing</span><h2>Departments & HoD</h2></div><i class="bi bi-diagram-2"></i></div>
<form method="post" class="settings-form compact">
<?= csrf_field() ?><input type="hidden" name="action" value="save_department">
<label><span>Department / Division</span><input type="text" name="department_name" required placeholder="e.g. Human Resource"></label>
<label><span>Assigned HoD</span><select name="hod_user_id"><option value="0">No assigned HoD yet</option><?php foreach($hodUsers as $hod): ?><option value="<?= (int)$hod['id'] ?>"><?= e($hod['fullname']) ?><?= $hod['department']?' · '.e($hod['department']):'' ?></option><?php endforeach; ?></select></label>
<button class="sts-primary-btn" type="submit"><i class="bi bi-plus-lg"></i> Save department</button>
</form>
<div class="settings-list">
<?php foreach($departments as $dept): ?>
<div><span><i class="bi bi-building"></i></span><div><strong><?= e($dept['name']) ?></strong><small><?= e($dept['hod_name'] ?: 'HoD not assigned') ?></small></div></div>
<?php endforeach; ?>
<?php if(!$departments): ?><p class="sts-muted-copy">No department routing configured yet.</p><?php endif; ?>
</div>
</section>

<section class="sts-card">
<div class="sts-card-heading"><div><span>Calendar</span><h2>Company events</h2></div><i class="bi bi-calendar-event"></i></div>
<form method="post" class="settings-form compact">
<?= csrf_field() ?><input type="hidden" name="action" value="add_event">
<label><span>Event title</span><input type="text" name="event_title" required></label>
<label><span>Date</span><input type="date" name="event_date" required></label>
<label><span>Type</span><select name="event_type"><option value="company">Company event</option><option value="deadline">Deadline</option><option value="training">Training</option></select></label>
<label><span>Description</span><textarea name="event_description" rows="2"></textarea></label>
<button class="sts-primary-btn" type="submit"><i class="bi bi-calendar-plus"></i> Add event</button>
</form>
<div class="settings-list">
<?php foreach($events as $event): ?>
<div><span><i class="bi bi-calendar3"></i></span><div><strong><?= e($event['title']) ?></strong><small><?= e(date('d M Y',strtotime((string)$event['event_date']))) ?> · <?= e(ucfirst((string)$event['event_type'])) ?></small></div></div>
<?php endforeach; ?>
<?php if(!$events): ?><p class="sts-muted-copy">No upcoming manual events.</p><?php endif; ?>
</div>
</section>
</div>
</div></main>
<script src="sedco-shell.js?v=20261007-02"></script>
</body>
</html>
