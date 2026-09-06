#!/usr/bin/env python3

"""Exercise the docs workflow validator in the pinned documentation runtime."""

from __future__ import annotations

import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


VALIDATOR = Path(__file__).resolve().parents[2] / "scripts" / "validate_docs_workflow.py"


class DocsWorkflowValidatorTest(unittest.TestCase):
    def test_that_the_known_good_pages_workflow_is_accepted(self) -> None:
        self.validate_workflow(known_good_workflow())

    def test_that_workflows_without_pull_requests_are_rejected(self) -> None:
        self.assert_invalid(
            known_good_workflow().replace("  pull_request:\n", ""),
            "must run for pull requests and pushes to main",
        )

    def test_that_workflows_without_pages_deployment_serialization_are_rejected(self) -> None:
        self.assert_invalid(
            known_good_workflow().replace(
                "concurrency:\n  group: pages\n  cancel-in-progress: false\n\n", ""
            ),
            "must serialize Pages deployments",
        )

    def test_that_workflows_that_cancel_an_active_pages_deployment_are_rejected(self) -> None:
        self.assert_invalid(
            known_good_workflow().replace("cancel-in-progress: false", "cancel-in-progress: true"),
            "must serialize Pages deployments",
        )

    def test_that_workflows_with_write_contents_are_rejected(self) -> None:
        self.assert_invalid(
            known_good_workflow().replace("contents: read", "contents: write"),
            "build job permissions must be exactly {contents: read}",
        )

    def test_that_workflows_with_an_unsafe_artifact_path_are_rejected(self) -> None:
        self.assert_invalid(
            known_good_workflow().replace("path: site", "path: public"),
            "upload-pages-artifact must upload exactly site/",
        )

    def test_that_workflows_that_deploy_from_pull_requests_are_rejected(self) -> None:
        self.assert_invalid(
            known_good_workflow().replace(
                "github.event_name == 'push' && github.ref == 'refs/heads/main'",
                "github.event_name == 'push'",
            ),
            "deploy job must run only for pushes to refs/heads/main",
        )

    def test_that_workflows_without_the_pages_environment_are_rejected(self) -> None:
        self.assert_invalid(
            known_good_workflow().replace("name: github-pages", "name: preview"),
            "deploy job must target the github-pages environment",
        )

    def test_that_workflows_with_direct_branch_deployment_are_rejected(self) -> None:
        self.assert_invalid(
            known_good_workflow().replace(
                "      - id: deployment",
                "      - run: mkdocs gh-deploy --force\n\n      - id: deployment",
            ),
            "must not publish a generated branch directly",
        )

    def assert_invalid(self, workflow: str, expected_error: str) -> None:
        with self.assertRaisesRegex(ValueError, expected_error):
            self.validate_workflow(workflow)

    def validate_workflow(self, workflow: str) -> None:
        with tempfile.TemporaryDirectory() as directory:
            workflow_path = Path(directory) / "docs.yml"
            workflow_path.write_text(workflow, encoding="utf-8")
            result = subprocess.run(
                [sys.executable, str(VALIDATOR), str(workflow_path)],
                capture_output=True,
                check=False,
                encoding="utf-8",
            )
            if result.returncode != 0:
                raise ValueError(result.stderr)


def known_good_workflow() -> str:
    return """name: Deploy Docs

on:
  pull_request:
  push:
    branches: [main]

concurrency:
  group: pages
  cancel-in-progress: false

jobs:
  build:
    runs-on: ubuntu-latest
    permissions:
      contents: read
    steps:
      - uses: actions/checkout@v4
      - run: ./bin/docs validate
      - uses: actions/upload-pages-artifact@v5
        with:
          path: site
  deploy:
    if: github.event_name == 'push' && github.ref == 'refs/heads/main'
    needs: build
    permissions:
      pages: write
      id-token: write
    environment:
      name: github-pages
      url: ${{ steps.deployment.outputs.page_url }}
    runs-on: ubuntu-latest
    steps:
      - id: deployment
        uses: actions/deploy-pages@v5
"""


if __name__ == "__main__":
    unittest.main()
