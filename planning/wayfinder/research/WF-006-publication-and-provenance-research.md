# WF-006 GitHub and Packagist publication and provenance research

**Research date:** 2026-08-07

**Scope:** Primary-source findings and recommendations for
[`WF-006`](../tickets/WF-006-publication-and-provenance.md). This note does not sign a tag,
push a ref, create a GitHub Release, call the Packagist update API, or change hosted state.

## Executive recommendation

Treat publication as a resumable saga whose authority remains the verified Git commit and its
signed annotated tag:

1. certify one immutable candidate commit and bind it to the exact release version, plan, evidence
   manifest, and canonical tag name;
2. create and locally verify a signed annotated tag over that commit, recording the tag object OID,
   peeled commit OID, signer identity, signature format, and verification output;
3. push that already-created tag, then read the remote ref and tag object back and require both the
   tag object and peeled commit to match;
4. create a GitHub draft release for the existing tag, attach the release notes, evidence manifest,
   archive, checksum file, and any signature/provenance files, then read every asset back and verify
   names, sizes, and SHA-256 digests;
5. publish the draft only after the repository has immutable releases enabled and the exact bounded
   publication approval is present; verify the release's tag, commit, immutability, assets, and
   release attestation;
6. observe Packagist's Composer v2 metadata until the exact version has the expected source URL,
   source reference, package metadata, and dist URL/checksum; finally install the package from a
   clean temporary consumer using Packagist and verify the installed source and behavior.

Packagist is a downstream projection and observation target, not the release authority. A Packagist
propagation delay or failure must produce an incomplete-publication stop, not a new tag or a changed
GitHub release.

## Primary-source findings

### Git tag identity and signing

Git distinguishes a lightweight tag, which is only a ref to another object, from an annotated tag
object. Annotated tags contain tagger identity, timestamp, message, and optionally a cryptographic
signature; Git documents annotated tags as the release-oriented form. `git tag -s` creates a signed
annotated tag, `git tag -v` verifies it, and `git verify-tag` validates the signature in the tag
object. Git supports OpenPGP, X.509, and SSH signing backends through `gpg.format`.

Sources: [git-tag](https://git-scm.com/docs/git-tag),
[git-verify-tag](https://git-scm.com/docs/git-verify-tag),
[Git signature format](https://git-scm.com/docs/signature-format).

The release command should therefore never discover “the latest tag” by lexical or version sort.
It should accept the canonical tag name, resolve both the ref's object and peeled commit, reject a
lightweight tag for a new publication, and fail if the tag already exists with a different object.
Verification must check the signature, trusted signer identity/fingerprint, tag name/message
policy, tag object OID, and peeled candidate commit OID. A cryptographically good signature from
an unapproved identity is not a successful release proof.

The source documents establish the signing mechanisms and verification commands, but do not choose
Fight Common's identity, custody model, trust roots, or emergency signer rotation. Those remain HITL
decisions. The narrow recommendation is one named release signer, a documented fingerprint set,
operator-held or hardware-backed custody, no private key in CI or repository secrets, and a local
or controlled signing invocation that fails closed when the expected identity is unavailable.

### GitHub tag and release objects

GitHub's Git database API treats annotated tags as tag objects and separately exposes their ref.
The tag-object response includes the tag name, tagger, target object, and a verification object with
`verified`, `reason`, signature/payload data, and verification time. Creating a tag object does not
create its `refs/tags/<name>` ref; those are separate effects. The release workflow should use a
locally signed tag and push it, rather than asking GitHub to synthesize a tag from a moving branch.

Sources: [GitHub tag API](https://docs.github.com/en/rest/git/tags),
[GitHub Git database API](https://docs.github.com/en/rest/git),
[GitHub commit API](https://docs.github.com/en/rest/commits/commits).

The release API accepts an existing `tag_name`, optional `target_commitish`, release name/body,
draft/prerelease flags, generated notes, and latest-release selection. If the tag already exists,
`target_commitish` is unused; this is another reason the command must verify the tag before creating
the release. The release response includes `tag_name`, `target_commitish`, `draft`, `immutable`,
publication timestamps, and assets. Assets expose name, state, size, and a SHA-256 `digest`.

Source: [GitHub releases REST API](https://docs.github.com/en/rest/releases/releases).

GitHub recommends creating an immutable release as a draft, attaching all assets, and publishing it.
Immutable publication locks the associated tag and assets and automatically creates a release
attestation containing the release tag, commit SHA, and release assets. GitHub also documents
`gh release verify` and `gh release verify-asset` for checking immutability and local asset equality.
The source notes that generated source archives cannot be verified as release assets because they are
created at download time; the workflow should use its own deterministic archive as the install proof.

Sources: [immutable releases](https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases),
[release integrity verification](https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/secure-your-dependencies/verify-release-integrity).

Recommendation: publish only after a preflight proves immutable releases are enabled. If the setting
is disabled, stop with `hosted_capability_unavailable`; do not silently publish a mutable release and
do not treat an ordinary GitHub release as equivalent provenance.

### Composer and Packagist projection

Composer models each package version with a name, version, and installation metadata. `source`
describes the development repository and `dist` describes a packaged release; Composer prefers dist
by default for normal installs, while `--prefer-install=source` selects the source checkout.

Sources: [Composer repositories](https://getcomposer.org/doc/05-repositories.md),
[Composer CLI install](https://getcomposer.org/doc/03-cli.md),
[Composer basic usage](https://getcomposer.org/doc/01-basic-usage.md).

Packagist's preferred read path is the static Composer v2 endpoint:
`https://repo.packagist.org/p2/<vendor>/<package>.json`. It contains tagged releases and is intended
to be kept current; Packagist documents the dynamic package JSON endpoint as cached for up to twelve
hours, so it is not the primary propagation oracle. The metadata-change feed provides update actions
and a timestamp; after an update, consumers should ensure the package metadata `Last-Modified` is at
least the action timestamp. If the feed returns `resync`, cached data must be treated as stale and
revalidated.

Sources: [Packagist API documentation](https://packagist.org/apidoc),
[Packagist Composer v2 metadata section](https://packagist.org/apidoc#track-package-updates).

Packagist's authenticated `POST /api/update-package` endpoint returns a success status and job IDs.
It is a safe recovery operation, but it is still an externally affecting action and requires a
separate operator approval. The workflow should record the job IDs, poll the static metadata endpoint
afterward, and never claim success from the update response alone.

Source: [Packagist update package API](https://packagist.org/apidoc#update-a-package).

## Proposed release contract

### Version, topology, and source authority

- Minor and major releases use `release/<version>` from `develop`, merge the certified result to
  `main`, sign the verified `main` merge commit, merge the release result back to `develop`, and
  create `<major>.<minor>` from the signed release commit.
- Maintenance patches start from the oldest affected supported line, then move forward through
  separately reviewed and certified ports. The current-line result reaches `main` through its own
  reviewed merge; an older line never replaces newer `main` contents.
- The canonical version tag is `vX.Y.Z` for new releases. The signed annotated tag and peeled commit
  are authoritative; GitHub and Packagist are projections.
- Existing lightweight `v1.0.0` and `v1.1.0` tags are legacy history. Do not rewrite, delete, or sign
  them retroactively. A future release must not infer a baseline from the ambiguous legacy names.

This follows the closed WF-001 and WF-005 contracts and the Git semantics above.

### GitHub release contents

The draft release should contain exactly the approved effect set:

- release title and notes generated from the bound changelog/release surfaces;
- deterministic Composer archive;
- SHA-256 checksum manifest for the archive and evidence files;
- immutable evidence manifest containing plan ID, candidate/tag OIDs, certification results, and
  tool identities;
- optional detached signature/provenance material if the approved policy requires it.

The command records the GitHub release ID, tag name, target commit, draft/published/immutable state,
asset IDs, asset digests, publication timestamp, and attestation receipt. It must reject unexpected
assets or changed digests and must not delete or replace a published immutable release automatically.

### Packagist observation and clean-install proof

For the expected version, verify all of the following from static Composer v2 metadata:

- package name and normalized version;
- `source.url` points to the canonical GitHub repository and `source.reference` equals the signed
  tag's peeled commit OID;
- package metadata matches the bound `composer.json` fields and expected requirements;
- `dist.url` identifies the expected version archive and, when present, `dist.shasum` or a downloaded
  archive digest matches the certified artifact;
- metadata is newer than the propagation observation boundary and is not a stale cached response.

Then create a clean temporary consumer with only the package requirement and approved platform
constraints. Run Composer's normal install against Packagist, prefer dist, record the resolved
package version/source/dist fields, verify the installed files and public smoke behavior, and retain
the resulting lockfile plus a bounded machine-readable receipt. A clean install is the proof that
the projection is usable, not merely that a metadata JSON entry exists.

## Resumability and failure states

Publication is effect-ordered and postcondition-driven. Each step has one durable state and receipt:

| State | Postcondition | Safe re-entry |
| --- | --- | --- |
| `tag_ready` | local signed annotated tag verifies against candidate | revalidate; never replace a conflicting tag |
| `tag_pushed` | remote ref, tag object, and peeled commit match | re-read; do not push a different object |
| `github_draft` | draft exists for exact tag and approved assets are verified | reuse only after full revalidation |
| `github_published` | release is published, immutable, and attested | verify; do not edit/delete automatically |
| `packagist_observing` | GitHub proof exists; Packagist has not yet projected it | poll static metadata with bounded backoff |
| `packagist_incomplete` | timeout, stale metadata, wrong source, or failed install | stop and present manual recovery approval |
| `published` | all GitHub and Packagist observations plus clean install pass | emit final provenance receipt |
| `partial_publication` | an effect may have occurred but cannot be verified | operator reconciliation only; no blind retry |

Transient network failure before a confirmed remote effect may be retried after revalidation. A
timeout after an API request, disconnect after upload, or ambiguous response is not known failure:
enter `partial_publication`, query the exact object/release/asset by immutable identity, and only
continue when the postcondition is proven. Never create a second tag, release, or version to escape
an uncertain state.

For Packagist, use a bounded observation window defined by the implementation plan (for example,
polling with exponential backoff and a maximum elapsed duration). The timeout itself is evidence of
`packagist_incomplete`, not evidence that publication failed or succeeded. Manual `update-package`
recovery requires a new approval bound to the same plan, version, tag/commit OIDs, and evidence
manifest; it may be attempted once per approved recovery effect, followed by fresh observation.

## Evidence manifest minimum

The immutable manifest should include:

- plan ID, run ID, version, canonical tag, repository, and source/candidate commit OIDs;
- tag object OID, peeled commit OID, signature format, signer fingerprint, and local verification
  result;
- remote ref/tag-object/peeled-commit observations;
- GitHub release ID, tag, target commit, state, immutable flag, attestation receipt, asset IDs,
  names, sizes, and SHA-256 digests;
- Packagist endpoint, observation timestamps, metadata digest/Last-Modified, version entry, source
  URL/reference, dist URL/checksum, update job IDs if recovery was approved, and clean-install
  receipt;
- exact approval IDs, tool versions, environment/container identity, stop states, retries, and the
  one resumable next action.

Do not store tokens, private keys, passphrases, environment dumps, or unbounded command logs in the
manifest. Detailed logs remain bounded supporting evidence.

## Open HITL decisions for grilling

1. Which signing backend and signer fingerprint set are authoritative: OpenPGP, SSH, X.509, or a
   deliberately supported set?
2. Where is the signing key held, who may invoke it, and what is the documented rotation/revocation
   and lost-key recovery procedure?
3. What exact GitHub immutable-release and protected-environment configuration must exist before the
   first publication command can proceed?
4. What Packagist observation timeout/backoff is appropriate, and what exact operator may approve a
   manual update-package recovery?
5. What archive naming, exclusion, normalization, checksum, and detached-signature policy is part of
   the approved release asset set?
6. Which clean-install smoke behavior is sufficient evidence for this library, and how long are
   detailed receipts retained?

These are decisions for the HITL grilling phase; research narrows the safe workflow but does not
authorize them.

## Local evidence captured

At research time, the working tree was clean on `planning/WF-006-publication-and-provenance` at
`8928793`, matching `develop` and `origin/develop`. Local tags demonstrate the legacy ambiguity:

- annotated `1.1.0` has tag object
  `5f1c2f2a4a78741836003b0d6acd229569beb454` and peels to
  `fdd48065c5527f4968943db7d61d6f1ad17619e7`;
- lightweight `v1.0.0` points to `bca016d7633e891ffda6e354fc35c0b1a0fe38a7`;
- lightweight `v1.1.0` points to `be965a0b94c9eed8646673418669c7f0b53c2a43`.

The sandbox could not resolve `api.github.com` or `repo.packagist.org` during this run, so live
hosted-state observations are intentionally not asserted here. The future inspect/verify command
must capture them in the evidence manifest before publication or final completion.

