<?php

require_once __DIR__ . '/../config/curl-helper.php';

function loadUtmifyOrderConfig(): array
{
    $file   = __DIR__ . '/../config/utmify.local.php';
    $config = is_file($file) ? include $file : [];
    if (!is_array($config)) $config = [];

    return array_merge([
        'pixel_id'  => '6a02277fb4d3d1b2ba254acf',
        'api_token' => '',
        'api_url'   => 'https://api.utmify.com.br/api-credentials/orders',
        'ssl_verify'=> true,
    ], $config);
}

function sendUtmifyOrder(array $payload): array
{
    $config = loadUtmifyOrderConfig();
    $token  = trim((string)($config['api_token'] ?? ''));
    $apiUrl = trim((string)($config['api_url']   ?? ''));

    if ($token === '' || $apiUrl === '') {
        return ['success' => false, 'error' => 'UTMify não configurada (api_token ausente)', 'http_code' => 500];
    }

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_RETURNTRANSFER=> true,
        CURLOPT_HTTPHEADER    => [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-api-token: ' . $token,
        ],
        CURLOPT_POSTFIELDS    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT       => 20,
        CURLOPT_CONNECTTIMEOUT=> 10,
    ]);
    applyCurlSslOptions($ch, $config);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'Falha ao comunicar com UTMify', 'details' => $curlError, 'http_code' => 502];
    }

    $decoded = json_decode($response, true);
    $ok      = $httpCode >= 200 && $httpCode < 300
        && is_array($decoded)
        && (($decoded['OK'] ?? false) === true || ($decoded['result'] ?? '') === 'SUCCESS');

    return [
        'success'  => $ok,
        'http_code'=> $httpCode,
        'utmify'   => $decoded ?: $response,
        'order_id' => $payload['orderId'] ?? null,
        'status'   => $payload['status']  ?? null,
    ];
}
