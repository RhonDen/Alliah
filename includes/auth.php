<?php
require_once __DIR__ . '/config.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentUserRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function redirectForRole(?string $role = null): void
{
    $resolvedRole = $role ?? currentUserRole();
    $target = $resolvedRole === 'admin' ? 'admin/dashboard.php' : 'my-bookings.php';
    header('Location: ' . BASE_URL . $target);
    exit;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        if (function_exists('setFlashMessage')) {
            setFlashMessage('error', 'Please log in to continue.');
        }
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();

    if (currentUserRole() !== $role) {
        if (function_exists('setFlashMessage')) {
            setFlashMessage('error', 'You do not have permission to access that page.');
        }
        redirectForRole();
    }
}

function userHasPassword(array $user): bool
{
    return !empty($user['password']);
}
