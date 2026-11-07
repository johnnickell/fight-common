# Define safe observation recording and query guarantees

**Labels:** `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Reusable execution introspection and MCP evolution](../mcp-execution-introspection-map.md)
**Depends on:** [WF-046 — Choose reusable execution introspection outcomes and ownership](WF-046-choose-execution-introspection-outcomes-and-ownership.md)

## Question

For the selected first outcome, what minimum safe recording, persistence and paginated CQRS query guarantees are
reusable without confusing observations with authoritative execution state or privileged raw logs?

## Evidence and constraints

Consume [the research](../research/mcp-observability-and-protocol-opportunities.md) and WF-046's accepted CQRS,
summary-first and large-query pagination constraints. Agent OS's current plans are a first-consumer contract, not
permission to copy its entire model or promise a new telemetry platform. John's subsequent rich-detail decision
below establishes a desired capability beyond that inspected consumer baseline; Agent OS alignment is pending.

## Accepted recording guarantees

John approved durable, retry-safe observation recording rather than best-effort logging:

- **Trusted admission:** the consumer identifies the producer and permitted execution/phase. Supplied IDs alone
  grant no access.
- **Safe persistence:** validate and sanitize bounded observation data before storing it.
- **Acknowledgment after commit:** success means the observation is persisted, not necessarily reflected in every
  summary projection yet.
- **Retry safety:** within the defined deduplication window, the same producer-scoped observation identity and
  content produce no duplicate. Reusing that identity with different content produces a conflict, not an overwrite.
- **Honest failures:** storage failures are explicit. A timeout can mean an unknown outcome; retrying with the
  same identity safely resolves it within the supported retry window.
- **Separate execution authority:** observations neither advance Workflow state nor certify success. Consumers
  decide whether observation failures interrupt execution; mandatory audit requirements retain their own stronger
  rules.

The accepted retry guarantee lasts while the necessary identity/content-comparison records remain stored; it is
not an indefinite promise after consumer deletion. These guarantees do not promise exactly-once delivery or
complete capture of events that never reached storage. Concrete adapter design remains implementation work.

## Accepted rich-detail browsing boundary

John explicitly wants the level of inspectable detail available in the historical SoftwareFactory: captured
messages, tool calls with arguments/results, and raw process output. He approved separate permissions for deeper
details and does not require reasoning capture or complete capture of all output.

- Keep the sanitized observation ledger and summary/activity Queries separate from protected captured content.
  Timeline entries may link to deeper details without copying their bodies into broadly accessible summaries,
  history/search projections or ordinary MCP responses.
- Support protected human browsing of the captured content through separately authorized, bounded CQRS detail
  Queries, paginated where practical. Common supplies reusable mechanics; the consumer owns permission policy,
  capture composition and UI under WF-046's ownership split.
- Access to the timeline, possession of a detail reference or human access does not automatically grant an Agent
  access to deeper content. Exact permission granularity and any Agent detail access remain consumer policy.
- This is partial capture of available runtime content, not a promise of complete transcripts, every output byte,
  or access to unexposed model reasoning. Capture gaps and unavailable details must remain explicit.
- John will align Agent OS planning with this desired capability. The inspected TICKET-00096 history exclusions,
  protected conversation owners and EPIC-00019 log policy are not silently amended by this Common decision.
  No changes to the Agent OS repository are authorized here.

This accepts the rich-detail capability and separation of permissions, not arbitrary payloads in the safe ledger
or unrestricted secret capture. The typed core, capture/redaction ownership and independent detail storage are
settled below. The accepted per-Command database transaction guarantee below also covers related database-backed
content/link changes; it does not promise atomicity with external files or add consumer retention workflows.

## Accepted browsing guarantees: stateless live offset/limit Queries

John explicitly approved ordinary live pagination after clarifying that the project uses offset/limit, presented
by the UI as `page` and `per_page`, not cursors. This replaces the earlier promise that the complete result stays
unchanged across separate requests. Cursor/filter binding, frozen result sets and stored browsing sessions are
not required.

- Each Query uses its supplied scope, filters, ordering and pagination, with current authorization checked for
  that call. Common does not remember the caller's previous search or manage UI navigation.
- Follow the existing [`Pagination`](../../../src/Domain/Repository/Pagination.php) page/per-page to offset/limit
  model. Page resets after filter/sort changes belong to the browser, not the library.
- Pages and totals may change between requests while data changes. Inserts, removals or changed matching records
  can cause rows to repeat or be skipped across pages. John accepted this normal live-offset behavior;
  deterministic sorting does not promise an unchanged result across requests.
- Summary statistics cover all authorized matches for the supplied selection, not just the current page, with
  freshness shown. Separate summary/detail calls are not promised a shared frozen result or unchanged counts.
- Execution, phase, activity and message collections are paginated; large individual outputs use bounded chunks.
  Finite record/page/response limits remain part of the bounded library contract, with exact values deferred to
  implementation planning.
- Every page/content read rechecks current access. Pagination parameters and references grant no authority.
  Missing or unavailable detail must be reported honestly; do not preserve deleted data for pagination.
- Keeping an already displayed page unchanged, announcing new activity and any **“Load newer events”** button are
  consumer UI choices, not Common workflows. No subscription or particular live transport is selected.

### Accepted default ordering and precision

John prefers fine-grained timestamps with UUID COMB ordering as the tie-breaker, and explicitly authorized
newest-recorded-first if recommended. The selected default is **server recording/receipt timestamp descending,
then full observation UUID COMB descending**, so delayed arrivals are discoverable near the top after refresh.
The timestamp is immutable on retry and is distinct from the producer's reported occurrence time and the commit
acknowledgment. It is not advertised as commit order or causal order.

- Retain reported occurrence time separately when available, with its actual source precision. Target microsecond
  timestamp support through storage and query comparison where the source provides it; do not
  invent precision for coarse producer clocks. Exact adapter precision support must be qualified.
- Keep the full unique observation UUID as a deterministic tie-breaker even at microsecond precision. Concurrent
  producers, coarse clocks and batches can still tie; random UUID suffixes resolve ordering, not chronology.
- Current [`Uuid::comb()`](../../../src/Domain/Value/Identifier/Uuid.php) defaults to a millisecond timestamp in the
  most significant bytes. Its full canonical byte ordering must agree between storage and application comparisons;
  do not assume every database's native GUID comparison matches this ordering.
- Preserve both timestamp meanings; newest-recorded is a presentation default, not a claim that recorded time
  equals occurrence time. Optional occurrence-time sorting is not required by this decision.
- Neither UUID ordering nor a timestamp-only high-water mark establishes a stable visibility boundary across
  concurrent transactions. Sort keys alone cannot deliver the original cross-request stability promise.

## Accepted independent detail storage; consumer-owned retention

John approved separate session, phase and phase-detail tables linked with foreign keys so details can be removed
independently of session/phase summaries. Consumers can then schedule removal or archival under their own policies.

John subsequently corrected the proposed cleanup/archive guarantees: these workflows are not Common's concern.
This clarification supersedes the earlier suggestion that Common supply retention orchestration or enforce
application retention eligibility.

- **Common:** relational storage separation, referential integrity, ordinary CQRS persistence operations and correct
  bounded reads when details are absent. This is a logical storage direction, not an exact three-table schema,
  migration or new authoritative Workflow model. It does not require every content byte to live in a database row.
- **Consumer:** retention periods, eligibility, holds, cleanup jobs, job batching/recovery, archival, archive
  verification and coordination with the consumer's other stores. None is a Common retention engine or archive
  workflow requirement, and none blocks this decision's library contract.
- Session/phase history can outlive protected details. Reads must not fabricate content or expose dangling links.
  Where the consumer supplies an expired-content marker, preserve that distinction from never-captured or otherwise
  unavailable content; Common cannot infer a retention reason from an absent row alone.
- Existing CQRS and per-operation persistence guarantees remain applicable. Their scope does not extend to
  guaranteeing the outcome of a consumer's multi-step maintenance job or indefinite deduplication after records
  have been deliberately removed.

### Consumer planning note, not a Common rule

John agreed with terminal session completion time as the default consumer retention anchor, excluding active
sessions and applicable holds, with cutoff and terminal status supplied by the consumer. This is retained as advice
for consumer planning only. Common does not own or enforce that eligibility policy. The later proposed cleanup
safety checklist was not approved as library scope and is withdrawn from this decision.

## Accepted typed core and consumer extensions

John approved typed common fields plus bounded, namespaced consumer extension attributes, adding that each phase
must carry the identity of the Agent doing the work. This is neither an arbitrary JSON log nor an Agent OS-specific
schema.

| Record | Common fields |
| --- | --- |
| Session | Identity, consumer scope/reference, observed timing and reported outcome |
| Phase | Identity, session/parent relationship, performing Agent identity, name, attempt and observed timing/outcome |
| Activity | Identity, phase relationship, producer, occurrence/recorded timestamps, event kind and safe summary |
| Protected detail | Linked message, tool arguments/result or process output, with content type and capture-completeness information |

- Performing Agent identity is a first-class phase field, not merely an extension attribute or model name. The
  consumer supplies the identity through trusted recording composition; Common does not create an Agent directory
  or treat that reference as an authentication/authorization grant. The Agent doing the work and the instrumentation
  producer recording it are distinct concepts.
- Measured usage carries explicit units and provenance. Missing measurements do not become zero; unknown, partial
  and unavailable information remains explicit.
- Consumers define application vocabulary. Common may record a phase named `review` without owning the meaning of
  review acceptance or enforcing the consumer's Workflow transitions.
- Bounded, namespaced extensions accommodate consumer-specific metadata without changing the core schema. They
  must not become a back door for raw content into summaries or safe observation records.
- Exact class names, columns and numeric limits belong in implementation planning, not this decision interview.
  The previously accepted timestamp, ordering, authority and permission distinctions remain unchanged.

## Accepted capture and redaction ownership

John approved the following admission boundary:

- **Consumer:** decides what to capture and redacts sensitive content before submission. Consumer-owned trusted
  composition supplies producer identity, scope and current access decisions; recorded identity fields are not
  credentials or independent authorization.
- **Common:** validates structure and limits, keeps protected content separate from summaries and applies
  consumer-supplied access decisions through the shared CQRS use cases. The existing requirement to check current
  access on every page/content read remains in force.
- Common does not provide a universal secret detector or automatically capture transcripts. Structural validation
  alone must not be represented as proof that arbitrary supplied content is secret-free.

This settles capture/redaction ownership without changing the accepted protected-detail capability or permitting
raw bodies in summary records. Consumer-specific capture policies and Agent OS planning changes remain outside
this decision.

## Accepted OpenTelemetry deferral

John approved leaving OpenTelemetry integration out of the initial capability. Recording, persistence and shared
Dashboard/MCP Queries must not require an OpenTelemetry SDK, service or exporter. Common owns its data model;
it is not frozen to the currently Development-status GenAI semantic conventions.

A separate adapter may be considered later when a consumer has a concrete export requirement. That is a possible
future decision, not an approved exporter, mapping deliverable or prerequisite for this capability.

## Accepted final persistence and isolation guarantees

John approved the final library rules:

- **Versioned data:** version saved structured payloads so later changes cannot silently give old data a different
  meaning. Reject unsupported versions clearly. Exact format/version identifiers remain implementation design.
- **Project separation:** verify that referenced sessions, phases and details belong to the permitted consumer
  project/scope, not merely that their IDs exist. Use the consumer's trusted context and current access decisions.
- **Per-Command database transaction:** save each Command's related database changes together, or save none of
  them. This includes related database-backed content/link changes. It is not a combined transaction with external
  files or the actual work being observed, and does not make observations authoritative Workflow state.
- **Retained-record retry guarantee:** prevent duplicate recording while the necessary identity records remain
  stored, preserving the previously approved same-content deduplication/different-content conflict behavior.
  No indefinite retry guarantee follows after the consumer deletes those records.
- **Freshness and failure:** success follows database commit; summaries may lag with freshness shown. Storage
  failures remain explicit, and unknown outcomes can be retried under the retained-record guarantee.

### Domain events and transaction boundary

John additionally required domain events and explicitly allowed them to fire outside the database transaction.
Include events for the observation capability's facts through the existing event-dispatch contracts, keeping them
distinct from the consumer's authoritative Workflow events and respecting the safe/protected-content boundary.

Post-commit dispatch outside the transaction is permitted. A later dispatch/subscriber failure cannot undo the
committed database changes and must not be represented as a database rollback. Dispatch after commit is not atomic
with persistence and may be lost; this decision adds neither an outbox nor an exactly-once/guaranteed-delivery
requirement. Exact event names, payloads and dispatch/failure handling belong in implementation planning under
[Architecture — Transactions and events](../../../docs/engineering/standards/Architecture.md#transactions-and-events).

## Accepted implementation evidence

John approved demonstrating the library contract through automated tests of the shared Commands/Queries and MCP
adapter. Required coverage includes version handling, project isolation, all-or-nothing related database writes,
retained-record retries/conflicts, current access checks, live offset/limit pagination and protected-detail
separation. Event tests must distinguish committed persistence from later dispatch/subscriber outcomes under the
chosen composition; do not claim delivery atomicity or rollback that the implementation cannot provide.

These are future package acceptance requirements, not tests run during this planning decision. They prove Common
behavior, not a deployed Dashboard, Agent OS runtime or actual MCP-client qualification. Consumer integration
remains separately owned.

## Deferred implementation details

Exact classes, schema, numeric limits, database mechanics, event catalog and adapter composition are subsequent
implementation-planning work within the approved guarantees. They are not unresolved product-scope decisions.
OpenTelemetry integration, retention/archival orchestration, UI navigation and frozen browsing results remain
explicitly excluded as described above.

## Resolution boundary

May specify reusable semantic guarantees, finite constraints and consumer prerequisites after WF-046. Exact PHP
classes, migrations, persistence products, runtime deployment, UI and implementation TASK decomposition remain
subsequent work. No generic mutation Tool may advance Workflow/Review state from telemetry.

## Resolution

Closed following John's approval of the final library rules and addition of domain events permitted outside the
database transaction. The accepted contract covers typed extensible observation records (including performing
Agent identity on phases), protected rich details, consumer-owned capture/redaction/access policy, project isolation,
versioned data, per-Command database atomicity, retained-record retry safety, domain events and summary-first live
offset/limit Queries through shared application handlers and MCP presentation.

The earlier unchanged-result promise is explicitly superseded, not left unresolved. OpenTelemetry integration is
deferred. Retention/archival, UI behavior and actual consumer composition remain consumer-owned. John owns pending
Agent OS planning alignment; no consumer records are amended here.

The map's next decision is [WF-048 — Select additional MCP capabilities from consumer journeys](WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md).
No implementation, EPIC creation, consumer amendment, commit, publication or release is authorized by this closure.
