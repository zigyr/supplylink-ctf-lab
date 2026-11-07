#!/usr/bin/env bash
# ============================================================
#  SupplyLink CTF 实验环境 - 启动脚本（Linux / macOS 通用）
#  用法: sudo bash lab/start_lab.sh
# ============================================================
set -e

if [ "$EUID" -ne 0 ]; then
    echo "请用 sudo 运行: sudo bash $0"
    exit 1
fi

REAL_USER="${SUDO_USER:-$USER}"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE="$REPO_ROOT/source"
RUNTIME="$REPO_ROOT/runtime"
OS="$(uname -s)"

export DB_FILE="$RUNTIME/supplylink.db"
export STORAGE_DIR="$RUNTIME/imports"
# 与真实靶机一致：JWT 密钥放在 /opt/supplylink/config/jwt.key（任务 2 目标）
export JWT_KEY_FILE="/opt/supplylink/config/jwt.key"

echo "[*] 仓库目录: $REPO_ROOT"
echo "[*] 运行用户: $REAL_USER    系统: $OS"

# ---------- 0. 依赖检查 ----------
command -v php >/dev/null || { echo "缺少 php。安装: Ubuntu/Debian 'sudo apt install php-cli php-sqlite3' / macOS 'brew install php'"; exit 1; }
command -v python3 >/dev/null || { echo "缺少 python3。安装: 'sudo apt install python3' / macOS 'brew install python3'"; exit 1; }
php -m | grep -qi pdo_sqlite || { echo "PHP 缺少 pdo_sqlite 扩展。Ubuntu/Debian: 'sudo apt install php-sqlite3'"; exit 1; }

# ---------- 0.5 停掉上一轮残留 ----------
pkill -f "rebind_dns.py" 2>/dev/null || true
pkill -f "php -S" 2>/dev/null || true
sleep 0.5

# ---------- 1. flag / 归档目录 / JWT 密钥（对应靶机 Linux 路径） ----------
mkdir -p /var/cache/archive "$STORAGE_DIR"
[ -f /var/cache/archive/sample.txt ] || echo 'Q4 设备验收单汇总（正常归档文件）' > /var/cache/archive/sample.txt
if [ ! -f /var/flag.txt ]; then
    echo 'flag{ssrf_jump_rebind_1fi_cha1n_pwned}' > /var/flag.txt
    chmod 644 /var/flag.txt
fi
if [ ! -f "$JWT_KEY_FILE" ]; then
    mkdir -p "$(dirname "$JWT_KEY_FILE")"
    printf 'flag{jwt_k3y_1s_n0t_s4fe_0n_d1sk}' > "$JWT_KEY_FILE"
    chmod 644 "$JWT_KEY_FILE"
fi
echo "[1/6] /var/flag.txt、/var/cache/archive/、/opt/supplylink/config/jwt.key 就绪"

# ---------- 2. 切换系统 DNS 到本地 rebinding 服务器 ----------
mkdir -p "$RUNTIME"
if [ "$OS" = "Darwin" ]; then
    # macOS: /etc/resolv.conf 只是摆设，必须用 networksetup 改每个网络服务的 DNS
    DNS_BAK="$RUNTIME/macos_dns_backup.txt"
    if [ ! -f "$DNS_BAK" ]; then
        : > "$DNS_BAK"
        networksetup -listallnetworkservices | tail -n +2 | sed 's/^\*//' | while IFS= read -r svc; do
            cur=$(networksetup -getdnsservers "$svc" 2>/dev/null)
            case "$cur" in
                *"aren't any DNS"*) cur="__EMPTY__" ;;
            esac
            printf '%s\t%s\n' "$svc" "$cur" >> "$DNS_BAK"
        done
    fi
    networksetup -listallnetworkservices | tail -n +2 | sed 's/^\*//' | while IFS= read -r svc; do
        networksetup -setdnsservers "$svc" 127.0.0.1 >/dev/null
    done
    dscacheutil -flushcache 2>/dev/null || true
    killall -HUP mDNSResponder 2>/dev/null || true
    echo "[2/6] macOS 系统 DNS 已切换到 127.0.0.1（原配置备份到 $DNS_BAK）"
else
    # Linux: 直接改 resolv.conf
    [ -f /etc/resolv.conf.bak ] || cp /etc/resolv.conf /etc/resolv.conf.bak
    printf 'nameserver 127.0.0.1\n' > /etc/resolv.conf
    echo "[2/6] Linux 系统 DNS 已切换到 127.0.0.1（备份在 /etc/resolv.conf.bak）"
fi

# ---------- 3. 启动 rebinding DNS（题目自带源码，53 端口需要 root） ----------
nohup python3 "$SOURCE/dns/rebind_dns.py" > "$RUNTIME/dns.log" 2>&1 &
sleep 0.5
echo "[3/6] rebinding DNS 已启动: *.cache.rebind.huanyutech.cn"
echo "      第一次解析 -> 93.184.216.34 (公网, 骗过校验)"
echo "      10秒内再查 -> 127.0.0.1      (本机, 实际抓取)"

# ---------- 4. 初始化数据库 ----------
chown -R "$REAL_USER" "$RUNTIME" 2>/dev/null || true
if [ ! -f "$DB_FILE" ]; then
    sudo -u "$REAL_USER" env DB_FILE="$DB_FILE" STORAGE_DIR="$STORAGE_DIR" JWT_KEY_FILE="$JWT_KEY_FILE" \
        php "$SOURCE/scripts/init_db.php"
fi
echo "[4/6] SQLite 数据库就绪: $DB_FILE"

# ---------- 5. 启动三个 PHP 服务 ----------
RUN_AS_USER="sudo -u $REAL_USER env DB_FILE=$DB_FILE STORAGE_DIR=$STORAGE_DIR JWT_KEY_FILE=$JWT_KEY_FILE"
$RUN_AS_USER nohup php -S 127.0.0.1:8000 -t "$SOURCE/public" > "$RUNTIME/portal_a.log" 2>&1 &
$RUN_AS_USER nohup php -S 0.0.0.0:8080   -t "$SOURCE/public" > "$RUNTIME/portal_b.log" 2>&1 &
$RUN_AS_USER nohup php -S 127.0.0.1:8081 -t "$SOURCE/internal" "$SOURCE/internal/router.php" > "$RUNTIME/internal.log" 2>&1 &
sleep 1
echo "[5/6] 服务已启动:"
echo "      门户 A (你操作的网站)      http://127.0.0.1:8000"
echo "      门户 B (模拟“公网”入口)    http://127.0.0.1:8080  (rebinding 域名会解析到这里)"
echo "      内网预览服务 (攻击目标)    http://127.0.0.1:8081/debug/preview.php"

# ---------- 6. 自检 rebinding ----------
echo "[6/6] 自检 rebinding 行为:"
IP1=$(python3 -c "import socket; print(socket.gethostbyname('evil.cache.rebind.huanyutech.cn'))" 2>/dev/null || echo FAIL)
IP2=$(python3 -c "import socket; print(socket.gethostbyname('evil.cache.rebind.huanyutech.cn'))" 2>/dev/null || echo FAIL)
echo "      第 1 次解析: $IP1   第 2 次解析: $IP2"
if [ "$IP1" = "93.184.216.34" ] && [ "$IP2" = "127.0.0.1" ]; then
    echo "      ✅ rebinding 工作正常"
else
    echo "      ⚠️ rebinding 异常，请检查 $RUNTIME/dns.log"
fi

echo ""
echo "============================================================"
echo " 实验环境已就绪！"
echo "   门户:     http://127.0.0.1:8000  (supplier_demo / Huanyu2026!)"
echo "   自动攻击: python3 lab/attack.py"
echo "   追踪器:   bash lab/run_trace.sh"
echo "   关闭:     sudo bash lab/stop_lab.sh"
echo "============================================================"
