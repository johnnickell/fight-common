#!/usr/bin/env python3
"""Validate planning records and refresh their Markdown views with --write."""

from __future__ import annotations

import argparse
import os
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLANNING = ROOT / "planning"
VALID_STATUSES = {
    "needs-triage", "needs-info", "ready-for-agent", "ready-for-human",
    "in-progress", "done", "wontfix",
}
TERMINAL = {"done", "wontfix"}
KINDS = {"epics": "EPIC", "tickets": "TICKET", "tasks": "TASK"}
LINK = re.compile(r"\!?\[[^\]]*\]\(([^\s)]+)\)")
BLOCK = re.compile(r"<!-- planning:([a-z-]+) -->\n[\s\S]*?<!-- /planning:\1 -->")


def frontmatter(path: Path) -> dict[str, str]:
    text = path.read_text(encoding="utf-8")
    if not text.startswith("---\n") or "\n---\n" not in text[4:]:
        raise ValueError("missing frontmatter")
    block = text[4:].split("\n---\n", 1)[0]
    values: dict[str, str] = {}
    for line in block.splitlines():
        key, separator, value = line.partition(":")
        if not separator or key.strip() in values:
            raise ValueError(f"invalid or duplicate frontmatter field: {line}")
        values[key.strip()] = value.strip()
    return values


def cell(value: str) -> str:
    return value.replace("|", "&#124;").replace("\n", " ") or "—"


def linked(source: Path, target: Path, label: str) -> str:
    return f"[{cell(label)}]({os.path.relpath(target, source.parent).replace(os.sep, '/')})"


def table(headers: list[str], rows: list[list[str]]) -> str:
    lines = ["| " + " | ".join(headers) + " |", "|" + "---|" * len(headers)]
    lines.extend("| " + " | ".join(row) + " |" for row in rows)
    if not rows:
        lines.append("| " + " | ".join(["None", *["—"] * (len(headers) - 1)]) + " |")
    return "\n".join(lines)


def dependencies(data: dict[str, str]) -> list[str]:
    return [item.strip() for item in data.get("blocked_by", "").split(",") if item.strip()]


def metadata(path: Path, field: str) -> str:
    match = re.search(rf"^\*\*{re.escape(field)}:\*\*\s*(.*?)$", path.read_text(), re.MULTILINE)
    return match[1].strip() if match else ""


def load_records() -> tuple[dict, list[str]]:
    records = {}
    errors = []
    for directory, kind in KINDS.items():
        for path in sorted((PLANNING / directory).rglob("*.md")):
            if path.name.startswith("_") or path.name in {"README.md", "BOARD.md"}:
                continue
            try:
                data = frontmatter(path)
                identifier = data.get("id", "")
                if not re.fullmatch(rf"{kind}-\d{{5}}", identifier):
                    raise ValueError(f"invalid {kind} identifier: {identifier}")
                if path.name != f"{identifier.split('-')[1]}-{kind}.md":
                    raise ValueError("filename and identifier disagree")
                if identifier in records:
                    raise ValueError(f"duplicate identifier: {identifier}")
                if data.get("status") not in VALID_STATUSES or not data.get("title"):
                    raise ValueError("missing title or invalid status")
                if data.get("order") and (not data["order"].isdigit() or int(data["order"]) < 1):
                    raise ValueError("order must be a positive integer")
                if data.get("pr") and not re.fullmatch(r"https://[^\s|)]+/pull/\d+", data["pr"]):
                    raise ValueError("pr must be a pull-request URL")
                if "archive" in path.parts and data["status"] not in TERMINAL:
                    raise ValueError("archived record is not terminal")
                records[identifier] = (path, data)
            except ValueError as exception:
                errors.append(f"{path.relative_to(ROOT)}: {exception}")
    for identifier, (path, data) in records.items():
        parent_key = "ticket" if identifier.startswith("TASK-") else "epic"
        parent = data.get(parent_key, "")
        parent_kind = "TICKET-" if parent_key == "ticket" else "EPIC-"
        if parent and (parent not in records or not parent.startswith(parent_kind)):
            errors.append(f"{identifier}: invalid {parent_key} parent {parent}")
        if identifier.startswith("TASK-") and not parent and data.get("kind") not in {"chore", "bug"}:
            errors.append(f"{identifier}: requires a TICKET parent or explicit standalone chore/bug kind")
        if identifier.startswith("TICKET-") and not parent:
            errors.append(f"{identifier}: requires an EPIC parent")
        if "prd" in data:
            errors.append(f"{identifier}: legacy prd field must be ticket")
        for blocker in dependencies(data):
            if blocker not in records or not blocker.startswith("TASK-"):
                errors.append(f"{identifier}: invalid TASK blocker {blocker}")
    visiting, visited = set(), set()

    def visit(identifier: str) -> None:
        if identifier in visiting:
            errors.append(f"dependency cycle at {identifier}")
            return
        if identifier in visited or identifier not in records:
            return
        visiting.add(identifier)
        for blocker in dependencies(records[identifier][1]):
            visit(blocker)
        visiting.remove(identifier)
        visited.add(identifier)

    for identifier in records:
        visit(identifier)
    decisions = sorted((PLANNING / "wayfinder/tickets").rglob("WF-*.md"))
    decision_ids = set()
    for decision in decisions:
        identifier = decision.name[:6]
        if identifier in decision_ids:
            errors.append(f"duplicate Wayfinder identifier: {identifier}")
        decision_ids.add(identifier)
        for field in ("Map", "Status", "Mode", "Labels", "Depends on"):
            if not metadata(decision, field):
                errors.append(f"{decision.relative_to(ROOT)}: missing {field}")
        owner = LINK.search(metadata(decision, "Map"))
        map_path = (decision.parent / owner[1]).resolve() if owner else None
        if not map_path or not map_path.is_file() or not re.search(r"^\*\*Label:\*\*.*wayfinder:map", map_path.read_text(), re.M):
            errors.append(f"{decision.relative_to(ROOT)}: requires an owning Wayfinder map")
        for target in LINK.findall(metadata(decision, "Depends on")):
            dependency = (decision.parent / target).resolve()
            if dependency not in [path.resolve() for path in decisions]:
                errors.append(f"{decision.relative_to(ROOT)}: invalid Wayfinder dependency {target}")
    return records, errors


def projections(records: dict) -> dict[tuple[Path, str], str]:
    views = {}
    board = PLANNING / "tasks/BOARD.md"
    tasks = [(key, path, data) for key, (path, data) in records.items()
             if key.startswith("TASK-") and "archive" not in path.parts]
    tasks.sort(key=lambda row: (int(row[2].get("order") or 999999), row[0]))
    groups = {name: [] for name in ("Active Work", "Ready Frontier", "Waiting", "Needs Info", "Human Action", "Needs Triage", "Recently Closed")}
    for identifier, path, data in tasks:
        blockers = [key for key in dependencies(data) if records[key][1]["status"] not in TERMINAL]
        status = data["status"]
        group = {"in-progress": "Active Work", "needs-info": "Needs Info", "ready-for-human": "Human Action", "needs-triage": "Needs Triage"}.get(status)
        group = group or ("Recently Closed" if status in TERMINAL else "Waiting" if blockers else "Ready Frontier")
        parent = data.get("ticket")
        parent_label = linked(board, records[parent][0], f'{parent} — {records[parent][1]["title"]}') if parent else "— (standalone " + data.get("kind", "task") + ")"
        groups[group].append([
            cell(data.get("order", "")), linked(board, path, identifier), cell(data["title"]), parent_label,
            cell(status), ", ".join(linked(board, records[key][0], key) for key in blockers) or "—",
            f'[PR #{data["pr"].rsplit("/", 1)[1]}]({data["pr"]})' if data.get("pr") else "—",
        ])
    views[board, "board"] = "\n\n".join(f"## {name}\n\n" + table(
        ["Order", "TASK ID", "Title", "Parent TICKET", "Status", "Blocked by", "PR"], rows
    ) for name, rows in groups.items())

    for directory, kind in KINDS.items():
        for archived in (False, True):
            source = PLANNING / directory / ("archive/README.md" if archived else "README.md")
            rows = []
            for identifier, (path, data) in sorted(records.items()):
                if not identifier.startswith(kind + "-") or ("archive" in path.parts) != archived:
                    continue
                parent = data.get("ticket") or data.get("epic")
                rows.append([linked(source, path, identifier), cell(data["title"]), cell(data["status"]),
                             linked(source, records[parent][0], parent) if parent else "—"])
            views[source, "records"] = table(["ID", "Title", "Status", "Parent"], rows)

    roadmap = PLANNING / "ROADMAP.md"
    rows = [[linked(roadmap, path, identifier), cell(data["title"]), cell(data.get("target", "")), cell(data["status"])]
            for identifier, (path, data) in sorted(records.items())
            if identifier.startswith("EPIC-") and "archive" not in path.parts]
    views[roadmap, "epics"] = table(["EPIC ID", "Title", "Target", "Status"], rows)

    for identifier, (path, data) in records.items():
        if "archive" in path.parts or identifier.startswith("TASK-"):
            continue
        parent_key = "epic" if identifier.startswith("EPIC-") else "ticket"
        rows = [[linked(path, child, key), cell(meta["title"]), cell(meta["status"])]
                for key, (child, meta) in sorted(records.items()) if meta.get(parent_key) == identifier]
        views[path, "children"] = table(["ID", "Title", "Status"], rows)

    maps = sorted(path for path in (PLANNING / "wayfinder").rglob("*.md")
                  if not path.name.startswith("_") and re.search(r"^\*\*Label:\*\*.*wayfinder:map", path.read_text(), re.M))
    decisions = sorted((PLANNING / "wayfinder/tickets").rglob("WF-*.md"))
    for path in maps:
        rows = []
        for decision in decisions:
            map_link = LINK.search(metadata(decision, "Map"))
            if not map_link or (decision.parent / map_link[1]).resolve() != path.resolve():
                continue
            identifier = decision.name[:6]
            title = decision.read_text().splitlines()[0].lstrip("# ")
            deps = []
            for target in LINK.findall(metadata(decision, "Depends on")):
                other = (decision.parent / target).resolve()
                deps.append(linked(path, other, other.name[:6]))
            rows.append([linked(path, decision, identifier), cell(title), cell(metadata(decision, "Labels").replace("`", "")),
                         cell(metadata(decision, "Mode")), cell(metadata(decision, "Status")), ", ".join(deps) or "—"])
        views[path, "decisions"] = table(["Decision ID", "Title", "Type", "Mode", "Status", "Depends on"], rows)
    for archived in (False, True):
        source = PLANNING / "wayfinder" / ("archive/maps/README.md" if archived else "README.md")
        rows = [[linked(source, path, path.stem), cell(metadata(path, "Status"))] for path in maps
                if ("archive" in path.parts) == archived]
        views[source, "maps"] = table(["Map", "Status"], rows)
    return views


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--write", action="store_true", help="refresh generated views after validating records")
    args = parser.parse_args()
    records, errors = load_records()
    for path in PLANNING.rglob("*.md"):
        if path.name.startswith("_"):
            continue
        for target in LINK.findall(path.read_text()):
            destination = target.partition("#")[0]
            if destination.endswith(".md") and not destination.startswith(("/", "http:", "https:", "mailto:")):
                if not (path.parent / destination).is_file():
                    errors.append(f"{path.relative_to(ROOT)}: broken local Markdown link {target}")
    ignored = subprocess.run(["git", "-c", f"safe.directory={ROOT.resolve()}", "check-ignore", "-q", ".runs/planning-check"], cwd=ROOT, check=False)
    if ignored.returncode:
        errors.append(".runs/ must be gitignored")
    if not errors:
        pending = {}
        for (path, name), content in projections(records).items():
            if not path.is_file():
                errors.append(f"missing view: {path.relative_to(ROOT)}")
                continue
            text = path.read_text()
            matches = [match for match in BLOCK.finditer(text) if match[1] == name]
            if len(matches) != 1:
                errors.append(f"{path.relative_to(ROOT)}: requires one planning:{name} block")
                continue
            expected = f"<!-- planning:{name} -->\n{content}\n<!-- /planning:{name} -->"
            if matches[0][0] != expected:
                if args.write:
                    pending[path] = text[:matches[0].start()] + expected + text[matches[0].end():]
                else:
                    errors.append(f"{path.relative_to(ROOT)}: stale {name} view; run ./bin/planning-check --write")
        if not errors:
            for path, text in pending.items():
                path.write_text(text)
    if errors:
        print("Planning validation failed:")
        for error in errors:
            print(f"- {error}")
        return 1
    active = sum(data["status"] not in TERMINAL for _, data in records.values())
    print(f"Planning validation passed: {len(records)} records, {active} active; generated views {'refreshed' if args.write else 'current'}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
