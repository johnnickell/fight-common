# Independent behavioral QA

Adopted from Agent OS's QA contract; [adoption provenance](../STANDARDS.md) records the inspected baseline.
The sequence is work → local verification → independent Review → independent QA → authorized Land → human merge.
Technical acceptance, QA, TASK completion and publication remain distinct facts.

## Scope and independence

After technical acceptance, challenge changed observable behavior before ordinary landing. No UI is required:
libraries, APIs, CLI/worker behavior, integrations, operational tools and executable instructions can all need QA.
Review assesses requirements, structure, code and tests; QA tries to find failures in use. A green build or thorough
review does not replace QA. The implementer or a material contributor to its submitted acceptance evidence cannot
independently pass the work. Builder self-checks remain diagnostic.

Choose the smallest useful set of intended-use and adversarial scenarios against accepted requirements and likely
or high-impact regressions. Record affected criteria and independently derived expected results, including rejected
operations and forbidden effects. Pure spelling, formatting or historical-record changes with no behavioral effect
can have criterion-specific N/A reasons in the technical handoff or canonical QA report. Small diff size, no UI,
passing tests or unavailable tools do not establish N/A. Consider runnable commands and workflow decisions before
calling documentation non-behavioral. Missing applicability assessment routes to QA, not directly to Land.

Required/requested scenarios cannot silently be dropped to obtain PASS. A narrowed diagnostic run does not prove
whole-TASK QA without applicable prior evidence or an authorized exclusion. Visual N/A is separate from overall QA
N/A. Preserve explicit accepted TASK limits: TASK-00172 adopts already-used workflows without requalifying them;
its QA checks Common-specific documentation/binding consistency, not simulated workflows or live multi-agent trials.
This scoped exclusion does not waive QA of later changed library behavior or novel workflow behavior.

## Authority and subject

QA may read source, run checks and owned isolated services, exercise writes against disposable data and write
ignored evidence. It does not repair source/tests, change requirements or planning status, rewrite technical
reviews, commit, upload artifacts, post PR comments, push, approve or merge. Missing disposable local settings,
test credentials and TLS material for owned services may be created within QA scope in ignored storage; use
client-local trust, never change host trust stores. External effects, shared/production data or credentials,
external-service credentials, tool installation and publication require separate authority.

Resolve the repository, TASK/requirements revision, branch, full baseline and implementation OIDs, working-tree
state, canonical [Review](Review.md) report/digest and any [reconciliation bridge](Delivery.md#reconciling-reviewed-revisions).
For PR intake inspect its actual base/head and resolve the TASK. Unknown identity or missing technical acceptance
means INCOMPLETE; a confirmed defect may still be reported as FAIL, never a diagnostic independent PASS.

For executable scenarios prove the process uses that source: launch command, checkout/mount or image provenance
and relevant build output. A URL, appearance or health check alone is insufficient. Record dependency/runtime
context, owned services/ports and disposable data. Preserve dirty user work and other TASKs' services. Use target
wrappers and existing qualified tools; a missing prerequisite holds the relevant scenario. Recover a removed
runtime at the exact revision in an owned isolated environment or state what is missing. Never rewind the user's
checkout for baseline evidence. Recheck source/runtime and any PR head at completion; changed or unverifiable
subjects cannot receive current PASS without fresh evidence or a proven behavior-preserving bridge.

## Exercises and evidence

Before execution, name the plausible failure, action/input, expected observable result and its independent contract
source. Do not derive expectations by copying implementation output or repeating its algorithm. Challenge relevant
boundaries: empty/malformed/oversized inputs, wrong actor or owner, duplicates/retries/reordering, dependency failure,
partial effects, cancellation, persistence/restart, transitions, concurrency and accessibility where affected.
Do not exercise every category regardless of scope or seed implementation defects to demonstrate QA failure.

| Surface | Useful exercise |
|---|---|
| Library / Domain / worker | Invoke the real public contract through an existing entry point or disposable driver; check values, errors, state and effects. No artificial HTTP endpoint or production wrapper solely for QA. |
| API / integration | Real requests to an identified isolated runtime; check status, headers, response and state/forbidden effects with disposable identities. No persistent Common HTTP runtime is presumed. |
| CLI / TUI | Actual commands, exit codes and effects; a real PTY for interactive input, resize, cancellation and exit. Redirected output does not prove layout. |
| Browser | Actual navigation, input/submission, keyboard/focus and affected responsive/loading/error/permission states, with related request/state evidence. |
| Tooling / instructions | Safely run affected owning commands; inspect changed entry points and references. Where required, trace concrete requests through changed decisions. Label walkthroughs and their limits; they are not live agent/tool proof. |

For planning-path changes, audit intended reference targets across affected live/archived records, Wayfinder notes,
templates and generated views, including Markdown anchors and plain/code-span artifact paths. A relocation requires
its ownership/digest manifest and complete affected-reference audit, not just the planning validator. Classify
historical paths and missing evidence honestly. This adoption relocates no evidence and authorizes no repairs during QA.

Bound time, volume, concurrency and data effects. Use supported isolated substitutes only for what they prove;
required real integration remains INCOMPLETE when unavailable. Existing tests can help set up or reproduce a case;
merely rerunning the build is not the entire investigation. Stop on unexpected external effects.

When needed, retain disposable probe scripts under the owned QA run. Use real public contracts and Common's runtime,
not a rewrite of business logic or a new framework. Record sanitized commands, inputs/fixture sources, seed where
randomized, expected assertions, outputs, exit status, state evidence and script digests. Do not promote probes into
the product suite during QA; confirmed bugs go to Work for regression-first repair. Preserve Common's prohibition
on new packaging/release-process tests and separate release-certification authority.

## Visual evidence

For affected UI/TUI changes use comparable actual Before and reviewed After captures with scenario, source OIDs,
viewport/terminal dimensions, data, theme/zoom and timestamps. Wait for the intended state; retain genuine failure
captures. Inspect every final sanitized image for behavior, readability, clipping and private data, then record its
SHA-256. Use an isolated baseline or verified saved evidence; a new screen may say "Not present before" with its
source basis, never an invented Before image. Screenshots alone cannot prove behavior.

QA invocation, including delegation by Build, covers creating, exercising, capturing and closing dedicated QA
terminal windows and writing task-owned evidence without per-image confirmation. It does not cover personal
sessions, whole-desktop capture, paid provider calls, software installation, OS permission changes or publication.
Use existing permitted capture tools and verify the exact owned window before each capture. Shared Pi sessions
use their provider's terminal-capture procedure when applicable; missing tools, bridge or OS access remain named
prerequisites, not permission to bypass host enforcement or fabricate images.

Unavailable required visuals mean INCOMPLETE. Retain a sanitized real transcript/recording when available and state
what it cannot prove; a transcript is not layout evidence. Non-visual changes use useful request/output/state or
documentation evidence instead of decorative screenshots of code/logs. Follow [Testing](Testing.md#evidence) for
Common's planning-only exclusion and PR Before/After evidence requirements.

## Dispositions and repair

| Disposition | Meaning and continuation |
|---|---|
| PASS | Independent, technically accepted, known current subject/runtime and every required scenario passed with sufficient evidence; proceed to authorized Land |
| FAIL | At least one confirmed behavioral defect; report unexecuted scenarios and missing prerequisites too; Work repairs, then fresh Review and affected QA |
| INCOMPLETE | No confirmed defect but required evidence, independence, acceptance, runtime identity or prerequisite is missing; resolve it and resume QA |
| N/A | No applicable behavioral QA, with criterion-specific reasons; continue review/landing without claiming behavioral PASS |

FAIL takes precedence over missing evidence; INCOMPLETE takes precedence over PASS. Failed startup is not
automatically a product defect. Distinguish environment failures from violations of accepted behavior.

Each finding has a stable `QA-01`-style ID, severity/impact, TASK criterion, exact subject, steps, expected/actual
results, evidence and disposition. Preserve IDs across reruns. Work revalidates confirmed defects, adds a failing
regression where technically possible and repairs within scope. Missing evidence alone does not authorize edits;
out-of-scope findings need human scope decisions. Material repairs require renewed technical acceptance and affected
QA; unaffected evidence needs an explicit applicability bridge. Review and QA repairs share Build's recorded cycle
allowance; QA failure never resets it.

A QA blocker prevents accepted landing. A demonstrated technical defect invalidates applicable acceptance, but QA
does not rewrite the technical report. New supporting QA evidence alone does not invalidate acceptance. Never use
an older PASS to override a current failure. `done` follows required Review/QA under [Delivery](Delivery.md).

## Canonical report

Resolve the base through Git common-directory and registered-worktree metadata as for Review. The canonical
report is `<base-worktree>/.runs/qa/<TASK-ID>/qa.md`. Verify ignored owned paths; reject symlinked output that
escapes them. Unknown/unwritable base means an incomplete handoff, not a second canonical location. Coordinate one
canonical writer; do not overwrite another active run.

Allocate a unique `.runs/qa/<TASK-ID>/runs/<run-id>/` with report and artifacts. Before runtime work preserve any
prior canonical report in immutable history and atomically publish an initial INCOMPLETE report, so interruption
cannot leave an older PASS current. Write a temporary regular file in the canonical directory, rename and read back.
On completion retain the run report, replace the canonical report atomically and verify read-back. Publication
failure makes the handoff incomplete even if scenarios passed. Only the canonical report selects the current result.

Use the [QA report template](../QA_REPORT_TEMPLATE.md), recording scope/applicability, independence, exact subject,
review identity, scenario totals/results, omissions, findings, evidence/digests, reuse, limitations and next action.
Chat points to the durable report; it is not a substitute for it. Keep links usable from both canonical and retained
reports; private absolute paths are allowed in ignored local evidence, never as public PR evidence.

## Publication and retention

QA prepares local evidence. Authorized Land owns uploads and PR updates; local PASS and pending publication can
coexist. Verify required artifacts are accessible to the intended PR audience before delivery completion. Local,
private or expired links are not published evidence. Missing upload support leaves an exact publication handoff,
not fabricated URLs or screenshot commits to product source.

Retain reports, probes and captures outside disposable worktrees. Stop/clean only owned resources within authority;
preserve useful failures for repair and evidence through landing. Mechanical changes may reuse QA only with a
proven behavior/runtime/dependency bridge, not merely because the diff is Markdown or the new OID looks related.
