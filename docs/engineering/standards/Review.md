# Review contract

Assess the exact TASK, change, revision, and declared scope against two independent axes. Review new code and meaningfully changed methods/components for a feature/bug; include easy nearby cleanup. Report larger unrelated debt as advisory. A chore explicitly cleaning named classes/files requires those complete files to comply. Do not hide in-scope debt behind a diff-only interpretation.

## Stable criterion catalog

Before examining findings, derive acceptance checks from the TASK and attach them to the Spec criteria below. Keep these cohesive criteria as the scoring denominator; do not pad it with easy subchecks. Determine applicability explicitly and retain IDs through follow-ups.

| ID | Spec criterion | Required evidence |
|---|---|---|
| SP-01 | Intended use cases and acceptance are complete | Actor/trigger/outcome mapped to behavior, including relevant UI/API paths |
| SP-02 | Validation and input rejection are accounted for | Boundary checks, failure responses, and tests or justified exclusions |
| SP-03 | Permissions and access rejection are accounted for | Authoritative enforcement, consumer ownership, or a justified exclusion |
| SP-04 | Commands, queries, events, and side effects match the contract | Trace of state changes, response data, dispatch and external effects |
| SP-05 | Failure, compatibility, and recovery behavior satisfy requirements | Absence/error behavior, existing data/API compatibility, relevant rollback/retry semantics |
| SP-06 | Completion evidence proves the outcome | Acceptance-linked tests, published Before/After evidence or justified alternative, accessible captures/links, actual final gate state |

| ID | Standards criterion | Reference |
|---|---|---|
| ST-01 | Domain knowledge and responsibilities are well placed | Architecture; CONTEXT.md |
| ST-02 | Dependencies and use-case coordination follow project architecture | Architecture; dependency rules; DI and contracts |
| ST-03 | General naming conventions are followed | Naming |
| ST-04 | PHP code and documentation style comply | PHP; actual coding standard |
| ST-05 | Adapters and presentation preserve boundaries and safe output | HTTP; project-specific transport contracts |
| ST-06 | Frontend responsibilities, data, state, and shared services comply | Frontend |
| ST-07 | Tests meaningfully verify production behavior | Testing; coverage and collaborator suitability |
| ST-08 | Documentation is created or updated without drift | CONTEXT.md, API docs, planning, runbooks and affected public docs |
| ST-09 | Verification and delivery hygiene satisfy applicable requirements | Testing; Delivery; exact evidence and owned resources |

Only apply relevant standards. Missing runtime behavior is not an automatic failure in a documentation-only TASK. Apply Testing’s planning-only screenshot exclusion without a score deduction; assess SP-06 against the applicable planning verification instead. Explicitly requested screenshots remain required. Conversely, TASK silence about validation or permissions does not waive SP-02/SP-03: inspect the actual boundary and account for the security process. A demonstrated omission fails the criterion. Do not invent a permission policy or claim an exploit merely because the TASK forgot to mention one.

## Scoring

Each criterion is **Pass**, **Fail**, **Unverified**, or **Not applicable**, with evidence/reason. Fail needs a confirmed violation. Unverified means required proof is absent, remains applicable, and prevents completion; it is not a proven code defect. N/A requires a valid exclusion rather than missing evidence.

For each axis independently: `score = 100 × passed / (passed + failed + unverified)`. Display counts and the score; if no criteria apply, report N/A, not 100. Every applicable criterion must pass before merging, regardless of rounding, unless John explicitly authorizes the scoped review-score override below. Multiple findings keep their criterion failed until all confirmed violations are resolved. Severity orders repairs; it does not change the score or make a mandatory rule optional. Advisory suggestions do not deduct points.

A perfect score means complete compliance within this stated scope and evidence. It is not a guarantee of defect-free software. Internal implementation checks use the same criteria as a readiness checklist; formal scoring is the independent review's output.

## Explicit review-score override

John may explicitly authorize landing below 100% on either review axis for a particular PR. First present the current scores, failed/unverified criteria, findings or missing evidence, and practical risks. Bind the authorization to the repository, PR, head/base revisions and accepted exceptions; these may be established by the current conversation rather than requiring John to type hashes. A general request to land, permission to support overrides, or an earlier PR's override is not approval to use one.

Record the authorization, reason if supplied, accepted risks and any agreed follow-up in the ignored review/landing handoff. Preserve actual scores and criterion states; an override does not turn Fail or Unverified into Pass, justify N/A, or imply reviewer approval. Report the outcome as landed with an explicit review-score override. Do not invent follow-up work or a reason on John's behalf.

If head/base content or findings change, reconcile the review and obtain renewed authorization before relying on the override. Reuse authorization already given for the exact reviewed state without asking again. This exception covers the review-score threshold only; it does not waive the full build gate, hosted protections, resource ownership, or separate release/deployment authorization, and does not authorize disabling or bypassing those controls.

## Adversarial findings

Before reporting or scoring a finding, attempt to disprove it against current code. Record:

- Stable finding ID, criterion, severity, current revision and exact location.
- Violated requirement and practical consequence.
- Reproduction or traced evidence; counterevidence considered.
- Feasible correction within scope and verification that would close it.
- Status: confirmed, resolved, rebutted/disproved, stale, decision needed, or advisory.

For chronology findings, apply [Delivery](Delivery.md#delivery-chronology): distinguish explicitly historical checkpoints from claims about current state. Do not deduct points because a pre-publication note lacks future commit/PR details, because a tracked file does not identify its own enclosing commit, or because later publication makes a true historical statement no longer current. Verify current state through TASK metadata, Git, hosted checks and ignored handoffs. A misleading unqualified current-state claim or stale implementation fact can still be a documentation defect; propose the smallest clarification, not a requirement to rewrite history after each push.

Architectural and documentation defects can use traced evidence without executable reproduction. Unproven suspicions remain questions; missing required evidence makes the criterion Unverified where appropriate. Incomplete product policy becomes a decision request with a recommendation, not invented implementation.

Revalidate each finding at the new head. Accept evidence-backed rebuttals after checking them; escalate actual policy disagreements to John. On follow-up, label findings previously reported, introduced by revision, or previously present but missed. Scrutinize new discoveries especially closely. For a confirmed miss, explain the earlier blind spot and propose a reusable learning when warranted. A learning becomes a rule only after John's approval, through standards governance.

## Reviewers and handoff

Use independent Spec and Standards reviewers with **GPT-5.6 Sol, high**. Each gets the exact TASK, revision/scope, relevant standards, and evidence; read-only source access, no fixes or further delegation. The coordinator adjudicates overlaps and contradictory findings against evidence. If that profile is unavailable, report it and ask for an explicit alternative; do not silently use the generic router's weaker fallback.

The review produces two score tables, confirmed findings, verified rebuttals, evidence gaps, advisories, and a copyable implementing-agent prompt. Keep artifacts in ignored run/handoff folders. A reviewer does not modify source or tracked planning records, regenerate tracked views, post external comments, commit, push, merge, or silently begin another build. Review verification may run checks within the declared read-only scope; it does not include applying repairs. Approval to exit Plan Mode or write/finish a handoff retains this boundary. If artifact writes were blocked, resume by saving the review artifact and returning control. Only a separate explicit instruction to implement repairs changes the role; the repair prompt inside a handoff is output, not authorization to execute it.
