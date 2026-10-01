---
id: TICKET-00030
epic: EPIC-00007
title: Compose Resources, Skills and Tools Through One Guarded Endpoint
status: done
---

# Compose Resources, Skills and Tools Through One Guarded Endpoint

## Problem statement

Correct isolated Resource and Skill handlers do not prove that consumers can compose them safely alongside Tools.
Discovery can conflict, Tool serialization can lose Resource links, direct reads can bypass filtering, and one
oversized body or encoded response can exhaust processing despite invocation-count limits. Common needs a real
package-owned combined endpoint journey, not a second endpoint or an assertion that semantic tests prove transport.

## Solution and boundaries

Compose the real Common Tools, Resources and structured Skills capabilities through the existing guarded MCP
`2026-07-28` endpoint. Consumers continue to supply routes, authentication, current availability decisions and
providers. This requirement owns cross-capability integration and its evidence, not replacement implementations
of those capabilities or a deferred testing phase for otherwise unverified changes.

- `server/discover` truthfully advertises Tools, Resources and the Skills extension together. Preserve one owner
  per method, shared Resource ownership across document/Skill providers and explicit registration. Confirm
  distinct extension IDs coexist and contradictory settings, duplicate methods and missing prerequisites fail.
- A package-owned read-only Tool returns a safe summary and standard `resource_link` to an exact registered
  document revision. The link survives Tool serialization; the client reads it through the same endpoint, where
  current Resource availability is checked independently of Tool availability and list membership.
- The client discovers a complete structured Skill entry without file prefetch, gets an entry directly when
  needed, reads only `SKILL.md`, then retrieves a selected nested reference/template/script/asset. Exact sizes,
  hashes and revision identity agree throughout; unrelated content is not delivered, rendered or executed.
- Preserve consumer authentication composition and existing Origin, invocation-guard, request metadata and
  header/body-mirror rejection behavior. Resource reads use the specified URI mirror; Skills methods do not gain
  an invented `Mcp-Name` requirement. Rejected requests never reach protected content retrieval.
- Add the bounded request-body/decode safeguard identified by research to the shared endpoint, coordinating with
  its owner rather than copying it. Check finite request bounds while reading, before unbounded buffering/decoding.
  Retain existing validation order as applicable and safe transport/protocol rejection. Invocation-count controls
  are not a substitute for byte/depth bounds.
- Qualify sensible finite defaults and optional validated overrides across shared request processing and the
  capability-owned metadata/raw/encoded response limits. An allowed revision and its files must fit declared
  serving budgets after JSON/Base64 expansion. Invalid configurations fail safely; no truncated success or eager
  allocation of the entire maximum per request is acceptable.
- Demonstrate conservative private cache behavior, current injected authorization, safe errors and redacted
  diagnostics in the composed endpoint, including changes in authorization between discovery/get/read.

Exact interfaces, limit values and implementation slicing remain later design. Routine tuning, retry and
stale-cursor recovery require no extra human approval gates. This TICKET preserves the existing MCP protocol,
Tools invocation, authentication and shared endpoint contracts rather than broadening unrelated delivery scope.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer composes all three capabilities | N/A; composition is not a domain command | `server/discover` | N/A; no domain mutation | Return truthful combined capabilities; reject conflicting registration before serving. |
| Client finds a document through a read-only Tool, then reads its link | N/A; fixture is read-only | Existing Tool/query mechanics, then `resources/read` | N/A; reads do not emit domain events | Return a bounded summary/link, then exact authorized document content; no planning mutation. |
| Client discovers a Skill and reads selected revision files | N/A; read-only | `skills/list`, optional `skills/get`, selected `resources/read` | N/A; no domain mutation | Preserve manifest/byte consistency without bulk retrieval, rendering or execution. |
| Consumer changes availability between requests | N/A; policy change is outside Common | Current injected availability on each protected request | N/A; no Common permission event | Deny subsequent protected content even with a saved link/manifest; Tool allowance is not document allowance. |
| Invalid headers, disallowed Origin, guard denial or failed authentication reaches the route | N/A; no business dispatch | Only validation/guard/authentication work appropriate to the rejection point | N/A; no domain mutation | Reject before content retrieval; retain established transport/protocol outcomes. |
| Oversized input or unsafe override is supplied | N/A; no business dispatch | Bounded input/configuration validation | N/A; no domain mutation | Reject before unbounded work; never truncate verified output into a successful response. |

The fixture query uses the existing Tool and query-bus integration, not a new Common planning-search API. Common
owns no new business commands, events, persistence schema or Workflow transitions here. Diagnostics, invocation
accounting and optional caches are technical effects. Actual planning Apply and mutation authorization stay in the
consumer's existing use cases; existing mutation Tools must remain compatible but are not reimplemented.

## Validation and permissions

Inject test consumer decisions and vary them across requests to prove enforcement without defining Agent OS roles,
permissions, catalog visibility rules or principal types. Test unavailable Resources and whole Skills through both
discovery and saved/direct URIs. Do not treat successful authentication, Tool availability, a Resource link, cursor,
digest or freshness hint as content authorization.

Prove HTTP method/protocol/Resource URI mirror checks and declared custom mirrors retain the existing contract.
Validate bounds at the relevant input/output boundary and preserve safe errors without leaking content, paths or
credentials. Generic unexpected failures produce sanitized diagnostics, not a successful empty response. Resource/
Skill protocol errors remain distinct from selected-Tool `isError` results. Use the already approved authentication
composition for the package fixture; OAuth completion is not required merely to prove authenticated content reads.

## Dependencies and sequencing

- [TICKET-00023](00023-TICKET.md), including TASK-00104 and merged TASK-00105, owns the existing foundation.
  The original planning worktree predated the HTTP merge; publication preparation fast-forwarded to `52ff756`.
  Implementation consumes the reviewed current source, not a local reimplementation. Additive bounded request reading is this TICKET's integration requirement;
  do not silently reopen or expand the completed HTTP TASK.
- [TICKET-00028](00028-TICKET.md) owns Resources and capability-specific bounds, errors, authorization and evidence.
  [TICKET-00029](00029-TICKET.md) owns Skills, manifests, narrow extension-ID composition and prerequisite checks.
  They can establish focused endpoint behavior without waiting on the combined Tools journey. Their own tests,
  documentation, compatibility classification and full gates are not postponed here.
- [TICKET-00024](00024-TICKET.md) and its [TASK-00106](../tasks/00106-TASK.md)/
  [TASK-00107](../tasks/00107-TASK.md) supply real Tool registration/invocation and semantic output. Coordinate
  standard Resource-link support with that owner before freezing the integration, without creating a parallel
  dispatcher or silently changing existing TASK scope. A hand-coded fake Tool protocol handler is not final
  coexistence evidence; focused fixture work may proceed before the real Tools dependency is ready.
- No blanket dependency on [TICKET-00025](00025-TICKET.md) streaming/cancellation,
  [TICKET-00026](00026-TICKET.md) protected interactions or [TICKET-00027](00027-TICKET.md) OAuth completion.
  Preserve those interfaces and acceptance boundaries without absorbing or waiving their unfinished work.
- The real Agent OS/Pi journey and Access Control adoption are separately owned downstream acceptance, not blockers
  for Common's package fixtures or completion. No new cross-repository write authorization is implied.

## Acceptance and evidence

1. Package-owned functional/integration evidence runs the real Common guarded handler with Tools, document
   Resources and structured Skills, exercising combined discovery, Tool-to-Resource-link reads and selective Skill
   reads. Include nonempty catalogs, multiple pages, nested files and binary content; empty-only discovery is not
   proof of the journey. Verify no domain mutation, rendering or execution occurs.
2. Adversarial endpoint evidence covers changed injected availability, denied direct/historical URIs, independent
   Tool/Resource decisions, invalid mirrors, Origin/guard/authentication rejection, conflicting composition,
   request/decode bounds, encoded-response expansion, invalid overrides, cache separation and sanitized failures.
   Rejection evidence proves content providers were not opened when guards disallowed retrieval.
3. Run applicable pinned official Resources/Skills conformance checks against the composed endpoint, retaining
   source/version, configuration and per-scenario results. Reuse valid capability-owned evidence where appropriate;
   do not substitute it for combined HTTP evidence. Record failures, unavailable/skipped checks and genuinely
   not-applicable directory scenarios separately. Supplement official coverage gaps with owned behavioral tests;
   skipped required behavior and undocumented gaps cannot be described as success.
4. Supply compatibility evidence for any shared registry/HTTP additions and preserve existing Tool/endpoint
   consumers. Each new API/behavior is classified in the compatibility manifest; signature preservation alone
   does not establish behavioral compatibility.
5. Document one coherent consumer composition and walkthrough, its injected authorization boundaries, finite
   limits/overrides, lazy structured files, cache/error behavior, deferred features and known conformance limits.
   Capability-owned documentation remains with TICKETs 00028/00029; this record connects it rather than duplicates it.
6. Run the full canonical `./bin/build` gate with exact owned-production coverage for implementation, retain the
   applicable conformance evidence and obtain ordinary independent acceptance. This TICKET owns the integrated
   EPIC evidence, not release certification or a waiver for earlier changes.

A fixture client shows server interoperability and selective retrieval, not actual Pi no-prefetch enforcement,
origin identity, host integrity rejection, session pinning, cache safety or production catalog persistence. Those
claims require the downstream client scenarios and host-owned evidence. No product gate, conformance or downstream
journey has been executed by authoring this planning record.

## Exclusions

A second endpoint, rewritten Tools/HTTP/SSE/cancellation/authentication, new framework adapters, production routes,
OAuth-server operation, Agent OS permissions/catalog policy, Pi implementation, real catalog storage, directory RPC,
rendering/execution, Workflow mutations and release/publication/deployment are excluded. Reading templates/scripts
as ordinary nested files is included. No new human gate is introduced for routine interfaces, numeric defaults or
implementation slicing.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00121](../tasks/00121-TASK.md) | Bound MCP Request Reading and Decoding | done |
| [TASK-00122](../tasks/00122-TASK.md) | Deliver the Combined Tools, Resources and Skills Journey | done |
<!-- /planning:children -->

## Decisions and progress

John approved the three-TICKET split after confirming [EPIC-00007](../epics/00007-EPIC.md)'s endpoint coexistence and
package-owned completion boundary. [WF-045](../wayfinder/tickets/WF-045-select-the-first-resources-and-skills-delivery.md)
and the [delivery handoff](../wayfinder/research/WF-045-mcp-resources-skills-delivery-proposal.md) retain supporting
decisions/evidence. [ADR 0010](../adr/0010-behavioral-contract-authority.md) governs compatibility promises;
[ADR 0024](../adr/0024-framework-adapter-support-and-delivery-boundaries.md) preserves explicit composition and
honest support claims, without inventing new framework adapters or booted-starter requirements for this shared
endpoint scope.

John approved two vertical implementation slices: [TASK-00121](../tasks/00121-TASK.md) adds bounded request reading/
decoding to the existing endpoint, and [TASK-00122](../tasks/00122-TASK.md) delivers the combined Tool-link/document
and Skill-entry/selected-file journeys. Each owns its applicable verification, compatibility, documentation and
full gate; earlier capability evidence is not deferred to final integration.

TASK-00121 retains already-completed TASK-00105 as its foundation dependency and can proceed independently of
Resources/Skills/Tools. TASK-00122 depends on TASK-00107, TASK-00120 and TASK-00121. Any required additive Resource-link
output support belongs to TASK-00122 after coordination with the Tool owner; TASK-00107 is not silently expanded.
At decomposition, both new TASKs were unranked; John later assigned orders 11 and 12 in their owning records.
This TICKET's decomposition is complete. That planning checkpoint claimed no implementation or conformance result.

At the 2026-09-30 TASK-00121 builder checkpoint, John selected the main checkout. Bounded actual-byte ingestion,
validated shared byte/parser-depth configuration, safe rejection and the unchanged guarded dispatch ordering are
implemented. Direct boundary tests and an authenticated Slim-route journey passed, together with the full local
gate: 5,019 Unit tests, exact 12,682/12,682 statements, 150 Integration and 50 Functional tests. TASK-00121 owns
configuration, compatibility, diagnostics, upstream-I/O limits and retained evidence. Independent technical
acceptance and applicable QA remain pending; its `done` is the implementation checkpoint, not a reviewer verdict.
TASK-00122's combined Tools/Resources/Skills journey remains future work. This TICKET and EPIC-00007 are not
closed; no release, publication, downstream acceptance or broader conformance result is implied.

At the later TASK-00122 implementation checkpoint, one real guarded endpoint composes the Tool, document
Resources and complete Skills providers. Its query-backed Tool returns a validated standard Resource link; the
same endpoint reads the exact document revision and selected Skill files with independent current decisions.
TASK-00121 supplies bounded request ingress; TASK-00117/00118 supply Resource discovery, reads and budgets;
TASK-00119/00120 supply immutable Skill revisions and complete list/get; TASK-00107 supplies Tool invocation.
TASK-00122 adds combined HTTP evidence, the narrow link API, compatible behavior classification, documentation,
applicable pinned scenario-specific Resources/Skills checks and a new complete local gate. Its TASK record and
`.runs/TASK-00122/receipt.md` retain outcomes and limitations. This was a builder checkpoint only: independent
technical review and behavioral QA were pending at that time, and neither this TICKET nor EPIC-00007 was closed
by the builder's evidence alone.

## Parent acceptance and closeout

The read-only parent assessment at `6bf11b3d5e80b85d0b3a32fe55f5ddd95d33868b` found **TICKET-00030 ready
for bounded closeout**. TASK-00121 and TASK-00122 are its complete implementation split; both are `done` with
independent technical acceptance and applicable independent behavioral QA PASS. Their combined guarded-endpoint
journey accounts for Tool-linked exact Resource reads, complete Skill discovery and selective file reads, current
availability, guarded rejection before content opens, bounded input/output, private caching, safe errors,
compatibility classification and consumer documentation. The TASK-00122 local `./bin/build` passed with 5,020 Unit
tests / 11,645 assertions and exact 12,687/12,687 owned statements, 150 Integration / 1,024 and 51 Functional /
2,122. Its final published head also passed the required hosted Tests and Docs delivery checks; those checks are
not a substitute for local acceptance. See `.runs/reviews/parent-closeout-00006-00030-6bf11b3/assessment.md`,
the canonical child reviews and QA reports, and `.runs/land/TASK-00122/receipt.md` for the evidence and limits.

**TICKET-00030 is done** for this Common package boundary. The pinned composed-server Resources/Skills scenarios
passed within their recorded scope; optional SHOULD warnings, six N/A directory skips and an aggregate Prompts
caching failure for an uncomposed capability remain disclosed, not a blanket conformance badge. This does not
qualify a real Pi client, production catalog/permissions, installed consumer runtime, release or deployment.
TASK-00123 and TASK-00124 are separately approved follow-up chores, not unfinished children of this TICKET.
EPIC-00007 requires its own explicit parent acceptance; this TICKET closeout does not close it automatically.
Publication, PR approval and merge of this administrative update remain separate from the recorded parent outcome.
