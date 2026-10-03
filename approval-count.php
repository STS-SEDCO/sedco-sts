<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

$user = current_user();

if (!$user || !user_can_review_applications($user)) {
    echo json_encode(['count' => 0]);
    exit;
}

$count = 0;

try {
    $stmt = db()->prepare(
        'SELECT id, application_no, user_id, form_type, department,
                assigned_hod_id, status, current_stage
         FROM applications
         WHERE form_type = "BPL"
           AND status = "pending"
           AND current_stage <> "completed"'
    );
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if (sts_can_review_application($row, $user)) {
            $count++;
        }
    }

    $stmt->close();
} catch (Throwable) {
    $count = 0;
}

echo json_encode(['count' => $count]);
