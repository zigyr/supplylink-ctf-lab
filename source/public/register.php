<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (current_user() !== null) {
    header('Location: /dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $userId = create_user(
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['display_name'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        login_user(['id' => $userId, 'username' => trim((string) ($_POST['username'] ?? ''))]);
        set_flash('success', '注册成功，欢迎使用寰宇供应链协同平台。');
        header('Location: /dashboard.php');
        exit;
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
        header('Location: /register.php');
        exit;
    }
}

render_header('申请试用账号');
?>
<div class="login-wrap">
    <section class="intro-panel">
        <h2>账号申请</h2>
        <p class="intro-sub">供应商试用通道</p>
        <p class="intro-text">试用账号仅开放单据导入与归档查询功能。正式账号请与集团采购管理部联系开通，并签署《供应链协同平台使用协议》。</p>
    </section>
    <section class="card auth-card">
        <h1>申请试用账号</h1>
        <form method="post" class="stack-form">
            <label>
                <span>用户名</span>
                <input type="text" name="username" maxlength="32" required>
            </label>
            <label>
                <span>企业名称</span>
                <input type="text" name="display_name" maxlength="64" required>
            </label>
            <label>
                <span>密码</span>
                <input type="password" name="password" minlength="8" required>
            </label>
            <button type="submit">注册并进入工作台</button>
        </form>
        <p class="muted">已有账号？<a href="/login.php">返回登录</a></p>
    </section>
</div>
<?php
render_footer();
