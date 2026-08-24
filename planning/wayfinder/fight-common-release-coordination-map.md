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

**Done** = every linked decision ticket is closed, the map links to its epic, PRDs, and executable-ticket
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
- [Define GitHub and Packagist publication and provenance](tickets/WF-006-publication-and-provenance.md)
  fixed the OpenPGP signer and custody boundary, signed-tag and commit authority, release topology,
  immutable GitHub publication gate, deterministic assets, Packagist observation and recovery,
  clean-install proof, single-operator approval, and postcondition-driven resumption.
- [Define the release-operations runbook](tickets/WF-007-release-operations-runbook.md)
  fixed the single dispatcher and journey-card information architecture, routing precedence,
  operator loop, stop and cancellation handling, bounded troubleshooting, Git-flow examples,
  oldest-supported-line routing, and independent publication recovery.
- [Synthesize the release implementation epic, PRD, and tickets](tickets/WF-008-release-implementation-handoff.md)
  fixed the four-PRD decomposition, first normal-release vertical slice, deterministic boundary fakes,
  ticket-sized branch and completion rules, implementation order, one-home planning migration,
  composed acceptance gate, and an initial eight executable implementation slices. After the full PRDs were
  approved, the oversized foundation, publication, patch, and integration slices were refined into twelve.
  The result is [EPIC-00003](../epics/00003-EPIC.md), [PRD-00010](../specs/00010-PRD.md) through
  [PRD-00013](../specs/00013-PRD.md), and [T-00032](../tickets/00032-TICKET.md) through
  [T-00043](../tickets/00043-TICKET.md).

## Tickets

| Ticket | Type | Mode | Status | Depends On |
|---|---|---|---|---|
| [Establish the release-coordination destination and standing boundaries](tickets/WF-001-release-destination-and-boundaries.md) | Grilling / Domain Modeling | HITL | **Closed** | — |
| [Define supported release lines and the compatibility contract](tickets/WF-002-supported-lines-and-compatibility-contract.md) | Research / Grilling / Domain Modeling | HITL | **Closed** | Release destination |
| [Design deterministic release commands and evidence](tickets/WF-003-deterministic-release-commands-and-evidence.md) | Research / Grilling / Prototype / Domain Modeling | AFK -> HITL | **Closed** | Release destination |
| [Define the plan, package, certify, and publish skill contracts](tickets/WF-004-release-skill-contracts.md) | Grilling / Prototype / Domain Modeling | HITL | **Closed** | Supported lines, deterministic commands |
| [Define patch and maintenance-line workflows](tickets/WF-005-patch-and-maintenance-workflows.md) | Grilling / Prototype / Domain Modeling | HITL | **Closed** | Supported lines, deterministic commands |
| [Define GitHub and Packagist publication and provenance](tickets/WF-006-publication-and-provenance.md) | Research / Grilling | AFK -> HITL | **Closed** | Deterministic commands, skill contracts |
| [Define the release-operations runbook](tickets/WF-007-release-operations-runbook.md) | Grilling / Prototype / Domain Modeling | HITL | **Closed** | Skill contracts, maintenance, publication |
| [Synthesize the release implementation epic, PRD, and tickets](tickets/WF-008-release-implementation-handoff.md) | Grilling / Domain Modeling | HITL | **Closed** | All release decisions |

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
