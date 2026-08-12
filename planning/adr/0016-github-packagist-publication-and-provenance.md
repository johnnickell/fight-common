# ADR 0016: GitHub and Packagist Publication and Provenance

- Status: accepted
- Date: 2026-08-08

## Decision

New Fight Common releases use one approved OpenPGP release signer and a signed annotated `vX.Y.Z`
tag. The signer fingerprint is recorded in the release evidence. The private key remains with the
operator or a hardware-backed signer and is never available to CI. A signer change, loss, or
revocation requires a new release plan and explicitly approved fingerprint set.

The release commit topology follows the established release contracts: major and minor releases
come from `release/<version>` through the verified `main` merge, while maintenance patches move
forward from the oldest affected supported line through separately certified ports. Existing
lightweight tags are historical and are not rewritten.

GitHub is the publication authority. The workflow may create and verify a draft release, but public
publication requires immutable releases to be enabled and an approved `release-publication` protected
environment. With a one-person team, the same named operator may sign, verify, and approve the
publication; the dual role is recorded in the evidence manifest. A mutable-release fallback is not
permitted.

The approved release assets are release notes, a deterministic rootless
`fight-common-vX.Y.Z.zip`, a SHA-256 checksum manifest, and an immutable evidence manifest. The
archive uses committed Composer exclusions and normalized timestamps and ordering. Optional detached
signature or provenance files require explicit policy inclusion.

Packagist is a downstream projection. After GitHub publication, the workflow observes Composer v2
metadata using 15-second, 30-second, 1-minute, 2-minute, then 5-minute polling for at most 30
minutes. Timeout, stale or mismatched metadata, or failed installation produces
`packagist_incomplete`; manual recovery is a separately approved update bound to the original plan,
version, tag, commit, and evidence manifest.

Completion requires a clean temporary Composer consumer using `composer install --prefer-dist
--no-dev`, verification of production autoloading and a representative public-API probe, and a
bounded clean-install receipt. The manifest and receipt remain permanent release assets; detailed
logs are retained for 90 days.

## Consequences

The signed tag and verified commit remain the source of release authority, while GitHub and Packagist
must prove their exact downstream projections. Publication is resumable through postcondition
verification and fails closed on ambiguity or timeout. The one-person approval rule preserves the
protected checkpoint without inventing independent review.

The implementation must still bind the actual signer fingerprint, operator identity, hosted GitHub
environment configuration, archive implementation, and evidence schema into each release plan.

## Rejected Alternatives

CI-held signing keys were rejected because they broaden key custody and make the release signer less
operator-controlled. Mutable GitHub releases were rejected because they do not provide the same
immutability guarantee. Treating Packagist update success or metadata presence alone as completion
was rejected because downstream propagation and installability require separate observation.
