<?php
declare(strict_types=1);

function normalize_import_host(string $host): string
{
    return rtrim(strtolower($host), '.');
}

function blocked_ipv4_ranges(): array
{
    return [
        '0.0.0.0/8',
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16',
        '100.64.0.0/10',
    ];
}

function blocked_ipv6_ranges(): array
{
    return [
        '::1/128',
        'fc00::/7',
        'fe80::/10',
    ];
}

function prefix_contains_ip(string $ip, string $prefix): bool
{
    list($network, $bits) = explode('/', $prefix, 2);
    $ipBinary = @inet_pton($ip);
    $networkBinary = @inet_pton($network);

    if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
        return false;
    }

    $fullBytes = intdiv((int) $bits, 8);
    $remainingBits = (int) $bits % 8;

    if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($networkBinary, 0, $fullBytes)) {
        return false;
    }

    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    $ipByte = ord($ipBinary[$fullBytes]);
    $networkByte = ord($networkBinary[$fullBytes]);

    return ($ipByte & $mask) === ($networkByte & $mask);
}

function is_blocked_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        foreach (blocked_ipv4_ranges() as $range) {
            if (prefix_contains_ip($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        foreach (blocked_ipv6_ranges() as $range) {
            if (prefix_contains_ip($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    throw new RuntimeException('invalid resolved IP: ' . $ip);
}

function resolve_import_host_addresses(string $host): array
{
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return [$host];
    }

    $resolved = [];
    $records = @dns_get_record($host, DNS_A + DNS_AAAA);

    if (is_array($records)) {
        foreach ($records as $record) {
            if (isset($record['ip']) && $record['ip'] !== '') {
                $resolved[] = $record['ip'];
            }

            if (isset($record['ipv6']) && $record['ipv6'] !== '') {
                $resolved[] = $record['ipv6'];
            }
        }
    }

    return array_values(array_unique($resolved));
}

function validate_import_port(array $parts, string $scheme)
{
    if (!isset($parts['port'])) {
        return null;
    }

    $port = (int) $parts['port'];
    $allowedPorts = $scheme === 'https' ? [443] : [80, 8080];

    if (!in_array($port, $allowedPorts, true)) {
        return 'Only standard web ports are allowed';
    }

    return null;
}

function validate_import_url(string $url)
{
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return '请输入合法的导入链接。';
    }

    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '仅支持 http / https 协议。';
    }

    $portError = validate_import_port($parts, $scheme);
    if ($portError !== null) {
        return $portError;
    }

    $host = normalize_import_host((string) $parts['host']);
    if ($host === '') {
        return 'missing host';
    }

    if (strcasecmp($host, 'localhost') === 0) {
        return 'blocked host: localhost';
    }

    // DNS is resolved during validation time only.
    $resolvedAddresses = resolve_import_host_addresses($host);
    if ($resolvedAddresses === []) {
        return '域名解析失败，请确认链接可被外部网络访问。';
    }

    try {
        foreach ($resolvedAddresses as $resolvedAddress) {
            if (is_blocked_ip($resolvedAddress)) {
                return '为保障集团内网安全，禁止导入内网地址: ' . $resolvedAddress;
            }
        }
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }

    return null;
}

function fetch_remote_document(string $url): array
{
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

    $lastError = null;
    set_error_handler(function ($severity, $message) use (&$lastError) {
        $lastError = $message;
        return true;
    });

    $body = file_get_contents($url, false, $context);

    restore_error_handler();

    if ($body === false) {
        throw new RuntimeException('远程导入失败: ' . ($lastError ?? 'unknown error'));
    }

    $headers = $http_response_header ?? [];
    $contentType = 'text/plain; charset=UTF-8';
    foreach ($headers as $headerLine) {
        if (stripos($headerLine, 'Content-Type:') === 0) {
            $contentType = trim(substr($headerLine, strlen('Content-Type:')));
            break;
        }
    }

    return [
        'body' => $body,
        'content_type' => $contentType,
    ];
}

function derive_original_name(string $sourceUrl): string
{
    $path = (string) parse_url($sourceUrl, PHP_URL_PATH);
    $filename = basename($path);
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename);

    if ($filename === null || $filename === '' || $filename === '.' || $filename === '..') {
        return 'imported-document.txt';
    }

    return $filename;
}

function create_imported_attachment(int $userId, string $sourceUrl): int
{
    $error = validate_import_url($sourceUrl);
    if ($error !== null) {
        throw new InvalidArgumentException($error);
    }

    $result = fetch_remote_document($sourceUrl);

    if ($result['body'] === '') {
        throw new RuntimeException('导入内容为空，已拒绝归档。');
    }

    $storageName = bin2hex(random_bytes(12)) . '.txt';
    $storagePath = storage_dir() . '/' . $storageName;
    $originalName = derive_original_name($sourceUrl);

    if (!is_dir(storage_dir()) && !mkdir(storage_dir(), 0775, true) && !is_dir(storage_dir())) {
        throw new RuntimeException('无法创建归档存储目录。');
    }

    if (file_put_contents($storagePath, $result['body']) === false) {
        throw new RuntimeException('无法写入归档存储。');
    }

    $stmt = db()->prepare(
        'INSERT INTO attachments (user_id, original_name, storage_name, source_url, content_type, created_at)
         VALUES (:user_id, :original_name, :storage_name, :source_url, :content_type, datetime(\'now\'))'
    );
    $stmt->execute([
        'user_id' => $userId,
        'original_name' => $originalName,
        'storage_name' => $storageName,
        'source_url' => $sourceUrl,
        'content_type' => $result['content_type'],
    ]);

    return (int) db()->lastInsertId();
}

function list_attachments_for_user(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT id, original_name, storage_name, source_url, content_type, created_at
         FROM attachments
         WHERE user_id = :user_id
         ORDER BY id DESC'
    );
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function attachment_by_id_for_user(int $attachmentId, int $userId)
{
    $stmt = db()->prepare(
        'SELECT id, user_id, original_name, storage_name, source_url, content_type, created_at
         FROM attachments
         WHERE id = :id AND user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $attachmentId,
        'user_id' => $userId,
    ]);

    $record = $stmt->fetch();
    return $record ?: null;
}
