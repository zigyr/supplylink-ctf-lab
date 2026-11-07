<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$target = trim((string) ($_GET['url'] ?? ''));

if ($target === '') {
    render_header('合作伙伴跳转');
    ?>
    <section class="card">
        <h1>统一跳转出口</h1>
        <p>该入口用于兼容部分供应商的短链接和临时分发地址，跳转行为将被记录用于审计。</p>
    </section>
    <?php
    render_footer();
    exit;
}

$parts = parse_url($target);
if ($parts === false || !isset($parts['scheme']) || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
    http_response_code(400);
    echo 'invalid redirect target';
    exit;
}

header('Location: ' . $target, true, 302);
exit;
