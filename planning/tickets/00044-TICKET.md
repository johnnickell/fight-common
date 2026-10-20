---
id: TICKET-00044
epic: EPIC-00010
title: Discover and retrieve authorized MCP Prompts
status: ready-for-agent
---

# Discover and retrieve authorized MCP Prompts

## Problem statement

Users need to discover available instructions and retrieve a complete, parameterized Prompt safely. Common must
supply reusable serving mechanics without owning the consumer's instruction library, duplicating Skills or turning
Prompt retrieval into execution. Listing and retrieval share definitions, access rules and content boundaries;
this TICKET owns the complete journey rather than splitting it into infrastructure and presentation requirements.

## Solution and boundaries

Implement [EPIC-00010](../epics/00010-EPIC.md)'s explicit, read-only Prompts capability under the accepted MCP
`2026-07-28` contract through the existing guarded HTTP composition.

- Register consumer-supplied Prompt definitions and content providers explicitly. Validate identities, declared
  arguments and supported descriptive metadata; reject invalid or conflicting registrations rather than exposing
  ambiguous behavior. Consumers own canonical content sources, wording, application vocabulary and runtime wiring.
- Serve `prompts/list` with only currently available definitions and their declared arguments/metadata. Support empty
  catalogs and bounded protocol pagination, with current access applied before disclosure. Results may change with
  catalog or permission changes, not as a side effect of remembered per-connection request history.
- Serve `prompts/get` by rechecking current availability, validating supplied arguments before content processing,
  then validating the complete bounded response before disclosure. Missing required arguments, invalid input,
  unavailable Prompts, unsupported output and provider failures must produce truthful safe protocol outcomes.
- Return complete messages containing text or Resource links only, using MCP `user`/`assistant` roles. No remote
  system/developer role or additional-input round trip is introduced. Parameterized content construction need not
  use a mandated template engine; it must remain within the declared argument and output contracts.
- Resource-link metadata must itself be safe for the recipient. Links neither authorize target access nor trigger
  network/filesystem retrieval, Skill loading, Tool execution or model work. Subsequent Resource reads retain their
  own current permissions. Hosts own message presentation/insertion and Skill loading/approval.
- Bound arguments, catalog metadata, message counts/content and final encoded responses. Apply protocol pagination
  and truthful cache metadata without treating a page token, cached listing or Prompt identifier as authorization.
  This catalog contract is separate from introspection's live `page`/`per_page` application Queries.
- Advertise only explicitly configured supported behavior. Do not advertise Prompt list-change delivery in this
  TICKET: Subscriptions owns that later integration. Ordinary HTTP or request-progress SSE does not establish
  subscription support. Do not advertise Completion, multimedia or interactive-Prompt behavior.
- Coexist with existing Tools, Resources and Skills without changing their public behavior. Reuse existing protocol
  dispatch, guarded HTTP, authentication/resource-server boundaries and consumer permissions rather than adding a
  second endpoint stack, principal model or token issuer.
- Discovery/retrieval are reads. Where a provider needs application data, use shared Query use cases through the
  existing `QueryBus::fetch()` boundary rather than adapter-level database reads or duplicated policy. Static
  instruction content does not require an invented business Query. No business Command, domain event or persistence
  workflow is needed merely to list or retrieve a Prompt.

Prompt text is instruction content, not permission, persona assignment, Workflow authority or review acceptance.
Referencing a canonical Skill avoids requiring a second copy of its procedure; it does not activate that Skill.
Repeatable instruction retrieval does not promise deterministic model behavior.

## Use cases

Operation labels describe responsibilities, not final PHP class names or a required provider implementation.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer enables a Prompt catalog | N/A; explicit composition | N/A; registration does not retrieve content | N/A; no domain change | Validate registration and advertise only enabled behavior |
| Caller discovers currently available Prompts | N/A; read only | Protocol `prompts/list`; shared application Queries only where content selection needs them | N/A; read only | Return bounded authorized metadata and protocol pagination, including empty results |
| Caller selects a Prompt and supplies arguments | N/A; no mutation dispatch | Protocol `prompts/get`; shared application Queries when application data is needed | N/A; read only | Return a validated complete text/Resource-link message result |
| Caller requests a Prompt after permissions or catalog change | N/A; read only | Fresh list/get with current access | N/A; read only | Apply current decisions rather than relying on a previous listing or connection history |
| Host receives a Resource or Skill reference | N/A; no automatic execution | No automatic target read; any later Resource request is separately authorized | N/A; no domain change | Return safe reference metadata only; host chooses subsequent interaction |

## Validation and permissions

Consumer composition owns authentication and current access decisions. Apply those decisions independently on
listing and retrieval; a Prompt name, prior listing, client capability or page token grants no access. Metadata
and arguments require protection as well as the resulting text. Consumer identity and permission policy must not
be accepted from untrusted Prompt arguments as authority.

Validate outer request shape, declared argument requirements/types and finite bounds before provider processing.
Validate output roles, supported content blocks, Resource-link metadata and complete encoded response limits before
returning results. Keep safe public outcomes distinct from private diagnostics: never disclose provider exception
messages, credentials, hidden definitions or protected content through errors. A provider/storage failure is not a
successful empty catalog. Do not silently drop unsupported content and claim a complete Prompt.

Exact PHP APIs, provider seams, numeric limits, pagination/cache mechanics, supported optional metadata and detailed
failure mappings are downstream design within these requirements. Document them and test permission changes across
calls; neither a custom content store nor a remembered browsing session is required. Consumer provider code remains
trusted application composition, not sandboxed remote code.

## Dependencies and exclusions

Reuse the existing [TICKET-00023](00023-TICKET.md) protocol/guarded HTTP foundation,
[TICKET-00024](00024-TICKET.md) coexistence conventions and [TICKET-00028](00028-TICKET.md) Resource-link/metadata
contracts where applicable. These existing capabilities do not already implement the Prompt catalog or lifecycle.
Skills may be referenced without making a specific consumer Skill library or Skill-loading implementation a gate.
The existing registry's recognition of Prompt methods is not acceptance evidence for list/get behavior.

No dependency on execution introspection, [EPIC-00011](../epics/00011-EPIC.md) Subscriptions or
[EPIC-00012](../epics/00012-EPIC.md) Tasks. Prompt list-change notification integration remains with Subscriptions;
this TICKET does not enable its flags or install a transport. No additional transport, new consumer permission
policy or amendment of Agent OS planning is authorized.

Exclude interactive Prompt flows, embedded Resource/document bodies, image/audio content blocks, argument
Completion, Resource templates, Apps and URL-mode input. Also exclude automatic link fetching/Skill activation,
model/Tool execution, persona or identity assignment, a mandatory template engine/catalog database, consumer Prompt
authoring/UI, guaranteed model behavior and actual client deployment qualification. No release target is selected.

## Acceptance and evidence

Automated tests must demonstrate the full journey through actual package-owned guarded HTTP composition:
discover permitted definitions, select a Prompt, supply arguments and retrieve validated text/Resource-link messages.
Evidence must cover:

- Explicit opt-in registration, malformed/conflicting definitions, truthful advertisement and unchanged coexistence
  with Tools, Resources and Skills, including operation without Subscriptions or asynchronous Tasks.
- Authorized empty/nonempty catalogs, bounded protocol pages, changed catalogs/access between requests and no
  per-connection discovery state. Verify selected pagination/cache behavior without treating either as permission.
- Valid parameterized content and required/invalid argument rejection before provider processing.
- Valid `user`/`assistant` text and Resource-link responses, invalid roles/content/metadata, and complete encoded bounds.
- Independent current retrieval authorization, safe unavailable/failure outcomes and no hidden metadata/content or
  private diagnostics in errors. Verify provider failures are not presented as empty successful results.
- No automatic Resource fetching, filesystem opening, Skill loading, Tool/model execution or business mutation in
  the provided serving path. Application-backed content uses shared Query policy rather than adapter database reads.
- No false list-change, Completion, multimedia or interactive-Prompt support claims.

Document public APIs, opt-in composition, content subset, pagination/cache behavior, finite limits, failures and
ownership boundaries; align CONTEXT.md when implemented. Each implementation TASK owns applicable product tests,
exact owned-production coverage, public compatibility classification and the complete local `./bin/build` gate under
the [project profile](../agents/project-profile.md). Package tests prove Common behavior, not Agent OS/Pi or browser
qualification; consumer deployment is separately owned and is not an extra Common acceptance gate. These are future
requirements, not product checks already run for this capability.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00157](../tasks/00157-TASK.md) | Discover and retrieve authorized MCP Prompts | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this single cohesive requirement TICKET as the complete decomposition of EPIC-00010. It retains the
accepted text/Resource-link-only content boundary and the HTTP-first, consumer-owned authority rules from
[WF-048](../wayfinder/tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md). Supporting protocol
evidence is linked from the [research](../wayfinder/research/mcp-prompts-skills-jobs-and-live-updates.md#1-prompts-and-skills-can-complement-each-other)
and the [official Prompts specification](https://modelcontextprotocol.io/specification/2026-07-28/server/prompts).

John subsequently approved one complete vertical TASK:
[TASK-00157 — Discover and retrieve authorized MCP Prompts](../tasks/00157-TASK.md), with no unfinished blockers.
Registration, listing and retrieval share definitions and authorization; this slice delivers the full guarded HTTP
journey instead of a partially usable capability. The complete decomposition is accepted and written.

Approved initial mechanics: an explicit bounded catalog with lazy selected content providers; protocol string-valued
arguments with declared required fields and rejection of undeclared/invalid/oversized input before processing;
deterministic catalog ordering and stateless authenticated continuation with fresh access and safe stale-token
rejection; private/zero-TTL listing defaults; existing Resource metadata and bounded-result/central-metadata reuse
with final encoded-response proof. Static content needs no business Query; application-backed content uses shared
`QueryBus::fetch()` use cases. Exact public APIs, supported optional metadata and numeric limits remain implementation
details to document. No mandatory database/template engine or dependency on unfinished introspection is introduced.

The TASK owns applicable tests, compatibility, docs/CONTEXT.md, exact coverage and full local build. No global
execution priority or worktree choice is assigned; choose main checkout or isolated worktree explicitly before
implementation. `ready-for-agent` records accepted planning, not implementation or authority to bypass map-wide
planning. No implementation, consumer amendments, commit or publication have occurred; release target is unassigned.

EPIC-00011's complete four-TICKET split is now approved and written as TICKET-00045–00048.
EPIC-00012's complete four-TICKET split is also approved and written as TICKET-00049–00052, completing all map
TICKET decomposition. TICKET-00040's complete three-TASK split is now accepted and written as TASK-00147–00149.
TICKET-00041's complete split is accepted/written as TASK-00150–00152; TICKET-00042's complete split is
accepted/written as TASK-00153–00154, and TICKET-00043's complete MCP split as TASK-00155–00156. EPIC-00009 and
EPIC-00010 now have complete accepted TASK decompositions without implementation or dependencies between them.
The [map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now records complete map-wide TASK decomposition through TASK-00171 and recommends Board-based selection, not automatic execution.
TICKET-00045's complete recording/removal split is also accepted/written as TASK-00158–00159, independently of Prompts.

TICKET-00046's complete inspection/recovery split is also accepted/written as TASK-00160–00161.

TICKET-00047's complete split is now accepted/written as TASK-00162–00164. Optional Prompt notifications belong to
[TASK-00164](../tasks/00164-TASK.md), blocked by live TASK-00162 and serving TASK-00157; ordinary serving retains no
reverse dependency, and Tool/Resource streams plus subscriber inspection need not wait for Prompts.

Next: `/skill:next`
