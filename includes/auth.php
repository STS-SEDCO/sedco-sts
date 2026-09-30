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

    return in_array(
        $user['role'],
        ['admin', 'training_section', 'head_of_department', 'head_of_division', 'pengerusi_besar', 'general_manager'],
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
        'pengerusi_besar' => 'general_manager',
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

    if ($role === 'admin') {
        return true;
    }

    return match (strtoupper($formType)) {
        'BPL', 'PKK' => $role === 'staff',
        'TEA' => $role === 'head_of_department',
        default => false,
    };
}


function review_stage_for_role(?string $role): ?string
{
    return match (normalized_role($role)) {
        'head_of_department' => 'hod',
        'training_section' => 'training',
        'general_manager' => 'gm',
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
        'admin' => 'System Administrator',
        default => 'User',
    };
}

function stage_label(?string $stage): string
{
    return match ($stage) {
        'hod' => 'Head of Department',
        'training' => 'Training Department',
        'gm' => 'General Manager',
        'completed' => 'Completed',
        default => 'Pending',
    };
}

function user_can_review_stage(string $stage, ?array $user = null): bool
{
    $user ??= current_user();

    if (!$user) {
        return false;
    }

    if (normalized_role($user['role'] ?? '') === 'admin') {
        return in_array($stage, ['hod', 'training', 'gm'], true);
    }

    return review_stage_for_role($user['role'] ?? '') === $stage;
}

require_once __DIR__ . '/sts.php';
