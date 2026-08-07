# Design deterministic release commands and evidence

**Labels:** `wayfinder:research`, `wayfinder:grilling`, `wayfinder:prototype`, `wayfinder:domain-modeling`
**Mode:** AFK -> HITL
**Status:** Closed
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

## Resolution

Fight Common will expose one repository-owned `bin/release` executable with narrow subcommands for
inspection, planning, preparation, packaging, certification, publication, branch maintenance,
patching, forward-porting, and verification. Release skills orchestrate these commands and explain
their results; they do not reproduce release policy or convert failures into success.

Release plans are immutable content-addressed canonical JSON documents identified by `plan_id`; each
execution has a separate `run_id` and stores artifacts under the existing `.runs/` convention. Every
command invocation emits a versioned machine-readable result with stable coarse exit codes. Mutating
commands support dry-run validation, and already-satisfied postconditions are idempotent success.

Run state uses append-only transition events plus an atomically replaced current-state projection.
Resume revalidates bound inputs and completed postconditions. Progress states move through planning,
preparation, packaging, certification, publication stages, and verification. Explicit stop states
distinguish stale plans, policy failures, missing authority, conflicts, failed or indeterminate
evidence, partial publication, unverifiable external state, superseded plans, and support-line expiry.

The compact immutable evidence manifest is the machine authority and has a canonical SHA-256
`manifest_id` bound by publication authorization. Detailed logs are bounded, redacted, digest-linked,
and supporting only. Certification composes named full-gate, Composer, archive, planning/API,
compatibility, and Git/ref evidence lanes; no single external CI result is sufficient.

Publication requires exact authorization bound to the plan, candidate and baseline object IDs, version,
evidence digest, and exceptions. GitHub immutable releases and an approval-protected environment are
prerequisites when available without added cost. External uncertainty enters `partial_publication` and
requires human-directed reconciliation. Tests use injected filesystem, Git, signing, hashing, clock,
authorization, GitHub, and Packagist ports with fake providers, effect ledgers, and crash points.

The exact release artifact schema, storage locking, event-chain integrity, detailed-log retention,
signer custody, archive normalization, hosted entitlement, and Packagist observation/recovery policy
are intentionally deferred to the implementation handoff and dependent publication/runbook tickets.

See the [WF-003 research artifact](../research/WF-003-deterministic-release-commands-and-evidence-research.md)
and [ADR 0014](../../adr/0014-deterministic-release-commands-and-evidence.md).
