# Independent review contract

Review is an independent attempt to disprove the exact TASK's acceptance claims. It reports findings and evidence;
implementation acceptance, independent review, publication, and merge are separate states. This document is the
single current criterion catalog. [Delivery](Delivery.md) owns reconciliation and effect authority.

## Target and independence

Read the TASK, accepted parents/decisions, exclusions, effective diff against an identified base, current Git
status, directly affected consumers, claimed checks, and prior findings. Include staged, unstaged, and untracked
target changes; prefer a clean committed target for delivery. Disclose authorship, direction, repairs, and material
evidence contributions. A material contributor cannot independently accept the work. One independent reviewer
may perform both named **Spec** and **Standards** passes; no fixed model or two-agent requirement applies. Builder
self-checks use these criteria for completeness but never issue independent acceptance.

Review new and meaningfully changed behavior and its interactions, not unrelated debt. A chore explicitly cleaning
named files requires those files to comply; do not hide in-scope debt behind a diff-only reading. Countercheck
claims at the narrowest useful boundary. A green build does not substitute for semantic review.

## Current criterion catalog

Map every TASK acceptance criterion to at least one applicable ID and traceable evidence. Preserve these IDs
across rounds under this catalog; historical reports retain their original catalog and meaning (see below).

| Spec ID | Question to disprove |
|---|---|
| SP-01 | Does the delivered outcome satisfy each accepted use case and stay within scope? |
| SP-02 | Are validation, rejection, and authorization accounted for at all exposed entry paths, including target and ownership checks where applicable? |
| SP-03 | Do messages, queries, events, state changes, response data, and external side effects match the contract? |
| SP-04 | Are failure, compatibility, recovery, and security/secret-safety requirements met where applicable? |
| SP-05 | Do tests, direct checks, and required verification establish every acceptance claim and its limitations? |

| Standards ID | Question to disprove |
|---|---|
| ST-01 | Are Domain knowledge, package/consumer ownership, Application coordination, dependency direction, and injection correctly placed? |
| ST-02 | Do adapters preserve transport boundaries, safe output, mapping, and server authority rather than recreate policy? |
| ST-03 | Do naming, PHP style, documentation, and applicable local conventions comply? |
| ST-04 | Do tests prove meaningful owned behavior and important product integration contracts at appropriate boundaries? |
| ST-05 | Are planning, delivery, verification, warnings, resource hygiene, and authority boundaries accurate? |

For **each** ID record `Pass`, `Fail`, `Unverified`, or justified `N/A`, with exact locations, commands/results,
or other traceable evidence and limitations. Fail requires a demonstrated violation. Unverified means necessary
proof is missing, not that a defect is demonstrated. N/A needs a scope-grounded exclusion, never missing evidence.
Account for every TASK criterion even when one catalog ID covers several requirements.

Apply only relevant standards. Documentation/planning work need not invent runtime routes, permission policy,
or product tests. Follow [Testing](Testing.md) for direct checks, screenshots and justified nonvisual alternatives.
Conversely, silence in a TASK does not waive entry-path validation, permissions or security where exposed behavior
requires them. Inspect the actual boundary; do not invent an exploit or policy merely because planning omitted it.

When behavior selects work, summarizes state, or supplies a fallback, inspect omitted states and empty results.
Check summaries against authoritative records and related views: freshness is not correctness. For planning-tool
changes, inspect direct qualification of empty/completed-only portfolios, undecomposed parents, live and archived
terminal children, mixed child states, information-needed, triage, dependency-blocked and mixed-priority work.
Derive expectations independently of the generator. Do not expand into unrelated tooling or insist on literal prose.

## Findings and verdict

Countercheck each finding against current source and contrary evidence before reporting it. Record:

- Stable finding ID, pass/criterion, TASK criterion, severity (`critical`, `high`, `medium`, `low`), revision and location.
- Violated requirement, expected versus observed behavior, and practical consequence.
- Reproduction or traced evidence and counterevidence considered.
- Bounded correction and verification that would close it; do not apply the repair.
- Disposition: confirmed, resolved, rebutted/disproved, stale, decision needed, or advisory.

Separate demonstrated defects, missing evidence, advisory improvements, and residual risks. Architectural and
instruction defects may use traced evidence without executable reproduction. Incomplete policy becomes a decision
request, not invented implementation. Severity orders repairs; it never makes a mandatory criterion optional.

`accept` requires Pass on every applicable Spec and Standards criterion, every TASK acceptance criterion accounted
for, and no blockers. Any Fail or Unverified requires `revise`. There is no percentage averaging or score override.
Acceptance is bounded by the target and evidence, not a guarantee of defect-free software. Explicit human delivery
decisions remain separate: record exact authority, target, risks and limitations without changing criterion states
or converting revise to accept. Such decisions cannot bypass verification, hosted protections, or separate release
and deployment authorization.

## Prior rounds and evidence

Start with the canonical report below, supplied handoffs, and relevant conversation. Inspect the matching legacy
`.runs/handoffs/<task>/`, referenced `.runs/notes/`, and archive locations in the metadata-resolved base checkout.
Ignored evidence is not copied to a new worktree. Search by verified TASK/PR ownership, repository and target
identity, not modification time. No GitHub review is not proof that no prior review exists. If a known report is
missing, name locations checked and request it; continue useful review but report reconciliation as incomplete.

Preserve finding IDs and dispositions; distinguish previously reported findings, revision-introduced findings,
and previously present but missed findings. Revalidate against the current head, accept checked rebuttals, and
explain a confirmed earlier blind spot. Promote reusable learnings only through [Governance](Governance.md).

Pre-adoption Common reports used six Spec and nine Standards criteria and numeric scores. Preserve their original
IDs, definitions, scores, decisions and snapshots; do not silently relabel an old SP-03, for example, as current
SP-03. Cite the report/catalog alongside historical IDs and explicitly map unresolved findings to current criteria
when reviewing again. Historical acceptance is evidence for its recorded content only, not automatic proof of the
new catalog. The report schema version does not by itself identify which criterion catalog was used.

A changed commit ID alone neither proves a defect nor requires another independent review. Retain acceptance only
with the provenance bridge in [Delivery](Delivery.md#reconciling-reviewed-revisions); semantic changes, uncertain
integration or failed checks require renewed independent review. Never rewrite the old report as if it reviewed
new OIDs. For chronology findings distinguish historical checkpoints from current claims under
[Delivery](Delivery.md#delivery-chronology); a tracked file need not contain its own commit or future PR details.

Verify the builder's complete gate log, actual exit and tested-content mapping. Apply
[Testing's input-equivalence rule](Testing.md#reusing-a-full-gate-result-after-documentation-only-follow-ups) before
calling earlier evidence stale. A verified builder receipt remains usable; do not rerun gates merely because a
review starts or to accumulate receipts. If proof is missing, stale or contradictory, name the precise gap and
request it or run the canonical gate when necessary to establish acceptance. Focused checks remain appropriate.
Report counts, warnings, skips, incomplete runs and limitations. Never treat a partial log as a pass. Apply the
[local-acceptance boundary](Testing.md#local-acceptance-and-hosted-delivery) in public and private repositories
alike: missing hosted CI or a PR does not make a criterion Unverified when local proof and acceptance evidence
suffice. Accepted review routes to authorized `land`; required hosted checks are post-publication delivery
obligations. Do not silently replace required local proof with hosted success.

## Canonical durable handoff

The current report is `<base-worktree>/.runs/reviews/<TASK-ID>/review.md`. Resolve the base using
`git rev-parse --path-format=absolute --git-common-dir` and `git worktree list --porcelain`; confirm the registered
base worktree matches that metadata rather than assuming the working directory is the base. Ambiguous or bare
layouts require a resolved base before writing. Check `.runs` is ignored and verify every report-directory path
component remains within that base without following symlinks. Refuse symlinked directories or report targets.

For a first implementation a missing report is normal. Revision requires the canonical `revise` report with a
matching TASK, branch, base/head and recorded snapshot. Trace intervening changes if the branch moved; request a
fresh report when identity/applicability cannot be established. An accepted implementation routes to the separately
authorized delivery operation, not implementation work solely to move its base.

Retain existing reports/history, including legacy Common handoffs and numbered siblings. Reconcile a legacy report
by identifying its exact target and catalog, linking it and carrying forward checked finding dispositions in the
canonical report; never delete history or claim the old verdict accepted different content. Before replacing an
existing canonical report, retain its exact bytes in an identified immutable history artifact in the ignored TASK
report directory. Do not create a new numbered review sequence. Write the new report to a temporary regular file
in that same directory, atomically rename it over only `review.md`, then read it back. Unwritable/unreadable output
is an incomplete handoff, not an actionable chat-only verdict. Chat only points to the verified durable report.

New reports use this header:

```yaml
---
review_handoff_version: 3
task: TASK-NNNNN
target_branch: feature/task-NNNNN-example
base_commit: <full Git OID>
head_commit: <full Git OID>
target_status: clean
verdict: accept
---
```

`target_status` is `clean` or the complete `git status --porcelain=v1 --untracked-files=all` snapshot, using a YAML
block scalar for multiple lines. For dirty reviews retain each reviewed file's path, state and digest or patch
(including untracked contents); record deletions and staged/unstaged versions so later sessions detect drift.
Identify selected ignored evidence and its content identity even when Git status is clean.

The body names reviewer relationship, current catalog, exact target/base, separate Spec and Standards tables with
all IDs, TASK-criterion-to-evidence mapping, findings (or None), prior reports/dispositions, fresh commands/results,
limitations, risks and verdict. For revise include a copyable bounded builder prompt. Existing version-2 canonical
reports remain readable: verify identity and snapshot, preserve their original catalog, and apply any required
provenance reconciliation. Only canonical `review.md` supplies the current verdict; older siblings cannot override it.

Review does not repair source, change planning status, regenerate tracked views, commit, publish, approve a PR,
merge or clean resources. Verification may run checks but not apply repairs. A review plan lists only ignored
report/evidence artifacts to write. Generic permission to proceed or finish that plan authorizes those artifacts,
not repairs quoted in them. Re-read role and scope on resumption; an explicit implementation request is required
to switch roles. Leaving repairs uncommitted does not make them independent review.
