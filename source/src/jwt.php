<?php
declare(strict_types=1);

function jwt_b64_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jwt_b64_decode(string $data)
{
    return base64_decode(strtr($data, '-_', '+/'), true);
}

function jwt_signing_key(): string
{
    static $key = null;

    if ($key === null) {
        $key = trim((string) @file_get_contents(jwt_key_file()));
    }

    return $key;
}

function jwt_issue(array $claims, int $ttl = 28800): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now = time();
    $claims['iat'] = $now;
    $claims['exp'] = $now + $ttl;

    $head = jwt_b64_encode(json_encode($header));
    $body = jwt_b64_encode(json_encode($claims));
    $signature = jwt_b64_encode(hash_hmac('sha256', $head . '.' . $body, jwt_signing_key(), true));

    return $head . '.' . $body . '.' . $signature;
}

function jwt_verify(string $token)
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    list($head, $body, $signature) = $parts;

    $header = json_decode((string) jwt_b64_decode($head), true);
    if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== 'JWT') {
        return null;
    }

    $expected = hash_hmac('sha256', $head . '.' . $body, jwt_signing_key(), true);
    $given = jwt_b64_decode($signature);
    if ($given === false || !hash_equals($expected, $given)) {
        return null;
    }

    $claims = json_decode((string) jwt_b64_decode($body), true);
    if (!is_array($claims)) {
        return null;
    }

    if (!isset($claims['exp']) || time() >= (int) $claims['exp']) {
        return null;
    }

    return $claims;
}
