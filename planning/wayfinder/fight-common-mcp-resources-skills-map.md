# Wayfinder Map: Reusable MCP Resources and Skills

**Label:** `wayfinder:map`
**Status:** Closed

> This map is an index, not a second requirements authority. [EPIC-00007](../epics/00007-EPIC.md) owns the accepted
> destination following WF-045 and the completed grill. Existing Tools/HTTP work retains its own boundaries.

## Destination

Chart the smallest reusable Common MCP content-serving foundation that lets Agent OS expose authorized planning
context as Resources, find it through read-only Tools, apply changes through existing authorized use cases, and
serve static revisioned Skills that its Pi Harness can discover and load safely. Separate Common protocol
mechanics from Agent OS planning, catalog, Harness and Workflow ownership and Access Control/consumer authority.

**Done** = the linked material decision is accepted or explicitly declined, remaining fog is resolved or delegated,
and this map links to the resulting EPIC handoff. **Done:** John confirmed the scope and Common acceptance;
[EPIC-00007](../epics/00007-EPIC.md) is the resulting handoff. Its subsequently approved TICKET decomposition is
linked below. All three TICKETs now have approved TASK splits; implementation has not started.

## Notes

- User selected an isolated planning worktree. Base is local `develop` at `6977533`; active TASK-00105 code at
  `381539f` was inspected read-only, not merged into this branch. Agent OS was inspected read-only at `a39bfc3`.
  At EPIC handoff, the original checkout's `develop` is `52ff756`, including merged TASK-00105 (PR #163). The
  research checkpoint remains historical. Publication preparation later fast-forwarded the planning branch to
  `52ff756`; its PR diff remains planning-only.
- [Current evidence and ownership map](research/WF-044-mcp-resources-skills-evidence.md) distinguishes released,
  merged, in-progress, planned and missing capabilities and corrects earlier conformance/Pi assumptions.
- Approved protocol remains **MCP `2026-07-28`**. The official stable Skills extension supports it.
- The [closed MCP-over-HTTP map](fight-common-mcp-http-support-map.md) and
  [Common EPIC-00006](../epics/00006-EPIC.md) remain the authority for Tools, HTTP, streaming/cancellation,
  protected input and OAuth work. Resources/Skills are a new boundary, not a reopened old map.
- [Proposed first delivery](research/WF-045-mcp-resources-skills-delivery-proposal.md) contains the minimal provider
  seam, exact protocol requirements, finite defaults/recovery, adjacent-capability classifications, dependencies,
  separately owned downstream work, end-to-end journey and behavioral acceptance scenarios.
- Agent OS's accepted SDK-only server ownership wording conflicts with this accepted Common role. Preserve the
  downstream record until its owner explicitly reconciles it; do not treat an older assessment as current code.

## Decisions so far

1. **[Verify Resources, Skills and Harness evidence — WF-044](tickets/WF-044-verify-resources-skills-and-harness-evidence.md)
   is closed as research.** Compatible official contracts and actual implementation gaps are established. This
   accepts factual findings, not proposed product scope, runtime support or a conformance claim.
2. **[Select the first Resources and Skills delivery — WF-045](tickets/WF-045-select-the-first-resources-and-skills-delivery.md)
   is closed.** John accepted a separate Common EPIC and clarified that each Skill revision preserves `SKILL.md`
   plus nested references, template files, scripts and other resources. A complete manifest and whole-Skill
   authorization coexist with lazy individual-file reads; no flattening, obligatory archive or bulk loading.
   Directory RPC and server-side rendering may wait; separate variants are created only when genuinely needed.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-044](tickets/WF-044-verify-resources-skills-and-harness-evidence.md) | Verify Resources, Skills and Harness evidence | wayfinder:research | AFK | Closed | — |
| [WF-045](tickets/WF-045-select-the-first-resources-and-skills-delivery.md) | Select the first Resources and Skills delivery | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | [WF-044](tickets/WF-044-verify-resources-skills-and-harness-evidence.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-044 verified evidence (closed)
  └──→ WF-045 delivery/ownership decision (closed)
         └──→ EPIC-00007 (approved; handoff complete)
                ├──→ TICKET-00028: TASK-00117 → TASK-00118
                │      └──→ TICKET-00029: TASK-00119 → TASK-00120
                └──→ TICKET-00030: TASK-00121 → TASK-00122

TASK-00105 (completed HTTP) ──→ TASK-00117 and TASK-00121 independently
TASK-00107 (Tools) + TASK-00120 (Skills/Resources) + TASK-00121 (bounds) ──→ TASK-00122
Common endpoint acceptance ──→ later consumer journey
Agent OS providers/Harness + live authorization ──→ actual Pi acceptance (not Common gate)
```

## Frontier

None. Both decisions are closed and the EPIC grill is complete. The subsequently approved TICKET split does not
reopen Wayfinder or authorize implementation.

## Planning handoff

**[EPIC-00007 — Reusable MCP Resources and Structured Skills](../epics/00007-EPIC.md)** is approved and owns the
accepted destination and completion evidence. Common must demonstrate Resources and structured Skills through
the existing guarded endpoint, including Tools coexistence and injected consumer authorization decisions.
Package fixtures, applicable conformance checks with honest gap reporting, compatibility evidence, documentation
and the full gate establish Common completion. The real Agent OS/Pi journey remains downstream acceptance.

[WF-045's resolution](tickets/WF-045-select-the-first-resources-and-skills-delivery.md#resolution) and the
[design/evidence proposal](research/WF-045-mcp-resources-skills-delivery-proposal.md) retain the structured-content
clarification and supporting detail. Sensible finite defaults and optional validated overrides are accepted;
exact interfaces, numeric values and implementation slicing remain routine later design, without new human
approval gates. The approved requirement handoff now comprises [authorized Resources — TICKET-00028](../tickets/00028-TICKET.md),
[structured Skills — TICKET-00029](../tickets/00029-TICKET.md) and
[combined endpoint acceptance — TICKET-00030](../tickets/00030-TICKET.md). TICKET-00028 now hands off to
[guarded discovery — TASK-00117](../tasks/00117-TASK.md), followed by
[authorized reads — TASK-00118](../tasks/00118-TASK.md), through the separately approved decomposition.
TICKET-00029 continues with [validated Skill revisions — TASK-00119](../tasks/00119-TASK.md), then
[complete Skills list/get — TASK-00120](../tasks/00120-TASK.md). The first Skill slice serves files as Resources;
the second advertises the complete extension. TICKET-00030 adds
[bounded ingress — TASK-00121](../tasks/00121-TASK.md) independently of that capability chain and
[the combined journey — TASK-00122](../tasks/00122-TASK.md), joining Tools, Skills/Resources and bounded ingress.
No implementation has begun. Agent OS/Access Control writes remain separately authorized.

## Delegated design and downstream evidence

- Exact PHP interfaces, parser and bounded cursor/encoding implementation: ordinary later design, not speculative
  decision tickets or repeated human approvals.
- Compatible released client SDK selection and actual Pi integration proof: downstream Harness evidence.
- Atomic immutable catalog storage, current Resource/Skill authorization and cached-content behavior: downstream
  Agent OS/Access Control work, explicitly separated in the proposal.
- TASK-00121 owns additive request-byte/decode limits; TASK-00122 owns any necessary additive Resource-link output
  integration. Coordinate with HTTP/Tool owners before implementation without expanding their existing TASKs.

## Out of scope

- Implementing or modifying Agent OS/Access Control. Requirement TICKETs and all six TASKs were created only through
  subsequent explicitly approved decomposition workflows, not by Wayfinder itself.
- Changing Common's protocol target, duplicating ongoing Tools/HTTP/SSE/cancellation/OAuth work, or treating
  request progress/MCP Tasks as durable Workflow authority.
- Arbitrary origins, federation, dynamic Skills, generic policy engines, unrestricted filesystem serving,
  arbitrary host execution, sandbox administration, releases, publication, merge or deployment.
