---
id: TICKET-00028
epic: EPIC-00007
title: Discover and Read Authorized Resources
status: done
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
TASKs. Explicit parent acceptance is recorded in the closeout section below.

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
| [TASK-00118](../tasks/00118-TASK.md) | Read Exact Authorized Resource Content | done |
<!-- /planning:children -->

## Decisions and progress

The implementation checkpoints below retain their historical review and delivery states. The final parent
acceptance section records the current closeout disposition; earlier pending statements are not current blockers.

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
uncomposed/deferred-method failures; no full Resources conformance is claimed. TASK-00118 was subsequently authorized in the main checkout; its exact reads and integrated Resource behavior
are implemented and locally verified at the 2026-09-30 pre-publication checkpoint. The first independent TASK-00118 review returned revise for R1 (central metadata omitted from pre-advertisement
read-budget validation). The 2026-09-30 revision now enforces the complete composed bound before advertisement or
content opening and passes a fresh full gate. Independent re-review, applicable QA and explicit TICKET closeout
remain pending; this parent is not automatically marked done.

| TICKET outcome | Owning implementation and evidence |
|---|---|
| Authorized metadata, deterministic bounded pages, opaque continuations, empty templates | TASK-00117 discovery contracts/tests and guarded discovery journey, preserved by TASK-00118 and the full gate. |
| Exact authorized listed/unlisted reads, current denial, byte/empty-file fidelity | TASK-00118 `McpResourceReadTest`, `McpResourceContentTest` and `McpResourceReadJourneyTest`; only selected authorized content opens. |
| Ownership conflicts, malformed output, traversal/non-fallback, raw/encoded bounds | TASK-00117 discovery validation plus TASK-00118 all-provider lookup, stream/metadata checks and R1 list/read exact-boundary regression including actual central metadata before advertisement/opening. |
| Cache hints, private separation, failures, guard/mirror/authentication ordering | Both TASKs' actual guarded-handler journeys; reads have no authority/content cache, retain fresh policy and safe central errors. |
| Protocol checks, compatibility, docs, complete local gate | TASK-00118 pinned conformance: list/text/binary/schema and Resource caching pass; missing-resource URI-data warning retained; only uncomposed Tools/Prompts fail aggregate caching. Three public additions/two behavior contracts classified, docs/CONTEXT updated, R1 full build exit 0 with exact 12,236/12,236 unit statements; additive internal metadata dispatch classified. |
| Independent acceptance | TASK-00117 is merged in the implementation base. TASK-00118 R1 is repaired and locally verified; independent re-review and applicable QA remain pending. Builder proof does not close this requirement. |

See TASK-00118 for exact counts, warnings, retained local receipt/transcripts, compatible read opt-in, consumer
storage/authorization boundaries and remaining downstream exclusions. No release, publication, Pi, Skills or
combined Tool/Resource-link acceptance is inferred.

## Parent acceptance and closeout

John authorized this tracked closeout after the independent parent assessment of `develop` at
`5d8ca58341695f9e4498aefc6fca205569475854`. That assessment explicitly accepted this bounded Resource parent,
not merely its completed child table. TICKET-00028 is **done**: discovery and exact reads satisfy the parent
outcomes above, both children have independent technical acceptance and applicable behavioral QA PASS, and the
required local verification is complete. Historical checkpoints and original reports remain unchanged.

- [TASK-00117](../tasks/00117-TASK.md) supplies authorized metadata-only discovery, bounded deterministic pages,
  authenticated cursors and empty templates; [TASK-00118](../tasks/00118-TASK.md) supplies exact authorized
  listed/unlisted reads, text/binary/empty-file fidelity, complete pre-disclosure read budgets and guarded endpoint
  integration. Independent re-review resolved TASK-00118 R1; neither its old pending checkpoint nor builder proof
  substitutes for that verdict. Canonical reports are `.runs/reviews/TASK-00117/review.md` and
  `.runs/reviews/TASK-00118/review.md`; behavioral dispositions are `.runs/qa/TASK-00117/qa.md` and
  `.runs/qa/TASK-00118/qa.md`.
- The assessment inspected later accepted Skill/provider integration rather than assuming ancestry meant unchanged
  Resource code. Across the Resource/Skill parent assessment, 1,507 focused tests / 4,363 assertions and six
  journeys totaling 32 tests / 898 assertions passed at the assessed head. All 209 indexed artifacts for the four
  child QA reports verified without missing files or digest mismatches. These checks are assessment evidence,
  not new QA or official-harness executions.
- The retained TASK-00120 complete gate has exit 0: Unit 4,972 / 11,428, exact 12,651/12,651 owned statements,
  Integration 150 / 1,024 and Functional 49 / 1,999. Its tested snapshot matches `d9cce23`; intervening changes
  through the assessed head are planning-only. Documentation and planning validation passed. This administrative
  closeout changes no product inputs; its own targeted checks and content mapping belong in the local handoff.
- Pinned official evidence remains scenario-specific: source `7169291ec0b68eb370fddcd9947313ab0d5e4156`,
  version `0.2.0-alpha.11`, protocol `2026-07-28`. Resource list/text/binary each have two SUCCESS results;
  missing-resource has three SUCCESS / one optional URI-data WARNING. Aggregate caching has six SUCCESS /
  two FAILURE for uncomposed Tools/Prompts only. No blanket MCP/caching qualification is claimed. Existing
  Material/MkDocs compatibility and guide-outside-navigation advisories remain disclosed.

The complete outcome mapping, provenance and limitations are retained locally in
`.runs/reviews/parent-closeout-5d8ca583/report.md`; the administrative handoff is
`.runs/handoffs/ticket-00028-00029-closeout/receipt.md`. Consumer storage, identity, authorization/cache policy
and real Pi qualification remain outside this acceptance. TICKET-00030 still owns shared ingress bounds and
combined Tools/Resources/Skills acceptance; EPIC-00007 remains open. This closeout neither archives records nor
authorizes publication, merge, release or deployment. TICKET-00026/00027 and their outstanding QA are unchanged.
