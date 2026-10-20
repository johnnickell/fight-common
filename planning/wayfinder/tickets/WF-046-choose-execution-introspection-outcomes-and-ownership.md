# Choose reusable execution introspection outcomes and ownership

**Labels:** `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Reusable execution introspection and MCP evolution](../mcp-execution-introspection-map.md)
**Depends on:** —

## Question

Which first session/phase introspection journey should Fight Common make reusable, and which responsibilities
belong in Common rather than the consuming application's execution/history model?

## Accepted constraints

John stated during the initial investigation: **“we want to stick with CQRS implementations and every big query
should be paginated where possible. We can always surface summary statistics and then allow one to drill down to
the details in a pages fashion.”**

Keep observation mutations behind Commands and read-only summaries/details behind Queries. MCP and native
Dashboard adapters consume the same application use cases, not direct database access or duplicate business logic.
Large collections need bounded paginated drill-downs; summary statistics cover the selected authorized scope,
not merely a rendered page. This accepts a design constraint, not the proposed catalog or a particular store.

## Accepted initial audience and journey

John approved both human dashboards and authorized Agents using the same application Queries, with
caller-specific permissions. The first complete journey answers: **“What is happening, where did it stop,
and what evidence explains the outcome?”**

1. Summary: execution counts, known outcomes, measured usage and freshness for the permitted scope.
2. Executions: a paginated list matching that scope.
3. Selected execution: its summary, followed by paginated phases.
4. Selected phase: paginated sanitized activity and authorized evidence references.

Trusted runtime instrumentation supplies observations through Commands. Inspection grants neither Workflow
transition authority nor raw-log access. This approval settles the audience and journey, not Common's storage
ownership, concrete Tool catalog or any amendment to Agent OS's existing plans.

## Accepted ownership

John approved a reusable Common implementation, not merely interfaces or MCP wrappers:

| Common owns | Consumer owns |
|---|---|
| Safe observation contracts and CQRS handlers | Workflow/phase meaning and authoritative transitions |
| Summary Queries and paginated detail Queries | Caller permissions and trusted producer identification |
| Reusable persistence adapter | Database composition, retention and operational policy |
| MCP presentation of those use cases | Dashboard UI and runtime integration |

For Agent OS, the reusable implementation should underpin its planned observation ledger rather than introduce
a competing execution database. Authoritative Workflow state stays with its existing owner. No amendment to
Agent OS's existing policy or planning records is requested or authorized by this decision.

This settles package ownership, not a storage product, schema, concrete Tool catalog or delivery scope. Exact
storage guarantees and adapter design belong to
[WF-047 — Define safe observation recording and query guarantees](WF-047-define-safe-observation-recording-and-query-guarantees.md).

## Supporting evidence

[The research note](../research/mcp-observability-and-protocol-opportunities.md) identifies the old Factory's useful
session/phase/evidence relationships and Agent OS's current separation of authoritative Workflow state, sanitized
observations and privileged operational logs. Agent OS already owns a ledger and Summary/Changes/Activity/Evidence
Dashboard plan; do not replace them.

The sampled `fight-software-factory` remains supporting evidence, not a confirmed substitute for the absent named
historical path. Further historical investigation is unnecessary to settle this outcome and ownership decision;
checkout confirmation remains conditional if later work needs more historical detail.

## Resolution boundary

May settle the first outcomes, package/consumer split and accepted exclusions. Does not choose concrete schemas,
allocate implementation records, modify another repository, authorize operational access or approve all researched
protocol features. Keep the accepted CQRS/pagination constraints even if the selected package scope changes.

## Resolution

Closed following John's explicit approvals of the initial audience/journey and the ownership split above.
The accepted outcome serves human dashboards and authorized Agents through shared summary-first, paginated CQRS
Queries, with trusted observation Commands. Common supplies the reusable implementation including a persistence
adapter; consumers retain application policy, composition and authoritative execution semantics.

[WF-047](WF-047-define-safe-observation-recording-and-query-guarantees.md) and
[WF-048](WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md) are now unblocked. No implementation,
consumer amendment, additional protocol feature or EPIC creation is authorized by this resolution.
