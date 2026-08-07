# ADR 0014: Deterministic Release Commands and Evidence

- Status: accepted
- Date: 2026-08-07

## Decision

Fight Common will expose one repository-owned `bin/release` executable with narrow subcommands for
inspection, planning, preparation, packaging, certification, publication, branch maintenance,
patching, forward-porting, and verification. The executable is one policy implementation, not one
unbounded mutating operation; human-guided skills select and explain subcommands but do not reproduce
release policy or translate failures into success.

Each release plan is immutable and content-addressed. `plan_id` is the SHA-256 digest of canonical
versioned JSON. Each execution has a separate unique `run_id`. Release artifacts live under the
existing `.runs/` convention; the release-specific subdirectory shape remains an implementation detail.

Every command invocation emits a versioned machine-readable JSON result with stable coarse exit codes.
Human-readable output is only a rendering of that result. Mutating commands support dry-run validation
that emits the exact intended effects without performing them. A verified already-satisfied postcondition
is idempotent success; dry-run output is not evidence that an effect occurred.

Run state is represented by append-only transition events plus an atomically replaced current-state
projection. Resume revalidates every bound input and every completed postcondition. Any external effect
followed by uncertainty or incomplete verification enters persistent `partial_publication` and requires
human-directed reconciliation.

The state vocabulary is explicit and reachable: progress moves through `planned`, `prepared`,
`packaged`, `certified`, publication stages, and `verified`. Stop states are `stale_plan`,
`policy_blocked`, `authority_required`, `conflict`, `certification_failed`, `evidence_indeterminate`,
`partial_publication`, `external_state_unverifiable`, `superseded`, and `stopped_at_eol`. Each state
reports the next permitted operation or required human action; a command may not collapse these cases
into one generic failure.

The compact immutable evidence manifest is the machine authority. Its canonical JSON has a SHA-256
`manifest_id`, and publication authorization binds that digest. Bounded, redacted detailed logs are
supporting material only. Certification composes explicit named lanes for the complete quality gate,
Composer resolution modes, archive installation and reproducibility, planning and compatibility
evidence, and Git/ref verification. No single external CI status substitutes for the composed evidence.

Publication requires an exact authorization record bound to the plan, candidate and baseline object IDs,
version, evidence-manifest digest, and compatibility exceptions. GitHub immutable releases and an
approval-protected environment are prerequisites when available without added cost; publication remains
blocked until those hosted controls are verified. Command tests use injected filesystem, Git, signing,
hashing, clock, authorization, GitHub, and Packagist ports with fake providers, effect ledgers, and crash
points; production credentials are unavailable to the test process.

## Consequences

Release skills and CI share one deterministic policy contract, while state, evidence, authorization, and
external recovery remain explicit and inspectable. The command design can support local dry runs and
resumable operations without treating local success or provider ambiguity as publication proof.

The manifest and required release reports are retained with the immutable GitHub release for the
release's lifetime. Detailed logs remain bounded, redacted, digest-linked, and subject to a later
explicit retention period. The exact release artifact schema, storage lock and atomic-write mechanism,
event-chain integrity, signer and key custody, archive normalization, hosted entitlement, and Packagist
polling/recovery procedure remain implementation decisions for later release-coordination tickets.

## Rejected Alternatives

Several unrelated top-level release scripts were rejected because their schemas, policy loading, exit
semantics, and state transitions could drift. Trusting state labels or exit code zero without rechecking
postconditions was rejected because crashes and remote ambiguity can occur after an effect. Treating raw
logs or one external CI status as the evidence authority was rejected because neither is a compact,
stable, complete release record.
