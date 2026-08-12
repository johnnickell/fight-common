# ADR 0015: Patch and Maintenance-Line Workflows

- Status: accepted
- Date: 2026-08-07

## Decision

Maintenance-line lifecycle and patch application are separate workflows. Maintenance owns branch
creation from the signed release commit, support and protection transitions, and EOL preservation.
Patch consumes an immutable, already-reviewed change set and never implements or reviews the fix.

Only supported lines may receive patches. Focused public behavioral evidence establishes affected
lines; ancestry alone is insufficient. The oldest affected line is patched first from its exact
current tip, using `patch/<version>-<slug>`. Each newer affected line receives a separate forward
port, pull request, and certification in oldest-to-newest order. Conflicts, missing provenance, or
semantic differences stop the sequence for human resolution.

Patch eligibility is evaluated independently per line. The current-line result reaches `main` by a
separate reviewed and certified forward-port PR based on `main`, so an older maintenance branch
cannot replace newer contents. EOL branches remain preserved and read-only.

An admissible fix binds exact commit OIDs and merged-PR review provenance, including base/head,
approvals, required-check conclusions, and merge receipt. Every affected line and forward port
requires its own compatibility, regression, quality-gate, Git, and PR evidence. Urgency and security
are metadata in the ordinary workflow. An incompatible patch requires an exact compatibility
exception; guided urgent mode reduces operator burden by collecting the ordinary proof and blockers
without weakening any gate.

## Consequences

The workflow is deterministic and traceable across divergent maintenance lines, while the operator
does not need to remember a separate emergency process. The command surface must explain the next
action and concrete blocker in a compact packet. Support-policy data and release plans remain the
authorities for line eligibility and bound approvals.

The repository must retain separate provenance and certification for each forward port. A source
fix may therefore require a line-specific adaptation or a higher release class rather than a blind
cherry-pick. Branch protection and publication remain separately authorized implementation effects.

## Rejected Alternatives

`hotfix/*` was rejected because it duplicates patch semantics and obscures review and provenance.

Patching every line with one unchanged commit was rejected because maintenance lines may have
semantic differences.

Merging an older maintenance branch wholesale into `main` was rejected because it can replace newer
contents. Urgency as a gate bypass was rejected because it makes the most consequential path the
least auditable.
