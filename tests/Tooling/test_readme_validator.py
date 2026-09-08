#!/usr/bin/env python3

"""Exercise the repository README contract in the pinned documentation runtime."""

from __future__ import annotations

import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
VALIDATOR = ROOT / "scripts" / "validate_readme.py"


class ReadmeValidatorTest(unittest.TestCase):
    def test_that_the_repository_readme_is_accepted_and_rendered(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "readme.html"
            result = self.validate(ROOT, output=output)

            self.assertEqual(0, result.returncode, result.stderr)
            self.assertTrue(output.is_file())
            rendered = output.read_text(encoding="utf-8")
            self.assertIn("Fight Common", rendered)
            self.assertIn("prefers-color-scheme: dark", rendered)

    def test_that_broken_local_paths_are_rejected(self) -> None:
        self.assert_invalid(
            lambda readme: readme.replace("(CHANGELOG.md)", "(MISSING-CHANGELOG)"),
            "README link target does not exist: MISSING-CHANGELOG",
        )

    def test_that_broken_local_fragments_are_rejected(self) -> None:
        self.assert_invalid(
            lambda readme: readme.replace("(CHANGELOG.md)", "(CHANGELOG.md#missing-release)"),
            "README link fragment does not exist: CHANGELOG.md#missing-release",
        )

    def test_that_missing_theme_assets_are_rejected(self) -> None:
        for asset in (
            "docs/assets/identity/fight-common-readme-dark.svg",
            "docs/assets/identity/fight-common-readme-light.svg",
        ):
            with self.subTest(asset=asset):
                self.assert_invalid(
                    lambda readme: readme,
                    f"README identity asset does not exist: {asset}",
                    missing_path=asset,
                )

    def test_that_swapped_theme_assets_are_rejected(self) -> None:
        self.assert_invalid(
            lambda readme: readme.replace(
                'src="docs/assets/identity/fight-common-readme-light.svg"',
                'src="docs/assets/identity/fight-common-readme-dark.svg"',
            ),
            "README fallback identity image must be docs/assets/identity/fight-common-readme-light.svg",
        )

    def test_that_empty_identity_alt_text_is_rejected(self) -> None:
        self.assert_invalid(
            lambda readme: readme.replace(
                'alt="Fight Common: the Inward Port mark beside the Fight Common wordmark"',
                'alt=""',
            ),
            "README identity fallback needs meaningful alternative text",
        )

    def test_that_stale_documentation_routes_are_rejected(self) -> None:
        self.assert_invalid(
            lambda readme: readme.replace("/quick-start/", "/quickstart/"),
            "README is missing the canonical Quick Start route",
        )

    def test_that_insecure_and_unsupported_links_are_rejected(self) -> None:
        self.assert_invalid(
            lambda readme: readme.replace(
                "https://github.com/johnnickell/fight-common/tree/main/src",
                "http://github.com/johnnickell/fight-common/tree/main/src",
            ),
            "README links must use HTTPS or repository-relative targets",
        )

    def test_that_unsupported_raw_html_is_rejected(self) -> None:
        self.assert_invalid(
            lambda readme: readme + "\n<div>Unsupported presentation wrapper</div>\n",
            "README raw HTML is limited to picture, source, and img",
        )

    def test_that_incorrect_badges_are_rejected(self) -> None:
        self.assert_invalid(
            lambda readme: readme.replace("PHP 8.5+", "PHP 8.4+"),
            "README must contain exactly the factual Tests, PHP 8.5+, and MIT badges",
        )

    def test_that_stale_internal_badges_are_rejected(self) -> None:
        self.assert_invalid(
            lambda readme: readme.replace(
                "[![License: MIT]",
                "[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg)](phpstan.neon.dist)\n[![License: MIT]",
            ),
            "README must contain exactly the factual Tests, PHP 8.5+, and MIT badges",
        )

    def test_that_contribution_copyright_and_license_signals_are_required(self) -> None:
        self.assert_invalid(
            lambda readme: readme.replace(
                "https://johnnickell.github.io/fight-common/maintenance/contributing/",
                "https://johnnickell.github.io/fight-common/maintenance/",
            ),
            "README is missing the canonical Contributing route",
        )
        self.assert_invalid(
            lambda readme: readme.replace("[MIT License](LICENSE)", "MIT License"),
            "README is missing the canonical License route",
        )
        self.assert_invalid(
            lambda readme: readme.replace("Copyright © 2026 John Nickell", "Maintained by John Nickell"),
            "README is missing its copyright signal",
        )

    def assert_invalid(self, mutate: object, expected_error: str, missing_path: str | None = None) -> None:
        with tempfile.TemporaryDirectory() as directory:
            fixture = Path(directory) / "repository"
            shutil.copytree(
                ROOT,
                fixture,
                ignore=shutil.ignore_patterns(".git", ".runs", "graphify-out", "site", "var", "vendor"),
            )
            readme_path = fixture / "README.md"
            readme = readme_path.read_text(encoding="utf-8")
            readme_path.write_text(mutate(readme), encoding="utf-8")  # type: ignore[operator]
            if missing_path is not None:
                (fixture / missing_path).unlink()

            result = self.validate(fixture)

            self.assertNotEqual(0, result.returncode)
            self.assertIn(expected_error, result.stderr)

    def validate(self, root: Path, output: Path | None = None) -> subprocess.CompletedProcess[str]:
        command = [sys.executable, str(VALIDATOR), str(root)]
        if output is not None:
            command.extend(["--output", str(output)])

        return subprocess.run(command, capture_output=True, check=False, encoding="utf-8")


if __name__ == "__main__":
    unittest.main()
