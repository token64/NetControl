<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify(): bool
{
    $t = $_POST['csrf_token'] ?? '';
    return is_string($t) && isset($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $t);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '">';
}
