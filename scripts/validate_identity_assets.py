#!/usr/bin/env python3

"""Validate the committed Fight identity asset family and its safety contract."""

from __future__ import annotations

import json
import math
import re
import struct
import sys
import xml.etree.ElementTree as element_tree
import zlib
from pathlib import Path
from typing import Dict, Iterable, List, Mapping, Sequence, Tuple

from identity_geometry import parse_linear_path


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DIRECTORY = ROOT / "docs" / "assets" / "identity"
SVG_NAMESPACE = "http://www.w3.org/2000/svg"
EXPECTED_FILES = {
    "manifest.json",
    "source/fight-identity-source.svg",
    "fight-mark-full-color.svg",
    "fight-mark-one-color-light.svg",
    "fight-mark-one-color-dark.svg",
    "fight-mark-reversed.svg",
    "fight-mark-light.svg",
    "fight-mark-dark.svg",
    "fight-family-horizontal-light.svg",
    "fight-family-horizontal-dark.svg",
    "fight-family-horizontal-one-color-light.svg",
    "fight-family-horizontal-one-color-dark.svg",
    "fight-family-horizontal-reversed.svg",
    "fight-common-horizontal-light.svg",
    "fight-common-horizontal-dark.svg",
    "fight-common-horizontal-one-color-light.svg",
    "fight-common-horizontal-one-color-dark.svg",
    "fight-common-horizontal-reversed.svg",
    "fight-common-stacked-light.svg",
    "fight-common-stacked-dark.svg",
    "fight-common-stacked-one-color-light.svg",
    "fight-common-stacked-one-color-dark.svg",
    "fight-common-stacked-reversed.svg",
    "fight-common-readme-light.svg",
    "fight-common-readme-dark.svg",
    "identity-specimen.svg",
    "identity-specimen.png",
    "favicon.svg",
    "favicon-16.png",
    "favicon-32.png",
    "favicon.ico",
    "apple-touch-icon.png",
    "fight-avatar-512.png",
    "fight-common-social-1280x640.png",
}
ALLOWED_SVG_ELEMENTS = {"svg", "title", "desc", "g", "polygon", "path"}
ALLOWED_SVG_ATTRIBUTES = {
    "svg": {"width", "height", "viewBox", "role", "aria-labelledby"},
    "title": {"id"},
    "desc": {"id"},
    "g": {"id", "data-component", "data-origin", "data-color", "fill"},
    "polygon": {"points", "fill"},
    "path": {"d", "fill", "fill-rule"},
}
APPROVED_COLORS = {
    "#101619",
    "#182126",
    "#7E8B91",
    "#C2410C",
    "#F4F6F7",
    "#AAB7BD",
    "#EEF2F3",
    "#FF7A45",
    "#FFFFFF",
    "#CBD3D7",
}


def fail(message: str) -> None:
    raise ValueError(message)


def local_name(tag: str) -> str:
    return tag.rsplit("}", 1)[-1]


def positive_number(value: object, field: str, path: Path) -> float:
    try:
        number = float(str(value))
    except ValueError:
        fail(f"Invalid {field} in {path.name}")
    if not math.isfinite(number) or number <= 0:
        fail(f"Invalid {field} in {path.name}")
    return number


def svg_dimensions(path: Path) -> Tuple[int, int]:
    raw = path.read_text(encoding="utf-8")
    lowered = raw.lower()
    if "lower-rail" in lowered or "lower_rail" in lowered:
        fail(f"Unapproved lower rail in {path.name}")
    if "<script" in lowered:
        fail(f"Script content is prohibited in {path.name}")
    if "<filter" in lowered or "<lineargradient" in lowered or "<radialgradient" in lowered:
        fail(f"Effect content is prohibited in {path.name}")
    if "<text" in lowered or "<tspan" in lowered:
        fail(f"Text dependency is prohibited in {path.name}")
    if "<image" in lowered or "http://" in lowered.replace(f'xmlns="{SVG_NAMESPACE}"', "") or "https://" in lowered:
        fail(f"External reference is prohibited in {path.name}")

    try:
        root = element_tree.fromstring(raw)
    except element_tree.ParseError as error:
        fail(f"Malformed SVG {path.name}: {error}")
    if root.tag != f"{{{SVG_NAMESPACE}}}svg":
        fail(f"Invalid SVG namespace in {path.name}")

    width = positive_number(root.attrib.get("width"), "width", path)
    height = positive_number(root.attrib.get("height"), "height", path)
    view_box = root.attrib.get("viewBox", "").split()
    if len(view_box) != 4:
        fail(f"Missing viewBox in {path.name}")
    view_numbers = [positive_number(value, "viewBox", path) if index >= 2 else float(value) for index, value in enumerate(view_box)]
    if view_numbers != [0.0, 0.0, width, height]:
        fail(f"Intrinsic dimensions and viewBox disagree in {path.name}")
    if root.attrib.get("role") != "img":
        fail(f"Accessible image role is missing in {path.name}")
    titles = [child for child in root if local_name(child.tag) == "title"]
    descriptions = [child for child in root if local_name(child.tag) == "desc"]
    if len(titles) != 1 or len(descriptions) != 1 or not (titles[0].text or "").strip() or not (descriptions[0].text or "").strip():
        fail(f"Accessible title and description are missing or empty in {path.name}")
    labels = root.attrib.get("aria-labelledby", "").split()
    expected_labels = [titles[0].attrib.get("id"), descriptions[0].attrib.get("id")]
    if labels != expected_labels or None in expected_labels:
        fail(f"Accessible title and description references are invalid in {path.name}")

    ids = set()
    for node in root.iter():
        name = local_name(node.tag)
        if node.tag != f"{{{SVG_NAMESPACE}}}{name}":
            fail(f"Foreign namespace is prohibited in {path.name}: {name}")
        if name not in ALLOWED_SVG_ELEMENTS:
            fail(f"Prohibited {name} element in {path.name}")
        unexpected_attributes = set(node.attrib) - ALLOWED_SVG_ATTRIBUTES[name]
        if unexpected_attributes:
            rendered = ", ".join(sorted(unexpected_attributes))
            fail(f"Prohibited distortion or effect attribute in {path.name}: {rendered}")
        identifier = node.attrib.get("id")
        if identifier:
            if identifier in ids:
                fail(f"Duplicate SVG identifier {identifier} in {path.name}")
            ids.add(identifier)
        for attribute, value in node.attrib.items():
            normalized = local_name(attribute).lower()
            lowered_value = value.lower()
            if normalized in {"href", "src"} or "url(" in lowered_value or "javascript:" in lowered_value:
                fail(f"External reference is prohibited in {path.name}")
            if normalized.startswith("on") or normalized == "style":
                fail(f"Script or effect attribute is prohibited in {path.name}")
        fill = node.attrib.get("fill")
        if fill and fill.upper() not in APPROVED_COLORS:
            fail(f"Unapproved color {fill} in {path.name}")
        if name == "polygon":
            try:
                points = [tuple(float(value) for value in pair.split(",")) for pair in node.attrib.get("points", "").split()]
            except ValueError:
                fail(f"Invalid polygon geometry in {path.name}")
            if len(points) < 3 or any(len(point) != 2 or not all(math.isfinite(value) for value in point) for point in points):
                fail(f"Invalid polygon geometry in {path.name}")
        if name == "path":
            data = node.attrib.get("d", "")
            if node.attrib.get("fill-rule") != "evenodd":
                fail(f"Outlined path must use evenodd fill in {path.name}")
            try:
                parse_linear_path(data)
            except ValueError:
                fail(f"Invalid path geometry in {path.name}")

    if path.name == "fight-identity-source.svg":
        required = {
            "mark",
            "structural-f",
            "approach-rail",
            "active-port",
            "ownership-rail",
            "family-wordmark",
            "product-descriptor",
        }
        if not required.issubset(ids):
            fail("Editable source is missing separately identified identity components")

    return int(width), int(height)


def png_dimensions(data: bytes, path: Path) -> Tuple[int, int]:
    if data[:8] != b"\x89PNG\r\n\x1a\n":
        fail(f"Invalid PNG signature in {path.name}")
    offset = 8
    found_ihdr = False
    found_iend = False
    width = height = 0
    while offset < len(data):
        if offset + 12 > len(data):
            fail(f"Truncated PNG chunk in {path.name}")
        length = struct.unpack(">I", data[offset : offset + 4])[0]
        kind = data[offset + 4 : offset + 8]
        payload = data[offset + 8 : offset + 8 + length]
        checksum = data[offset + 8 + length : offset + 12 + length]
        if len(payload) != length or len(checksum) != 4:
            fail(f"Truncated PNG chunk in {path.name}")
        if struct.unpack(">I", checksum)[0] != zlib.crc32(kind + payload) & 0xFFFFFFFF:
            fail(f"Invalid PNG checksum in {path.name}")
        if kind == b"IHDR":
            if length != 13:
                fail(f"Invalid PNG header in {path.name}")
            width, height, depth, color_type, compression, filtering, interlace = struct.unpack(">IIBBBBB", payload)
            if (depth, color_type, compression, filtering, interlace) != (8, 2, 0, 0, 0):
                fail(f"Unexpected PNG encoding in {path.name}")
            found_ihdr = True
        elif kind == b"IEND":
            found_iend = True
            if offset + 12 + length != len(data):
                fail(f"Trailing PNG content in {path.name}")
        elif kind not in {b"IDAT"}:
            fail(f"Unexpected PNG metadata in {path.name}")
        offset += 12 + length
    if not found_ihdr or not found_iend or width <= 0 or height <= 0:
        fail(f"Incomplete PNG in {path.name}")
    return width, height


def icon_dimensions(data: bytes, path: Path) -> List[List[int]]:
    if len(data) < 38:
        fail(f"Truncated icon in {path.name}")
    reserved, icon_type, count = struct.unpack("<HHH", data[:6])
    if (reserved, icon_type, count) != (0, 1, 2):
        fail(f"Icon must contain exactly 16px and 32px images in {path.name}")
    dimensions = []
    for index in range(count):
        entry_offset = 6 + index * 16
        width, height, colors, reserved_byte, planes, depth, size, image_offset = struct.unpack(
            "<BBBBHHII", data[entry_offset : entry_offset + 16]
        )
        width = width or 256
        height = height or 256
        if colors != 0 or reserved_byte != 0 or planes != 1 or depth != 32:
            fail(f"Invalid icon directory entry in {path.name}")
        payload = data[image_offset : image_offset + size]
        if png_dimensions(payload, path) != (width, height):
            fail(f"Icon directory dimensions disagree with embedded PNG in {path.name}")
        dimensions.append([width, height])
    if dimensions != [[16, 16], [32, 32]]:
        fail(f"Icon must contain exactly 16px and 32px images in {path.name}")
    return dimensions


def relative_luminance(color: str) -> float:
    channels = [int(color[index : index + 2], 16) / 255 for index in (1, 3, 5)]
    linear = [channel / 12.92 if channel <= 0.04045 else ((channel + 0.055) / 1.055) ** 2.4 for channel in channels]
    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]


def contrast(first: str, second: str) -> float:
    light, dark = sorted((relative_luminance(first), relative_luminance(second)), reverse=True)
    return (light + 0.05) / (dark + 0.05)


def validate_contrast(palette: Mapping[str, str]) -> None:
    required = (
        ("structure_light", "canvas_light", 7.0),
        ("steel_light", "canvas_light", 3.0),
        ("kiln_light", "canvas_light", 4.5),
        ("structure_dark", "canvas_dark", 7.0),
        ("steel_dark", "canvas_dark", 3.0),
        ("kiln_dark", "canvas_dark", 4.5),
    )
    for foreground, background, minimum in required:
        ratio = contrast(palette[foreground], palette[background])
        if ratio < minimum:
            fail(f"Palette contrast fails for {foreground} on {background}: {ratio:.2f}:1 is below {minimum:.1f}:1")


def validate(directory: Path) -> None:
    if not directory.is_dir() or directory.is_symlink():
        fail(f"Identity asset directory is missing or unsafe: {directory}")
    paths = sorted(directory.rglob("*"), key=lambda item: item.as_posix())
    for path in paths:
        if path.is_symlink():
            fail(f"Identity asset family contains symbolic link: {path.relative_to(directory)}")
    actual = {path.relative_to(directory).as_posix() for path in paths if path.is_file()}
    if actual != EXPECTED_FILES:
        missing = sorted(EXPECTED_FILES - actual)
        extra = sorted(actual - EXPECTED_FILES)
        fail(f"Identity asset inventory differs; missing={missing}; extra={extra}")

    try:
        manifest = json.loads((directory / "manifest.json").read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        fail(f"Identity manifest is malformed: {error}")
    if manifest.get("schema") != 1:
        fail("Identity manifest schema must be 1")
    if manifest.get("source") != "source/fight-identity-source.svg":
        fail("Identity manifest must name the editable source")
    if manifest.get("generator") != "scripts/generate_identity_assets.py":
        fail("Identity manifest must name the repository generator")
    files = manifest.get("files")
    if not isinstance(files, dict) or set(files) != EXPECTED_FILES - {"manifest.json"}:
        fail("Identity manifest inventory differs from committed assets")
    palette = manifest.get("palette")
    if not isinstance(palette, dict) or set(palette.values()) - APPROVED_COLORS:
        fail("Identity manifest contains an unapproved palette")
    validate_contrast(palette)

    for name, expected_dimensions in files.items():
        path = directory / name
        if name.endswith(".svg"):
            actual_dimensions: object = list(svg_dimensions(path))
        elif name.endswith(".png"):
            actual_dimensions = list(png_dimensions(path.read_bytes(), path))
        elif name.endswith(".ico"):
            actual_dimensions = icon_dimensions(path.read_bytes(), path)
        else:
            fail(f"Unsupported identity asset format: {name}")
        if actual_dimensions != expected_dimensions:
            fail(f"Manifest dimensions disagree for {name}: {actual_dimensions} != {expected_dimensions}")


def main(arguments: Sequence[str]) -> int:
    if len(arguments) > 1:
        print("Usage: validate_identity_assets.py [DIRECTORY]", file=sys.stderr)
        return 2
    directory = Path(arguments[0]) if arguments else DEFAULT_DIRECTORY
    try:
        validate(directory)
    except (KeyError, TypeError, ValueError) as error:
        print(f"Identity asset validation failed: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
