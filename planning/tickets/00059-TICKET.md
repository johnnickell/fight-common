---
id: TICKET-00059
epic: EPIC-00014
title: Retire Python wherever practical
status: ready-for-agent
---

# Retire Python wherever practical

## Problem statement

Common maintainers use a mix of Python, shell and PHP for project tooling. Porting the quality runner alone would leave most Python paths unevaluated.

## Solution and boundaries

Inventory project-owned Python files, entry points and embedded snippets across build, planning, runtime and database lifecycle, docs and identity, and release tooling. For each, remove unused code, replace it with maintainable PHP or a thin host wrapper where practical, or document precisely why it should remain and what alternatives were ruled out. Third-party Python tools such as MkDocs are not themselves project-owned code; their presence does not justify retaining our scripts by default.

Move the quality runner first. Keep `bin/*` host-callable and use container-run PHP with Composer dev autoload where possible. Keep host-only operations on the host, regardless of language. Planning must remain offline and work before images or `vendor/` exist without introducing a new host prerequisite. Do not mount the Docker socket into the PHP service or change the installed consumer package surface.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Maintainer runs planning from a clean checkout | `./bin/planning-check`, write and archive commands as applicable | Read local records and Git identity | No package events | Only authorized write paths change planning records |
| Maintainer builds and checks the package | `./bin/build`, `./bin/build --force`, other affected wrappers | Inspect runtime and receipt identity | No package events | Existing runtime, build logs, reports and receipts retain their behavior |
| Maintainer audits retained Python | No product command | Read inventory and exception rationale | No package events | Every retained item has an explicit justification |

## Validation and permissions

Preserve clean-checkout and isolated-worktree behavior, offline checks, argument handling, failure exits, runtime ownership, coverage and receipt integrity, and consumer autoload. Preserve existing read/write and resource guards for affected commands. No release execution or publication authorized.

## Acceptance and evidence

Provide a complete inventory and disposition for each item. For exceptions, identify the actual prerequisite or measurable cost, alternatives tried or evaluated, and evidence for retaining Python. Show parity for each migrated path, separately from CI optimization timing. Include new PHP tooling in applicable checks and input hashing.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00221](../tasks/00221-TASK.md) | Inventory Python and move the quality runner to container PHP | done |
| [TASK-00222](../tasks/00222-TASK.md) | Retire or justify Python in offline planning and archive commands | done |
| [TASK-00223](../tasks/00223-TASK.md) | Retire or justify Python in host runtime and database tooling | done |
| [TASK-00224](../tasks/00224-TASK.md) | Retire or justify Python in docs and identity tooling | ready-for-agent |
| [TASK-00225](../tasks/00225-TASK.md) | Retire or justify Python in release helpers | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

[WF-051](../wayfinder/tickets/WF-051-draw-the-tooling-runtime-boundary.md) records runtime boundaries. The approved EPIC amendment expands retirement beyond the quality runner. Requirements and TASK decomposition are approved. TASK-00221 completed the inventory and quality runner migration. TASK-00222 documented why the offline planning and archive helpers remain in Python. TASK-00223 through TASK-00225 track the remaining dispositions.
