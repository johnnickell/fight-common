#!/usr/bin/env python3
"""Qualify the maintainer PHPCS policy against real source, outside the product gate."""

import json
import os
from pathlib import Path
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[1]
COMPLIANT = r"""<?php

declare(strict_types=1);

namespace Fight\Common;

/**
 * Class PhpcsEnforcementProbe
 */
final class PhpcsEnforcementProbe
{
    /**
     * @inheritDoc
     */
    public function handle(): void
    {
    }
}
"""


class PhpcsEnforcementTest(unittest.TestCase):
    def scan(self, source):
        result = subprocess.run(
            [
                "docker", "run", "--rm", "-i", "-v", f"{ROOT}:/app:ro", "-w", "/app",
                os.environ.get("FIGHT_COMMON_PHP_IMAGE", "fight-common"),
                "php", "vendor/bin/phpcs", "--standard=phpcs.xml", "--report=json",
                "--stdin-path=src/PhpcsEnforcementProbe.php", "-",
            ],
            input=source, text=True, capture_output=True, check=False, timeout=60,
        )
        self.assertIn(result.returncode, (0, 1, 2, 3), result.stderr + result.stdout)
        report = json.loads(result.stdout)
        self.assertEqual(len(report["files"]), 1, result.stdout)
        messages = next(iter(report["files"].values()))["messages"]
        return result.returncode, {message["source"] for message in messages}

    def test_compliant_source_passes(self):
        self.assertEqual(self.scan(COMPLIANT), (0, set()))

    def test_inline_inherited_documentation_fails(self):
        source = COMPLIANT.replace("/**\n     * @inheritDoc\n     */", "/** @inheritDoc */")
        status, diagnostics = self.scan(source)
        self.assertNotEqual(status, 0)
        self.assertTrue({
            "Generic.Commenting.DocComment.ContentAfterOpen",
            "Generic.Commenting.DocComment.ContentBeforeClose",
        }.issubset(diagnostics), diagnostics)

    def test_line_length_warnings_fail(self):
        constant = "    private const string LABEL = '" + ("x" * 100) + "';\n\n"
        source = COMPLIANT.replace("    /**", constant + "    /**", 1)
        status, diagnostics = self.scan(source)
        self.assertNotEqual(status, 0)
        self.assertIn("Generic.Files.LineLength.TooLong", diagnostics)

    def test_annotations_cannot_hide_inline_documentation(self):
        for annotation in (
            "// phpcs:ignoreFile",
            "// phpcs:disable",
            "// phpcs:disable Generic.Commenting.DocComment",
            "// phpcs:ignore Generic.Commenting.DocComment",
        ):
            with self.subTest(annotation=annotation):
                source = COMPLIANT.replace(
                    "/**\n     * @inheritDoc\n     */",
                    annotation + "\n    /** @inheritDoc */",
                )
                status, diagnostics = self.scan(source)
                self.assertNotEqual(status, 0)
                self.assertIn("Generic.Commenting.DocComment.ContentAfterOpen", diagnostics)


if __name__ == "__main__":
    unittest.main(verbosity=2)
