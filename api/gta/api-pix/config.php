<?php
// =====================================================
// config.php — Mangofy PIX / Grand Theft Auto VI
// =====================================================
if (!defined('MANGOFY_API_KEY')) {
    define('MANGOFY_API_KEY',    '2ea9165b40a4f435f009781c6a7fadbb6l25bkxxofhdtnrktuwwi3dvezvb8vb');
    define('MANGOFY_STORE_CODE', '5f62d3e992c0dd344ecda2d93a6975ff');
    define('MANGOFY_API_URL',    'https://checkout.mangofy.com.br/api/v1/payment');

    // Valor padrão em centavos caso o front não envie value.
    define('TAXA_VALOR',         41500); // R$415,00
    define('TAXA_DESCRICAO',     'Grand Theft Auto VI');
}
