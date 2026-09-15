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
The parent TASK retains responsibility for the complete outcome.

## Completion notes

Record actual verification, review state, PR, and remaining decisions. Do not imply merge or deployment from
completed implementation. Refresh the generated views after updating metadata.
