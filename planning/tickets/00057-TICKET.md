---
id: TICKET-00057
epic: EPIC-00014
title: Shorten the full hosted CI gate
status: ready-for-agent
---

# Shorten the full hosted CI gate

## Problem statement

Maintainers wait 22–29 minutes for recent hosted checks. The Functional suite, especially the subscription capacity-lock matrix, accounts for much of the elapsed time.

## Solution and boundaries

Target a 10-minute median for the full hosted gate, including setup and all required checks. Start with the subscription matrix and examine the full critical path. Preserve exact 100% direct Unit coverage, meaningful framework and integration checks, and existing release probes. Integration and Functional breadth may shrink if the lost assurance and reason are documented. Do not treat an isolated passing retry as proof a test is stable.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| A maintainer compares before and after performance | `./bin/build` through the hosted Tests workflow | Fresh run timing, phase logs, and coverage reports | No package events | Comparable timing and coverage evidence |
| A maintainer reviews the outcome before tagging | No tag command in this TICKET | Median and test trade-offs | No package events | If above ten minutes, explicit maintainer decision to hold or proceed |

## Validation and permissions

Use at least three comparable fresh runs per side, not reused receipts. Report failures, nondeterminism, and changed test breadth. No new runtime permissions or publication authority.

## Acceptance and evidence

Show phase and end-to-end timings, exact Unit coverage, retained checks, and any trade-offs. Preserve the baseline before changing tests or runner behavior. Verify tooling parity separately from timing improvements so changes are attributable.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00218](../tasks/00218-TASK.md) | Lock the hosted timing baseline | done |
| [TASK-00219](../tasks/00219-TASK.md) | Reduce subscription capacity-lock test cost | done |
| [TASK-00230](../tasks/00230-TASK.md) | Shorten the remaining gate and measure the combined result | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

[WF-049](../wayfinder/tickets/WF-049-set-test-and-ci-runtime-target.md) owns the timing baseline and initial investigation. Requirements and TASK decomposition are approved. [TASK-00218](../tasks/00218-TASK.md) recorded the accepted hosted timing baseline. [TASK-00219](../tasks/00219-TASK.md) reduced the Functional subscription capacity-lock matrix from 84 cases to 19, with independent review and QA. The local measurements do not establish hosted savings; TASK-00230 owns that comparison.
