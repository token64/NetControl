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

/**
 * Libera el bloqueo de sesión en el servidor (session_write_close).
 * PHP mantiene la sesión bloqueada desde session_start() hasta el fin del script;
 * si una página hace trabajo lento (p. ej. cURL) o el navegador dispara varias
 * peticiones en paralelo (fetch + documento principal), conviene llamar esto
 * en cuanto esta petición ya no vaya a modificar $_SESSION.
 */
function auth_release_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
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
