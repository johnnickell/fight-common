# Establish the release-coordination destination and standing boundaries

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** None

## Question

What is this Wayfinder finding a route to, and which release responsibilities belong to Fight
Common rather than Fight CMS, general AIOS, Packagist, or consuming applications?

## Resolution

Fight Common will receive a repository-local, implementation-ready release-coordination design
before any release skills are built. The planned catalog contains:

- `fight-common-plan-release`
- `fight-common-package-release`
- `fight-common-certify-release`
- `fight-common-publish-release`
- `fight-common-maintain-release-line`
- `fight-common-patch-release-line`

The skills are Guided Operator Workflows over deterministic repository commands. Release rules,
compatibility judgments that can be automated, evidence production, and state verification belong
in commands and CI rather than prompt prose. Prompts discover state, explain evidence, enforce
approval boundaries, invoke allowlisted commands, and present resumable next actions.

Authorization is separated by effect:

- planning is read-only;
- packaging requires approval for local branch, file, and candidate-artifact mutations;
- certification verifies and records evidence without external mutation;
- publishing requires explicit approval before every bounded merge, tag, push, GitHub Release, or
  Packagist-affecting action;
- maintenance and patch workflows require separate approval for each affected release line and
  forward port.

`fight-common-patch-release-line` accepts an already-reviewed fix commit or merged pull request. It
does not implement the fix. It selects the oldest affected supported base, creates the patch work,
updates release surfaces, and hands off for certification. Every forward port receives its own pull
request and certification evidence. Conflicts or semantic differences stop for human judgment.

There is no `hotfix/*` flow. Urgency is release-plan metadata and cannot bypass review or
certification. Features and unreleased fixes follow git-flow from `develop`; released defects begin
on the oldest affected supported maintenance line and move forward explicitly.

The initial support policy is:

- the current minor receives bug and security fixes;
- the immediately previous minor receives security, data-loss, and critical compatibility fixes
  for six months after the next minor is published;
- older minors are end-of-life and read-only;
- only the latest patch in a supported minor is supported.

`SUPPORTED_VERSIONS.md` will become the deterministic human-facing authority for those states and
dates. Patch eligibility is strict: incompatible public API, Composer platform, dependency, or
constraint changes fail unless the release class explicitly permits and documents them.

Certification must compare the public API and Composer constraints with the appropriate prior tag.
It must test repository-locked, lowest-permitted, and latest-permitted dependency lanes. External
consumer builds remain advisory unless Fight Common separately adopts and owns their contract.

GitHub is the publication authority. Packagist is a verified downstream projection of the exact
version and source commit. Propagation failure leaves a resumable incomplete publication and any
manual Packagist action requires separate approval.

Future releases use signed annotated tags bound to the verified release commit. The existing
lightweight `v1.0.0` and `v1.1.0` tags remain untouched legacy history.

Minor and major releases use `release/<version>` from `develop`, merge the certified result to
`main`, sign the verified `main` merge, merge release results back to `develop`, and create the
`<major>.<minor>` maintenance branch from the signed release commit. Patch work uses
`patch/<version>-<slug>` from the oldest affected maintenance line, then moves forward through
separate reviewed pull requests. An older maintenance branch never replaces newer `main` contents.

`package-release` prepares the immutable Git candidate, release surfaces, Composer metadata, and
candidate evidence; it does not create a competing authoritative distribution. Certification
builds and clean-installs an exported Composer archive. The signed tag remains canonical while
GitHub and Packagist provide downstream source and distribution delivery.

Deterministic checks recommend the minimum valid SemVer increment, but a human authorizes the exact
version. Every downstream plan and action binds to that version and candidate commit.

## References

- [Symfony maintained releases](https://symfony.com/releases)
- [Symfony maintenance policy](https://symfony.com/doc/current/contributing/code/maintenance.html)
- [Symfony pull-request guidance](https://symfony.com/doc/current/contributing/code/pull_requests.html)
