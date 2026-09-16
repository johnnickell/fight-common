# Fight Common MCP-over-HTTP Grill Handoff

**Source map:** [Fight Common MCP over HTTP Support](../fight-common-mcp-http-support-map.md)
**Status:** EPIC drafted; Wayfinder resolved

**EPIC:** [EPIC-00006: Reusable MCP Streamable HTTP Tool Support](../../epics/00006-EPIC.md)

## Destination and representative consumer experience

Fight Common enables a consuming application to expose explicitly selected existing commands and queries as MCP tools over MCP `2026-07-28` Streamable HTTP, without coupling the application use cases to MCP. The EPIC destination is reusable Fight Common support and the consumer experience it supplies; it is not an Access Control feature.

The representative consumer experience is: a consumer application opts in to Fight Common MCP support, supplies
its routes and authentication composition, explicitly exposes selected pre-existing CQRS use cases, and receives
protocol-faithful MCP responses through its chosen supported framework adapter. Its use cases retain their own
authorization and business policy. Fight Access Control is a likely early consumer context: a local provisioned
agent may call a selected permission-checked tool through a consumer-owned HMAC route; Access Control resolves
the Agent and applies policy; the tool invokes the normal bus and returns a safe View. This is not required proof
for Fight Common. The same Common support must be capable of consumer-configured OAuth/Bearer protection for
interoperable MCP clients.

Fight Common verifies query-backed and mutation-backed behavior through package-owned test fixtures. A real
Access Control mutation may be added when a suitable Agent use case emerges; its absence does not block either
package.

## Accepted boundaries and supporting evidence

| Accepted decision | Evidence | Consequence |
|---|---|---|
| Target MCP `2026-07-28` Streamable HTTP only | [WF-037](../tickets/WF-037-target-the-stateless-mcp-http-profile.md); [official transport specification](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http) | Consumer-selected MCP routes use stateless POST; legacy HTTP+SSE and session compatibility are excluded. |
| OAuth/Bearer is interoperable; HMAC remains available for provisioned machine-to-machine use | [WF-038](../tickets/WF-038-set-the-authentication-interoperability-posture.md); [official authorization specification](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization) | A consumer may offer either or both on separate routes; Fight Common does not choose routes or Agent policy. |
| Fight Common stays policy-free | [WF-039](../tickets/WF-039-define-fight-common-and-consumer-ownership-boundary.md) | Consumers own routes, principals, permissions, authorization-server operation, and selection of exposed tools. Access Control owns Agent/current-authority and permissions. |
| OAuth support is resource-server support, not an authorization server | [WF-043](../tickets/WF-043-define-reusable-oauth-resource-server-support.md) | Shared work may support protected-resource metadata, Bearer extraction/challenges, and validation ports, but not token issuance, client registration, login, consent, or permission policy. |

## Accepted design and implementation evidence

- MCP must use its own JSON-RPC/MCP wire representation. JSend's semantic-then-native-adapter separation is a precedent, not a wire-format or naming requirement.
- Common supplies reusable protocol handling and an explicit tool contract/registry seam. Consumer-specific HTTP responses, Agent-permission policy, and governance are not Common patterns to transplant.
- Prefer protocol-faithful `McpJsonResponse` and `McpStreamResponse` representations over a forced `McpResponseEnvelope` name.
- Accepted EPIC outcome: direct JSON and request-scoped SSE support across every currently supported framework adapter. It requires framework-by-framework feasibility evidence; if an adapter cannot faithfully satisfy it, the scope or exception requires John's explicit decision and cannot be silently narrowed.
- Accepted SSE scope: this means timely, incremental request-scoped SSE delivery (including related progress notifications before the final response), not merely a completed result serialized as one SSE event. The transport model and every supported framework adapter must honor the behavior; buffering and disconnect/cancellation semantics need evidence during implementation.
- A portable PSR response is a likely interoperability baseline, but whether it faithfully represents live streaming depends on the actual emitter/framework capability.
- Tools delegate explicitly to existing consumer command/query buses and safe Views. The accepted named concepts
  and signatures are recorded below; additional public API shapes and registration mechanics remain decomposition
  work.
- Common's verification uses package-owned query-backed and mutation-backed fixtures, focused unit/contract tests,
  and limited adapter integration tests where a framework seam requires them. A small consumer fixture may prove a
  real composition seam without depending on Fight Access Control. Real consumer adoption remains optional
  evidence, not a planning, EPIC, release, or later-development gate.
- Consumer composition is hybrid: routes, firewalls, authenticators, and policy remain explicit consumer configuration; an MCP tool is explicitly opted in by implementing the Common contract, after which supported framework integration may automatically tag/collect it into the registry. No arbitrary Action discovery is proposed.
- Authorization evidence: Symfony's controller-oriented `#[IsGranted]` attribute is not automatically enforced on a discovered MCP tool service, so MCP requires a deliberate integration rather than controller reuse.
- Accepted authorization convention: `McpTool` is a Fight Common contract. Common invokes a request-scoped neutral availability service with tool metadata only; it never receives an Agent or generic principal context. Fight Access Control owns the planned `RequiresAgentPermission` attribute (exact namespace/name deferred) and standard integration service/decorator that resolves its own current Agent, filters discovery, and checks invocation before delegating. The consuming application wires request authentication and runtime composition. The attribute is not implemented in the current Access Control source. The generic MCP Action remains policy-free. Once an existing command or query reaches its bus, it is already authorized.
- Accepted CQRS convention: each consumer `McpTool` explicitly validates MCP arguments, maps them to its existing command or query, invokes the existing bus, and maps safe application output to MCP. Common does not reflectively hydrate use cases or delegate to HTTP Actions.
- Accepted envelope-metadata convention: MCP transport details remain outside command/query payloads. Common supplies a small namespaced baseline for canonical tool and correlation identity plus an opt-in consumer extension hook for scalar, namespaced audit metadata. Existing `CommandFilter` and `QueryFilter` pipelines enrich envelopes without making handlers aware of caller transport, principal, or authorization policy.
- Accepted command-result convention: `CommandBus::execute()` stays `void`. A command-backed tool may return a truthful acknowledgment with data it knows before dispatch (such as a generated identifier), or use an existing follow-up query when genuinely needed; it should prefer one read or write per MCP operation.
- Accepted response convention: `McpTool` returns a protocol-semantic result (exact name deferred), not `ResultSet`, a framework response, raw JSON-RPC, or an envelope. The result may compose safe structured `Arrayable` data and MCP content; the responder creates the MCP wire response and framework adapters emit it.
- Accepted error convention: tools return successful semantic results and throw typed existing application exceptions. The MCP responder centrally distinguishes protocol failures, expected tool-execution errors, and unexpected failures; individual tools do not shape MCP errors.
- Validation evidence: the existing `#[Validation]` attribute is method-targeted, while `SymfonyValidationSubscriber` reflects a controller method and validates request query/body. MCP can reuse the attribute and `ValidationService`, but needs a dedicated selected-tool invocation validator over `tools/call.params.arguments`, not the Symfony controller subscriber.
- Accepted input convention: the Common MCP tool invoker reflects the existing `#[Validation]` attribute on the selected tool invocation method, calls `ValidationService` over exactly `tools/call.params.arguments`, and passes validated `ApplicationData` to the tool. A tool does not receive a framework request, JSend payload, or JSON-RPC identifier.
- Accepted schema convention: every `McpTool` explicitly declares both its MCP input and output JSON Schemas, while `#[Validation]` remains the runtime input guard. Schema and rules need focused consistency/conformance tests. An empty validation-rule list still yields validated `ApplicationData` over the original arguments; it does not prevent a tool from receiving input.
- Accepted metadata-declaration convention: Fight Common supplies a method-level `#[McpToolInfo(name: ..., description: ..., inputSchema: ..., outputSchema: ...)]` attribute as the complete, explicit MCP discovery declaration. The tool metadata resolver reads it beside `#[Validation]` and rejects missing or invalid identity, description, input schema, or output schema during tool composition. It is the MCP opt-in marker; its field names intentionally mirror the MCP tool definition.
- Accepted tool-method convention: `McpTool` follows the Action-style single public method: `handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput`. `McpToolInfo`, validation, and Access Control permission attributes attach to `handle()`. `McpToolOutput` contains semantic protocol data only: its structured value must conform to the declared output schema, and the responder creates the MCP wire response and compatible text representation.
- Accepted progress convention: `McpProgressReporter` is a framework-neutral, request-scoped invocation dependency. It reports numeric/status work progress and exposes cancellation checkpoints; it becomes a no-op when a client did not request progress. It does not carry partial tool-result content: email bodies and other result data belong in the final `McpToolOutput`. Exact reporter convenience-method names remain provisional until the first framework adapter proves them.
- Accepted response-selection convention: a `tools/call` request with an MCP progress token receives a request-scoped SSE response and a live `McpProgressReporter`; without that token, it receives a direct JSON response and a no-op reporter. This keeps MCP tools transport-neutral while preserving timely progress when requested.
- Accepted availability convention: Fight Common supplies a neutral, caller-aware availability/filter boundary for `tools/list` and invocation. Consumers apply their own principal/permission policy through it. MCP `2026-07-28` requires `ttlMs` and `cacheScope` on `tools/list`; Common emits the conservative `0`/`private` defaults, while consumers may override them when their authorization-sensitive discovery result has a safe cache policy. Common does not own scopes, permissions, or cache policy.
- Accepted list convention: Fight Common paginates the authorization-filtered, deterministically ordered `McpToolRegistry`; small registries return a single page without `nextCursor`. Cursor mechanics remain an implementation concern, but must not let a caller reach an unavailable tool.
- Accepted notification scope: the first Tool capability does not advertise `tools.listChanged`. Its `subscriptions/listen` lifecycle and long-lived notification streams are deferred as a later capability; request-scoped tool progress remains in scope.
- Accepted interaction scope: the first Tool capability supports MCP multi-round-trip `input_required` results. A consumer tool may explicitly ask for confirmation or other additional client input; Fight Common supplies the MCP representation and protected retry-state mechanics, but does not decide which operations are destructive or require confirmation.
- Accepted interaction-state convention: Common supplies two protected state modes. Ordinary multi-step input may use integrity-bound stateless state. A confirmation requires a consumer-provided atomic one-time interaction store, preventing replay. In both cases Common binds the state to a neutral caller identity, selected tool, original arguments, and expiry. Existing HMAC request nonce machinery is not repurposed: it is HMAC-authentication-specific and cannot serve OAuth-authenticated MCP callers. The consumer alone decides that an operation is destructive or requires confirmation.
- Accepted interactive-tool convention: ordinary tools retain the Action-like `handle()` method. An interactive-tool subtype adds `resume()` for the retried interaction. Common restores original validated arguments and separately validates `inputResponses` against the schema persisted with the interaction request before calling `resume()`; a confirmation state is atomically consumed first. `McpToolInfo` and initial `#[Validation]` remain on `handle()` and are not duplicated on `resume()`.
- Accepted cancellation convention: Common supports cooperative cancellation only. Closing an SSE request stream marks its reporter cancelled and prevents further response messages. A tool checks cancellation at safe points and stops when practical; Common does not forcefully interrupt application work, roll back, or claim that an already-dispatched command did not complete.
- Accepted invocation-limit convention: MCP composition requires a consumer-configured neutral invocation guard; missing configuration is a composition failure, not an implicit pass-through. The consumer owns its thresholds, caller key, exemptions, backing store, and policy. Common invokes the guard before tool dispatch and maps a denial consistently without owning rate policy.
- Accepted Origin convention: MCP composition requires a consumer-configured `McpOriginPolicy`. Its standard allow-list accepts exact scheme-host-port origins; a native-agent-only consumer chooses an explicit deny-all policy. Missing Origin remains valid, while a supplied Origin must pass policy. This is separate from framework trusted-proxy/forwarded-header configuration, which continues to govern whether host/scheme request data is trustworthy.
- Accepted transport-mirror convention: Common owns complete Streamable-HTTP header/body validation before protocol dispatch: protocol-version, method, tool name, and every declared `x-mcp-header` value. Framework adapters only extract request headers and emit the standardized protocol result; they must not implement divergent subsets of this validation.
- Accepted response convention refinement: Common follows MCP's defined transport, JSON-RPC, and OAuth response behavior wherever the protocol is explicit. A selected tool's validation or business failure remains a complete MCP tool result with `isError: true`. The required pre-dispatch invocation guard returns HTTP `429 Too Many Requests` with a JSON-RPC error body when a request id exists; it does not select or invoke a tool.
- Accepted public-error convention: only deliberately classified public-safe failures expose sanitized actionable detail. Unclassified exceptions become a generic `-32603` internal error and are logged once through redaction-aware diagnostics.
- Accepted concealment convention: after authentication, an unavailable tool is omitted from `tools/list`; direct invocation is indistinguishable from an unknown tool. OAuth authentication and scope failures retain required HTTP `401`/`403` behavior before MCP dispatch.

## SSE feasibility evidence — implementation risk retained

- MCP `2026-07-28` permits a request response to be either one `application/json` object or a request-scoped `text/event-stream`; the SSE stream may contain related notifications before its final response. A one-event final-response stream is therefore protocol-valid, while progress notifications and long-lived subscriptions require genuine incremental delivery. [Streamable HTTP, Sending and Receiving Messages](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http#sending-messages)
- Fight Common's current JSend adapters construct complete bodies: PSR-17 uses `StreamFactoryInterface::createStream()`, Symfony and Laravel construct JSON responses, and CodeIgniter calls `setJSON()`. They establish direct-JSON precedent but do not establish a reusable streaming abstraction.
- Symfony has `EventStreamResponse`, and Laravel exposes `eventStream()` / streamed response support. Their framework APIs are evidence for native progressive SSE paths. [Symfony HttpFoundation](https://symfony.com/doc/current/components/http_foundation.html#streaming-server-sent-events), [Laravel ResponseFactory](https://api.laravel.com/docs/13.x/Illuminate/Routing/ResponseFactory.html)
- Slim's `ResponseEmitter` reads a PSR-7 body in chunks, while CodeIgniter's `ResponseTrait::sendBody()` emits its stored string. PSR-7 represents a stream body but does not standardize server emission, buffering control, cancellation, or an asynchronous producer. The current Common support surface has no dedicated Slim/Yii response adapter, and no first-class CodeIgniter SSE adapter was found. Consequently, a preassembled one-event SSE body appears representable across the existing response styles; timely multi-event/progress delivery across every supported framework is not yet proven.
- MCP recommends `X-Accel-Buffering: no` for SSE and treats a client-closing the stream as cancellation. Those are transport/runtime behaviors, not properties supplied by current JSend response factories. [Streamable HTTP, Receiving Messages and Cancellation](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http#receiving-messages)

## Protocol-surface research — Q19 resolved

The current specification is modular rather than a single all-or-nothing server feature set. Its own overview separates the required base protocol, versioning, and message patterns from server and client capabilities that applications may implement selectively. [MCP base-protocol overview](https://modelcontextprotocol.io/specification/2026-07-28/basic)

| Capability group | What it includes | Relevance to this EPIC |
|---|---|---|
| Base / Streamable HTTP | JSON-RPC validation and errors; per-request metadata and version negotiation; required HTTP metadata-header agreement; Origin protection; direct JSON or request-scoped SSE response | Required foundation for any Fight Common MCP HTTP support. |
| Tool server feature | `tools/list`, `tools/call`, schemas, structured/content results, pagination/caching, and optional tool-list-change notification | The established primary capability: exposes consumer use cases. |
| Resources and Prompts server features | Discovery, reads/renders, templates, subscriptions and change notifications | Separate consumer abstractions; not implied by a tool registry or CQRS-use-case adapter. |
| Client capabilities / MRTR | Elicitation, sampling, and roots supplied by a client; `input_required` result/retry flow | Needed only when a server feature/tool elects to request client input; it changes tool-result and state-handling scope. |
| Cross-cutting utilities | Progress, cancellation, logging, argument completion, subscriptions/listen, tracing, schema dialect and external-reference safeguards | The first EPIC includes the progress, cancellation, and observability behavior required by its Tool flow; other utilities remain separate later capabilities. |
| Extensions | Tasks, Skills, MCP Apps, enterprise extensions | Excluded from first-EPIC delivery without becoming permanent exclusions. |

This means “support MCP faithfully” requires the mandatory base/transport rules and truthful advertisement of only
the capabilities Fight Common actually supplies. Resources, Prompts, Tasks, Apps, Skills, and other extensions
remain possible later capabilities but are not part of the first EPIC.

## Accepted extensibility direction

Fight Common will use a modular capability model rather than a tools-only dispatcher. A consumer-owned MCP route delegates to the reusable protocol handler, which selects a registered capability handler by MCP method. Framework adapters may collect explicit capability services through their native DI mechanisms; this is not a new application message bus and does not expose ordinary Actions automatically.

`McpToolRegistry` remains the focused Tool-capability component: it maps explicitly registered `McpTool` objects to their canonical `McpToolInfo` names and supplies tool discovery/invocation lookup. A larger `McpCapabilityRegistry` (or map; exact name remains provisional) owns capability-level routing and registration, with `McpToolRegistry` as one registered capability. Future Resources, Prompts, Tasks, Apps, Skills, and extension support must use their own focused components rather than expanding `McpToolRegistry` beyond tools.

## Optional-capability evidence — Q20 resolved

| Candidate | Plain-language value | Architectural consequence | Current specification maturity |
|---|---|---|---|
| Resources | A server offers URI-identified contextual data for a host or model to discover and read. This is the substrate proposed for Skills distribution. | Needs a dedicated resource registry/provider contract, URI validation and consumer-owned access policy, safe text/binary serialization, pagination/caching. Templates and change subscriptions are separately optional resource features. | Core server feature. [Resources specification](https://modelcontextprotocol.io/specification/2026-07-28/server/resources) |
| Tasks | A long-running call returns a durable task handle; a client polls, cancels, receives status, and can resume after disconnect. | Requires a durable task store, task lifecycle/state machine, `tasks/get` / `tasks/update` / `tasks/cancel`, authorization on every task operation, and task-to-worker/application-job integration. It is not a substitute for request-scoped SSE. | Experimental (`io.modelcontextprotocol/tasks`). [MCP Tasks overview](https://tasks.extensions.modelcontextprotocol.io/), [extension repository](https://github.com/modelcontextprotocol/ext-tasks) |
| MCP Apps | A capable host renders an interactive server-provided UI (form/chart/video) inline in conversation. | Adds UI-resource/template serving, client capability negotiation, iframe/message security, and app-only tool visibility. It is a server/UI capability, not a replacement for JSON/SSE responders. | Official extension has a stable `2026-01-26` specification; compatibility with our chosen `2026-07-28` core target requires explicit verification. [MCP Apps repository](https://github.com/modelcontextprotocol/ext-apps) |
| Skills over MCP | A server distributes structured `SKILL.md` workflow instructions and related files, such as future fight-* skills, alongside its capabilities. | Built on the core Resources primitive: needs safe skill/resource discovery and read contracts, URI/resource policies, and content/provenance governance—not a new Tool implementation. | Draft / experimental (`io.modelcontextprotocol/skills`). [Skills working group](https://github.com/modelcontextprotocol/ext-skills), [draft SEP-2640](https://github.com/modelcontextprotocol/ext-skills/blob/main/docs/sep-draft-skills-extension.md) |
| OAuth client credentials | Standard machine-to-machine OAuth for noninteractive clients. | Requires an authorization server to issue tokens and manage client credentials; Common's accepted resource-server support can validate the resulting Bearer tokens, but must not become the issuer/registration system. | Draft authorization extension. [draft specification](https://github.com/modelcontextprotocol/ext-auth/blob/main/specification/draft/oauth-client-credentials.mdx) |
| Enterprise-managed authorization | An enterprise SSO/IdP can obtain MCP access tokens without each user manually connecting every server. | Depends on an authorization server and enterprise IdP token exchange; it is outside Common's policy-free resource-server boundary unless a future consumer supplies those systems. | Stable authorization extension. [specification](https://github.com/modelcontextprotocol/ext-auth/blob/main/specification/stable/enterprise-managed-authorization.mdx) |

Tasks and Skills are directly interesting for Fight Common's future use cases: Tasks could bridge long-running consumer work, while Skills could distribute future fight-* skill directories. Neither is mature enough to silently become a first-EPIC implementation obligation. The modular capability registry keeps both possible without precommitting to them.

**Accepted first-EPIC capability boundary:** deliver the shared MCP protocol foundation and the complete Tool capability only. Resources are not a partial first-EPIC feature; they remain the leading candidate for the next capability EPIC because they are the substrate for future Skills. Re-evaluate the next capability after Tool delivery based on the highest-value / highest-lift evidence then available.

**Core protocol evidence:** MCP `2026-07-28` servers must implement `server/discover`; a client may call it to learn supported versions/capabilities before other requests, but may also negotiate inline through per-request metadata. The response must advertise the actual capabilities the server supplies. This is foundation behavior, not an additional business capability. [MCP schema](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/main/schema/2026-07-28/schema.ts), [2026-07-28 changelog](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/main/docs/specification/2026-07-28/changelog.mdx)

**Accepted discovery composition:** the reusable protocol handler implements `server/discover`; the consumer supplies required `McpServerInfo` to `McpCapabilityRegistry`, which derives advertised capability metadata from its registered capabilities. This makes the advertised identity truthful to the consumer application while retaining Common's protocol implementation. First-EPIC documentation includes framework-wiring examples.

## Closed proof disposition

Fight Common proves the reusable boundary with package-owned test fixtures. No Fight Access Control query or
mutation is required. Access Control can implement a real Agent mutation when a suitable business example is
found, then adopt the shared integration independently.

## Deferred for later decomposition unless grilling exposes a blocker

- Exact PHP class and interface names beyond the accepted named concepts and signatures; no `Envelope` name is assumed.
- Tool-registration API and command/query argument-mapping mechanics.
- Detailed error-code taxonomy, correlation/logging fields, cancellation implementation, and every failure example.
- Framework-native streaming implementation strategy and conformance tests, except where necessary to decide the EPIC's uniform-support acceptance boundary.
- Access Control's Agent permission strings, policy rules, route names, and authorization-server deployment.
