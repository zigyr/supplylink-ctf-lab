#!/usr/bin/env bash
# ============================================================
#  SupplyLink CTF Lab —— 容器入口
#  逻辑与 lab/start_lab.sh 一致，但全部封在容器内：
#  不需要 sudo、不碰宿主机 DNS、不留运行残留。
# ============================================================
set -e

RUNTIME="$(dirname "$DB_FILE")"

echo "[*] SupplyLink CTF Lab 容器启动中..."

# ---------- 1. 容器内 DNS 指向本地 rebinding 服务器 ----------
printf 'nameserver 127.0.0.1\n' > /etc/resolv.conf
echo "[1/6] 容器 DNS 已指向 127.0.0.1（仅容器内部生效，宿主机无感）"

# ---------- 2. flag / 归档目录 / JWT 密钥（与真实靶机路径一致） ----------
mkdir -p /var/cache/archive "$STORAGE_DIR" "$RUNTIME" "$(dirname "$JWT_KEY_FILE")"
echo 'Q4 设备验收单汇总（正常归档文件）' > /var/cache/archive/sample.txt
printf '%s' "$FLAG" > /var/flag.txt
printf '%s' "$JWT_KEY" > "$JWT_KEY_FILE"
echo "[2/6] /var/flag.txt、/var/cache/archive/、$JWT_KEY_FILE 就绪"

# ---------- 3. 启动 rebinding DNS ----------
python3 /app/source/dns/rebind_dns.py > "$RUNTIME/dns.log" 2>&1 &
sleep 0.5
echo "[3/6] rebinding DNS 已启动: *.cache.rebind.huanyutech.cn"
echo "      第一次解析 -> 93.184.216.34 (公网, 骗过校验)"
echo "      10秒内再查 -> 127.0.0.1      (本机, 实际抓取)"

# ---------- 4. 初始化数据库 ----------
if [ ! -f "$DB_FILE" ]; then
    php /app/source/scripts/init_db.php
fi
echo "[4/6] SQLite 数据库就绪: $DB_FILE"

# ---------- 5. 启动三个 PHP 服务 ----------
# 门户 A 必须监听 0.0.0.0：docker 端口发布是从容器 eth0 进入的，绑回环会映射不进来
php -S 0.0.0.0:8000   -t /app/source/public > "$RUNTIME/portal_a.log" 2>&1 &
php -S 0.0.0.0:8080   -t /app/source/public > "$RUNTIME/portal_b.log" 2>&1 &
php -S 127.0.0.1:8081 -t /app/source/internal /app/source/internal/router.php > "$RUNTIME/internal.log" 2>&1 &
sleep 1
echo "[5/6] 服务已启动:"
echo "      门户 A (你操作的网站)      http://127.0.0.1:8000"
echo "      门户 B (模拟“公网”入口)    容器内 127.0.0.1:8080"
echo "      内网预览服务 (攻击目标)    容器内 127.0.0.1:8081/debug/preview.php"

# ---------- 6. 自检 rebinding ----------
IP1=$(python3 -c "import socket; print(socket.gethostbyname('evil.cache.rebind.huanyutech.cn'))" 2>/dev/null || echo FAIL)
IP2=$(python3 -c "import socket; print(socket.gethostbyname('evil.cache.rebind.huanyutech.cn'))" 2>/dev/null || echo FAIL)
echo "[6/6] 自检 rebinding: 第 1 次解析 $IP1 / 第 2 次解析 $IP2"
if [ "$IP1" = "93.184.216.34" ] && [ "$IP2" = "127.0.0.1" ]; then
    echo "      ✅ rebinding 工作正常"
else
    echo "      ⚠️ rebinding 异常，请检查容器日志"
fi

echo ""
echo "============================================================"
echo " 实验环境已就绪！"
echo "   门户:     http://127.0.0.1:8000  (supplier_demo / Huanyu2026!)"
echo "   自动攻击: python3 lab/attack.py   （在宿主机上运行）"
echo "   关闭:     Ctrl+C 或 docker stop"
echo "============================================================"

# 任一服务退出则容器退出；Ctrl+C / docker stop 时回收全部子进程
trap 'kill $(jobs -p) 2>/dev/null || true' EXIT
wait -n
exit $?
