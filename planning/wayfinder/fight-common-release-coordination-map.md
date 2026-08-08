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
- Every consequential decision is explicitly human-approved. Independent frontier questions may be
  grouped in grilling rounds. Deterministic tooling may recommend or verify; it may not silently
  authorize local or external mutations.
- Refer to tickets by their linked names rather than bare identifiers.

## Decisions so far

- [Establish the release-coordination destination and standing boundaries](tickets/WF-001-release-destination-and-boundaries.md)
  fixed the project scope, skill catalog, deterministic-first principle, authorization model,
  branch topology, support baseline, compatibility evidence, publication authority, provenance,
  and package boundary.
- [Define supported release lines and the compatibility contract](tickets/WF-002-supported-lines-and-compatibility-contract.md)
  fixed manifested public operations and behavior, non-structural compatibility, support-policy
  data and clocks, canonical `vX.Y.Z` tags and immutable baselines, affected-line proof, patch
  exceptions, SemVer authorization, and composed fail-closed certification evidence.
- [Design deterministic release commands and evidence](tickets/WF-003-deterministic-release-commands-and-evidence.md)
  fixed the single command surface, content-addressed plans, durable run state, postcondition-driven
  resume, machine results, evidence-manifest authority, explicit stop states, publication authorization,
  and test seams. Exact implementation and hosted-operation details remain deferred to dependent tickets.
- [Define the plan, package, certify, and publish skill contracts](tickets/WF-004-release-skill-contracts.md)
  fixed one-phase skill ownership, `.runs`-only planning bookkeeping, immutable source/candidate/
  baseline/support-policy bindings, bounded local and per-effect external approvals, composed
  certification, durable stop handoffs, single-source routing, and closed capability boundaries.
- [Define patch and maintenance-line workflows](tickets/WF-005-patch-and-maintenance-workflows.md)
  fixed the reviewed-fix boundary, supported-line and oldest-first selection, per-line compatibility
  and certification, ordered forward ports, safe current-line integration, maintenance lifecycle,
  EOL preservation, and guided urgent handling without a safety bypass.

## Frontier

- [Define GitHub and Packagist publication and provenance](tickets/WF-006-publication-and-provenance.md)

## Waiting

- [Define the release-operations runbook](tickets/WF-007-release-operations-runbook.md) — waiting
  for WF-006, [Define GitHub and Packagist publication and provenance](tickets/WF-006-publication-and-provenance.md).
- [Synthesize the release implementation epic, PRD, and tickets](tickets/WF-008-release-implementation-handoff.md)
  — waiting for WF-006 and WF-007.

Choose only one frontier ticket per Wayfinder session. A ticket is takeable when every item in its
`Depends on` field is closed and it is not claimed by another session.

## Not yet specified

- Exact release-evidence manifest fields, storage locking, event-chain integrity, and detailed-log
  retention.
- Signing implementation, signer identity, key custody, and verification mechanism for future
  annotated tags.
- Exact archive normalization and committed exclusion policy.
- Exact Packagist observation and manually approved recovery mechanism when automatic propagation fails.
- Exact branch-protection and GitHub-environment changes required before workflows may mutate remote
  state.

## Out of scope

- Fight CMS Base Releases, Site Releases, production promotion, deployment, DNS, infrastructure,
  databases, Media, queues, or operational recovery.
- Implementing `.agents` skills, commands, workflows, runbooks, or branch protection while charting.
- Publishing a Fight Common release or changing existing branches, tags, GitHub Releases, Packagist
  state, or consumer repositories.
- Rewriting or signing the legacy lightweight `v1.0.0` and `v1.1.0` tags.
- Making external consumer builds hidden release blockers without a separately approved, owned
  compatibility contract.
