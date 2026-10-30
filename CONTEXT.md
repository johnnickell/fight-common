# Project Context and Ubiquitous Language

This project is a shared PHP library for applications using Hexagonal Architecture and CQRS. It supplies domain primitives, application ports, and optional infrastructure adapters without defining the business language of any one consuming application.

This file records how terms are used inside this library. Public APIs, documentation, tickets, and ADRs should use these meanings consistently.

## Architectural language

| Term | Meaning in Fight Common |
| --- | --- |
| **Domain** | Framework-free business concepts and reusable domain primitives. Domain code does not depend on Application or Adapter code. |
| **Application** | Use-case coordination and ports. It may depend on Domain contracts, PHP internals, neutral PSR contracts, and the documented `CronExpression` utility exception, but not on concrete adapters or framework implementations. |
| **Port** | An interface owned by an inward layer that describes a capability required from the outside. Examples include `CommandBus`, `UnitOfWork`, `HttpClient`, and `FileStorage`. |
| **Package/consumer ownership** | Common owns reusable capabilities and their promised behavior; consumers own application policy, permissions, routes, use-case composition and operations unless an explicit package contract says otherwise. Required public compatibility aliases and reusable framework integrations remain supported. |
| **Adapter** | An outer runtime integration that makes a third-party library, framework extension point, or infrastructure capability convenient for consumers. An Adapter often implements an inward-owned port, but it may instead translate a framework-facing contract or package external behavior behind a Fight Common API. |
| **Service container** | A registry and composition mechanism that constructs and connects application services. Fight Common's portable PSR-11 implementation belongs to Application; framework compiler passes, providers, and service factories are Adapter integrations for the same capability. |
| **Interoperability standard adapter** | A provider-neutral Adapter that translates between a Fight capability and an accepted external standard without losing observable behavior. Direct use of a standard interface needs no wrapper, and a semantic mismatch is documented rather than disguised as support. |
| **Queued Fight message delivery** | At-least-once transport of one complete Fight command or event message to a neutral handler that invokes the synchronous Fight bus or dispatcher. Post-commit submission prevents work for a rolled-back transaction but is not an atomic outbox. |
| **Framework support evidence** | The combination of library-owned adapter conformance evidence and a booted installed-package journey in the corresponding starter repository. The former proves translation behavior; the latter proves native registration and lifecycle. The approved progressive MCP exception in ADR 0024 accepts package evidence for Common completion without a starter/consumer confirmation gate; it does not claim consumer runtime qualification. |
| **Capability registrar** | An Adapter class that registers one bounded set of Fight services in a concrete service container. A registrar uses explicit service and handler maps; project-owned collaborators are constructed through the container's existing factories. |
| **Service-container adapter** | Framework-facing wiring that registers one bounded Fight Common capability with a native service container. Symfony compiler passes, Laravel service providers, Yii configuration providers, and CodeIgniter service factories use different extension mechanisms but share this responsibility. |
| **Standard** | Development-time policy published under `src/Standards`. Standards are orthogonal to the runtime Domain, Application, and Adapter dependency chain; they depend only on the tooling contracts they extend, and runtime layers do not depend on them. |
| **Service** | An application-level coordinator or named registry. A service does not make an infrastructure implementation part of the domain. |
| **Dependency direction** | Runtime dependencies point inward: Adapter -> Application -> Domain. Domain uses only Domain and PHP internals. Application additionally permits neutral PSR contracts and the documented `CronExpression` utility exception. Standards remain outside that chain and cannot depend on a runtime layer. |

## Shared domain primitives

| Term | Meaning in Fight Common |
| --- | --- |
| **Value** | An immutable description or measurement compared by value rather than identity. Values validate themselves and are replaced rather than mutated. |
| **Value object** | The base implementation of `Value`, providing value equality, hashing, string conversion, and JSON representation. |
| **JSON snapshot** | Opt-in `JsonObject::fromSnapshot()` captures mixed JSON-encodable input without retaining caller-owned objects or executable serializers; `fromSnapshotString()` explicitly reconstructs snapshots. Each data/JSON output is a fresh object-mode value. Native integers/finite floats retain kinds, snapshot encoding uses shortest round-trip precision and zero-fraction preservation, integer-range and float-overflow text rejection includes overwritten properties/subtrees, and codecs require at most 511 nested containers at depth setting 512. Only presentation options are allowed; equality/hash remain captured-string-based. Consumer serializer effects/throwables are not sandboxed, and outer encoding has its own flags/precision. Legacy factories/helpers and Doctrine text hydration remain unchanged, including shape/float limitations; snapshots are neither redaction, arbitrary deep clones nor automatic persistence adoption. |
| **Strict JSON** | `Domain\Value\Basic\StrictJson` is a bounded immutable JSON value. Object nodes remain typed values, lists remain arrays, and scalars preserve their types; named factories own plain-data/Unicode/depth safety, including the shared codec ceiling of 511 nested containers (scalar node depth 511, container node depth 510), and property navigation and replacement do not expose mutable generic objects. JSON encoding alone emits fresh PHP object representations. Existing `JsonObject` remains unchanged. |
| **Email address** | `EmailAddress` validates with the existing lexical `Validate::isEmail` contract. Parts split after the complete accepted local part, preserving quotes/escape bytes and removing only domain-literal brackets (retaining `IPv6:`). Original string/JSON, case-sensitive concrete-type equality/hash and canonical/legacy Doctrine stored text stay unchanged; `canonical()` separately lowercases the whole address, not its stored/login identity. No DNS, reachability, ownership, authorization or contact permission is implied; consumers own those policies. |
| **E.164 phone number** | Final readonly `E164PhoneNumber::fromString()` accepts exactly a plus, nonzero first ASCII digit and one through fifteen total ASCII digits (`\A\+[1-9][0-9]{0,14}\z`). No arbitrary minimum, trimming, national conversion, assignment/provider lookup or extensions. Invalid strings raise DomainException; exact text owns same-concrete-type equality/hash/string/JSON round trips. Explicit optional `toString()` adoption leaves SMS constructor/factory/service strings, short codes, sender IDs, non-E.164 addresses and body/media/provider translation unchanged; construction never sends. Lexical validity is not assignment, reachability, SMS capability, consent or permission. Consumers own country/provider/sender/contact policy; no Identifier, ordering, persistence or consumer-extension contract is added. |
| **IP address** | Abstract readonly `IpAddress::fromString()` selects final IPv4/IPv6 values; concrete factories reject the other family with DomainException. Bare literals are not trimmed or interpreted as hostnames/CIDR/brackets/ports/zones. IPv4 is dotted decimal; IPv6 is lowercase minimal hexadecimal with the first longest multi-zero run compressed, including all-hex mapped suffixes. Equivalent spellings share concrete-type equality/hash/string/JSON and reconstruct stably; mapped IPv6 stays unequal to IPv4. Valid private/loopback/other categories imply no reachability, trusted origin, routing permission or SSRF protection. Existing Validate/Application rules remain unchanged; no automatic adoption, Identifier/ordering, extension or persistence contract is introduced. Consumers own connection context and network policy. |
| **Calendar Date / local Time** | Final readonly Domain/Value/DateTime values validate proleptic Gregorian years 0001–9999 and exact local hour/minute/second/microsecond bounds. Named fromParts/fromString/fromNative factories require canonical ASCII YYYY-MM-DD and HH:MM:SS.ffffff strings, reject rollover/24:00/leap seconds with DomainException, and copy the supplied timestamp's own local components without ambient-zone conversion or retaining mutable input. Same-concrete-type canonical equality/hash/string/JSON and -1/0/1 chronological comparison agree; wrong-type comparison rejects. Neither value identifies an instant or timezone; existing native timestamp/Timezone contracts remain unchanged. Consumers own calendar/scheduling/access policy; no arithmetic, persistence or automatic adoption is introduced. |
| **Strict zoned DateTime** | Final readonly Domain/Value/DateTime/DateTime combines validated Date/Time with an explicit Timezone. Fresh local construction rejects DST gaps and requires a real offset-in-seconds for overlaps; native/instant import and versioned string/JSON reconstruction retain exact Unix seconds, microseconds and the native timezone identifier, including either fold. Equality/hash use instant plus identifier; compareTo orders instant first then identifier, while compareInstantTo/isSameInstantAs ignore the zone for later ranges. inTimezone preserves the instant and reinterpretInTimezone preserves local components with fresh strict validation. Supported local years are 0001–9999; conversions outside that range reject. It is opt-in, not a replacement for existing native timestamps, audit bounds, Timezone identity, scheduling or consumer policy. No frozen timezone-rule version, automatic persistence, arithmetic or authorization is promised. |
| **Inclusive calendar DateRange** | Final readonly Domain/Value/DateTime/DateRange holds ordered validated Date endpoints within proleptic Gregorian years 0001–9999. fromDates/fromString reject reversed or malformed endpoints with DomainException; equal dates form a one-date range. contains includes both endpoints and every intervening calendar date without steps, timezone, midnight or instant conversion. start/end expose immutable Dates; same-concrete-type equality/hash/string/JSON use the exact versioned endpoint pair. This opt-in calendar interval does not replace native timestamp or inclusive audit boundaries, supply interval length/iteration, persistence mapping, scheduling or access permission. Consumers own calendar policy and adoption; instant intervals are distinct. |
| **Half-open InstantRange** | Final readonly Domain/Value/DateTime/InstantRange holds ordered strict zoned DateTime endpoints and tests `[start, end)` by exact instant, not zone identity. Equal instants form an empty interval even across zones; reversed instants reject. Equality/hash use exact endpoint Unix seconds and microseconds, independent of zone labels; versioned string/JSON retain the endpoint timezone context, so equal ranges can have different representations. Factory and reconstructed getters preserve that immutable context and exact instants, even at UTC-year bounds. This opt-in value does not change native timestamps or inclusive AuditRepository/DoctrineAuditRepository `getBetween()` bounds (`>=` and `<=`), imply access permission or supply Duration/length, calendar semantics, persistence, scheduling or automatic adoption. |
| **Exact Decimal** | Final readonly Domain/Value/Basic/Decimal uses string-only ASCII signed plain decimal construction with at most 4,096 input bytes, 1,024 normalized coefficient digits and scale 0–1,024. Canonical numeric equality/hash/order/string/JSON collapse redundant zeros, leading zeros and negative zero. Exact add/subtract/multiply/finite divide reject unsupported results; round and divideRounded require explicit output scale 0–1,024 and native RoundingMode (all eight cases), with one exact remainder-based decision. The baseline is plain PHP without BCMath/GMP/float arithmetic, ambient precision or process-wide scale changes. It is optional exact arithmetic, not a Currency, Money, float-recovery, persistence or financial-policy contract; consumers own adoption and permitted rounding. |
| **Bounded Currency** | Final readonly `Domain\Value\Money\Currency` recognizes exactly 49 curated uppercase ASCII codes with package-owned accounting exponents: CLP/JPY/KRW/VND at 0, BHD/KWD at 3 and AED/ARS/AUD/BDT/BRL/CAD/CHF/CNY/COP/CZK/DKK/EGP/EUR/GBP/HKD/HUF/IDR/ILS/INR/KES/LKR/MAD/MXN/MYR/NGN/NOK/NZD/PEN/PHP/PKR/PLN/QAR/RON/RUB/SAR/SEK/SGD/THB/TRY/TWD/UAH/USD/ZAR at 2. Each starts at retained definition v1; new codes are deliberate additions, changed scales need a new version and old definitions remain readable. Code alone owns Currency equality/hash/string/JSON identity, while Money must capture and check the definition and scale separately. Unsupported/malformed codes and unknown versions reject without default. This is an opt-in bounded accounting unit, not a complete currency catalog, cash policy, exchange rate, permission to transact or automatic persistence adoption. Consumers own eligibility and financial policy. |
| **Exact Money** | Final readonly `Domain\Value\Money\Money` captures supported Currency code, definition version and accounting exponent with signed native integer minor units, including minimum and zero. Exact Decimal major conversion and scalar arithmetic reject fractional minor results unless a native RoundingMode is explicitly selected for one final-boundary decision; compatible code/scale addition, subtraction and comparison reject mismatch and overflow. Largest-remainder weighted allocation works on exact magnitudes with original-index ties, restores sign and preserves total at native limits. Versioned string/JSON saves exact textual units, code, scale and retained definition; readers reject unknown/tampered context rather than reinterpret current definitions. Numeric equality/hash ignore metadata-only revisions but saved text preserves provenance. This optional value has no float backend, payment/ledger effect, persistence mapping, exchange rate, cash/eligibility/permission policy or automatic adoption; consumers own those decisions. |
| **Fixed elapsed Duration** | Final readonly Domain/Value/DateTime/Duration stores signed native integer microseconds, including PHP_INT_MIN/zero/negative values without a new platform floor. Named microsecond/millisecond/second factories scale with prechecked bounds; exact coarser-unit conversions reject signed fractions. Checked add/subtract/negate return replacements and reject unrepresentable results, including minimum negation, without float intermediates. Canonical decimal microseconds plus `us` owns same-type equality/hash/string/JSON and strict pre-cast text reconstruction; compareTo returns numeric -1/0/1. It is not calendar-month/year, wall-clock/DST, instant or DateTime/range arithmetic. Existing native timestamps, scheduler/timeout/TTL contracts remain unchanged; consumers own explicit optional adoption, positivity, limits and scheduling policy. No clock/I/O, Identifier, extension or persistence contract is introduced. |
| **WeekDay** | Native integer-backed enum matching PHP's w convention, SUNDAY=0 through SATURDAY=6, returned by Date::weekDay() under the proleptic Gregorian calendar. Date delegates weekday formatting to PHP with explicit internal UTC, independent of ambient zones/skipped local dates, without exposing an instant or timestamp conversion. Native cases/from/tryFrom/JSON semantics apply, including invalid-integer ValueError/null and strict-call TypeError. It is not a ValueObject or Identifier, supplies no ISO Sunday-seven alias and grants no scheduling authority. |
| **Identifier** | A value that identifies a domain concept and can be compared and ordered. |
| **Stream identifier** | Final readonly `StreamId` implements Identifier for the exact nonempty aggregate-name/identifier byte tuple. Value equality/hash and bytewise name-first ordering agree; canonical `stream:v1:<base64(name)>:<base64(id)>` string/JSON reconstructs without normalization. Strict canonical standard Base64 and nonempty components are required; invalid frames and wrong-type comparison raise DomainException. Stored component fields and explicit diagnostics remain unchanged. TASK-00130 records John's scoped minor exception for automatic collection deduplication/equivalent-key replacement and JSON changing from `{}` to a string; this is not backward-compatible instance behavior. Old standalone PHP objects remain readable, but old serialized hash buckets must be rebuilt. Identity/encoding confers neither authority nor secrecy. |
| **Unique ID** | A UUID-backed `Identifier` with named generation and reconstruction methods. `MessageId` and `AuditEntryId` are examples. |
| **Specification** | A composable business rule evaluated with `isSatisfiedBy()`. Specifications combine through `and()`, `or()`, and `not()`. |
| **Collection** | A typed group of values. Lists preserve sequence, sets enforce uniqueness, tables associate typed keys and values, and sorted variants require comparison rules. |
| **Comparator** | A strategy that establishes ordering between values for sorted collections. |
| **Serializable** | An object that can describe and reconstruct its state. Serialization is representation, not persistence. |

## Messaging and CQRS

| Term | Meaning in Fight Common |
| --- | --- |
| **Payload** | The domain content carried by a message. It has an array representation and can be reconstructed from that representation. |
| **Message** | An effectively immutable technical envelope containing a `MessageId`, message type, timestamp, payload type, payload, and an isolated metadata snapshot. Standalone `Meta` values remain mutable, but a message copies metadata at its boundary and returns a copy from `meta()`. Metadata carries application, transport, audit, or observability context and is not domain state. |
| **Message identity** | A `MessageId` is the idempotency identity of one event occurrence and its payload. `withMeta()` and `mergeMeta()` derive a same-ID envelope with a different isolated metadata snapshot without creating a new occurrence or changing message equality. |
| **Metadata (`Meta`)** | Scalar, nested contextual data attached to a message without changing its payload. |
| **Command** | An imperative payload requesting a state-changing action. Commands do not return application data. |
| **Command message** | The message envelope carrying a command. |
| **Command bus** | The application port that wraps or dispatches commands to exactly one routed handler. |
| **Command handler** | The application component registered for and responsible for one command type. |
| **Command filter** | Pipeline behavior surrounding command handling, such as metrics or cross-cutting policy. |
| **Query** | A payload requesting information without expressing a state change. |
| **Query message** | The message envelope carrying a query. |
| **Query bus** | The application port that fetches or dispatches a query and returns its result. |
| **Query handler** | The application component registered for and responsible for one query type. |
| **Query filter** | Pipeline behavior surrounding query handling. |
| **Event** | A domain payload describing something that happened. It is stated in past tense and is not an instruction. |
| **Event message** | The technical message envelope carrying an event. Its timestamp records envelope creation, not necessarily the domain occurrence time; domain-significant time belongs in the event payload. A domain event and its event message are related but not interchangeable terms. |
| **Event dispatcher** | The application port that triggers or dispatches event messages to zero or more handlers. A synchronous dispatcher attempts every matching handler even when one fails, then reports the collected failures after fan-out completes. Event-specific handlers form the first priority-ordered phase; `AllEvents` handlers form a second priority-ordered phase. |
| **Event subscriber** | A component declaring the event types it observes and the handlers and priorities used for them. Idempotence is encouraged for subscribers that receive published stored events but is not required by the general subscriber contract. |

Commands use `execute()` for payloads and `dispatch()` for messages. Queries use `fetch()` for payloads and `dispatch()` for messages. Events use `trigger()` for payloads and `dispatch()` for messages.

## Persistence and validation

| Term | Meaning in Fight Common |
| --- | --- |
| **Repository** | A domain-oriented collection boundary for retrieving and persisting domain records without exposing infrastructure details. |
| **Pagination** | An immutable request for page, page size, offset, limit, and normalized field ordering. Opt-in `Pagination::strict()` resolves null defaults, requires positive bounds and ASC/DESC strings, and rejects unrepresentable integer offsets with DomainException before multiplication. Native PHP parameter typing remains distinct from value validation. The deprecated legacy constructor retains permissive defaults/normalization without runtime warnings during minor migration; its negative-bound/overflow limitations are not repaired. Consumers own field eligibility, SQL safety, authorization and provider/query policy. |
| **Result set** | A typed page of records plus page and total-count metadata. |
| **Unit of work** | The application transaction boundary used to commit work and run a callable transactionally. A closed unit of work is terminally unable to accept another operation; a rolled-back transaction does not by itself mean the unit of work is closed. |
| **Required audit record** | Secret-free durable evidence required for a classified sensitive command to succeed. The protected mutation and its required audit record commit atomically; a later projection of that evidence is derived state rather than the authoritative audit record. |
| **Input data** | Untrusted field data entering validation. |
| **Validation rule** | A reusable predicate such as length, range, format, type, or required-field behavior. |
| **Validator** | A component that applies a specification to a validation context and may add field errors. |
| **Validation coordinator** | The component that runs one invocation's queued validators in a fresh context and produces one `ValidationResult`. It discards the queue on normal and exceptional exits; explicit `resetValidators()` discards pending work without execution. ValidationService also clears the same supplied coordinator when input/rule preparation fails. Sequential reuse retains original throwables and ordinary results, not stale rules/errors; it promises neither reentrancy nor rollback of consumer validator effects, and confers no authorization or public-safe diagnostics. |
| **Application failure** | An opt-in transport-neutral `Application\Error\Exception\ApplicationException` category explicitly chosen by a consumer use case. Eleven extensible categories describe authentication required, permission denied, intentional resource absence/gone, state conflict, failed precondition, generic invalid input, rule violation, rate limiting, unexpected server error and temporary service unavailability. Ordinary message/code/cause are private diagnostics; only rate limiting and service unavailability carry optional nonnegative integer retry-delay seconds, not retry permission or HTTP metadata. Existing structured ValidationException and Domain failures stay separate; no automatic mapping, safe HTTP handling, transaction/rollback promise or mandatory migration follows from the new types. |
| **Validation result** | Either passed application data or failed error data; data and errors are mutually exclusive states. |
| **Application data** | Validated data safe for application use. |
| **Error data** | Structured field errors produced by failed validation. |

## Supporting capability language

These are distinct capabilities. Avoid using their names interchangeably.

| Capability | Canonical terms |
| --- | --- |
| **Observability** | A **health check** produces one `HealthResult`; a **health report** aggregates results and their worst status. A **metric** is a measured counter, gauge, or timing. An **audit entry** records actor, action, timestamp, and context. |
| **Authentication and security** | An **authenticator** validates a request. A **request service** signs one. A **nonce** is a one-time replay-prevention value and is consumed atomically. Password hashing and token encoding are separate security ports. |
| **OAuth resource server** | A policy-free boundary around a consumer-owned protected resource. Common renders configured metadata, extracts one header-only Bearer credential, supplies complete validation requirements to a consumer validator, independently checks normalized claims and effective scopes, and hands only validated claims to consumer authentication composition before dispatch. Consumers own cryptography, trusted issuer-bound keys and rotation, scope hierarchies, principal resolution, routes and authorization. It is neither token issuance nor signature-only JWT decoding. |
| **OAuth effective scopes** | A trusted case-sensitive set returned by the consumer validator after intrinsic token validation and any issuer-defined hierarchy expansion. Common checks every current-operation scope before handoff; missing scope is 403, not invalid-token 401 or a business permission decision. |
| **Scheduler** | A **job** has a name, schedule, executable command or callable, and runtime policy. The **scheduler** determines which jobs are due and prevents overlapping execution with a lock. |
| **Process** | A **process** is an immutable execution descriptor. A **process runner** queues and executes processes with concurrency and failure behavior. A scheduled job may invoke a process, but the terms are not synonyms. |
| **File storage** | Backend-neutral file content operations, potentially local or remote, exposed through `FileStorage`. |
| **Filesystem** | Operations on the local operating-system filesystem, exposed through `Filesystem`. |
| **File transfer** | Sending, retrieving, and listing remote resources through a named `FileTransport`. A **resource** describes a remote file-system entry. |
| **Transport services** | HTTP clients, mail transports, SMS transports, and socket publishers are outbound ports with concrete adapters. A **null adapter** intentionally performs no external delivery; a **logging adapter** records and delegates or substitutes an operation as documented. |
| **Safe HTTP error path** | Explicit PSR-15 middleware, native Symfony exception listener or opt-in Laravel exception handler maps declared Application failures, structured validation and supported HTTP-only outcomes through one fixed-public JSend policy. Consumer challenge, public-data projector and redaction-aware diagnostic sink remain explicit; missing required metadata or failed projection/reporting falls back to generic 500. Symfony keeps JSON/XHR negotiation, native responses and framework-delegated HTML; its JSON listener reports before the framework logger. Laravel's handler owns report-before-render even for normally ignored failures, returns native safe JSON for expectsJson requests and delegates non-JSON rendering to Laravel. Common structured validation stays 400; native Laravel validation stays 422 without implicitly exposing either field map. Legacy PSR-15/Symfony/Laravel outputs remain unchanged and potentially unsafe. No authorization, rollback, downstream starter or consumer runtime qualification is implied. |
| **JSend envelope** | A framework-neutral semantic representation of one JSend `success`, `fail`, or `error` result. It owns JSend data, message, code, and serialization but not HTTP status, headers, or a framework-native response. A controller chooses the HTTP outcome and a native response adapter converts the envelope. |
| **Registry service** | A service such as `StorageService` or `FileTransferService` that selects a named port implementation. It is not itself the storage or transport backend. |
| **MCP semantic responder** | An Application-level service that decodes one MCP JSON-RPC request, validates shared protocol rules, runs `server/discover` or one registered capability, and returns one semantic JSON-RPC response. Capabilities may opt into `McpMetadataAwareCapability` to receive the responder's actual central result metadata per request for pre-disclosure serving budgets; ordinary `McpCapability` handling remains unchanged. The metadata is not an authorization context, and final identity merging remains with the responder. It is not an HTTP endpoint, authenticator, authorizer, or framework response factory. |
| **Guarded MCP HTTP endpoint** | Canonical `Adapter\Mcp\Http\McpRequestHandler` is a PSR-15 handler on a consumer-owned route that validates a supplied Origin, POST negotiation, the complete transport mirror set, and a consumer invocation guard before semantic dispatch. It returns direct JSON through PSR-17 factories by default; explicit progressive composition selects a request-scoped SSE body for token-bearing tools/call requests after the same safeguards. It records unexpected failures through consumer-supplied redaction-aware diagnostics and introduces no route, principal, authorization policy or session. |
| **MCP request bounds** | `McpRequestLimits` shares a finite encoded-byte budget (default 1 MiB; configurable 1–64 MiB) and PHP JSON parsing depth (default 512; configurable 1–512) between the decoder and guarded endpoint. HTTP reads use bounded chunks and one-byte lookahead before decoding; actual bytes, not declared lengths, govern acceptance. Excess HTTP input is empty 413; excessive depth/malformed JSON keeps the safe parse error, while broken/no-progress streams use sanitized central diagnostics. Direct string decoding rejects oversized inputs before parsing but cannot undo prior allocation. Consumers retain upstream buffering, admission, timeouts, authentication and complete-body stream responsibility. This deliberately narrows oversized-input acceptance without changing in-budget call shapes or policy ownership. |
| **MCP invocation guard** | A required consumer-implemented decision receiving only the validated method and optional standard operation name. Consumers own caller identity, keys, thresholds, exemptions and storage. Denial is HTTP 429 with Common-defined JSON-RPC code `429`, message `Invocation limit exceeded.`, and no diagnostic data; that code is not assigned by MCP. |
| **MCP transport mirror** | A case-insensitive HTTP field name whose case-sensitive decoded value must agree with its exact body property path. Common validates protocol version, method, applicable name/URI, and every registered custom declaration before dispatch, including Base64 UTF-8, safe integer, Boolean, absent/null and duplicate-value behavior. Tool registration compiles statically reachable primitive input-schema annotations; the guarded HTTP boundary selects only the available named Tool's declarations before custom-header comparison. Invocation rechecks availability without caching authority. |
| **MCP capability** | An explicit consumer-registered handler that owns a bounded set of MCP methods, capability advertisement, outer-parameter validation, and semantic result construction. Standard methods must advertise their matching standard capability family, and an advertised standard family requires its mandatory anchor method; discovery definitions and server identity contain JSON-safe Unicode values at composition. Subscription-driven flags remain false by default; explicit request-scoped `McpSubscriptions` registry composition derives enabled Tool/Resource and explicitly optional Prompt flags from its actual guarded live path and requires the corresponding serving families. Arbitrary consumer-declared true claims still reject. Custom extensions remain explicit. The capability registry does not automatically expose ordinary Actions or CQRS handlers. |
| **MCP Prompt serving** | Explicit `Application\\Mcp\\Prompt\\McpPromptDiscovery` snapshots a bounded catalog of immutable `McpPromptInfo` metadata from consumer `McpPrompt` providers without constructing bodies. Fresh `McpPromptAvailability` filters before bytewise list pagination and independently before direct get; declared bounded string arguments validate before only the selected provider runs. Complete immutable `McpPromptOutput` accepts user/assistant text or validated Resource-link messages, never embedded bodies, interaction or execution. Authenticated stateless cursors bind only visible metadata and serving parameters; private zero-TTL hints grant no access. Finite definition/catalog/argument/output/result and actual complete encoded-response budgets include current central metadata and envelope escaping. Static content needs no Query; application-backed content uses shared QueryBus authorization. Links trigger no fetch, Skill activation, Tool/model work, Command or event; later Resource reads authorize independently. Consumers own identity, current policy, content and runtime; hosts own message approval/use. See [Prompts](docs/mcp-prompts.md) for APIs, numeric limits, failures, compatibility and guarded HTTP evidence. |
| **MCP Resource metadata provider** | An explicitly composed `Application\Mcp\Resource\McpResourceProvider` yields fresh immutable descriptors in strictly increasing bytewise exact-URI order, without content reads or full-corpus materialization. Common merges streams and rejects duplicate ownership, unsorted/invalid output and exceeded budgets, including hidden descriptors. The discovery port has no content method or URI-to-path/network fallback. Its optional `McpReadableResourceProvider` subtype adds exact metadata lookup and authorized lazy opening on the same provider object without changing existing implementations. |
| **MCP Resource discovery** | One `McpResourceDiscovery` owns `resources/list` and empty complete `resources/templates/list` through the existing guarded endpoint. Current consumer availability filters before pagination. Fixed bounded authenticated cursors bind visible metadata, catalog scope and page size, not a principal or concealed catalog. Empty cursor restarts; only absent `nextCursor` marks completion. Conservative private/zero-TTL defaults and validated finite scan/metadata/page/result limits do not confer authority. Its default composition remains discovery-only; explicitly supplied `McpResourceReadLimits` adds exact `resources/read` on the same providers and availability boundary. Templates remain deferred; separately composed subscriptions match exact currently permitted Resource URIs without reading them. Conformance evidence is scenario-specific. |
| **MCP Resource read** | An opt-in exact all-provider lookup independent of listing membership, followed by fresh availability before opening one selected `McpResourceContent` stream. Conflicting ownership and inconsistent metadata fail; unknown and denied identities share safe invalid parameters. Bounded text preserves UTF-8/CRLF bytes; binary uses Base64; empty files remain one content item. Read-enabled listing and lookup validate the complete worst-case encoded read budget, including the current responder's actual central metadata, before advertising descriptors or opening content. Raw and final encoded guards remain independent, and Common closes returned streams on success/failure. URI identity is never normalized into a storage target; consumers retain storage containment, timeouts and version guarantees. No content/permission cache or authorization-by-URI is introduced. |
| **MCP Skill revision** | A bounded immutable snapshot of an explicitly supplied complete file set, with one root `SKILL.md`, all supporting members and one exact raw-byte SHA-256/size manifest. `McpSkillRevision` validates structure, safe parsed JSON frontmatter and serving budgets once; reads retain original bytes and routine discovery does not rehash the corpus. Consumers own bounded acquisition, completeness of their source export, unique revision addresses, atomic publication and retention. It is not a catalog store or a currently advertised Skills extension. |
| **MCP Skill Resource provider** | `McpSkillResources` contributes validated revision files to the existing Resource capability through optional `McpProtectedResourceProvider`. Common checks provider-owned whole-Skill availability in addition to general Resource policy before disclosure/opening, while concealed metadata still participates in ownership/budget validation. Only selected files receive streams; optional Resource digest verification rejects changed bytes. Neutral complete entries contain no principal or file content; templates/scripts/nested Skills are never executed or activated. Explicit `McpSkillDiscovery` composition adds complete list/get; Resource-only use remains independent. |
| **MCP Skill discovery** | `McpSkillDiscovery` owns complete `skills/list` and exact `skills/get` over the same immutable `McpSkillResources` and readable Resource capability. Construction validates supplied immutable entries and wiring without enumerating external Resource providers. Admitted list/get validates the current complete shared Resource catalog before disclosure, including hidden descriptors. Fresh whole-Skill and general member availability filter before bytewise URI ordering and atomic count/byte pages; direct get never requires listing membership. Bounded authenticated cursors contain no authority or concealed catalog, and stale cursors restart safely. Complete entry/read/result budgets include actual central metadata; no content is reopened or rehashed for discovery. Conservative private cache hints do not authorize reuse across callers. The registry composes distinct extension IDs, rejects conflicting same-ID settings and requires complete Skills/Resources composition. Directory RPC, subscriptions, execution and real Pi qualification remain excluded. |
| **MCP Tool** | An explicitly supplied `Application\Mcp\Tool\McpTool` implementation whose `handle(ApplicationData, McpProgressReporter): McpToolOutput|McpInputRequired` method declares exactly one `McpToolInfo` attribute. This explicitly approved pre-release widening permits the interactive subtype; non-interactive implementations retain their narrower complete-output return. Registration validates identity, description and the documented bounded JSON Schema 2020-12 declaration profile without scanning Actions, hydrating payloads or invoking the Tool. The selected-Tool invoker schema-checks exactly the arguments, applies method-level Validation rules through ValidationService, and supplies only validated ApplicationData plus a no-op reporter to non-streaming calls. |
| **MCP Tool availability** | A consumer-owned request-scoped decision receiving only immutable `McpToolInfo`. Discovery reevaluates it before bytewise canonical-name ordering and pagination; invocation shares the same decision and applies it before argument validation. HTTP mirror selection also checks current availability so hidden and unknown Tools reject identically. Common neither receives a principal nor replaces business authorization. |
| **MCP Tool discovery** | The explicit `tools/list` capability returning only available definitions with zero/private cache defaults. Consumer-keyed authenticated cursors bind to the visible catalog and page size, contain no concealed registration data, and reject after relevant catalog changes. Explicit cache overrides remain consumer policy. |
| **MCP Tool output** | A complete consumer-projected JSON value with compatible text, exposed through immutable `Domain\Value\Basic\StrictJson` from `structuredContent()` and preserving object/list distinctions. The schema accessors and definition arrays on `McpToolInfo` likewise contain StrictJson values; attribute declarations remain arrays and discovery preserves its JSON wire representation. Construction rejects object keys beginning with U+0000 because PHP cannot represent them in object-mode JSON decoding. It is semantic output, not a protocol envelope, partial result or authorization decision. An opt-in `structuredWithResourceLinks()` attaches validated `McpResourceInfo` metadata as standard `resource_link` content items after the matching text, without making link possession a read grant or changing structured schema validation. Invocation checks structured output against the declared bounded schema profile before emitting a complete result; consumers still own public-safe projection. |
| **MCP progress reporter** | The unchanged request-progress port for public-safe numeric/status work and cooperative cancellation observation, never Tool result content. Invocation defaults to a fresh NullMcpProgressReporter that validates nondecreasing progress, emits nothing and never reports cancellation. A bound request execution supplies live, strictly increasing finite progress with the exact string/integer token. The default direct-JSON HTTP composition still rejects progress-token calls; explicitly enabled progressive composition uses the HTTP emitter for live SSE and observed-disconnect cancellation. |
| **MCP Tool execution** | The internal request-scoped Application lifecycle that dispatches one decoded tools/call through the semantic responder, delivers ordered progress before one complete final response, and suppresses later output after outer-adapter cancellation. Completion closes reporting before final delivery. Live reporter violations, including malformed argument types, remain terminal even when caught or mapped by a Tool; unexpected failures are diagnosed once and sanitized. Cancellation is advisory, never forced interruption, command rollback or a notifications/cancelled message. Its ordered delivery and invocation-binding seams remain internal. The HTTP pull stream pauses execution at each frame until the emitter flushes it; controlled Application transcripts alone are not live HTTP evidence. |
| **MCP progressive HTTP delivery** | Explicit opt-in response-mode selection plus a lazy single-use PSR event stream and concrete PHP-SAPI emitter. Each progress frame crosses the write/flush boundary before Tool work resumes; observed client closure closes that request's body and resumes work cooperatively with cancelled reporting. Symfony/Laravel share native StreamedResponse translation, CodeIgniter uses a native send-phase adapter, and Yii/Slim use the shared PSR emitter. Package proof uses PHP cli-server with output buffering/compression disabled and a raw socket client; it is not a starter receipt or a guarantee for another server/proxy. |
| **MCP ordinary input interaction** | An explicitly registered `McpInteractiveTool` statically requires current-request form elicitation, returns keyed `McpInputRequired` forms without dispatching its mapped use case, and later receives restored original validated arguments plus typed accept/decline/cancel responses through `resume()`. Stateless versioned AEAD binds neutral caller identity, canonical Tool, original/validated arguments, form schemas/runtime rules, ordinary mode, expiry and issuing ID. Retry rechecks current availability before disclosure or response validation. State is bounded to 65536 bytes before cryptography; dedicated rotating keys and a 300-second default (1–900 second) TTL bound ordinary replay but do not make it single-use. Destructive confirmation uses the distinct atomic mode below. Common owns mechanics, not caller resolution, business authorization or destructive-action classification. |
| **MCP destructive confirmation** | A consumer-designated `McpInputRequired::confirmation()` interaction binds a dedicated random identity and confirmation mode into the protected state. The consumer implements `McpConfirmationStore` to issue without overwrite and atomically consume one exact unexpired full-state digest before affirmative `resume()`. Any decline/cancel retires the whole interaction without resume or bus dispatch. Replays, losing contenders and store failures reject safely. Consumption persists across failures, cancellation and disconnect; admission is at most once, not atomic with the business mutation or a rollback guarantee. Concrete persistence, approval language, authorization and destructive classification remain consumer-owned. Ordinary state never uses the store. |
| **MCP Tool failure mapping** | An exact failure-class-to-constant-public-text registration applied only during selected Tool execution. Schema/rule rejection uses fixed safe text; unclassified, output, metadata and availability failures become generic internal errors and are diagnosed once at the central semantic or HTTP boundary. No exception message is automatically public. |
| **MCP Tool envelope metadata** | Opt-in, request-scoped command/query filter enrichment while one validated available Tool executes: canonical `fight.mcp/tool`, fresh `fight.mcp/correlation`, and explicitly allowlisted scalar namespaced audit fields projected by the consumer. It preserves payload and envelope identity and clears on every exit. It is not a principal, authorization decision, raw request or durable audit record. |
| **MCP request metadata** | Bounded protocol context supplied under `_meta`: protocol version, client capabilities/information, and progress token. StrictJson owns immutable JSON shape; metadata keys, defined fields, present trace context, and schema-permitted implementation icon sources are validated as protocol inputs, including RFC 2397 Base64 image data whose raw media components use RFC 2396 URL characters or valid percent escapes, whose decoded subtype and parameter attributes are RFC 2045 MIME tokens, and whose parameter values may use correctly escaped tspecial-bearing or quoted-string forms. Schema-permitted empty strings and open objects are retained. It is neither a credential nor a principal and does not convey authorization policy. |

MCP-specific HTTP and native response adapters are grouped under `Adapter\Mcp\Http`, `Adapter\Mcp\Symfony` and
`Adapter\Mcp\CodeIgniter`. Their nine released `Adapter\Http` names remain functional, final and deprecated in
PHPDoc/documentation only throughout 1.x. Canonical service parents are non-final solely for these compatibility
subclasses, not consumer extension; the final Origin value retains a separate legacy implementation to preserve
factory identity and serialized state. Internal HTTP helpers move directly without public aliases. See the
[complete adapter migration map](docs/components/mcp/index.md#canonical-adapter-namespaces-and-1x-compatibility).
All agent-owned classes present on develop also live beneath `Mcp`: Domain uses direct `Session`, `Phase`,
`Observation`, `Activity`, `Detail`, `Usage`, `Value`, `Notice`, `Task` and `Subscription` concept branches;
Application and adapters retain cohesive capability branches. Provisional tracking paths move directly without
snapshot aliases. Legacy AI payload/webhook APIs gain equally deprecated `Mcp\Operation` homes while their
released `Auth` names remain supported with their original 1.2 deprecation and 2.0 sunset. This does not revive
or register retired AI Tools, or change MCP method or Tool identifiers, schema, admission or delivery. Durable Task-input
additions use these same canonical Mcp homes as described below. Existing stateless forms/confirmation remain unchanged. Generic
infrastructure keeps its ownership. See the [complete namespace map](docs/mcp-namespaces.md).
This bounded move does not change protocol, security or delivery contracts.

## Execution observation language

**Protected MCP execution inspection:** Two additional explicit opt-in `Application\Mcp\Execution` Tools
(`execution.messages`, `execution.detail`) fetch `ListCapturedMessages`/`GetCapturedDetail` through the ordinary
QueryBus and the same guarded endpoint, independently of safe Tool registration. Fresh Tool availability never
replaces separate current DetailReadAdmission metadata/body grants on each page/chunk. Exact full relationship
selection grants no authority; denied/unknown/mismatched identities share null. Typed projections retain content
types, Base64 byte coordinates, empty versus missing/expired/partial capture and live ordering/continuation without
a snapshot, retention lease or complete-output inference. Message pages default 1/32 with 1,024-byte initial chunks;
individual chunks allow offset 0–65,536 and length 1–4,096. Matching text, schemas and the existing 262,144-byte
projection/1,048,576-byte complete encoded-response limits apply; overflow/errors are sanitized, not successful
empty data. No mutation/event, new policy/endpoint or optional capability dependency is introduced. See
[MCP execution inspection](docs/mcp-execution-inspection.md#protected-detail-opt-in-and-compatibility) for wiring,
exact schemas, permission seams and the two additive final public classes' compatibility classification.

**Safe MCP execution inspection:** Five explicit opt-in `Application\Mcp\Execution` Tools (`execution.summarize`,
`execution.list`, `execution.get`, `execution.phases`, `execution.activity`) call the shared Queries through
`QueryBus::fetch()`, preserving typed safe results, full-selection totals, live pages, current trusted admission,
recording-time/full-UUID order and source precision. Tool availability is independent and fresh. No protected
bodies, mutations, events or extra MCP capability dependency is introduced. Exact schemas and the 262,144-byte
projection limit supplement application validation; opt-in `McpToolOutput::boundedStructured()` retains a
1,048,576-byte complete JSON-RPC success-response bound through text duplication/escaping, request identity and
actual central metadata. Overflow uses the existing sanitized failure path, not truncation or empty success.
Existing unbounded Tool output remains unchanged. See [MCP execution inspection](docs/mcp-execution-inspection.md)
for composition, catalog, schemas, bounds, null/empty meanings and compatibility.

| Term | Meaning in Fight Common |
| --- | --- |
| **Authorized execution overview** | Opt-in `Domain\Mcp\Session\Query` summary/list payloads describe Session inspection; `Application\Mcp\Execution\Session\QueryHandler` coordinates them. `Read` is a technical responsibility, not an aggregate boundary. Application Session `Service` owns `ExecutionReadAdmission`, `ExecutionReader` and typed `ExecutionAccess` project/session constraints; `Data` owns the safe `ExecutionPage`, `ExecutionSummary` and `ExecutionView` results. Current consumer-supplied grants are intersected with finite `ExecutionSelection` before DBAL counts/rows. `SummarizeExecutions` scans the full authorized retained selection in bounded batches, counting sessions once, choosing latest session-observation outcome and preserving exact usage groups by metric/unit/provenance with explicit missing/completeness counts. `ListExecutions` uses `Pagination::strict()` plus finite bounds and fixed fields, ordered by latest retained recording time then bytewise project/session. `Domain\Mcp\Usage\UsageTotal` owns compatible measurement arithmetic beside `UsageMeasurement`. `Adapter\Mcp\Execution\Dbal\DbalExecutionReader` sits alongside the DBAL recording adapters without a separate `Reader` namespace and starts no transaction or business mutation; multi-statement responses and separate requests are live, not snapshots. Freshness describes persisted evidence encountered, never complete capture, Workflow authority or a global checkpoint. Protected bodies are never read. See [execution reads](docs/execution-reads.md) for limits, ordering, failure, composition and compatibility. |
| **Selected execution / phases** | `Domain\Mcp\Session\Query\GetExecution` returns nullable Application Session `ExecutionInspection` with safe latest session timing/outcome and full currently permitted usage statistics, independent of phase pages. `Domain\Mcp\Phase\Query\ListExecutionPhases` and its Application Phase handler/`Service\PhaseReader` return nullable bounded live `Data\PhasePage`/`PhaseView` results through `Adapter\Mcp\Execution\Dbal\DbalPhaseReader`. Both reuse fresh Session admission; optional project-scoped phase restrictions constrain overview/selected evidence, phase totals and exposed parents. Concealed/missing execution is the same null; an admitted execution with no matching phases has an empty page. Phase state excludes activity/detail completion; performing Agent, bounded distinct models and instrumentation producer remain distinct. `Application\Mcp\Execution\Observation\Data\ObservationView` exposes only observed timing/outcome and recording provenance. Latest phase order is recording time/full UUID/producer descending, with bytewise phase-ID ties; parent disclosure also requires consistent same-session current access. Models/usage are finite and explicit, missing completion/measurement is not success/zero, and separate statements/requests remain live. Neither a review label nor surrounding success establishes acceptance or Workflow authority. These selected summary/phase results contain no activity rows or protected bodies; activity browsing uses its separate Application port below. No mutation/events, new schema/index or frozen browsing session is introduced. |
| **Recorded execution subjects** | Ordinary `Domain\Mcp\Session\Session` and `Domain\Mcp\Phase\Phase` aggregate roots own project-scoped identities and immutable consumer/session/parent/Agent/name/attempt relationships. Their individual Domain repositories retrieve and persist subjects independently; they are not a Workflow engine or an event-sourced graph. State-based relationship checks belong to the roots. Authorized summary/list payloads and selection belong to Domain Session, with dedicated Application Session read results rather than new aggregates or reporting methods on write repositories. Selected execution inspection reuses this Session read boundary; phase pages have a dedicated Application Phase read port. Activity browsing likewise has an Application Observation projection port, not reporting methods or caller grants on Domain repositories. |
| **Safe phase activity inspection** | `Domain\Mcp\Observation\Query\ListPhaseActivity` and its Application Observation handler reuse fresh `ExecutionReadAdmission` to return nullable bounded `Data\ActivityPage`/`ActivityView` through `Service\ActivityReader` and `Adapter\Mcp\Execution\Dbal\DbalActivityReader`. Domain repositories remain aggregate collection/persistence contracts; this inward Application Observation port projects safe observations and permitted content-row presence, not replacement persistence, a new Activity aggregate or a second authorizer. Project/session/phase scope plus optional producer/full-UUID activity-browsing constraints apply before decoding; explicit detail-reference grants default to none and never grant bodies. Overview aggregate permission remains independently session/phase-scoped, not inferred from activity permission. Pages default 1/100, size at most 100, offset at most 1,000,000; only producer/kind/model exact filters and recorded_at ASC/DESC are supported. Scans use 100-row keyset batches and full matching totals, with at most 32 permitted references per activity and 1 MiB encoded response before failure. Full receipt microseconds/UUID/producer order is deterministic, not causal/commit/checkpoint order; occurrence preserves supplied precision. `Application\Mcp\Execution\Detail\Data\DetailReference` preserves capture and explicit absent/unavailable/expired markers; complete/partial is retained only on content-row presence, otherwise missing with unknown reason. No body column is read, no hidden-reference count is emitted, and retained means neither body validation nor permission/lease. Separate live statements/requests are not snapshots; removal/revocation can change later pages. Existing fixed read failures conceal public diagnostics; no writes/events, schema/index change, protected retrieval/removal, MCP/UI or Workflow authority is added. See [execution reads](docs/execution-reads.md#phase-activity-and-permitted-detail-references). |
| **Execution observation** | Opt-in immutable version-one `Domain\Mcp\Observation\Observation` aggregate-root evidence about a session or phase, separate from authoritative Workflow state. It contains producer-scoped full UUID identity, project/session/consumer references, optional performing-Agent phase/parent/name/attempt, occurrence and nullable observed start/end times, explicit unknown/succeeded/failed/cancelled outcome and bounded namespaced scalar extensions. Missing completion is unknown, not success. Performing Agent and instrumentation producer are distinct. State is private and read through typed `id()`, `producerId()`, `session()`, `phase()`, timing, `outcome()` and `extensions()` and `detail()` accessors; session/phase references likewise expose typed methods rather than public properties. Activity observations use the same provisional version-one representation, ordinary root and phase Command, with existing phase/session relationships, precision-preserving occurrence and bounded usage; separately stored protected detail extends the same recording path, while protected inspection/removal use the separate Detail boundary below and automatic capture remains excluded. Safe summary/list Queries use the separate current-read boundary. |
| **Phase activity / measured usage** | `Domain\Mcp\Activity\PhaseActivity` holds a consumer-owned namespaced kind, at most 512-byte safe UTF-8 summary, optional model reference and up to 32 unique metric measurements. Model, performing Agent and instrumentation producer are distinct. `UsageMeasurement` preserves exact nonnegative decimal strings, explicit unit/provenance and complete/partial/unknown/unavailable evidence; unknown/unavailable require null, measured zero is `"0"`, and absent usage is never zero. `Observation::forActivity()` creates version-one evidence; `ObservationOccurrence` retains zero through six supplied fractional digits in canonical UTC text. Activity cannot create its phase, certify review or advance Workflow state. The existing safe `ObservationRecorded` event contains references only, never activity text or measurements. Consumers own vocabulary, capture accuracy, redaction and billing; Common supplies no inference or protected-body channel. |
| **Recording Commands** | `Domain\Mcp\Observation\Command\RecordSessionObservation` and `RecordPhaseObservation` privately hold their validated observations and expose them through `observation()`. Their `toArray()`/`fromArray()` payloads use the current common version-one shape with nullable activity/detail; neither exposes a public payload property. `RecordProtectedDetail` separately pairs a safe detail observation with protected content; its serialized Command contains bodies and must not be logged as safe metadata. |
| **Observation admission** | Consumer-owned current trusted producer/project/session/phase/parent permission decision, invoked by `ObservationRecorder` for every Command including retries before concealed relationship/content lookups. IDs, message metadata and phase names are not credentials or review authority. Matching retained content does not grant continued access. |
| **Recorded observation** | Observation owns typed immutable submitted evidence plus its immutable UTC server recording time, preserving supplied microseconds and full UUID COMB representation. Time is not causal or commit order. Exact canonical version-one content, excluding recording time, deduplicates one producer/UUID key while retained; changed content conflicts. Session/phase subjects and parent relationships remain immutable. `ObservationRepository` returns recorded roots. `RecordedObservation` is only a receipt/view over that root, not another aggregate, time owner or repository; `observation()` and `recordedAt()` expose the root and its time. |
| **Recording transaction** | Application `RecordingTransaction` extends `TransactionalUnitOfWork` separately from the four Domain repositories. `DbalRecordingTransaction` owns the real commit and schema version-row serialization lock; all four DBAL repositories share that boundary object and its connection. `Application\Mcp\Execution\Observation\Service\ObservationRecorder` coordinates admission, repository checks and writes behind one void Command operation. Session/phase and observation writes commit or roll back together, never in three independent commits. Caller transactions and unowned writes reject without affecting unrelated work. Uncertain begin/commit/rollback closes the boundary and never proves absence. No ORM flush, external-work transaction, Event Store positions or hidden transient retry is implied. |
| **Protected capture/detail** | `Domain\Mcp\Detail\DetailCapture` supplies version-one kind (message/tool arguments/tool result/process output), lowercase parameter-free media type up to 128 bytes, explicit complete/partial/absent/unavailable/expired capture and an existing activity UUID. `Observation::forDetail()` binds it to the same producer/project/session/phase; details cannot create phases or activities. `ProtectedContent` alone owns protected UTF-8 body bytes (65,536 maximum; output segments 16,384), never safe summaries/extensions/events. Complete/partial require a body including empty; absence states require null. `OutputSegment` requires stream stdout/stderr/combined, index 0–1,000,000 and byte offset/end at most 2,147,483,647; no continuity/full-output inference. Consumers own capture/redaction and workflow authority. |
| **Protected storage/removal boundary** | `ProtectedContentRepository` and `DbalProtectedContentRepository` are trusted recording storage, not public authorized Queries. `fight_execution_contents` stores body/comparison separately, FK-linked to safe detail identity; safe observations store capture metadata and an activity FK, no body/hash copy. Related effects share the existing recording transaction. A removed protected row is unavailable, never inferred expired; retained null-body rows preserve explicit absence markers. Capture completeness is historical metadata, not current availability. With safe identity retained, removed content raises `ProtectedContentRemoved` on matching-safe retries without body comparison/restoration; changed safe metadata conflicts. Removing identity too ends deduplication, permitting new admitted recording. Removal must share the serialization lock with retries; real three-database tests prove both lock orders. Public authorized reads/removal use the separate Detail Query/Command boundary, not recording admission; removal shares this transaction/serialization lock. |
| **Committed observation fact** | Safe `Domain\Mcp\Observation\Event\ObservationRecorded` carries only observation, producer, project, session, nullable phase references and recording time through `id()`, `producerId()`, `projectId()`, `sessionId()`, `phaseId()` and `recordedAt()` over private state. Its serialized payload stays unchanged. New evidence triggers it after commit; matching retries never redispatch. `CommittedObservationDispatchFailed::event()` exposes the safe committed fact despite dispatch/subscriber failure; `ObservationStorageFailed::outcomeUncertain()` distinguishes storage uncertainty from rollback. Both exceptions keep their context private. No outbox, guaranteed replay or exactly-once delivery is promised. |
| **Observation schema** | Reserved DBAL `fight_execution_*` version/lock, session, phase and observation tables, composed via public DBAL 4.4-compatible APIs. Composite FKs preserve same-project/session links. SQLite/MySQL/PostgreSQL are qualified; MySQL uses InnoDB and binary reference collation. Canonical UTC timestamp text and complete UUID strings avoid truncation. Schema one includes the recording-time/full-UUID index for all observation kinds. Exclusive idle `install()` creates or validates the current version-one namespace and required index; partial installations and unsupported versions reject. No development-format upgrades or resume paths are supplied. `compareRecordingTo()` agrees with descending SQL recording-time/full-UUID ordering when reversed, including random suffixes; equal keys across producer namespaces remain ties. These keys are not causality, commit order or stable visibility. Setup operations, capture/redaction, retention and access policy remain consumer-owned. |
| **Protected detail inspection** | `Domain\Mcp\Detail\DetailSelection` and `Query\ListCapturedMessages`/`GetCapturedDetail` select one exact project/session/phase/producer/activity and optional detail/kind. Application Detail handlers freshly invoke distinct `Service\DetailReadAdmission` on every page/chunk; `DetailAccess` contains separate current metadata/body grants, never timeline authority. `Service\DetailReader` and `Adapter\Mcp\Execution\Dbal\DbalDetailReader` project actual bounded SQLite/MySQL/PostgreSQL reads without changing Domain recording repositories. Metadata is SQL-constrained before decoding/counting, and body columns are read only for separately admitted returned details. `Data\MessagePage` has strict live 1/32 defaults, size at most 32, offset at most 1,000,000, recorded_at ASC/DESC only and first 1,024 bytes per permitted message. `CapturedDetail` returns the existing `DetailReference`, body permission and nullable `ContentChunk`; individual requests allow offset 0–65,536 and length 1–4,096 bytes. Base64 preserves arbitrary byte cuts through recorded UTF-8; concatenate decoded bytes before text interpretation. Exact end/empty capture has an empty final chunk; null means denied or unavailable according to metadata. Complete/partial capture is historical; missing rows have unknown reason and explicit absent/unavailable/expired markers survive. End of a segment proves neither complete output nor continuity. Recording microseconds/full UUID order is deterministic, not causal/commit/checkpoint order; occurrence retains original precision. Separate statements/requests are live with no permission cache, frozen snapshot or retention lease. Reads write/emit nothing; failures use existing fixed public text with private causes. No schema/index, recording, safe-read or consumer-policy change; optional MCP presentation uses these same Queries. Ordinary removal uses its independent boundary below. See [protected reads](docs/execution-protected-reads.md) for composition, bounds, representation, consumer ownership and compatibility. |

**Authorized protected-detail removal:** `Domain\Mcp\Detail\Command\RemoveCapturedDetail` selects exactly
one detail ID/kind under an exact `DetailSelection`. Application Detail `CommandHandler\RemoveCapturedDetailHandler`
and `Service\DetailRemover` invoke independent current `DetailRemovalAdmission` inside recording's real-commit
transaction before any lookup, including repeats. `DetailSelection::acceptsDetail()` and retained capture/activity
relationships enforce scope. Read/reference/recording grants never authorize removal; consumers supply trusted
current permission for the entire selection. Denied, missing and inconsistent identities share fixed
`DetailRemovalRejected`. `ProtectedContentRepository::removeFor()`/DBAL delete only the protected body/comparison
row; safe identity/capture/activity links and session/phase/usage evidence stay. Retained identity plus missing
content is an admitted no-op; unknown identity rejects. Outer transactions reject before writes without touching
caller work. The existing schema lock serializes recording/removal on SQLite/MySQL/PostgreSQL; rollback and uncertain
commit retain existing failure categories. No removal event is emitted; a later consumer delivery failure cannot
undo deletion. Protected/safe reads use actual presence: missing body means no chunk/available reference, unknown
absence reason, while supplied expiry/absence markers survive. Reads are live without a page/chunk lease or
retraction of earlier bytes. Retained safe identity blocks retry restoration (`ProtectedContentRemoved` for matching
safe metadata; changed safe metadata conflicts). Deliberately purging identity ends deduplication, permitting a fresh
explicit admitted recording, not hidden restoration. Identity purge, retention eligibility/holds, scheduling,
archive/job recovery, external atomicity, MCP removal Tools and UI remain excluded/consumer-owned. Five opt-in
public declarations and the provisional repository method are compatibility-classified against tags, not older
development snapshots; no schema/index/dependency change. See [detail removal](docs/execution-detail-removal.md).

See [execution observations](docs/execution-observations.md) for finite field/extension limits, composition,
comparison, retained-record boundaries, schema installation and safe failure handling. All Execution additions remain
provisional until tagged; compatibility is against tagged releases, not prior development commits. One current
version-one payload includes nullable activity for session/phase evidence; unsupported versions still reject.
No historical codecs, envelope aliases or development-format migrations are supplied. Existing released audit, CQRS,
transactional unit-of-work and Event Store guarantees remain unchanged.

## Durable asynchronous Task language

`Domain\Mcp\Task\Task` owns coherent working/input_required/completed/failed/cancelled state, original immutable
`TaskDefinition`, server millisecond timestamps, immutable creation-duration TTL, optional polling advice and a
Task-local version fence. `TaskState` owns status-specific input/result/error invariants and finite encoded bounds.
Completed Tool `isError: true` remains completed; failed holds a safely mapped JSON-RPC execution error. Neither
silence, disconnect, expiry nor a handle establishes execution, cancellation, business success or Workflow authority.

Shared `CreateTask` / `RecordTaskState` Commands and `GetTask` Query route through Application Task handlers and
`TaskLifecycle`. Independent current creation, producer-update, read and removal admissions are consumer-supplied; possession,
capability declarations and submitted labels grant nothing. Required `TaskPayloadPolicy` validates retained original
contract context without executing work and freshly authorizes whole-state safe disclosure. Concrete
`ToolTaskPayloadPolicy` validates captured Tool schemas, supported flat forms/text results and exact constant public
execution errors; explicit disclosure remains consumer-owned. Protected data is never replaced with empty success.

`Adapter\Mcp\Task\Dbal\DbalTaskStore` implements Application `TaskStore` / `TransactionalUnitOfWork` directly on its
own idle DBAL connection, not an unrelated ORM transaction. Dedicated version-one `fight_task_state` / `fight_tasks`
storage supports SQLite/MySQL/PostgreSQL. A namespace lock serializes mutation/admission through actual commit;
state/payload/comparison changes are atomic. Enclosing transactions and dirty reads reject before writes without
committing or rolling back caller work. Unknown begin/commit/rollback closes the boundary with explicit storage
uncertainty; fresh authorized polling reconciles possible committed state. Reference-only `TaskChanged` follows
commit; `CommittedTaskDispatchFailed` cannot erase persistence. No outbox/replay/general-notice-feed guarantee exists.

Creation retains one SHA-256 original-content digest; exact encoded-content repeats are admitted no-ops while
retained, including after progress. Updates retain only the latest transition digest plus expected version; only
an immediately matching retry is a no-op. Conflicting/stale updates and all new terminal replacements reject.
Comparison memory is bounded, private and removed with the row; physical removal ends those guarantees, with no hidden
historical archive or exactly-once job promise. `GetTask` projects one immutable coherent bounded `StrictJson` view,
never definition/comparison material. Denied/missing/cross-scope/expired/withheld is null with unknown absence reason;
storage failure is explicit. Reads emit/mutate nothing and never renew TTL or remove state. Consumer clocks, retention,
permissions, safe evidence, workers, scheduling and external-effect recovery remain consumer-owned.

`RemoveTasks(TaskSelection::fromIds(scope, ids))` selects 1–100 distinct explicit identities. Fresh independent
`TaskRemovalAdmission` checks every member under the same namespace lock before any existence/deletion work,
including missing/repeated requests. Denial rejects the whole selection. Authorized missing members are no-ops;
whole-row deletion atomically removes state, original context, input/result/error, TTL and both comparison digests,
without a hidden archive/tombstone. TTL expiry alone never deletes. A bounded reference-only `TaskDataRemoved` lists
only actual deletions after commit; dispatch failure cannot restore data and repeats never replay facts. Late producer
updates to absent state reject without upsert. Separately admitted creation after removal starts a new lifetime,
even for an old creation retry; consumers use fresh random IDs and own bounded retries/old-producer quiescence for
any deliberate tuple reuse. No permanent identity-memory, cross-lifetime version fence, forensic database erasure,
job stop or external-effect rollback is promised. Removal owns its actual commit and rejects caller transactions.

`TaskInputBatch` / `TaskInputs` extend the same retained row with immutable caller-bound form/confirmation groups,
private original schema/runtime contracts, durable accepted answers and a lifetime high-water key counter. The
Application `TaskInputLifecycle` routes `RequestTaskInput`, `SupplyTaskInput`, `AcquireTaskInput`, `RetireTaskInput`
and protected `GetTaskInput` through existing buses/storage. Independent current submission, observation and business
continuation decisions remain separate from producer/read grants. All active validation precedes atomic partial-batch
admission; inactive keys ignore content under finite whole-envelope bounds. Unanswered forms stay outstanding; any
active confirmation refusal retires only that group, and complete unexpired non-refused groups acquire once before
consumer continuation. Consumer failures/uncertain acknowledgment never restore consumed input or prove execution.
Keys `input-<scoped-Task namespace>-1` through counter 1,024 never repeat within a retained lifetime or identify
another Task/scope's request, even after explicit payload retirement;
at most 16 retained requests/groups and fixed encoded bounds prevent hidden archives. Whole-row removal removes
this bookkeeping too. Queries are read-only, expose no private rules and do not renew expiry. Producer state cannot
replace contracts or clear unanswered forms. Public projection omits expired forms without a read-side mutation,
preserving the admitted producer message (including empty string versus null) while any forms remain outstanding.
`Application\\Mcp\\Task\\McpTaskInput` implements actual per-request negotiated, bounded `tasks/update` Command
handling and an empty complete acknowledgment, with no independent capability advertisement. Ordinary stateless
exact-key/replay and existing confirmation-store behavior are unchanged. See [Task input](docs/task-input.md).

`RequestTaskCancellation` and protected `GetTaskCancellation` share this same row/lock through
`TaskCancellationLifecycle`. Independent current cancellation/intent-observation grants never imply ordinary read,
producer or worker-control authority. One bounded original timestamp/version records intent separately from actual
outcome; commit and shared-read reconstruction precede initial acknowledgment. Repeats/known terminal requests
reauthorize as no-ops without renewal, new stop obligations or payload changes. Missing/expired/removed state never
resurrects or implies stopping. All input/producer changes preserve intent and terminal winners; consumed confirmation
and accepted answers survive cancellation. Input continuation requires current business and intent-observation grants,
and its protected Query exposes coherent current intent/version before the consumer's separate stopping decision.
No worker/outbox, forced stop, guaranteed delivery or atomic external-effect/compensation promise is added.
`McpTaskCancellation` implements negotiated bounded `tasks/cancel` with safe errors and empty complete acknowledgment,
not a capability advertisement or stopped-job receipt. See [Task cancellation](docs/task-cancellation.md).

This lifecycle does not advertise MCP Tasks, change Tool return contracts or supply live delivery.
TASK-00168 owns complete activation/combined HTTP proof. See
[durable Tasks](docs/durable-tasks.md) for exact composition, numeric bounds, retry lifetime, schema and compatibility.

## Live MCP subscription language

Explicit `Application\Mcp\Subscription\McpSubscriptions` enables guarded POST `subscriptions/listen` for requested
Tool/Resource catalog changes, optional explicitly enabled Prompt catalog changes and exact selected Resource updates. It acknowledges the supported/currently permitted
subset first; every signal and graceful completion carries the exact typed originating JSON-RPC ID in standard
subscription metadata. Random owner-bound technical identities remain separate. Shared authorized `RecoverNotices`
Queries establish a current committed boundary (`startAtCurrent: true`, no historical replay), then source bounded
cross-process batches. Uncertain/incomplete/lost continuity ends delivery, never silently reports healthy catch-up.
Consumer `McpSubscriptionAdmission` independently authorizes the whole signal directly before each queued emission;
no protected notice fields become arbitrary wire payloads. Optional `prompts: true` requires actual Prompt serving;
only explicitly recorded `mcp.prompts.list_changed` maps to `notifications/prompts/list_changed`, without names,
bodies, arguments or revision promises. Whole-catalog activity permission remains distinct from Prompt retrieval;
queued changes reauthorize again after tracking callbacks. Listening never calls Prompt content/metadata providers
or fetches links; registration alone snapshots metadata. Subsequent list/get independently reauthorizes, and generic
tracking/inspection already retains requested/accepted Prompt filters. Consumers own safe change capture, not a new
Prompt store or guaranteed complete catalog monitor. Ordinary serving and Tool/Resource subscriptions remain independent;
Task/Mercure delivery is separate.

**Current subscriber inspection:** `Domain\Mcp\Subscription\SubscriberSelection` and
`Query\{SummarizeSubscribers,ListSubscribers}` route through `Application\Mcp\Subscription\QueryHandler` to separate
current `Service\SubscriberReadAdmission` / `SubscriberAccess` and `SubscriberReader` /
`Adapter\Mcp\Subscription\DbalSubscriberReader`. Complete-metadata grants cover every requested/accepted filter,
identity and reference; a stream's full nonempty opening scope set must fit both selected and granted scopes, with
optional exact registration restrictions. Counts/extrema include only admitted matches: distinct technical
registrations versus distinct trusted subscriber strings, plus three fixed observed-state buckets. No join
multiplication or page-local summary. `Data\SubscriberSummary`, `SubscriberPage`, `SubscriberView` and
`SubscriberReadScope` retain explicit store visibility, invocation clock, complete opening filters and honest
recently-observed/disconnected/stale-uncertain state, never connectivity, continuing permission or receipt evidence.
Strict live pages default 1/16, maximum size 16/offset 1,000,000, opened_at ASC/DESC with bytewise full-ID ties.
Direct existing SQLite/MySQL/PostgreSQL tracking scans use 32-row batches, at most 8,192 examined rows under churn,
bounded distinct-identity memory and 262,144-byte encoded pages; overflow/failure is fixed `SubscriberReadFailed`,
not empty success. No read-side heartbeat/cleanup/transaction/event, duplicate store, schema/index change,
Prompt dependency, MCP Tool, dashboard, historical audience or consumer policy is introduced. Empty authorized
selections reveal no concealed global freshness; multi-statement reads and later pages remain live. Cleanup stays
with the lifecycle owner, and absence does not prove no previous stream. See
[subscriber inspection](docs/subscriber-inspection.md) for APIs, complete-row permission, all finite bounds,
unknown/failure meanings, cross-process runtime evidence and tagged-release compatibility.

`Adapter\Mcp\Subscription\DbalSubscriptionSchema`/`DbalSubscriptionStore` own actual SQLite/MySQL/PostgreSQL
version-one technical registrations, requested/accepted filters, trusted subscriber/scope, random ownership and
observed open/closed/five-second freshness. Owner heartbeats cannot revive closed/expired rows; abrupt death becomes
stale/uncertain, not confirmed connectivity or a permission lease. Short dedicated transactions, a namespace capacity
lock, at most 256 fresh registrations/4,096 retained rows and explicit 1–256-row cleanup bound tracking. No historical
audience/receipt ledger is added. The existing lazy event stream/emitter and native integrations carry subscriptions
without buffering. Finite lifetime/waits, one bounded Query batch/frame and actual encoded-frame checks constrain work;
consumer worker/SQL/SAPI/proxy timeouts remain necessary because PHP cannot preempt blocked I/O. Returned callbacks
must pass fresh cancellation/deadline and independent five-second tracking checks before new output. Owner-bound
tracking is revalidated after admission, without reviving expired ownership or caching permission. Opening then
rechecks the complete accepted subset after final tracking, with no later consumer callback before acknowledgment;
changed permission closes without acknowledgment. Local freshness/cancellation/deadline checks still apply, and even
a returned store callback cannot make an already elapsed lease healthy. DBAL renewal advances the invocation-supplied clock
by elapsed host wall-clock seconds after acquiring the capacity lock, checks old expiry at that serialized boundary
and keeps the proposed expiry unchanged; lock waits cannot resurrect ownership. Supplied epochs remain supported
under synchronized/no-rollback wall-clock assumptions. Expired opening produces no acknowledgment, and body
closure during production suppresses bytes from the in-progress read. Failure preserves
retained notices for independently authorized polling; reconnect starts a new live registration, not replay.
See [live MCP subscriptions](docs/mcp-subscriptions.md) for exact composition, safe type mapping, numeric limits,
continuity prerequisites, runtime qualification, schema and tagged-release compatibility.

## Retained safe notice language

`Domain\Mcp\Notice\Notice` is a bounded immutable change notice, not authoritative application state, an execution
observation, protocol identity or an Event Store event. `NoticeMetadata`, `NoticeRelationships` and
`NoticeOccurrence` preserve bounded scalar safe labels, distinct subject/recipient/author/causation references,
explicit unknowns/broadcasts and supplied UTC precision. Consumer `NoticeAdmission` freshly validates trusted
producer/scope/content/references on originals and retries; submitted fields never grant access.

`RecordNotice` routes through `RecordNoticeHandler` and `NoticeRecorder` to Application `NoticeStore`, which extends
`TransactionalUnitOfWork`. `Adapter\Mcp\Notice\Dbal\DbalNoticeStore` owns dedicated actual SQLite/MySQL/PostgreSQL
transactions, rejecting enclosing transactions before writes. One state-row lock serializes allocation through
actual commit of content, relationships and exact versioned comparison. Scope/producer/ID-bound identical retries
deduplicate while retained; changed admitted content conflicts without overwrite. `RecordedNotice` keeps immutable
server time separate from its namespace-local positive signed-32-bit commit-ordered position; neither establishes
causality, cross-store order or delivery. Occurrence retains zero through six source fractional digits or null unknown.

`NoticeRecorded` contains only safe recording references after commit. `CommittedNoticeDispatchFailed::event()`
distinguishes failed later fact dispatch from retained persistence; retries never replay publication. Storage uncertainty
closes the boundary without claiming rollback/absence. Trusted bounded idle `readAfter()` reconstructs retained rows,
not public authorization or complete catch-up. `RemoveNotices` selects 1–100 exact scope/producer/ID/position tuples
bound to the namespace incarnation. `NoticeRemover` and its routed handler invoke independent current
`NoticeRemovalAdmission` for the whole selection before lookup and all matching retained content/relationships
before deletion. Unknown, denied, repeated, stale-position and stale-namespace targets share fixed rejection with
no selected mutation; no existence/count response distinguishes a concealed record from a missing one. The same
actual transaction/lock atomically cascades notice/relationship/comparison deletion and advances
one bounded global `lost_through` watermark while retaining ordering high water, even when the feed becomes empty.
No removal event, receipt, cleanup eligibility, archive, public mutation Tool or outbox is defined. Comparison lives
only with retained evidence; deletion ends deduplication, and new admitted same-ID content is a later recording,
not restoration. Old removal targets cannot delete that new incarnation.

Trusted `readWindow()` returns `Application\Mcp\Notice\Data\NoticeRecoveryWindow` with coherently locked bounded rows,
namespace, committed high water and conservative global loss boundary. Its continuity predicate is storage proof
only: missing/corrupt state fails, and changed/retired namespace invalidates old proof. No per-ID/per-scope tombstones,
body/comparison archive or unbounded loss log remains. Global counters and loss changes must never be projected
across concealed scopes; partial/unproven access requires conservative public uncertainty independent of concealed
activity unless a narrower safe proof is established. TICKET-00046 owns protected continuation, current access/
selection continuity and public completeness semantics. Empty rows alone never establish catch-up, expiry or receipt.
Exclusive loss-evidence retirement preserves high water, rotates namespace and conservatively marks loss through
high water; no ordinary retirement/reset API is supplied. Consumers own safe capture, access, retention and operations.
See [safe notices](docs/safe-notices.md) for composition, all numeric bounds, qualified recovery, compatibility and
failure semantics. Notice additions remain provisional against tags; no development schema migration is promised.

**Authorized retained-notice inspection:** `Domain\Mcp\Notice\NoticeSelection` and
`Query\{SummarizeNotices,ListNotices,GetNotice}` route through Application Notice handlers and fresh
`Service\NoticeReadAdmission` to separate `NoticeReader`/`Adapter\Mcp\Notice\Dbal\DbalNoticeReader`. Trusted
`NoticeAccess` scope grants and optional exact restrictions intersect caller selection before counts/projection;
whole safe metadata and all relationships require permission, never a supplied ID/topic/recipient/SUPER_ADMIN label.
`Data\NoticeSummary` counts all encountered admitted matches once with complete bounded topic/type dimensions
(128 each), explicit audience/unknown counts and time extrema. `NoticePage` uses strict live page/per-page 1/32,
size at most 32, offset at most 1,000,000, recorded_at ASC/DESC with private position ties; `NoticeView` exposes
safe content and server time, not global positions/state/comparison. Filters cover topic, subject Agent, intended
recipient, type, producer/author/causes and half-open recording/occurrence instants without altering source precision.
Direct detail freshly authorizes independently of listing; unknown/denied/disappeared is the same null, not expiry.
Fixed `NoticeReadFailed` differs from successful empty results. Actual three-database reads scan bounded 100-row
batches with finite retained output, no business mutation/event, transaction or snapshot/catch-up promise.
No schema/index, stream/UI/Mercure or consumer-policy change is introduced by inspection. See
[notice inspection](docs/notice-inspection.md) for composition, finite bounds, full-selection semantics, linear-scan
cost, live consistency and additive compatibility classification.

**Authorized retained-notice recovery:** `Domain\Mcp\Notice\Query\RecoverNotices` routes through
`Application\Mcp\Notice\QueryHandler\RecoverNoticesHandler`, fresh `NoticeReadAdmission` and `Service\NoticeRecovery`.
The same `NoticeAccess` snapshot supplies optional trusted `NoticeRecoveryIdentity` (caller, nullable policy epoch,
explicit namespace-evidence permission). Missing caller rejects; unproven epoch or concealed global evidence always
reports uncertain. Canonical selection/grants/caller/epoch/serving parameters bind a confidential authenticated
continuation; changes reject and require restart so newly eligible older data cannot be silently skipped.
`NoticeStore::readWindow` accepts one scope or 0–16 scopes, merging 1–100 retained rows in committed-position order
under one coherent lock with namespace/high-water/loss evidence. `Data\NoticeRecoveryBatch` returns 1–32 bounded
whole safe notices (or empty), complete/incomplete/uncertain and an opaque examined-boundary continuation, including
for subsequent polls after completion. Scan exhaustion is not catch-up; known loss stays sticky, removed final rows
are not expiry, missing evidence fails and retired namespaces reject. No raw counters/gap details, deleted bodies,
retention lease, processing acknowledgment, mutation or read-side event is supplied.
`NoticeContinuationProtector` is an inward port; optional Adapter `SodiumNoticeContinuationProtector` uses dedicated
rotating 32-byte keys, XChaCha20-Poly1305 and padded fixed 1,432-byte tokens, with a 300-second default TTL (1–900).
Explicit `Application\Mcp\Notice\RecoverNoticesTool` registers only `notices.recover` on the existing guarded HTTP
composition through QueryBus; Tool availability and Query access remain independent. Default Query projection is
65,536 bytes (configurable 2,048–262,144) and complete encoded MCP response 1,048,576 bytes including structured/text
duplication, escaping, ID and actual central metadata. Oversized single notices/results and storage/output failures
return no advanced continuation, never truncation or empty success. Consumers own cadence/current-data reload,
policy epochs, safe capture, routes and deployment. See [notice recovery](docs/notice-recovery.md) for composition,
exact APIs/bounds, completion and restart meanings, compatibility and actual three-database guarded HTTP evidence;
controlled publication failure is not live MCP/Mercure or deployed-client qualification.

**Optional retained-notice publication:** `Application\Mcp\Notice\Publication\RecordNoticeAndPublish` executes the
synchronous recording Command before `NoticePublication::publish(GetNotice)`. The latter reconstructs currently
admitted committed content through the shared Query, then independently asks consumer `NoticePublicationPolicy`
for a fresh whole-audience `NoticePublicationGrant`. One permitted topic, explicit public/private mode and selected
retained fields map through existing Publisher/PrivatePublisher and optional Mercure adapters. Mandatory versioned
scope/producer/notice identity, distinct selected relationships and null-versus-empty recipients stay truthful;
no target read, arbitrary body, MCP envelope or full Task state is sent. Topics use a deliberate 256-byte HTTPS
DNS/path subset, lists select at most 10 fields/16 existing metadata keys, references count at most 32 and actual
escaped encoded data at most 4,096 bytes. Invalid/denied/missing-private cases never fall back to public.
Recording exceptions propagate before optional output; committed publication failures return fixed safe statuses
and cannot undo data. Private redaction-aware diagnostics may fail without changing a failed outcome to success.
Repeated attempts reauthorize and can duplicate sends, including deduplicated recording retries; no outbox, delivery
ledger, automatic retry, eventual-send or receipt/processing guarantee exists. Shared inspection/recovery/polling
reauthorize independently and remain Mercure-free; consumers own topic/browser/hub policy, scheduling and reload.
The six additive public declarations are provisional against tags; existing ports/schema/dependencies stay unchanged.
See [notice publication](docs/notice-publication.md) for composition, precise limits, exception/uncertainty semantics,
compatibility and actual three-database/controlled-HubInterface evidence, not deployed browser qualification.

## Access control language

| Term | Meaning in Fight AccessControl planning |
| --- | --- |
| **Invited registration** | Creation of a user identity without a password by an authorized actor. The user remains pending activation until a one-time activation grant is completed. It is distinct from public self-registration. |
| **Pending activation** | The account state of an invited user who has not completed the one-time password-setting journey. A pending user cannot authenticate or authorize application operations. |
| **Disabled user** | An active identity placed into a reversible administrative suspension. Disablement revokes every session and denies authentication and authorization; explicit enablement returns the identity to active state without restoring prior sessions. |
| **Deleted user** | A protected soft-deleted identity retained as a tombstone for stable audit and historical references. Deletion revokes every session and grant and is reversible only through an audited super-admin restoration process, never ordinary enablement. Consumer-owned retention or erasure workflows govern personal-data removal. |
| **Canonical user email** | The normalized login and delivery address uniquely reserved across pending, active, disabled, and soft-deleted users in a single-tenant project. Relationships use stable `UserId`, not email. Consumer persistence owns the unique constraint so a future multi-tenant project can scope it by tenant without rewriting identity references. |
| **Email change** | A request-and-confirm identity journey that reserves a new canonical email while the old address remains authoritative. Confirmation by a purpose-bound single-use grant changes the address, advances authentication version, and revokes every session; cancellation or expiry releases the reservation. |
| **Email-change grant** | A purpose-bound single-use aggregate authorizing confirmation of one reserved canonical email. It is not interchangeable with activation or reset, permits only one active grant per user and reservation per target address, and uses a configurable expiry with a 24-hour starter default. |
| **User restoration** | An audited super-admin recovery of a deleted user into an explicitly selected `ACTIVE` or `DISABLED` state. Active restoration retains the password for confirmed accidental deletion; disabled restoration requires the normal password-reset/reactivation path. Neither mode restores sessions or grants, and both advance authentication version. |
| **Security email template model** | The constrained, documented values AccessControl passes to a project-owned activation or password-reset template: safe display values, the one-time action URL, and expiry. It never exposes a User or grant aggregate, password hash, credential digest, or general security context. |
| **Security email delivery** | Durable invocation-neutral work for an activation or reset email. It references the user and grant, stores any recoverable raw credential only as short-lived ciphertext under a key outside the database, and destroys that ciphertext after confirmed delivery or bounded terminal expiry. The consumer decides whether its handler runs synchronously or asynchronously; the record is not the authoritative grant. |
| **Activation grant** | A hashed, expiring, single-use credential that permits one pending user to set an initial password. Reissuing an activation grant revokes its predecessor. |
| **Activation delivery** | The retryable attempt to send an invited user the current activation grant. Registration remains durably pending when delivery fails; failure is visible to the inviter, and resending revokes the predecessor grant rather than creating another user. |
| **Pending invitation correction** | An audited super-admin correction of a pending user's canonical email. It revokes the prior activation grant and delivery, releases the old address, and creates a fresh grant and delivery for the corrected address without activating the user or reusing the credential. |
| **Managed Role** | A project-defined Role with a stable UUID, authoritative name, and exact Managed Permission membership. It is readable but runtime-immutable; version-controlled definition review and reconciliation are its mutation path. Fight Common `HashSet` differences derive permissions to add and remove. |
| **Managed Permission** | A project-defined Permission with a stable UUID, authoritative uppercase-underscore name, and required `ADMIN_SAFE` or `SUPER_ADMIN_ONLY` tier. Typed authorization references and definitions must match in both directions. It is readable but runtime-immutable and is reconciled into persistence. |
| **Custom access-control record** | A Role or Permission created outside the Managed Role/Permission definitions and owned by runtime administration. Super administrators may mutate it subject to authorization and invariants; reconciliation preserves it. |
| **Managed Permission removal** | An intentional code-and-data change that first proves no consumer code path still checks the Managed Permission, then explicitly removes its Managed Role assignments and live record through reconciliation. Historical audit evidence retains the permission name without depending on the live record. |
| **Access-control deletion guard** | The invariant that rejects deletion of a custom Role or Permission while any user, role, or other live record still references it. Deletion never cascades; dependencies must be explicitly detached or reassigned first. |
| **Authenticated principal** | An immutable, framework-neutral representation of an active user for one request, containing identity, account and authentication version, and role and permission snapshots. It is not a User aggregate or framework security token. |
| **Access token** | A short-lived, purpose-bound JWT used from client memory to authenticate API requests. It is not durable session state and does not independently authorize stale account or permission data. |
| **Refresh session** | A server-tracked login on one browser or remote device. It owns an opaque refresh-credential family, revocation state, trusted persistence policy, and the authority to mint replacement access tokens. Multiple refresh sessions may coexist for one user, and authoritative state is shared across application container instances through a database or Redis adapter with atomic mutation semantics. |
| **Refresh conflict** | A bounded concurrent-refresh outcome in which a credential was just consumed by another request from the same browser session. It issues no credential, does not revoke the family, and tells the client to await the winning cookie update before one bounded retry. Reuse outside the conflict window is treated as compromise. |
| **Remember me** | A login choice that permits the current refresh session to survive browser restart and use a longer configured lifetime. It never changes access-token lifetime or grants additional authority. |
| **Current-session logout** | Revocation of only the refresh session represented by the presented credential. Other browser and device sessions remain active. |
| **Global session revocation** | Revocation of every refresh session for one user. Logout-all, disabling the account, changing the password, and resetting the password require this effect. |
| **Session management** | The capability to inspect coarse, non-secret metadata for refresh sessions and revoke one or more of them. Users manage their own sessions; a principal with `MANAGE_USER_SESSIONS` may manage another user's sessions, and every administrative revocation is audited. |
| **Required security audit** | A typed, secret-free audit record whose durable write is part of a classified sensitive command's success. It records actor, target, action, outcome, time, correlation identity, relevant session or delivery identities, and any required reason. A consumer uses one atomic UnitOfWork or atomically creates a durable audit handoff; ordinary post-commit event publication is not sufficient. |
| **Session idle timeout** | The maximum period since authoritative session activity before reauthentication is required. Rotation may advance this deadline but never the session's absolute timeout. |
| **Session absolute timeout** | The maximum lifetime of a refresh session from authentication or explicit reauthentication. Possession or rotation of a refresh credential cannot extend it. |
| **Purpose-bound token** | A signed token accepted only by validation rules for its one declared use, issuer, audience, type, algorithm, time window, subject, token ID, and key ID. API access and realtime subscription tokens are different purposes and are not interchangeable. |
| **Plain password** | A transient typed AccessControl value accepted only at password use-case and hashing boundaries. It is never persisted, serialized into events, or logged. |
| **Password hash** | The opaque typed result of password hashing stored by the User aggregate. AccessControl does not expose the implementation string through read models. |
| **Password verification** | The typed result of comparing a plain password with a hash, including validity and whether successful authentication should replace the stored hash. |
| **Session bootstrap** | The SPA loading phase that always begins without an access JWT and awaits the refresh endpoint. A valid refresh session returns a fresh access JWT and authoritative principal before authenticated UI or API work proceeds. |
| **Proactive refresh** | Refresh begun at the configured safety boundary before access-token expiry. The starter default treats a 15-minute token as needing refresh at ten minutes old, using bounded clock-offset handling and one shared awaited refresh operation. |
| **Authentication abuse control** | Configurable throttling and observation applied by both normalized account identity and source signal, with generic public outcomes and no attacker-triggerable permanent account lockout. CAPTCHA and external risk scoring are replaceable consumer integrations. |
| **Realtime topic authorization** | The server-side decision that maps an authenticated principal and requested realtime topics or channels to the exact private subscriptions that principal may receive. Client display state and possession of a topic name are not authority. |
| **Private realtime update** | A published update deliverable only to a subscriber whose short-lived subscription credential authorizes its exact topic or channel. Publication and subscription credentials are separate from ordinary API access tokens. |
| **Public realtime envelope** | The versioned browser-facing representation of one explicitly publishable domain event. It carries a stable public name, message identity, occurrence time, authorized topic, an allowlisted payload, and allowlisted technical metadata without exposing the internal PHP message serialization. |
| **Realtime event transformer** | One Application component dedicated to one publishable domain event that maps it to a public realtime envelope. Transformers contain no provider transport logic and are composed as a registry without a central event-type switch. |
| **Public realtime update** | An explicitly designated update whose underlying information is already available without authentication. Topic identifiers are discoverable rather than secret, so public delivery is never appropriate for user, account, job, administration, or personalized data. |
| **Realtime revocation window** | The bounded period during which an already-open provider connection may remain after its backing application session is revoked. Revocation prevents credential renewal immediately; provider-specific disconnection may shorten the portable maximum. |

## Engineering quality language

| Term | Meaning in Fight Common |
| --- | --- |
| **Coding standard** | The reusable, opinionated set of PHP formatting, documentation, and declaration-layout rules published by Fight Common. A consuming project chooses the files to which the standard applies. |
| **Public API manifest** | The machine-readable inventory of PHP declarations for which Fight Common makes a versioned compatibility promise. Its initial baseline contains every production-autoloaded declaration in the authoritative published `1.1.0` source unless that declaration was already marked `@internal`; every later production declaration must be deliberately classified. For each public declaration, callable, constructible, extensible, and implementable are independent consumer-operation promises. A non-final class is extensible only when affirmative design or history evidence establishes that promise. |
| **Behavioral contract** | An explicit consumer-facing behavior promised by published documentation, public PHPDoc, or an accepted ADR. A stable contract ID links the normative statement to designated compatibility evidence. Ordinary tests and incidental observed behavior do not create behavioral contracts by themselves. |
| **Compatibility contract** | The combined versioned promises covering the public API manifest, behavioral contracts, exceptions, serialization, persisted data, framework adapters, accepted runtime environments, Composer package metadata, and the coding standard. Compatibility is classified by consumer-visible effect rather than PHP signatures alone. |
| **Deprecation** | A minor-release notice that a still-functional public contract is planned for removal or incompatible change in a future major release. A deprecated contract remains supported for at least one released minor before removal. TASK-00173 adds 50 property-name typed getters across 14 released HTTP/OAuth/MCP classes while retaining the same public readonly storage, construction, identity, arrays and serialization. Direct reads are deprecated in documentation only, without runtime notices; the first deprecating release is unassigned. Standards is wholly excluded. See the [complete migration map](docs/maintenance/public-property-getters.md); removal/private conversion needs separate future-major authorization. |
| **Supported release line** | A `major.minor` maintenance line whose phase, exact branch, release baselines, allowed fix classes, and exclusive UTC support boundary are declared in the canonical support-policy data. Only its latest patch release is supported. |
| **Release baseline** | The canonical release tag and immutable peeled commit OID against which a candidate's compatibility is certified. Baselines are selected by release class and must have valid ancestry; tooling never guesses from tag ordering. |
| **Affected line** | A supported release line on which focused public behavioral evidence reproduces a defect. An introducing commit may be unknown, but affected status may not be inferred from ancestry alone. |
| **Compatibility exception** | John's explicit decision for one otherwise incompatible patch candidate addressing security, imminent data loss or critical interoperability when no compatible repair exists. Present the exact version/commit, consumer impact, mitigation, evidence and recovery in ordinary language under [ADR 0028](planning/adr/0028-clear-package-release-decisions.md). It is not reusable or permission to call a known break compatible; no typed or digest-bound approval record is required. |
| **Compatibility manifest** | The committed `compatibility/manifest.json` authority that classifies public declarations and consumer operations, links behavioral contract IDs to normative documentation and fixtures, records package-surface promises, and identifies its authoritative baseline. Generated inventories validate completeness but do not replace intentional classification. |
| **Compatibility finding** | A consumer-visible difference or unresolved risk between a published baseline and candidate, explained with relevant evidence. Resolve material uncertainty before shipping under [ADR 0028](planning/adr/0028-clear-package-release-decisions.md); synthetic finding IDs and the historical fourteen-row release plan are not required. Certifier success alone does not establish compatibility. |
| **Quality gate** | A mandatory automated check that must pass before work is accepted. A report without an enforced pass/fail condition is not a gate. |
| **User acceptance testing (UAT)** | A bounded human review of a user-facing journey after its automated checks pass. A project may adopt UAT as a required pre-completion gate; doing so supplements automated evidence and does not replace it. |
| **Architecture gate** | The quality gate that verifies inward dependency direction between Domain, Application, and Adapter. |
| **Coverage gate** | The quality gate that compares covered statements with all executable statements. Fight Common requires exact complete statement coverage rather than a rounded percentage. |
| **Dependency freshness lane** | CI verification performed after resolving the latest versions permitted by the package constraints. It is distinct from a local build using already-resolved dependencies. |
| **Product build** | The complete ordered quality gate invoked by `./bin/build`; it verifies the checkout but grants no independent acceptance or delivery authority. |
| **Build workflow** | An explicitly invoked shared Team Lead workflow coordinating one TASK through Engineer, independent Review, QA and authorized Land to a PR. It is distinct from the product build and does not merge, release or deploy. |
| **Candidate commit** | The exact clean `HEAD` commit certified by `bin/release certify`. A branch name or dirty checkout cannot substitute for this immutable identity. |
| **Certification record** | The compact, digest-bearing JSON record emitted by `bin/release certify`. It binds the version, candidate commit, archive digest, dependency lanes, package surface, installed-consumer result, and cited historical starter receipts. |
| **Publication authorization** | John's informed `ship <version>` decision for the unchanged certified candidate and stated publication form, after reading the summary required by [ADR 0028](planning/adr/0028-clear-package-release-decisions.md). It covers the remaining normal release operation and applicable reviewed housekeeping, not unrelated releases, branch deletion or application deployment. Certification alone authorizes no effect; changed inputs, failures or new material risk require a fresh decision. |
| **Partial publication** | A fail-closed condition in which an external effect may have occurred but its postcondition is uncertain. Recovery requires operator-directed reconciliation and fresh verification. |
| **Reviewed fix** | An immutable, already-reviewed change set identified by exact commit OIDs and its merged pull request provenance: base and head OIDs, approvals, required-check conclusions, and merge receipt. A branch or pull request number alone is not a fix identity. |
| **Affected-line patch** | A patch workflow execution for one supported release line, based on that line's exact current tip and independently classified and certified for compatibility. EOL lines are read-only. |
| **Forward port** | A separately reviewed and certified application of an older affected-line fix to a newer affected supported line, ordered oldest to newest and carrying predecessor and source provenance. |
| **Urgent release mode** | Guided metadata and operator ergonomics for a time-sensitive patch. It collects the ordinary evidence and approvals into one action packet; it does not bypass review, compatibility, certification, or authorization. |

## Framework support language

| Term | Meaning in Fight Common |
| --- | --- |
| **Supported framework line** | A stable major line of a consumer framework for which Fight Common promises a tested, documented composition. The range is expressed as an exact Composer constraint and is deliberate Fight Common policy, not the framework's own support policy. |
| **Current-only support window** | The default Fight Common posture: only the current stable major line of each framework is supported. The window never widens beyond current plus the immediately-preceding major. |
| **Widen trigger** | The event that moves a supported range from current-only to current + previous: a framework shipping its next major version. The widened form is the union `^new || ^current`. |
| **Tighten trigger** | The event that drops the oldest line from a widened window: the framework stopping maintenance of that line. A line is never silently carried past the framework's own maintenance boundary. |
| **Default composition** | The one opinionated, documented, Composer-installable set of native, portable, and third-party facilities Fight Common recommends for a capability in a starter. Replaceable alternatives are composition-root choices, not obligations to test every combination. |
| **Recommended composition** | A default composition that fills a capability with no first-party native facility in its framework. There is no unsupported capability: every public contract has a recommended, documented solution. |
| **Starter-owned wiring** | Integration code that lives in the starter's composition root rather than in a shared Fight Common package. Examples include a Laravel ServiceProvider, a Yii configuration provider, a CodeIgniter `Config\Services`, a Slim explicit container, and Symfony project-owned service loading. |
| **Capability worksheet** | The per-framework record, for every public Fight Common contract, of the native facility, existing reusable binding, starter-owned wiring, new-shared-adapter decision, functional journey, and remaining unknown. |
| **Isolated fixture** | A minimal Composer verification root requiring the Fight Common candidate plus exactly one framework's constraint set, resolved at lowest and latest as evidence. It is a dependency manifest and compatibility probe, not a starter application. |
| **Combined resolution lane** | The root dependency set in which all supported frameworks and optional adapter packages must resolve together. Each widened previous line is proven in its own isolated fixture before the combined lane is re-resolved. |
| **Forward-compatible seam** | A stable Fight Common port or boundary chosen so a future native facility (for example a released `yiisoft/queue`) can replace the current composition without breaking the public contract. |

## Proposed Event Sourcing language for 1.2

These terms describe the planned optional extension. They do not describe existing production APIs until their tickets are implemented.

| Term | Planned meaning |
| --- | --- |
| **Event-sourced aggregate** | The framework-free lifecycle contract used by repositories for an aggregate whose state changes only by explicitly applying domain events. It owns a static named constructor for reconstitution from history and exposes its identity through the existing `Identifier` contract. |
| **Aggregate root** | The reference abstract implementation of the event-sourced aggregate contract. It stores the consumer's domain-specific `Identifier`, implements `id()`, and owns version tracking and pending-event lifecycle while the consumer aggregate owns explicit event routing and semantic state transitions. |
| **Recorded event** | A new event held by an aggregate until it is persisted. |
| **Released event** | A recorded event returned in recording order and immediately removed from the aggregate's pending collection for persistence. |
| **Stored event** | An append-only storage envelope containing an event message, stream version, stable event name, persisted schema version, and global position. Its stored identity is the stable event name, not the event payload's PHP class name. After upcasting, the message contains the current payload while the envelope retains the schema version actually persisted. Publication may derive a same-ID message with additional technical metadata without rewriting the stored snapshot. Global position provides prefix-stable commit visibility: once a position is visible, no lower-positioned event may become visible later. |
| **Aggregate definition** | Repository configuration pairing one stable aggregate name with the current PHP aggregate class. It maps durable stream identity without requiring a global aggregate registry. |
| **Stream** | The ordered event history of one aggregate, identified by a stable aggregate name and aggregate identifier rather than by the PHP aggregate class name. |
| **Expected version** | The stream version observed before new events were recorded and checked during append for optimistic concurrency. An empty stream has version zero and its first event has stream version one. |
| **Event Store** | The append-only port that accepts event messages, maps them to durable event identity and schema, writes versioned streams, and returns hydrated stored events from stream-ordered and globally ordered reads. |
| **Event mapper** | The explicit, bidirectional registry for one Event Store that maps a stable event name to the current PHP event class and reconstructs an event message from stored data. It must contain every event persisted in that store, and aliases must be unique across the store. Consumer configuration registers every stored event; attribute scanning, convention-based discovery, and FQCN fallback are not part of the contract. A PHP class rename changes the mapping without changing stored history. |
| **Event mapping provider** | A portable declaration of typed event mappings owned by one bounded context. It declares a durable event namespace plus locally unique event names; each typed mapping owns the current PHP class, current schema version, and complete upcaster chain. The event mapper qualifies local names into canonical stored names. Multiple providers may compose the complete catalog for one Event Store and may be registered directly or collected by optional framework integration. |
| **Upcaster** | A one-event-to-one-event in-memory transformation from one stored payload schema version to the next integer version. It does not rewrite stored history, and class renames alone do not require upcasting. |
| **Projector** | A stably named idempotent consumer that declares the current event payload FQCNs it understands and moves matching, already-upcasted stored events into a read model. Its stable name owns checkpoint identity independently of its PHP class. Projectors do not know stable storage aliases or legacy schemas. |
| **Projection runner** | The worker-facing service that polls stored events for one projector, handles them in global order, and advances that projector's checkpoint only after each successful projection. |
| **Read model** | Query-oriented data derived from events; it is not the authoritative event history. |
| **Checkpoint** | The last global event position successfully processed by one named projector. Processing means either successfully projecting a declared current event type or successfully skipping an undeclared type. The runner advances it per event, not only after a complete fetched batch. |
| **Event publication** | Post-commit fan-out of stored event messages through the Event Dispatcher in a worker process separate from projection. Publication attempts all matching subscribers and does not automatically replay an event because a subscriber failed. |
| **Publication cursor** | The last global position for which event publication attempted all matching subscribers. It advances after the complete attempt even when subscriber failures were collected; unlike a projection checkpoint, it records attempted fan-out rather than successful state projection. |
| **Event publication runner** | A stably named worker-facing service that polls committed stored events, dispatches their original event messages through a `SynchronousEventDispatcher`, reports collected subscriber failures, and advances its publication cursor after each complete attempt. Multiple named runners may publish independently from one Event Store. |
| **Publication cursor store** | The port that loads and monotonically saves attempted-fan-out positions independently by stable publication name. It has no reset operation in 1.2 and is distinct from a projector checkpoint store even when adapters share persistence mechanics. |
| **Event dispatch failure** | The aggregate exception thrown by a Synchronous Event Dispatcher only after it has attempted every matching handler and one or more failed. It contains the ordered handler failures and proves fan-out completed despite those failures. |
| **Event handler failure** | A transient value pairing one failed callable description with its original `Throwable`. It allows callers such as the Event Publication Runner to distinguish completed fan-out with handler failures from an unexpected dispatcher or infrastructure failure. |
| **Event publication failure** | A structured snapshot of one stored-event publication attempt that had handler failures. It includes publication, stored-event, and message identity, the UTC time at which dispatch began, and each failed callable's description, exception FQCN, integer code, and bounded diagnostic message. It excludes original throwables, stack traces, event payloads, and message metadata. |
| **Publication failure recorder** | The port used by the Event Publication Runner to record one aggregated `EventPublicationFailure` before advancing its publication cursor. Fight Common 1.2 provides in-memory and DBAL implementations plus a PSR-3 logging decorator that requires and delegates to another implementation. |

Event Sourcing remains additive to CQRS. Aggregates record plain `Event` payloads; repositories create messages and storage envelopes. Projectors consume stored events independently using at-least-once delivery.

`EventMessage` remains the live messaging contract and may identify its payload by PHP class. Durable Event Store adapters persist the stable event name and schema version instead of treating the serialized `EventMessage` payload type as the stored event identity. Only events persisted by the Event Store require mappings; events used solely by the Event Dispatcher do not.

An event namespace is durable storage identity rather than a display name or PHP namespace. For example, an `orders` provider may declare the local name `order-placed`, producing the canonical stored name `orders.order-placed`. Renaming a bounded context in code does not by itself rename its stored event namespace.

Schema versions begin at one. Each event mapping owns and validates an unbroken upcaster chain from version one to its declared current schema version; version-one mappings have no upcasters. Missing, duplicate, or skipping steps make registration invalid.

Stored-event mapping is fail-closed. Reading an unknown stable event name, a schema newer than the registered current version, or an older schema without a complete upcast path raises a dedicated mapping failure. Readers never downcast, skip, or best-effort hydrate unsupported history.

Event Store append is idempotent by message identity. If every requested `MessageId` already occupies the intended stream positions immediately after the supplied expected version, the operation succeeds as already appended without writing again or comparing payload or metadata content. A partial batch match or any message ID in a different stream or position fails closed.

Event Store implementations own event mapping on both sides of persistence. Append accepts `EventMessage` instances and snapshots their current metadata while mapping payload classes to stable event names and current schemas. Reads upcast payloads, hydrate current event classes and messages, and return `StoredEvent` envelopes. Aggregate repositories do not handle aliases, schemas, or upcasters.

`Meta` is a mutable technical-context value, but messages isolate it: construction stores a copy, `meta()` returns a copy, and `withMeta()` or `mergeMeta()` creates a same-ID envelope with a new snapshot. The single append persists one metadata snapshot; later derived publication timing or transport context does not update Event Store history or affect domain reconstitution.

The Event Store preserves the EventMessage creation timestamp as technical envelope time, normalized to UTC with microsecond precision. It does not infer domain occurrence time from that value, and ordering remains defined only by stream and global positions.

Fight Common 1.2 does not introduce an EventMessage factory or clock abstraction. Aggregate repositories create messages through the existing `EventMessage::create()` contract; callers and tests that require exact IDs, timestamps, or metadata may use the public constructor.

Global ordering must be safe for checkpoint polling, not merely unique and increasing after all transactions finish. Durable stores serialize global-position allocation inside the append transaction so a reader that checkpoints a visible position cannot later miss a lower position committed out of order. An auto-increment event-table key alone does not provide this guarantee on MySQL.

Projection batches bound polling work but do not define checkpoint atomicity. The runner handles and checkpoints each event in global order so a failure resumes from the failed event rather than replaying the successfully handled prefix of its fetched batch.

Projectors declare current event payload FQCNs, following the same code-facing convention as Event Subscribers. The Event Store has already resolved stable aliases, applied upcasters, and hydrated current payloads before projection routing. The runner invokes a projector only for declared FQCNs and advances its checkpoint over undeclared types as successful skips. Adding a handled type later requires a checkpoint reset and rebuild to process its history.

Each projector declares an explicit stable name, such as `orders.order-summary`, used as its checkpoint key. Renaming or moving the PHP projector class does not rename or reset that durable processing identity.

Normal checkpoint saves are monotonic and reject backward movement. `reset(projectorName)` is a distinct administrative operation that returns one projector to position zero. Consumers stop its worker and clear or recreate the read model before reset; Fight Common does not coordinate read-model replacement. Arbitrary backward checkpoint positions are outside 1.2.

A projector failure stops the current batch immediately, leaves the failed event uncheckpointed, and propagates to the caller. The core runner never processes a later global position after an earlier one fails; consuming workers own retry, backoff, and dead-letter policy.

`ProjectionRunner` and `EventPublicationRunner` execute in separate worker processes with different guarantees. Projection is ordered at-least-once processing: successful idempotent state updates advance a projector checkpoint, while failures stop and retry. Event publication considers only committed stored events, attempts every matching subscriber, records one aggregated `EventPublicationFailure` through a `PublicationFailureRecorder` when any subscribers fail, and advances its position through a distinct `PublicationCursorStore` after the complete attempt without automatically redispatching that event. A crash before cursor advancement can still cause duplicate delivery, so subscriber idempotence remains advisable.

Fight Common 1.2 records publication failures but does not define automatic or targeted replay. A consuming project may implement replay, including invoking only subscribers that originally failed, and a proven implementation may inform a later shared contract.

Each subscriber failure identifies the invoked handler by its current subscriber FQCN and method. Symfony's default service registration uses the subscriber FQCN as its service ID, so a consuming project can resolve and invoke that handler again while the class and method remain unchanged. This is operational identity rather than durable storage identity: subscriber refactors may require project-owned migration or interpretation of old failure records.

The Event Dispatcher continues to support handlers registered directly as arbitrary callables. Named functions are described by function name and closures by a non-replayable `Closure` descriptor. Those fallbacks preserve dispatcher compatibility, but only a class-and-method description is an intended candidate for project-owned targeted invocation.

`SynchronousEventDispatcher::dispatch()` retains its `void` return contract and existing two-phase ordering. It first attempts event-specific handlers by priority, then attempts `AllEvents` handlers by priority. Each handler invocation catches any `Throwable`, including PHP `Error` values such as `TypeError`, and continues within and across both phases. Afterward it throws one `EventDispatchFailed` aggregate containing ordered `EventHandlerFailure` values with their original throwables. The aggregate is thrown only after both phases complete. Failures while resolving or preparing handlers remain dispatcher infrastructure failures and do not prove fan-out completed.

The Event Publication Runner catches `EventDispatchFailed`, converts it into one `EventPublicationFailure`, records it, and may then advance the publication cursor. Any other dispatcher exception propagates without cursor advancement because the runner cannot prove that every handler was attempted.

At the beginning of each stored-event dispatch attempt, the runner captures the current time directly with `DateTimeImmutable`, normalizes it to UTC with microsecond precision, and reuses that value if it creates an `EventPublicationFailure`. Fight Common does not introduce a clock abstraction solely for this operational timestamp. Comparing it with the EventMessage creation timestamp can estimate storage-to-publication latency without treating either timestamp as domain occurrence time.

Conversion snapshots each handler failure as its callable description, exception FQCN, integer exception code, and bounded diagnostic message. The message is normalized to valid UTF-8, stripped of unsafe control characters, and limited to 4 KiB. Fight Common does not attempt secret detection or redaction; consuming projects treat exception messages as user-surfaceable text and do not place credentials or sensitive payload data in them. The publication failure does not retain the original `Throwable`, stack trace, event payload, or message metadata. Both DBAL persistence and the PSR-3 logging decorator consume this same portable snapshot; full throwable diagnostics remain transient on `EventDispatchFailed`.

The DBAL publication-failure recorder durably stores one aggregated record per publication name and global position, including message and event identity, attempt time, and the bounded handler-failure snapshots. Its purpose in 1.2 is operational evidence; failure queries and replay APIs remain deferred.

The PSR-3 publication-failure recorder is a decorator rather than an independent persistence choice. It requires another recorder, logs an `EventPublicationFailure` first, and then delegates the same record to that recorder, such as the in-memory or DBAL adapter. Either logging or delegation failure propagates and prevents publication-cursor advancement. A failed delegate may therefore produce a duplicate log on retry; publication name and global position provide the correlation key. This composes observability with the desired storage behavior and prevents logging from silently replacing recording.

Subscriber failures do not block publication-cursor advancement after their aggregated publication failure is successfully recorded. Failure-recorder or cursor-store infrastructure errors do block advancement and propagate, causing the publication attempt to retry. Failure recorders are idempotent by publication name and global position so partial recording or a crash does not create duplicate operational records.

Each Event Publication Runner has an explicitly configured stable publication name. Publication cursors and failure records are scoped by that name, allowing independent subscriber pipelines to consume the same Event Store without sharing progress.

The Event Publication Runner requires `SynchronousEventDispatcher` because its contract observes completion and collected failures from every matching subscriber. An asynchronous dispatcher acknowledges transport submission rather than subscriber execution and is outside the publication-runner boundary.

Publication cursor saves are monotonic. `PublicationCursorStore` has no reset operation in 1.2 because resetting would redispatch stored history to subscribers that are not required to be idempotent. Whole-stream and targeted republication belong to the deferred replay workflow.

Releasing events is a fail-stop boundary. If a repository save fails after release, that aggregate instance is invalid and must be discarded; retry begins by loading a fresh aggregate. Event Store implementations may perform safe transient retries internally while they retain the released events.

Aggregate version is the number of events applied to its current state, including newly recorded events. Replay increments version without recording, and recording both applies the event and increments version. When releasing a batch, its expected version is the aggregate version minus the number of released events.

Aggregate event application is fail-closed. Recording or replaying an event that the aggregate does not explicitly route raises a domain failure. An intentionally state-neutral historical event is still routed explicitly to a no-op transition.

Reconstitution belongs to the aggregate rather than an infrastructure factory. A generic repository receives the aggregate class, loads its history, and invokes the aggregate's static reconstitution contract; the aggregate retains control of its constructor and initialization rules.

Aggregate reconstitution consumes ordered plain `Event` payloads. The repository unwraps stored envelopes and live messages before invoking the aggregate, keeping storage aliases, schema versions, positions, message identity, and metadata outside aggregate state. Domain-significant values belong in the event payload.

Generic event-sourced repository lookup uses nullable `find(Identifier)`. An empty Event Store stream becomes `null`, and reconstitution is not invoked. Application services and handlers decide whether absence is exceptional and choose the use-case-specific failure.

Each generic aggregate repository receives an `AggregateDefinition` for its one aggregate type. The definition supplies the stable aggregate name used to construct stream identity and the current PHP class used for reconstitution. A class refactor updates the definition without changing stored stream names.

An event-sourced aggregate identifier implements `Identifier`. Repositories convert it to its stable string representation when constructing a stream identity; the stable aggregate name distinguishes identical identifier strings belonging to different aggregate types.

## Language rules

- Prefer the exact public contract name when discussing a library capability.
- Distinguish a payload from its message envelope.
- Distinguish domain rules (`Specification`) from input validation rules.
- Distinguish persistence (`Repository`, `UnitOfWork`) from serialization.
- Distinguish File Storage, Filesystem, and File Transfer.
- Consumer applications own their business-specific aggregate, command, query, event, and read-model names.

## Release coordination language

| Term | Meaning in Fight Common |
| --- | --- |
| **Signed release tag** | A human-created annotated `vX.Y.Z` tag under the scoped ship decision, with signature, signer and peeled commit verified against the exact certification record. Signing secrets remain with John; certification does not create the tag. |
| **Immutable publication** | A verified immutable GitHub release of the exact signed tag and commit, with observed assets matching the stated publication form (which may have no uploaded assets). A mutable release is not equivalent provenance. |
| **Publication checkpoint** | The readable release summary and John's informed ship decision under [ADR 0028](planning/adr/0028-clear-package-release-decisions.md), followed by provider verification. The unused historical `release-publication` environment and per-effect approvals are not required. Actual host-enforced restrictions remain binding. |
| **Downstream projection** | Packagist's Composer metadata and distribution archive view of the GitHub release. It is observed and verified after GitHub publication but never becomes release authority. |
| **Incomplete publication** | A fail-closed condition entered when Packagist propagation, metadata, installation, or external-effect verification cannot complete. Recovery is explicit and remains bound to the certified candidate and publication receipt. |
| **Clean-install proof** | A bounded receipt from a temporary Composer consumer that resolves the exact published version through Packagist, verifies source and distribution metadata, and passes the approved public smoke behavior. |
| **Packagist observation window** | The bounded downstream verification period: poll at 15 seconds, 30 seconds, 1 minute, 2 minutes, then every 5 minutes for at most 30 minutes. Timeout produces `packagist_incomplete`. |
| **Release archive** | The Composer archive named `fight-common-X.Y.Z.tar` produced from the exact clean candidate commit. Its SHA-256 is recorded in the certification record. |
| **Clean-install receipt** | Evidence from a temporary Composer consumer that installs the candidate archive with `--no-dev`, exercises representative public behavior, and proves the maintainer-only `Fight\Release\` namespace is unavailable. |
| **Release command** | The repository-owned `bin/release certify <version>` executable. It certifies a clean exact commit and performs no merge, tag, push, GitHub, Packagist, or deployment effect. |

## Durable and ephemeral work

Committed planning state lives in `planning/`. The TASK Board displays executable work; the Roadmap planning
frontier displays decomposition. [Automatic parent completion](planning/CONVENTIONS.md#automatic-parent-completion)
closes eligible TICKETs and EPICs in the child-completion operation, counting live and archived children. TASK `done`
requires implementation acceptance, required verification, independent Review acceptance and satisfied required QA
(or justified overall N/A). Implementation awaiting Review/QA stays non-terminal with its handoff explicit; delivery
is separate. Historical completion records retain the meaning of their original policy. Current Board/Roadmap
TASK-routing summaries derive from the same ordered records; their authored planning/dependency narratives are
not competing current status. The offline build rejects stale summaries without rewriting them. Ordinary Land
records the verified PR URL and historical acceptance/publication, without a new delivery marker, persistent current
merge note/Board row, or post-merge Board cleanup for John. Land still directly checks the exact owned PR before
publication and at final handoff under the
[workflow binding](planning/agents/shared-workflows.md#delivery-pre-submit-and-final-handoff). Land closes the
accepted TASK/eligible parents and applicable current docs before final publication and retires eligible owned
local Git/Docker/storage. The tracked delivery marker/table/check/reconciliation mechanism is removed, including
legacy scans and recurring Work/planning intake/post-merge obligations. Planning/routing stays offline; preserve
historical acceptance/publication/reconciliation and independent reports. Closed-unmerged, non-terminal scope,
unintegrated or unverifiable outcomes still hold the affected action for its real owner. Human-only merge,
open-PR remote-head preservation, independent Review/QA/full verification and qualified resource cleanup remain;
no background watcher or execution after stopping is promised.

Coordination and evidence live under ignored `.runs/` directories and are not committed project history. The
current independent review handoff is `<base-worktree>/.runs/reviews/<TASK-ID>/review.md`, resolved and retained
under the [Review contract](docs/engineering/standards/Review.md). Important outcomes and deviations belong in the
TASK; a report's accept/revise verdict is distinct from QA and delivery authority. The current independent QA
report is `<base-worktree>/.runs/qa/<TASK-ID>/qa.md` under the [QA contract](docs/engineering/standards/QA.md).
The [shared workflow binding](planning/agents/shared-workflows.md) owns provider/target substitutions and prospective
Build/task-run paths without relocating historical evidence.

## Disposable database tooling

The project image copies its public `local.ini` with explicit readable permissions. A restrictive checkout
umask must not make that root-owned image configuration unreadable to the non-root gate user: doing so silently
loses the intended coverage mode and 512 MiB memory setting. No host trust, global settings or runtime protections
are changed.

`bin/build` and complete `bin/phpunit` own isolated MySQL/PostgreSQL fixtures through
`bin/.disposable-database-runtime` and its Python lifecycle. Creation intent, returned IDs and image-declared
anonymous mounts are saved privately before starting databases. Allocation uncertainty is saved before each
create; returned IDs and complete creator observations are durable before fallible image/volume inspection.
Cleanup establishes exact coverage of every original creator mount before removing any creator. An interrupted
capture resumes only against that same still-present fingerprinted creator and its original mounts; missing
identity/authority or a lost return value holds all destructive effects. Original receipts/failed attempts remain,
not rewritten into success or recovered by names. EXIT/INT/TERM cleanup normally stops exact owned
containers, removes them without force, then separately qualifies volumes against all consumers (including
stopped containers) and verifies absence. Cleanup failure makes an otherwise successful caller fail; an earlier
workload failure retains its original nonzero exit. Images/shared builder caches are accounted for and retained,
not deleted by workload association. No global prune or shared service shutdown is used.

Receipts default to the Git-common-directory parent’s ignored `.runs/database-runs/`, outside linked worktrees.
`FIGHT_COMMON_DATABASE_RECEIPT_DIR` may select another private retained evidence directory before creation.
After an interrupted run, inspect that exact receipt and resolve its named holds, then run
`python3 bin/.disposable-database-lifecycle.py cleanup --receipt /absolute/path/to/the-recorded-receipt.json`
from an intact owning checkout. Use `--docker` only for the same qualified Docker endpoint/tool. Never rerun
creation against an existing receipt. Changed identity, inspection failure, shared/stopped consumers and creation
interrupted before returned-ID capture remain finite holds; names alone never authorize deletion. Preserve
receipts through review/delivery. This is trusted local tooling, not a hostile-code sandbox or atomic protection
against unrelated concurrent Docker mutation.

## Decisions

Architectural decisions live in `planning/adr/`. Public Event Sourcing integration documentation lives in `docs/event-sourcing.md`.
