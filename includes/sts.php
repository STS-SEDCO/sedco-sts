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

        $bindTypes = str_repeat('s', count($roles));
        $bindArgs = [$bindTypes];

        foreach ($roles as $index => $roleValue) {
            $bindArgs[] = &$roles[$index];
        }

        call_user_func_array([$stmt, 'bind_param'], $bindArgs);
        $stmt->execute();
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

    if ($stage === 'gm') {
        sts_notify_role(
            ['general_manager'],
            $title,
            $message,
            $link,
            'review'
        );
        return;
    }

    if ($stage === 'chairman') {
        sts_notify_role(
            ['pengerusi_besar'],
            $title,
            $message,
            $link,
            'review'
        );
        return;
    }

    if ($stage === 'finance') {
        sts_notify_role(
            ['finance'],
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

function sts_bpl_has_downstream_tea(int $parentBplId): bool
{
    if ($parentBplId <= 0) {
        return false;
    }

    static $evaluatedBplIds = null;

    if ($evaluatedBplIds === null) {
        $evaluatedBplIds = [];

        $stmt = db()->prepare(
            'SELECT parent_application_id, payload
             FROM applications
             WHERE form_type = "TEA"
               AND status <> "cancelled"'
        );
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $primaryParentId = (int) ($row['parent_application_id'] ?? 0);

            if ($primaryParentId > 0) {
                $evaluatedBplIds[$primaryParentId] = true;
            }

            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            $payload = is_array($payload) ? $payload : [];
            $linkedIds = $payload['evaluated_parent_ids'] ?? [];

            if (!is_array($linkedIds)) {
                $linkedIds = [$linkedIds];
            }

            foreach ($linkedIds as $linkedId) {
                $linkedId = (int) $linkedId;

                if ($linkedId > 0) {
                    $evaluatedBplIds[$linkedId] = true;
                }
            }
        }

        $stmt->close();
    }

    return isset($evaluatedBplIds[$parentBplId]);
}

function sts_pkk_has_downstream_tea(array $application): bool
{
    $parentBplId = (int) ($application['parent_application_id'] ?? 0);

    if ($parentBplId <= 0) {
        return true;
    }

    return sts_bpl_has_downstream_tea($parentBplId);
}

function sts_can_edit_pkk(array $application, array $user): bool
{
    if (
        (string) ($application['form_type'] ?? '') !== 'PKK'
        || (int) ($application['user_id'] ?? 0) !== (int) ($user['id'] ?? 0)
        || (string) ($application['status'] ?? '') === 'cancelled'
    ) {
        return false;
    }

    return !sts_pkk_has_downstream_tea($application);
}

function sts_can_cancel_pkk(array $application, array $user): bool
{
    return sts_cancel_application_supported()
        && sts_can_edit_pkk($application, $user);
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

        return $applicationDepartment !== ''
            && $userDepartment !== ''
            && strcasecmp($applicationDepartment, $userDepartment) === 0;
    }

    return true;
}

function sts_bpl_applicant_role(array $application): string
{
    if (!empty($application['applicant_role'])) {
        return normalized_role((string) $application['applicant_role']);
    }

    $userId = (int) ($application['user_id'] ?? 0);

    if ($userId <= 0) {
        return '';
    }

    static $roleCache = [];

    if (array_key_exists($userId, $roleCache)) {
        return $roleCache[$userId];
    }

    try {
        $stmt = db()->prepare(
            'SELECT role
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $roleCache[$userId] = normalized_role((string) ($row['role'] ?? ''));
    } catch (Throwable) {
        $roleCache[$userId] = '';
    }

    return $roleCache[$userId];
}

function sts_bpl_required_stages(array $application): array
{
    $stages = ['training', 'hod', 'gm', 'chairman', 'finance'];
    $applicantRole = sts_bpl_applicant_role($application);

    $skipByRole = [
        'head_of_department' => ['hod'],
        'general_manager' => ['hod', 'gm'],
        'pengerusi_besar' => ['hod', 'gm', 'chairman'],
    ];

    $skipStages = $skipByRole[$applicantRole] ?? [];

    if ($skipStages) {
        $stages = array_values(array_filter(
            $stages,
            static fn (string $stage): bool => !in_array($stage, $skipStages, true)
        ));
    }

    return $stages;
}

function sts_next_bpl_stage(array $application, string $currentStage): string
{
    $stages = sts_bpl_required_stages($application);
    $index = array_search($currentStage, $stages, true);

    if ($index === false) {
        return $stages[0] ?? 'completed';
    }

    return $stages[$index + 1] ?? 'completed';
}

function sts_expected_bpl_stage(array $application): string
{
    if ((string) ($application['form_type'] ?? '') !== 'BPL') {
        return (string) ($application['current_stage'] ?? 'completed');
    }

    if ((string) ($application['status'] ?? '') !== 'pending') {
        return (string) ($application['current_stage'] ?? 'training');
    }

    $applicationId = (int) ($application['id'] ?? 0);

    if ($applicationId <= 0) {
        return 'training';
    }

    $approvedStages = [];

    try {
        $stmt = db()->prepare(
            'SELECT review_stage
             FROM application_reviews
             WHERE application_id = ?
               AND decision = "approved"
             ORDER BY reviewed_at ASC, id ASC'
        );
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $approvedStages[(string) $row['review_stage']] = true;
        }

        $stmt->close();
    } catch (Throwable) {
        return (string) ($application['current_stage'] ?? 'training');
    }

    foreach (sts_bpl_required_stages($application) as $stage) {
        if (empty($approvedStages[$stage])) {
            return $stage;
        }
    }

    return 'completed';
}

function sts_sync_bpl_pending_stage(array $application): string
{
    $expectedStage = sts_expected_bpl_stage($application);
    $currentStage = (string) ($application['current_stage'] ?? '');

    if (
        (string) ($application['form_type'] ?? '') !== 'BPL'
        || (string) ($application['status'] ?? '') !== 'pending'
        || $expectedStage === $currentStage
    ) {
        return $expectedStage;
    }

    $applicationId = (int) ($application['id'] ?? 0);

    if ($applicationId <= 0) {
        return $expectedStage;
    }

    try {
        $slaDueAt = $expectedStage === 'completed'
            ? null
            : sts_review_sla_due();

        $stmt = db()->prepare(
            'UPDATE applications
             SET current_stage = ?, sla_due_at = ?
             WHERE id = ?
               AND status = "pending"'
        );
        $stmt->bind_param('ssi', $expectedStage, $slaDueAt, $applicationId);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable) {
        // Stage validation still uses the expected stage even if repair cannot be persisted.
    }

    return $expectedStage;
}

function sts_repair_pending_bpl_stages(): void
{
    try {
        $result = db()->query(
            'SELECT a.id, a.application_no, a.user_id, a.form_type,
                    a.status, a.current_stage, u.role AS applicant_role
             FROM applications a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.form_type = "BPL"
               AND a.status = "pending"'
        );

        $applications = [];

        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }

        foreach ($applications as $application) {
            sts_sync_bpl_pending_stage($application);
        }
    } catch (Throwable) {
        // Approval screens still enforce stage prerequisites even if repair fails.
    }
}

function sts_stage_prerequisites_met(array $application): bool
{
    if ((string) ($application['form_type'] ?? '') !== 'BPL') {
        return true;
    }

    if ((string) ($application['status'] ?? '') !== 'pending') {
        return false;
    }

    $expectedStage = sts_expected_bpl_stage($application);
    $currentStage = (string) ($application['current_stage'] ?? '');

    return $currentStage === $expectedStage;
}

function sts_can_review_application(array $application, array $user): bool
{
    if (!sts_stage_prerequisites_met($application)) {
        return false;
    }

    $stage = (string) ($application['current_stage'] ?? '');

    if (
        (int) ($application['user_id'] ?? 0) === (int) ($user['id'] ?? 0)
        && normalized_role($user['role'] ?? '') !== 'admin'
    ) {
        return false;
    }

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
        if ((int) $parent['user_id'] !== (int) $user['id']) {
            return null;
        }

        $trainingEnd = trim((string) ($parent['training_end'] ?? ''));
        $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Kuala_Lumpur')))->format('Y-m-d');

        if ($trainingEnd === '' || $trainingEnd > $today) {
            return null;
        }

        $duplicate = db()->prepare(
            'SELECT id
             FROM applications
             WHERE parent_application_id = ?
               AND form_type = "PKK"
               AND status <> "cancelled"
             LIMIT 1'
        );
        $duplicate->bind_param('i', $parentApplicationId);
        $duplicate->execute();
        $existing = $duplicate->get_result()->fetch_assoc();
        $duplicate->close();

        return $existing ? null : $parent;
    }

    if (strtoupper($followupType) === 'TEA') {
        if ($role !== 'head_of_department') {
            return null;
        }

        if ((int) ($parent['user_id'] ?? 0) === (int) ($user['id'] ?? 0)) {
            return null;
        }

        $hodDepartment = trim((string) ($user['department'] ?? ''));
        $applicantDepartment = trim((string) ($parent['department'] ?? ''));

        if ($applicantDepartment === '') {
            $departmentStmt = db()->prepare(
                'SELECT department
                 FROM users
                 WHERE id = ?
                 LIMIT 1'
            );
            $parentUserId = (int) ($parent['user_id'] ?? 0);
            $departmentStmt->bind_param('i', $parentUserId);
            $departmentStmt->execute();
            $departmentRow = $departmentStmt->get_result()->fetch_assoc();
            $departmentStmt->close();
            $applicantDepartment = trim((string) ($departmentRow['department'] ?? ''));
        }

        if (
            $hodDepartment === ''
            || $applicantDepartment === ''
            || strcasecmp($hodDepartment, $applicantDepartment) !== 0
        ) {
            return null;
        }

        $trainingEnd = trim((string) ($parent['training_end'] ?? ''));
        $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Kuala_Lumpur')))->format('Y-m-d');

        if ($trainingEnd === '' || $trainingEnd > $today) {
            return null;
        }

        $pkkCheck = db()->prepare(
            'SELECT id
             FROM applications
             WHERE parent_application_id = ?
               AND form_type = "PKK"
               AND status = "approved"
               AND current_stage = "completed"
             LIMIT 1'
        );
        $pkkCheck->bind_param('i', $parentApplicationId);
        $pkkCheck->execute();
        $hasCompletedPkk = (bool) $pkkCheck->get_result()->fetch_assoc();
        $pkkCheck->close();

        if (!$hasCompletedPkk) {
            return null;
        }

        return sts_bpl_has_downstream_tea($parentApplicationId)
            ? null
            : $parent;
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

    if ($role === 'head_of_department') {
        $evaluationMonth = (int) (
            new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'))
        )->format('n');

        if (!in_array($evaluationMonth, [1, 6, 7, 12], true)) {
            return;
        }
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
                       AND p.status <> "cancelled"
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
                 INNER JOIN users u ON u.id = b.user_id
                 WHERE b.form_type = "BPL"
                   AND b.status = "approved"
                   AND b.training_end IS NOT NULL
                   AND b.training_end <= CURDATE()
                   AND b.user_id <> ?
                   AND LOWER(TRIM(COALESCE(NULLIF(b.department, ""), NULLIF(u.department, "")))) = LOWER(TRIM(?))
                   AND EXISTS (
                     SELECT 1 FROM applications p
                     WHERE p.parent_application_id = b.id
                       AND p.form_type = "PKK"
                       AND p.status = "approved"
                       AND p.current_stage = "completed"
                   )
                 ORDER BY b.training_end ASC'
            );
            $stmt->bind_param('is', $userId, $department);
            $type = 'TEA';
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if ($type === 'TEA' && sts_bpl_has_downstream_tea((int) ($row['id'] ?? 0))) {
                continue;
            }

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
