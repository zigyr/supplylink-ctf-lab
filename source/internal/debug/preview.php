<?php
declare(strict_types=1);

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIp, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'archive preview is restricted to the maintenance console';
    exit;
}

$file = trim((string) ($_GET['file'] ?? ''));
if ($file === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'missing file parameter';
    exit;
}

$baseDir = '/var/cache/archive/';
$sanitized = str_replace('../', '', $file);
$target = $baseDir . ltrim($sanitized, '/');

if (!is_file($target)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'preview target not found';
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
echo "----- archive inspection report -----\n";
echo 'generated at ' . date('Y-m-d H:i:s') . "\n";
echo "-------------------------------------\n";
include $target;
echo "\n---------------- end ----------------\n";
