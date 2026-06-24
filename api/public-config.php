<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$metaFile   = __DIR__ . '/../config/meta.local.php';
$utmifyFile = __DIR__ . '/../config/utmify.local.php';

$meta   = is_file($metaFile)   ? include $metaFile   : [];
$utmify = is_file($utmifyFile) ? include $utmifyFile : [];

if (!is_array($meta))   $meta   = [];
if (!is_array($utmify)) $utmify = [];

echo json_encode([
    'meta_pixel_id'   => (string)($meta['pixel_id']   ?? '1295854422207338'),
    'utmify_pixel_id' => (string)($utmify['pixel_id'] ?? '6a02277fb4d3d1b2ba254acf'),
], JSON_UNESCAPED_UNICODE);
