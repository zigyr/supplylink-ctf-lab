<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$attachmentId = (int) ($_GET['attachment_id'] ?? 0);
$attachment = attachment_by_id_for_user($attachmentId, (int) $user['id']);

if ($attachment === null) {
    http_response_code(404);
    render_header('文件不存在', $user);
    ?>
    <section class="card">
        <h1>文件不存在</h1>
        <p>当前账号无权访问该归档文件。</p>
    </section>
    <?php
    render_footer();
    exit;
}

$filePath = storage_dir() . '/' . $attachment['storage_name'];
if (!is_file($filePath)) {
    http_response_code(410);
    render_header('文件已失效', $user);
    ?>
    <section class="card">
        <h1>文件已失效</h1>
        <p>归档记录存在，但底层文件已经丢失。</p>
    </section>
    <?php
    render_footer();
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: attachment; filename="' . rawurlencode((string) $attachment['original_name']) . '"');
readfile($filePath);
exit;
