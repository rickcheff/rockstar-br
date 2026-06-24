<?php

require_once __DIR__ . '/../config/meta.php';
require_once __DIR__ . '/../config/curl-helper.php';

function metaClientIp(): string
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (empty($_SERVER[$key])) continue;
        $value = trim((string)$_SERVER[$key]);
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $value);
            $value = trim($parts[0]);
        }
        if (filter_var($value, FILTER_VALIDATE_IP)) return $value;
    }
    return '0.0.0.0';
}

function metaHashValue(?string $value): ?string
{
    if ($value === null) return null;
    $normalized = strtolower(trim($value));
    if ($normalized === '') return null;
    return hash('sha256', $normalized);
}

function metaHashPhone(?string $phone, string $defaultCountry = '351'): ?string
{
    if ($phone === null) return null;
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') return null;
    if (strlen($digits) <= 11 && strpos($digits, $defaultCountry) !== 0) {
        $digits = $defaultCountry . $digits;
    }
    return hash('sha256', $digits);
}

function clientIpOverride(array $userInput): string
{
    if (!empty($userInput['client_ip_address']) && filter_var($userInput['client_ip_address'], FILTER_VALIDATE_IP)) {
        return $userInput['client_ip_address'];
    }
    return metaClientIp();
}

function sendMetaCapiEvent(array $input): array
{
    $config       = loadMetaConfig();
    $pixelId      = trim((string)($config['pixel_id']      ?? ''));
    $accessToken  = trim((string)($config['access_token']  ?? ''));
    $apiVersion   = trim((string)($config['api_version']   ?? 'v21.0'));
    $phoneCountry = trim((string)($config['default_phone_country'] ?? '351'));

    if ($pixelId === '' || $accessToken === '') {
        return ['success' => false, 'error' => 'Meta Pixel não configurado (pixel_id ou access_token ausente)', 'http_code' => 500];
    }

    $eventName = trim((string)($input['event_name'] ?? ''));
    if ($eventName === '') {
        return ['success' => false, 'error' => 'event_name é obrigatório', 'http_code' => 400];
    }

    $userInput   = is_array($input['user_data']   ?? null) ? $input['user_data']   : [];
    $customInput = is_array($input['custom_data'] ?? null) ? $input['custom_data'] : [];

    $userData = array_filter([
        'client_ip_address' => clientIpOverride($userInput),
        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'fbp' => $userInput['fbp'] ?? null,
        'fbc' => $userInput['fbc'] ?? null,
        'em'  => ($em = metaHashValue($userInput['email']      ?? null)) ? [$em] : null,
        'ph'  => ($ph = metaHashPhone($userInput['phone']      ?? null, $phoneCountry)) ? [$ph] : null,
        'fn'  => ($fn = metaHashValue($userInput['first_name'] ?? null)) ? [$fn] : null,
        'ln'  => ($ln = metaHashValue($userInput['last_name']  ?? null)) ? [$ln] : null,
    ], static fn($v) => $v !== null && $v !== '' && $v !== []);

    $customData = array_filter([
        'currency'     => $customInput['currency']     ?? null,
        'value'        => isset($customInput['value'])     ? (float)$customInput['value']     : null,
        'content_name' => $customInput['content_name'] ?? null,
        'content_ids'  => $customInput['content_ids']  ?? null,
        'content_type' => $customInput['content_type'] ?? null,
        'contents'     => !empty($customInput['contents']) && is_array($customInput['contents']) ? $customInput['contents'] : null,
        'num_items'    => isset($customInput['num_items']) ? (int)$customInput['num_items'] : null,
        'order_id'     => $customInput['order_id']     ?? null,
    ], static fn($v) => $v !== null && $v !== '' && $v !== []);

    $eventId = (string)($input['event_id'] ?? uniqid('evt_', true));
    $event   = [
        'event_name'       => $eventName,
        'event_time'       => time(),
        'event_id'         => $eventId,
        'action_source'    => 'website',
        'event_source_url' => (string)($input['event_source_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '')),
        'user_data'        => $userData,
    ];
    if ($customData !== []) $event['custom_data'] = $customData;

    $payload = ['data' => [$event]];
    $testCode = trim((string)($input['test_event_code'] ?? $config['test_event_code'] ?? ''));
    if ($testCode !== '') $payload['test_event_code'] = $testCode;

    $url = sprintf(
        'https://graph.facebook.com/%s/%s/events?access_token=%s',
        rawurlencode($apiVersion),
        rawurlencode($pixelId),
        rawurlencode($accessToken)
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST          => true,
        CURLOPT_RETURNTRANSFER=> true,
        CURLOPT_HTTPHEADER    => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT       => 20,
        CURLOPT_CONNECTTIMEOUT=> 10,
    ]);
    applyCurlSslOptions($ch, $config);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    @curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => 'Falha ao comunicar com a Meta', 'details' => $curlError, 'http_code' => 502, 'event_id' => $eventId, 'event_name' => $eventName];
    }

    $decoded = json_decode($response, true);
    $ok      = $httpCode >= 200 && $httpCode < 300;

    return [
        'success'    => $ok,
        'event_id'   => $eventId,
        'event_name' => $eventName,
        'http_code'  => $httpCode,
        'meta'       => [
            'http_code'       => $httpCode,
            'events_received' => $decoded['events_received'] ?? null,
            'fbtrace_id'      => $decoded['fbtrace_id']      ?? null,
            'messages'        => $decoded['messages']         ?? null,
            'error'           => $decoded['error']            ?? null,
        ],
    ];
}
