#!/usr/bin/env python3

"""Focused regression checks for documentation artifact parser caching."""

import importlib.util
import tempfile
import unittest
from pathlib import Path


VALIDATOR = Path(__file__).parents[2] / "scripts" / "validate_docs_artifact.py"
SPEC = importlib.util.spec_from_file_location("validate_docs_artifact", VALIDATOR)
assert SPEC is not None and SPEC.loader is not None
validator = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(validator)


class DocsArtifactValidatorTest(unittest.TestCase):
    def test_that_parsed_documents_are_reused_from_the_cache(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            document = Path(directory) / "index.html"
            document.write_text('<h1 id="start">Start</h1>', encoding="utf-8")
            cache = {}

            first = validator.parse_html(document, cache)
            second = validator.parse_html(document, cache)

            self.assertIs(first, second)


if __name__ == "__main__":
    unittest.main()
