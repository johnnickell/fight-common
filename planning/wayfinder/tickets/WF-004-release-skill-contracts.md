# Define the plan, package, certify, and publish skill contracts

**Labels:** `wayfinder:grilling`, `wayfinder:prototype`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** [Define supported release lines and the compatibility contract](WF-002-supported-lines-and-compatibility-contract.md), [Design deterministic release commands and evidence](WF-003-deterministic-release-commands-and-evidence.md)

## Question

What exact input, evidence, approval, stop, idempotency, and handoff contract belongs to each of
`fight-common-plan-release`, `fight-common-package-release`, `fight-common-certify-release`, and
`fight-common-publish-release`?

## Must decide

- permitted actor and read-only, local-mutation, or external-mutation classification;
- deterministic command allowlist and forbidden capabilities for each skill;
- how plans bind version, branch, candidate commit, prior tag, support policy, and evidence;
- handoffs and resumable states across planning, packaging, certification, and publication;
- approval granularity for branch creation, file mutation, merges, tags, pushes, GitHub Releases,
  and Packagist-affecting recovery;
- how a skill proves postconditions instead of treating command completion as success;
- catalog routing and canonical links from `CLAUDE.md` and `AGENTS.md` without duplication.

## Resolution boundary

Resolve interfaces and operator-visible behavior. Cheap prototypes are allowed; `.agents` skills,
commands, workflows, and external mutations are not.

## Resolution

Each release skill owns exactly one release phase. It consumes the prior phase's content-addressed
artifact, revalidates every bound input and claimed postcondition, and emits a durable phase handoff
that identifies the resumable next action. A skill may invoke read-only inspection and only the
deterministic `bin/release` subcommands allowlisted for its phase.

No skill automatically invokes the next skill or crosses into another phase's effect class. Moving
from planning to packaging, packaging to certification, or certification to publication requires an
explicit operator invocation of the next skill. Already-satisfied postconditions remain idempotent
success only after revalidation; a prior command completion or state label is not sufficient proof.

Planning is read-only with one narrow bookkeeping allowance: `fight-common-plan-release` may create
and atomically update immutable plans, run-state projections, machine results, and bounded logs under
`.runs/`. These planning control artifacts do not authorize or perform tracked-file, dependency, Git
ref, branch, or external-system mutations.

The planning inspection may recommend the minimum valid SemVer increment, but it cannot authorize a
release version. The operator must explicitly provide and approve the exact version before an
authoritative plan is created. That version is bound into the plan; changing it, the candidate, a
baseline, or another bound release input creates a new `plan_id` rather than mutating the existing
plan.

The plan binds an immutable `source_commit_oid`, exact prior-tag baseline object IDs, and an immutable
support-policy identity or digest; any source branch or ref is descriptive metadata and must be
rechecked for drift. Packaging produces the immutable `candidate_commit_oid` and carries it in the
package handoff. Certification and publication bind that candidate OID alongside the plan and its
exact baseline and support-policy bindings; no moving branch ref can satisfy those bindings.

Packaging presents a dry-run `packaging_effect_set` and requires one explicit operator approval for
that exact bounded set. The set names every intended local branch, tracked-file, dependency-metadata,
and candidate-artifact effect; an unlisted effect is forbidden. A new effect set or changed bound input
requires a new approval and, where it changes the plan, a new plan identity.

Certification is verification-only with respect to the candidate, Git refs, and external systems. It
may write evidence manifests, machine results, and bounded logs under `.runs/`. A failed or
indeterminate result emits a durable certification stop handoff with the stop state, evidence, and
resumable next action; it cannot be represented as a successful certification or silently discarded.

Publication requires a separate human authorization for each bounded external effect: merge, tag,
push, GitHub Release, or Packagist-affecting action. Every authorization binds the exact `plan_id`,
candidate and baseline object IDs, version, evidence-manifest digest, and compatibility exceptions;
it cannot be reused after any bound input changes or for another effect.

If an external effect may have occurred but its postcondition cannot be verified, publication enters
the persistent `partial_publication` stop state. The skill stops automatic progress, records the
uncertainty and evidence, and resumes only after an operator-directed reconciliation proves the
external state and authorizes the next bounded effect. Blind retries are not recovery.

Every phase handoff uses one minimum machine-readable shape: `plan_id`, `run_id`, phase, status,
all bound object IDs and digests, approvals, evidence references, any stop state, and exactly one
resumable next action. Missing or stale fields make the handoff non-resumable until the owning phase
reissues it.

Repository routing remains single-sourced. `CLAUDE.md` links to the release-coordination map and
canonical ticket contracts; `AGENTS.md` points to `CLAUDE.md` and does not duplicate those links or
their policy. Future `.agents` catalog entries may route to the same canonical documents but may not
copy their contracts.

Successful certification requires a compact immutable manifest composing every required lane from
WF-003: the complete quality gate, locked/lowest/latest dependency lanes, archive installation and
reproducibility, planning/API, compatibility, and Git/ref verification. A hosted check or raw log may
support a lane but cannot replace the composed manifest. Any failed or indeterminate lane prevents a
successful certification handoff.

Each skill exposes a closed capability boundary. Packaging can perform only the approved local effect
set; certification can inspect and verify but cannot edit files or refs; publication can perform only
the separately authorized external effect. An action outside the owning skill's declared capability
set is rejected before execution, even if a lower-level command could technically perform it.
