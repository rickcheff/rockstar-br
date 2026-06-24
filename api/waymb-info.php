<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['id'])) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }

$ch = curl_init('https://api.waymb.com/transactions/info');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['id' => $input['id']]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$body   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
@curl_close($ch);

if ($err) { http_response_code(502); echo json_encode(['error' => $err]); exit; }
http_response_code($status);
echo $body;
