#!/usr/bin/env python3
"""Emit output above the PHP artifact transport bound for write or read tests."""

from __future__ import annotations

import sys


operation = sys.argv[1] if len(sys.argv) > 1 else ""
limit = 65537 if operation == "write" else (16 * 1024 * 1024) + 1
remaining = limit

if operation == "write":
    sys.stdin.buffer.read()

try:
    while remaining > 0:
        chunk = min(65536, remaining)
        sys.stdout.buffer.write(b"x" * chunk)
        sys.stdout.buffer.flush()
        remaining -= chunk
except BrokenPipeError:
    pass
