<?php
declare(strict_types=1);
/**
 * Sin login: comprobar desde el navegador si el CT sirve código nuevo.
 * Ej.: http://IP/netcontrol/nc_version.php  →  esperado nc_panel_ui_rev=2
 */
require_once __DIR__ . '/includes/nc_build.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo 'nc_panel_ui_rev=' . NC_PANEL_UI_REV . "\n";
echo 'ok=netcontrol' . "\n";
