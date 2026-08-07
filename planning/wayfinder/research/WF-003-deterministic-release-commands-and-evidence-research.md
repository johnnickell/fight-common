# WF-003 deterministic release commands and evidence research

**Research date:** 2026-08-06

**Scope:** Primary-source findings and recommendations for
[`WF-003`](../tickets/WF-003-deterministic-release-commands-and-evidence.md). This note does not approve a
command design, implement tooling, change Git or hosted configuration, publish a release, or mutate
Packagist.

## Executive recommendation

- Put release policy behind one repository-owned executable with narrow subcommands. A single entry point
  gives local use, CI, and skills one versioned contract; subcommands keep read-only inspection, local
  preparation, certification, and externally mutating publication separate. Skills may select and explain a
  subcommand, but may not reproduce its policy or convert a failure into success.
- Make the release plan content-addressed. Canonicalize a strictly versioned JSON plan, hash it with SHA-256,
  and use that digest as `plan_id`. Keep `run_id` as a separate unique attempt identifier. Authorization binds
  the exact `plan_id`, candidate and baseline OIDs, version, evidence-manifest digest, and exception IDs.
- Model a run as an append-only sequence of attempted transitions plus an atomically replaced current-state
  projection. Resume rechecks every bound input and every completed postcondition. It never skips a step
  because a state label merely says it ran.
- Treat publication as a saga, not an atomic transaction. Push the already-created signed annotated tag,
  verify its remote object and peeled commit, create a GitHub draft, upload and verify assets, then publish and
  observe Packagist. Any failure after the first remote effect enters a persistent partial-publication stop;
  no automatic tag/release deletion or blind retry is safe.
- Keep a compact, immutable evidence manifest as the authority and detailed logs as bounded supporting
  material. The manifest records exact inputs, tool identities, check results, output digests, GitHub receipt,
  and expected Packagist projection. Secrets, environment dumps, authorization tokens, command-line
  credentials, and unbounded logs never enter either surface.
- Require immutable GitHub releases before publication. GitHub says an immutable published release locks its
  tag and assets and automatically receives a release attestation; the recommended flow is draft, attach all
  assets, then publish. This repository currently has release immutability disabled and no release approval
  environment, so a future publication command must stop until separately authorized configuration work
  establishes those controls. [GitHub immutable releases](https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases),
  [GitHub deployment environments](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments)

## Current repository and hosted-state evidence

- The active planning branch begins at `84d6fbae2b4784004bb9533ae97105ba72d7580c`. `develop` and
  `origin/develop` identify that same commit; `main`, `1.1`, and their origin refs identify
  `be965a0b94c9eed8646673418669c7f0b53c2a43`; `1.0` identifies
  `bca016d7633e891ffda6e354fc35c0b1a0fe38a7`.
- The tag set is materially ambiguous. Lightweight `v1.1.0` identifies `be965a0`, while annotated `1.1.0`
  has tag object `5f1c2f2` and peels to `fdd4806`. Packagist's tagged `1.1.0` metadata identifies
  `fdd48065c5527f4968943db7d61d6f1ad17619e7`; its `dist.shasum` is empty. This confirms ADR 0012's
  requirement that commands consume explicit canonical tags and peeled OIDs rather than discover a
  lexically latest tag.
  [Live Packagist Composer v2 metadata](https://repo.packagist.org/p2/johnnickell/fight-common.json),
  [Packagist API documentation](https://packagist.org/apidoc)
- GitHub currently reports no Releases, and its repository immutable-release setting is `enabled: false`.
  The only repository workflows are tests and documentation plus GitHub's Pages workflow. There is no
  release workflow, artifact attestation, or release approval environment. The existing `main` ruleset blocks
  deletion and enables code-quality warnings but does not require a release check.
  [Live GitHub Releases API](https://api.github.com/repos/johnnickell/fight-common/releases),
  [live immutable-release setting](https://api.github.com/repos/johnnickell/fight-common/immutable-releases),
  [live workflows API](https://api.github.com/repos/johnnickell/fight-common/actions/workflows)
- `composer.json` has no committed `composer.lock`, no `archive` policy, and no repository release scripts.
  The test workflow runs one `composer install` without a lock, which resolves and creates an ephemeral lock;
  it does not prove the accepted locked/lowest/latest composition. Composer documents that install uses exact
  versions only when a lock exists, while update writes newly resolved exact versions.
  [Composer install and update](https://getcomposer.org/doc/03-cli.md#install-i)
- The current repository has focused interactive wrappers and `bin/planning-check`, but no `bin/build` or
  complete repository-owned release gate. PRD-00009 and ADRs 0006-0008 describe a future shared gate; they are
  planning evidence, not an executable release precondition today.
- `CHANGELOG.md` has an `[Unreleased]` section and points its comparisons at lightweight `v1.1.0`. That link
  does not match Packagist's authoritative `1.1.0` source commit and must be treated as detected release-input
  drift until a separately approved lineage repair resolves it.

## What deterministic commands should own

The command owns facts that can be computed from explicit inputs and policy already committed in the
repository. A human owns consequential authorization and genuine ambiguity.

| Operation | Repository command owns | Human or hosted control owns |
| --- | --- | --- |
| Inspect | Parse policy and manifests; resolve exact local and remote refs; classify current support phase; report dirty state, tag ambiguity, missing tools/configuration, and external observations | Decide whether an unexpected condition should change policy |
| Plan | Validate requested version and line; select only explicit policy baselines; compute minimum SemVer recommendation; enumerate intended effects and required approvals; emit immutable plan | Select the release version at or above the recommendation and approve the exact plan |
| Prepare | Create an isolated candidate workspace from the bound commit; render release notes and package inputs; generate ephemeral dependency resolutions without changing the source tree | Approve any source, changelog, policy, or manifest correction through normal review before replanning |
| Package | Export only the bound commit; apply committed package-exclusion policy; build named archive(s); inventory members; calculate SHA-256; rebuild and byte-compare | Approve a change to the published package surface |
| Certify | Run the accepted full gate, planning check, compatibility composition, lowest/locked/latest lanes, archive installs, source/archive equivalence, Git ancestry/ref checks, and evidence validation | Resolve indeterminate contracts or approve an exact exception |
| Publish | Enforce authorization; perform each named GitHub/Packagist effect once; record receipts; verify each postcondition before advancing | Supply explicit publication approval through the configured protected environment; direct recovery after a partial effect |
| Branch | Calculate source and destination OIDs, ancestry, support eligibility, and exact proposed ref/PR effects | Authorize creation/update of each maintenance or forward-port branch/PR |
| Patch | Validate an already-reviewed fix, affected-line evidence, oldest-supported-line base, exception, and per-line plan | Decide the fix and authorize the affected-line declaration/exception |
| Forward-port | Verify source fix identity and ancestry; prepare distinct commits/PRs; require separate certification per newer line | Review/merge each port and resolve conflicts semantically |
| Verify | Recompute local digests and query exact remote tag, release, asset, attestation, workflow, and Packagist identities | Decide recovery when an externally observed state is contradictory or unverifiable |

SemVer defines release classification by effect on the declared public API; it does not authorize a release or
discover consumer reliance. A command can therefore calculate a minimum supported bump from the accepted
manifest and evidence, but the exact version remains human-authorized.
[Semantic Versioning 2.0.0](https://semver.org/)

## Recommended command surface

Use one versioned executable such as `bin/release` with these narrow subcommands:

```text
bin/release inspect
bin/release plan
bin/release prepare
bin/release package
bin/release certify
bin/release publish
bin/release branch
bin/release patch
bin/release forward-port
bin/release verify
```

This is one policy implementation, not one all-powerful operation. `inspect`, `plan`, `certify`, and `verify`
are non-mutating toward GitHub and Packagist. `prepare` and `package` may write only inside the named run's
local workspace. `branch`, `patch`, `forward-port`, and `publish` expose effects in their plan and require the
specific authority assigned to those effects.

Several unrelated top-level scripts were rejected as a recommendation because their schemas, policy loading,
exit semantics, and state transitions could drift. A single `release` command with subcommands can still use
small internal executables; the stable public contract remains one dispatcher and one schema family.

### Dry-run contract

- `inspect`, `plan`, and pre-publication `verify` are intrinsically read-only and do not need a misleading
  `--dry-run` switch.
- For a mutating subcommand, `--dry-run` performs all possible validation and emits the exact effect list, but
  performs no local ref update, push, API write, notification, or Packagist update. It cannot claim that remote
  permissions, races, signatures, or publication will succeed.
- A dry-run is not resumable as if effects occurred. Its result may become a new immutable plan, but a real run
  begins with fresh stale-plan checks.
- Every external effect has an explicit expected before-state and after-state. Local Git ref updates can use
  compare-and-swap semantics: Git's `update-ref <ref> <new> <old>` updates only if the ref still has the expected
  old OID. [Git `update-ref`](https://git-scm.com/docs/git-update-ref)

### Inputs, preconditions, and postconditions

Every invocation accepts structured input or exact flags and emits a structured result. It never prompts when
running under CI. At minimum, bind:

- repository identity and object format;
- requested version and release line;
- candidate commit OID and candidate tree OID;
- canonical tag name plus expected tag-object and peeled commit identities when applicable;
- explicit baseline tag objects and peeled commits;
- support-policy, compatibility-manifest, changelog, Composer manifest, package-policy, and workflow blob OIDs;
- approved exception IDs and their bound digests;
- expected remote ref values or expected absence;
- toolchain/container identity and exact Composer version;
- authorization record identity and evidence-manifest digest for publication.

Use `git rev-parse --verify --end-of-options '<ref>^{commit}'` or the corresponding object type to reject
missing, ambiguous, or incorrectly typed inputs. Use porcelain Git status for scripts; Git guarantees porcelain
v1 is stable across versions and user configuration, and recommends NUL termination for machine parsing.
[Git `rev-parse`](https://git-scm.com/docs/git-rev-parse),
[Git status porcelain](https://git-scm.com/docs/git-status#_porcelain_format_version_1)

A completed step records its postconditions, not merely exit code zero. Resume proves those postconditions
again. Examples include an archive's byte digest and member inventory, a workflow run's exact `head_sha`,
`run_attempt`, completed status and successful conclusion, a remote signed-tag object and peeled OID, a GitHub
release ID/tag/immutable flag/assets and digests, and Packagist's normalized version with matching source and
dist references. GitHub's workflow-run API distinguishes queued/in-progress/completed states and conclusions;
queued or running is not passed. [GitHub workflow-runs API](https://docs.github.com/en/rest/actions/workflow-runs)

### Exit and machine-result contract

Keep exit codes stable and coarse, with exact detail in a versioned JSON result:

| Exit | Meaning |
| ---: | --- |
| `0` | Requested terminal postcondition is verified, including an already-satisfied idempotent operation |
| `10` | Invalid invocation or schema |
| `20` | Policy or deterministic precondition failed |
| `21` | Plan is stale or a bound input drifted |
| `22` | Exact authority is missing, expired, or mismatched |
| `23` | Ref/branch/forward-port conflict requires human resolution |
| `30` | A deterministic check ran and failed |
| `31` | Required evidence is missing, indeterminate, expired, or unverifiable |
| `40` | An external effect occurred and the intended publication is incomplete; recovery direction required |
| `70` | Command/tool/infrastructure failure before any external effect |

Each result includes `schema_version`, command, plan/run IDs, starting and ending state, outcome, stable finding
IDs, completed effects, next permitted operations, and paths/digests for evidence. Human-readable text is a
rendering of this result, not a second authority.

## Plan and run-state contract

### Identity and canonicalization

Define `plan_id = sha256(JCS(plan_without_plan_id))`. RFC 8785 exists because hashing and signing JSON requires
an invariant representation; it constrains JSON and deterministically serializes and sorts properties. Keep the
release schema to strings, integers, booleans, arrays, and objects, reject duplicate keys and unsupported
numbers, and account for RFC 8785's verified errata in any implementation.
[RFC 8785 JSON Canonicalization Scheme](https://www.rfc-editor.org/rfc/rfc8785.html)

The plan contains no observation timestamps, random values, machine paths, or credentials. Recreating it from
the same inputs must yield the same bytes and ID. A `run_id` is a separate unique attempt ID linked to one plan;
retry creates a new run, while resume names the existing run.

### State and transition evidence

Recommended durable states are:

```text
planned -> prepared -> packaged -> certified -> awaiting_publication_authority
        -> publishing_tag -> publishing_draft -> publishing_assets
        -> publishing_release -> observing_packagist -> verified
```

Terminal or gated stop states are:

- `stale_plan`: a bound local or external input changed;
- `policy_blocked`: support, compatibility, package, or full-gate policy failed;
- `authority_required`: no exact current authorization matches;
- `conflict`: a branch/ref/forward-port operation cannot be applied mechanically;
- `certification_failed`: a check produced a definite failure;
- `evidence_indeterminate`: required evidence cannot establish pass or fail;
- `partial_publication`: at least one external effect occurred but the final verified state was not reached;
- `external_state_unverifiable`: the provider response cannot be reconciled with the bound expectation;
- `superseded`: a new plan intentionally replaces this one without deleting its history;
- `stopped_at_eol`: the exclusive support boundary arrived before completion.

Each transition appends an event with sequence number, prior state, attempted operation, input digest, start/end
times, outcome, stable findings, effect receipt, and evidence digests. Atomically replace a current-state
projection only after the append succeeds. A crash can then leave, at worst, an attempted effect whose provider
postcondition must be queried before retry. Never infer "not attempted" from a missing final state alone.

### Staleness and safe resume

Before every transition, re-resolve candidate/baseline objects, support clock, policy/manifest blobs, remote
refs, exact authorization, toolchain identity, and all completed external receipts. A changed bound value makes
the plan stale even if the new state looks benign. A new dependency/advisory result may invalidate certification
without changing source; record the advisory-data observation time and require an approved freshness window.

Resume is safe only when:

1. the plan bytes still hash to `plan_id`;
2. the run event chain is valid and has no sequence gap;
3. all bound inputs and authorization still match;
4. every previously completed step's postconditions still verify; and
5. an in-flight external effect is reconciled by querying the provider before deciding whether to repeat it.

## Evidence manifest and logs

### Compact manifest

Use a canonical, versioned JSON manifest with these sections:

- `subject`: package, version, line, candidate commit/tree, canonical tag object/peeled commit;
- `plan`: plan/run IDs, schema and command versions, minimum SemVer recommendation, authorization digest;
- `inputs`: baseline/tag identities, policy and manifest blob IDs, changelog and Composer digests, exceptions;
- `environment`: container image digest, PHP/Composer/Git and checker versions, workflow blob and runner identity;
- `checks`: stable check ID, category, exact command-contract version, outcome, started/finished times, report digest;
- `dependency_lanes`: lowest, repository-locked, and latest resolution lock digest plus install/test outcomes;
- `package`: archive name, format, SHA-256, size, member-inventory digest, repeated-build comparison, clean-install results;
- `git`: worktree porcelain result, ancestry results, source/remote ref snapshots, signature verification result;
- `publication_expectation`: tag, GitHub release/assets, and exact expected Packagist normalized version/source/dist refs;
- `receipts`: append-only external IDs and verified observations available at the time the manifest is finalized;
- `findings`: stable IDs, severity/classification, disposition, exception reference, and report digest;
- `redaction`: policy version and confirmation that prohibited fields were not persisted.

SLSA provenance is a useful compatible model, not a substitute for release policy: it identifies output
subjects by digest and records build definition, external parameters, resolved dependencies, builder, invocation,
and byproducts. Map the package archive to a provenance subject and the candidate commit/toolchain to resolved
dependencies. GitHub artifact attestations can cryptographically bind an artifact to workflow, repository,
commit, event, and environment, but GitHub explicitly says attestations must be verified to add security.
[SLSA build provenance](https://slsa.dev/spec/v1.2/build-provenance),
[GitHub artifact attestations](https://docs.github.com/en/actions/concepts/security/artifact-attestations)

### Exact certification evidence

Certification should compose, at minimum:

- the accepted complete repository gate from one invocation, once it exists;
- `composer validate --strict`, which makes warnings as well as errors non-zero and checks lock freshness when a
  lock exists;
- separate lowest, repository-locked, and latest-permitted resolution lockfiles and clean installs/tests;
- real-platform `composer check-platform-reqs`, which ignores simulated `config.platform`;
- Composer audit JSON, policy outcome, observation time, and data-source identity;
- explicit-baseline structural API, compatibility-manifest, constraint-set, behavioral, serialization,
  persistence, framework-adapter, static-analysis, and deprecation evidence required by ADR 0013;
- exact planning validation and absence of unresolved release-blocking tickets;
- archive member inventory, source-to-archive policy comparison, duplicate build SHA-256 equality, and clean
  consumer installs from the archive with production autoload verification;
- Git object types, tag signatures, peeled OIDs, ancestry, expected remote refs, and stable porcelain status;
- hosted workflow ID, run ID/attempt, workflow blob, exact head SHA, completed/success result, job conclusions,
  and artifact/attestation digests.

Composer documents `validate --strict`, lowest resolution, audit exit behavior, real platform checks, and archive
generation. Its archive documentation and schema define supported formats and exclusions, but do not promise
byte-for-byte reproducibility. Therefore the repository must define normalization and prove reproducibility by
building twice; it must not infer reproducibility merely because `composer archive` succeeded.
[Composer CLI](https://getcomposer.org/doc/03-cli.md),
[Composer archive schema](https://getcomposer.org/doc/04-schema.md#archive)

Git archive is a viable lower-level input because archiving a commit/tag uses the commit time, includes the
commit ID in tar metadata, and reads `export-ignore` from the archived tree by default. Avoid
`--worktree-attributes`, which deliberately reads uncommitted working-tree attributes. Compression and other
archive-format details still require a pinned implementation and duplicate-build proof.
[Git `archive`](https://git-scm.com/docs/git-archive)

### Detailed logs, redaction, retention, and custody

- Capture stdout and stderr separately per check with byte counts, SHA-256, truncation status, and a bounded
  maximum. A truncated log can support diagnosis but cannot satisfy a check whose structured report is missing.
- Redact before persistence, not only at display. Prohibit tokens, cookies, authorization headers, GitHub or
  Packagist credentials, environment dumps, DSNs, signing-key material, and raw provider errors likely to echo
  requests. Prefer allowlisted structured fields over pattern-based secret detection.
- Keep the compact manifest and required structured reports with the immutable GitHub release assets. GitHub
  workflow artifacts support configurable retention and can be deleted with the workflow run, so they are
  useful bounded diagnostic custody but not the sole durable release authority.
  [GitHub workflow artifacts](https://docs.github.com/en/actions/concepts/workflows-and-actions/workflow-artifacts),
  [artifact retention](https://docs.github.com/en/actions/tutorials/store-and-share-data#configuring-a-custom-artifact-retention-period)
- Record detailed-log digests and retention deadlines in the manifest. A digest proves which log was observed;
  it does not make an expired log available. The implementation design must select an explicit retention term
  and decide whether security-relevant detailed reports need longer repository-owned storage.

## Safe publication and downstream verification

Recommended effect order:

1. Reverify certification, exact authorization, remote before-state, immutable-release setting, protected
   publication environment, and exclusive release concurrency.
2. Push the pre-created signed annotated tag object for the exact candidate; verify remote tag-object and peeled
   commit OIDs. Do not let GitHub's create-release endpoint create a missing tag from `target_commitish`, because
   that would bypass the planned signed annotated tag object.
3. Create or reconcile one draft GitHub Release for the existing tag. The REST API can create a draft and later
   publish it; record release ID and every response rather than trusting names alone.
   [GitHub Releases REST API](https://docs.github.com/en/rest/releases/releases)
4. Upload the package, evidence manifest, required structured reports, and attestations; read them back and
   verify names, sizes, and SHA-256 digests. GitHub's release-asset API exposes a `digest` field.
5. Publish the draft. With immutability enabled, verify the release reports immutable, the tag and assets are
   locked, and the release attestation verifies. GitHub recommends this draft-assets-publish order.
6. Trigger Packagist only when observation shows the version missing or stale. Packagist calls its update API
   safe, but a successful response returns job IDs: it proves enqueue, not completion.
7. Poll the preferred Composer v2 metadata endpoint until the normalized version's source and dist references
   equal the candidate OID, or until the bounded deadline. Tagged releases appear in the p2 endpoint; branch
   metadata is separate. [Packagist API](https://packagist.org/apidoc)
8. Perform a clean consumer install using the published version and record the resolved source/dist reference.
   Only then enter `verified`.

GitHub's `gh release verify-asset` can prove a local file exactly matches a release asset. GitHub explicitly says
it cannot verify automatically generated source zipballs/tarballs because they are created on request. Packagist
currently points `dist` at such a GitHub zipball and supplies no `shasum`; therefore the repository-produced
package asset and manifest need their own digest/attestation, while Packagist verification must at least prove
its immutable source/dist **reference** is the candidate OID.
[GitHub release integrity verification](https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/secure-your-dependencies/verify-release-integrity)

GitHub Actions permits concurrent workflow runs by default. Use a release-line concurrency key with queuing,
not cancel-in-progress behavior, because GitHub otherwise allows conflicting runs and normally cancels an older
pending run in the same group. Command-level provider reconciliation remains required even with workflow
concurrency. [GitHub Actions concurrency](https://docs.github.com/en/actions/concepts/workflows-and-actions/concurrency)

## Idempotency and partial effects

- Read-only checks are repeatable but time-sensitive checks record freshness.
- Local outputs are content-addressed and written under the run ID; an existing path is accepted only after its
  digest and provenance match.
- Ref creation succeeds as already satisfied only when the existing ref is the exact planned object. Any other
  value is drift, never overwrite authority.
- Draft reconciliation accepts exactly one draft with the planned tag, candidate, and asset set. Multiple or
  contradictory drafts stop as unverifiable.
- Asset upload is already satisfied only when name, size, and digest match. Never replace an immutable or
  mismatched asset automatically.
- Packagist update can be repeated only after observation proves the expected version is still absent/stale and
  the previous job has reached a known terminal condition or the approved reconciliation timeout has elapsed.
- Once a tag is pushed or a draft/release exists, failures use exit `40` and retain `partial_publication` until a
  human chooses an exact recovery plan. Automatic deletion, force-push, tag reuse, release recreation, or
  version substitution is outside safe resume.

## Test seams without production mutations

- Make command policy consume ports for process execution, clock, filesystem, Git object/ref queries, GitHub,
  Packagist, signing, hashing, and authorization. Production adapters invoke real tools/APIs; fixtures provide
  deterministic responses and a recorded effect ledger.
- Exercise the public command in temporary Git repositories with real commits, annotated tags, divergent refs,
  dirty/untracked paths, duplicate-normalized versions, missing objects, stale plans, and compare-and-swap races.
- Serve fake GitHub/Packagist HTTP endpoints locally and assert exact requests, auth-header redaction, response
  reconciliation, pagination, queued/running/success distinctions, retry bounds, timeouts, and partial effects.
- Use disposable package fixtures to prove member exclusions, traversal/symlink handling, duplicate-build
  equality, malformed manifests, tampered digests, and clean Composer artifact installs.
- Crash at every transition boundary and before/after every simulated external response. Resume must query the
  effect ledger/provider and either verify already-satisfied state, perform the one missing effect, or stop.
- Contract-test adapters against read-only provider APIs. Mutating adapter tests target fake repositories and
  servers only; production GitHub/Packagist credentials must be structurally unavailable to the test process.

## Decision consequences and remaining unknowns

Research supports these decisions for grilling:

1. One `bin/release` policy entry point with narrow subcommands is safer than independent scripts.
2. JCS-canonical JSON plus SHA-256 should identify plans and evidence manifests; unique run IDs identify
   attempts.
3. Resume must be postcondition-driven, and all post-mutation uncertainty must enter a persistent partial state.
4. The immutable evidence manifest is compact and durable; raw logs are bounded, redacted supporting material.
5. The signed tag, GitHub immutable release/assets/attestation, and Packagist projection form three separately
   verified stages. Packagist is never the publication authority.

The following facts still require later decisions or implementation evidence:

- exact schema fields, storage path, lock/atomic-write mechanism, event-chain integrity, and retention periods;
- exact signer identity, signing mechanism, key custody, and whether GitHub release attestation supplements or
  replaces any separately generated package attestation;
- how the repository will create a genuine locked dependency lane, because no lockfile exists today;
- the normalized archive format/implementation and committed exclusion policy;
- how the historical `1.1.0`/`v1.1.0` lineage and changelog link will be repaired before `v1.2.0`;
- who may approve the protected publication environment and whether self-review/admin bypass is forbidden;
- the exact Packagist polling deadline, update-job reconciliation procedure, and recovery authority;
- durable custody for post-publication observations, because immutable release assets cannot be amended and
  workflow artifacts alone can expire or be deleted;
- whether GitHub immutable releases, required status checks, environment review, artifact attestation, and
  release workflow capabilities available to this public repository meet the final governance contract after
  separately authorized configuration.

## Primary sources

- [Semantic Versioning 2.0.0](https://semver.org/)
- [Git status porcelain format](https://git-scm.com/docs/git-status#_porcelain_format_version_1)
- [Git `rev-parse`](https://git-scm.com/docs/git-rev-parse)
- [Git `update-ref`](https://git-scm.com/docs/git-update-ref)
- [Git `archive`](https://git-scm.com/docs/git-archive)
- [Composer CLI](https://getcomposer.org/doc/03-cli.md)
- [Composer schema](https://getcomposer.org/doc/04-schema.md)
- [Composer versions and constraints](https://getcomposer.org/doc/articles/versions.md)
- [Composer repositories](https://getcomposer.org/doc/05-repositories.md)
- [GitHub immutable releases](https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases)
- [GitHub release integrity verification](https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/secure-your-dependencies/verify-release-integrity)
- [GitHub Releases REST API](https://docs.github.com/en/rest/releases/releases)
- [GitHub workflow-runs API](https://docs.github.com/en/rest/actions/workflow-runs)
- [GitHub workflow artifacts](https://docs.github.com/en/actions/concepts/workflows-and-actions/workflow-artifacts)
- [GitHub artifact attestations](https://docs.github.com/en/actions/concepts/security/artifact-attestations)
- [GitHub deployment environments](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments)
- [GitHub Actions concurrency](https://docs.github.com/en/actions/concepts/workflows-and-actions/concurrency)
- [Packagist API](https://packagist.org/apidoc)
- [Packagist package-update hooks](https://packagist.org/about#how-to-update-packages)
- [SLSA build provenance](https://slsa.dev/spec/v1.2/build-provenance)
- [RFC 8785 JSON Canonicalization Scheme](https://www.rfc-editor.org/rfc/rfc8785.html)
