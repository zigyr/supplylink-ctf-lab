<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if (current_user() !== null) {
    header('Location: /dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = find_user_by_username($username);

    if ($user === null || !password_verify($password, $user['password_hash'])) {
        set_flash('error', '用户名或密码错误。');
        header('Location: /login.php');
        exit;
    }

    login_user($user);
    set_flash('success', '登录成功，欢迎回到工作台。');
    header('Location: /dashboard.php');
    exit;
}

render_header('供应商登录');
?>
<div class="login-wrap">
    <section class="intro-panel">
        <h2>寰宇科技集团</h2>
        <p class="intro-sub">供应链电子单据协同平台</p>
        <p class="intro-text">面向集团各事业部与授权供应商，提供采购发票、设备验收单、物流对账单等电子单据的在线提交、远程导入与归档查询服务。</p>
        <ul class="intro-list">
            <li>远程单据导入，自动归档入库</li>
            <li>覆盖能源装备、电气自动化、机械制造全业务线</li>
            <li>归档数据统一留存，支持事后审计追溯</li>
        </ul>
    </section>
    <section class="card auth-card">
        <h1>供应商登录</h1>
        <form method="post" class="stack-form">
            <label>
                <span>用户名</span>
                <input type="text" name="username" maxlength="32" required>
            </label>
            <label>
                <span>密码</span>
                <input type="password" name="password" minlength="8" required>
            </label>
            <button type="submit">登 录</button>
        </form>
        <p class="muted">尚未开通账号？<a href="/register.php">申请试用账号</a></p>
    </section>
</div>
<!-- 联调账号 supplier_demo / Huanyu2026! ，正式上线前务必删除 - 信息化部 周工 -->
<?php
render_footer();
