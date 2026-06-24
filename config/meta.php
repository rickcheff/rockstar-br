<?php

function loadMetaConfig(): array
{
    $file   = __DIR__ . '/meta.local.php';
    $config = is_file($file) ? include $file : [];
    if (!is_array($config)) $config = [];

    return array_merge([
        'pixel_id'               => '1295854422207338',
        'access_token'           => '',
        'api_version'            => 'v21.0',
        'default_phone_country'  => '351',
        'ssl_verify'             => true,
        'test_event_code'        => '',
    ], $config);
}
