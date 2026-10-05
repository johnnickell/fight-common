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
- [Testing](standards/Testing.md)

## Baseline identity

| Document | SHA-256 |
|---|---|
| `AGENTS.md baseline` | `026dbae813a7962b104ecf5785429f24bb7dbcacd866a66cea9b9d1e7dae10db` |
| `AGENTS.md installed` | `f2ba692b08a0abc109200c76f5b2041ade64d6606828b06a3ba4e6fd048f5444` |
| `standards/Architecture.md` | `aa214a9f76dbeb8be6236edebbdab42e3edfe9df929c17b7b420560f25426718` |
| `standards/Delivery.md` | `de68e6897dfe28ae8d24bd081b67f6c76945e75db41c4bd6fe1a35779521430d` |
| `standards/Frontend.md` | `236d9fae3bd94af3a22b6158953979be04c0c8e388f62d56461579d58e9eb8ec` |
| `standards/Governance.md` | `54f36212c604615d7c6e2691de41b3b2c843c4f53e59800c9e330eeedf3d3ed5` |
| `standards/HTTP.md` | `456f7161f08bdd23063bab934ba9a26fb178f0e1ad35e0d898255dd9702626c9` |
| `standards/Naming.md` | `783c67a53b62f9a1576a3a0c00a6438f1b6c40b0df84f268874689b715e74907` |
| `standards/PHP.md` | `b102071e4939424796e4edc20d0b46373210634189c8f024038214e0e18cf623` |
| `standards/Planning.md` | `f1536f87a8041614a8429f8a6fc0b349cdb1ae397b9cd98c11e6abf67f7e37e7` |
| `standards/Review.md` | `0bec2986099fafa2866fa07a5fd4e319e172d2cc354671e30b62858f9e6eb402` |
| `standards/Testing.md` | `77639b06e8bfc306345963826382e45af603bb176308e494917d59ea4eb10e9b` |

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
post-publication hosted delivery checks are preserved. No private installation, new landing workflow, release
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
