<?php
// Proxy local e robusto para consulta de CEP.
// Retorna sempre JSON para o checkout preencher endereço automaticamente.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$cep = preg_replace('/\D+/', '', $_GET['cep'] ?? '');

function json_out($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strlen($cep) !== 8) {
    json_out(['erro' => true, 'message' => 'CEP inválido']);
}

function get_json_url($url) {
    // 1) cURL, com SSL flexível para evitar erro comum de certificado em hospedagem compartilhada.
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Mozilla/5.0 br2026-checkout-cep/2.0'
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code >= 200 && $code < 300) {
            $json = json_decode($body, true);
            if (is_array($json)) return $json;
        }
    }

    // 2) file_get_contents como fallback.
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: Mozilla/5.0 br2026-checkout-cep/2.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body !== false) {
        $json = json_decode($body, true);
        if (is_array($json)) return $json;
    }

    return null;
}

// ViaCEP
$data = get_json_url("https://viacep.com.br/ws/{$cep}/json/");
if (is_array($data) && empty($data['erro'])) {
    json_out([
        'erro' => false,
        'cep' => $data['cep'] ?? $cep,
        'logradouro' => $data['logradouro'] ?? '',
        'bairro' => $data['bairro'] ?? '',
        'localidade' => $data['localidade'] ?? '',
        'cidade' => $data['localidade'] ?? '',
        'uf' => $data['uf'] ?? '',
    ]);
}

// BrasilAPI
$data = get_json_url("https://brasilapi.com.br/api/cep/v1/{$cep}");
if (is_array($data) && empty($data['error']) && !empty($data['state'])) {
    json_out([
        'erro' => false,
        'cep' => $data['cep'] ?? $cep,
        'logradouro' => $data['street'] ?? '',
        'bairro' => $data['neighborhood'] ?? '',
        'localidade' => $data['city'] ?? '',
        'cidade' => $data['city'] ?? '',
        'uf' => $data['state'] ?? '',
    ]);
}

// OpenCEP como terceira opção
$data = get_json_url("https://opencep.com/v1/{$cep}.json");
if (is_array($data) && empty($data['erro']) && (!empty($data['uf']) || !empty($data['localidade']))) {
    json_out([
        'erro' => false,
        'cep' => $data['cep'] ?? $cep,
        'logradouro' => $data['logradouro'] ?? '',
        'bairro' => $data['bairro'] ?? '',
        'localidade' => $data['localidade'] ?? '',
        'cidade' => $data['localidade'] ?? '',
        'uf' => $data['uf'] ?? '',
    ]);
}

json_out([
    'erro' => true,
    'message' => 'Não foi possível consultar o CEP agora. Preencha o endereço manualmente.',
    'cep' => $cep,
]);
