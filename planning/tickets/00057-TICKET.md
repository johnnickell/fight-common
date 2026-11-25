---
id: TICKET-00057
epic: EPIC-00014
title: Shorten the full hosted CI gate
status: done
---

# Shorten the full hosted CI gate

## Problem statement

Maintainers wait 22–29 minutes for recent hosted checks. The Functional suite, especially the subscription capacity-lock matrix, accounts for much of the elapsed time.

## Solution and boundaries

Target a 10-minute median for the full hosted gate, including setup and all required checks. Start with the subscription matrix and examine the full critical path. Preserve exact 100% direct Unit coverage, meaningful framework and integration checks, and existing release probes. Integration and Functional breadth may shrink if the lost assurance and reason are documented. Do not treat an isolated passing retry as proof a test is stable.

The maintainer also approved a suite-wide target of **fewer than 100 Functional cases (99 maximum)**, down from the 1,221 recorded by TASK-00219. Count expanded PHPUnit cases, including each data-provider case, and report skips separately. Do not meet the target by skipping cases or merely relabeling full journeys. Keep critical end-to-end journeys and representative framework smoke tests in Functional, real database and framework boundary checks in Integration, and isolated behavior in Unit. Preserve meaningful regression protection, exact 100% direct Unit coverage, and existing release probes. The case-count target and hosted timing target are separate acceptance measures.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| A maintainer compares before and after performance | `./bin/build` through the hosted Tests workflow | Fresh run timing, phase logs, and coverage reports | No package events | Comparable timing and coverage evidence |
| A maintainer reviews the outcome before tagging | No tag command in this TICKET | Median and test trade-offs | No package events | If above ten minutes, explicit maintainer decision to hold or proceed |

## Validation and permissions

Use at least three comparable fresh runs per side, not reused receipts. Report failures, nondeterminism, and changed test breadth. No new runtime permissions or publication authority.

## Acceptance and evidence

Show phase and end-to-end timings, exact Unit coverage, retained checks, and any trade-offs. Preserve the baseline before changing tests or runner behavior. Verify tooling parity separately from timing improvements so changes are attributable.

For the suite-size target, show the complete Functional count and comparable suite timings before and after the changes, and map consolidated or moved cases to the protection retained. TASK-00231 started with MCP Task Live and Task Cancellation and now owns the remaining reduction to at most 99 expanded cases. Neither the first slice nor the hosted timing comparison proves that the suite reached 99 cases.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00218](../tasks/00218-TASK.md) | Lock the hosted timing baseline | done |
| [TASK-00219](../tasks/00219-TASK.md) | Reduce subscription capacity-lock test cost | done |
| [TASK-00230](../tasks/00230-TASK.md) | Shorten the remaining gate and measure the combined result | done |
| [TASK-00231](../tasks/00231-TASK.md) | Reduce the Functional suite to at most 99 expanded cases | done |
<!-- /planning:children -->

## Decisions and progress

[WF-049](../wayfinder/tickets/WF-049-set-test-and-ci-runtime-target.md) owns the timing baseline and initial investigation. Requirements and TASK decomposition are approved. [TASK-00218](../tasks/00218-TASK.md) recorded the accepted hosted timing baseline. [TASK-00219](../tasks/00219-TASK.md) reduced the Functional subscription capacity-lock matrix from 84 cases to 19, with independent review and QA. The local measurements do not establish hosted savings; TASK-00230 owns that comparison.
