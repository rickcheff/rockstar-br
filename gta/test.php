<?php
echo json_encode([
    'status' => 'ok',
    'php_version' => phpversion(),
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
