---
id: TICKET-00060
epic: EPIC-00014
title: Provide the v1.5.1 to v2.0.0 upgrade path
status: ready-for-agent
---

# Provide the v1.5.1 to v2.0.0 upgrade path

## Problem statement

Consumers need to know which deprecated APIs disappear in 2.0, how to migrate before upgrading, and which formerly accepted inputs now fail.

## Solution and boundaries

Reconcile current contracts with published v1.5.1 and remove verified eligible deprecations. Require all `ResultSet` and `Pagination` construction to follow current `strict()` input rules. Use equivalent Domain helpers where appropriate. Add `Fight\Common\Domain\base64_url_encode()` and `base64_url_decode()`: canonical unpadded output, optional correct padding on decode, and `false` for malformed input. Do not change existing accepted wire or storage forms through helper substitution.

Provide a root `UPGRADE.md` grouped by component, with exact replacements or explicit no-replacement paths and pre-upgrade work for configuration, queues, workers or saved data where needed. Do not change dependency floors, remove unrelated APIs, silently migrate consumer data, or publish a release.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer prepares to upgrade | Composer upgrade only after consumer-owned prerequisite steps | Read migration guide, used API and stored/configured identities | No new events | Consumer updates imports, wiring or data as documented |
| Caller constructs a page or result set | Existing constructor or factory calls under strict rules | Not a new query API | None | Valid construction or documented rejection |
| Caller encodes or decodes bytes | Domain Base64URL helpers | No external query | None | Return encoded or decoded bytes, or `false` on malformed input |

## Validation and permissions

Verify release-window eligibility and canonical alias targets; test strict construction and accepted/rejected encoding forms. Check representative Fight Access Control and Fight Agent OS usage and relevant public behavior. Coordinate manifest edits with the glossary cutover. No new permission model; encoding is not authentication or integrity validation.

## Acceptance and evidence

Provide a tag-linked removal inventory, updated compatibility evidence, direct Unit coverage, and consumer checks. Trace each public break to its upgrade instruction and any pre-upgrade hazard. Preserve ordinary release verification separately.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00226](../tasks/00226-TASK.md) | Remove deprecated contracts and migrate their supported replacements | done |
| [TASK-00227](../tasks/00227-TASK.md) | Enforce strict ResultSet and Pagination construction | done |
| [TASK-00228](../tasks/00228-TASK.md) | Add and adopt the Base64URL helpers | done |
| [TASK-00229](../tasks/00229-TASK.md) | Verify the upgrade path and finish UPGRADE.md | in-progress |
<!-- /planning:children -->

## Decisions and progress

[WF-052](../wayfinder/tickets/WF-052-define-the-2-0-upgrade-contract.md) owns the approved scope. The child TASKs above record implementation progress; upgrade verification and the final guide remain with [TASK-00229](../tasks/00229-TASK.md).
