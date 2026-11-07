<?php
// 完全复刻 import_service.php 的 fetch_remote_document 行为，用于复现用户报错
$url = $argv[1];
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 5,
        'follow_location' => 1,
        'max_redirects' => 3,
        'ignore_errors' => true,
        'user_agent' => 'SupplyLink-Archive/2.1',
    ],
]);
set_error_handler(function ($s, $m) { echo "  [warn] $m\n"; return true; });
$body = file_get_contents($url, false, $context);
restore_error_handler();
if ($body === false) {
    echo "FETCH FAILED\n";
} else {
    echo "FETCH OK, body length = " . strlen($body) . "\n";
    echo "--- body head ---\n" . substr($body, 0, 300) . "\n";
}
