---
id: TICKET-00058
epic: EPIC-00014
title: Replace project context with a concise glossary
status: ready-for-agent
---

# Replace project context with a concise glossary

## Problem statement

Contributors and agents must read a long context document to find Common’s vocabulary. The same file also owns live contracts and historical references, so deleting it without a cutover would lose useful information.

## Solution and boundaries

Create one root `GLOSSARY.md` with short definitions grouped by capability. Move unique material to its owning docs; update agent guidance, live references, and both messaging compatibility manifest entries. Preserve the meaning and navigation of historical references. Then delete `CONTEXT.md` without a stub. Do not change API behavior or duplicate the old document.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Contributor looks up a Common term or contract | No runtime command | Read the glossary or owning docs | No runtime events | None at runtime |
| Maintainer verifies the cutover | Offline planning and applicable documentation and manifest checks | Inspect old references and new contract locations | No runtime events | Docs, agent guidance and manifest references replace the old file |

## Validation and permissions

Verify links and normative headings, and retain the existing event fan-out and metadata isolation semantics and fixtures. Coordinate shared manifest edits with the upgrade TICKET. No new application permissions or data migration.

## Acceptance and evidence

Show that the glossary is concise, `CONTEXT.md` is gone, live references resolve, and both messaging promises remain traceable to the same behavior.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00220](../tasks/00220-TASK.md) | Cut over to GLOSSARY.md and delete CONTEXT.md | in-progress |
<!-- /planning:children -->

## Decisions and progress

[WF-050](../wayfinder/tickets/WF-050-define-the-glossary-cutover.md) owns the approved cutover. Requirements and TASK decomposition are approved. No implementation has started.
