<?php
declare(strict_types=1);

function smarolt_request(string $endpoint, array $data = []): ?array
{
    if (SMAROLT_API_KEY === '') {
        return null;
    }

    $url = 'https://api.smarolt.com/v1/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'X-API-KEY: ' . SMAROLT_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
        CURLOPT_TIMEOUT    => 15,
    ]);

    $res = curl_exec($ch);
    if ($res === false) {
        curl_close($ch);
        return null;
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($res, true);
    return is_array($decoded) ? array_merge(['_http' => $code], $decoded) : null;
}
