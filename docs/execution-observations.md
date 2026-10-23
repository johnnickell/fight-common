# Durable execution observations

An opt-in recording slice in `Domain\Execution`, `Application\Execution` and
`Adapter\Execution\Dbal` records trusted **evidence**, not authoritative Workflow state. Recording a phase named
`review` grants no review authority. A missing end time/outcome does not mean success, failure or completion.
Performing Agent references are distinct from the instrumentation producer and optional model identity. This slice
includes phase activity, measured usage and separately stored protected messages/tool data/output. It supplies no
model directory, MCP removal tools or automatic capture. The separate [protected Queries](execution-protected-reads.md)
and [ordinary removal Command](execution-detail-removal.md) reuse this storage. Safe execution overview
Queries are documented separately in [authorized execution overviews](execution-reads.md).

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
| `Observation` | `id()`, `producerId()`, `session()`, `phase()`, `occurredAt()`, `startedAt()`, `endedAt()`, `outcome()`, `extensions()`, `activity()`, `detail()`, `occurrence()`, `recordedAt()`, `matches()`, `compareRecordingTo()` |
| `SessionReference` | `projectId()`, `sessionId()`, `consumerReference()` |
| `PhaseReference` | `sessionId()`, `phaseId()`, `parentPhaseId()`, `agentId()`, `name()`, `attempt()` |
| `RecordedObservation` | `observation()`, `recordedAt()` |

For example, admission reads `$observation->session()->projectId()` and
`$observation->phase()?->parentPhaseId()`. These methods preserve validated values, nulls, immutable object identity
and UTC microseconds; they do not grant permission or introduce mutation. Native `ObservationOutcome` enum
`name`/`value` behavior applies. Ordinary public-property reads and object enumeration are unavailable. All Execution
additions remain provisional until tagged; no earlier development payload or PHP object snapshot is supported.

| Field | Version-one limit / meaning |
|---|---|
| Version | Integer `1` only, for both submitted and stored data; unsupported versions reject |
| Observation identity | Full canonical lowercase 36-character UUID, generated using `ObservationId::generate()` (UUID COMB) or reconstructed with the existing UUID contract |
| Producer, project, session, consumer reference, phase, parent and Agent references | 1–128 ASCII bytes, `[A-Za-z0-9][A-Za-z0-9._:-]*`; exact case-sensitive identity, no whitespace or normalization |
| Session reference | Project ID, session ID and consumer execution reference; session ID is unique within its project |
| Phase reference | Session ID, project-scoped phase ID, optional parent phase, performing Agent ID, name and attempt |
| Phase name / attempt | Same reference alphabet, name at most 64 bytes; integer attempt 1–1,000,000 |
| Outcomes | `unknown` (default), `succeeded`, `failed`, `cancelled`; never inferred from missing timing or process exit |
| Times | Immutable native instants normalized to UTC, years 0001–9999; ordinary session/phase JSON uses `YYYY-MM-DDTHH:MM:SS.ffffffZ`; start and end may independently be unknown, but known end cannot precede known start |
| Activity | Null for ordinary session/phase/detail evidence; a bounded object for activity evidence with precision-preserving occurrence as below |
| Detail | Null except for protected-detail evidence; safe bounded capture metadata linked to an existing same-scope activity |
| Extensions | At most 32 namespaced keys, each at most 96 ASCII bytes matching `[a-z][a-z0-9.-]*/[a-z][a-z0-9._-]*`; only string, native integer, Boolean or null values |
| Extension strings / total | At most 256 UTF-8 bytes per string and 4,096 bytes for the sorted JSON extension map; no objects, lists, floats or nested content |

`fromArray()` rejects extra/missing core fields rather than silently retaining new fields. Extension values are a
small consumer-owned vocabulary, **not** a channel for messages, tool arguments/results, process output or secrets.
Consumers own capture choices and redaction before submission; lexical/structural checks are not secret detection.
Domain validation uses fixed diagnostics rather than echoing submitted content.

## Phase activity and measured usage

`Observation::forActivity(id, producerId, session, phase, occurrence, activity, extensions)` creates version `1`
activity observations through the same `RecordPhaseObservation` and handler/recorder as ordinary phase evidence.
The matching session and phase must already exist; activity cannot create a phase or change its performing Agent.
`activity()` and `occurrence()` return typed values for activity evidence and null for ordinary session/phase evidence.
`occurredAt()` returns the instant; `occurrence()->toString()` retains actual source precision. Activity does not report
phase timing or completion: `startedAt()`/`endedAt()` must be null and `outcome()` must be unknown, including on
reconstruction. It never advances Workflow state.

All observation kinds share the exact field set `version`, `observation_id`, `producer_id`, `session`, `phase`,
`occurred_at`, `started_at`, `ended_at`, `outcome`, `activity`, `detail`, `extensions`. Ordinary session/phase evidence
requires `activity: null` and `detail: null`; activity evidence requires an activity object, null detail and non-null
phase. Detail evidence requires capture metadata, null activity and non-null phase. No missing-field fallback exists.
Activity occurrence is canonical UTC `YYYY-MM-DDTHH:MM:SSZ` or the same with **one through six** supplied fractional
digits. Rollover, offsets, leap seconds, year zero and finer precision reject. `.120` stays `.120`, not `.120000`;
these represent the same instant but different reported precision and therefore different retry content. Ordinary
session/phase native timestamps use canonical six-digit UTC text.

`PhaseActivity::create(kind, summary, modelId: null, usage: [])` owns immutable private safe data. Its exact nested
version-one representation is `version`, `kind`, `summary`, `model_id`, `usage`:

- Kind and metric names are consumer-owned namespaced ASCII vocabulary, matching the extension-key pattern and
  bounded to 96 bytes. Common assigns no automatic semantics to `consumer/model-call` or any other kind.
- Summary is at most 512 valid UTF-8 bytes, including empty, with ASCII controls prohibited. It is **consumer-redacted
  safe text**, never messages, tool arguments/results or process output. Structure cannot universally detect secrets.
- Optional model ID uses the 1–128 byte reference grammar. It is neither the phase's performing Agent nor the
  authenticated instrumentation producer, and confers no authority.
- Usage contains at most 32 `UsageMeasurement` values, one per exact metric name, sorted bytewise by metric for
  canonical comparison. Empty usage means no supplied measurements, not zero. Array references and output copies
  cannot mutate the retained immutable snapshot. Nested unknown fields and duplicate metrics reject.

`UsageMeasurement::create(metric, unit, provenance, completeness, value)` holds a consumer-reported measurement.
Its exact version-one array is `version`, `metric`, `unit`, `provenance`, `completeness`, `value`. Unit and provenance
use the reference grammar. Provenance identifies the consumer's evidence source (for example `provider-response`),
not authentication or performing identity. Values are string-only, nonnegative plain decimals of at most 128 input
bytes, canonicalized exactly through Decimal; no floats, signs, exponents, whitespace, estimates or inferred values.
Typed accessors expose all fields; string `"0"` is a real measured zero. Completeness is explicitly:

| UsageCompleteness | Value | Meaning |
|---|---|---|
| `COMPLETE` | Required exact decimal | Consumer reports complete measurement for this activity/metric |
| `PARTIAL` | Required exact decimal | Consumer reports only measured partial evidence; no extrapolation |
| `UNKNOWN` | Required null | Evidence completeness/value is unknown |
| `UNAVAILABLE` | Required null | Consumer explicitly reports unavailable evidence |

An absent metric, unknown/unavailable metric, partial value and measured zero are distinct. Common does not derive
cost, context capacity, quota, outcome, permissions or billing from tokens or another metric. Consumers own units,
metric vocabulary, capture accuracy and whether independently reported observations overlap. Recording alone
implies no aggregation or unit conversion; [overview Queries](execution-reads.md) explicitly group compatible evidence.

```php
use Fight\Common\Domain\Execution\Activity\PhaseActivity;
use Fight\Common\Domain\Execution\Observation\ObservationOccurrence;
use Fight\Common\Domain\Execution\Usage\UsageCompleteness;
use Fight\Common\Domain\Execution\Usage\UsageMeasurement;

$activity = PhaseActivity::create('consumer/model-call', 'Completed provider request', 'model:one', [
    UsageMeasurement::create('provider/output', 'token', 'provider-response', UsageCompleteness::PARTIAL, '12'),
    UsageMeasurement::create('provider/cost', 'USD', 'provider-response', UsageCompleteness::UNAVAILABLE, null)
]);
$evidence = Observation::forActivity(
    ObservationId::generate(), 'instrumentation', $session, $phaseObservation->phase(),
    ObservationOccurrence::fromString('2026-10-20T12:34:56.120Z'), $activity
);
$commands->execute(new RecordPhaseObservation($evidence));
```

This uses the composition below after session and phase recording. No second pipeline, usage repository or hidden
instrumentation is required. The observation row contains the entire bounded activity/usage snapshot and retained
retry identity together, FK-linked to its existing project/session/phase. There are no independently committed
measurement rows; protected bodies use the separate storage described below. The existing `ObservationRecorded` fact
covers activity and its usage atomically; it includes **no** kind, summary, model, usage, extensions or bodies.

## Protected detail recording

`Observation::forDetail(id, producerId, session, phase, occurrence, capture)` creates a safe version-one observation.
`DetailCapture` links an **existing activity observation** in the same producer namespace, exact project/session/
consumer reference and phase (including parent/Agent/name/attempt). Detail cannot create its phase or activity.
The detail identity is its own full observation UUID, never the target activity's UUID. `detail()` exposes only capture
metadata; `activity()` remains null, timing remains unknown and extensions are empty. Agent/model/producer identities
remain on their existing owners; no Workflow or review authority is mutated.

`RecordProtectedDetail(observation, content)` pairs that safe observation with `ProtectedContent`. Its Command payload
is `{observation: <safe version-one data>, content: {version: 1, body: <string|null>}}`. Unlike safe Events, this Command
**contains protected bytes**: consumer bus filters, queues and diagnostics must not log/project it into safe channels.
The handler uses the same recorder, admission and real transaction as session/phase/activity Commands.

| Contract | Bound / meaning |
|---|---|
| `DetailKind` | `message`, `tool_arguments`, `tool_result`, `process_output` |
| `DetailCapture` version | Integer `1`; exact fields `version`, `activity_id`, `kind`, `content_type`, `completeness`, `segment` |
| Content type | Required lowercase parameter-free media type, at most 128 ASCII bytes; lexical `type/subtype`, not content validation |
| `CaptureCompleteness` | `complete`/`partial` require a body (including empty string); `absent`/`unavailable`/consumer-supplied `expired` require null |
| Message/tool body | At most 65,536 raw UTF-8 bytes; type is declared, not parsed as JSON or automatically redacted |
| Process-output body | At most 16,384 raw UTF-8 bytes per submission; arbitrary binary must be encoded upstream within this budget |
| `OutputSegment` | Required only for process output: `stdout`/`stderr`/`combined`, integer index 0–1,000,000 and byte offset 0–2,147,483,647; offset plus body bytes must remain within that bound |

Complete/partial describes the producer's **submitted capture unit**, not proof of complete conversation/process output.
Segment coordinates are supplied facts: no automatic concatenation, ordering/continuity inference, gap repair, total-size
promise or capture service. Separate IDs may deliberately overlap; consumers own capture selection and vocabulary.
`ProtectedContent::create(capture, body)` validates content against capture; `body()` is protected access, not read
permission. All values are immutable with private state and typed accessors. Unsupported versions, extra fields,
invalid UTF-8, inconsistent absence, bad coordinates and oversized bodies reject with fixed text, never submitted bytes.

```php
use Fight\Common\Domain\Execution\Detail\CaptureCompleteness;
use Fight\Common\Domain\Execution\Detail\DetailCapture;
use Fight\Common\Domain\Execution\Detail\DetailKind;
use Fight\Common\Domain\Execution\Detail\OutputSegment;
use Fight\Common\Domain\Execution\Detail\ProtectedContent;
use Fight\Common\Domain\Execution\Observation\Command\RecordProtectedDetail;

// After the activity example has committed, using the composition below:
$capture = DetailCapture::create(
    $evidence->id(), DetailKind::PROCESS_OUTPUT, 'text/plain', CaptureCompleteness::PARTIAL,
    OutputSegment::create('stdout', 0, 0)
);
$detail = Observation::forDetail(
    ObservationId::generate(), 'instrumentation', $session, $phaseObservation->phase(),
    ObservationOccurrence::fromString('2026-10-20T12:34:57.12Z'), $capture
);
$commands->execute(new RecordProtectedDetail($detail, ProtectedContent::create($capture, 'synthetic output')));
```

Recording admission is **not** protected-read authorization. The internal repository is trusted infrastructure,
not a public inspection API. The separate [protected inspection](execution-protected-reads.md) boundary supplies
current separately authorized bounded Queries; the [ordinary removal Command](execution-detail-removal.md) uses
independent current permission. Neither belongs to the recording use case. Consumers retain authentication, permission
policy, pre-submission redaction, capture accuracy, retention eligibility/jobs, database roles and operations.

## Trusted admission and composition

`RecordSessionObservation` and `RecordPhaseObservation` keep their payloads private and expose them through
`observation(): Observation`. Use the accessor rather than direct property access; `toArray()` and `fromArray()`
use the single current version-one observation shape. Commands live in
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
use Fight\Common\Adapter\Execution\Dbal\DbalProtectedContentRepository;
use Fight\Common\Adapter\Execution\Dbal\DbalRecordingTransaction;
use Fight\Common\Adapter\Execution\Dbal\DbalSessionRepository;
use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Execution\Observation\CommandHandler\RecordPhaseObservationHandler;
use Fight\Common\Application\Execution\Observation\CommandHandler\RecordProtectedDetailHandler;
use Fight\Common\Application\Execution\Observation\CommandHandler\RecordSessionObservationHandler;
use Fight\Common\Application\Execution\Observation\Service\ObservationRecorder;
use Fight\Common\Domain\Execution\Observation\Command\RecordPhaseObservation;
use Fight\Common\Domain\Execution\Observation\Command\RecordProtectedDetail;
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
    $events,
    new DbalProtectedContentRepository($transaction)
);
$router = new InMemoryCommandRouter();
$router->registerHandler(RecordSessionObservation::class, new RecordSessionObservationHandler($recorder));
$router->registerHandler(RecordPhaseObservation::class, new RecordPhaseObservationHandler($recorder));
$router->registerHandler(RecordProtectedDetail::class, new RecordProtectedDetailHandler($recorder));
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
retry comparison and adapter qualification. Do not expose them directly to untrusted callers. Public [overview Queries](execution-reads.md)
return dedicated safe results through current read admission; recording repositories remain internal to composition.

## Retry and transaction guarantees

The retry key is **producer ID + observation UUID**, independent of the subject ID and Command message ID. Keep the
original observation ID, submitted content and occurrence time for uncertain retries; generating a new observation
or using a new `now` value is not the same-content retry. Exact canonical submitted bytes are retained, not just a
hash. Version-one comparison emits the fixed core field order, sorted extension keys, UTC microseconds and preserved
scalar types. Array input key order and equivalent timezone instants do not affect comparison. Integer `3` differs
from string `"3"`. Floats are excluded so process JSON float-precision settings cannot alter retry identity.
Server recording time is excluded. Matching retries write nothing new and preserve that original time; changed data
throws `ObservationConflict`. Detail comparison additionally requires the protected row, as described below.
Guarantees last only while the required identity/comparison records remain retained; deletion does not promise
indefinite deduplication.

Application's `RecordingTransaction` extends the existing `TransactionalUnitOfWork` contract. Its DBAL implementation
`DbalRecordingTransaction` owns the single real transaction, shared connection and serialization lock. Construct **all
four DBAL repositories with that same boundary object**, as above; a repository tied to another boundary cannot
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
| `ObservationConflict` | Retained key has changed safe metadata or protected content; no overwrite |
| `ProtectedContentRemoved` | Safe identity remains but protected comparison row is gone; no body comparison or restoration |
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

The reserved `fight_execution_*` namespace contains a version/lock table, session subjects, phase subjects, retained
safe observation rows and separately protected `fight_execution_contents`. Composite foreign keys enforce
same-project/session parent and observation links. Observation rows hold producer-scoped identity, safe versioned
payload/comparison, nullable `activity_id` FK and immutable recording time. The linked activity cannot be deleted while
safe detail observations reference it. Content rows are FK-linked by `(producer_id, observation_id)`; deleting their
safe identity cascades content, not session/phase summaries. Database grants and encryption remain consumer operations.

`install()` creates only its tables and initial version row on an idle connection; repeated installation validates
complete presence, version `1` and the required recording-order index. It does not repair
partial installations, infer unknown versions or alter consumer tables. Setup is deployment-owned and must be exclusive; MySQL DDL is not advertised as transactional. After
a failed setup, inspect/recover the partial namespace explicitly. For migration composition, call
`addTo($schema, $connection->getDatabasePlatform())` on a consumer-owned DBAL `Schema`, apply only the intended new tables,
and seed `fight_execution_schema` with `singleton = 1, version = 1` before recording. Preserve the declared constraints,
engine and collations. No destructive migration or maintenance/archival orchestration is supplied.

All times are stored as canonical fixed UTC text rather than precision-losing native datetime mappings; supplied
microseconds and server recording microseconds round-trip. The **entire** UUID is stored, including its random suffix.
Neither COMB time bits nor recording timestamps imply causal order or commit order; public browsing remains
future work. Qualification exercises real SQLite/MySQL/PostgreSQL round trips, independent competing session and phase
writers, FKs, rollback, outer-transaction rejection, lost commit acknowledgment and post-commit subscriber failure.
Package evidence is not consumer runtime/deployment qualification.

## Protected storage and removal boundary

Safe observations contain no bodies, content digests, encrypted bodies or second protected comparison copy.
`fight_execution_contents.payload` alone holds exact version-one protected content (including a null body for explicit
absence). Its DBAL text length permits JSON-escaping expansion beyond ordinary MySQL TEXT while raw byte limits remain
fixed. Related safe identity/link/capture metadata and protected content commit together; failures after either insert
roll all new effects back. Post-commit events expose only existing safe reference facts, not capture metadata or bodies.

`DbalProtectedContentRepository::getFor(detail)` is a trusted recording/reconstruction boundary, not an authorized Query.
A null **row** means content is no longer stored; a retained `ProtectedContent` with `body() === null` preserves an
explicit absence marker. Capture `complete`/`partial` is historical supplied metadata, **never a current availability
flag**. An absent row alone never means expired. A future read must check current storage and authorization rather
than claiming availability from the safe capture marker.

| Retained records | Retry behavior after current admission and relationship validation |
|---|---|
| Safe observation plus protected row | Exact safe content and body deduplicate with original recording time; changed metadata/body conflicts; matching retry does not redispatch |
| Safe observation, protected row removed | Matching safe content raises `ProtectedContentRemoved`, regardless of submitted body; changed safe metadata still conflicts. No exact body comparison, silent restoration or new event |
| Safe identity and protected row removed | Retry protection ended; a currently admitted submission can become new evidence with a new server recording time and one new dispatch attempt |

Storage conformance and the [public removal Command](execution-detail-removal.md) remove content **inside the same
`RecordingTransaction` serialization boundary**. Arbitrary SQL maintenance outside this lock is not covered by removal/retry race
guarantees. Tests exercise both lock orders (removal first and retained retry first) on SQLite/MySQL/PostgreSQL.
Removing body alone preserves safe detail/activity and session/phase evidence and cannot leave a truthfully available
body link: storage reconstruction returns no content. Removing identity too ends the guarantee, not an undeclared
retained-content lease. TICKET-00042's public Queries/removal reuse this boundary without a reverse dependency;
retention eligibility, holds and maintenance jobs remain consumer-owned.

## Recording comparison and current schema

`Observation::compareRecordingTo(other)` compares **ascending** immutable UTC server recording time, then the entire
UUID through its existing full-byte comparator. It requires both roots to be recorded. Descending order uses
`$b->compareRecordingTo($a)` and agrees with SQL `ORDER BY recorded_at DESC, observation_id DESC` on SQLite, MySQL
and PostgreSQL, including random suffixes and microsecond ties. Never use a truncated UUID timestamp, occurrence
or producer identity as a replacement. Equal time/full UUID keys across distinct producer namespaces compare equal;
these two keys alone are not a strict total order across that collision. They are neither causality, commit order
nor a stable cross-transaction visibility boundary. Public Query selection/pagination is not introduced here.

Schema version `1` includes the `fight_execution_recording_order` index on `(recorded_at, observation_id)`.
Session, phase and activity/usage evidence use that same schema and version-one payload. Bounded measurements live
in the retained identity row, not extra tables that could partially commit. The stored `version` column must match
the payload version; unsupported versions, including `2`, reject.

During an **exclusive, idle, consumer-owned setup window**, `install()` creates the current namespace or validates
an existing complete version-one installation. Partial namespaces, unsupported versions and a missing order index
reject; installation does not upgrade or resume an earlier development format. MySQL DDL is not transactional:
inspect/recover a failed partial installation explicitly. No concurrent schema deployment is promised.

Conformance tests use controlled adapter recording timestamps to prove SQL/application ties; Commands still assign
server time. Actual database tests cover current session/phase/activity reconstruction and retries, repeated install
without changed evidence, precision, admission, conflicts, independent competing retries, after-insert rollback and
post-commit subscriber failures on all three supported databases. These are package guarantees, not deployment proof.

## Compatibility

Compatibility is measured against tagged releases, not earlier development commits. Tagged v1.4.0 contains no
Execution capability: all session/phase/activity/usage/protected-detail APIs, payloads and schema remain one mutable provisional
version-one design until first tagged. There are no historical codecs, aliases, legacy wrappers or migrations for
previous development snapshots. Unsupported-version rejection remains current validation, not past-version support.
The project profile records this release-baseline rule.

The manifest classifies the intended public roots, repositories, Commands, handlers, admission/transaction ports,
exceptions, receipt, Event and DBAL adapters; `ObservationFields` is internal. These classifications do not freeze
never-released development representations. Concrete classes are final, ports are implementable, and no inheritance
extension promise is added.
Released/unrelated bus, dispatcher, UUID, audit, Event Store and unit-of-work contracts remain unchanged; TASK-00173's
released-property migration is not implemented here.

TASK-00148 adds public final `PhaseActivity`, `ObservationOccurrence`, `UsageMeasurement` and the native
`UsageCompleteness` enum, plus named Observation factories/accessors/comparison. These are callable/factory-constructible
additions, not subclass/implementation extension points. The compatibility manifest classifies them and their
behavioral contracts. Session, phase and activity use the same Commands, admission, repositories and safe Event
pipeline. Readers accept only the current version-one representation; schema one includes its recording-order index. Matching retained retries
preserve their original recording time without dispatch. No released unrelated API is removed.

TASK-00149 adds public final `DetailCapture`, `OutputSegment`, `ProtectedContent`, `RecordProtectedDetail`, its handler,
`ProtectedContentRemoved` and `DbalProtectedContentRepository`, native `DetailKind`/`CaptureCompleteness` enums and the
implementable Domain `ProtectedContentRepository` port. Observation adds `forDetail()`/`detail()` and one nullable
version-one field; the recorder takes the fourth repository and paired content. Schema one directly includes the
content table and activity FK. These are provisional additions, not an upgrade/migration contract for earlier
unreleased snapshots. The compatibility manifest classifies the new surface and behavioral guarantees. Existing
session/phase/activity Commands and safe Event behavior remain supported; no unrelated released API changes.

Message envelope `payload_type` and code-facing handler/subscriber registrations follow the PHP namespaces:
`Fight.Common.Domain.Execution.Observation.Command.RecordSessionObservation`, the corresponding
`RecordPhaseObservation`, and `Fight.Common.Domain.Execution.Observation.Event.ObservationRecorded`. Register the
handler classes and subscriber Event FQCN/canonical name together. No compatibility mapping for superseded
development envelope names is supplied or required. No released message identity changes or release
certification is implied.
