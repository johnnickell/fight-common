#!/usr/bin/env python3

"""Validate the GitHub Pages workflow that publishes the docs artifact."""

from __future__ import annotations

import copy
import re
import sys
from pathlib import Path
from typing import Any

import yaml


class WorkflowLoader(yaml.SafeLoader):
    """Use YAML 1.2-style booleans so GitHub's unquoted ``on`` remains a key."""


WorkflowLoader.yaml_implicit_resolvers = copy.deepcopy(yaml.SafeLoader.yaml_implicit_resolvers)
for initial, resolvers in WorkflowLoader.yaml_implicit_resolvers.items():
    WorkflowLoader.yaml_implicit_resolvers[initial] = [
        resolver
        for resolver in resolvers
        if resolver[0] != "tag:yaml.org,2002:bool"
    ]
WorkflowLoader.add_implicit_resolver(
    "tag:yaml.org,2002:bool",
    re.compile(r"^(?:true|True|TRUE|false|False|FALSE)$"),
    list("tTfF"),
)

EXPECTED_DEPLOY_CONDITION = "github.event_name == 'push' && github.ref == 'refs/heads/main'"
EXPECTED_DEPLOY_URL = "${{ steps.deployment.outputs.page_url }}"
EXPECTED_CONCURRENCY = {"group": "pages", "cancel-in-progress": False}


def fail(message: str) -> None:
    raise ValueError(message)


def mapping(value: Any, description: str) -> dict[str, Any]:
    if not isinstance(value, dict):
        fail(f"Documentation workflow {description} must be a mapping")

    return value


def steps(job: dict[str, Any], name: str) -> list[dict[str, Any]]:
    value = job.get("steps")
    if not isinstance(value, list) or not all(isinstance(step, dict) for step in value):
        fail(f"{name} job must define workflow steps")

    return value


def validate_event_contract(workflow: dict[str, Any]) -> None:
    events = mapping(workflow.get("on"), "on contract")
    if set(events) != {"pull_request", "push"}:
        fail("Documentation workflow must run for pull requests and pushes to main")

    push = mapping(events.get("push"), "push event")
    if set(push) != {"branches"} or push["branches"] != ["main"]:
        fail("Documentation workflow push event must target only main")


def validate_concurrency_contract(workflow: dict[str, Any]) -> None:
    if workflow.get("concurrency") != EXPECTED_CONCURRENCY:
        fail("Documentation workflow must serialize Pages deployments with {group: pages, cancel-in-progress: false}")


def validate_build_job(build: dict[str, Any]) -> None:
    if build.get("permissions") != {"contents": "read"}:
        fail("build job permissions must be exactly {contents: read}")

    build_steps = steps(build, "build")
    if len(build_steps) != 3:
        fail("build job must contain only checkout, docs validation, and artifact upload")

    checkout, validate, upload = build_steps
    if checkout.get("uses") != "actions/checkout@v4":
        fail("build job must check out with actions/checkout@v4")
    if validate.get("run") != "./bin/docs validate":
        fail("build job must validate docs with ./bin/docs validate")
    if upload.get("uses") != "actions/upload-pages-artifact@v5":
        fail("build job must upload with actions/upload-pages-artifact@v5")
    if upload.get("with") != {"path": "site"}:
        fail("upload-pages-artifact must upload exactly site/")


def validate_deploy_job(deploy: dict[str, Any]) -> None:
    if deploy.get("needs") != "build":
        fail("deploy job must need the build job")
    if deploy.get("if") != EXPECTED_DEPLOY_CONDITION:
        fail("deploy job must run only for pushes to refs/heads/main")
    if deploy.get("permissions") != {"pages": "write", "id-token": "write"}:
        fail("deploy job permissions must be exactly {pages: write, id-token: write}")

    environment = mapping(deploy.get("environment"), "deploy environment")
    if environment.get("name") != "github-pages":
        fail("deploy job must target the github-pages environment")
    if environment.get("url") != EXPECTED_DEPLOY_URL:
        fail("deploy job must expose the github-pages page_url")

    deploy_steps = steps(deploy, "deploy")
    if len(deploy_steps) != 1:
        fail("deploy job must contain only the Pages deployment action")
    deployment = deploy_steps[0]
    if deployment.get("id") != "deployment" or deployment.get("uses") != "actions/deploy-pages@v5":
        fail("deploy job must deploy with actions/deploy-pages@v5")


def validate_absence_of_direct_branch_deployment(workflow_text: str) -> None:
    if re.search(r"\b(?:mkdocs\s+gh-deploy|ghp-import|git\s+push|gh-pages)\b", workflow_text, re.IGNORECASE):
        fail("Documentation workflow must not publish a generated branch directly")


def validate(workflow_path: Path) -> None:
    try:
        workflow_text = workflow_path.read_text(encoding="utf-8")
    except OSError as error:
        fail(f"Documentation workflow cannot be read: {error}")

    try:
        workflow = yaml.load(workflow_text, Loader=WorkflowLoader)
    except yaml.YAMLError as error:
        fail(f"Documentation workflow YAML is invalid: {error}")

    workflow = mapping(workflow, "root")
    if "permissions" in workflow:
        fail("Documentation workflow must scope permissions to each job")

    validate_absence_of_direct_branch_deployment(workflow_text)
    validate_event_contract(workflow)
    validate_concurrency_contract(workflow)
    jobs = mapping(workflow.get("jobs"), "jobs")
    if set(jobs) != {"build", "deploy"}:
        fail("Documentation workflow must contain only build and deploy jobs")
    validate_build_job(mapping(jobs.get("build"), "build job"))
    validate_deploy_job(mapping(jobs.get("deploy"), "deploy job"))


def main(arguments: list[str]) -> int:
    if len(arguments) != 1:
        print("Usage: validate_docs_workflow.py WORKFLOW_PATH", file=sys.stderr)
        return 2

    try:
        validate(Path(arguments[0]))
    except ValueError as error:
        print(f"Documentation workflow validation failed: {error}", file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
