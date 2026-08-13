---
id: T-00055
prd: PRD-00015
title: Resolve the Five Isolated and Combined Framework Fixtures
status: ready-for-agent
blocked_by: T-00049,T-00050,T-00051,T-00052,T-00053,T-00054,T-00057
---

# Resolve the Five Isolated and Combined Framework Fixtures

## What to Build

After WF-015 selects the exact supported package lines and default capability compositions, add one minimal
in-repository Composer fixture for Symfony, Laravel, Yii 3, CodeIgniter 4, and Slim plus the root combined set.
Each fixture proves the selected native or portable public journey without relying on unrelated framework
packages or creating another repository.

## Decision Handoff

PRD-00015 (Framework Supported Lines and Default Capability Compositions) supplies the exact supported
package lines, default compositions, and integration responsibilities; T-00057 pins Fight Common's own
Symfony components to the `^8.1` floor before this ticket's combined lane resolves. This ticket is
`ready-for-agent` when its blocking edges are terminal; the WF-017 prototypes are the downstream seam for
composition behavior, not a gate on resolving the fixture lanes.

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

PRD-00015 — Framework Supported Lines and Default Capability Compositions.
