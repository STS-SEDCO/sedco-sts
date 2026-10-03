<?php
declare(strict_types=1);

/**
 * Shared Smart Training System workflow helpers.
 * This file is loaded after auth.php has defined db(), current_user(),
 * normalized_role(), role_label(), and stage_label().
 */


function sts_sedco_departments(): array
{
    return [
        'Bahagian Audit Dalam (IAD)',
        'Bahagian Pembangunan Perniagaan dan Pelaburan (BDI)',
        'Bahagian Kewangan (FND)',
        'Bahagian Pembangunan Usahawan (EDD)',
        'Bahagian Pengurusan Strategik (SMD)',
        'Bahagian Pengurusan Hartanah (PMD)',
        'Bahagian Sumber Manusia dan Pentadbiran (HRAD)',
    ];
}

function sts_form_name(string $type): string
{
    return match (strtoupper($type)) {
        'BPL' => 'Permohonan Latihan',
        'PKK' => 'Penilaian Keberkesanan Kursus',
        'TEA' => 'Training Effectiveness Assessment',
        default => strtoupper($type),
    };
}

function sts_setting(string $key, string $default = ''): string
{
    try {
        $stmt = db()->prepare(
            'SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (string) $row['setting_value'] : $default;
    } catch (Throwable) {
        return $default;
    }
}

function sts_review_sla_due(): string
{
    $days = max(1, (int) sts_setting('review_sla_days', '3'));
    return date('Y-m-d H:i:s', time() + ($days * 86400));
}

function sts_followup_due(?string $trainingEnd, string $type): ?string
{
    if (!$trainingEnd) {
        return null;
    }

    $timestamp = strtotime($trainingEnd . ' 23:59:59');

    if (!$timestamp) {
        return null;
    }

    $days = strtoupper($type) === 'PKK'
        ? max(1, (int) sts_setting('pkk_due_days', '7'))
        : max(1, (int) sts_setting('tea_due_days', '30'));

    return date('Y-m-d H:i:s', $timestamp + ($days * 86400));
}

function sts_department_hod(?string $department): ?int
{
    $department = trim((string) $department);

    if ($department === '') {
        return null;
    }

    try {
        $stmt = db()->prepare(
            'SELECT hod_user_id
             FROM departments
             WHERE name = ? AND is_active = 1
             LIMIT 1'
        );
        $stmt->bind_param('s', $department);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return !empty($row['hod_user_id']) ? (int) $row['hod_user_id'] : null;
    } catch (Throwable) {
        return null;
    }
}

function sts_audit(
    string $action,
    ?string $entityType = null,
    string|int|null $entityId = null,
    array $metadata = [],
    ?int $userId = null
): void {
    try {
        if ($userId === null && function_exists('current_user')) {
            $actor = current_user();
            $userId = $actor ? (int) $actor['id'] : null;
        }

        $entityIdString = $entityId === null ? null : (string) $entityId;
        $metadataJson = $metadata
            ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

        $stmt = db()->prepare(
            'INSERT INTO audit_logs
                (user_id, action, entity_type, entity_id, metadata, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'isssss',
            $userId,
            $action,
            $entityType,
            $entityIdString,
            $metadataJson,
            $ip
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable) {
        // Audit logging must never block the main workflow.
    }
}

function sts_queue_email(
    ?int $userId,
    string $recipient,
    string $subject,
    string $body
): void {
    if (sts_setting('email_notifications', '0') !== '1' || $recipient === '') {
        return;
    }

    try {
        $status = 'queued';
        $stmt = db()->prepare(
            'INSERT INTO email_queue
                (user_id, recipient_email, subject, body, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $userId, $recipient, $subject, $body, $status);
        $stmt->execute();
        $queueId = (int) $stmt->insert_id;
        $stmt->close();

        $from = sts_setting('mail_from', 'noreply@sts.local');
        $headers = [
            'From: Smart Training System <' . $from . '>',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $sent = @mail($recipient, $subject, $body, implode("\r\n", $headers));

        if ($sent) {
            $update = db()->prepare(
                'UPDATE email_queue
                 SET status = "sent", attempts = attempts + 1, sent_at = NOW()
                 WHERE id = ?'
            );
            $update->bind_param('i', $queueId);
        } else {
            $error = 'PHP mail() could not send the message. Configure SMTP/mail on the server.';
            $update = db()->prepare(
                'UPDATE email_queue
                 SET status = "failed", attempts = attempts + 1, last_error = ?
                 WHERE id = ?'
            );
            $update->bind_param('si', $error, $queueId);
        }

        $update->execute();
        $update->close();
    } catch (Throwable) {
        // In-app notifications remain the source of truth when email is unavailable.
    }
}

function sts_notify(
    int $userId,
    string $title,
    string $message,
    ?string $link = null,
    string $type = 'info'
): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO notifications
                (user_id, type, title, message, link)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $userId, $type, $title, $message, $link);
        $stmt->execute();
        $stmt->close();

        $emailStmt = db()->prepare(
            'SELECT email FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
        );
        $emailStmt->bind_param('i', $userId);
        $emailStmt->execute();
        $row = $emailStmt->get_result()->fetch_assoc();
        $emailStmt->close();

        if ($row) {
            sts_queue_email(
                $userId,
                (string) $row['email'],
                '[STS] ' . $title,
                $message . ($link ? "\n\nOpen STS: " . $link : '')
            );
        }
    } catch (Throwable) {
        // Notifications must not block submissions or approvals.
    }
}

function sts_notify_role(
    array $roles,
    string $title,
    string $message,
    ?string $link = null,
    string $type = 'info',
    ?string $department = null,
    ?int $onlyUserId = null
): void {
    try {
        if ($onlyUserId !== null) {
            sts_notify($onlyUserId, $title, $message, $link, $type);
            return;
        }

        $roles = array_values(array_unique(array_filter($roles)));

        if (!$roles) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql = 'SELECT id, department FROM users
                WHERE is_active = 1 AND role IN (' . $placeholders . ')';

        $stmt = db()->prepare($sql);
        $stmt->execute($roles);
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if ($department !== null && $department !== '') {
                $recipientDepartment = trim((string) ($row['department'] ?? ''));

                if ($recipientDepartment !== '' && strcasecmp($recipientDepartment, $department) !== 0) {
                    continue;
                }
            }

            sts_notify((int) $row['id'], $title, $message, $link, $type);
        }

        $stmt->close();
    } catch (Throwable) {
        // Keep workflow operational even if notification setup is incomplete.
    }
}

function sts_notify_stage(
    string $stage,
    string $applicationNo,
    ?string $department = null,
    ?int $assignedHodId = null
): void {
    $link = 'submissions.php';
    $title = 'Application ready for review';
    $message = $applicationNo . ' is waiting for your review.';

    if ($stage === 'hod') {
        sts_notify_role(
            ['head_of_department', 'head_of_division'],
            $title,
            $message,
            $link,
            'review',
            $department,
            $assignedHodId
        );
        return;
    }

    if ($stage === 'training') {
        sts_notify_role(
            ['training_section'],
            $title,
            $message,
            $link,
            'review'
        );
        return;
    }

    if ($stage === 'gm') {
        sts_notify_role(
            ['general_manager', 'pengerusi_besar'],
            $title,
            $message,
            $link,
            'review'
        );
    }
}

function sts_save_version(
    int $applicationId,
    ?int $actorUserId,
    string $eventType,
    array $payload
): void {
    try {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $stmt = db()->prepare(
            'INSERT INTO application_versions
                (application_id, actor_user_id, event_type, payload)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('iiss', $applicationId, $actorUserId, $eventType, $json);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable) {
        // Version history is supportive and must not stop a transaction.
    }
}


function sts_cancel_application_supported(): bool
{
    static $supported = null;

    if ($supported !== null) {
        return $supported;
    }

    try {
        $statusResult = db()->query("SHOW COLUMNS FROM applications LIKE 'status'");
        $statusColumn = $statusResult ? $statusResult->fetch_assoc() : null;
        $statusType = strtolower((string) ($statusColumn['Type'] ?? ''));

        $reasonResult = db()->query("SHOW COLUMNS FROM applications LIKE 'cancellation_reason'");
        $reasonColumn = $reasonResult ? $reasonResult->fetch_assoc() : null;

        $cancelledResult = db()->query("SHOW COLUMNS FROM applications LIKE 'cancelled_at'");
        $cancelledColumn = $cancelledResult ? $cancelledResult->fetch_assoc() : null;

        $supported =
            str_contains($statusType, "'cancelled'")
            && $reasonColumn !== null
            && $cancelledColumn !== null;
    } catch (Throwable) {
        $supported = false;
    }

    return $supported;
}

function sts_can_view_application(array $application, array $user): bool
{
    $role = normalized_role($user['role'] ?? '');

    if ($role === 'admin') {
        return true;
    }

    if ((int) ($application['user_id'] ?? 0) === (int) $user['id']) {
        return true;
    }

    if (!user_can_review_applications($user)) {
        return false;
    }

    if ($role === 'head_of_department') {
        $assignedHod = (int) ($application['assigned_hod_id'] ?? 0);

        if ($assignedHod > 0) {
            return $assignedHod === (int) $user['id'];
        }

        $applicationDepartment = trim((string) ($application['department'] ?? ''));
        $userDepartment = trim((string) ($user['department'] ?? ''));

        return $applicationDepartment === ''
            || $userDepartment === ''
            || strcasecmp($applicationDepartment, $userDepartment) === 0;
    }

    return true;
}

function sts_can_review_application(array $application, array $user): bool
{
    $stage = (string) ($application['current_stage'] ?? '');

    if (!user_can_review_stage($stage, $user)) {
        return false;
    }

    if (!sts_can_view_application($application, $user)) {
        return false;
    }

    return (string) ($application['status'] ?? '') === 'pending'
        && $stage !== 'completed';
}

function sts_unread_notifications(int $userId): int
{
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) AS total
             FROM notifications
             WHERE user_id = ? AND is_read = 0'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($row['total'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
}

function sts_store_attachments(int $applicationId, int $userId): array
{
    $stored = [];

    if (
        empty($_FILES['attachments'])
        || !is_array($_FILES['attachments']['name'] ?? null)
    ) {
        return $stored;
    }

    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    $uploadDir = dirname(__DIR__) . '/uploads/applications';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return $stored;
    }

    $names = $_FILES['attachments']['name'];
    $tmpNames = $_FILES['attachments']['tmp_name'];
    $errors = $_FILES['attachments']['error'];
    $sizes = $_FILES['attachments']['size'];

    foreach ($names as $index => $originalName) {
        if (($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $size = (int) ($sizes[$index] ?? 0);

        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            continue;
        }

        $tmp = (string) ($tmpNames[$index] ?? '');

        if (!is_uploaded_file($tmp)) {
            continue;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);

        if (!isset($allowed[$mime])) {
            continue;
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $destination = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($tmp, $destination)) {
            continue;
        }

        $safeOriginal = mb_substr(basename((string) $originalName), 0, 255);

        $stmt = db()->prepare(
            'INSERT INTO application_attachments
                (application_id, uploaded_by, original_name, stored_name, mime_type, file_size)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iisssi',
            $applicationId,
            $userId,
            $safeOriginal,
            $storedName,
            $mime,
            $size
        );
        $stmt->execute();
        $attachmentId = (int) $stmt->insert_id;
        $stmt->close();

        $stored[] = [
            'id' => $attachmentId,
            'name' => $safeOriginal,
            'size' => $size,
        ];
    }

    return $stored;
}

function sts_validate_parent_bpl(
    int $parentApplicationId,
    array $user,
    string $followupType
): ?array {
    if ($parentApplicationId <= 0) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, application_no, user_id, department, assigned_hod_id,
                title, payload, status, current_stage, training_end
         FROM applications
         WHERE id = ? AND form_type = "BPL"
         LIMIT 1'
    );
    $stmt->bind_param('i', $parentApplicationId);
    $stmt->execute();
    $parent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$parent || $parent['status'] !== 'approved') {
        return null;
    }

    $role = normalized_role($user['role'] ?? '');

    if (strtoupper($followupType) === 'PKK') {
        return (int) $parent['user_id'] === (int) $user['id'] ? $parent : null;
    }

    if (strtoupper($followupType) === 'TEA') {
        if ($role === 'admin') {
            return $parent;
        }

        if ($role !== 'head_of_department') {
            return null;
        }

        return sts_can_view_application($parent, $user) ? $parent : null;
    }

    return null;
}


function sts_ensure_followup_notifications(array $user): void
{
    $role = normalized_role($user['role'] ?? '');
    $userId = (int) ($user['id'] ?? 0);

    if ($userId <= 0 || !in_array($role, ['staff', 'head_of_department'], true)) {
        return;
    }

    try {
        if ($role === 'staff') {
            $stmt = db()->prepare(
                'SELECT b.id, b.application_no, b.title, b.training_end
                 FROM applications b
                 WHERE b.user_id = ?
                   AND b.form_type = "BPL"
                   AND b.status = "approved"
                   AND b.training_end IS NOT NULL
                   AND NOT EXISTS (
                     SELECT 1 FROM applications p
                     WHERE p.parent_application_id = b.id
                       AND p.form_type = "PKK"
                   )
                 ORDER BY b.training_end ASC'
            );
            $stmt->bind_param('i', $userId);
            $type = 'PKK';
        } else {
            $department = trim((string) ($user['department'] ?? ''));
            $stmt = db()->prepare(
                'SELECT b.id, b.application_no, b.title, b.training_end
                 FROM applications b
                 WHERE b.form_type = "BPL"
                   AND b.status = "approved"
                   AND b.training_end IS NOT NULL
                   AND (
                     b.assigned_hod_id = ?
                     OR (
                       b.assigned_hod_id IS NULL
                       AND (b.department = ? OR b.department IS NULL OR b.department = "")
                     )
                   )
                   AND NOT EXISTS (
                     SELECT 1 FROM applications t
                     WHERE t.parent_application_id = b.id
                       AND t.form_type = "TEA"
                   )
                 ORDER BY b.training_end ASC'
            );
            $stmt->bind_param('is', $userId, $department);
            $type = 'TEA';
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $due = sts_followup_due((string) $row['training_end'], $type);

            if (!$due || strtotime($due) > time()) {
                continue;
            }

            $link = strtolower($type) . '.php?parent=' . (int) $row['id'];
            $title = $type . ' follow up due';

            $check = db()->prepare(
                'SELECT id
                 FROM notifications
                 WHERE user_id = ? AND title = ? AND link = ?
                 LIMIT 1'
            );
            $check->bind_param('iss', $userId, $title, $link);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                continue;
            }

            sts_notify(
                $userId,
                $title,
                $row['application_no'] . ' · ' . $row['title']
                    . ' is ready for ' . $type . ' follow up.',
                $link,
                'warning'
            );
        }

        $stmt->close();
    } catch (Throwable) {
        // Follow up reminders are recreated the next time the dashboard loads.
    }
}
