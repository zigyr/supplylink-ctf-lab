<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$user = require_login();
$attachments = list_attachments_for_user((int) $user['id']);

render_header('工作台', $user);
?>
<section class="grid">
    <article class="card">
        <div class="card-head">
            <div>
                <p class="eyebrow">单据导入</p>
                <h1><?= e($user['display_name']) ?></h1>
            </div>
            <span class="pill">@<?= e($user['username']) ?></span>
        </div>
        <p>供应商可将托管在自有服务器上的单据文件地址提交至平台，系统将自动抓取并归档入库。</p>
        <form method="post" action="/import.php" class="stack-form">
            <label>
                <span>单据 URL</span>
                <input type="url" name="source_url" placeholder="http://<?= e(import_rebind_host()) ?>/invoice.xml" required>
            </label>
            <button type="submit">导入并归档</button>
        </form>
        <div class="note">
            <strong>导入说明</strong>
            <p>为保障集团内网安全，平台会校验导入地址并拒绝内网 IP。请提交供应商侧可正常访问的公网域名，联调时切勿将动态解析域名手工改为固定 IP。</p>
        </div>
    </article>

    <article class="card">
        <div class="card-head">
            <div>
                <p class="eyebrow">运维提示</p>
                <h2>常见问题</h2>
            </div>
        </div>
        <ul class="tips">
            <li>部分供应商单据链接会 302 跳转到分发节点，属正常现象，平台会自动跟随。</li>
            <li>归档预览服务仅供运维在本机调试失败单据时使用，未对供应商开放。</li>
            <li>若导入失败，请先确认链接仍可被外部网络正常解析和访问。</li>
        </ul>
    </article>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <p class="eyebrow">归档记录</p>
            <h2>我的归档单据</h2>
        </div>
        <span class="pill"><?= count($attachments) ?> 份</span>
    </div>

    <?php if ($attachments === []): ?>
        <p class="muted">暂无归档记录，可先提交一个单据链接测试。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>编号</th>
                    <th>原始文件名</th>
                    <th>存储编号</th>
                    <th>来源 URL</th>
                    <th>归档时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attachments as $attachment): ?>
                    <tr>
                        <td>#<?= (int) $attachment['id'] ?></td>
                        <td><?= e($attachment['original_name']) ?></td>
                        <td><?= e($attachment['storage_name']) ?></td>
                        <td class="source-cell"><?= e($attachment['source_url']) ?></td>
                        <td><?= e($attachment['created_at']) ?></td>
                        <td><a href="/download.php?attachment_id=<?= (int) $attachment['id'] ?>">下载</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <p class="eyebrow">合作伙伴</p>
            <h2>入库供应商快速入口</h2>
        </div>
    </div>
    <p class="muted">以下入口经平台统一跳转出口访问，用于点击统计与安全审计。</p>
    <ul class="partner-list">
        <li><a href="/jump.php?url=http%3A%2F%2Fwww.huanchong-heavy.cn%2Fportal" target="_blank">浣熊重工机械股份有限公司</a></li>
        <li><a href="/jump.php?url=http%3A%2F%2Fwww.cj-electric.cn%2Fsupplier" target="_blank">长江电气自动化集团</a></li>
        <li><a href="/jump.php?url=http%3A%2F%2Fwww.oasis-energy.cn%2Fhome" target="_blank">绿洲储能科技有限公司</a></li>
    </ul>
</section>
<!-- TODO: 归档预览服务(8081)正式启用前需补充访问鉴权，当前仅监听回环地址 - 运维组 王工 -->
<?php
render_footer();
