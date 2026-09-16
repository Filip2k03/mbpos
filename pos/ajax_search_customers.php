<?php
// Compatibility tombstone: customer lookup was retired from the V5 POS.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
http_response_code(410);

echo json_encode([
    'results' => [],
    'error' => 'Customer lookup is not available in POS V5.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit();
