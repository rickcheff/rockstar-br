<?php

function applyCurlSslOptions($ch, array $config): void
{
    $sslVerify = isset($config['ssl_verify']) ? (bool)$config['ssl_verify'] : true;
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify ? 1 : 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);
}
