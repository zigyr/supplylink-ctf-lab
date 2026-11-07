<?php
declare(strict_types=1);

function current_user()
{
    static $user = false;

    if ($user !== false) {
        return $user;
    }

    $token = (string) ($_COOKIE['sl_token'] ?? '');
    if ($token === '') {
        $user = null;
        return $user;
    }

    $claims = jwt_verify($token);
    if ($claims === null || !isset($claims['uid'])) {
        $user = null;
        return $user;
    }

    $stmt = db()->prepare('SELECT id, username, display_name, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $claims['uid']]);
    $record = $stmt->fetch();
    $user = $record ?: null;

    return $user;
}

function find_user_by_username(string $username)
{
    $stmt = db()->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $record = $stmt->fetch();

    return $record ?: null;
}

function create_user(string $username, string $displayName, string $password): int
{
    $username = trim($username);
    $displayName = trim($displayName);

    if (!preg_match('/\A[a-zA-Z0-9_]{3,32}\z/', $username)) {
        throw new InvalidArgumentException('用户名仅允许 3-32 位字母、数字和下划线。');
    }

    if ($displayName === '' || strlen($displayName) > 64) {
        throw new InvalidArgumentException('显示名称不能为空，且长度不能超过 64。');
    }

    if (strlen($password) < 8) {
        throw new InvalidArgumentException('密码长度至少为 8 位。');
    }

    if (find_user_by_username($username) !== null) {
        throw new InvalidArgumentException('用户名已存在。');
    }

    $stmt = db()->prepare(
        'INSERT INTO users (username, display_name, password_hash, created_at) VALUES (:username, :display_name, :password_hash, datetime(\'now\'))'
    );
    $stmt->execute([
        'username' => $username,
        'display_name' => $displayName,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    return (int) db()->lastInsertId();
}

function login_user(array $user)
{
    $token = jwt_issue([
        'uid' => (int) $user['id'],
        'sub' => (string) $user['username'],
        'role' => 'supplier',
    ]);
    setcookie('sl_token', $token, time() + 28800, '/', '', false, true);
}

function require_login()
{
    $user = current_user();
    if ($user === null) {
        set_flash('error', '请先登录后再继续。');
        header('Location: /login.php');
        exit;
    }

    return $user;
}

function logout_user()
{
    setcookie('sl_token', '', time() - 3600, '/');
}
