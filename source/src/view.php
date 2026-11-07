<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render_header(string $title, $user = null)
{
    $flash = consume_flash();
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - 寰宇供应链协同平台</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <img src="/assets/logo.svg" alt="寰宇科技" class="brand-logo">
            <div>
                <strong>寰宇供应链协同平台</strong>
                <p>SupplyLink · 寰宇科技集团</p>
            </div>
        </div>
        <nav class="nav">
            <?php if ($user !== null): ?>
                <a href="/dashboard.php">工作台</a>
                <a href="/logout.php">退出</a>
            <?php else: ?>
                <a href="/login.php">登录</a>
                <a href="/register.php">注册</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="page">
        <?php if ($flash !== null): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
    <?php
}

function render_footer()
{
    ?>
    </main>
    <footer class="footer">
        <p>© 2026 寰宇科技集团 信息化部 · 智能驱动，绿色高效 · 仅限授权供应商及集团员工使用</p>
    </footer>
</body>
</html>
    <?php
}
