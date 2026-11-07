#!/usr/bin/env bash
# ============================================================
#  SupplyLink CTF 实验环境 - 关闭脚本（Linux / macOS 通用）
#  用法: sudo bash lab/stop_lab.sh
# ============================================================
set -e

if [ "$EUID" -ne 0 ]; then
    echo "请用 sudo 运行: sudo bash $0"
    exit 1
fi

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME="$REPO_ROOT/runtime"
OS="$(uname -s)"

pkill -f "rebind_dns.py" 2>/dev/null && echo "[✓] rebinding DNS 已停止" || echo "[-] rebinding DNS 未在运行"
pkill -f "php -S" 2>/dev/null && echo "[✓] PHP 服务已停止" || echo "[-] PHP 服务未在运行"

if [ "$OS" = "Darwin" ]; then
    DNS_BAK="$RUNTIME/macos_dns_backup.txt"
    if [ -f "$DNS_BAK" ]; then
        while IFS=$'\t' read -r svc dns; do
            if [ "$dns" = "__EMPTY__" ]; then
                networksetup -setdnsservers "$svc" Empty >/dev/null
            else
                # shellcheck disable=SC2086
                networksetup -setdnsservers "$svc" $dns >/dev/null
            fi
        done < "$DNS_BAK"
        rm -f "$DNS_BAK"
        dscacheutil -flushcache 2>/dev/null || true
        killall -HUP mDNSResponder 2>/dev/null || true
        echo "[✓] macOS 系统 DNS 已恢复"
    fi
else
    if [ -f /etc/resolv.conf.bak ]; then
        cp /etc/resolv.conf.bak /etc/resolv.conf
        echo "[✓] Linux 系统 DNS 已恢复"
    fi
fi

echo "实验环境已关闭。"
