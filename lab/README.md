# SupplyLink 实验环境指南

> 本地复现完整攻击链：**信息泄露 → SSRF（DNS Rebinding + 开放重定向）→ 内网服务 → 路径穿越 → 拿 flag**
>
> 支持原生 Linux（含 WSL）和 macOS，clone 到哪儿都能跑。

---

## 一、目录结构与各文件作用

```
仓库根目录/
├── source/          # 题目源码（www.zip 原样：public/ internal/ src/ scripts/ dns/）
├── lab/             # 本目录：实验工具
│   ├── start_lab.sh        # 启动：部署源码 + 拉起 DNS 与 3 个 PHP 服务（sudo 运行）
│   ├── stop_lab.sh         # 关闭：停服务、恢复系统 DNS、清理运行产物（sudo 运行）
│   ├── attack.py           # 一键攻击：自动走完登录→构造恶意 URL→下载归档→打印 flag
│   ├── trace_payload.php   # payload 单步追踪器：用真实源码逐关执行，打印每关后的变量
│   ├── run_trace.sh        # trace_payload.php 的启动壳
│   ├── rebind_demo.py      # DNS rebinding 机理小实验：直观感受"同域名两次解析结果不同"
│   └── fetch_test.* / read_test.sh   # 细节验证小脚本（可忽略）
└── runtime/         # 运行后生成：SQLite 数据库、服务日志（不进 git）
```

## 二、环境组成

启动后本机开启 4 个组件：

| 组件 | 地址 | 角色 |
|---|---|---|
| 门户 A | http://127.0.0.1:8000 | 你浏览器访问的网站 |
| 门户 B | 0.0.0.0:8080 | 模拟"靶机公网入口"，rebinding 域名解析回 127.0.0.1 后命中它 |
| 内网预览 | 127.0.0.1:8081 | 攻击目标（internal/），只监听回环 |
| rebinding DNS | 127.0.0.1:53 | 同一域名：首查返回公网 IP，10 秒内再查返回 127.0.0.1 |

> 真实靶机里门户 A、B 是同一个站点；本地拆两个进程只是因为 PHP 内置
> 服务器单线程，自己请求自己会死锁。对攻击链没有影响。

## 三、⚠️ 关于 DNS（务必读完再启动）

攻击的核心是 **DNS Rebinding**：`start_lab.sh` 会在本机 53 端口启动
`source/dns/rebind_dns.py`，并**临时把系统 DNS 指向 127.0.0.1**。

这意味着：

- 运行期间**本机所有域名解析都经过它**——它只认
  `*.cache.rebind.huanyutech.cn`，其余一律 NXDOMAIN，表现为"断网"，**属预期现象**；
- 它对同一域名**轮流返回不同 IP**：第一次查询返回公网 IP（骗过
  `is_ssrf_safe_url()` 校验），10 秒窗口内的后续查询返回 `127.0.0.1`
  （让 `file_get_contents` 实际抓取本机）——**check 与 use 之间的这次
  "变脸"就是 TOCTOU 的落点**；
- 所以每次攻击要换一个**没被查过的新子域名**（`abc123.`、`momo77.` 随便起），
  并在 10 秒内完成提交；
- **关闭时务必用 `stop_lab.sh`**，它负责恢复原 DNS 配置
  （Linux 还原 `/etc/resolv.conf`；macOS 还原 `networksetup` 配置并刷新缓存）。
  直接杀进程会留下"断网"后遗症。

## 四、启动 / 关闭 / 开打

```bash
sudo bash lab/start_lab.sh    # 启动（会临时切换系统 DNS）

python3 lab/attack.py                                                     # 任务1：读 /var/flag.txt
python3 lab/attack.py "....//....//....//opt/supplylink/config/jwt.key"   # 任务2：偷 JWT 密钥

sudo bash lab/stop_lab.sh     # 关闭并恢复系统 DNS
```

想看 payload 每一关之后变量长什么样：`bash lab/run_trace.sh`。
