<?php
error_reporting(E_ALL);
ini_set("display_errors", "1");

try {
    // Simula a requisição que o front-end está fazendo
    $json = file_get_contents("php://input");
    $dados = json_decode($json, true);
    
    if (!$dados) {
        echo json_encode([
            "erro" => "JSON inválido",
            "input" => $json,
            "json_error" => json_last_error_msg()
        ]);
        exit;
    }
    
    // Tenta incluir a API
    require_once __DIR__ . '/api-pix/api.php';
    
} catch (Throwable $e) {
    echo json_encode([
        "erro" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
        "trace" => $e->getTraceAsString()
    ]);
}
?>
