<?php
// Compatibilidade com checkout antigo: verificar.php?id=... ou ?orderId=...
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$paymentCode = trim($_GET['code'] ?? $_GET['id'] ?? $_GET['orderId'] ?? '');
if ($paymentCode === '') {
    http_response_code(400);
    echo json_encode(['success'=>false, 'error'=>'Código não informado.']);
    exit;
}
$paymentCode = preg_replace('/[^a-zA-Z0-9_-]/', '', $paymentCode);
$url = 'https://checkout.mangofy.com.br/api/v1/payment/' . urlencode($paymentCode);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: ' . MANGOFY_API_KEY,
        'Store-Code: '    . MANGOFY_STORE_CODE,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$resposta   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErro   = curl_error($ch);
curl_close($ch);
if ($curlErro) {
    http_response_code(502);
    echo json_encode(['success'=>false, 'error'=>'Falha de conexão.']);
    exit;
}
$resultado = json_decode($resposta, true);
$r = $resultado['data'] ?? $resultado;
$status = is_array($r) ? ($r['payment_status'] ?? $r['status'] ?? 'unknown') : 'unknown';
http_response_code($httpStatus >= 400 ? $httpStatus : 200);
echo json_encode([
    'success' => $httpStatus < 400,
    'data' => [
        'id' => $paymentCode,
        'transactionId' => $paymentCode,
        'status' => $status,
        'raw' => $r,
    ],
], JSON_UNESCAPED_UNICODE);
