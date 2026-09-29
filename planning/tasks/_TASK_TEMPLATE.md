---
id: TASK-NNNNN
ticket: TICKET-NNNNN
kind: feature
title: Brief executable outcome
status: needs-triage
order:
blocked_by:
pr:
---

# Brief executable outcome

## Outcome

State the bounded use case or chore and its independently reviewable outcome. A TASK normally owns one PR.

## Scope

- In scope:
- Out of scope:

## Use cases and contracts

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Describe the interaction | Name or justified N/A | Name or justified N/A | Name or justified N/A | Describe changes |

## Validation and permissions

State validation, permissions, and rejection behavior. Explain justified exclusions. Silence does not waive
the shared security process.

## Acceptance criteria

- [ ] Observable behavior or artifact, including relevant failure paths.

## Verification and evidence

Name focused checks, before/after evidence, and the canonical gate. For a bug, reproduce it and add one failing
regression test before fixing it. Tests cover production code and meaningful behavior; verify documentation
and tooling directly without adding tooling tests to the product suite.

## Coordination

Hand off dependency-ordered SUBTASKs under `.runs/`. Record deliberately separate SUBTASK PRs and their order.
The parent TASK retains responsibility for the complete outcome. For new work use `feature/task-NNNNN-<slug>`
and PR title `TASK-NNNNN — <TASK title>`; preserve established identities and release/patch conventions.

## Completion notes

At the implementation checkpoint, account for every acceptance criterion and required local verification before
marking `done`, before PR publication. A green build alone is insufficient. Record actual counts, warnings,
limitations, files changed and remaining risks. State pending independent review explicitly; record review,
publication, merge, release and deployment separately without implying approval or effect authority.

Link the ignored builder receipt and handoff; independent review uses the base-worktree canonical report and the
[Review contract](../../docs/engineering/standards/Review.md), not builder self-approval. Refresh generated views
after metadata changes. Preserve truthful historical outcomes rather than rewriting prior completion evidence.
