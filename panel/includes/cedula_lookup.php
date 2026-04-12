<?php
declare(strict_types=1);

/**
 * Consulta opcional de nombre por cédula vía HTTP (plantilla configurable).
 * Típico en RD: API de consulta pública por RNC/cédula (DGII / terceros); no sustituye acuerdos con la JCE.
 */
function nc_cedula_lookup_enabled(): bool
{
    if (! defined('CEDULA_LOOKUP_ENABLED') || CEDULA_LOOKUP_ENABLED !== true) {
        return false;
    }
    if (! defined('CEDULA_LOOKUP_URL_TEMPLATE')) {
        return false;
    }
    $tpl = trim((string) CEDULA_LOOKUP_URL_TEMPLATE);

    return $tpl !== '' && str_contains($tpl, '%s');
}

function nc_cedula_lookup_etiqueta(): string
{
    if (defined('CEDULA_LOOKUP_ETIQUETA_FUENTE')) {
        $t = trim((string) CEDULA_LOOKUP_ETIQUETA_FUENTE);

        return $t !== '' ? $t : 'Consulta externa';
    }

    return 'Consulta externa';
}

/** Cédula identity RD moderna: 11 dígitos. */
function nc_cedula_digits_rd(string $raw): ?string
{
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === '' || strlen($d) !== 11) {
        return null;
    }

    return $d;
}

function nc_cedula_lookup_insecure_ssl(): bool
{
    return defined('CEDULA_LOOKUP_INSECURE_SSL') && CEDULA_LOOKUP_INSECURE_SSL === true;
}

/** Proxy saliente para cURL (ej. `http://127.0.0.1:8888` o `tcp://proxy.empresa.com:3128`). */
function nc_cedula_lookup_http_proxy(): string
{
    if (! defined('CEDULA_LOOKUP_HTTP_PROXY')) {
        return '';
    }

    return trim((string) CEDULA_LOOKUP_HTTP_PROXY);
}

function nc_cedula_lookup_http_proxy_userpwd(): string
{
    if (! defined('CEDULA_LOOKUP_HTTP_PROXY_USERPWD')) {
        return '';
    }

    return trim((string) CEDULA_LOOKUP_HTTP_PROXY_USERPWD);
}

function nc_cedula_apply_proxy_curl($ch): void
{
    $proxy = nc_cedula_lookup_http_proxy();
    if ($proxy === '') {
        return;
    }
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    $up = nc_cedula_lookup_http_proxy_userpwd();
    if ($up !== '') {
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $up);
    }
}

/** @return list<string> */
function nc_cedula_lookup_http_headers(): array
{
    return [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Accept-Language: es-DO,es,en;q=0.9',
    ];
}

/**
 * Cabeceras extra si el host lo suelen exigir los WAF (p. ej. megaplus + Imunify360).
 *
 * @return list<string>
 */
function nc_cedula_lookup_http_headers_for_url(string $url): array
{
    $h = nc_cedula_lookup_http_headers();
    $host = parse_url($url, PHP_URL_HOST);
    if (is_string($host) && str_contains(strtolower($host), 'megaplus.com.do')) {
        $h[] = 'Referer: https://rnc.megaplus.com.do/';
        $h[] = 'Origin: https://rnc.megaplus.com.do';
    }

    return $h;
}

/** Aclara errores típicos de proveedores externos (sin ocultar el mensaje original). */
function nc_cedula_lookup_clarify_error(string $error): string
{
    $e = trim($error);
    if ($e === '') {
        return $error;
    }
    if (stripos($e, 'imunify') !== false || stripos($e, 'bot-protection') !== false) {
        return $e . ' La IP pública de este servidor está bloqueada como “automatización”. Soluciones: pedir whitelist al proveedor de la API, usar `CEDULA_LOOKUP_HTTP_PROXY` si tenés proxy saliente permitido, o apuntar `CEDULA_LOOKUP_URL_TEMPLATE` a un relay propio (ver deploy/cedula-relay.example.php). Podés desactivar la consulta con CEDULA_LOOKUP_ENABLED = false.';
    }

    return $e;
}

function nc_http_apply_ssl_opts($ch): void
{
    if (nc_cedula_lookup_insecure_ssl()) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
}

function nc_http_get_body(string $url, int $timeoutSec = 15): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeoutSec,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTPHEADER => nc_cedula_lookup_http_headers_for_url($url),
        ]);
        nc_http_apply_ssl_opts($ch);
        nc_cedula_apply_proxy_curl($ch);
        $body = curl_exec($ch);
        curl_close($ch);
        if (! is_string($body) || $body === '') {
            return null;
        }

        return $body;
    }
    $opts = [
        'http' => [
            'timeout' => $timeoutSec,
            'header' => implode("\r\n", nc_cedula_lookup_http_headers_for_url($url)) . "\r\n",
            'ignore_errors' => true,
        ],
    ];
    if (nc_cedula_lookup_insecure_ssl()) {
        $opts['ssl'] = ['verify_peer' => false, 'verify_peer_name' => false];
    }
    $ctx = stream_context_create($opts);
    $body = @file_get_contents($url, false, $ctx);

    return is_string($body) && $body !== '' ? $body : null;
}

/**
 * POST JSON (p. ej. megaplus /api/consulta con body {"rnc":"..."}).
 */
function nc_http_post_json_body(string $url, array $payload, int $timeoutSec = 15): ?string
{
    if (! function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return null;
    }
    $headers = nc_cedula_lookup_http_headers_for_url($url);
    $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => $timeoutSec,
        CURLOPT_TIMEOUT => $timeoutSec,
    ]);
    nc_http_apply_ssl_opts($ch);
    nc_cedula_apply_proxy_curl($ch);
    $body = curl_exec($ch);
    curl_close($ch);
    if (! is_string($body) || $body === '') {
        return null;
    }

    return $body;
}

/** URL POST sin query, a partir de plantilla GET ...?rnc=%s */
function nc_cedula_post_url_from_template(string $tpl): ?string
{
    $cut = str_replace(['?rnc=%s', '&rnc=%s'], '', $tpl);
    if ($cut === $tpl || str_contains($cut, '%s')) {
        return null;
    }

    return $cut;
}

/**
 * Extrae un nombre legible de distintos formatos JSON (megaplus, wrappers, etc.).
 */
function nc_cedula_extract_nombre_from_array(array $json): ?string
{
    $tryString = static function ($v): ?string {
        if (! is_string($v)) {
            return null;
        }
        $t = trim($v);

        return $t !== '' ? $t : null;
    };

    foreach (['nombre_razon_social', 'razon_social', 'nombre', 'nombre_completo', 'NombreRazonSocial', 'RAZON_SOCIAL'] as $k) {
        if (isset($json[$k])) {
            $s = $tryString($json[$k]);
            if ($s !== null) {
                return $s;
            }
        }
    }

    $nombres = $tryString($json['NOMBRES'] ?? $json['nombres'] ?? null);
    $a1 = $tryString($json['APELLIDO1'] ?? $json['apellido1'] ?? null);
    $a2 = $tryString($json['APELLIDO2'] ?? $json['apellido2'] ?? null);
    if ($nombres !== null || $a1 !== null || $a2 !== null) {
        $full = trim(implode(' ', array_filter([$nombres ?? '', $a1 ?? '', $a2 ?? ''], static fn ($x) => $x !== '')));

        return $full !== '' ? $full : null;
    }

    if (isset($json['data']) && is_array($json['data'])) {
        $nested = nc_cedula_extract_nombre_from_array($json['data']);
        if ($nested !== null) {
            return $nested;
        }
    }

    if (isset($json['resultados'][0]) && is_array($json['resultados'][0])) {
        return nc_cedula_extract_nombre_from_array($json['resultados'][0]);
    }

    return null;
}

/**
 * Interpreta cuerpo JSON de la API; devuelve ok+nombre, error, o null (sin datos útiles).
 *
 * @return array{ok: true, nombre: string}|array{ok: false, error: string}|null
 */
function nc_cedula_interpret_api_json(array $json): ?array
{
    if (! empty($json['error'])) {
        $msg = isset($json['mensaje']) && is_string($json['mensaje'])
            ? trim($json['mensaje'])
            : (isset($json['message']) && is_string($json['message']) ? trim($json['message']) : 'Sin resultados.');

        return ['ok' => false, 'error' => $msg !== '' ? $msg : 'Sin resultados.'];
    }

    $nombre = nc_cedula_extract_nombre_from_array($json);
    if ($nombre !== null) {
        return ['ok' => true, 'nombre' => $nombre];
    }

    foreach (['message', 'mensaje', 'Mensaje'] as $mk) {
        if (isset($json[$mk]) && is_string($json[$mk])) {
            $m = trim($json[$mk]);
            if ($m !== '') {
                return ['ok' => false, 'error' => $m];
            }
        }
    }

    return null;
}

/**
 * @return array{ok: true, nombre: string}|array{ok: false, error: string}
 */
function nc_lookup_nombre_por_cedula(string $cedula11): array
{
    if (! nc_cedula_lookup_enabled()) {
        return ['ok' => false, 'error' => 'Consulta deshabilitada.'];
    }
    $tpl = (string) CEDULA_LOOKUP_URL_TEMPLATE;
    $url = sprintf($tpl, rawurlencode($cedula11));
    $body = nc_http_get_body($url);
    if ($body === null) {
        return ['ok' => false, 'error' => nc_cedula_lookup_clarify_error('Sin respuesta del servicio de consulta (red, SSL o firewall).')];
    }
    $json = json_decode($body, true);
    if (! is_array($json)) {
        return ['ok' => false, 'error' => nc_cedula_lookup_clarify_error('Respuesta no válida (no JSON).')];
    }

    $interpreted = nc_cedula_interpret_api_json($json);
    if ($interpreted !== null) {
        if ($interpreted['ok']) {
            return $interpreted;
        }
        $err = $interpreted['error'];
        $tryPost = str_contains(strtolower($tpl), 'megaplus.com.do')
            && (stripos($err, 'imunify') !== false || stripos($err, 'bot-protection') !== false || stripos($err, '415') !== false);
        if (! $tryPost) {
            return ['ok' => false, 'error' => nc_cedula_lookup_clarify_error((string) $interpreted['error'])];
        }
    } else {
        $tryPost = str_contains(strtolower($tpl), 'megaplus.com.do');
    }

    if ($tryPost) {
        $postUrl = nc_cedula_post_url_from_template($tpl);
        if ($postUrl !== null) {
            $body2 = nc_http_post_json_body($postUrl, ['rnc' => $cedula11]);
            if ($body2 !== null) {
                $json2 = json_decode($body2, true);
                if (is_array($json2)) {
                    $interpreted2 = nc_cedula_interpret_api_json($json2);
                    if ($interpreted2 !== null) {
                        if (! $interpreted2['ok']) {
                            return ['ok' => false, 'error' => nc_cedula_lookup_clarify_error((string) $interpreted2['error'])];
                        }

                        return $interpreted2;
                    }
                }
            }
        }
    }

    if ($interpreted !== null && ! $interpreted['ok']) {
        return ['ok' => false, 'error' => nc_cedula_lookup_clarify_error((string) $interpreted['error'])];
    }

    return ['ok' => false, 'error' => nc_cedula_lookup_clarify_error('No hay nombre para esta cédula en el registro consultado (p. ej. no inscrita como contribuyente en DGII).')];
}
