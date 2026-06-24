<?php
// =====================================================
// api.php — cria pagamento PIX via Mangofy
// =====================================================
require_once __DIR__ . '/config.php';

function way_log(string $ctx, $dados): void {
    $f = __DIR__ . '/pagamento_debug.log';
    $l = '[' . date('Y-m-d H:i:s') . '] [' . $ctx . '] ' . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n";
    @file_put_contents($f, $l, FILE_APPEND | LOCK_EX);
}

function only_digits($v): string {
    return preg_replace('/[^0-9]/', '', (string)$v);
}

function parse_amount_to_cents($value): int {
    if ($value === null || $value === '') return (int) TAXA_VALOR;
    if (is_numeric($value)) {
        return (int) round(((float)$value) * 100);
    }
    $v = trim((string)$value);
    $v = str_replace(['R$', '€', ' '], '', $v);
    if (strpos($v, ',') !== false && strpos($v, '.') === false) {
        $v = str_replace(',', '.', $v);
    } elseif (strpos($v, ',') !== false) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return (int) round(((float)$v) * 100);
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false, 'erro'=>'Método não permitido.']);
    exit;
}

$body  = file_get_contents('php://input');
$dados = json_decode($body, true);

way_log('PIX - ENTRADA BRUTA', ['body' => $body, 'parsed' => $dados, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'json_error' => json_last_error_msg()]);

if (!is_array($dados)) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'erro'=>'Dados inválidos. Body: ' . substr($body, 0, 100)]);
    exit;
}

$nome     = trim($dados['nome'] ?? $dados['name'] ?? $dados['payerName'] ?? '');
$email    = trim($dados['email'] ?? '');
$cpf      = only_digits($dados['cpf'] ?? $dados['document'] ?? '');
$method   = strtolower($dados['method'] ?? 'pix');
$currency = strtoupper($dados['currency'] ?? 'BRL');

$erros = [];
if ($nome === '')                                     $erros[] = 'Nome é obrigatório.';
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
if (strlen($cpf) !== 11) $erros[] = 'CPF inválido (deve ter 11 dígitos).';

$valorCentimos = parse_amount_to_cents($dados['value'] ?? $dados['valor'] ?? null);
if ($valorCentimos < 50) $erros[] = 'Valor inválido.';

if (!empty($erros)) {
    way_log('PIX - VALIDAÇÃO FALHOU', ['erros' => $erros]);
    http_response_code(422);
    echo json_encode(['success'=>false, 'erro'=>implode(' ', $erros)]);
    exit;
}

// PIX recebe valor em BRL (decimal)
$amountBrl = round($valorCentimos / 100, 2);

$itemName = TAXA_DESCRICAO;
$itemAmount = $amountBrl;
$items = is_array($dados['items'] ?? null) ? $dados['items'] : [];
if (!empty($items[0]['name'])) $itemName = (string)$items[0]['name'];

// Gerar ID único da transação
$externalCode = 'GTA' . time() . rand(100000, 999999);

// Preparar items para Mangofy
$pixItems = [];
if (!empty($items)) {
    foreach ($items as $item) {
        $pixItems[] = [
            'name'     => (string)($item['name'] ?? $itemName),
            'quantity' => (int)($item['quantity'] ?? 1),
            'price'    => (float)($item['amount'] ?? ($itemAmount / (count($items)))),
        ];
    }
} else {
    $pixItems[] = [
        'name'     => $itemName,
        'quantity' => 1,
        'price'    => $itemAmount,
    ];
}

// Telefone formatado (apenas dígitos)
$telefoneLimpo = only_digits($dados['phone'] ?? $dados['telefone'] ?? '11999999999');
if (strlen($telefoneLimpo) < 10) $telefoneLimpo = '11' . $telefoneLimpo;
if (strlen($telefoneLimpo) > 11) $telefoneLimpo = substr($telefoneLimpo, -11);

$payload = [
    'store_code'       => MANGOFY_STORE_CODE,
    'payment_method'   => 'pix',
    'payment_format'   => 'regular',
    'external_code'    => $externalCode,
    'installments'     => 1,
    'postback_url'     => 'http://127.0.0.1:3500/gta/api-pix/postback.php',
    'payment_amount'   => (int)$valorCentimos,
    'shipping_amount'  => 0,
    'items'            => $pixItems,
    'customer'         => [
        'name'     => $nome,
        'email'    => $email,
        'document' => $cpf,
        'phone'    => $telefoneLimpo,
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'type'     => 'individual',
    ],
    'pix'              => [
        'key_type'       => 'cpf',
        'key_value'      => $cpf,
        'expires_in_days' => 1,
    ],
];

way_log('PIX - PAYLOAD MANGOFY', $payload);

$ch = curl_init(MANGOFY_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Authorization: ' . MANGOFY_API_KEY,
        'Store-Code: ' . MANGOFY_STORE_CODE,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$resposta   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErro   = curl_error($ch);
curl_close($ch);

$data = json_decode($resposta, true);
way_log('PIX - RESPOSTA MANGOFY', ['http' => $httpStatus, 'curl_err' => $curlErro ?: null, 'body' => $data]);

if ($curlErro) {
    http_response_code(502);
    echo json_encode(['success'=>false, 'erro'=>'Falha ao conectar com o gateway de pagamento.']);
    exit;
}

// Extrair dados da resposta Mangofy
$paymentData = $data['data'] ?? $data;
$txId = $paymentData['payment_code'] ?? $paymentData['id'] ?? $paymentData['payment_id'] ?? null;
$pixCode = $paymentData['pix']['pix_qrcode_text'] ?? $paymentData['qr_code'] ?? $paymentData['pix_code'] ?? null;

if (!$txId) {
    $msg = $data['message'] ?? $data['error'] ?? 'Erro ao processar pagamento (HTTP ' . $httpStatus . ').';
    http_response_code(502);
    echo json_encode(['success'=>false, 'erro'=>$msg]);
    exit;
}

$saida = [
    'success'      => true,
    'sucesso'      => true,
    'payment_code' => $txId,
    'status'       => 'pending',
    'valor'        => $valorCentimos,
    'data'         => [
        'id'            => $txId,
        'transactionId' => $txId,
        'valor'         => number_format($amountBrl, 2, '.', ''),
        'status'        => 'pending',
        'pix_code'      => $pixCode,
    ],
];

way_log('PIX - SAÍDA FRONT', $saida);
echo json_encode($saida, JSON_UNESCAPED_UNICODE);
