<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: application-status.php');
    exit;
}

verify_csrf();

$user = current_user();

if (!$user) {
    header('Location: login.php');
    exit;
}

$applicationNo = trim((string) ($_POST['application_no'] ?? ''));
$reason = trim((string) ($_POST['cancellation_reason'] ?? ''));

if ($applicationNo === '') {
    http_response_code(400);
    exit('Invalid application.');
}

if (mb_strlen($reason) < 5) {
    header(
        'Location: application-status.php?cancel_error=1&application='
        . rawurlencode($applicationNo)
    );
    exit;
}

$db = db();
$db->begin_transaction();

try {
    $stmt = $db->prepare(
        'SELECT id, application_no, user_id, form_type, title, department,
                assigned_hod_id, payload, status, current_stage
         FROM applications
         WHERE application_no = ?
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->bind_param('s', $applicationNo);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$application) {
        throw new RuntimeException('Application not found.');
    }

    if ((int) $application['user_id'] !== (int) $user['id']) {
        http_response_code(403);
        throw new RuntimeException('You can only cancel your own application.');
    }

    if (!in_array((string) $application['status'], ['pending', 'correction'], true)) {
        throw new RuntimeException('This application can no longer be cancelled.');
    }

    $applicationId = (int) $application['id'];
    $userId = (int) $user['id'];
    $payload = json_decode((string) $application['payload'], true);
    $payload = is_array($payload) ? $payload : [];

    sts_save_version(
        $applicationId,
        $userId,
        'cancelled',
        $payload + ['_cancellation_reason' => $reason]
    );

    $update = $db->prepare(
        'UPDATE applications
         SET status = "cancelled",
             current_stage = "completed",
             sla_due_at = NULL,
             cancelled_at = NOW(),
             cancellation_reason = ?,
             completed_at = NOW(),
             review_note = NULL
         WHERE id = ?'
    );
    $update->bind_param('si', $reason, $applicationId);
    $update->execute();
    $update->close();

    $db->commit();

    sts_audit(
        'application_cancelled',
        'application',
        $applicationNo,
        [
            'form_type' => $application['form_type'],
            'previous_stage' => $application['current_stage'],
            'reason' => $reason,
        ],
        $userId
    );

    sts_notify(
        $userId,
        'Application cancelled',
        $applicationNo . ' has been cancelled successfully.',
        'application-detail.php?application=' . rawurlencode($applicationNo),
        'warning'
    );

    $reviewerMessage = $applicationNo . ' was cancelled by the applicant and no longer requires review.';

    if ($application['current_stage'] === 'hod') {
        sts_notify_role(
            ['head_of_department', 'head_of_division'],
            'Application cancelled',
            $reviewerMessage,
            'submissions.php',
            'warning',
            $application['department'] ?? null,
            !empty($application['assigned_hod_id']) ? (int) $application['assigned_hod_id'] : null
        );
    } elseif ($application['current_stage'] === 'training') {
        sts_notify_role(
            ['training_section'],
            'Application cancelled',
            $reviewerMessage,
            'submissions.php',
            'warning'
        );
    } elseif ($application['current_stage'] === 'gm') {
        sts_notify_role(
            ['general_manager', 'pengerusi_besar'],
            'Application cancelled',
            $reviewerMessage,
            'submissions.php',
            'warning'
        );
    }

    header(
        'Location: application-status.php?cancelled=1&application='
        . rawurlencode($applicationNo)
    );
    exit;
} catch (Throwable $error) {
    $db->rollback();

    if (http_response_code() < 400) {
        http_response_code(400);
    }

    exit(e($error->getMessage()));
}
