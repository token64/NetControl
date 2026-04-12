<?php
/**
 * EJEMPLO — Relay mínimo para consulta cédula/RNC (megaplus u otro JSON).
 *
 * 1) Copiá este archivo a un host con PHP cuya IP NO esté bloqueada por el proveedor.
 * 2) Cambiá RELAY_SECRET por un string largo aleatorio.
 * 3) En NetControl config.php:
 *      const CEDULA_LOOKUP_URL_TEMPLATE = 'https://TU-DOMINIO/cedula-relay.php?key=TU_SECRETO&rnc=%s';
 *    (el %s sigue siendo solo la cédula de 11 dígitos; el relay reenvía a megaplus).
 *
 * Seguridad: sin RELAY_SECRET correcto responde 403. No subas el secreto a git.
 */
declare(strict_types=1);

const RELAY_SECRET = 'cambiá-esto-por-una-clave-larga-aleatoria';
const UPSTREAM_GET = 'https://rnc.megaplus.com.do/api/consulta?rnc=%s';

header('Content-Type: application/json; charset=UTF-8');

$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if (! hash_equals(RELAY_SECRET, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rnc = isset($_GET['rnc']) ? preg_replace('/\D+/', '', (string) $_GET['rnc']) : '';
if (strlen($rnc) !== 11) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'rnc inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = sprintf(UPSTREAM_GET, rawurlencode($rnc));
if (! function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'sin curl'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36',
        'Accept-Language: es-DO,es,en;q=0.9',
        'Referer: https://rnc.megaplus.com.do/',
    ],
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (! is_string($body) || $body === '') {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'sin respuesta upstream'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code($code >= 200 && $code < 300 ? 200 : $code);
echo $body;
