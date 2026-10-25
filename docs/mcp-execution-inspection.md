# Execution inspection through MCP

Opt in to five read-only Tools over the [shared execution Queries](execution-reads.md). They use the existing
[guarded MCP HTTP endpoint](components/mcp/index.md), not a new endpoint or generic CQRS bridge. Recording and
removal remain trusted application Commands. Two separately opted-in [protected-detail Tools](#protected-detail-opt-in-and-compatibility)
complete the journey through the shared protected Queries. Prompts, Subscriptions, asynchronous Tasks, a dashboard
and actual Agent OS/Pi deployment are not required or qualified by this package evidence.

## Composition and trusted context

Register the shared Query handlers on the consumer's `QueryBus` first, with `ExecutionReadAdmission` resolved
from **current trusted request context**, not Tool arguments or protocol/envelope metadata. Use the existing
`DbalExecutionReader`, `DbalPhaseReader` and `DbalActivityReader` for SQLite, MySQL or PostgreSQL; Tools know
only `QueryBus::fetch()` and typed safe results. The admission implementation supplies current project/session,
phase/activity and separate detail-reference grants. Configure Agent permissions independently from humans.

```php
use Fight\Common\Application\Mcp\Execution\GetExecutionTool;
use Fight\Common\Application\Mcp\Execution\ListExecutionPhasesTool;
use Fight\Common\Application\Mcp\Execution\ListExecutionsTool;
use Fight\Common\Application\Mcp\Execution\ListPhaseActivityTool;
use Fight\Common\Application\Mcp\Execution\SummarizeExecutionsTool;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;

// $queries is the ordinary QueryBus with all five shared inspection handlers.
// $availability implements McpToolAvailability using current consumer request context.
$tools = new McpToolRegistry([
    new SummarizeExecutionsTool($queries),
    new ListExecutionsTool($queries),
    new GetExecutionTool($queries),
    new ListExecutionPhasesTool($queries),
    new ListPhaseActivityTool($queries)
]);
$discovery = new McpToolDiscovery($tools, $availability, $dedicatedCursorKey);
$invocation = new McpToolInvocation(new McpToolInvoker($tools, $availability));
// Add both capabilities to the existing McpCapabilityRegistry and guarded McpRequestHandler.
```

Consumers still own authentication, route composition, Origin policy, invocation guard, timeouts and the
redaction-aware diagnostic sink. Registration performs no business read. Tool availability is reevaluated for
catalog discovery, HTTP mirror selection and invocation, independently of fresh use-case admission on every
Query. Listing a Tool, endpoint access, page parameters, IDs and returned detail references grant no business
access. Neither availability nor admission is cached. There is no automatic Command/event/Action registration.

## Exact Tool catalog and arguments

All arguments are JSON objects with **no additional fields**. IDs are existing 1–128-byte ASCII observation
references, validated again by the shared Domain Query contracts. `filters`, when present, is an object with
only the fields shown. Names are exact and case sensitive.

| Tool | Shared Query | Required arguments | Optional arguments |
| --- | --- | --- | --- |
| `execution.summarize` | `SummarizeExecutions` | `projects`: array of at most 16 project IDs | `filters`: `session_id`, `consumer_reference` |
| `execution.list` | `ListExecutions` | `projects` | Same filters; `page`, `per_page`, `direction` |
| `execution.get` | `GetExecution` | `project_id`, `session_id` | None |
| `execution.phases` | `ListExecutionPhases` | `project_id`, `session_id` | `filters`: `phase_id`, `agent_id`, `name`; `page`, `per_page`, `direction` |
| `execution.activity` | `ListPhaseActivity` | `project_id`, `session_id`, `phase_id` | `filters`: `producer_id`, `kind`, `model_id`; `page`, `per_page`, `direction` |

An empty project list selects nothing. Projects/filters select records; the consumer's current admission still
constrains the selection. `kind` is a supported namespaced name, at most 96 bytes. Unknown fields, including
`principal`, `access`, `_meta` inside arguments, raw SQL and body selectors, reject without a Query.

Application pages default to `page: 1`, `per_page: 100`. Size is 1–100; the application offset must be at most
1,000,000. The schema additionally bounds page to 1–1,000,001; the shared Query validates the actual offset
combination. `direction` is `ASC` or `DESC`, default `DESC`; it maps only to `latest_recorded_at` for executions/
phases and `recorded_at` for activity. These are live offset pages, not a snapshot, remembered selection or lease:
new/deleted evidence can repeat/skip rows on later pages. MCP **catalog** cursors are independent of these pages.

## Results and evidence meaning

Structured content is the existing typed safe result's exact `toArray()` projection, or JSON null for a missing/
concealed selected execution or phase. Summary/list return their ordinary empty result, while an admitted phase
with no matching activity returns an empty activity page. The compatible text content is JSON for exactly the
same complete structured value, including `null`; there is no success/error JSend wrapper on the MCP wire.
Discovery publishes the exact input/output schemas, including nullable selected results, fixed field sets,
finite arrays, explicit outcomes and completeness/availability states.

- Statistics cover **all authorized matches**, not the returned page. Tools never recalculate aggregates or
  retrieve missing data. Usage retains exact strings, grouping by metric/unit/provenance, completeness counts,
  measured zero versus null, and `activities_without_usage`. Incompatible units are never combined.
- Latest persisted recording time describes encountered retained evidence, not complete capture or a global
  checkpoint. Missing observation/measurement is not success or zero. Observed outcomes are not Workflow truth
  or independent Review/QA acceptance.
- Ordering preserves the shared immutable receipt-time/full observation UUID contract, including microseconds
  and deterministic producer/reference ties; it is not causal/commit order. Occurrence time preserves supplied
  precision independently. Performing Agent, model and instrumentation producer remain distinct.
- Activity exposes only safe summaries and separately permitted detail **metadata**. No protected body, comparison,
  concealed reference count or extension data is projected. Complete/partial capture remains historical; a missing
  content row means `missing` with unknown reason. Explicit absent/unavailable/expired markers survive. A retained
  reference is neither body permission nor a retention lease. Structural safety does not redact consumer secrets
  mistakenly placed in an otherwise valid safe summary.

## Finite presentation and safe failures

Shared application limits remain in force. Tool output schemas bound each page to 100 rows, models to 128 per
phase, usage measurements to 32 per activity, detail references to 32 per activity and summary usage groups to
256 (the current DBAL reader admits at most 128). References are at most 128 characters, activity summaries at
most 512 characters, timestamps at most 27 characters, usage values at most 128 characters and aggregate decimal
text at most 1,026. Domain byte/value validation remains stricter where applicable; schemas supplement it.

Every inspection Tool also rejects a projected JSON representation above **262,144 encoded bytes**, before text
construction. `McpToolOutput::boundedStructured()` opts into a **1,048,576-byte final encoded JSON-RPC success
response limit**. The positive configurable budget is 1–67,108,864 bytes for other explicitly bounded Tools.
The invoker retains that limit through structured-output schema validation and the complete result. The central
`McpJsonResponse::success()` encodes the actual final response, including compatible text duplication, Unicode/
slash/quote/backslash escaping, `resultType`, request ID, central server identity and other actual metadata, and
rejects an overflow before any successful bytes are handed to HTTP. Exact limits are inclusive. This seam is
reusable by later protected inspection; it does not introduce another responder or alter ordinary unbounded
`structured()`/`structuredWithResourceLinks()` output. It bounds encoding, not earlier Query allocations or
consumer streaming/proxy buffering; shared readers have their own finite scan/result safeguards.

Input schemas/rules use the existing safe Tool errors. Application reference/offset validation, unexpected storage
errors, malformed typed projections, schema violations and exceeded output budgets follow the existing generic
internal-error path and redaction-aware diagnostics, never raw exception messages, credentials, partial successful
history or silent truncation. Recovery is to reduce the selection/page or restore evidence/access/storage, then
make another independently admitted request. The failure itself uses the existing protocol error envelope, not
the rejected success payload; the bound is not a replacement global error/request-size policy.

## Compatibility and public surface

Against tagged releases, five final, opt-in public Application declarations are added:
`SummarizeExecutionsTool`, `ListExecutionsTool`, `GetExecutionTool`, `ListExecutionPhasesTool` and
`ListPhaseActivityTool`, under `Fight\Common\Application\Mcp\Execution`. Consumers may construct them with their
QueryBus and invoke their declared read-only handles; they are not inheritance or implementation extension points.
`ExecutionToolSchemas`, `ExecutionToolInput` and `ExecutionToolOutput` are internal presentation helpers, with no
consumer promise. The compatibility manifest classifies all eight additions.

`McpToolOutput::boundedStructured()` and its budget accessor are additive opt-in methods. The internal result
budget propagation methods and the central response validation preserve all existing unbounded result behavior;
no Tool interface signature, released alias, Query/admission/DBAL contract, dependency floor, schema/index,
recording format or transaction guarantee changes. The Execution API remains provisional until tagged; no codecs,
aliases or migrations for earlier development snapshots are introduced. The pre-existing release-manifest
operation fingerprint reconciliation remains release-owned; this product build is not release certification.

## Protected detail opt-in and compatibility

Register `GetCapturedDetailTool` and/or `ListCapturedMessagesTool` explicitly in the **same** registry above.
Neither is enabled by the five safe Tools. They invoke only `QueryBus::fetch()` with the existing
[protected Queries](execution-protected-reads.md), not SQL, a Resource reader, file access or a mutation bus.
First register `GetCapturedDetailHandler` and `ListCapturedMessagesHandler` with `DbalDetailReader` and a
consumer implementation of **`DetailReadAdmission`**, separate from `ExecutionReadAdmission`:

```php
use Fight\Common\Application\Mcp\Execution\GetCapturedDetailTool;
use Fight\Common\Application\Mcp\Execution\ListCapturedMessagesTool;

// Add to the explicitly selected safe Tool instances before constructing McpToolRegistry.
$selectedTools[] = new ListCapturedMessagesTool($queries);
$selectedTools[] = new GetCapturedDetailTool($queries);
```

Availability and detail admission remain **independent and fresh on every page/chunk**. Consumers resolve current
trusted caller context (including human/Agent differences) outside the arguments. `DetailAccess` separately
constrains project/session/phase, metadata and bodies. An endpoint grant, available Tool, prior read, safe timeline
result, ID, `_meta` or continuation is not protected-content authority. Registration/discovery reads no bodies.
No new route, principal, permission strings, authentication mechanism or optional capability is introduced.

### Protected catalog and schemas

Both input objects forbid extra fields and require the exact flat selection `project_id`, `session_id`, `phase_id`,
`producer_id`, `activity_id`. Project/session/phase/producer use the existing 1–128-byte reference grammar;
activity and detail IDs are full canonical lowercase UUIDs (schema length exactly 36, Domain validates syntax).
They select a complete relationship, never grant scope. Cross-project/session/phase/producer/activity or kind
mismatches and missing/metadata-denied identities produce the same JSON **null** with matching text `null`.

| Tool | Shared Query | Additional required fields | Optional fields and defaults |
| --- | --- | --- | --- |
| `execution.messages` | `ListCapturedMessages` | None | `page` 1, `per_page` 32 (1–32), `direction` DESC (ASC/DESC) |
| `execution.detail` | `GetCapturedDetail` | `detail_id`, `kind`: message/tool_arguments/tool_result/process_output | `offset` 0 (0–65,536), `length` 4,096 (1–4,096 raw bytes) |

Message pages use `recorded_at` ordering only; schema page is 1–1,000,001 and shared validation limits the actual
offset to 1,000,000. They preserve full metadata-permitted totals, immutable receipt microseconds/full UUID order,
and supplied occurrence precision. No filters/cursor/frozen browsing state is added. Each message has at most its
first 1,024 raw bytes; use `execution.detail` to continue. An admitted activity without messages gives an empty
page; out-of-range pages retain total. Safe activity references provide detail IDs/kinds; take the activity UUID
and producer from the activity row, not the detail ID. Every request resupplies the full selection.

Output is the typed Query's exact `toArray()` plus matching JSON text, validated by the declared schema:
`messages, total, page, per_page` for pages, or `reference, body_permitted, chunk` for individual detail. References
use the same safe capture/availability representation as activity. Metadata-only permission gives
`body_permitted: false, chunk: null`; a body grant without metadata grants reveals nothing.

A chunk is exactly `encoding: "base64", data, offset, bytes, total_bytes, next_offset`. Base64 contains at most
5,464 characters for at most 4,096 raw bytes. Coordinates are relative to this captured body, not an absolute
process stream; the reference separately retains stream/index/absolute offset. Decode strictly and concatenate
bytes before interpreting text: a chunk may cut a multibyte character. Declared media types and CRLF survive;
recording still requires UTF-8 and does not gain arbitrary binary capture. Captured content is untrusted data,
not instructions to execute. An empty captured body or exact-end offset yields a real empty final chunk.
Offsets beyond actual retained length fail safely, never silently clamp or fabricate bytes.

Complete/partial describe historical capture, not current availability. Retained body removal yields `missing`
with unknown reason and null chunk; supplied absent/unavailable/expired markers remain distinct even after row
removal. A missing detail identity gives null, not an invented expiry. Null `next_offset` proves only the end of
this retained body/segment, not complete process output/transcript or continuity. Separate statements and calls
are live: insertion/removal can change totals or repeat/skip rows, content can disappear between chunks, and
revocation applies to later requests. There is no snapshot, content lease, permission cache or retraction of
already returned bytes. Safe activity no longer marks removed body rows retained.

The shared **262,144-byte projection** and **1,048,576-byte complete success-response** bounds apply unchanged,
including structured content, its matching text, escaping, protocol identity and actual central metadata. The
schemas bound message arrays to 32 and chunk coordinates/body lengths to existing recording limits. Even a valid
application result can fail the final protocol budget. No truncation, successful-empty substitution, inferred
completeness or partial successful response is used. Invalid arguments use existing safe Tool errors; malformed
projections, Query/storage failures and budget overflow use sanitized internal errors. Redaction-aware diagnostics
must not log raw bodies, Query results or private exception causes. Only separately authorized successful detail
responses may contain protected content. Reads dispatch no mutation Commands/events and open no transaction.

Compatibility against tagged releases: **two additive final public classes**, `GetCapturedDetailTool` and
`ListCapturedMessagesTool`, permit construction with `QueryBus` and calls to their declared handles, not extension
or implementation. The manifest adds both and increases the Application inventory by two. Existing internal
`ExecutionToolSchemas`/`ExecutionToolOutput` gain protected schema/result support; no released public signature,
Tool/HTTP contract, Query policy, database schema/index, dependency, recording or transaction behavior changes.
Never-released Execution additions remain provisional, without migration promises for development snapshots.
The inherited release-wide operation fingerprint reconciliation remains release-owned, not newly certified here.

Package evidence uses trusted recording Commands, the real guarded HTTP composition and shared Queries on
SQLite/MySQL/PostgreSQL. It proves read-only behavior and equivalent safe output, not actual consumer/Agent OS/Pi
qualification, universal capture redaction, production authorization or deployment readiness.
