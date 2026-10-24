#!/usr/bin/env python3
"""Validate planning records and refresh their Markdown views with --write."""

from __future__ import annotations

import argparse
import datetime
import json
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
                if data.get("delivery"):
                    if kind != "TASK" or data["delivery"] != "awaiting-merge" or "archive" in path.parts:
                        raise ValueError("delivery must be awaiting-merge on a live TASK, or empty")
                    if not data.get("pr"):
                        raise ValueError("an awaiting-merge delivery handoff requires its PR URL")
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


def complete_parents(records: dict) -> dict[Path, str]:
    """Plan bottom-up parent closure from all live and archived child records."""
    pending = {}
    for kind, parent_key in (("TICKET-", "ticket"), ("EPIC-", "epic")):
        for identifier, (path, data) in sorted(records.items()):
            if not identifier.startswith(kind) or "archive" in path.parts or data["status"] in TERMINAL:
                continue
            children = [child for _, child in records.values() if child.get(parent_key) == identifier]
            if not children or any(child["status"] not in TERMINAL for child in children):
                continue
            status = "wontfix" if all(child["status"] == "wontfix" for child in children) else "done"
            text = path.read_text()
            header, body = text[4:].split("\n---\n", 1)
            header = re.sub(r"^status:[^\n]*$", f"status: {status}", header, count=1, flags=re.MULTILINE)
            pending[path] = f"---\n{header}\n---\n{body}"
            data["status"] = status
    return pending


def task_groups(records: dict) -> dict[str, list]:
    """One authoritative ordering and state classification for every TASK view."""
    tasks = [(key, path, data) for key, (path, data) in records.items()
             if key.startswith("TASK-") and "archive" not in path.parts]
    tasks.sort(key=lambda row: (not row[2].get("order"), int(row[2].get("order") or 0), row[0]))
    groups = {name: [] for name in ("Active Work", "Ready Frontier", "Waiting", "Needs Info", "Human Action", "Needs Triage", "Recently Closed")}
    for identifier, path, data in tasks:
        blockers = [key for key in dependencies(data) if records[key][1]["status"] not in TERMINAL]
        status = data["status"]
        group = {"in-progress": "Active Work", "needs-info": "Needs Info", "ready-for-human": "Human Action", "needs-triage": "Needs Triage"}.get(status)
        group = group or ("Recently Closed" if status in TERMINAL else "Waiting" if blockers else "Ready Frontier")
        groups[group].append((identifier, path, data, blockers))
    return groups


def routing_summary(source: Path, groups: dict[str, list], records: dict) -> str:
    """Select current work, not a workflow verdict or inferred delivery state."""
    selected = [(name, task) for name in ("Human Action", "Needs Info") for task in groups[name]]
    active, ready = groups["Active Work"], groups["Ready Frontier"]
    if active:
        selected.extend(("Active Work", task) for task in active)
    elif ready:
        selected.append(("Ready Frontier", ready[0]))
    else:
        for name in ("Needs Triage", "Waiting"):
            selected.extend((name, task) for task in groups[name][:1])
    rows = [[name, linked(source, path, identifier), cell(data["title"]), cell(data["status"]),
             ", ".join(linked(source, records[key][0], key) for key in blockers) or "—"]
            for name, (identifier, path, data, blockers) in selected]
    summary = table(["Route", "TASK", "Title", "Status", "Blocked by"], rows)
    if not active and not ready:
        summary += "\n\nNo TASK is currently executable; unresolved work may remain."
    return summary


def projections(records: dict) -> dict[tuple[Path, str], str]:
    views = {}
    board = PLANNING / "tasks/BOARD.md"
    groups = task_groups(records)
    sections = []
    for name, tasks in groups.items():
        rows = []
        for identifier, path, data, blockers in tasks:
            parent = data.get("ticket")
            parent_label = linked(board, records[parent][0], f'{parent} — {records[parent][1]["title"]}') if parent else "— (standalone " + data.get("kind", "task") + ")"
            rows.append([
                cell(data.get("order", "")), linked(board, path, identifier), cell(data["title"]), parent_label,
                cell(data["status"]), ", ".join(linked(board, records[key][0], key) for key in blockers) or "—",
                f'[PR #{data["pr"].rsplit("/", 1)[1]}]({data["pr"]})' if data.get("pr") else "—",
            ])
        sections.append(f"## {name}\n\n" + table(
            ["Order", "TASK ID", "Title", "Parent TICKET", "Status", "Blocked by", "PR"], rows
        ))
    views[board, "board"] = "\n\n".join(sections)
    views[board, "next"] = routing_summary(board, groups, records)

    handoffs = current_delivery(records)
    views[board, "delivery"] = table(["TASK", "Current delivery", "PR"], [
        [linked(board, path, identifier), "Awaiting human review/merge", f'[{data["pr"]}]({data["pr"]})']
        for identifier, path, data in handoffs
    ])

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
    views[roadmap, "execution"] = routing_summary(roadmap, groups, records)
    rows = [[linked(roadmap, path, identifier), cell(data["title"]), cell(data.get("target", "")), cell(data["status"])]
            for identifier, (path, data) in sorted(records.items())
            if identifier.startswith("EPIC-") and "archive" not in path.parts]
    views[roadmap, "epics"] = table(["EPIC ID", "Title", "Target", "Status"], rows)

    frontier = []
    for identifier, (path, data) in sorted(records.items()):
        if identifier.startswith("TASK-") or data["status"] in TERMINAL:
            continue
        parent_key = "epic" if identifier.startswith("EPIC-") else "ticket"
        children = [meta for _, meta in records.values() if meta.get(parent_key) == identifier]
        if not children:
            action = "Decompose into TICKETs" if parent_key == "epic" else "Decompose into TASKs"
        else:
            continue
        frontier.append([linked(roadmap, path, identifier), cell(data["title"]), cell(data["status"]), action])
    views[roadmap, "frontier"] = table(["Parent ID", "Title", "Status", "Planning action"], frontier)

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


def current_delivery(records: dict) -> list[tuple[str, Path, dict]]:
    """Select explicit current handoffs only, never historical prose or terminal status."""
    return [(identifier, path, data) for identifier, (path, data) in sorted(records.items())
            if data.get("delivery") == "awaiting-merge"]


def delivery_identity(records: dict) -> tuple[list[tuple[str, Path, dict]], str | None, list[str]]:
    handoffs = current_delivery(records)
    if not handoffs:
        return handoffs, None, []
    if len(handoffs) > 16:
        return handoffs, None, ["at most 16 current delivery handoffs may be verified per invocation"]
    try:
        origin = subprocess.run(["git", "-c", f"safe.directory={ROOT.resolve()}", "remote", "get-url", "origin"],
                                cwd=ROOT, capture_output=True, text=True, timeout=5, check=True).stdout.strip()
    except (OSError, subprocess.SubprocessError):
        return handoffs, None, ["delivery identity incomplete: cannot read the target origin; planning owner must resolve it"]
    match = re.fullmatch(r"(?:git@github\.com:|https://github\.com/)([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+?)(?:\.git)?", origin)
    if not match:
        return handoffs, None, ["delivery identity incomplete: origin must identify one GitHub repository"]
    repository = match[1]
    errors = []
    for identifier, path, data in handoffs:
        if not re.fullmatch(rf"https://github\.com/{re.escape(repository)}/pull/[1-9][0-9]*", data["pr"]):
            errors.append(f"{path.relative_to(ROOT)} ({identifier}): delivery PR identity does not match target origin")
    return handoffs, repository, errors


def verify_delivery(handoffs: list[tuple[str, Path, dict]], repository: str | None) -> list[str]:
    """Read bounded authoritative PR metadata without publishing or rewriting planning."""
    errors = []
    for identifier, path, data in handoffs:
        label = f"{path.relative_to(ROOT)} ({identifier})"
        number = int(data["pr"].rsplit("/", 1)[1])
        try:
            result = subprocess.run(
                ["gh", "pr", "view", str(number), "--repo", repository, "--json", "number,url,title,state,mergedAt"],
                cwd=ROOT, capture_output=True, text=True, timeout=15, check=True,
                env={**os.environ, "GH_PROMPT_DISABLED": "1"},
            )
            if len(result.stdout) > 16384:
                raise ValueError("oversized metadata")
            pr = json.loads(result.stdout)
            if (not isinstance(pr, dict) or type(pr.get("number")) is not int or pr["number"] != number
                    or pr.get("url") != data["pr"] or not isinstance(pr.get("title"), str)
                    or not pr["title"].startswith(identifier + " — ")):
                raise ValueError("identity mismatch")
            state = pr.get("state")
            if state not in {"OPEN", "MERGED", "CLOSED"} or "mergedAt" not in pr:
                raise ValueError("invalid state")
            if state == "MERGED":
                if not isinstance(pr["mergedAt"], str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z", pr["mergedAt"]):
                    raise ValueError("invalid merge time")
                datetime.datetime.fromisoformat(pr["mergedAt"].replace("Z", "+00:00"))
                errors.append(f"{label}: stale awaiting-merge handoff; PR #{number} is MERGED. Planning owner must reconcile delivery and refresh the Board.")
            elif pr["mergedAt"] is not None:
                raise ValueError("contradictory merge evidence")
            elif state == "CLOSED":
                errors.append(f"{label}: stale awaiting-merge handoff; PR #{number} is CLOSED WITHOUT MERGE. Planning owner must correct the handoff and refresh the Board.")
            else:
                print(f"Delivery fresh: {identifier} PR #{number} is OPEN; human review/merge remains pending.")
        except (OSError, subprocess.SubprocessError, ValueError, TypeError):
            # Raw CLI/API output may contain credentials or private diagnostics; never echo it.
            errors.append(f"{label}: delivery verification INCOMPLETE (tooling/authentication/API/metadata or identity unavailable); planning owner must recover authoritative evidence. No fresh pass or merge is inferred.")
    return errors


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument("--write", action="store_true", help="close eligible parents and refresh generated views after validating records")
    mode.add_argument("--check-delivery", action="store_true", help="read-only local validation plus live verification of explicit current delivery handoffs")
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
    handoffs, repository, identity_errors = delivery_identity(records)
    errors.extend(identity_errors)
    ignored = subprocess.run(["git", "-c", f"safe.directory={ROOT.resolve()}", "check-ignore", "-q", ".runs/planning-check"], cwd=ROOT, check=False)
    if ignored.returncode:
        errors.append(".runs/ must be gitignored")
    if not errors:
        closures = complete_parents(records)
        pending = dict(closures) if args.write else {}
        if not args.write:
            for path in closures:
                errors.append(f"{path.relative_to(ROOT)}: parent completion out of sync; run ./bin/planning-check --write")
        for (path, name), content in projections(records).items():
            if not path.is_file():
                errors.append(f"missing view: {path.relative_to(ROOT)}")
                continue
            text = pending.get(path, path.read_text())
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
            for path in closures:
                print(f"Completed parent: {path.relative_to(ROOT)}")
    if errors:
        print("Planning validation failed:")
        for error in errors:
            print(f"- {error}")
        return 1
    active = sum(data["status"] not in TERMINAL for _, data in records.values())
    print(f"Local planning validation passed (offline): {len(records)} records, {active} active; generated views {'refreshed' if args.write else 'current'}")
    if not args.check_delivery:
        print("Delivery freshness NOT CHECKED; routing publication/merge handoffs requires --check-delivery.")
        return 0
    delivery_errors = verify_delivery(handoffs, repository)
    if delivery_errors:
        print("Delivery verification failed or incomplete:")
        for error in delivery_errors:
            print(f"- {error}")
        return 1
    print(f"Delivery verification passed: {len(handoffs)} explicit current handoff(s); no historical PRs queried.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
