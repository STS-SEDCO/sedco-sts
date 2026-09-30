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
        'SELECT id, fullname, email, phone_number, role, created_at
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();

    $result = $stmt->get_result();
    $cachedUser = $result->fetch_assoc() ?: null;
    $stmt->close();

    if ($cachedUser === null) {
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
        ['admin', 'training_section', 'head_of_department', 'pengerusi_besar'],
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
