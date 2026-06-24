<?php
session_start();

// Recebe os dados via POST e salva na sessão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dados'])) {
    $dados = json_decode($_POST['dados'], true);
    if ($dados && isset($dados['cpf'])) {
        $_SESSION['dados_inscricao'] = $dados;
        echo json_encode(['status' => 'ok']);
        exit;
    }
}
// Se não for POST ou dados inválidos
http_response_code(400);
echo json_encode(['status' => 'erro', 'msg' => 'Dados inválidos']);
exit;
