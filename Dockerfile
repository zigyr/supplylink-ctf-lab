# ============================================================
#  SupplyLink CTF Lab —— 单容器全封装镜像
#  容器内包含：rebinding DNS + 门户 A(8000) + 门户 B(8080) + 内网预览(8081)
#  DNS Rebinding 完全发生在容器内部，宿主机 DNS 不受影响。
#
#  构建:  docker build -t supplylink-ctf-lab .
#  运行:  docker run --rm -p 8000:8000 supplylink-ctf-lab
#  开打:  python3 lab/attack.py        （宿主机上运行，只打 8000 端口）
# ============================================================
FROM php:8.2-cli-bookworm

# python3 跑 rebinding DNS；libsqlite3-dev 是 pdo_sqlite 的编译依赖
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY source/ /app/source/
COPY docker/entrypoint.sh /app/docker/entrypoint.sh
RUN chmod +x /app/docker/entrypoint.sh

# 与真实靶机一致的运行参数（可用 docker run -e 覆盖）
ENV DB_FILE=/app/runtime/supplylink.db \
    STORAGE_DIR=/app/runtime/imports \
    JWT_KEY_FILE=/opt/supplylink/config/jwt.key \
    FLAG='flag{ssrf_jump_rebind_1fi_cha1n_pwned}' \
    JWT_KEY='flag{jwt_k3y_1s_n0t_s4fe_0n_d1sk}'

# 8000 = 门户 A（浏览器/攻击脚本入口）；8080 = 门户 B（可选暴露，调试用）
EXPOSE 8000 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]
