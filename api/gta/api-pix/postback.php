<?php
// Webhook/postback do gateway PIX.
// Mantém o endpoint válido para a Mangofy e registra qualquer retorno recebido.
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$log = [
    'received_at' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'headers' => function_exists('getallheaders') ? getallheaders() : [],
    'raw_body' => $raw,
    'payload' => $payload,
];
@file_put_contents(__DIR__ . '/postback_debug.log', json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true, 'received' => true], JSON_UNESCAPED_UNICODE);
