# Engineering standards adoption

Adopted 2026-09-14 as the first Fight engineering baseline. This is a reviewed local copy, not a published skill-package release. It requires no private tooling or external checkout. Updates are explicit repository changes; local configuration and documented project exceptions remain visible in the [project profile](../../planning/agents/project-profile.md).

The root [AGENTS.md](../../AGENTS.md) contains the shared policy with project-relative links and a project binding. Its baseline hash below describes the source policy before those link/binding substitutions. Standard document hashes and the installed AGENTS hash describe the exact installed bytes. The original source-policy hash remains historical provenance.

## Standards

- [Architecture](standards/Architecture.md)
- [Delivery](standards/Delivery.md)
- [Frontend](standards/Frontend.md)
- [Governance](standards/Governance.md)
- [HTTP](standards/HTTP.md)
- [Naming](standards/Naming.md)
- [PHP](standards/PHP.md)
- [Planning](standards/Planning.md)
- [Review](standards/Review.md)
- [QA](standards/QA.md) and [report template](QA_REPORT_TEMPLATE.md)
- [Testing](standards/Testing.md)
- [Shared workflow binding](../../planning/agents/shared-workflows.md)

## Baseline identity

| Document | SHA-256 |
|---|---|
| `AGENTS.md baseline` | `026dbae813a7962b104ecf5785429f24bb7dbcacd866a66cea9b9d1e7dae10db` |
| `AGENTS.md installed` | `187248c51307fce8ba3fb3d3df24c15eaceee1c3a208f5d8aac372548984d444` |
| `standards/Architecture.md` | `aa214a9f76dbeb8be6236edebbdab42e3edfe9df929c17b7b420560f25426718` |
| `standards/Delivery.md` | `edea87ae0c622b604bf81bbb457d65fa563105445a98886cdb8f12d17b2010e8` |
| `standards/Frontend.md` | `236d9fae3bd94af3a22b6158953979be04c0c8e388f62d56461579d58e9eb8ec` |
| `standards/Governance.md` | `edb5ea685253c45638930ae9f41a5e0dd12caa1f143dcd9b7ca207013eb350c2` |
| `standards/HTTP.md` | `456f7161f08bdd23063bab934ba9a26fb178f0e1ad35e0d898255dd9702626c9` |
| `standards/Naming.md` | `783c67a53b62f9a1576a3a0c00a6438f1b6c40b0df84f268874689b715e74907` |
| `standards/PHP.md` | `b102071e4939424796e4edc20d0b46373210634189c8f024038214e0e18cf623` |
| `standards/Planning.md` | `3977009d18e64afe804a80fa3f6dcef5ff062ea9694f890717a3b8b84bc22c66` |
| `standards/Review.md` | `cd86ecfa4f1a9b7a73394ff14c97dbf12def59ebb3bdea084f18253be5f21277` |
| `standards/Testing.md` | `f893cb8525d526987f0fe9f98909afff05575c29829eb4fb5814a00d39ac9519` |
| `standards/QA.md` | `715aa0bb9caf81c03f5949cca0c4d2cecad1cc1b82b30c1b55cd38fe111c3258` |
| `QA_REPORT_TEMPLATE.md` | `1e71e01157b297abdfef19fc146fb18cfc36337abb1fcd984f2dd7b7bf8aa7e6` |
| `planning/agents/shared-workflows.md` | `f1522da4c0ba1cd77839135a1fd26253bfa123d0145a9c9e96230d1b5c9ef166` |

## Project scope and unresolved work

PHP/library, planning, tests, and release guidance apply to this repository. Frontend and production deployment references apply only when such work is actually in scope; this baseline does not add those products. LocalDevelopment enrollment is not asserted. Existing public API compatibility and framework constraints are preserved in the profile. Detailed support-authority reconciliation remains separate release work.

The planning and standards adoption are implemented locally. Behavioral trials of the associated authoring workflows are separate from this content adoption.

## Targeted standards refresh — 2026-09-16

Planning now distinguishes unfinished, executable and attention-needed work, with truthful next-action fallbacks. Review now checks omitted states and cross-view contradictions against independent expected behavior. At that checkpoint only those approved clauses were applied; earlier baseline content, project bindings and exceptions remained unchanged. Installed digests above identify the current local documents.

## Scoped compatibility exception — 2026-09-27

For [TASK-00116](../../planning/tasks/00116-TASK.md), the maintainer explicitly retained DBAL 4.4 support before
2.0 and separately approved the four-site constructor diagnostic exception documented in the
[project profile](../../planning/agents/project-profile.md#approved-dbal-schema-compatibility-exception).
This reconciles DBAL 4.5's new `@internal` annotation with the still-supported 4.4 public API. It does not change
shared standard digests, production behavior, dependency constraints, PHPCS policy, or unrelated diagnostics.
The profile owns the exact limits and removal conditions.

## Local-first workflow amendment — 2026-09-28

During TASK-00109's hosted-only review follow-up, John explicitly confirmed that work and independent review
complete using local evidence before land pushes and creates a PR, and requested standards alignment. The
[project profile](../../planning/agents/project-profile.md#local-first-acceptance-and-publication) owns the
approved Fight Common decision and retained post-publication delivery checks. This supersedes the former
public-repository hosted-review exception and clarifies ADR 0008 without claiming any missing run passed.

At that checkpoint the local Testing, Review and Delivery standards, root instructions and contributor guidance
were updated together. The amended installed `AGENTS.md` digest at that checkpoint was
`d701a94d530cd6ca4e5a6e705560b73b785e88b2ddeaa1ec114f47e9ac55641c`.
This was an explicit local amendment, not an automatic baseline synchronization or a change to other repositories,
installed skill files, hosted workflows, branch protections, release certification or deployment authority.

## Bounded adoption — TASK-00115

[TASK-00115](../../planning/tasks/00115-TASK.md) records the maintainer-approved planning frontier, prospective TASK
branch/PR identity, implementation-complete done, independent two-pass accept/revise review, canonical version-3
handoff and mechanical-reconciliation provenance. This replaces the fixed dual-model percentage review contract;
historical reports retain their original identities and meanings. Review and Testing now permit necessary reviewer
gate execution while preserving valid builder receipts and input-equivalence reuse. Implementation resumed after
the DBAL repair and local-first amendment above; both decisions remain intact. Done precedes independent review,
whose acceptance remains required before ordinary authorized land; neither state implies hosted delivery.

Library ownership is explicit. Existing release tests/probes are retained without new packaging/release-process
tests in any suite or harness; the [profile inventory](../../planning/agents/project-profile.md#retained-release-verification)
records actual active checks and legacy fixtures. Product runtime, shipped coding-standard and framework contracts
remain testable. Common's namespaces, Application semantics/container, CronExpression allowance, transaction
contracts, compatibility promises, exact unit coverage, full gate, ordinary dependency resolution, GitFlow and
post-publication hosted delivery checks were preserved at that checkpoint (the later 2026-10-09 correction below
removes their mandatory-pass interpretation). No private installation, new landing workflow, release
certification or publication is implied by this adoption. Installed hashes above identify the reconciled documents;
historical baseline identity remains intact.

## Automatic parent completion amendment — 2026-10-01

John approved closing eligible TICKETs and EPICs in the same operation that completes their children, without a
separate parent assessment, review, QA, confirmation, or skill invocation. The local
[planning conventions](../../planning/CONVENTIONS.md#automatic-parent-completion) own the rule; the planning
standard, project guidance and generator now follow it. Child acceptance, independent implementation review,
publication, merge and archive authority retain their existing meanings. The Planning digest above identifies
this local amendment; no automatic standards synchronization or change to other repositories is implied.

## Clear package release decisions — 2026-10-05

John approved the future release wording in [PR #192](https://github.com/johnnickell/fight-common/pull/192).
[ADR 0028](../../planning/adr/0028-clear-package-release-decisions.md) owns the Fight Common-only decision:
one readable summary and informed ship decision for an unchanged certified candidate, replacing the mandatory
fourteen-row plan, typed/digest-bound approvals and repeated normal publication approvals. Independent review,
meaningful compatibility evidence, the full certifier, human-held signing key and provider/registry verification
remain. Failure, ambiguity, changed inputs or new material risk still stop for resolution. CONTEXT, the profile,
release guide and local Delivery cross-reference now route to that authority; earlier ADRs retain historical text
with scoped precedence notices. This does not claim the rejected 1.3 checklist passed, fix the inherited local
archive limitation, rewrite a published tag, or change other repositories or application deployment authority.
The installed Delivery digest above identifies this reviewed local amendment.

## Hosted CI discretion correction — 2026-10-09

John explicitly corrected the project policy: a hosted green result has never been a prerequisite for human PR
merge in Fight Common. [TASK-00146](../../planning/tasks/00146-TASK.md) aligns the project profile, ADR 0008,
release ADR 0028 and the local Testing standard. The existing Tests and Deploy Docs workflows remain useful
independent signals; missing/failing results are disclosed with their cause, not labeled green. When current local
acceptance and applicable QA are satisfied, land can finish its ready-for-human handoff despite optional CI
failures. An explicitly accepted TASK requirement or real host restriction still binds; a demonstrated product
defect is not waived. Human merge control, local full gate, independent review, QA, exact-commit release
certification and signing remain unchanged. Historical receipts and earlier amendment checkpoints are not
rewritten. This local project correction does not itself claim independent review or publication and is not an
automatic canonical baseline sync or a change to other repositories, GitHub workflows/rulesets or the Agent OS
skill installation. The Testing digest above identifies the current local copy; other shared-standard digests
remain unchanged.

## Shared workflows and independent QA — 2026-10-20

John approved [TASK-00172](../../planning/tasks/00172-TASK.md) to bind installed shared skills to Common-owned
policy and adopt Agent OS's independent QA contract. TASK `done` now requires independent Review acceptance and
satisfied required QA (or justified overall N/A), in addition to implementation acceptance and required verification.
This supersedes TASK-00115's earlier completion rule prospectively; historical statuses, approvals and reports
retain their original meaning and are not mass-migrated.

The local QA standard/template preserve independent ownership, PASS/FAIL/INCOMPLETE/N/A, non-UI applicability,
real subject/evidence identity, repair routing and publication separation. The binding adds provider/target
substitutions, explicit Build authority, stage/planning handoffs and prospective ignored evidence paths.
Shared skill/monitor mechanics remain with the installed provider; ordinary contributors need no private checkout.
No runtime enrollment, global installation, dependency change, evidence relocation or product test is introduced.

The comparison inspected Agent OS revision `04e0e6cfbd832b88354d9ed2e5247ec712a91fb6` and Common baseline
`354b0c2d1b8ec45a5e7b224d3de03b3bbe18d1e8`. QA is a reviewed local adaptation, not automatic synchronization or
certification of provider code. John explicitly excluded requalification of already-used shared workflows:
validate Common-specific documentation and required local checks without new workflow trials or simulations.
Independent Review and applicable local documentation QA remain separate, pending evidence rather than implied
by this adoption record. Exact coverage, compatibility, gate/reuse policy, advisory hosted CI, GitFlow, release
certification/signing and human merge control remain intact. The digests above identify the current local copies.

## No post-merge Board chore — historical 2026-10-27 checkpoint

During [TASK-00166](../../planning/tasks/00166-TASK.md#latest-base-integration-and-permanent-delivery-policy), John
rejected persistent merge-handoff notes and even a renamed post-merge burden, then directed that the docs be fixed.
[The checkpoint conventions](https://github.com/johnnickell/fight-common/blob/9c66408eea782b2a975f893e0a0442e58ac2d536/planning/CONVENTIONS.md#current-delivery-handoffs) recorded the then-current Common rule: ordinary Land
records verified PR URL and historical acceptance/publication, but creates no new delivery marker, tracked current
merge note or Board row. No post-merge Board cleanup request or routine retirement TASK/corrective PR. This local
amendment overrides conflicting provider examples without changing any provider/global installation.

Exact live owned-PR checks before publication and at final handoff still apply with zero legacy markers. Existing
legacy marker verification/authorized retirement preserves historical merge/integration evidence and read-only
routing/Review/QA. Independent Review, required QA/full verification, human-only merge and ownership-qualified Land
cleanup remain intact. No watcher or promise of execution after stopping is introduced. The affected installed
AGENTS, Delivery, Planning, Testing and binding digests above are refreshed; original baseline provenance and
historical decisions/receipts remain. This records approval and implementation, not fresh independent acceptance.

## Complete delivery mechanism removal — central TASK-00542

The October 28 explicit amendment removes the retained legacy delivery marker/table/generator/live-check/
reconciliation mechanism and its routing/intake duties, superseding that part of the historical checkpoint above.
[Publication closeout](../../planning/CONVENTIONS.md#publication-closeout) is current: Land closes accepted TASKs,
eligible parents and applicable current docs during delivery, verifies its exact live own PR/default/base/head
and retires eligible owned local Git/Docker/storage. Preserve historical acceptance/publication/reconciliation
and independent reports, human-only merge and open-PR remote heads. No renamed queue or recurring post-merge
planning chore replaces the removed mechanism. Planning/routing stays offline; uncertainty holds the affected
effect. No local TASK, publication or independent acceptance is created by this central repair. Current installed
digests above identify the amended documents, not fresh upstream provider qualification.
