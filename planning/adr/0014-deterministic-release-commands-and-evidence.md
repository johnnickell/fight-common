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
Evidence requirements bind explicit, extensible lane identifiers: 1 to 128 lowercase ASCII characters,
starting with a letter and composed of alphanumeric segments separated by a single dot or hyphen. The plan
rejects empty, malformed, or duplicate identifiers and canonicalizes their order without owning a closed lane
registry.

Immutable plan creation uses a repository-owned helper in the canonical Linux runtime. The helper opens the
canonical `.runs/` root and each relative output component with no-follow directory descriptors and rejects dot,
dot-dot, and symbolic-link traversal. The shell-free PHP producer binds the standard-input frame to a canonical ASCII
decimal byte length and lowercase SHA-256 digest in the helper argument vector. That length grammar is one `0` or a
nonzero digit followed by zero or more ASCII digits, with no sign, whitespace, Unicode digits, or leading zero; the
value is bounded to 16 MiB. Numeric fault controls use the same grammar and may not exceed the framed byte length.
Before opening a stage, the helper reads exactly that length and rejects early EOF, surplus bytes, malformed framing,
or digest disagreement. It writes the
verified plan bytes to an exclusive random file inside an owner-held `0700` staging directory below the final parent.
Only after a complete write and file `fsync` does the helper atomically publish the staged inode to the final filename
without replacement, using `renameat2(RENAME_NOREPLACE)` where supported and an exclusive same-filesystem hard link
otherwise. After hard-link publication, the helper retains the staged file descriptor, verifies that the private
source name still identifies that inode, removes only that name, and `fsync`s both staging and final-parent
directories; an empty staging directory is then removed and the parent is `fsync`ed again. The owner-only staging
authority excludes untrusted pathname replacement during that identity check and cleanup. A detected test-time
substitution fails closed and is retained rather than unlinked, while the already-published inode remains unchanged.
Partial producer or helper writes therefore never occupy the final digest name, and successful publication retains no
private stage. Failed or colliding private stages may be retained; they cannot poison a final-name retry. Artifact
inspection is one descriptor-relative,
no-follow read that distinguishes a missing final entry from regular content and a governed stop, so planning never
uses an exists-then-read pathname race. Reads enforce the same 16 MiB artifact bound as writes, consume at most one
byte beyond that bound in fixed-size chunks, and never send oversized content through the PHP process transport. A
regular-file publication collision remains provisional until the Application canonically re-reads and re-hashes the
winning artifact through the held-parent protocol. Helper status `30` means atomic final publication completed before
identity, durability, cleanup, or process-protocol verification became uncertain; it is never an ordinary write
failure. The write ledger records uncertainty, and the Application independently descriptor-reads and hashes the final
name: exact expected bytes resolve to verified created success, different bytes are a conflict, and a missing or
unclassifiable final state remains uncertainty.

Every normally completed command invocation emits a versioned machine-readable JSON result with stable coarse
exit codes. A deliberately configured deterministic boundary crash is an abnormal test interruption: it records
the attempted effect and throws before normal JSON, later effects, artifacts, or persisted postconditions.
Canonical-runtime bootstrap failure is the only exit `70` result. It reports status `infrastructure_unavailable`,
exit class `failed`, finding `release.runtime.bootstrap_unavailable`, empty postcondition and effect collections,
no inspection or plan success fields, and the single next action `restore_release_runtime_and_retry`; its command
and capability are normalized to the `inspect`, `plan`, or `unknown` command family.
After the canonical PHP process or release container has started, a generic fatal error, resource termination,
signal, helper termination, non-governed exit, or missing, malformed, or unauthenticated normal-result sideband is
instead exit `71`: status `infrastructure_terminated`, exit class `failed`, finding
`release.runtime.result_unavailable`, empty postcondition and effect collections, no inspection or plan success
fields, and the single next action `inspect_release_runtime_termination`. Exit `70` is never used for a runtime
that started. An authenticated governed normal result retains its declared exit, and an authenticated configured
crash retains exit `86` without normal JSON.
Human-readable output is only a rendering of a normally completed result. Mutating commands support dry-run
validation that emits the exact intended effects without performing them. A verified already-satisfied
postcondition is idempotent success; dry-run output is not evidence that an effect occurred.

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
explicit retention period. The exact release artifact schema, run-state storage lock and projection-replacement
mechanism, event-chain integrity, signer and key custody, archive normalization, hosted entitlement, and
Packagist polling/recovery procedure remain implementation decisions for later release-coordination tickets.

## Rejected Alternatives

Several unrelated top-level release scripts were rejected because their schemas, policy loading, exit
semantics, and state transitions could drift. Trusting state labels or exit code zero without rechecking
postconditions was rejected because crashes and remote ambiguity can occur after an effect. Treating raw
logs or one external CI status as the evidence authority was rejected because neither is a compact,
stable, complete release record.
