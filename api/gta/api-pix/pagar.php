<?php
// =====================================================
// api.php — gera PIX via Mangofy com compatibilidade para o checkout atual
// Aceita payload antigo: name, email, cpf, phone, value, items
// Também aceita payload novo: nome, email, cpf, telefone
// Retorna nos dois formatos: success/data e sucesso/pix_copia_cola
// =====================================================
require_once __DIR__ . '/config.php';

function pix_log_debug(string $ctx, $dados): void {
    $f = __DIR__ . '/pagamento_debug.log';
    $l = '[' . date('Y-m-d H:i:s') . '] [' . $ctx . '] ' . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n";
    @file_put_contents($f, $l, FILE_APPEND | LOCK_EX);
}

function only_digits($v): string {
    return preg_replace('/\D/', '', (string)$v);
}

function parse_amount_to_cents($value): int {
    if ($value === null || $value === '') return (int) TAXA_VALOR;
    if (is_numeric($value)) {
        return (int) round(((float)$value) * 100);
    }
    $v = trim((string)$value);
    $v = str_replace(['R$', ' '], '', $v);
    // pt-BR: 1.234,56 -> 1234.56
    if (strpos($v, ',') !== false) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return (int) round(((float)$v) * 100);
}


function build_postback_url(array $dados): string {
    $url = trim((string)($dados['postback_url'] ?? $dados['postbackUrl'] ?? $dados['webhook_url'] ?? ''));
    if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) return $url;

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $host = preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', (string)$host);
    if ($host !== '' && !preg_match('/^(localhost|127\.0\.0\.1|0\.0\.0\.0)(:\d+)?$/', $host)) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
              || (($_SERVER['SERVER_PORT'] ?? '') == '443');
        $scheme = $https ? 'https' : 'http';
        return $scheme . '://' . $host . '/api-pix/postback.php';
    }

    // Fallback usado apenas para impedir erro do gateway em ambientes sem domínio detectável.
    return 'https://seusite.com.br/api-pix/postback.php';
}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false, 'erro'=>'Método não permitido.', 'error'=>'Método não permitido.']);
    exit;
}

$body  = file_get_contents('php://input');
$dados = json_decode($body, true);

pix_log_debug('PIX - ENTRADA', [
    'raw_body'   => $body,
    'parsed'     => $dados,
    'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
]);

if (!is_array($dados)) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'erro'=>'Dados inválidos.', 'error'=>'Dados inválidos.']);
    exit;
}

$nome     = trim($dados['nome'] ?? $dados['name'] ?? '');
$email    = trim($dados['email'] ?? '');
$cpf      = only_digits($dados['cpf'] ?? $dados['document'] ?? '');
$telefone = only_digits($dados['telefone'] ?? $dados['phone'] ?? '');

if (!$email && $cpf) {
    $email = 'lead+' . $cpf . '@pix.local';
}

$erros = [];
if ($nome === '')                               $erros[] = 'Nome é obrigatório.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
if (strlen($cpf) !== 11)                        $erros[] = 'CPF inválido.';
if (strlen($telefone) < 10 || strlen($telefone) > 11) $erros[] = 'Telefone inválido.';

$valorCentavos = parse_amount_to_cents($dados['value'] ?? $dados['valor'] ?? null);
if ($valorCentavos < 100) $erros[] = 'Valor inválido.';

if (!empty($erros)) {
    pix_log_debug('PIX - VALIDAÇÃO FALHOU', ['erros' => $erros, 'dados_recebidos' => ['nome'=>$nome,'email'=>$email,'cpf_len'=>strlen($cpf),'telefone_len'=>strlen($telefone),'valor'=>$valorCentavos]]);
    http_response_code(422);
    echo json_encode(['success'=>false, 'erro'=>implode(' ', $erros), 'error'=>implode(' ', $erros)]);
    exit;
}

$itemsInput = is_array($dados['items'] ?? null) ? $dados['items'] : [];
$itemName = TAXA_DESCRICAO;
if (!empty($itemsInput) && !empty($itemsInput[0]['name'])) {
    $itemName = (string)$itemsInput[0]['name'];
}

$externalCode = 'BR2026-' . strtoupper(substr(md5($email . microtime(true)), 0, 12));

$payload = [
    'store_code'      => MANGOFY_STORE_CODE,
    'payment_method'  => 'pix',
    'payment_format'  => 'regular',
    'external_code'   => $externalCode,
    'installments'    => 1,
    'postback_url'    => build_postback_url($dados),
    'payment_amount'  => $valorCentavos,
    'shipping_amount' => 0,
    'items'           => [[
        'name'     => $itemName ?: TAXA_DESCRICAO,
        'quantity' => 1,
        'amount'   => $valorCentavos,
    ]],
    'customer' => [
        'name'     => $nome,
        'email'    => $email,
        'document' => $cpf,
        'phone'    => $telefone,
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    ],
    'pix' => ['expires_in_days' => 1],
];

pix_log_debug('PIX - PAYLOAD MANGOFY', $payload);

$ch = curl_init(MANGOFY_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Authorization: ' . MANGOFY_API_KEY,
        'Store-Code: '    . MANGOFY_STORE_CODE,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$resposta   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErro   = curl_error($ch);
$curlInfo   = curl_getinfo($ch);
curl_close($ch);

$parsed = json_decode($resposta, true);
pix_log_debug('PIX - RESPOSTA MANGOFY', [
    'http_status'    => $httpStatus,
    'curl_erro'      => $curlErro ?: null,
    'tempo_total_s'  => $curlInfo['total_time'] ?? null,
    'resposta_raw'   => $resposta,
    'resposta_parse' => $parsed,
]);

if ($curlErro) {
    http_response_code(502);
    echo json_encode(['success'=>false, 'erro'=>'Falha ao conectar com o gateway.', 'error'=>'Falha ao conectar com o gateway.']);
    exit;
}

$r = $parsed;
if (isset($r['data']) && is_array($r['data'])) $r = $r['data'];

if ($httpStatus >= 400 || !is_array($r)) {
    $msg = is_array($r) ? ($r['message'] ?? $r['error'] ?? 'Erro no gateway (HTTP ' . $httpStatus . ').') : 'Erro no gateway (HTTP ' . $httpStatus . ').';
    http_response_code(502);
    echo json_encode(['success'=>false, 'erro'=>$msg, 'error'=>$msg]);
    exit;
}

$pixBlock = $r['pix'] ?? $r;
$qrCodeBase64 = $pixBlock['qr_code_base64'] ?? $pixBlock['base64'] ?? $pixBlock['image'] ?? null;
$qrCodeUrl    = $pixBlock['qr_code_url'] ?? $pixBlock['url'] ?? $pixBlock['qrcode_url'] ?? null;
$copiaCola    = $pixBlock['pix_qrcode_text'] ?? $pixBlock['qr_code'] ?? $pixBlock['copy_paste']
              ?? $pixBlock['copia_e_cola'] ?? $pixBlock['pix_code'] ?? $pixBlock['emv']
              ?? $pixBlock['payload'] ?? null;

if (!$copiaCola) {
    array_walk_recursive($r, function($v, $k) use (&$copiaCola) {
        if (!$copiaCola && is_string($v) && strlen($v) > 50 && strpos($v, '000201') === 0) {
            $copiaCola = $v;
        }
    });
}

$qrImage = $qrCodeUrl ?: '';
if (!$qrImage && $qrCodeBase64) {
    $qrImage = (strpos($qrCodeBase64, 'data:image') === 0) ? $qrCodeBase64 : 'data:image/png;base64,' . $qrCodeBase64;
}
if (!$qrImage && $copiaCola) {
    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($copiaCola);
}

if (!$copiaCola) {
    http_response_code(502);
    echo json_encode(['success'=>false, 'erro'=>'PIX gerado sem código copia e cola.', 'error'=>'PIX gerado sem código copia e cola.']);
    exit;
}

$paymentCode = $r['payment_code'] ?? $r['id'] ?? $externalCode;
$status = $r['payment_status'] ?? $r['status'] ?? 'PENDING';
$valorReais = number_format($valorCentavos / 100, 2, '.', '');

$saida = [
    'sucesso'        => true,
    'success'        => true,
    'payment_code'   => $paymentCode,
    'status'         => $status,
    'valor'          => $valorCentavos,
    'pix_copia_cola' => $copiaCola,
    'pix_qrcode_b64' => $qrCodeBase64,
    'pix_qrcode_url' => $qrCodeUrl,
    'data' => [
        'id'            => $paymentCode,
        'copiaecola'    => $copiaCola,
        'qrcode_image'  => $qrImage,
        'transactionId' => $paymentCode,
        'valor'         => $valorReais,
        'status'        => $status,
        'expires_in'    => 1800,
    ],
];

pix_log_debug('PIX - SAÍDA FRONT', $saida);
echo json_encode($saida, JSON_UNESCAPED_UNICODE);
