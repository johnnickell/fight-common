# Set the test and CI runtime target

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common v2.0.0 readiness](../fight-common-2-0-readiness-map.md)
**Depends on:** —

## Question

What fresh local and hosted gate times should 2.0 target, and which changes can achieve them without losing useful proof?

## Must decide

- Use [the hosted Tests workflow](../../../.github/workflows/tests.yml), which runs `./bin/build`, and comparable fresh local evidence to separate startup and dependency resolution from static checks, coverage, and each test suite. Report cold and warm timings separately and identify the slowest tests.
- Investigate recurring or reproducible flakes first. Fix avoidable nondeterminism; remove or consolidate cases only where they add no distinct useful proof, or replace them with deterministic equivalent checks. Do not treat rarity of failure as evidence of redundancy.
- Set a runtime target from the measured baseline. Compare before/after under matching conditions while preserving exact required Unit coverage, meaningful framework and integration behavior, and existing release probes.

## Evidence so far

- The 12 recent successful hosted runs sampled took **22m16s–29m04s** from workflow start to final update. The [Nov 20 sampled job](https://github.com/johnnickell/fight-common/actions/runs/44686662705/job/136122496875) spent **27m51s** in `./bin/build`: **5m24s** before Composer validation (planning and preparation, including images and dependencies), **1m33s** in pre-test quality checks, **2m51s** in the Unit coverage phase, **2m00s** in Integration, and **15m35s** in Functional. The rest was final checks and reporting. Functional alone was about 56% of the gate. These are hosted runner figures, not local or warm-cache targets.
- The nearby [TASK-00217 local receipt](../../tasks/00217-TASK.md) reports **38m37s** elapsed, including approximately **3m01s Unit**, **3m01s Integration**, and **21m19s Functional** in the original log. This is historical, not an exact-HEAD cold/warm pair. The fresh `./bin/build --force` at `1b4a630a96aa6975065a82f851e5c7ad55ec295f` took **42m15s** from the stopped project stack with local Docker layers available: **3m16s Unit**, **3m30s Integration**, and **20m54s Functional**. All three suites and the exact coverage check passed, but the build exited **1** because planning changed during execution; no successful cache receipt was published. Treat it as timing evidence, not an accepted gate. The log, exit, and copied test-run history are under `.runs/notes/WF-049/`.
- Six recent hosted failures sampled did not reach PHPUnit: five were broken references to ignored local `.runs` evidence in the clean checkout, and one failed during preparation. They are not product-test flakes.
- Start current reproduction with the previously observed `McpTaskLiveJourneyTest` `sqlite-slim` frame-read failure ([TASK-00204](../../tasks/00204-TASK.md)) and `McpSubscriptionJourneyTest` `sqlite-expired-body` freshness failure ([TASK-00182](../../tasks/00182-TASK.md)). Both passed a subsequent isolated rerun at the time. On current HEAD, three serial invocations per journey passed both current SQLite variants (`sqlite-default-slim` / `sqlite-configured-slim` and `sqlite-expired-body-default` / `sqlite-expired-body-configured`): **0 failures in 12 isolated executions**. The only runner advisory was the deprecated probe flag `--do-not-cache-result`; use `--do-not-record-test-run-history` next time. This is non-reproduction under sampled conditions, not proof of stability. [TASK-00150](../../tasks/00150-TASK.md) also records historical empty-header streaming failures; use the retained diagnostics to resolve exact cases before adding them to the probe list.

## Functional timing breakdown

The copied PHPUnit history contains 1,286 Functional cases totaling **20m53s**, consistent with the suite log. `McpSubscriptionJourneyTest` took **10m31s**; its 84 capacity-lock cases alone took **8m14s** (about 39% of Functional). `McpTaskLiveJourneyTest` took **2m35s**. The slowest single case was the MySQL/CodeIgniter Task journey at **12.2s**; the largest cumulative cost is the subscription matrix, not that single case.

## Resolution

The maintainer revised the target from 20 minutes to a **10-minute median for the full hosted gate**. The subscription capacity-lock matrix remains the first optimization target. Measure the target across at least three comparable fresh workflow runs before and after, including setup and every required check. The maintainer clarified that **exact 100% direct Unit coverage of owned production code remains required**; the Integration and Functional suites may cover fewer cases. Record what non-Unit coverage would be lost and why that trade-off is worthwhile. Retain meaningful framework and integration checks and existing release probes. The sampled gate’s planning, preparation and pre-test checks alone took nearly seven minutes, so investigate the full critical path; matrix consolidation alone is not a credible plan for reaching ten minutes. First map what each subscription matrix axis proves (database, timeout mode, transport, table configuration, delayed bootstrap). Profile fixture and lock waits. Prioritize redundant cases, but also consider reducing non-Unit matrix breadth where the lost assurance is explicit and acceptable. Consider concurrent execution only if it isolates databases and preserves reproducible failures. No removal is recommended from this sample. A warm-runtime fresh gate and further suite-context flake trials remain separate evidence if needed; never substitute cache reuse for a fresh trial. This closes the planning decision, not the optimization. Carry the approved scope into the map’s implementation planning handoff; no test removal or gate policy change is approved here.

## Resolution boundary

Choose the timing target and evidence-backed candidates. No tests are deleted and no gate policy changes during mapping.
