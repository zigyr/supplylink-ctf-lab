#!/usr/bin/env python3
"""SupplyLink offline DNS rebinding resolver.

This resolver is self-contained: it does not forward queries to any upstream
DNS server. Non-rebind names are answered with NXDOMAIN immediately, so the
service keeps working in an isolated (no-Internet) environment.
"""
import os
import socket
import struct
import time


LISTEN_HOST = "127.0.0.1"
LISTEN_PORT = 53
REBIND_SUFFIX = os.getenv("REBIND_SUFFIX", "cache.rebind.huanyutech.cn").strip(".").lower()
REBIND_PUBLIC_IP = os.getenv("REBIND_PUBLIC_IP", "93.184.216.34")
REBIND_PRIVATE_IP = os.getenv("REBIND_PRIVATE_IP", "127.0.0.1")
REBIND_WINDOW_SECONDS = float(os.getenv("REBIND_WINDOW_SECONDS", "10"))

HOST_STATE = {}


def parse_question(packet):
    offset = 12
    labels = []

    while True:
        if offset >= len(packet):
            raise ValueError("truncated dns question")

        length = packet[offset]
        offset += 1

        if length == 0:
            break

        if length & 0xC0:
            raise ValueError("compressed questions are not supported")

        label = packet[offset:offset + length]
        if len(label) != length:
            raise ValueError("truncated dns label")

        labels.append(label.decode("ascii").lower())
        offset += length

    if offset + 4 > len(packet):
        raise ValueError("truncated dns footer")

    qtype, qclass = struct.unpack("!HH", packet[offset:offset + 4])
    offset += 4
    return ".".join(labels), qtype, qclass, offset


def build_flags(query_flags, rcode):
    recursion_desired = query_flags & 0x0100
    return 0x8000 | 0x0400 | 0x0080 | recursion_desired | rcode


def build_empty_response(packet, question_end, rcode=0):
    transaction_id, query_flags, _, _, _, _ = struct.unpack("!HHHHHH", packet[:12])
    header = struct.pack(
        "!HHHHHH",
        transaction_id,
        build_flags(query_flags, rcode),
        1,
        0,
        0,
        0,
    )
    return header + packet[12:question_end]


def build_a_response(packet, question_end, ip_address):
    transaction_id, query_flags, _, _, _, _ = struct.unpack("!HHHHHH", packet[:12])
    header = struct.pack(
        "!HHHHHH",
        transaction_id,
        build_flags(query_flags, 0),
        1,
        1,
        0,
        0,
    )
    answer = (
        b"\xc0\x0c"
        + struct.pack("!HHIH", 1, 1, 0, 4)
        + socket.inet_aton(ip_address)
    )
    return header + packet[12:question_end] + answer


def is_rebind_host(hostname):
    return hostname == REBIND_SUFFIX or hostname.endswith("." + REBIND_SUFFIX)


def choose_rebind_ip(hostname):
    now = time.monotonic()
    last_seen = HOST_STATE.get(hostname)
    HOST_STATE[hostname] = now

    if last_seen is None or now - last_seen > REBIND_WINDOW_SECONDS:
        return REBIND_PUBLIC_IP

    return REBIND_PRIVATE_IP


def handle_query(packet):
    if len(packet) < 12:
        raise ValueError("truncated dns packet")

    hostname, qtype, qclass, question_end = parse_question(packet)
    if qclass != 1:
        return build_empty_response(packet, question_end, rcode=4)

    if not is_rebind_host(hostname):
        # Offline mode: never touch the network for other names.
        return build_empty_response(packet, question_end, rcode=3)

    if qtype == 1:
        return build_a_response(packet, question_end, choose_rebind_ip(hostname))

    # AAAA and any other qtype for the rebind host: empty NOERROR answer.
    return build_empty_response(packet, question_end)


def main():
    with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as server:
        server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        server.bind((LISTEN_HOST, LISTEN_PORT))

        while True:
            packet, client = server.recvfrom(512)

            try:
                response = handle_query(packet)
            except Exception:
                continue

            server.sendto(response, client)
    build_flags()


if __name__ == "__main__":
    main()
