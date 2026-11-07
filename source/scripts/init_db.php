<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

$pdo = db();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(32) NOT NULL UNIQUE,
        display_name VARCHAR(64) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        storage_name VARCHAR(64) NOT NULL,
        source_url TEXT NOT NULL,
        content_type VARCHAR(128) NOT NULL,
        created_at DATETIME NOT NULL
    )'
);

$pdo->exec('CREATE INDEX IF NOT EXISTS idx_attachments_user ON attachments (user_id)');

$stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
$stmt->execute(['username' => 'supplier_demo']);
if (!$stmt->fetch()) {
    $insert = $pdo->prepare(
        'INSERT INTO users (username, display_name, password_hash, created_at) VALUES (:username, :display_name, :password_hash, datetime(\'now\'))'
    );
    $insert->execute([
        'username' => 'supplier_demo',
        'display_name' => '浣熊重工机械股份有限公司',
        'password_hash' => password_hash('Huanyu2026!', PASSWORD_DEFAULT),
    ]);
}

echo "database initialized\n";
