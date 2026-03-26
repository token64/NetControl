<?php
declare(strict_types=1);

function auth_logged_in(): bool
{
    return !empty($_SESSION['panel_user']) && $_SESSION['panel_user'] === ADMIN_USER;
}

function require_auth(): void
{
    if (!auth_logged_in()) {
        flash_set('warning', 'Inicia sesión para continuar.');
        redirect('login.php');
    }
}

function auth_login(string $user, string $password): bool
{
    if ($user !== ADMIN_USER) {
        return false;
    }
    return password_verify($password, ADMIN_PASSWORD_HASH);
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
