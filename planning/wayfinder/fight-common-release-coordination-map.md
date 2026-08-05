# Fight Common Release Coordination

**Label:** `wayfinder:map`
**Status:** Open

## Destination

Produce an implementation-ready design and executable planning handoff for Fight Common's local
release-coordination system. The design must define deterministic repository commands, evidence,
approval gates, resumability, and handoffs for these planned skills:

- `fight-common-plan-release`
- `fight-common-package-release`
- `fight-common-certify-release`
- `fight-common-publish-release`
- `fight-common-maintain-release-line`
- `fight-common-patch-release-line`

The way is clear when a later implementation effort can build the commands, `.agents` catalog,
runbook, CI integration, and policy updates without making another architectural or operational
release decision.

## Notes

- This is a planning-only Wayfinder. Produce decisions and an implementation handoff, not skills,
  release commands, GitHub configuration, Packagist mutations, branches, tags, or releases.
- `CLAUDE.md`, `composer.json`, `CHANGELOG.md`, `.github/workflows/`, `planning/`, existing
  maintenance branches, and existing tags are live evidence.
- Symfony is a precedent for strict patch compatibility, fixing the oldest maintained line first,
  and moving fixes forward. Fight Common owns its own policy and does not inherit Symfony's scale,
  calendar, or governance automatically.
- Every consequential decision is human-approved one at a time. Deterministic tooling may recommend
  or verify; it may not silently authorize local or external mutations.
- Refer to tickets by their linked names rather than bare identifiers.

## Decisions so far

- [Establish the release-coordination destination and standing boundaries](tickets/WF-001-release-destination-and-boundaries.md)
  fixed the project scope, skill catalog, deterministic-first principle, authorization model,
  branch topology, support baseline, compatibility evidence, publication authority, provenance,
  and package boundary.

## Frontier

- [Define supported release lines and the compatibility contract](tickets/WF-002-supported-lines-and-compatibility-contract.md)
- [Design deterministic release commands and evidence](tickets/WF-003-deterministic-release-commands-and-evidence.md)

Choose only one frontier ticket per Wayfinder session. A ticket is takeable when every item in its
`Depends on` field is closed and it is not claimed by another session.

## Not yet specified

- Whether the compatibility checker should be an existing PHP tool, a repository-owned comparison,
  or a composed set of checks.
- Exact release-evidence manifest schema, detailed-log custody, retention, and publication surface.
- Signing implementation, signer identity, key custody, and verification mechanism for future
  annotated tags.
- Exact Packagist observation and manually approved recovery mechanism when automatic propagation
  fails.
- Exact branch-protection and GitHub-environment changes required before the workflows may mutate
  remote state.
- Whether deterministic release commands should be one command with subcommands or several narrow
  commands.

## Out of scope

- Fight CMS Base Releases, Site Releases, production promotion, deployment, DNS, infrastructure,
  databases, Media, queues, or operational recovery.
- Implementing `.agents` skills, commands, workflows, runbooks, or branch protection while charting.
- Publishing a Fight Common release or changing existing branches, tags, GitHub Releases, Packagist
  state, or consumer repositories.
- Rewriting or signing the legacy lightweight `v1.0.0` and `v1.1.0` tags.
- Making external consumer builds hidden release blockers without a separately approved, owned
  compatibility contract.
