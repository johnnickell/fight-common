"""Shared strict geometry parsing for the Fight identity asset tools."""

from __future__ import annotations

import math
import re
from typing import List, Tuple


Point = Tuple[float, float]
Contour = Tuple[Point, ...]


def parse_linear_path(value: str) -> Tuple[Contour, ...]:
    """Parse closed polygonal contours using absolute M, L, and Z only."""
    if not value or re.search(r"[^MLZ0-9.,+\-\s]", value):
        raise ValueError("Paths must use absolute M, L, and Z commands only")

    tokens = re.findall(r"[MLZ]|[+-]?(?:\d+(?:\.\d*)?|\.\d+)", value)
    contours: List[Contour] = []
    current: List[Point] = []
    command = ""
    index = 0

    while index < len(tokens):
        token = tokens[index]
        if token == "M":
            if current or command or index + 2 >= len(tokens):
                raise ValueError("Every contour must close with Z before the next M")
            command = "M"
            index += 1
            continue
        if token == "L":
            if not current or command == "L" or index + 2 >= len(tokens):
                raise ValueError("L must follow a contour point and precede a coordinate pair")
            command = "L"
            index += 1
            continue
        if token == "Z":
            if command or len(current) < 3:
                raise ValueError("Z must close a polygonal contour with at least three points")
            contours.append(tuple(current))
            current = []
            index += 1
            continue
        if not current and command != "M":
            raise ValueError("Every contour must begin with M")
        if current and command not in {"", "L"}:
            raise ValueError("Only L or implicit coordinate pairs may follow M")
        if index + 1 >= len(tokens) or tokens[index + 1] in {"M", "L", "Z"}:
            raise ValueError("Path coordinates must occur in finite pairs")
        point = (float(token), float(tokens[index + 1]))
        if not all(math.isfinite(coordinate) for coordinate in point):
            raise ValueError("Path coordinates must be finite")
        current.append(point)
        command = ""
        index += 2

    if current or command or not contours:
        raise ValueError("Every contour must explicitly close with Z")

    return tuple(contours)
