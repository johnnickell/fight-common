#!/usr/bin/env python3
"""Test fixture that rejects input and violates the storage helper output contract."""

import os


os.close(0)
print("unexpected helper output")
