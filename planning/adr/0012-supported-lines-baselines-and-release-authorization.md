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
history. Each superseded minor keeps its own six-month limited-fix window after its successor's
publication, even if another minor ships meanwhile; more than one previous minor may therefore
remain supported at once. John's 2026-10-02 release-policy decision confirms that 1.0 and 1.1
must not be retired early merely because 1.3 is prepared. For a legacy tag without an observed
publication instant, declare a conservative explicit UTC boundary with the evidence and uncertainty
in `SUPPORTED_VERSIONS.md`; do not infer an exact publication time from a commit timestamp.

### Tags and comparison baselines

Future canonical release tags use the `vX.Y.Z` form and are signed annotated tags. A second tag that
normalizes to the same SemVer version is forbidden. The authoritative historical `1.1.0` exception
remains the bare annotated tag (current peeled commit `1666dbaa503e40ea2fc652bccbeba22e6cda6b70`);
the lightweight `v1.1.0` tag remains untouched legacy history. The earlier `fdd4806` identity was
replaced by TASK-00101's authorship-only all-ref rewrite; its exact before/after identities are in
that completion record. Do not treat the old object ID as the present remote tag or move either tag. The manifest's
`baseline` tag object and peeled OIDs identify the **current** canonical 1.1 tag; its older
`source_locator` commit prefixes remain historical provenance for tree-identical pre-rewrite source,
not present-day release baselines. TASK-00101 records that tree identity.

Compatibility uses these explicit baselines:

- a patch compares with the latest released patch on the same maintenance line;
- a minor compares with the latest release of the preceding minor in the same major;
- a major compares with the latest release of the preceding major to produce its migration
  inventory.

Every baseline records the canonical tag and peeled OID. The baseline must be an ancestor of the
candidate; missing, moving, ambiguous, duplicate-normalized, or non-ancestor references fail rather
than fall back to tag ordering. The historical `1.2.0` lineage required reconciliation with the
published `1.1.0` commit before certification. The actual published `v1.2.0` commit is **not**
descended from the current canonical `1.1.0` peeled commit; that historical publication does not
satisfy the stated ancestry rule or create a precedent for future candidates. Do not rewrite the
published tag to mask it. Check the actual refs and TASK-00101's rewrite map when choosing any later
baseline. ADR 0025 replaced the retired release framework with thin, exact-commit certification;
it does not assert that the thin command supplies the composed baseline-relative SemVer assessment
or typed approval described below.

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

The plan carries the exception as one complete `patch_exception_authorities` record referenced by
the matching `patch-exception:<exception-id>:exact-version:<X.Y.Z>` compatibility exception. The
record binds the exception ID, exact version, candidate commit OID, baseline tag-object and peeled
commit OIDs, one closed emergency class (`security`, `imminent-data-loss`, or
`critical-interoperability`), a positive no-compatible-repair attestation with non-empty evidence,
the complete canonical compatibility assessment, non-empty overridden finding and test-evidence ID
sets, consumer impact, mitigation, recovery posture, evidence-manifest SHA-256 digest, and repository
release-authority approval ID. The overridden set is exactly every non-patch finding in that bound
assessment, and that assessment's derived minimum increment exactly equals the plan's inspected minimum
release class. A lower-patch authorization contains exactly one patch-exception reference and exactly one
authority record, both for the approved exact version; unrelated, stale, missing, duplicate, wildcard, or
indeterminate findings or surplus records fail closed.
Plans approving the inspected minimum or a higher version contain no patch-exception references or authority
records, and their release approval binds an empty patch-exception authority digest set.
The record carries a verified canonical SHA-256 content identity, and the release approval binds the
complete canonical set of those authority identities. That approval ID must also be present in the
plan's required approvals. Missing, incomplete, ambiguous, unreferenced, or mismatched records fail
before plan hashing or persistence. Finding, evidence, and authority identity sets are canonical.

Tooling continues to recommend a major version unless the exact exception is present and valid.

### SemVer recommendation and authorization

Deterministic tooling calculates the minimum SemVer increment from every compatibility category. A
human may authorize that version or a higher one. A lower version requires the exact patch
exception above.

Authorization is one complete typed `release_approval_authority` record, not an
`exact-version:<version>` string. It binds its lowercase approval ID, exact version, candidate OID,
canonical baseline tag, baseline tag-object and peeled OIDs, evidence-manifest SHA-256 digest, the
canonical complete compatibility-exception ID and patch-exception authority identity sets, the
inspected minimum release class, and the actual baseline-relative authorized release class. Its
approval ID is also present in
`required_approvals`. Any bound value changing invalidates approval and requires recertification.

For **1.3.0 only**, John's 2026-10-04 decision to assess 1.2→1.3 compatibility separately
reconciles this earlier plan/comparator design with ADR 0025's thin certifier. Before the final
release decision, prepare a retained, independently reviewed compatibility assessment against the
actual annotated `v1.2.0` tag object and peeled commit. Inventory the ADR 0013 categories with
stable finding and evidence IDs, source locations, `patch`/`minor`/`major`/`indeterminate`
classifications and limitations; derive the minimum release class from the highest category.
Missing or indeterminate categories, or a `major` minimum for 1.3.0, stop the minor release rather
than become an implicit exception. The current certifier's installed package-surface check and
product gates are evidence, not substitutes for baseline-relative review.

After the exact `main` release merge is known and the relevant evidence is complete, obtain John's
**separate, explicit version approval** in the retained release handoff. Record a typed authority
with the exact `1.3.0` version and merge OID, canonical baseline tag/object/peeled OIDs, the complete
assessment's SHA-256 digest, inspected minimum class, authorized baseline-relative class, approval
identity and time. There is no patch exception for this minor. An altered commit, baseline or
assessment invalidates the authority. Independently verify that record and the assessment before
final-commit certification and signing; do not label `./bin/release certify` as validating or
emitting this retired plan machinery. This narrow 1.3 path does not reinstate the old release
framework or weaken compatibility for later releases; their process requires a fresh decision.

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

At this decision's pre-1.2 checkpoint, the strict ancestry rule required reconciling the published
`1.1.0` lineage before certifying `v1.2.0`. The actual published `v1.2.0` does not satisfy that rule;
this historical exception cannot be repaired by rewriting published refs and supplies no precedent
for future certification. An unfinished release cannot race an EOL boundary, and an emergency remains
visible as an exact human exception rather than a suppressed finding.

## Rejected Alternatives

Maintaining a handwritten support table beside separate machine data was rejected because the two
authorities could drift. Inclusive local dates were rejected because automation would disagree at
timezone boundaries.

Automatic latest-tag discovery was rejected because the existing `1.1.0` and `v1.1.0` tags resolve
to different commits. Allowing ancestry alone to classify affected lines was rejected because code
history does not prove observable behavior.
