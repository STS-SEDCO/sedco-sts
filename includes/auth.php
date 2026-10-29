<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function require_login(): void
{
    if (empty($_SESSION['user_id']) || current_user() === null) {
        header('Location: login.php');
        exit;
    }
}

function current_user(): ?array
{
    static $cachedUser = false;

    if ($cachedUser !== false) {
        return $cachedUser;
    }

    if (empty($_SESSION['user_id'])) {
        $cachedUser = null;
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, fullname, email, phone_number, staff_id, department, job_title, role, is_active, created_at
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();

    $result = $stmt->get_result();
    $cachedUser = $result->fetch_assoc() ?: null;
    $stmt->close();

    if ($cachedUser === null || (int) ($cachedUser['is_active'] ?? 1) !== 1) {
        $cachedUser = null;
        $_SESSION = [];
        session_destroy();
    }

    return $cachedUser;
}

function user_can_review_applications(?array $user = null): bool
{
    $user ??= current_user();

    if (!$user) {
        return false;
    }

    // Finance is no longer an approval stage (P0 workflow update).
    // Finance role accounts can still log in but will not see the approval queue.
    return in_array(
        $user['role'],
        ['admin', 'training_section', 'head_of_department', 'head_of_division', 'general_manager', 'pengerusi_besar'],
        true
    );
}


function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = (string) ($_POST['_csrf'] ?? '');
    $expected = (string) ($_SESSION['csrf_token'] ?? '');

    if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
        http_response_code(419);
        exit('Your session has expired. Please refresh the page and try again.');
    }
}


function normalized_role(?string $role): string
{
    return match ($role) {
        'head_of_division' => 'head_of_department',
        default => (string) $role,
    };
}

function user_can_submit_form_type(string $formType, ?array $user = null): bool
{
    $user ??= current_user();

    if (!$user) {
        return false;
    }

    $role = normalized_role($user['role'] ?? '');
    $formType = strtoupper($formType);

    // TEA is strictly HOD-only, including for admin accounts.
    if ($formType === 'TEA') {
        return $role === 'head_of_department';
    }

    if ($role === 'admin') {
        return true;
    }

    return match ($formType) {
        'BPL', 'PKK' => in_array(
            $role,
            ['staff', 'training_section', 'head_of_department', 'general_manager', 'pengerusi_besar'],
            true
        ),
        default => false,
    };
}


function review_stage_for_role(?string $role): ?string
{
    // Returns the PRIMARY review stage for a role.
    // training_section is special: they handle both 'training' (initial) and
    // 'training_final'. This function returns their primary stage; use
    // user_can_review_stage() to check access to training_final.
    // Finance is no longer an approval stage — returns null.
    return match (normalized_role($role)) {
        'training_section' => 'training',
        'head_of_department' => 'hod',
        'general_manager' => 'gm',
        'pengerusi_besar' => 'chairman',
        default => null,
    };
}

function role_label(?string $role): string
{
    return match (normalized_role($role)) {
        'staff' => 'Staff / Applicant',
        'head_of_department' => 'Head of Department',
        'training_section' => 'Training Department',
        'general_manager' => 'General Manager',
        'pengerusi_besar' => 'Pengerusi',
        'finance' => 'Kewangan',
        'admin' => 'System Administrator',
        default => 'User',
    };
}

function stage_label(?string $stage): string
{
    return match ($stage) {
        'training'       => 'Training Department',
        'hod'            => 'Head of Department',
        'gm'             => 'General Manager',
        'chairman'       => 'Pengerusi',
        'training_final' => 'Training Department (Final Action)',
        'finance'        => 'Kewangan',        // kept for legacy display only
        'completed'      => 'Completed',
        default          => 'Pending',
    };
}

function user_can_review_stage(string $stage, ?array $user = null): bool
{
    $user ??= current_user();

    if (!$user) {
        return false;
    }

    $role = normalized_role($user['role'] ?? '');

    if ($role === 'admin') {
        return in_array($stage, ['training', 'hod', 'gm', 'chairman', 'training_final'], true);
    }

    // Training section handles both the initial review and the final action stage.
    if ($role === 'training_section') {
        return in_array($stage, ['training', 'training_final'], true);
    }

    return review_stage_for_role($user['role'] ?? '') === $stage;
}

require_once __DIR__ . '/sts.php';
