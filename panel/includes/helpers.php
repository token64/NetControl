<?php
declare(strict_types=1);

function esc(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['_flash'])) {
        return null;
    }
    $f = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $f;
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Método no permitido');
    }
}

/** Nombre mostrado en sidebar/topbar (config opcional). */
function nc_admin_display_name(): string
{
    if (defined('ADMIN_DISPLAY_NAME')) {
        $n = trim((string) constant('ADMIN_DISPLAY_NAME'));
        if ($n !== '') {
            return $n;
        }
    }
    return ADMIN_USER;
}

/** Iniciales para avatar (2 letras). */
function nc_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'NC';
    }
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) >= 2) {
        $a = function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1);
        $b = function_exists('mb_substr') ? mb_substr($parts[1], 0, 1) : substr($parts[1], 0, 1);
        return strtoupper($a . $b);
    }
    $s = function_exists('mb_substr') ? mb_substr($name, 0, 2) : substr($name, 0, 2);
    return strtoupper($s);
}
