# Define patch and maintenance-line workflows

**Labels:** `wayfinder:grilling`, `wayfinder:prototype`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Release Coordination](../fight-common-release-coordination-map.md)
**Depends on:** [Define supported release lines and the compatibility contract](WF-002-supported-lines-and-compatibility-contract.md), [Design deterministic release commands and evidence](WF-003-deterministic-release-commands-and-evidence.md)

## Question

How do `fight-common-maintain-release-line` and `fight-common-patch-release-line` safely select,
create, patch, forward-port, support, and retire maintenance branches without a separate hotfix
flow?

## Must decide

- exact boundary between line lifecycle and applying an already-reviewed fix;
- accepted fix identity and proof that implementation review is complete;
- branch naming, oldest-affected-line selection, change classification, and patch eligibility;
- pull-request and certification evidence required on every affected line;
- ordered forward ports, provenance, conflict stops, and semantic-difference handling;
- current-line integration with `main` without allowing an older line to replace newer contents;
- maintenance-branch creation, protection, support transitions, EOL, and read-only preservation;
- security or urgent-fix handling as metadata without bypassing the ordinary safety contract.

## Resolution boundary

Resolve workflows and state transitions. Do not apply a real fix, create maintenance or patch
branches, change branch protection, or publish a patch release.

## Resolution

`fight-common-maintain-release-line` owns maintenance-line lifecycle: creating a
`<major>.<minor>` branch from the signed release commit, validating protection and support
transitions, and preserving an end-of-life branch as read-only history. It does not apply fixes.

`fight-common-patch-release-line` consumes an immutable, already-reviewed change set. The input is
the exact commit OID set plus review provenance: the merged pull request's base and head OIDs,
approval evidence, required-check conclusions, and merge receipt. A branch name or pull request
number alone is never a fix identity. The workflow does not implement or review the fix.

Only supported release lines are eligible. Focused public behavioral evidence is run against every
supported line to establish affected and unaffected status; ancestry alone is insufficient. The
oldest affected supported line is selected first, using its exact current tip OID as the patch base.
The proposed work branch is `patch/<version>-<slug>`. A moved ref, unreleased maintenance content,
or any other base ambiguity stops for human resolution rather than selecting a fallback.

Patch eligibility is classified independently on each affected line. A line must prove that the
change is patch-compatible: it adds no public contract or deprecation, narrows no supported
environment, and introduces no incompatible behavior. A semantic difference may require an adapted
implementation, a higher release class, or human resolution; the workflow never forces one source
commit unchanged across divergent lines.

After the oldest line is reviewed and certified, each newer affected supported line receives one
separate forward-port pull request and certification. Forward ports proceed oldest to newest and
carry source-line, predecessor-PR, and commit provenance. Conflicts, missing provenance, and
semantic differences stop the chain. The current-line result reaches `main` through its own
reviewed and certified forward-port PR based on `main`; an older maintenance branch never replaces
newer contents wholesale.

Every affected-line and forward-port PR binds the exact fix and base OIDs, line, plan, and evidence.
Required evidence includes the public regression fixture, compatibility classification, complete
quality checks, and verified Git and pull-request state. Queued or running hosted checks are not
passes. Each affected line and forward port requires its own approval.

Urgency and security are release-plan metadata, not a second hotfix flow or a gate bypass. An urgent
mode gathers the OIDs, line evidence, checks, approvals, and blockers into one guided action packet
so the operator supplies only the urgency, impact, and reviewed fix. An otherwise incompatible
patch requires one exact compatibility exception bound to its candidate, version, baselines,
findings, and evidence; absent that exception, certification remains blocked.

Support transitions use canonical machine-readable policy and exclusive UTC boundaries. At EOL,
unfinished work stops and the branch remains preserved but read-only unless a separately reviewed
policy change extends support before expiry. Branch creation, protection changes, patch branches,
forward ports, and release publication remain separately authorized effects.
