# Design deterministic release commands and evidence

**Labels:** `wayfinder:research`, `wayfinder:grilling`, `wayfinder:prototype`
**Mode:** AFK -> HITL
**Status:** Open
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** [Establish the release-coordination destination and standing boundaries](WF-001-release-destination-and-boundaries.md)

## Question

Which release decisions and actions can repository-owned commands make deterministic, and what
evidence and state contract lets human-guided skills plan, resume, stop, and verify them safely?

## Must decide

- command boundaries for inspect, prepare, package, certify, publish, branch, patch, forward-port,
  and verify operations;
- dry-run behavior, deterministic inputs, preconditions, postconditions, exit codes, and idempotency;
- one-command-with-subcommands versus several narrow commands;
- immutable plan and run identifiers, stale-plan detection, state persistence, and safe resume;
- release-evidence manifest schema, bounded detailed logs, redaction, retention, and custody;
- exact full-gate, Composer-lane, archive-install, planning, API-diff, and Git evidence;
- stop states for conflicts, drift, missing authority, partial publication, and unverifiable state;
- test seams for commands without mutating production GitHub or Packagist state.

## Resolution boundary

Prototype schemas or state transitions when useful. Do not implement commands, install tools,
change CI, publish evidence, or perform Git mutations beyond the approved Wayfinder branch.
