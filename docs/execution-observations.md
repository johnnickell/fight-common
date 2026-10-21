# Durable execution observations

An opt-in recording slice in `Domain\Execution`, `Application\Execution` and
`Adapter\Execution\Dbal` records trusted **evidence**, not authoritative Workflow state. Recording a phase named
`review` grants no review authority. A missing end time/outcome does not mean success, failure or completion.
Performing Agent references are distinct from the instrumentation producer; this slice has no model directory,
activity/usage recording, protected bodies, public inspection Queries, MCP tools or automatic capture.

## Vocabulary and finite version-one representation

Execution contains three ordinary DDD aggregate roots, not one graph and not event-sourced aggregates:

- `Domain\Execution\Session\Session` owns project-scoped session identity and its stable consumer relationship.
- `Domain\Execution\Phase\Phase` owns project-scoped phase identity, session/parent, performing Agent, name and attempt.
- `Domain\Execution\Observation\Observation` owns producer-scoped immutable evidence and its server recording time.
  It contains an `ObservationId`, producer reference, `SessionReference`, optional `PhaseReference`, occurrence time,
  nullable observed start/end times, explicit `ObservationOutcome` and immutable `ObservationExtensions`.

Each has its own Domain repository. Session and Phase are recorded subjects, not authoritative Workflow state.
References live with their owning concepts; they describe immutable relationships without loading a graph.
`RecordedObservation` is only a receipt over a recorded Observation root: its `recordedAt()` reads that root's time,
with no competing identity, repository or independently stored time. `Observation::recorded()` creates an immutable
recorded copy; a different receipt time on an already recorded root rejects. `recordedAt()` rejects on submitted,
unrecorded evidence. The recorder assigns the actual server time, never a producer-supplied receipt time.
The same subject may have multiple observations; later evidence never overwrites an existing observation.
No EventSourcing base, replay, stream version, Event Store or outbox is involved.

All properties declared by this slice's classes are private, including readonly values and exception context.
Existing inherited contracts such as `UniqueId` are unchanged. Use typed methods:

| Value | Read accessors |
|---|---|
| `Session` | `projectId()`, `id()`, `consumerReference()`, `reference()`, `matches()` |
| `Phase` | `projectId()`, `id()`, `sessionId()`, `parentPhaseId()`, `agentId()`, `name()`, `attempt()`, `reference()`, `matches()`, `belongsTo()`, `acceptsParent()` |
| `Observation` | `id()`, `producerId()`, `session()`, `phase()`, `occurredAt()`, `startedAt()`, `endedAt()`, `outcome()`, `extensions()`, `recordedAt()`, `matches()` |
| `SessionReference` | `projectId()`, `sessionId()`, `consumerReference()` |
| `PhaseReference` | `sessionId()`, `phaseId()`, `parentPhaseId()`, `agentId()`, `name()`, `attempt()` |
| `RecordedObservation` | `observation()`, `recordedAt()` |

For example, admission reads `$observation->session()->projectId()` and
`$observation->phase()?->parentPhaseId()`. These methods preserve validated values, nulls, immutable object identity
and UTC microseconds; they do not grant permission or introduce mutation. Native `ObservationOutcome` enum
`name`/`value` behavior is unchanged. Structured `toArray()`/`fromArray()` payloads and comparison bytes remain unchanged;
ordinary public-property reads and object enumeration are intentionally unavailable. This completes the requested
encapsulation of unreleased TASK-00147 APIs, not a conversion of released interfaces or a promise that raw PHP object
serialization from the superseded public-property implementation remains compatible.

| Field | Version-one limit / meaning |
|---|---|
| Version | Integer `1` only, for both submitted and stored data; unsupported versions reject |
| Observation identity | Full canonical lowercase 36-character UUID, generated using `ObservationId::generate()` (UUID COMB) or reconstructed with the existing UUID contract |
| Producer, project, session, consumer reference, phase, parent and Agent references | 1–128 ASCII bytes, `[A-Za-z0-9][A-Za-z0-9._:-]*`; exact case-sensitive identity, no whitespace or normalization |
| Session reference | Project ID, session ID and consumer execution reference; session ID is unique within its project |
| Phase reference | Session ID, project-scoped phase ID, optional parent phase, performing Agent ID, name and attempt |
| Phase name / attempt | Same reference alphabet, name at most 64 bytes; integer attempt 1–1,000,000 |
| Outcomes | `unknown` (default), `succeeded`, `failed`, `cancelled`; never inferred from missing timing or process exit |
| Times | Immutable native instants normalized to UTC, years 0001–9999; canonical JSON `YYYY-MM-DDTHH:MM:SS.ffffffZ`; start and end may independently be unknown, but known end cannot precede known start |
| Extensions | At most 32 namespaced keys, each at most 96 ASCII bytes matching `[a-z][a-z0-9.-]*/[a-z][a-z0-9._-]*`; only string, native integer, Boolean or null values |
| Extension strings / total | At most 256 UTF-8 bytes per string and 4,096 bytes for the sorted JSON extension map; no objects, lists, floats or nested content |

`fromArray()` rejects extra/missing core fields rather than silently retaining new fields. Extension values are a
small consumer-owned vocabulary, **not** a channel for messages, tool arguments/results, process output or secrets.
Consumers own capture choices and redaction before submission; lexical/structural checks are not secret detection.
Domain validation uses fixed diagnostics rather than echoing submitted content.

## Trusted admission and composition

`RecordSessionObservation` and `RecordPhaseObservation` keep their payloads private and expose them through
`observation(): Observation`. Use the accessor rather than direct property access; `toArray()` and `fromArray()`
retain the version-one Command payload shape. These are maintainer-requested corrections to the unreleased
TASK-00147 APIs, not compatibility changes to released Commands. Commands live in
`Domain\Execution\Observation\Command`; the safe Event lives in `Domain\Execution\Observation\Event`.
Handlers, recording failures and the recorder live in `Application\Execution\Observation\CommandHandler`,
`Exception` and `Service`, respectively. Admission and the shared transaction port remain Application responsibilities.

Consumers implement `ObservationAdmission::admit(Observation): void` using **current trusted invocation context**.
It must verify the authenticated instrumentation producer and permitted project, session, target phase and parent
phase references, throwing `RecordingDenied` on denial. IDs in Commands, `Meta` and correlation values are references,
not credentials. The performing Agent is not the authenticated producer. No permissive admission implementation is
provided. Do not accept a serialized caller-selected admission decision or derive trust from a phase name.

The coordinator calls admission inside its owned transaction **on every invocation**, before looking up relationships
or retained retry content. Scope decisions must remain effective for that invocation; the consumer owns revocation
synchronization and any external policy storage. Admission should be bounded and must not commit/rollback, perform
unrelated writes or dispatch observed work. Async consumers must reconstruct trusted current context at execution,
not treat an old queued producer ID as authentication.

A phase requires an existing matching session; a parent requires the same project **and** session. Reusing a subject
with changed consumer reference, session, parent, performing Agent, name or attempt rejects. Missing, concealed and
inconsistent relationships share `InvalidObservationRelationship` without disclosing stored values. To represent a
new attempt with a different performing identity, use a new phase subject ID and the intended attempt number.

```php
use Fight\Common\Adapter\Execution\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Execution\Dbal\DbalObservationSchema;
use Fight\Common\Adapter\Execution\Dbal\DbalPhaseRepository;
use Fight\Common\Adapter\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Execution\Dbal\DbalSessionRepository;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Execution\Observation\CommandHandler\RecordPhaseObservationHandler;
use Fight\Common\Application\Execution\Observation\CommandHandler\RecordSessionObservationHandler;
use Fight\Common\Application\Execution\Observation\Service\ObservationRecorder;
use Fight\Common\Domain\Execution\Observation\Command\RecordPhaseObservation;
use Fight\Common\Domain\Execution\Observation\Command\RecordSessionObservation;
use Fight\Common\Domain\Execution\Observation\Observation;
use Fight\Common\Domain\Execution\Observation\ObservationId;
use Fight\Common\Domain\Execution\Phase\PhaseReference;
use Fight\Common\Domain\Execution\Session\SessionReference;

// Deployment/setup only, using a dedicated idle Doctrine DBAL connection:
(new DbalObservationSchema())->install($connection);

// $admission implements ObservationAdmission using the consumer's current trusted context.
$transaction = new DbalRecordingTransaction($connection);
$events = new SimpleEventDispatcher();
$recorder = new ObservationRecorder(
    $transaction,
    new DbalSessionRepository($transaction),
    new DbalPhaseRepository($transaction),
    new DbalObservationRepository($transaction),
    $admission,
    $events
);
$router = new InMemoryCommandRouter();
$router->registerHandler(RecordSessionObservation::class, new RecordSessionObservationHandler($recorder));
$router->registerHandler(RecordPhaseObservation::class, new RecordPhaseObservationHandler($recorder));
$commands = new RoutingCommandBus($router);

$session = SessionReference::fromStrings('project', 'session', 'build:147');
$sessionObservation = Observation::create(
    ObservationId::generate(), 'instrumentation', $session, null, new DateTimeImmutable('now')
);
$commands->execute(new RecordSessionObservation($sessionObservation)); // void, real commit completed

$phaseObservation = Observation::create(
    ObservationId::generate(),
    'instrumentation',
    $session,
    PhaseReference::fromStrings('session', 'phase:review', null, 'agent:reviewer', 'review', 1),
    new DateTimeImmutable('now')
);
$commands->execute(new RecordPhaseObservation($phaseObservation));
```

`ObservationRecorded` also keeps its state private. Subscribers read it through `id()`, `producerId()`,
`projectId()`, `sessionId()`, `phaseId()` and `recordedAt()`; `phaseId()` is null for a session fact. Its safe
`toArray()`/`fromArray()` payload remains unchanged, including UTC recording time with microseconds.

Use the existing subscriber registration contract to observe `ObservationRecorded`. When calling
`SimpleEventDispatcher::addHandler()` directly, use the existing canonical event name from
`ClassName::underscore(ObservationRecorded::class)`, not the raw PHP class name. The safe event contains only observation,
producer, project, session and nullable phase IDs and recording time. Null phase identifies a session fact; non-null
identifies a phase fact. It contains no extensions, Agent description, consumer text, bodies or command metadata.
Events are facts about saved observations, not authoritative session/phase transitions.

`SessionRepository::getById(project, session)`, `PhaseRepository::getById(project, phase)` and
`ObservationRepository::getById(producer, observationId)` return independently reconstructed nullable roots; `add()`
persists a new immutable root inside the shared recording transaction. They are Domain contracts and do not inherit
Application's unit of work. They are not authorized public inspection APIs: these reads support recording checks,
retry comparison and adapter qualification. Do not expose them directly to untrusted callers. Future public Queries
may use dedicated read results rather than hydrating roots; they are outside this slice.

## Retry and transaction guarantees

The retry key is **producer ID + observation UUID**, independent of the subject ID and Command message ID. Keep the
original observation ID, submitted content and occurrence time for uncertain retries; generating a new observation
or using a new `now` value is not the same-content retry. Exact canonical submitted bytes are retained, not just a
hash. Version-one comparison emits the fixed core field order, sorted extension keys, UTC microseconds and preserved
scalar types. Array input key order and equivalent timezone instants do not affect comparison. Integer `3` differs
from string `"3"`. Floats are excluded so process JSON float-precision settings cannot alter retry identity.
Server recording time is excluded. Matching retries write nothing new and preserve that original time; changed data
throws `ObservationConflict`. Guarantees last only while the observation/identity/comparison row remains retained.
Deleting it does not promise indefinite deduplication.

Application's `RecordingTransaction` extends the existing `TransactionalUnitOfWork` contract. Its DBAL implementation
`DbalRecordingTransaction` owns the single real transaction, shared connection and serialization lock. Construct **all
three DBAL repositories with that same boundary object**, as above; a repository tied to another boundary cannot
write inside this one's transaction. The recorder coordinates admission and repository-dependent checks, while roots
own relationship/content matching and adapters own SQL mechanics. Callers only execute a Command; they never sequence
repository writes or three independent commits.

This DBAL path does **not** use ORM `DoctrineTransactionalUnitOfWork` or flush semantics. Do not wrap recording in an
ORM/filter/consumer transaction. The boundary rejects an already-active transaction, nested invocation and unowned
writes before admission/writes; it never commits or rolls back caller-owned work. Do not share/manage this connection
while recording. Successful void acknowledgment follows the actual outer database commit, not a released savepoint.

Every recording takes a write lock on the single `fight_execution_schema` version row before reading. This deliberately
serializes recordings within this schema, including independently competing retries, so current relationships and
retained content are checked after the prior writer. It trades write throughput for straightforward portable integrity;
it does not allocate global positions or promise commit/causal order. Consumers own connection lock timeouts/backoff
(e.g. SQLite `PRAGMA busy_timeout`) and retry only using the documented identity/admission rules. No automatic transient
retry is hidden in this adapter. No transaction with the work being observed, files or external effects is promised.

## Failure and event boundaries

| Result | Meaning / caller action |
|---|---|
| `DomainException` | Invalid structure, limits, version or timing; correct the submission, do not blindly retry |
| `RecordingDenied` | Current trusted producer/scope admission failed; existing identity grants no access |
| `InvalidObservationRelationship` | Unavailable/inconsistent relationship; no partial subject or observation writes |
| `ObservationConflict` | Retained key has changed content; no overwrite |
| `LogicException` | Unsupported outer/nested/closed-boundary transaction composition; fix composition |
| `ObservationStorageFailed` with `outcomeUncertain() === false` | A DBAL operation failed and the owned transaction rolled back |
| `ObservationStorageFailed` with `outcomeUncertain() === true` | Begin/commit acknowledgment or rollback failed; **do not assume absence**, discard the terminal boundary and reconcile/retry through a fresh idle connection with the same identity/content and fresh admission |
| `CommittedObservationDispatchFailed` | Database already committed; safe `event()` identifies the fact, while event/subscriber delivery failed |

Exception `getPrevious()` is private diagnostic material and must never be rendered as public error data. It can
contain provider/subscriber details. Public exception messages are fixed; consumers remain responsible for safe HTTP,
logging and error presentation. Unsupported/corrupt persisted structured data is rejected, not silently reinterpreted.

New observations attempt `EventDispatcher::trigger()` only **after commit**. A subscriber failure cannot undo saved
data. Matching retries **do not redispatch**, even after an earlier failed/lost dispatch. A crash between commit and
trigger can lose the event, and an uncertain commit may already have saved data without dispatch. There is no outbox,
guaranteed replay, exactly-once delivery or transaction encompassing subscribers. The existing dispatcher still attempts
its normal fan-out and reports subscriber failures; the recorder distinguishes that from storage rollback.

## Schema and database qualification

Install with Doctrine DBAL `^4.4` (no floor increase) on SQLite, MySQL or PostgreSQL. The schema helper uses public
schema-manager/schema-composition APIs available across the supported range; no new internal API suppressions are
needed. SQLite foreign keys are enabled at recording-boundary construction, requiring an idle connection.
MySQL tables explicitly use InnoDB and `utf8mb4_bin` to preserve exact reference/producer case while supporting UTF-8
extension values.

The reserved `fight_execution_*` namespace contains a version/lock table, session subjects, phase subjects and retained
observation rows. Composite foreign keys enforce same-project/session parent and observation links. Observation rows
hold the producer-scoped identity, versioned submitted payload/comparison and immutable recording time. Future separately
protected content can reference these records without putting bodies in these safe rows.

`install()` creates only its tables and initial version row on an idle connection; repeated installation validates
complete presence and version `1`. It does not repair partial installations, infer/migrate unknown versions or alter
consumer tables. Setup is deployment-owned and must be exclusive; MySQL DDL is not advertised as transactional. After
a failed setup, inspect/recover the partial namespace explicitly. For migration composition, call
`addTo($schema, $connection->getDatabasePlatform())` on a consumer-owned DBAL `Schema`, apply only the intended new tables,
and seed `fight_execution_schema` with `singleton = 1, version = 1` before recording. Preserve the declared constraints,
engine and collations. No destructive migration, automatic evolution or maintenance/archival orchestration is supplied.

All times are stored as canonical fixed UTC text rather than precision-losing native datetime mappings; supplied
microseconds and server recording microseconds round-trip. The **entire** UUID is stored, including its random suffix.
Neither COMB time bits nor recording timestamps imply causal order or commit order; this slice defines no browsing
order. Qualification exercises real SQLite/MySQL/PostgreSQL round trips, independent competing session and phase
writers, FKs, rollback, outer-transaction rejection, lost commit acknowledgment and post-commit subscriber failure.
Package evidence is not consumer runtime/deployment qualification.

## Compatibility

The accepted aggregate revision intentionally replaces the **unreleased TASK-owned** flat PHP names and combined
`ObservationStore`; no legacy forwarding APIs are retained. The manifest deliberately classifies roots, repositories,
Commands, handlers, admission/transaction ports, exceptions, receipt, Event and DBAL adapters; `ObservationFields` is
internal. Concrete classes are final, ports are implementable, and no inheritance extension promise is added.
Released/unrelated bus, dispatcher, UUID, audit, Event Store and unit-of-work contracts remain unchanged; TASK-00173's
released-property migration is not implemented here.

The schema remains version `1`, with identical tables, submitted JSON, canonical comparison bytes, event payload
fields and UTC receipt text. Raw rows captured by the preceding implementation reconstruct through the new individual
repositories on SQLite, MySQL and PostgreSQL; matching retries retain their original time without dispatch. No
observation-data migration is required, and raw PHP object serialization across the pre-release rename is not promised.

Message envelope `payload_type` and code-facing handler/subscriber registrations follow the **new** PHP namespaces:
`Fight.Common.Domain.Execution.Observation.Command.RecordSessionObservation`, the corresponding
`RecordPhaseObservation`, and `Fight.Common.Domain.Execution.Observation.Event.ObservationRecorded`. Payload arrays
remain unchanged. Pre-release queued envelopes using old Application/flat Domain type names must have those three
`payload_type` values explicitly mapped at their consumer-owned transport boundary before normal reconstruction;
there is no automatic alias or silent stored-envelope rewrite. Register the new handler classes and subscriber Event
FQCN/canonical name together. This is the approved pre-release namespace replacement, not a claim that old envelopes
or registration keys are unchanged. No released message identity changes or release certification is implied.
