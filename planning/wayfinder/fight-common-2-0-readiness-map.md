# Wayfinder Map: Fight Common v2.0.0 readiness

**Label:** `wayfinder:map`
**Status:** Closed

> This map is an index. Its linked decision tickets own the decisions.

## Destination

Prepare a bounded pre-tag cleanup plan for Fight Common 2.0: a short glossary, a faster trustworthy test gate, container-run PHP tooling with host entry points, and a documented consumer upgrade.

**Done** = the decisions below are closed, remaining fog is resolved or excluded, and the map links to the approved implementation planning handoffs. This is not release certification or permission to tag.

## Notes

- Use the sibling Fight Access Control glossary as the style reference and its tooling and Fight Agent OS as implementation references, not as requirements to copy.
- Keep the project’s [test quality and coverage rules](../../docs/engineering/standards/Testing.md#test-quality-and-suite-balance) and [package compatibility policy](../agents/project-profile.md#identity-and-architecture) in force. Compare public changes to a verified tagged 1.x baseline, not just current development code.
- At checkout `1b4a630a`, there is no root `cli-config.php`, tracked history for that root path in local refs, or reference in the checked entry points. No cleanup item is justified without the exact path and checkout where it was seen.

## Decisions so far

1. [WF-049 — Set the test and CI runtime target](tickets/WF-049-set-test-and-ci-runtime-target.md) is closed. The approved target is a 10-minute median for the full hosted gate. Investigate the subscription capacity-lock matrix first. Keep exact 100% direct Unit coverage; Integration and Functional breadth may shrink with explicit trade-offs.
2. [WF-050 — Replace project context with a concise glossary](tickets/WF-050-define-the-glossary-cutover.md) is closed. Create one root glossary, relocate the current contracts, and delete `CONTEXT.md` after verifying references. Do not leave a pointer.
3. [WF-051 — Draw the host and container tooling boundary](tickets/WF-051-draw-the-tooling-runtime-boundary.md) is closed. Move the quality runner to container-run PHP with Composer dev autoload; keep offline planning and runtime orchestration on the host.
4. [WF-052 — Define the 2.0 removal and upgrade contract](tickets/WF-052-define-the-2-0-upgrade-contract.md) is closed. Use v1.5.1 as the upgrade baseline, remove verified eligible deprecations, document the breaks by component, and add Base64URL helpers that emit unpadded output and accept optional standard padding on decode. The maintainer also requires strict-only construction for `ResultSet` and `Pagination`.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-049](tickets/WF-049-set-test-and-ci-runtime-target.md) | Set the test and CI runtime target | wayfinder:research, wayfinder:grilling | HITL | Closed | — |
| [WF-050](tickets/WF-050-define-the-glossary-cutover.md) | Replace project context with a concise glossary | wayfinder:research, wayfinder:grilling | HITL | Closed | — |
| [WF-051](tickets/WF-051-draw-the-tooling-runtime-boundary.md) | Draw the host and container tooling boundary | wayfinder:research, wayfinder:grilling | HITL | Closed | — |
| [WF-052](tickets/WF-052-define-the-2-0-upgrade-contract.md) | Define the 2.0 removal and upgrade contract | wayfinder:research, wayfinder:grilling | HITL | Closed | — |
<!-- /planning:decisions -->

## Blocking relationships

No hard dependencies between decisions. Capture the current gate baseline before changing tooling or pruning tests so timing comparisons remain useful. Finish the decisions before decomposing the approved destinations.

## Frontier

None. All four decisions are closed and the EPIC has been approved and written.

## Planning handoff

[EPIC-00014 — Fight Common 2.0 readiness](../epics/00014-EPIC.md) owns the approved destination. If the full hosted gate misses the ten-minute median target, the maintainer must decide whether to hold the release or accept the measured outcome before tagging. TICKET decomposition is approved and recorded under the EPIC. The approved tooling amendment makes the quality runner the first migration and requires a concrete justification for any Python retained. TASK decomposition is approved and recorded under the four TICKETs. The [TASK Board](../tasks/BOARD.md) owns execution selection; implementation has not started.

## Follow-up work outside the decision map

- Which subscription matrix cases prove distinct behavior and whether historical failures recur in suite context are follow-up investigation under WF-049’s approved scope.
- The exact released compatibility surface and consumer migration impact are follow-up inventory under WF-052’s approved scope.

## Out of scope

- Implementation and test deletions during mapping, lowering required Unit coverage or weakening release evidence, changing supported dependency floors by default, and signing, tagging or publishing the release.
