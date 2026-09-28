# Select the first Resources and Skills delivery

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Reusable MCP Resources and Skills](../fight-common-mcp-resources-skills-map.md)
**Depends on:** [WF-044 — Verify Resources, Skills and Harness evidence](WF-044-verify-resources-skills-and-harness-evidence.md)

## Question

Which bounded Common delivery should we approve for authorized Resources and static Skills, and how should it
hand off to Agent OS without duplicating ongoing MCP work or moving consumer policy into Common?

## Choices presented

### 1. Separate destination and ownership reconciliation

**Recommend:** a separate Common Resources-and-Skills EPIC over the existing MCP foundation, while Agent OS owns
its provider/use-case adapters and Pi client. If accepted, explicitly reconcile Agent OS WF-014's older SDK-only
server wording downstream; keep official SDK reuse as a client/dependency choice, not a reason to duplicate the
already-approved Common server stack.

**Consequence:** Tools/HTTP/SSE/OAuth delivery keeps its present finish line. Common owns reusable server
correctness once, but Agent OS must adopt the resulting package and build/qualify the missing client. Adding
these requirements to EPIC-00006 instead would deliberately expand its explicit Tools-only destination.

### 2. Minimal static delivery and bundle visibility

**Recommend:** Resource listing/reading and empty template discovery; static revision-addressed Skills list/get
with complete manifests over those same Resources. Defer directory RPC and server-side template rendering,
not nested paths or ordinary template files. For Agent OS, authorize whole Skills rather than silently redact
their manifests. Separate content variants are warranted only by a genuine audience/content requirement.

**Consequence:** lazy supporting-file access needs no directory service, content stays consistent through an
active snapshot, and first-party loading avoids repeated routine approval prompts. More flexible browsing or
per-file audience variants remain later consumer-driven work, not prerequisites. Common enforces neutral
contracts; this consumer visibility convention does not introduce permission policy into the library.

## Evidence and proposed boundary

- [Verified sources, current implementation and ownership](../research/WF-044-mcp-resources-skills-evidence.md).
- [First delivery proposal](../research/WF-045-mcp-resources-skills-delivery-proposal.md), including defaults versus
  invariants, recovery, adjacent capabilities, dependencies, downstream handoffs, journey and acceptance scenarios.
- [Existing Tools EPIC-00006](../../epics/00006-EPIC.md), whose protocol target and existing work remain unchanged.

## Resolution boundary

This decision may select the product/architectural boundary and approve a later EPIC grill handoff. It does not
create that EPIC, decompose TICKETs/TASKs, approve exact public PHP interfaces or operational constants, implement,
change another checkout, publish, release or deploy. Ordinary limit tuning and automatic recovery are not new
human gates. Agent OS/Access Control integrations must be recorded in their owning repositories under separate
write authorization.

## Resolution

**Decision owner:** John. **Accepted:** 2026-09-27, with the following explicit clarification.

1. Plan a **separate Common Resources-and-Skills EPIC**. Existing Tools/HTTP/streaming/cancellation/authorization
   work retains its current boundary. Agent OS ownership reconciliation remains a separately recorded downstream
   handoff; this decision does not modify its checkout or accepted records.
2. Preserve Agent OS's **structured, multi-file Skill model**: root `SKILL.md` plus nested supporting references,
   templates, scripts, assets and other resources. Each immutable revision has a complete manifest; every file
   remains individually addressable and is retrieved lazily when needed.
3. **Whole bundle means the complete revision and manifest**, not flattened Markdown, an obligatory archive
   download, bulk prefetch or loading every file into model context. Manifest completeness and lazy retrieval
   must both hold.
4. Deferring `resources/directory/read` and server-side template rendering is acceptable. Nested paths and
   ordinary template files remain supported from the first delivery. MCP URI templates are a separate protocol
   feature, not ordinary files under `templates/`; deferring them does not remove those files.
5. Filter access to **whole Skills** using consumer authorization, consistently for discovery, direct lookup and
   individual Resource reads. Do not create per-caller partial manifests or require duplicate content variants
   merely to implement authorization. Create separate variants only when genuinely needed.
6. Serving script files as content does not execute them or grant host execution authority. Existing origin,
   integrity, held-manifest and authorization boundaries remain intact.

The [delivery proposal](../research/WF-045-mcp-resources-skills-delivery-proposal.md) now incorporates these
accepted boundaries and retains implementation details/defaults as proposals. This decision closure did not
itself authorize EPIC creation, TICKET/TASK decomposition or implementation.

## Completed EPIC handoff

John subsequently confirmed the EPIC grill and authorized creation of
[EPIC-00007 — Reusable MCP Resources and Structured Skills](../../epics/00007-EPIC.md). It records the accepted
scope and Common completion through the existing guarded endpoint, including Tools coexistence, injected consumer
authorization, package fixtures, applicable conformance checks with honest gaps, compatibility, documentation and
the full gate. Common does not own Agent OS permissions/catalog policy; real Agent OS/Pi acceptance stays downstream.

Finite defaults and optional validated overrides are accepted. Exact interfaces, numeric values and implementation
slicing remain routine later design, without additional human approval gates. The map is now Closed with that
handoff linked. No TICKETs or TASKs were created; no implementation or runtime verification is claimed.
