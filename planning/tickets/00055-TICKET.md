---
id: T-00055
prd: PRD-00014
title: Resolve the Five Isolated and Combined Framework Fixtures
status: needs-info
blocked_by: T-00049,T-00050,T-00051,T-00052,T-00053,T-00054
---

# Resolve the Five Isolated and Combined Framework Fixtures

## What to Build

After WF-015 selects the exact supported package lines and default capability compositions, add one minimal
in-repository Composer fixture for Symfony, Laravel, Yii 3, CodeIgniter 4, and Slim plus the root combined set.
Each fixture proves the selected native or portable public journey without relying on unrelated framework
packages or creating another repository.

## Decision Prerequisite

WF-015 must close with exact maintained package names, constraints, native facilities, and composition choices.
When that handoff exists, replace this decision prerequisite with the canonical implementation-ticket edge,
add any prototype ticket that genuinely gates a selected shared adapter, and move this ticket to
`ready-for-agent`.

## Acceptance Criteria

- [ ] Five minimal fixture roots require the Fight Common candidate plus exactly one framework and its selected
      native response and capability dependencies.
- [ ] Every fixture resolves and records both the lowest and latest supported set with exact versions and lock
      digests.
- [ ] The root development set resolves all five exact supported ranges together and runs the combined adapter
      suite; lowest combined resolution runs when the approved constraints make it meaningful.
- [ ] Each fixture proves native response construction, dependency-injection wiring, routing, transaction
      composition, and the other capabilities selected by the approved worksheet.
- [ ] Fixtures exercise existing portable adapters or direct bindings where those satisfy the contract; they do
      not require equal framework-branded implementations.
- [ ] No new shared adapter is added without the prototype evidence and dependency edge required by PRD-00014.
- [ ] Framework and optional adapter packages remain outside production requirements, while exact activation
      packages are named in suggestions and normative version documentation.
- [ ] Fixture locks remain disposable inputs and every lane emits the stable receipt consumed by certification.
- [ ] An unavailable, skipped, failed, conflicted, or indeterminate isolated or combined set fails its lane and
      produces one resumable next action.

## Verification

Full submit gate, `./bin/planning-check`, lowest and latest solves for all five isolated fixtures, combined
resolution and adapter probes, package-surface validation, and deterministic failed-lane fixtures.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
