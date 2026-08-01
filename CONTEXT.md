# Project Context and Ubiquitous Language

`fight-common` is the shared PHP library for Fight applications. It supplies domain primitives, application ports, and optional infrastructure adapters without defining the business language of any one consuming application.

This file records how terms are used inside this library. Public APIs, documentation, tickets, and ADRs should use these meanings consistently.

## Architectural language

| Term | Meaning in Fight Common |
| --- | --- |
| **Domain** | Framework-free business concepts and reusable domain primitives. Domain code does not depend on Application or Adapter code. |
| **Application** | Use-case coordination and ports. It may depend on Domain contracts but not on concrete adapters. |
| **Port** | An interface owned by an inward layer that describes a capability required from the outside. Examples include `CommandBus`, `UnitOfWork`, `HttpClient`, and `FileStorage`. |
| **Adapter** | A concrete implementation of a port using infrastructure such as Doctrine, Symfony, Guzzle, Flysystem, or an external transport. |
| **Service** | An application-level coordinator or named registry. A service does not make an infrastructure implementation part of the domain. |
| **Dependency direction** | Dependencies point inward: Adapter -> Application -> Domain. |

## Shared domain primitives

| Term | Meaning in Fight Common |
| --- | --- |
| **Value** | An immutable description or measurement compared by value rather than identity. Values validate themselves and are replaced rather than mutated. |
| **Value object** | The base implementation of `Value`, providing value equality, hashing, string conversion, and JSON representation. |
| **Identifier** | A value that identifies a domain concept and can be compared and ordered. |
| **Unique ID** | A UUID-backed `Identifier` with named generation and reconstruction methods. `MessageId` and `AuditEntryId` are examples. |
| **Specification** | A composable business rule evaluated with `isSatisfiedBy()`. Specifications combine through `and()`, `or()`, and `not()`. |
| **Collection** | A typed group of values. Lists preserve sequence, sets enforce uniqueness, tables associate typed keys and values, and sorted variants require comparison rules. |
| **Comparator** | A strategy that establishes ordering between values for sorted collections. |
| **Serializable** | An object that can describe and reconstruct its state. Serialization is representation, not persistence. |

## Messaging and CQRS

| Term | Meaning in Fight Common |
| --- | --- |
| **Payload** | The domain content carried by a message. It has an array representation and can be reconstructed from that representation. |
| **Message** | An immutable envelope containing a `MessageId`, message type, timestamp, payload type, payload, and metadata. |
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
| **Event message** | The message envelope carrying an event. A domain event and its event message are related but not interchangeable terms. |
| **Event dispatcher** | The application port that triggers or dispatches event messages to zero or more handlers. |
| **Event subscriber** | A component declaring the event types it observes and the handlers and priorities used for them. |

Commands use `execute()` for payloads and `dispatch()` for messages. Queries use `fetch()` for payloads and `dispatch()` for messages. Events use `trigger()` for payloads and `dispatch()` for messages.

## Persistence and validation

| Term | Meaning in Fight Common |
| --- | --- |
| **Repository** | A domain-oriented collection boundary for retrieving and persisting domain records without exposing infrastructure details. |
| **Pagination** | A request for page, page size, offset, limit, and normalized field ordering. |
| **Result set** | A typed page of records plus page and total-count metadata. |
| **Unit of work** | The application transaction boundary used to commit work, run a callable transactionally, and report whether the underlying persistence context is closed. |
| **Input data** | Untrusted field data entering validation. |
| **Validation rule** | A reusable predicate such as length, range, format, type, or required-field behavior. |
| **Validator** | A component that applies a specification to a validation context and may add field errors. |
| **Validation coordinator** | The component that runs validators and produces one `ValidationResult`. |
| **Validation result** | Either passed application data or failed error data; data and errors are mutually exclusive states. |
| **Application data** | Validated data safe for application use. |
| **Error data** | Structured field errors produced by failed validation. |

## Supporting capability language

These are distinct capabilities. Avoid using their names interchangeably.

| Capability | Canonical terms |
| --- | --- |
| **Observability** | A **health check** produces one `HealthResult`; a **health report** aggregates results and their worst status. A **metric** is a measured counter, gauge, or timing. An **audit entry** records actor, action, timestamp, and context. |
| **Authentication and security** | An **authenticator** validates a request. A **request service** signs one. A **nonce** is a one-time replay-prevention value and is consumed atomically. Password hashing and token encoding are separate security ports. |
| **Scheduler** | A **job** has a name, schedule, executable command or callable, and runtime policy. The **scheduler** determines which jobs are due and prevents overlapping execution with a lock. |
| **Process** | A **process** is an immutable execution descriptor. A **process runner** queues and executes processes with concurrency and failure behavior. A scheduled job may invoke a process, but the terms are not synonyms. |
| **File storage** | Backend-neutral file content operations, potentially local or remote, exposed through `FileStorage`. |
| **Filesystem** | Operations on the local operating-system filesystem, exposed through `Filesystem`. |
| **File transfer** | Sending, retrieving, and listing remote resources through a named `FileTransport`. A **resource** describes a remote file-system entry. |
| **Transport services** | HTTP clients, mail transports, SMS transports, and socket publishers are outbound ports with concrete adapters. A **null adapter** intentionally performs no external delivery; a **logging adapter** records and delegates or substitutes an operation as documented. |
| **Registry service** | A service such as `StorageService` or `FileTransferService` that selects a named port implementation. It is not itself the storage or transport backend. |

## Engineering quality language

| Term | Meaning in Fight Common |
| --- | --- |
| **Coding standard** | The reusable, opinionated set of PHP formatting, documentation, and declaration-layout rules published by Fight Common. A consuming project chooses the files to which the standard applies. |
| **Quality gate** | A mandatory automated check that must pass before work is accepted. A report without an enforced pass/fail condition is not a gate. |
| **Architecture gate** | The quality gate that verifies inward dependency direction between Domain, Application, and Adapter. |
| **Coverage gate** | The quality gate that compares covered statements with all executable statements. Fight Common requires exact complete statement coverage rather than a rounded percentage. |
| **Dependency freshness lane** | CI verification performed after resolving the latest versions permitted by the package constraints. It is distinct from a local build using already-resolved dependencies. |
| **Build** | The complete ordered set of quality gates used to decide whether the repository is acceptable. |

## Proposed Event Sourcing language for 1.2

These terms describe the planned optional extension. They do not describe existing production APIs until their tickets are implemented.

| Term | Planned meaning |
| --- | --- |
| **Aggregate root** | A consistency boundary whose state changes only by explicitly applying domain events. |
| **Recorded event** | A new event held by an aggregate until it is persisted. |
| **Released event** | A recorded event removed from the aggregate's pending collection for persistence. |
| **Stored event** | An immutable envelope containing an event message, stream version, stable event name, schema version, and global position. |
| **Stream** | The ordered event history of one aggregate, identified by aggregate type and identifier. |
| **Expected version** | The stream version observed during load and checked during append for optimistic concurrency. |
| **Event Store** | The append-only port that writes versioned streams and exposes stream-ordered and globally ordered reads. |
| **Event mapper** | The boundary between stable event names/schema versions and current PHP event classes. |
| **Upcaster** | A one-event-to-one-event transformation from an older stored schema to the next supported schema. |
| **Projector** | An idempotent consumer that moves stored events into a read model. |
| **Read model** | Query-oriented data derived from events; it is not the authoritative event history. |
| **Checkpoint** | The last global event position successfully handled by one named projector. |

Event Sourcing remains additive to CQRS. Aggregates record plain `Event` payloads; repositories create messages and storage envelopes. Projectors consume stored events independently using at-least-once delivery.

## Language rules

- Prefer the exact public contract name when discussing a library capability.
- Distinguish a payload from its message envelope.
- Distinguish domain rules (`Specification`) from input validation rules.
- Distinguish persistence (`Repository`, `UnitOfWork`) from serialization.
- Distinguish File Storage, Filesystem, and File Transfer.
- Consumer applications own their business-specific aggregate, command, query, event, and read-model names.

## Durable and ephemeral work

Committed planning state lives in `planning/`. Coordinate-build artifacts live under `.runs/<date>-<slug>/`; `.runs/` is gitignored and is never canonical project history. Important results and deviations from a run must be copied back to its ticket.

## Decisions

Architectural decisions live in `planning/adr/`. Public Event Sourcing integration documentation lives in `docs/event-sourcing.md`.
