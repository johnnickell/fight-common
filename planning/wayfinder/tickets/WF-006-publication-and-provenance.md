# Define GitHub and Packagist publication and provenance

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** AFK -> HITL
**Status:** Open
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
