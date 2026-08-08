# Define GitHub and Packagist publication and provenance

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** AFK -> HITL
**Status:** Closed
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** [Design deterministic release commands and evidence](WF-003-deterministic-release-commands-and-evidence.md), [Define the plan, package, certify, and publish skill contracts](WF-004-release-skill-contracts.md)

## Question

What exact, resumable workflow makes a verified Git commit a signed GitHub release and then proves
that Packagist exposes the same version, source, metadata, and installable package?

## Must decide

- annotated-tag signing identity, custody, invocation, verification, and failure behavior;
- exact commit and merge topology for major, minor, and maintenance patch publication;
- GitHub Release contents, release notes, evidence assets, checksums, and immutability expectations;
- Packagist automatic propagation observations, timeout, retry, and incomplete-publication state;
- manually approved Packagist recovery without making Packagist the release authority;
- remote ref, tag object, commit OID, GitHub Release, Composer metadata, and clean-install proof;
- idempotent re-entry after local disconnects or partial external success;
- handling of legacy lightweight tags without rewriting history.

## Resolution boundary

Use primary Git, GitHub, Composer, and Packagist sources. Resolve the contract without signing a tag,
pushing a ref, creating a GitHub Release, or mutating Packagist.

## Resolution

New releases use one approved OpenPGP signer and a locally verified signed annotated `vX.Y.Z` tag.
The signer is operator- or hardware-held, unavailable to CI, and rotation or revocation requires a
new release plan and approved fingerprint set. Major/minor and maintenance-patch topology inherits
the established WF-001/WF-005 contracts; legacy lightweight tags remain untouched.

GitHub is the publication authority. The workflow verifies the remote tag object and peeled commit,
prepares a draft with release notes, a deterministic rootless `fight-common-vX.Y.Z.zip`, SHA-256
checksums, and an immutable evidence manifest, then publishes only when immutable releases and the
protected `release-publication` environment are available. As a one-person team, the same operator
may sign, verify, and approve publication, with that dual role recorded.

Packagist is a downstream projection. Composer v2 metadata is observed for at most 30 minutes using
15-second, 30-second, 1-minute, 2-minute, then 5-minute polling. Timeout, stale or mismatched
metadata, or failed installation produces `packagist_incomplete`; manual recovery requires separate
approval bound to the same plan, version, tag, commit, and evidence manifest.

Completion requires a clean temporary consumer using `composer install --prefer-dist --no-dev`,
production autoload verification, and a representative public-API probe. The manifest and receipt
are permanent release assets; detailed logs are retained for 90 days. Resumption is
postcondition-driven and never uses blind retries after ambiguous external effects.

See the [WF-006 research artifact](../research/WF-006-publication-and-provenance-research.md) and
[ADR 0016](../../adr/0016-github-packagist-publication-and-provenance.md).
