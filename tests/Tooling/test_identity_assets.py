#!/usr/bin/env python3

"""Exercise the deterministic Fight identity asset toolchain."""

from __future__ import annotations

import json
import shutil
import struct
import subprocess
import sys
import tempfile
import unittest
import xml.etree.ElementTree as element_tree
import zlib
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
GENERATOR = ROOT / "scripts" / "generate_identity_assets.py"
VALIDATOR = ROOT / "scripts" / "validate_identity_assets.py"
TRACKED_ASSETS = ROOT / "docs" / "assets" / "identity"

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

EXPECTED_RASTER_DIMENSIONS = {
    "favicon-16.png": (16, 16),
    "favicon-32.png": (32, 32),
    "apple-touch-icon.png": (180, 180),
    "fight-avatar-512.png": (512, 512),
    "fight-common-social-1280x640.png": (1280, 640),
    "identity-specimen.png": (1440, 1040),
}


class IdentityAssetsTest(unittest.TestCase):
    def test_that_generation_is_deterministic_complete_and_matches_the_tracked_assets(self) -> None:
        with tempfile.TemporaryDirectory() as first, tempfile.TemporaryDirectory() as second:
            self.run_tool(GENERATOR, "--output", first)
            self.run_tool(GENERATOR, "--output", second)

            first_files = self.files(Path(first))
            second_files = self.files(Path(second))
            tracked_files = self.files(TRACKED_ASSETS)

            self.assertEqual(EXPECTED_FILES, set(first_files))
            self.assertEqual(first_files, second_files)
            self.assertEqual(first_files, tracked_files)
            self.run_tool(GENERATOR, "--output", first, "--check")
            self.run_tool(VALIDATOR, first)

    def test_that_raster_and_icon_dimensions_are_exact(self) -> None:
        for name, expected in EXPECTED_RASTER_DIMENSIONS.items():
            data = (TRACKED_ASSETS / name).read_bytes()
            self.assertEqual(b"\x89PNG\r\n\x1a\n", data[:8])
            self.assertEqual(expected, struct.unpack(">II", data[16:24]))

        icon = (TRACKED_ASSETS / "favicon.ico").read_bytes()
        reserved, icon_type, count = struct.unpack("<HHH", icon[:6])
        self.assertEqual((0, 1, 2), (reserved, icon_type, count))
        sizes = []
        for offset in (6, 22):
            width, height = struct.unpack("BB", icon[offset : offset + 2])
            sizes.append((width or 256, height or 256))
        self.assertEqual([(16, 16), (32, 32)], sizes)

    def test_that_the_16px_favicon_uses_one_reversed_artwork_color(self) -> None:
        pixels = self.png_pixels((TRACKED_ASSETS / "favicon-16.png").read_bytes())

        self.assertIn(bytes.fromhex("FFFFFF"), pixels)
        self.assertNotIn(bytes.fromhex("FF7A45"), pixels)
        self.assertNotIn(bytes.fromhex("AAB7BD"), pixels)

    def test_that_manifest_records_exact_inventory_dimensions_and_palette(self) -> None:
        manifest = json.loads((TRACKED_ASSETS / "manifest.json").read_text(encoding="utf-8"))

        self.assertEqual(1, manifest["schema"])
        self.assertEqual(sorted(EXPECTED_FILES - {"manifest.json"}), sorted(manifest["files"]))
        self.assertEqual(
            {
                "canvas_dark": "#101619",
                "canvas_light": "#F4F6F7",
                "kiln_dark": "#FF7A45",
                "kiln_light": "#C2410C",
                "steel_dark": "#AAB7BD",
                "steel_light": "#7E8B91",
                "structure_dark": "#EEF2F3",
                "structure_light": "#182126",
            },
            manifest["palette"],
        )
        self.assertEqual([1280, 640], manifest["files"]["fight-common-social-1280x640.png"])

    def test_that_check_reports_byte_drift(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            self.run_tool(GENERATOR, "--output", directory)
            path = Path(directory) / "fight-mark-light.svg"
            path.write_bytes(path.read_bytes() + b"\n")

            process = self.run_tool(GENERATOR, "--output", directory, "--check", expected=1)
            self.assertIn("fight-mark-light.svg", process.stderr)

    def test_that_validator_rejects_unsafe_or_unapproved_vector_content(self) -> None:
        mutations = {
            "script": ("</svg>", "<script>alert(1)</script></svg>"),
            "external reference": ("</svg>", '<image href="https://example.test/a.png"/></svg>'),
            "text dependency": ("</svg>", "<text>FIGHT</text></svg>"),
            "lower rail": ("</svg>", '<path id="lower-rail" d="M0 0h1v1z"/></svg>'),
            "effect": ("</svg>", '<filter id="shadow"/></svg>'),
            "distortion": ('<g id="artwork">', '<g id="artwork" transform="rotate(12 64 64)" opacity="0.5" stroke="#182126">'),
        }
        for label, (needle, replacement) in mutations.items():
            with self.subTest(label=label), tempfile.TemporaryDirectory() as directory:
                shutil.copytree(TRACKED_ASSETS, Path(directory) / "identity")
                path = Path(directory) / "identity" / "fight-mark-light.svg"
                path.write_text(
                    path.read_text(encoding="utf-8").replace(needle, replacement),
                    encoding="utf-8",
                )
                process = self.run_tool(VALIDATOR, path.parent, expected=1)
                self.assertIn(label, process.stderr.lower())

    def test_that_validator_rejects_accessible_names_which_target_artwork(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            shutil.copytree(TRACKED_ASSETS, Path(directory) / "identity")
            path = Path(directory) / "identity" / "source" / "fight-identity-source.svg"
            path.write_text(
                path.read_text(encoding="utf-8").replace(
                    'aria-labelledby="source-title source-desc"',
                    'aria-labelledby="mark family-wordmark"',
                ),
                encoding="utf-8",
            )

            process = self.run_tool(VALIDATOR, path.parents[1], expected=1)
            self.assertIn("accessible title and description", process.stderr.lower())

    def test_that_validator_rejects_foreign_namespaces_with_svg_local_names(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            shutil.copytree(TRACKED_ASSETS, Path(directory) / "identity")
            path = Path(directory) / "identity" / "source" / "fight-identity-source.svg"
            path.write_text(
                path.read_text(encoding="utf-8").replace(
                    '<title id="source-title">',
                    '<x:title xmlns:x="urn:not-svg" id="source-title">',
                ).replace("</title>", "</x:title>", 1),
                encoding="utf-8",
            )

            process = self.run_tool(VALIDATOR, path.parents[1], expected=1)
            self.assertIn("foreign namespace", process.stderr.lower())

    def test_that_validator_rejects_open_or_interrupted_path_contours(self) -> None:
        mutations = {
            "open contour": (' Z"/>', '"/>'),
            "interrupted contour": (' L 218.17 22.91', ' M 218.17 22.91'),
        }
        for label, (needle, replacement) in mutations.items():
            with self.subTest(label=label), tempfile.TemporaryDirectory() as directory:
                shutil.copytree(TRACKED_ASSETS, Path(directory) / "identity")
                path = Path(directory) / "identity" / "source" / "fight-identity-source.svg"
                path.write_text(
                    path.read_text(encoding="utf-8").replace(needle, replacement, 1),
                    encoding="utf-8",
                )

                process = self.run_tool(VALIDATOR, path.parents[1], expected=1)
                self.assertIn("invalid path geometry", process.stderr.lower())

    def test_that_every_svg_has_intrinsic_geometry_and_accessible_metadata(self) -> None:
        namespace = "{http://www.w3.org/2000/svg}"
        for path in sorted(TRACKED_ASSETS.rglob("*.svg")):
            root = element_tree.parse(path).getroot()
            with self.subTest(path=path.name):
                self.assertTrue(root.attrib.get("width"))
                self.assertTrue(root.attrib.get("height"))
                self.assertTrue(root.attrib.get("viewBox"))
                self.assertEqual("img", root.attrib.get("role"))
                labelled = root.attrib.get("aria-labelledby", "").split()
                self.assertEqual(2, len(labelled))
                title = root.find(f"{namespace}title")
                description = root.find(f"{namespace}desc")
                self.assertIsNotNone(title)
                self.assertIsNotNone(description)
                self.assertEqual([title.attrib.get("id"), description.attrib.get("id")], labelled)
                self.assertTrue((title.text or "").strip())
                self.assertTrue((description.text or "").strip())

    @staticmethod
    def files(directory: Path) -> dict[str, bytes]:
        return {
            path.relative_to(directory).as_posix(): path.read_bytes()
            for path in sorted(directory.rglob("*"))
            if path.is_file()
        }

    @staticmethod
    def png_pixels(data: bytes) -> bytes:
        offset = 8
        compressed = []
        while offset < len(data):
            length = struct.unpack(">I", data[offset : offset + 4])[0]
            kind = data[offset + 4 : offset + 8]
            if kind == b"IDAT":
                compressed.append(data[offset + 8 : offset + 8 + length])
            offset += 12 + length
        rows = zlib.decompress(b"".join(compressed))
        return b"".join(rows[index + 1 : index + 49] for index in range(0, len(rows), 49))

    def run_tool(self, tool: Path, *arguments: object, expected: int = 0) -> subprocess.CompletedProcess[str]:
        process = subprocess.run(
            [sys.executable, str(tool), *(str(argument) for argument in arguments)],
            cwd=ROOT,
            capture_output=True,
            check=False,
            encoding="utf-8",
        )
        self.assertEqual(expected, process.returncode, process.stderr)

        return process


if __name__ == "__main__":
    unittest.main()
