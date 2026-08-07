# Define supported release lines and the compatibility contract

**Labels:** `wayfinder:research`, `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** [Establish the release-coordination destination and standing boundaries](WF-001-release-destination-and-boundaries.md)

## Question

What exact, machine-checkable policy classifies a change as patch, minor, or major; identifies the
supported release lines affected by a defect; and determines which public API, behavioral,
dependency, PHP-platform, and Composer-constraint changes are permitted on each line?

## Must decide

- the public API surface and comparison baseline for each release class;
- treatment of documented behavior, deprecations, exception behavior, serialization, database and
  framework adapters, and the reusable coding standard;
- machine-readable `SUPPORTED_VERSIONS.md` structure, clocks, support transitions, and EOL behavior;
- how a fix declares affected lines and how tooling proves the oldest correct base;
- permitted patch exceptions, their evidence, and human approval;
- the minimum SemVer recommendation and exact human authorization contract;
- which Symfony maintenance principles fit Fight Common and which do not.

## Resolution

- Fight Common will use a machine-readable public API manifest. Its initial baseline contains every
  production-autoloaded declaration in the authoritative published `1.1.0` source at `fdd4806`
  unless that declaration was already marked `@internal`. Every later production declaration must
  be deliberately classified as public or internal. See
  [ADR 0009](../../adr/0009-public-api-manifest-baseline.md).
- The manifest records callable, constructible, extensible, and implementable as independent
  compatibility promises. Callability or constructibility does not implicitly promise that a type
  is safe to extend.
- A non-final class is extensible only with affirmative evidence: an intended abstract base or
  exception hierarchy, documentation or tests showing extension, a published subclass, or history
  showing that `final` was removed for consumer extension. Protected members are stable only on
  types classified as extensible; ambiguous `1.1.0` types require a history and known-consumer
  audit.
- Explicit consumer-facing claims in the published `1.1.0` README, documentation, and public PHPDoc
  are presumptively binding; accepted ADRs define behavior from their effective release onward.
  Stable behavioral claims receive contract IDs linking normative documentation to designated
  compatibility fixtures. Ordinary tests and incidental observed behavior do not create contracts.
  Contradictory or ambiguous evidence fails for human resolution. See
  [ADR 0010](../../adr/0010-behavioral-contract-authority.md).
- Exception families and documented structured diagnostics are stable, while prose messages are
  not unless explicitly contracted. Serialization promises semantic structure and backward reads,
  not byte identity by default. Patch and minor releases require no destructive or mandatory data
  rewrite, may not narrow accepted runtime or dependency environments, and preserve public
  framework wiring. Deprecations begin only in a minor, remain functional for at least one released
  minor, and may be removed only in a later major. See
  [ADR 0011](../../adr/0011-non-structural-compatibility-policy.md).
- `SUPPORTED_VERSIONS.md` will contain canonical fenced JSON using exclusive UTC support boundaries
  and immutable EOL history. Patch, minor, and major baselines are explicit canonical tags plus
  peeled OIDs, with ancestry required and no tag-order fallback. Future tags use only signed
  annotated `vX.Y.Z`; historical published `1.1.0` at `fdd4806` remains the authoritative exception,
  and its lineage must be reconciled before `v1.2.0` certification.
- Affected lines require cross-line behavioral evidence; an introducing commit may remain explicitly
  unknown. Incompatible patches are limited to exact approved security, imminent data-loss, or
  critical interoperability exceptions. Human SemVer authorization binds the exact version,
  candidate, baselines, evidence-manifest digest, and exception IDs. Fight Common adopts Symfony's
  compatibility discipline and oldest-line-first principle without its cadence, LTS, future-PHP,
  inheritance, or governance assumptions. See
  [ADR 0012](../../adr/0012-supported-lines-baselines-and-release-authorization.md).
- Patch, minor, and major classification follows consumer-visible SemVer effects. The committed
  `compatibility/manifest.json` intentionally owns API operations, behavioral contract references,
  package promises, and baseline identity. A repository command composes structural, manifest,
  Composer, behavioral, dependency-lane, and static-analysis evidence; no single checker owns
  policy. Indeterminate findings and unexpected archive drift fail closed. See
  [ADR 0013](../../adr/0013-compatibility-manifest-and-certification-composition.md).

## Acceptance scenarios

- Adding an intentionally classified public feature without changing an existing contract requires
  at least a minor release; removing or incompatibly changing a manifested contract requires a
  major release.
- Making a non-final class `final` is breaking only when the manifest promises extensibility;
  changing a protected member is breaking only for a manifested extensible type.
- A signature-preserving change that contradicts an explicit behavioral contract cannot ship as a
  patch or minor merely because structural comparison passes.
- Removing a serialized field, making existing persisted data unreadable, narrowing an accepted
  Composer constraint, or enabling a stricter default coding-standard rule requires a major
  release.
- A new deprecation begins in a minor, remains functional for at least one released minor, and may
  be removed only in a later major.
- A defect with an unknown introducing commit may be patched when fixtures prove every affected and
  unaffected supported line and the fix passes on the oldest affected line.
- A baseline whose tag is ambiguous, duplicate-normalized, moving, missing, or not an ancestor of
  the candidate blocks certification; tooling never chooses another tag automatically.
- An incompatible patch without an exact approved security, imminent data-loss, or critical
  interoperability exception blocks certification. Changing the candidate, version, baseline, or
  evidence digest invalidates the exception and release authorization.
- A supported line becomes read-only at its exclusive UTC `ends_at` instant. Unfinished release work
  stops unless a reviewed policy change extended support before that instant.
- Any compatibility finding that tooling cannot classify blocks certification until evidence or an
  exact approved exception resolves it.
