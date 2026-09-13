# ADR 0027: Human Release Publication

- Status: accepted
- Date: 2026-09-12
- Supersedes: ADR 0016 only where this decision conflicts with its publication environment, sequencing, and asset clauses

## Context

ADR 0025 retains certification as one local, verification-only operation. The certified `1.2.0` candidate
`d262866714fe1a3a60e086806cede526937d5874` passed all ten ordered outcomes, and merged `develop` commit
`ef64fcbbcccb2c1438a15f9c5b460cc2a96e9d13` has the same tree
`837a863000d425eb6c5b404278f41f670bd7db70`. This accepts the candidate evidence without confusing that
tree identity with the final tag identity on `main`.

The `release-publication` environment named by ADR 0016 was never used. A human-operated release needs
per-effect authorization and independently checked provider postconditions instead of an unused environment
gate. GitHub immutable releases protect a tag and uploaded assets only after publication, so a draft must be
complete and verified before it is published.

## Decision

- The release candidate is cut from freshly fetched `origin/develop` as `release/1.2.0`, reviewed and validated
  in its isolated worktree, then merged to `main` only through a pull request.
- Each release-branch push, merge, immutable-release enablement, signed-tag creation, tag push, draft-release
  creation, and release publication effect requires a distinct human approval immediately before that effect.
- T-00035 fetches the exact remote `main` merge into a clean, isolated release worktree and freshly runs
  `./bin/release certify 1.2.0` there. Only then may the operator sign that same commit with the approved signer.
- The signed annotated tag remains `vX.Y.Z`; signer custody remains with the operator or hardware-backed
  signer and the private key never enters repository automation or CI.
- The only release assets are the certified Composer tar, `certification.json`, and `SHA256SUMS` covering those
  first two assets. Create and verify a complete draft before its separately authorized immutable publication.
- Verify the remote tag object and peeled commit, immutable release state, exact assets and digests, and any
  offered attestations independently. Ambiguous state stops for reconciliation rather than retrying an effect.
- T-00041 remains the separate Packagist projection and installed-consumer qualification boundary. A
  publication receipt identifies the exact `main` commit, tag, release, and three verified assets for that work.

## Consequences

Certification, tag identity, GitHub publication, and Packagist projection remain distinct facts. T-00017 can
accept the tree-identical `develop` merge, while T-00035 first carries that accepted candidate through the
`release/1.2.0` review branch and must freshly certify the exact `main` merge before signing because the certified
and tagged commit identities must ultimately match.

ADR 0016 remains applicable for signer custody, signed annotated tags, draft-first immutable publication,
independent provider postconditions, and the separate Packagist boundary. Its unused publication-environment,
release sequencing, and zip/release-notes/evidence-asset requirements no longer apply where they conflict with
this decision.

## Rejected Alternatives

Treating the accepted `develop` tree as automatically tag-ready was rejected because tree equality does not
prove the final remote `main` commit. Publishing before the complete draft is verified was rejected because
it leaves no safe correction point. Reintroducing a release workflow or CI-held signing material was rejected
because it weakens the operator-controlled boundary established by ADR 0025.
