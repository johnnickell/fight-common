# Define MCP tool and CQRS integration conventions

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common MCP over HTTP Support](../fight-common-mcp-http-support-map.md)
**Depends on:** [Define Fight Common and consumer ownership boundary](WF-039-define-fight-common-and-consumer-ownership-boundary.md), [Define reusable OAuth resource-server support](WF-043-define-reusable-oauth-resource-server-support.md)

## Question

How should an MCP tool describe, validate, and invoke an existing Fight command or query without duplicating application behavior?

## Why this matters

The convention determines public tool schemas and naming, DI registration, validation ownership, idempotency/retry implications, and whether a consumer can safely reuse its existing command/query handlers.

## Evidence required

- MCP `tools/list` and `tools/call` schemas and error semantics.
- Fight Common command/query buses, message construction, validation contracts, and safe presentation conventions.
- A representative query and command mapping design; a real Fight Access Control candidate is useful later validation evidence, not a design prerequisite.

## Must decide

- tool definition/registration ownership and schema source;
- command/query mapping and metadata/context propagation;
- validation boundary and rejection behavior; and
- tool naming, discoverability, and cacheability conventions.

## Resolution boundary

This may define reusable tool integration conventions. It does not select the first consumer’s permissions or expose every existing command/query as an MCP tool.

## Resolution

An explicitly opted-in `McpTool` is the consumer adapter from MCP to an existing command or query. Its `handle()`
receives validated `ApplicationData`, maps it explicitly to the existing payload, calls `CommandBus::execute()` or
`QueryBus::fetch()`, and returns public-safe `McpToolOutput`. It does not invoke an HTTP Action, reflectively hydrate
use cases, duplicate handler behavior, or create transport responses. Command dispatch remains void; a tool returns
only data it truthfully knows or an existing follow-up query result when genuinely required.

`#[McpToolInfo(name:, description:, inputSchema:, outputSchema:)]` is the required method-level, explicit opt-in
and discovery contract. `#[Validation]` and `ValidationService` validate exactly `tools/call.params.arguments`
before the tool receives `ApplicationData`. The schema contract is explicit and separately requires
consistency/conformance evidence; no validation rules still permits validated `ApplicationData` over the arguments.

MCP transport information does not enter command/query payloads. Existing `CommandFilter` and `QueryFilter`
pipelines may enrich message envelopes for audit and tracing without handlers observing a human-versus-agent
distinction. Fight Common supplies a small namespaced MCP metadata baseline, including canonical tool and correlation
identity, plus an opt-in consumer extension hook for scalar, namespaced audit metadata derived from its resolved
principal/context. It never adds authorization decisions, credentials, raw arguments, headers, or an Agent model;
exact key names are implementation details. Consumers that do not compose those pipelines retain normal bus behavior.

A later consumer proof may validate the convention when an eligible Agent command exists; it is neither a prerequisite
nor a blocker for Fight Common or Fight Access Control development.

**Decision owner:** John

**Exit condition:** The reusable conventions show how a representative command and query map without application-use-case duplication and with an explicit public-safe tool contract. A later consumer proof is optional implementation evidence, not a Wayfinder, release, or development gate.
