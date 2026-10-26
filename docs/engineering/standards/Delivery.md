# Runtime and delivery

Read the current project profile, Git state, runbooks, and owning commands. Treat recorded environments and command examples as leads to verify. Existing project deviations remain explicit until deliberately migrated.

## Worktree and resource ownership

Before implementation, ask John to choose the main checkout or an isolated worktree with extra runtime resources; reuse a choice already made for this TASK. Main checkout still uses the appropriate feature/patch branch. Do not overwrite unrelated dirty work. If the selected checkout cannot safely host the work, explain the conflict and resolve the affected choice.

Use ignored `.runs/worktrees/<slug>/`, `.runs/notes/<date>-<task>/`, `.runs/handoffs/<task>/`, `.runs/reviews/<TASK-ID>/`, and `.runs/archive/`. The metadata-resolved base worktree owns the [canonical review report](Review.md#canonical-durable-handoff) and [QA report](QA.md#canonical-report). Follow the [prospective evidence layout](../../../planning/agents/shared-workflows.md#ignored-evidence) for Build/task runs without moving historical evidence. Keep reusable handoffs/evidence when cleaning environments. Durable requirements and outcomes belong in planning records.

Record TASK, repo, branch/base/PR, checkout path, owned containers/volumes/routes, URLs, creation evidence, cleanup commands, and retention requests before allocating resources. Libraries may need no persistent HTTP runtime. Do not provision one just to satisfy a checklist.

Use the shared LocalDevelopment project's documented enrollment and worktree procedures. Integration wiring, DNS, routes, certificates and shared services belong to that project. The consuming project profile points to the operator contract; skills do not invent NGINX or `/etc/hosts` management. Verify enrollment and actual worktree support. If unavailable, record the gap and continue independent work; obtain the missing runtime decision before claiming UI/API proof. Never treat another project's successful enrollment as evidence for this one.

Keep TASK environments alive through review unless John requests otherwise. Authorized Land must retire eligible
TASK-owned resources under the [bound target and qualified retirement contract](../../../planning/agents/shared-workflows.md#integrated-delivery-and-resource-retirement).
Publication, document synchronization and cleanup remain separate obligations; a hold is incomplete cleanup.
Verified merged cleanup-only needs no metadata correction PR. Preserve active/dirty/shared resources, evidence
and durable data; resume only missing ownership-proven effects with postconditions.

## Git and PR identity

Use merge commits, never squash/rebase merges. Prefer GitFlow:

| Work | Source → destination |
|---|---|
| New TASK feature/unreleased fix | `feature/task-NNNNN-<slug>` from `develop` → `develop` |
| Current-line release | `release/*` from `develop` → `main`, then merge `main` → `develop` |
| Application emergency repair | `hotfix/*` from `main`; complete according to the project's approved hotfix flow |
| Released library repair | `patch/<version>-<slug>` from the oldest affected supported line, with separately reviewed forward ports |

New TASK PR titles use `TASK-NNNNN — <TASK title>`; set and verify the actual title, not just the body. Preserve established branch/PR identities without retrospective renaming. These prospective conventions do not replace release/patch naming.

Libraries do not use hotfix branches. Do not commit directly to protected integration/release branches. Check branch existence and project policy before creating missing GitFlow structure.

The normal sequence is `work` → local verification → independent `review` → independent `qa` → authorized `land` → human merge. Land owns non-force push and PR creation/update after technical acceptance and satisfied required QA; work, review and QA do not publish. An explicit Build invocation delegates those stages through publication under the [shared binding](../../../planning/agents/shared-workflows.md#entry-points-and-authority), not merge, release or deployment. Recommending Build grants no authority.

Find an existing PR at landing intake and immediately before PR creation. Establish unique ownership using repo, branch, TASK and recorded SUBTASK relationship, base, and remote head. A similar title alone is insufficient. Reuse the matching PR, including review repairs. If ownership is ambiguous, stop publication with the candidates and missing fact. A closed/merged PR is not silently reopened; distinguish follow-up work.

PR descriptions explain the final behavior/problem, verification and material limitations, and include the Before/After evidence section required by [Testing](Testing.md). Verify the published evidence, not just local captures. Preserve a history of first failures and later successful gates in evidence. Do not expose private reference identities, local credentials, or private research. Honor hosted branch protection when landing. Follow [Testing](Testing.md#local-acceptance-and-hosted-delivery): work and review use local proof in public and private repositories alike. After publication, land obtains explicitly required hosted delivery evidence from the project profile. If it is pending or unavailable, report delivery incomplete and retain required resources; do not rescind local acceptance solely for missing CI, fabricate a pass or send the builder through an empty repair cycle.

Before ordinary landing or any PR merge, require current-content independent acceptance under [Review](Review.md), with every applicable Spec and Standards criterion passing and no blockers, plus satisfied [QA](QA.md). Land checks canonical QA identity, independence, scenario coverage, artifact digests and any provenance bridge; a newer FAIL cannot be replaced by an older PASS. Missing applicability/evidence routes to QA or its prerequisite owner, confirmed defects to Work then renewed Review and affected QA. Human delivery decisions remain separately recorded authority, never a score override or reviewer acceptance. Local verification must satisfy [Testing](Testing.md#gate-boundaries), including its documented evidence-reuse rule. Technical acceptance and successful hosted delivery are separate checkpoints. Satisfy any declared delivery and host-enforced merge checks before the effect they guard; missing delivery evidence is not a technical-review failure. Release preparation does not waive verification or review. If head/base moved, reconcile evidence as below.

## Reconciling reviewed revisions

A changed commit ID alone neither demonstrates a defect nor requires another independent review. The original
report anchors reviewed content, not a frozen base. Within separately authorized integration scope:

1. Record the accepted report and old/new full base and head OIDs. Establish a clean target and inspect branch
   ancestry, publication and directly affected base changes. Preserve Common's merge-commit GitFlow and published
   history; this rule does not authorize rebasing, force pushes, cleanup, publication or merge.
2. Inspect the old reviewed effective diff and the new-base effective diff, conflict resolutions and affected
   dependency interactions. Regenerate planning views from preserved authoritative records; do not silently pick
   a side. An identical patch is not proof that changed dependencies preserve behavior.
3. Save a provenance bridge in the ignored TASK handoff: report identity, old/new OIDs, effective differences,
   every conflict resolution, dependency-interaction evidence, and required fresh focused/full-gate results.
   Apply Testing's input-equivalence rule only where its conditions hold. Verify the final status and content.
4. Retain independent acceptance only when this proves mechanical reconciliation with unchanged accepted behavior.
   Semantic changes (including requirements or authority prose), uncertain integration, unexpected scope changes,
   or failed checks require renewed independent review. Preserve the original report unchanged; link the bridge
   rather than pretending the old reviewer examined new OIDs. Stop delivery when proof is incomplete.

Independent planning-view conflicts can be mechanical after regeneration and inspection; they are not a reason
alone for another review. This is evidence reconciliation, not adoption of a different landing workflow.

## Delivery chronology

Mark TASK `done` only after implementation acceptance, required verification, independent Review acceptance and
satisfied required QA (or justified overall N/A). Keep implementation awaiting Review/QA `in-progress` with its
current handoff explicit; a green build alone does not prove acceptance. Review/QA publish independent reports,
not planning changes. Authorized Land normally records closeout. Record publication, merge, release and deployment
separately; done grants none of those authorities. Preserve earlier completion records under their original policy.

Tracked completion notes describe implementation and verification at an explicit checkpoint, such as “At the pre-publication checkpoint…”. Statements about no commit, PR, merge or deployment must be scoped to that checkpoint when they are historical. Preserve true historical outcomes; correct unqualified claims that misleadingly describe current state. Keep durable implementation facts current rather than treating all stale prose as historical.

Record a verified PR URL in TASK metadata once it exists; never predict a PR number or require publication details before publication. Include an existing PR link before the final gate where possible. When first publication requires a follow-up metadata/view change, verify and deliver that change through normal project gates. This does not require another tracked update to describe the metadata commit itself.

After commit/push/PR creation, record the actual resulting commit, PR, publication outcome and verification references in ignored run/handoff artifacts. Use Git to verify publication. Land records any required hosted delivery results for the published head separately from the accepted local candidate. Builders report hosted CI as unchecked unless explicitly tasked with checking it. A pre-commit gate identifies the tested tree or content snapshot; after committing, link that evidence to the resulting commit after confirming the tested content was preserved, or document the input equivalence and targeted checks required by Testing’s documentation-only evidence-reuse rule. A tracked file must never be required to contain its own enclosing commit hash or future CI outcome. Do not create recursive bookkeeping commits merely to restate each new head. Real content changes still require applicable verification and review.

### Acceptance and administrative closeout

Land records `done` after candidate A has independent acceptance and satisfied required QA, preserving their
identities separately from administrative closeout B. B may update only truthful completion/review/QA/PR metadata
and generated planning views; verify the administrative-only difference and run applicable current checks under
[Testing](Testing.md). Close eligible parents in the same operation. Instructions, requirements, code or other
behavioral changes are not administrative closeout and need fresh applicable acceptance evidence.

Any explicitly required final-head delivery checks cover B, including closeout. Record results in ignored evidence
and the PR, not another recursive status commit. Advisory hosted failures remain disclosed, not mandatory passes.
Pending required delivery proof retains resources without toggling `done`. Administrative closeout alone does not
require another independent review or rewrite of the canonical verdict. A demonstrated defect or unproven
integration requires repair, renewed technical acceptance and affected QA before delivery.

If a future accepted requirement needs hosted evidence for initial acceptance, identify its exact scope and
publication authority first. Only a canonical review showing that this is the sole missing acceptance proof can
route to authorized draft publication; retain incomplete TASK status and resources, then obtain independent
acceptance and required QA before closeout. A literal-final-head acceptance requirement that conflicts with a
completion commit needs the requirement owner's explicit amendment, not a silent waiver or endless status commits.
This exception creates no default hosted gate and grants no publication by itself.

## Signed library releases

Require John's explicit signoff on the exact version before release mutations. Fight Common's [ADR 0028](../../../planning/adr/0028-clear-package-release-decisions.md) owns the approved local release flow: after its readable summary and exact-commit certification, one informed ship decision covers the remaining normal release operation for the unchanged candidate. This replaces typed/digest-bound and per-effect release approvals, not independent PR review, required checks or host protections. Stop for changed inputs, failures, uncertainty or new material risk; it does not authorize unrelated releases, branch deletion or application deployment. Other projects follow their own approved release policy. Use `vX.Y.Z` tags. Current-line tags identify the exact release merge commit on main; maintenance tags identify the release commit on that maintenance line. Do not move/recreate a published tag.

The release workflow owns release branch integration, the human signing handoff, publication verification, merge-back into develop, and completed release-branch cleanup. Provide a concrete copyable script using the project's actual tooling that prompts interactively for signing or sudo. Never collect a passphrase/password in chat. Prepare everything possible before asking for the final human operation. Resume from verified state after the human runs it; instructions alone are not evidence that a tag was published.

For libraries ignoring the lockfile, ordinary dependency preparation uses `composer update`. A release resolver's already selected lowest/latest lane must remain intact during certification; follow its documented preserving mode rather than running a broad update over it. Product gates and release certification are separate evidence.

Library support ([canonical line data and UTC boundaries](../../../SUPPORTED_VERSIONS.md)):

| Line | Support |
|---|---|
| Current minor | Bug and security fixes |
| Each superseded minor with an unexpired window | Security, data-loss and critical compatibility fixes for six months after its immediate successor minor is published, even if a further minor ships |
| Latest minor of previous major | Same limited six-month window after the new major |
| Expired minor | End of life, retained read-only |

Only the latest patch of a supported minor is supported. Maintenance branches use `major.minor`, such as `1.1`. Derive lifecycle dates from actual releases and the support authority. Create a needed maintenance branch at the exact signed release commit, update support/protection records, and retire support by preserving the branch read-only. Branch existence alone does not prove current support. Do not merge an old maintenance line wholesale over newer main content.

## Production deployments

Production operations require the exact target and approved project procedure. Inspect artifact identity, timestamped release directories, configuration/secrets, backup verification, migration ordering, atomic promotion where supported, health checks, retention, and recovery commands. Prefer existing proven operator tooling over new orchestration.

Prepare a concrete deployment plan/script with expected results and abort/recovery points. Resolve whether John executes it or authorizes agent execution; do not infer that choice from asking to design a deployment. A deployment request with an already explicit execution scope need not be reconfirmed.

Before migration/promotion, verify the selected artifact and required backup/recovery readiness. After promotion, verify actual health and record outcome. A code rollback does not automatically reverse a destructive database migration. Follow the approved recovery plan; pause on an unplanned destructive restore. Keep operational qualification outside ordinary feature CI/builds, while still requiring relevant checks for the actual deployment.
