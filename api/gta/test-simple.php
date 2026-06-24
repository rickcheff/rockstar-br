<?php
file_put_contents(__DIR__ . '/test-output.txt', "PHP funcionando - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
echo "OK";
?>
