---
id: TICKET-00028
epic: EPIC-00007
title: Discover and Read Authorized Resources
status: ready-for-agent
---

# Discover and Read Authorized Resources

## Problem statement

Consumers need a reusable way to expose registered documents and other content as MCP Resources without
reimplementing URI lookup, content representation, pagination and safe availability filtering. Discovery alone
cannot protect content: a client can request a saved or guessed URI directly, and a previously allowed caller
may no longer have access. Common must enforce injected consumer decisions without defining permission policy.

## Solution and boundaries

Provide the Resources capability for MCP `2026-07-28`, composed with the existing semantic responder and guarded
endpoint. Consumers explicitly register content providers and supply current request-scoped availability decisions.

- `resources/list` returns authorized descriptors with deterministic ordering and bounded opaque-cursor pagination.
  Filter before pagination; metadata enumeration must not require opening every file or loading the entire corpus.
- `resources/read` resolves the exact registered URI and checks current availability before exposing content.
  Support UTF-8 text and Base64 binary representation with truthful URI, MIME type and applicable metadata. A
  valid empty file is successful empty content, not an empty `contents` array masquerading as not-found.
- `resources/templates/list` returns an empty complete result. This defers parameterized URI resolution, not
  ordinary files whose content happens to be a template.
- Compose providers behind one owner of each Resource method. Reject ambiguous URI ownership rather than relying
  on provider order. Registered content may use non-filesystem URIs; no lookup implies filesystem or network access.
- Preserve protocol-complete result/cache representation. Use conservative private freshness defaults and optional
  validated overrides. Only the consumer may designate genuinely caller-independent public content; immutable bytes
  or a known URI do not make access public.
- Bound metadata, pages, cursors, URI inputs, raw content and encoded results with sensible finite defaults and
  validated overrides. Account for JSON/Base64 expansion and reject excess without truncating successful content.
  A configured valid resource must be readable within the corresponding response bounds.

The provider boundary separates metadata enumeration, availability and content retrieval without prescribing exact
PHP types, storage technology, URI grammar or limit values. The consumer owns document rendering, revision lookup,
identity and permission decisions. Common owns protocol mechanics and their consistent enforcement.

## Use cases

These are protocol reads, not new business CQRS messages. Commands and domain events are N/A because Resource
requests do not mutate application state; a provider may call existing consumer queries without Common defining them.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Client lists available registered content | N/A; read-only | `resources/list`; provider-owned enumeration | N/A; no domain mutation | Return filtered descriptors and continuation without opening unrelated file content. |
| Client reads an exact URI, whether listed or supplied directly | N/A; read-only | `resources/read`; provider-owned lookup | N/A; no domain mutation | Return only the authorized requested content; no implicit URL fetch or path traversal. |
| Client asks for URI templates | N/A; read-only | `resources/templates/list` | N/A; no domain mutation | Return an empty complete template list with valid cache metadata. |
| Availability changes after a list or prior read | N/A; read-only | Fresh availability check on the next list/read | N/A; no domain mutation | Conceal denied content even when the client retains a URI, cursor or cached descriptor. |
| Client resumes with an invalid or stale cursor | N/A; read-only | Validate continuation; restart enumeration when appropriate | N/A; no domain mutation | Return a safe failure; ordinary client restart needs no new human approval. |

Diagnostics and optional cache effects are technical side effects. No Common schema migration, catalog write,
Workflow transition or guarantee to erase previously disclosed client knowledge is introduced.

## Validation and permissions

- Apply the same injected consumer availability decision to discovery and direct reads before protected names,
  counts or bytes are exposed. Common receives neutral resource metadata, not Agent OS permission strings,
  credentials or a generic principal. Authentication remains outside this capability.
- Validate method parameters, URI identity, cursor shape/scope and provider output. Accept protocol-valid cursor
  values, including an empty string where permitted; end-of-list is absence of `nextCursor`, not its truthiness.
  Cursors confer no authority and must not reveal concealed content. Do not claim cross-page snapshot consistency
  for a changing catalog unless the provider actually supplies it.
- Reject traversal or reinterpretation that escapes registered ownership, including encoded-path ambiguity. No
  URI-to-arbitrary-path/URL fallback, automatic redirects, DNS lookup or directory recursion is allowed. Any later
  storage adapter must prove its own containment; a neutral URI contract is not proof of filesystem safety.
- Unknown and unavailable resources have the same safe protocol outcome. Invalid parameters/cursors use the
  approved invalid-parameters response; unexpected provider failures become generic internal errors with sanitized
  diagnostics. Resource errors are not Tool `isError` results. Do not silently repair invalid UTF-8 into different
  bytes or serialize an inconsistent provider result as success.
- Validate limits and cache overrides, including relationships between raw and encoded bounds. Cache reuse must not
  bypass current availability or cross private authorization contexts. No new permission or cache policy engine.

## Dependencies and sequencing

- [EPIC-00007](../epics/00007-EPIC.md) owns scope and completion; [TICKET-00023](00023-TICKET.md) supplies the
  semantic foundation and existing guarded endpoint. TASK-00105 is merged in current `develop`, although this
  original planning base predated that merge. Publication preparation fast-forwarded to `52ff756`; implementation
  must still recheck and consume the actual reviewed foundation.
- Resources is independently useful and does not depend on Skills or completed Tools. Demonstrate it through the
  existing endpoint as well as focused semantic fixtures; do not create a replacement endpoint.
- [TICKET-00029](00029-TICKET.md) extends the shared provider composition with static Skill files.
  [TICKET-00030](00030-TICKET.md) owns combined Tools/Resources/Skills acceptance and additive shared request-body
  bounds. Coordinate the boundary without making basic Resource contracts depend on that final combined journey.

## Acceptance and evidence

Package-owned fixtures must demonstrate nonempty and empty catalogs; authorization filtering before paging;
deterministic continuation; direct authorized reads absent from discovery; denied/unknown concealment; changed
availability; exact text/binary and empty-file reads; and correct empty template discovery. Include multiple
providers, ownership conflicts, malformed output, traversal attempts, stale/invalid cursors, limit boundaries,
encoding expansion, private cache separation and provider failures.

Endpoint evidence must preserve Resource URI mirror checks and existing Origin/guard rejection before content
retrieval. A focused Resources endpoint composition can be accepted without Skills or Tools; it does not replace
TICKET-00030's combined endpoint acceptance.

Run applicable pinned official Resources conformance scenarios and record source/version, exercised cases,
results and gaps honestly. Supplement harness gaps with owned behavioral evidence; skipped required behavior is
not a pass. Classify all new public APIs and observable behavior in the compatibility manifest and prove existing
MCP/HTTP consumers remain compatible. Document composition, authorization injection, errors, cache behavior,
limits/overrides and deferred templates. Each implementation change requires the project full gate, including
exact production coverage; this is not deferred to TICKET-00030. Slice evidence is recorded below and in the owning
TASKs; this TICKET does not yet claim complete Resource acceptance.

## Exclusions

Skills-specific manifests, Tool registration/invocation, filesystem mounting, automatic URL fetching, URI-template
resolution, subscriptions/listChanged, directory RPC, consumer permission/catalog policy, production storage,
Agent OS providers/Pi client, new framework adapters and release/publication are excluded. Existing Tools, HTTP,
SSE, cancellation and authentication work retains its current ownership.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00117](../tasks/00117-TASK.md) | Discover Authorized Resources Through the Guarded Endpoint | done |
| [TASK-00118](../tasks/00118-TASK.md) | Read Exact Authorized Resource Content | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved the three-TICKET decomposition of EPIC-00007. This record owns reusable Resource behavior;
[WF-045](../wayfinder/tickets/WF-045-select-the-first-resources-and-skills-delivery.md) and the
[completed map](../wayfinder/fight-common-mcp-resources-skills-map.md) retain the accepted boundary.
[ADR 0005](../adr/0005-layer-dependency-matrix.md) preserves inward dependency direction and
[ADR 0010](../adr/0010-behavioral-contract-authority.md) governs documented behavioral promises. Exact interfaces,
values and implementation details remain routine design, not new human approval gates.

John approved two vertical implementation slices: [TASK-00117](../tasks/00117-TASK.md) delivers guarded Resource
discovery, and [TASK-00118](../tasks/00118-TASK.md) adds exact authorized reads and complete Resource acceptance.
Each owns its relevant verification, documentation, compatibility and full gate; there is no deferred testing TASK.
TASK-00117 retains TASK-00105 as an already-completed foundation dependency; TASK-00118 depends on TASK-00117.
Both remain unranked to preserve existing global priorities. The isolated planning copy of TASK-00105 was refreshed
verbatim from current `develop` solely to report its completed status/evidence, without importing runtime code.

TASK decomposition is complete. John subsequently authorized TASK-00117 in the main checkout; its discovery
implementation and local verification are complete at the 2026-09-29 pre-publication checkpoint, with independent
review pending. It supplies metadata-only providers, current visibility, bounded list/empty-template discovery,
real guarded-handler proof and applicable pinned conformance evidence. The broader caching scenario retains its
uncomposed/deferred-method failures; no full Resources conformance is claimed. TASK-00118's exact authorized reads
and complete Resource acceptance remain future work, requiring a separate implementation request.
