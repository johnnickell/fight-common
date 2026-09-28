# Verify Resources, Skills and Harness evidence

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Reusable MCP Resources and Skills](../fight-common-mcp-resources-skills-map.md)
**Depends on:** —

## Question

What is actually implemented, underway, planned or released in Common and Agent OS, and which official Resources
and Skills contracts are compatible with Common's already-approved MCP revision?

## Must decide

- Separate live code from planning, earlier assessments and released dependencies.
- Verify Resources/Skills wire behavior, ownership seams and Pi client obligations from primary sources.
- Identify overlapping existing work and conformance limits before proposing a new delivery.

## Resolution boundary

Research may establish facts and recommendations. It cannot approve scope, change the protocol revision, rewrite
Agent OS decisions, select application policy or implement a capability.

## Resolution

[Source-backed evidence and capability/ownership map](../research/WF-044-mcp-resources-skills-evidence.md)
records the 2026-09-27 read-only inspection. Common's merged semantic foundation targets MCP `2026-07-28`;
guarded HTTP exists on the active TASK-00105 branch but remains under review. Tools, streaming, interactions and
OAuth retain their existing records. Resources and Skills are missing, including from inspected Common v1.2.0.

The official stable Skills extension is compatible with the approved revision. It requires list/get, standard
Resource reads, complete static manifests, exact frontmatter and truthful extension advertisement; directory
reading is optional. Server and Harness duties are distinct. Installed Pi has integration seams but no verified
built-in MCP Skills loader. Official conformance includes client no-prefetch and verification scenarios, contrary
to the earlier assessment's incomplete server-only characterization; it still cannot establish all host duties.

Agent OS's accepted trust/snapshot boundaries are preserved. Its SDK-only server-ownership wording is identified
as an explicit downstream reconciliation need, not silently superseded. [WF-045](WF-045-select-the-first-resources-and-skills-delivery.md)
owns the human decision on the proposed delivery. This research closure is not product ratification or a claim
of fresh build, conformance, release or consumer integration evidence.
