# Shared workflow binding

Use this binding when an installed shared skill operates on Fight Common, including every fresh role delegated
by Build. Fight Agent OS supplies skill mechanics; Fight Common owns its requirements, implementation rules,
verification and completion. A provider's TASK ID, checkout or successful receipt is never evidence for Common.
Ordinary contributors can follow the local standards without installing the shared automation.

## Resolve the target and provider

Start from the explicitly selected checkout, otherwise the invocation's Git root. Verify repository, branch,
Git common directory and registered worktrees. Reuse the user's checkout choice; ask before implementation if
none exists. Resolve the provider from the actual installed skill resource supplied by the harness/operator,
not an assumed sibling checkout or home-directory path. Keep skill scripts and provider-relative references
with that provider. Do not copy skills, create symlinks, install packages or change global settings implicitly.

For routing-only requests, use [lightweight routing](../CONVENTIONS.md#lightweight-work-routing), not full
execution orientation. For execution, read [AGENTS.md](../../AGENTS.md), the [profile](project-profile.md),
[CONTEXT.md](../../CONTEXT.md), selected record/accepted parents and applicable local standards.

Apply these substitutions throughout provider instructions, including nested references:

| Provider concern | Fight Common authority |
|---|---|
| Project plans, Board, status and completion | [Conventions](../CONVENTIONS.md), [Board](../tasks/BOARD.md), selected Common records |
| Architecture, namespaces, dependency and package policy | [Profile](project-profile.md), [Architecture](../../docs/engineering/standards/Architecture.md), accepted Common ADRs |
| Engineering quality and verification | Applicable [local standards](../../docs/engineering/STANDARDS.md), especially [Testing](../../docs/engineering/standards/Testing.md) |
| Execution, branches, reconciliation, publication and cleanup | [Delivery](../../docs/engineering/standards/Delivery.md) and the profile |
| Review criteria and canonical report | [Review](../../docs/engineering/standards/Review.md); preserve its current catalog and legacy history |
| QA applicability, dispositions and report | [QA](../../docs/engineering/standards/QA.md) and its [template](../../docs/engineering/QA_REPORT_TEMPLATE.md) |
| Gate and runtime commands | Actual Common `./bin/*` and the profile; `./bin/build` is the complete local gate; Land also requires [delivery pre-submit](#delivery-pre-submit-and-final-handoff) |
| PR body | [Common PR template](../../.github/pull_request_template.md) |
| Handoffs and scratch | Sections below; retain existing evidence locations |
| Package release | [Release guide](../../release/README.md), [ADR 0028](../adr/0028-clear-package-release-decisions.md) and the profile |

Common retains exact 100% owned-production statement coverage, ordinary library dependency resolution and
published API/framework promises. Do not import the application's coverage denominator, locked-dependency
workflow, `App` namespace, Compose runtime, browser/frontend gates or deployment machinery. The local
[documentation-only reuse rule](../../docs/engineering/standards/Testing.md#reusing-a-full-gate-result-after-documentation-only-follow-ups)
also applies during shared work/review/land; a provider example calling the full gate repeatedly does not revoke it.
Hosted CI remains advisory unless a specific accepted requirement or actual host rule says otherwise. Preserve
Common's merge-commit GitFlow; shared reconciliation examples are not new rebase or force-push authority.

Build records a reusable private project-context file alongside its ledger: target/provider identities, resolved
absolute instruction paths, selected checkout and canonical base, selected Common records, substitutions,
commands, completion policy and original authority. Pass its path and the owning skill path to every fresh role.
Children verify their target identity and read this binding before applying provider references. Missing resources
or an unresolved conflict hold only the affected action; never fall back to provider plans or weaken Common rules.

## Entry points and authority

Confirm skill availability through installed metadata/bindings. These commands describe supported shared
capabilities, not proof that they are installed in every checkout. Build and Next are explicit-only skills and
may be absent from automatic model-selection lists; resolve their actual installed resources before use.

| Invocation | Responsibility and stopping point |
|---|---|
| `/skill:next` | Recommend Board/Wayfinder/planning work; no mutation or automatic dispatch |
| `/skill:work TASK-NNNNN` | Implement, verify and commit owned work; stop before publication and independent verdicts |
| `/skill:review TASK-NNNNN` | Independently accept/revise; write only owned reports/evidence, not source or planning |
| `/skill:qa TASK-NNNNN` | Independently challenge reviewed behavior; report PASS/FAIL/INCOMPLETE/N/A, no repairs or publication |
| `/skill:land TASK-NNNNN` | Within explicit authority, reconcile, close out accepted/QA-satisfied work, publish the PR and clean proven owned resources; human merges |
| `/skill:build TASK-NNNNN` | Explicit delegation of Engineer → independent Review → QA → Land, including owned commits, non-force push, PR creation/update and applicable evidence uploads |

Build is not `./bin/build`: the former coordinates delivery; the latter only verifies the checkout. Build may
accept a TICKET only when the provider's selection rules identify one unambiguous eligible child. It does not
build every child, approve/merge a PR, release, deploy or continue to another TASK. Individual stage invocations
retain their narrower authority. Merely recommending a command grants none of its effects.

Use the owning provider's Build monitor, model-resolution rules and persistent revision ledger. Five repair
cycles are shared across Review and QA by default; a new session or QA failure does not reset them. Explicit
model choices and existing pins remain authoritative; missing provider/model prerequisites need resolution,
not silent substitution. Fresh role sessions separate context, not OS permissions or credentials. No model
selection or child launch follows from reading this binding.

## Handoffs

End useful completions with `Next:` and a supported command containing the actual target, or the precise missing
human decision/prerequisite. Never output placeholder IDs as runnable commands. Recommendations do not execute.

- New implementation or confirmed repairs: Next may recommend Build by default; an implementation-only request
  selects Work. Preserve an existing checkout, ledger and consumed repair allowance.
- Verified committed implementation: independent Review. Keep the TASK `in-progress` with that handoff explicit.
- Review accept: applicable QA; technical acceptance is not QA PASS.
- Review revise or QA FAIL with confirmed defects: Work, then fresh Review and affected QA.
- Missing evidence or QA INCOMPLETE: its evidence/prerequisite owner, not speculative source edits.
- Current independent acceptance plus satisfied QA or justified overall N/A: authorized Land; otherwise name
  the missing publication authority. Land owns administrative completion and delivery metadata, not new acceptance.
- Successful Land: complete [delivery pre-submit and final handoff](#delivery-pre-submit-and-final-handoff), then
  human review/merge and stop. Later routing must also use `./bin/planning-check --check-delivery`; an offline
  local pass is not live PR evidence.
- Planning: follow [map-wide order](../CONVENTIONS.md#map-wide-planning-order). Use Wayfinder for an available
  decision, Grill for one approved EPIC destination, then To Tickets, To Tasks and finally Next. Required human
  acceptance remains a stopping point. TASK closure rolls eligible parents up in the same operation.

[Delivery](../../docs/engineering/standards/Delivery.md) owns `done`, acceptance-candidate reconciliation and
administrative closeout. Historical `done` records retain their earlier meaning; never infer current review/QA
from a terminal status alone. Explicit hosted-evidence exceptions follow the accepted target requirement and
publication plan rather than creating a circular review-before-publication prerequisite.

## Delivery pre-submit and final handoff

These Common-specific steps apply to every Land invocation and delegated Land agent, including administrative
closeout resumed after acceptance. They supplement the provider's local planning/build checks; they do not change
technical acceptance, QA, optional hosted CI, or publication authority.

1. At Land intake and immediately before any push/PR publication, run `./bin/planning-check --check-delivery` in
   the selected checkout. It checks the Board/Roadmap against authoritative records and live PR state for all
   explicit current handoffs. A previous offline build or earlier successful delivery check cannot substitute.
2. On stale MERGED/CLOSED handoffs, verify the reported PR's exact identity and actual merge/closure evidence.
   Within authorized planning scope, reconcile the owning TASK's current handoff and clear the ended marker;
   preserve its historical acceptance/delivery facts. Closed without merge is not merged or implemented anew.
   Inspect local integration separately; PR merge does not prove the checkout contains it. Refresh with
   `./bin/planning-check --write`, inspect the resulting Board/Roadmap and parent changes, then rerun the live check.
   Missing authority or unverifiable metadata stops publication with the precise owner/evidence handoff. No
   automatic merge, fetch, status inference, historical PR scan or silent repair is authorized by the check.
3. After publishing, verify the owned PR's repository/TASK/base/head identity and OPEN state before recording
   `delivery: awaiting-merge` and its actual URL. Refresh generated views, verify the administrative-only diff and
   applicable local checks, and rerun the live check before any metadata push and after the final metadata changes
   and publication. Save the command, result and observation time in the delivery receipt. A failed or unavailable
   result blocks a successful Land handoff; do not claim freshness from the offline gate. A concurrent merge or
   closure routes back to reconciliation, not a stale request for human merge.
4. Return human review/merge only for the verified open PR. A later human merge can make that snapshot stale:
   subsequent routing/Land must recheck and the planning owner must reconcile it. No pre-submit check can predict
   that future event or update tracked planning after the agent has stopped.

The build remains network-independent for planning. This mandatory delivery boundary uses the existing bounded
read-only GitHub metadata check, not hosted test success, and never runs `--write` as a checking side effect.

## Ignored evidence

Resolve canonical base paths through Git metadata, not the current directory. Keep `.runs` ignored. Use these
locations prospectively; do not relocate legacy evidence or rewrite historical receipt paths to match them.

| Location beneath the canonical base | Owner |
|---|---|
| `.runs/build/<TASK-ID>/` | Build coordination, project context, ledger and stage attempts |
| `.runs/tasks/<TASK-ID>/runs/<unique-run>/` | Task-owned checks, logs, probes and delivery evidence |
| `.runs/reviews/<TASK-ID>/review.md` | Current independent technical review; preserve prior reports |
| `.runs/qa/<TASK-ID>/qa.md` | Current independent QA; preserve unique run reports/captures |
| `.runs/handoffs/`, `.runs/notes/`, `.runs/archive/` | Existing handoffs, notes and explicitly archived evidence |
| `.runs/worktrees/` | Approved Git-operated worktrees, never ordinary directory cleanup |

Task scratch may live in the selected checkout when appropriate; canonical reports stay at the base. Engineer
and coordinator may read independent reports, never publish their verdicts. Preserve useful evidence through
landing. Application/runtime inputs must not depend on `.runs`; no new persistent runtime is introduced here.

## Harness and optional facilities

Harness extension installation and skill discovery are separate operator setup. Use an existing trusted
installation; inspect the installed provider's instructions for setup or recovery instead of embedding machine
paths in this repository. A tested Pi version is evidence, not a version pin or future compatibility guarantee.

Normal native Pi tools and shared workflows need no Jev credentials or enrollment. Optional Jev relevance
screening/source disclosure requires separate operator consent and budget. Its portable rule comes from the
installed provider; targets need no copy. In participating sessions inspect `workflow.skill` and `effect`, not
`phase` alone: Build can report `phase: qa` with `skill: build` and `effect: coordination`. This metadata grants
no task authority, and the harness is not an OS sandbox. Preserve enrollment/accounting and active ledgers.

Do not make the custom split-pane workspace, managed Runner/database integration, HTTP runtime enrollment or
provider qualification a prerequisite for library work. Those are separate optional capabilities. This adoption
requires no upstream workflow trials; its approved verification boundary is recorded in
[TASK-00172](../tasks/00172-TASK.md#accepted-decisions).
