<?php
declare(strict_types=1);

function set_flash(string $type, string $message)
{
    $payload = base64_encode(json_encode(['type' => $type, 'message' => $message]));
    setcookie('sl_notice', $payload, time() + 30, '/');
}

function consume_flash()
{
    $raw = $_COOKIE['sl_notice'] ?? '';
    if ($raw === '') {
        return null;
    }

    setcookie('sl_notice', '', time() - 3600, '/');
    $data = json_decode((string) base64_decode($raw, true), true);

    if (is_array($data) && isset($data['type'], $data['message'])) {
        return $data;
    }

    return null;
}
