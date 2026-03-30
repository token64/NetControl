<?php
/**
 * CLI: aplica un .sql usando las mismas credenciales que el panel.
 * Uso (desde la raíz del repo): php panel/tools/apply_sql_file.php sql/migration_finanzas_pagos.sql
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/config.php';
require $root . '/includes/db.php';

$file = $argv[1] ?? '';
if ($file === '') {
    fwrite(STDERR, "Uso: php apply_sql_file.php <archivo.sql>\n");
    exit(1);
}

if (!preg_match('#^([A-Za-z]:)?[/\\\\]#', $file)) {
    $file = getcwd() . DIRECTORY_SEPARATOR . $file;
}

if (!is_readable($file)) {
    fwrite(STDERR, "No se puede leer: {$file}\n");
    exit(1);
}

$sql = file_get_contents($file);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Archivo vacío o ilegible.\n");
    exit(1);
}

try {
    $mysqli = db();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    fwrite(STDERR, "Revisá DB_HOST, DB_USER, DB_PASS y DB_NAME en panel/config.php\n");
    exit(1);
}

if (!$mysqli->multi_query($sql)) {
    fwrite(STDERR, $mysqli->error . "\n");
    exit(1);
}

do {
    if ($result = $mysqli->store_result()) {
        $result->free();
    }
} while ($mysqli->more_results() && $mysqli->next_result());

if ($mysqli->errno) {
    fwrite(STDERR, $mysqli->error . "\n");
    exit(1);
}

echo "OK: {$file}\n";
