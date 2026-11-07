<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard.php');
    exit;
}

$sourceUrl = trim((string) ($_POST['source_url'] ?? ''));

try {
    $attachmentId = create_imported_attachment((int) $user['id'], $sourceUrl);
    set_flash('success', '导入成功，归档编号 #' . $attachmentId . ' 已生成。');
} catch (Throwable $exception) {
    set_flash('error', $exception->getMessage());
}

header('Location: /dashboard.php');
exit;
