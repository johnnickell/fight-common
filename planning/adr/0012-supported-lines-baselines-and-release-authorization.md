# ADR 0012: Supported Lines, Baselines, and Release Authorization

- Status: accepted
- Date: 2026-08-06

## Decision

### Support-policy authority and clock

`SUPPORTED_VERSIONS.md` contains one fenced JSON block as its canonical machine-readable authority.
A human table is generated from or validated against that block. The data has a schema version and
one record per `major.minor` line containing its branch, initial and latest release tags and peeled
commit OIDs, phase, allowed fix classes, exclusive UTC `ends_at` instant, and successor.

At `ends_at`, unfinished release work stops. Continuing support requires a separately reviewed
policy commit that changes the boundary before it expires. End-of-life records remain immutable
history.

### Tags and comparison baselines

Future canonical release tags use the `vX.Y.Z` form and are signed annotated tags. A second tag that
normalizes to the same SemVer version is forbidden. The authoritative historical `1.1.0` exception
remains the bare annotated tag at `fdd4806`; the lightweight `v1.1.0` tag remains untouched legacy
history.

Compatibility uses these explicit baselines:

- a patch compares with the latest released patch on the same maintenance line;
- a minor compares with the latest release of the preceding minor in the same major;
- a major compares with the latest release of the preceding major to produce its migration
  inventory.

Every baseline records the canonical tag and peeled OID. The baseline must be an ancestor of the
candidate; missing, moving, ambiguous, duplicate-normalized, or non-ancestor references fail rather
than fall back to tag ordering. The `1.2.0` lineage must therefore be reconciled with the published
`1.1.0` commit before `v1.2.0` certification.

### Affected-line proof

A fix declares affected and unaffected supported lines and their immutable baseline OIDs. Focused
public behavioral evidence must reproduce the defect on every affected baseline, show its absence
on every applicable unaffected baseline, and pass with the fix on the oldest affected line. Every
forward port receives separate certification.

The exact introducing commit may remain explicitly unknown when this cross-line evidence is
complete. Tooling does not fabricate precision, and ancestry alone does not prove that a line is
affected.

### Patch compatibility exceptions

An incompatible patch is eligible for exception only for a security, imminent data-loss, or
critical interoperability failure for which no compatible repair exists. The exception records the
exact version, candidate and baseline OIDs, overridden finding IDs, consumer impact, mitigation,
tests, recovery posture, and repository release authority approval. It cannot use wildcards or
authorize another candidate.

Tooling continues to recommend a major version unless the exact exception is present and valid.

### SemVer recommendation and authorization

Deterministic tooling calculates the minimum SemVer increment from every compatibility category. A
human may authorize that version or a higher one. A lower version requires the exact patch
exception above.

Authorization binds the version, candidate OID, baseline OIDs, evidence-manifest digest, and
exception IDs. Any bound value changing invalidates approval and requires recertification.

### Adopted maintenance precedent

Fight Common adopts explicit public and internal surfaces, no unapproved compatibility breaks
within a major, compatible deprecation before removal, oldest-supported-line-first fixes, explicit
forward ports, and narrowly approved emergency exceptions.

It does not adopt Symfony's cadence, LTS duration, automatic future-PHP promise, expansive
inheritance assumptions, or organization-specific governance.

## Consequences

Support state, baseline selection, defect reach, and release authority become deterministic inputs
rather than prompt or tag-discovery guesses. Historical tag ambiguity cannot silently choose a
different consumer baseline.

The strict ancestry rule requires the published `1.1.0` lineage to be repaired before `v1.2.0` can
be certified. An unfinished release cannot race an EOL boundary, and an emergency remains visible as
an exact human exception rather than a suppressed finding.

## Rejected Alternatives

Maintaining a handwritten support table beside separate machine data was rejected because the two
authorities could drift. Inclusive local dates were rejected because automation would disagree at
timezone boundaries.

Automatic latest-tag discovery was rejected because the existing `1.1.0` and `v1.1.0` tags resolve
to different commits. Allowing ancestry alone to classify affected lines was rejected because code
history does not prove observable behavior.
