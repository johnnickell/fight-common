#!/usr/bin/env python3

"""Generate the deterministic Fight identity asset family from one SVG source."""

from __future__ import annotations

import argparse
import json
import math
import struct
import sys
import xml.etree.ElementTree as element_tree
import zlib
from pathlib import Path
from typing import Dict, Iterable, List, Mapping, Sequence, Tuple

from identity_geometry import parse_linear_path


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUTPUT = ROOT / "docs" / "assets" / "identity"
SOURCE = DEFAULT_OUTPUT / "source" / "fight-identity-source.svg"
SVG_NAMESPACE = "http://www.w3.org/2000/svg"

PALETTE = {
    "canvas_dark": "#101619",
    "canvas_light": "#F4F6F7",
    "kiln_dark": "#FF7A45",
    "kiln_light": "#C2410C",
    "steel_dark": "#AAB7BD",
    "steel_light": "#7E8B91",
    "structure_dark": "#EEF2F3",
    "structure_light": "#182126",
}

VARIANTS = {
    "light": {
        "structure": PALETTE["structure_light"],
        "steel": PALETTE["steel_light"],
        "kiln": PALETTE["kiln_light"],
    },
    "dark": {
        "structure": PALETTE["structure_dark"],
        "steel": PALETTE["steel_dark"],
        "kiln": PALETTE["kiln_dark"],
    },
    "one-color-light": {"structure": PALETTE["structure_light"], "steel": PALETTE["structure_light"], "kiln": PALETTE["structure_light"]},
    "one-color-dark": {"structure": PALETTE["structure_dark"], "steel": PALETTE["structure_dark"], "kiln": PALETTE["structure_dark"]},
    "reversed": {"structure": "#FFFFFF", "steel": "#FFFFFF", "kiln": "#FFFFFF"},
}

Point = Tuple[float, float]
Contour = Tuple[Point, ...]
Shape = Tuple[Tuple[Contour, ...], str]
PlacedShape = Tuple[Tuple[Contour, ...], str]


def parse_points(value: str) -> Tuple[Point, ...]:
    return tuple(tuple(float(number) for number in pair.split(",")) for pair in value.split())  # type: ignore[return-value]


def load_components(source: Path) -> Dict[str, List[Shape]]:
    root = element_tree.parse(source).getroot()
    components: Dict[str, List[Shape]] = {"mark": [], "family": [], "descriptor": []}

    def walk(node: element_tree.Element, component: str = "", color: str = "") -> None:
        component = node.attrib.get("data-component", component)
        color = node.attrib.get("data-color", color)
        if node.tag == f"{{{SVG_NAMESPACE}}}polygon":
            if component not in components or color not in {"structure", "steel", "kiln"}:
                raise ValueError("Every source shape must belong to a named component and color role")
            components[component].append(((parse_points(node.attrib["points"]),), color))
        if node.tag == f"{{{SVG_NAMESPACE}}}path":
            if component not in components or color not in {"structure", "steel", "kiln"}:
                raise ValueError("Every source shape must belong to a named component and color role")
            components[component].append((parse_linear_path(node.attrib["d"]), color))
        for child in node:
            walk(child, component, color)

    walk(root)
    if not all(components.values()):
        raise ValueError("Source must contain mark, family, and descriptor polygon geometry")

    origins = {"mark": (0.0, 0.0), "family": (176.0, 0.0), "descriptor": (176.0, 0.0)}
    for name, shapes in components.items():
        origin_x, origin_y = origins[name]
        components[name] = [
            (
                tuple(
                    tuple((x - origin_x, y - origin_y) for x, y in contour)
                    for contour in contours
                ),
                color,
            )
            for contours, color in shapes
        ]

    return components


def place(
    shapes: Iterable[Shape],
    variant: Mapping[str, str],
    x: float = 0,
    y: float = 0,
    scale: float = 1,
) -> List[PlacedShape]:
    return [
        (
            tuple(
                tuple((x + point_x * scale, y + point_y * scale) for point_x, point_y in contour)
                for contour in contours
            ),
            variant[color],
        )
        for contours, color in shapes
    ]


def xml_number(value: float) -> str:
    if float(value).is_integer():
        return str(int(value))
    return ("%.4f" % value).rstrip("0").rstrip(".")


def svg_document(
    width: int,
    height: int,
    title: str,
    description: str,
    shapes: Sequence[PlacedShape],
    background: str = "",
) -> bytes:
    lines = [
        f'<svg xmlns="{SVG_NAMESPACE}" width="{width}" height="{height}" viewBox="0 0 {width} {height}" role="img" aria-labelledby="asset-title asset-desc">',
        f"  <title id=\"asset-title\">{title}</title>",
        f"  <desc id=\"asset-desc\">{description}</desc>",
    ]
    if background:
        lines.append(f'  <polygon fill="{background}" points="0,0 {width},0 {width},{height} 0,{height}"/>')
    lines.append('  <g id="artwork">')
    for contours, color in shapes:
        if len(contours) == 1:
            value = " ".join(f"{xml_number(x)},{xml_number(y)}" for x, y in contours[0])
            lines.append(f'    <polygon fill="{color}" points="{value}"/>')
            continue
        path = " ".join(
            "M " + " L ".join(f"{xml_number(x)} {xml_number(y)}" for x, y in contour) + " Z"
            for contour in contours
        )
        lines.append(f'    <path fill="{color}" fill-rule="evenodd" d="{path}"/>')
    lines.extend(["  </g>", "</svg>", ""])
    return "\n".join(lines).encode("utf-8")


def mark_svg(components: Mapping[str, Sequence[Shape]], variant_name: str, title: str) -> bytes:
    return svg_document(
        128,
        128,
        title,
        "A structural F with an inbound rail, active triangular port, one upper ownership rail, and open lower counterspace.",
        place(components["mark"], VARIANTS[variant_name]),
    )


def horizontal_svg(
    components: Mapping[str, Sequence[Shape]],
    variant_name: str,
    include_descriptor: bool,
    width: int = 500,
    height: int = 160,
    title: str = "Fight Common horizontal lockup",
) -> bytes:
    polygons = place(components["mark"], VARIANTS[variant_name], 16, 16)
    polygons += place(components["family"], VARIANTS[variant_name], 168, 16)
    if include_descriptor:
        polygons += place(components["descriptor"], VARIANTS[variant_name], 168, 16)
    description = "The Inward Port mark beside the outlined FIGHT wordmark"
    if include_descriptor:
        description += " and independently outlined COMMON descriptor"
    return svg_document(width, height, title, description + ".", polygons)


def stacked_svg(components: Mapping[str, Sequence[Shape]], variant_name: str) -> bytes:
    polygons = place(components["mark"], VARIANTS[variant_name], 90, 12, 1.25)
    polygons += place(components["family"], VARIANTS[variant_name], 28, 158)
    polygons += place(components["descriptor"], VARIANTS[variant_name], 78, 164)
    return svg_document(
        340,
        300,
        "Fight Common stacked lockup",
        "The Inward Port mark above the outlined FIGHT wordmark and independently outlined COMMON descriptor.",
        polygons,
    )


def specimen_geometry(components: Mapping[str, Sequence[Shape]]) -> List[PlacedShape]:
    polygons: List[PlacedShape] = []
    quiet = "#CBD3D7"
    polygons.append(((((40, 330), (1400, 330), (1400, 334), (40, 334)),), quiet))
    polygons.append(((((40, 682), (1400, 682), (1400, 686), (40, 686)),), quiet))
    polygons.append(((((720, 720), (1380, 720), (1380, 980), (720, 980)),), PALETTE["canvas_dark"]))
    polygons += place(components["mark"], VARIANTS["light"], 64, 64, 1.5)
    polygons += place(components["family"], VARIANTS["light"], 296, 79, 1.5)
    polygons += place(components["descriptor"], VARIANTS["light"], 296, 79, 1.5)
    polygons += place(components["mark"], VARIANTS["one-color-light"], 64, 410, 1.5)
    polygons += place(components["family"], VARIANTS["one-color-light"], 296, 425, 1.5)
    polygons += place(components["descriptor"], VARIANTS["one-color-light"], 296, 425, 1.5)
    polygons.append(((((760, 374), (1380, 374), (1380, 638), (760, 638)),), PALETTE["canvas_dark"]))
    polygons += place(components["mark"], VARIANTS["reversed"], 808, 410, 1.5)
    polygons += place(components["family"], VARIANTS["reversed"], 1040, 425, 0.9)
    polygons += place(components["descriptor"], VARIANTS["reversed"], 1040, 425, 0.9)

    sizes = ((64, 80), (32, 248), (16, 360))
    for size, x in sizes:
        scale = size / 128
        polygons += place(components["mark"], VARIANTS["one-color-light"], x, 786, scale)
        frame = ((
            ((x - 8, 778), (x + size + 8, 778), (x + size + 8, 786 + size + 8), (x - 8, 786 + size + 8)),
        ), quiet)
        polygons.insert(len(polygons) - len(components["mark"]), frame)

    polygons += place(components["mark"], VARIANTS["dark"], 760, 754, 1.5)
    polygons += place(components["family"], VARIANTS["dark"], 992, 769, 0.9)
    polygons += place(components["descriptor"], VARIANTS["dark"], 992, 769, 0.9)
    return polygons


def specimen_svg(components: Mapping[str, Sequence[Shape]]) -> bytes:
    return svg_document(
        1440,
        1040,
        "Fight identity production specimen",
        "Full-color, one-color, reversed, dark-theme, and exact 64, 32, and 16 pixel identity treatments.",
        specimen_geometry(components),
        PALETTE["canvas_light"],
    )


def hex_rgb(color: str) -> Tuple[int, int, int]:
    return tuple(int(color[index : index + 2], 16) for index in (1, 3, 5))  # type: ignore[return-value]


def point_inside(x: float, y: float, polygon: Sequence[Point]) -> bool:
    inside = False
    previous_x, previous_y = polygon[-1]
    for current_x, current_y in polygon:
        if (current_y > y) != (previous_y > y):
            crossing = (previous_x - current_x) * (y - current_y) / (previous_y - current_y) + current_x
            if x < crossing:
                inside = not inside
        previous_x, previous_y = current_x, current_y
    return inside


def rasterize(width: int, height: int, background: str, shapes: Sequence[PlacedShape], samples: int = 4) -> bytes:
    base = hex_rgb(background)
    pixels = bytearray(base * (width * height))
    offsets = tuple((index + 0.5) / samples for index in range(samples))
    sample_count = samples * samples
    for contours, color in shapes:
        rgb = hex_rgb(color)
        points = tuple(point for contour in contours for point in contour)
        minimum_x = max(0, int(math.floor(min(point[0] for point in points))))
        maximum_x = min(width, int(math.ceil(max(point[0] for point in points))))
        minimum_y = max(0, int(math.floor(min(point[1] for point in points))))
        maximum_y = min(height, int(math.ceil(max(point[1] for point in points))))
        unique_x = {point[0] for point in points}
        unique_y = {point[1] for point in points}
        if (
            len(contours) == 1
            and len(points) == 4
            and len(unique_x) == 2
            and len(unique_y) == 2
            and all(float(value).is_integer() for value in unique_x | unique_y)
        ):
            row = bytes(rgb) * (maximum_x - minimum_x)
            for y in range(minimum_y, maximum_y):
                offset = (y * width + minimum_x) * 3
                pixels[offset : offset + len(row)] = row
            continue
        for y in range(minimum_y, maximum_y):
            for x in range(minimum_x, maximum_x):
                covered = sum(
                    sum(point_inside(x + dx, y + dy, contour) for contour in contours) % 2
                    for dx in offsets
                    for dy in offsets
                )
                if not covered:
                    continue
                alpha = covered / sample_count
                offset = (y * width + x) * 3
                for channel in range(3):
                    pixels[offset + channel] = round(rgb[channel] * alpha + pixels[offset + channel] * (1 - alpha))
    return png(width, height, bytes(pixels))


def png_chunk(kind: bytes, payload: bytes) -> bytes:
    return struct.pack(">I", len(payload)) + kind + payload + struct.pack(">I", zlib.crc32(kind + payload) & 0xFFFFFFFF)


def png(width: int, height: int, pixels: bytes) -> bytes:
    stride = width * 3
    rows = b"".join(b"\x00" + pixels[offset : offset + stride] for offset in range(0, len(pixels), stride))
    return (
        b"\x89PNG\r\n\x1a\n"
        + png_chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 2, 0, 0, 0))
        + png_chunk(b"IDAT", zlib.compress(rows, 9))
        + png_chunk(b"IEND", b"")
    )


def icon(images: Sequence[Tuple[int, bytes]]) -> bytes:
    header = struct.pack("<HHH", 0, 1, len(images))
    entries = []
    payload = []
    offset = 6 + 16 * len(images)
    for size, data in images:
        entries.append(struct.pack("<BBBBHHII", size, size, 0, 0, 1, 32, len(data), offset))
        payload.append(data)
        offset += len(data)
    return header + b"".join(entries) + b"".join(payload)


def raster_mark(
    components: Mapping[str, Sequence[Shape]],
    size: int,
    padding_ratio: float = 0.0,
    variant_name: str = "dark",
) -> bytes:
    padding = size * padding_ratio
    scale = (size - 2 * padding) / 128
    polygons = place(components["mark"], VARIANTS[variant_name], padding, padding, scale)
    return rasterize(size, size, PALETTE["canvas_dark"], polygons)


def social_image(components: Mapping[str, Sequence[Shape]]) -> bytes:
    polygons = place(components["mark"], VARIANTS["dark"], 104, 192, 2)
    polygons += place(components["family"], VARIANTS["dark"], 424, 204, 2)
    polygons += place(components["descriptor"], VARIANTS["dark"], 424, 204, 2)
    return rasterize(1280, 640, PALETTE["canvas_dark"], polygons)


def build_outputs(source: Path) -> Dict[str, bytes]:
    components = load_components(source)
    outputs: Dict[str, bytes] = {"source/fight-identity-source.svg": source.read_bytes()}
    outputs.update(
        {
            "fight-mark-full-color.svg": mark_svg(components, "light", "Fight Inward Port full-color mark"),
            "fight-mark-one-color-light.svg": mark_svg(components, "one-color-light", "Fight Inward Port one-color mark for light surfaces"),
            "fight-mark-one-color-dark.svg": mark_svg(components, "one-color-dark", "Fight Inward Port one-color mark for dark surfaces"),
            "fight-mark-reversed.svg": mark_svg(components, "reversed", "Fight Inward Port reversed mark"),
            "fight-mark-light.svg": mark_svg(components, "light", "Fight Inward Port mark for light surfaces"),
            "fight-mark-dark.svg": mark_svg(components, "dark", "Fight Inward Port mark for dark surfaces"),
            "fight-family-horizontal-light.svg": horizontal_svg(components, "light", False, title="Fight family horizontal lockup for light surfaces"),
            "fight-family-horizontal-dark.svg": horizontal_svg(components, "dark", False, title="Fight family horizontal lockup for dark surfaces"),
            "fight-family-horizontal-one-color-light.svg": horizontal_svg(components, "one-color-light", False, title="Fight family one-color horizontal lockup for light surfaces"),
            "fight-family-horizontal-one-color-dark.svg": horizontal_svg(components, "one-color-dark", False, title="Fight family one-color horizontal lockup for dark surfaces"),
            "fight-family-horizontal-reversed.svg": horizontal_svg(components, "reversed", False, title="Fight family reversed horizontal lockup"),
            "fight-common-horizontal-light.svg": horizontal_svg(components, "light", True, title="Fight Common horizontal lockup for light surfaces"),
            "fight-common-horizontal-dark.svg": horizontal_svg(components, "dark", True, title="Fight Common horizontal lockup for dark surfaces"),
            "fight-common-horizontal-one-color-light.svg": horizontal_svg(components, "one-color-light", True, title="Fight Common one-color horizontal lockup for light surfaces"),
            "fight-common-horizontal-one-color-dark.svg": horizontal_svg(components, "one-color-dark", True, title="Fight Common one-color horizontal lockup for dark surfaces"),
            "fight-common-horizontal-reversed.svg": horizontal_svg(components, "reversed", True, title="Fight Common reversed horizontal lockup"),
            "fight-common-stacked-light.svg": stacked_svg(components, "light"),
            "fight-common-stacked-dark.svg": stacked_svg(components, "dark"),
            "fight-common-stacked-one-color-light.svg": stacked_svg(components, "one-color-light"),
            "fight-common-stacked-one-color-dark.svg": stacked_svg(components, "one-color-dark"),
            "fight-common-stacked-reversed.svg": stacked_svg(components, "reversed"),
            "fight-common-readme-light.svg": horizontal_svg(components, "light", True, 720, 160, "Fight Common README lockup for light surfaces"),
            "fight-common-readme-dark.svg": horizontal_svg(components, "dark", True, 720, 160, "Fight Common README lockup for dark surfaces"),
            "identity-specimen.svg": specimen_svg(components),
            "favicon.svg": svg_document(
                128,
                128,
                "Fight favicon",
                "The one-color Fight Inward Port mark on a carbon background.",
                place(components["mark"], VARIANTS["reversed"], 8, 8, 0.875),
                PALETTE["canvas_dark"],
            ),
        }
    )
    favicon_16 = raster_mark(components, 16, variant_name="reversed")
    favicon_32 = raster_mark(components, 32)
    outputs["favicon-16.png"] = favicon_16
    outputs["favicon-32.png"] = favicon_32
    outputs["favicon.ico"] = icon(((16, favicon_16), (32, favicon_32)))
    outputs["apple-touch-icon.png"] = raster_mark(components, 180, 0.1)
    outputs["fight-avatar-512.png"] = raster_mark(components, 512, 0.12)
    outputs["fight-common-social-1280x640.png"] = social_image(components)
    outputs["identity-specimen.png"] = rasterize(
        1440,
        1040,
        PALETTE["canvas_light"],
        specimen_geometry(components),
    )

    dimensions = {
        name: ([1280, 640] if name == "fight-common-social-1280x640.png" else
               [512, 512] if name == "fight-avatar-512.png" else
               [180, 180] if name == "apple-touch-icon.png" else
               [32, 32] if name == "favicon-32.png" else
               [16, 16] if name == "favicon-16.png" else
               [[16, 16], [32, 32]] if name == "favicon.ico" else
               [460, 128] if name.startswith("source/") else
               [1440, 1040] if name == "identity-specimen.png" else
               [1440, 1040] if name == "identity-specimen.svg" else
               [340, 300] if "stacked" in name else
               [720, 160] if "readme" in name else
               [500, 160] if "horizontal" in name else
               [128, 128])
        for name in outputs
    }
    manifest = {
        "schema": 1,
        "source": "source/fight-identity-source.svg",
        "generator": "scripts/generate_identity_assets.py",
        "palette": PALETTE,
        "files": dimensions,
    }
    outputs["manifest.json"] = (json.dumps(manifest, indent=2, sort_keys=True) + "\n").encode("utf-8")
    return outputs


def check_outputs(output: Path, expected: Mapping[str, bytes]) -> int:
    actual_names = {
        path.relative_to(output).as_posix()
        for path in output.rglob("*")
        if path.is_file()
    } if output.exists() else set()
    expected_names = set(expected)
    drift = sorted(actual_names ^ expected_names)
    drift.extend(
        name for name in sorted(actual_names & expected_names)
        if (output / name).read_bytes() != expected[name]
    )
    if drift:
        for name in drift:
            print(f"Identity asset drift: {name}", file=sys.stderr)
        return 1
    return 0


def write_outputs(output: Path, outputs: Mapping[str, bytes]) -> None:
    for name, data in outputs.items():
        path = output / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(data)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--check", action="store_true")
    arguments = parser.parse_args()
    outputs = build_outputs(SOURCE)
    if arguments.check:
        return check_outputs(arguments.output, outputs)
    write_outputs(arguments.output, outputs)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
