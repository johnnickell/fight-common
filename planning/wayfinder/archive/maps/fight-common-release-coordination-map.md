# Fight Common Release Coordination

**Label:** `wayfinder:map`
**Status:** Closed

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

**Done** = every linked decision ticket is closed, the map links to its epic, TICKETs, and executable-ticket
handoff, and no Wayfinder decision remains before normal implementation planning resumes.

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

- [Establish the release-coordination destination and standing boundaries](../../tickets/archive/WF-001-release-destination-and-boundaries.md)
  fixed the project scope, skill catalog, deterministic-first principle, authorization model,
  branch topology, support baseline, compatibility evidence, publication authority, provenance,
  and package boundary.
- [Define supported release lines and the compatibility contract](../../tickets/archive/WF-002-supported-lines-and-compatibility-contract.md)
  fixed manifested public operations and behavior, non-structural compatibility, support-policy
  data and clocks, canonical `vX.Y.Z` tags and immutable baselines, affected-line proof, patch
  exceptions, SemVer authorization, and composed fail-closed certification evidence.
- [Design deterministic release commands and evidence](../../tickets/archive/WF-003-deterministic-release-commands-and-evidence.md)
  fixed the single command surface, content-addressed plans, durable run state, postcondition-driven
  resume, machine results, evidence-manifest authority, explicit stop states, publication authorization,
  and test seams. Exact implementation and hosted-operation details remain deferred to dependent tickets.
- [Define the plan, package, certify, and publish skill contracts](../../tickets/archive/WF-004-release-skill-contracts.md)
  fixed one-phase skill ownership, `.runs`-only planning bookkeeping, immutable source/candidate/
  baseline/support-policy bindings, bounded local and per-effect external approvals, composed
  certification, durable stop handoffs, single-source routing, and closed capability boundaries.
- [Define patch and maintenance-line workflows](../../tickets/archive/WF-005-patch-and-maintenance-workflows.md)
  fixed the reviewed-fix boundary, supported-line and oldest-first selection, per-line compatibility
  and certification, ordered forward ports, safe current-line integration, maintenance lifecycle,
  EOL preservation, and guided urgent handling without a safety bypass.
- [Define GitHub and Packagist publication and provenance](../../tickets/archive/WF-006-publication-and-provenance.md)
  fixed the OpenPGP signer and custody boundary, signed-tag and commit authority, release topology,
  immutable GitHub publication gate, deterministic assets, Packagist observation and recovery,
  clean-install proof, single-operator approval, and postcondition-driven resumption.
- [Define the release-operations runbook](../../tickets/archive/WF-007-release-operations-runbook.md)
  fixed the single dispatcher and journey-card information architecture, routing precedence,
  operator loop, stop and cancellation handling, bounded troubleshooting, Git-flow examples,
  oldest-supported-line routing, and independent publication recovery.
- [Synthesize the release implementation epic, TICKET, and tickets](../../tickets/archive/WF-008-release-implementation-handoff.md)
  fixed the four-TICKET decomposition, first normal-release vertical slice, deterministic boundary fakes,
  ticket-sized branch and completion rules, implementation order, one-home planning migration,
  composed acceptance gate, and an initial eight executable implementation slices. After the full TICKETs were
  approved, the oversized foundation, publication, patch, and integration slices were refined into twelve.
  The result is [EPIC-00003](../../../epics/archive/00003-EPIC.md), [TICKET-00010](../../../tickets/archive/00010-TICKET.md) through
  [TICKET-00013](../../../tickets/archive/00013-TICKET.md), and [TASK-00032](../../../tasks/archive/00032-TASK.md) through
  [TASK-00043](../../../tasks/archive/00043-TASK.md).

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-001](../../tickets/archive/WF-001-release-destination-and-boundaries.md) | Establish the release-coordination destination and standing boundaries | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | — |
| [WF-002](../../tickets/archive/WF-002-supported-lines-and-compatibility-contract.md) | Define supported release lines and the compatibility contract | wayfinder:research, wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | [WF-001](../../tickets/archive/WF-001-release-destination-and-boundaries.md) |
| [WF-003](../../tickets/archive/WF-003-deterministic-release-commands-and-evidence.md) | Design deterministic release commands and evidence | wayfinder:research, wayfinder:grilling, wayfinder:prototype, wayfinder:domain-modeling | AFK -> HITL | Closed | [WF-001](../../tickets/archive/WF-001-release-destination-and-boundaries.md) |
| [WF-004](../../tickets/archive/WF-004-release-skill-contracts.md) | Define the plan, package, certify, and publish skill contracts | wayfinder:grilling, wayfinder:prototype, wayfinder:domain-modeling | HITL | Closed | [WF-002](../../tickets/archive/WF-002-supported-lines-and-compatibility-contract.md), [WF-003](../../tickets/archive/WF-003-deterministic-release-commands-and-evidence.md) |
| [WF-005](../../tickets/archive/WF-005-patch-and-maintenance-workflows.md) | Define patch and maintenance-line workflows | wayfinder:grilling, wayfinder:prototype, wayfinder:domain-modeling | HITL | Closed | [WF-002](../../tickets/archive/WF-002-supported-lines-and-compatibility-contract.md), [WF-003](../../tickets/archive/WF-003-deterministic-release-commands-and-evidence.md) |
| [WF-006](../../tickets/archive/WF-006-publication-and-provenance.md) | Define GitHub and Packagist publication and provenance | wayfinder:research, wayfinder:grilling | AFK -> HITL | Closed | [WF-003](../../tickets/archive/WF-003-deterministic-release-commands-and-evidence.md), [WF-004](../../tickets/archive/WF-004-release-skill-contracts.md) |
| [WF-007](../../tickets/archive/WF-007-release-operations-runbook.md) | Define the release-operations runbook | wayfinder:grilling, wayfinder:prototype, wayfinder:domain-modeling | HITL | Closed | [WF-004](../../tickets/archive/WF-004-release-skill-contracts.md), [WF-005](../../tickets/archive/WF-005-patch-and-maintenance-workflows.md), [WF-006](../../tickets/archive/WF-006-publication-and-provenance.md) |
| [WF-008](../../tickets/archive/WF-008-release-implementation-handoff.md) | Synthesize the release implementation epic, TICKET, and tickets | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | [WF-002](../../tickets/archive/WF-002-supported-lines-and-compatibility-contract.md), [WF-003](../../tickets/archive/WF-003-deterministic-release-commands-and-evidence.md), [WF-004](../../tickets/archive/WF-004-release-skill-contracts.md), [WF-005](../../tickets/archive/WF-005-patch-and-maintenance-workflows.md), [WF-006](../../tickets/archive/WF-006-publication-and-provenance.md), [WF-007](../../tickets/archive/WF-007-release-operations-runbook.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
Release destination ──┬──→ Supported lines ──┬──→ Skill contracts ──┬──→ Publication
                      └──→ Deterministic commands ┘                  └──→ Runbook
                                      └──→ Maintenance ────────────────┘

All release decisions ──→ EPIC-00003 / PRD-00010 through PRD-00013 / T-00032 through T-00043
```

## Frontier

None. The implementation handoff is complete; implementation requires its normal ticket and branch approvals.

## Not yet specified (fog)

- Exact release-evidence manifest fields, storage locking, event-chain integrity, and detailed-log
  retention.
- Actual signer fingerprint, operator identity, and hosted environment provisioning remain
  implementation and configuration work for the release handoff.

## Out of scope

- Fight CMS Base Releases, Site Releases, production promotion, deployment, DNS, infrastructure,
  databases, Media, queues, or operational recovery.
- Implementing `.agents` skills, commands, workflows, runbooks, or branch protection while charting.
- Publishing a Fight Common release or changing existing branches, tags, GitHub Releases, Packagist
  state, or consumer repositories.
- Rewriting or signing the legacy lightweight `v1.0.0` and `v1.1.0` tags.
- Making external consumer builds hidden release blockers without a separately approved, owned
  compatibility contract.
