# Select additional MCP capabilities from consumer journeys

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Reusable execution introspection and MCP evolution](../mcp-execution-introspection-map.md)
**Depends on:** [WF-046 — Choose reusable execution introspection outcomes and ownership](WF-046-choose-execution-introspection-outcomes-and-ownership.md)

## Question

Which additional MCP features should Common support for the selected introspection outcome or another explicitly
named consumer journey, and which should be deferred rather than implemented for completeness?

## Evidence and recommendation

[The research inventory](../research/mcp-observability-and-protocol-opportunities.md#4-mcp-feature-inventory-and-recommendation)
compares existing package support with official MCP `2026-07-28` and extension documentation. Tools and Resources
are core primitives; Skills is an official extension, not a replacement for core Prompts. Logging, Roots and
Sampling are deprecated in this revision and should not anchor new work.

Prefer the existing Tools/Resources surface for summary-first paginated Queries. Prompts can add deliberate
user-selected inspection recipes; subscriptions can support live updates when a real client needs them. Evaluate
Tasks for a real long-running operation and MCP Apps only for a supporting host's embedded UI requirement.
These were the initial recommendations. The accepted selections and deferrals below supersede them; no actual
client support is established by this planning decision.

## Initial scope discussion

WF-046 and WF-047 are Closed. The accepted introspection journey uses shared summary/detail Queries with stateless
live offset/limit pagination. Consumer browser updates and Common domain-event dispatch do not by themselves
require MCP subscriptions, asynchronous MCP Tasks or an embedded MCP UI.

The initial recommendation was existing Tools/Resources with additional capabilities deferred absent a present
consumer use case. John has now identified candidate uses below; blanket deferral was not accepted. New
introspection Tool handlers are still needed; existing protocol support does not mean introspection is implemented.

| Optional capability | Concrete user benefit to select if needed now |
| --- | --- |
| Prompts | A user chooses a saved instruction such as “Explain why this session failed.” |
| Resource templates / Completion | A client discovers parameterized resource addresses and suggests permitted argument values. |
| Subscriptions | An MCP client receives notice of new activity without repeatedly asking for changes. |
| Tasks extension | A long-running Tool operation returns a handle so its result can be retrieved later. |
| MCP Apps | A compatible MCP host displays an interactive view inside the host. |
| URL-mode elicitation | A user completes an interaction on a separate website instead of an ordinary input form. |

This table records the options considered, not the final selection. Actual consumer/client integration remains
separate qualification; no host support is assumed. Reuse
[WF-043 — Define reusable OAuth resource-server support](WF-043-define-reusable-oauth-resource-server-support.md)
and consumer-owned authentication/permissions rather than treating optional feature selection as new identity scope.

## Consumer questions and follow-up evidence

John expressed interest in role/persona-based Prompts alongside Skills, structured data easier to map to database
fields, subscriptions potentially integrated with Mercure and asynchronous Tasks resembling cron-polled Jobs.
Apps remain a possibility; URL-mode input needs clarification against OAuth and Agent web ingestion.

[Research: MCP roles, repeatable workflows, structured data and live jobs](../research/mcp-prompts-skills-jobs-and-live-updates.md)
answers those questions from primary protocol sources, current Common code and read-only Agent OS planning:

- Prompts can supply selectable role-guided instructions; Skills supply reusable procedures/supporting files.
  Neither assigns authoritative Agent identity, permission or system-message priority. Host-owned loading remains
  explicit; a prompt naming a Skill does not automatically activate it.
- Resource templates describe URI patterns, not data schemas. Tool input/output schemas plus application
  validation fit structured findings and database mapping; Common already supports that mechanism.
- MCP subscriptions and Mercure can consume the same application changes, with separate authorized protocol
  adapters. Existing Mercure publishers are not an implemented MCP subscription bridge.
- MCP Tasks can expose asynchronous jobs and be polled, but do not supply scheduling or job execution.
- URL-mode elicitation supports human interactions such as third-party OAuth, not MCP-server authentication or
  Agent web ingestion. Agent OS already plans separately authorized search/retrieval Tools in TICKET-00095.

John subsequently agreed with these distinctions and selected Prompts, Subscriptions and Tasks as the strongest
candidates for generic implementation. Consumer instructions, Mercure operations, cron jobs, account/token
lifecycle and web-ingestion policy remain with their owners.

## Accepted generic capabilities

John approved the following generic implementation-planning destinations, rather than limiting Common to
introspection-specific features:

| Capability | Selected journey and ownership |
| --- | --- |
| Prompts | Users select parameterized role-guided task instructions alongside reusable Skills. Common supplies discovery/retrieval mechanics; consumers own instructions, persistent persona/identity, permissions and host loading. |
| Subscriptions | Authorized MCP clients receive application/resource change notifications and can read current data. Common supplies reusable MCP subscription behavior; Mercure may share the event source or integrate through an adapter, without being required for every consumer. |
| Tasks | Clients track asynchronous Tool work through durable handles, status and eventual results, including polling/cron-based integration. Common supplies reusable protocol/lifecycle mechanics; consumers own job execution, scheduling and application policy. |

The structured-data use case uses existing Tool schemas plus application validation and Commands/Queries; it does
not by itself select Resource templates or Completion. Agent identity, planning TASKs, application jobs and MCP
Task handles remain distinct. Neither a Prompt nor a completion notification grants Workflow/review authority.

No client support is assumed by selecting capabilities. This is approval of planning direction, not authorization
to implement features or change Agent OS policy.

## Accepted initial deferrals

John approved deferring **Apps, URL-mode input, Resource templates and Completion** from the initial scope.
These remain possible later capabilities, not speculative implementations or prerequisites of the selected work.
The structured-data requirement uses existing Tool schemas; no selected address-discovery, embedded-host or
third-party account-connect journey currently requires these additions. Ordinary input/confirmation support remains
available under its existing contract, and the prior OAuth resource-server boundary is unchanged.

## Accepted transport, composition and qualification boundaries

John approved these boundaries for the three additional MCP capabilities:

- Start with Common's existing **HTTP transport**. Additional transports are not part of this initial scope.
- Enable each capability explicitly and advertise only what is actually available. Follow the target MCP
  `2026-07-28` and official Tasks extension contracts, not earlier subscription/experimental-Tasks APIs. Unsupported
  capabilities or client combinations must not be presented as supported; protocol-specific fallback/error behavior
  belongs in the destination's requirements and tests.
- Reuse consumer authentication and permissions and WF-043's existing resource-server boundary. Do not create a
  principal model, token issuer or new consumer permission policy. Apply the supplied access decisions to discovery,
  invocation and protected notification/task data as relevant to each capability.
- Prove Common behavior with protocol/integration tests through the existing HTTP composition. Actual client/host
  deployment qualification is separately owned; package tests are not proof of deployed Agent OS/Pi support, and
  that downstream deployment is not a new Common acceptance prerequisite.
- Keep the capabilities independently usable: introspection reads do not require subscriptions; Tasks supports
  polling without subscriptions. Persistent Task records and subscription streams do not change WF-047's stateless
  offset/limit data Query contract or make transport state authoritative Workflow state.

## Approved EPIC destinations

John approved **four separate EPIC destinations**. At decision closure these were named handoffs, not newly created
EPIC records. The [map's planning handoff](../mcp-execution-introspection-map.md#planning-handoff) tracks subsequent
grill approvals and resulting EPIC links:

| Destination title | Approved boundary |
| --- | --- |
| Execution introspection | Recording, persistence, domain events and shared summary/detail Queries with MCP presentation under WF-046 and WF-047. |
| Generic MCP Prompts | Discover and retrieve parameterized instructions alongside Skills, with consumer-owned persona/content and host loading. |
| Generic MCP Subscriptions | Authorized MCP change notifications, with optional Mercure integration and no mandatory Mercure dependency. |
| Generic MCP Tasks | Durable asynchronous-operation handles, status, results, input and cancellation; consumer-owned job execution and scheduling. |

Proceed through one named EPIC handoff at a time, starting with Execution introspection. This is planning order,
not a dependency requiring the generic capabilities to wait for introspection implementation. Complete all four
EPIC handoffs before TICKET decomposition, then all map TICKET decomposition before TASK decomposition. Exact
classes, catalogs, storage adapters, limits and protocol-specific failure cases belong in those planning stages;
do not reopen the settled ownership and feature-selection decisions merely to allocate implementation work.

## Resolution boundary

May select bounded destinations and exclusions from the researched inventory. Does not implement features,
qualify a client, create EPICs without approval, replace Agent OS's Dashboard/Mercure or change its existing
protocol/permission/infrastructure scope. CQRS and summary-first pagination remain applicable across adapters.

## Resolution

Closed following John's approval of the selected capabilities, initial deferrals, HTTP-first composition and
qualification boundaries, and four-EPIC split. Prompts, Subscriptions and Tasks are generic package destinations;
Execution introspection retains WF-046/047's distinct accepted contract. Apps, URL-mode input, Resource templates
and Completion are deferred from this initial scope.

All decisions in this map are Closed. At closure, the next operation was the named **Execution introspection** EPIC
handoff through grill, followed by the other three approved destinations. The subsequent approved grill created
[EPIC-00009](../../epics/00009-EPIC.md); see the map for the current remaining handoffs. This decision closure itself
did not run grill or authorize TICKET/TASK decomposition, implementation, consumer changes, commit, publication or
release.
