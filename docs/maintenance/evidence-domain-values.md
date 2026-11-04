# Execution and Notice domain classification

## Classification and public surface

TASK-00190 classifies the affected `Domain\Mcp` declarations by their actual responsibilities, not by readonly
syntax. Source links below refer to the owning source directories; the named declarations are the complete
inventory in Session, Phase, Observation, Activity, Detail, Usage, Notice, Subscription and Value at this checkpoint.
All retained declarations remain public as previously classified. Six new final, factory-constructible/callable
values are public: `SessionId`, `PhaseId`, `ObservationIdentity`, `NoticeId`, `ObservationReference` and
`ObservationName`. None promises subclassing or consumer implementation. The compatibility manifest records
these additions and removes the two deleted provisional declarations. No new Domain service is introduced.

| Source / declarations | Classification and invariant owner |
| --- | --- |
| [Session](https://github.com/johnnickell/fight-common/tree/develop/src/Domain/Mcp/Session): `Session` | Ordinary aggregate root; immutable project/session identity and stable consumer relationship, not Workflow state |
| Session: `SessionId`, `SessionReference`, `ExecutionSelection` | Values: project/session identity; complete consumer relationship; finite caller selection without grants |
| Session: `SessionRepository` | Domain persistence port; `getById(SessionId)` cannot accept a Phase identity |
| Session/Query: `GetExecution`, `ListExecutions`, `SummarizeExecutions` | Query messages, not values or query-result DTOs; exact payload decoders and request bounds remain with each message |
| [Phase](https://github.com/johnnickell/fight-common/tree/develop/src/Domain/Mcp/Phase): `Phase` | Ordinary aggregate root; project/phase identity, immutable session/parent/Agent/name/attempt relationships |
| Phase: `PhaseId`, `PhaseReference` | Values: project/phase identity versus complete subject relationship; session membership is not a replacement identity key |
| Phase: `PhaseRepository`, Query/`ListExecutionPhases` | Persistence port with `getById(PhaseId)`; Query message with its own decoder, not a value |
| [Observation](https://github.com/johnnickell/fight-common/tree/develop/src/Domain/Mcp/Observation): `Observation` | Ordinary aggregate root; submitted evidence, relationships, timing and immutable server recording time |
| Observation: `ObservationId`, `ObservationIdentity` | Existing UniqueId-based UUID value unchanged; complete producer/UUID identity composes it without truncation or replacement |
| Observation: `ObservationOccurrence`, `ObservationExtensions` | Values: exact source-precision UTC occurrence; bounded sorted scalar extensions |
| Observation: `ObservationOutcome` | Native backed enum, not a ValueObject |
| Observation: `ObservationRepository` | Persistence port returning recorded roots by producer plus existing typed UUID; not a receipt repository or public read grant |
| Observation/Command: `RecordSessionObservation`, `RecordPhaseObservation`, `RecordProtectedDetail`; Query/`ListPhaseActivity`; Event/`ObservationRecorded` | Messages, not values. Protected Command alone carries bodies; the committed fact contains safe references only |
| [Activity](https://github.com/johnnickell/fight-common/tree/develop/src/Domain/Mcp/Activity): `PhaseActivity` | Value describing an Observation's safe activity and sorted measured usage; no independent activity aggregate |
| [Usage](https://github.com/johnnickell/fight-common/tree/develop/src/Domain/Mcp/Usage): `UsageMeasurement`, `UsageTotal` | Values owning exact nonnegative decimal evidence, unit/provenance/completeness and compatible addition; missing is not zero |
| Usage: `UsageCompleteness` | Native enum; complete/partial/unknown/unavailable remain distinct |
| [Detail](https://github.com/johnnickell/fight-common/tree/develop/src/Domain/Mcp/Detail): `DetailCapture`, `OutputSegment`, `ProtectedContent`, `DetailSelection` | Values: safe capture metadata, coordinates, separately protected bytes, exact caller selection. Capture owns body compatibility; DetailSelection owns complete target matching, not permission |
| Detail: `CaptureCompleteness`, `DetailKind` | Native enums; capture completeness is independent of current storage availability |
| Detail: `ProtectedContentRepository`; Command/`RemoveCapturedDetail`; Query/`ListCapturedMessages`, `GetCapturedDetail` | Persistence port and messages, not values. Removal/read admission stays separately Application-owned |
| [Notice](https://github.com/johnnickell/fight-common/tree/develop/src/Domain/Mcp/Notice): `Notice` | Ordinary aggregate root; bounded safe change content and exact scope/producer/local-ID identity, not an Event Store event |
| Notice: `NoticeId`, `NoticeOccurrence`, `NoticeMetadata`, `NoticeRelationships`, `NoticeSelection`, `NoticeRemovalSelection` | Values: identity, source precision, safe scalar labels, independent relationships, read selection, incarnation/position-pinned removal selection |
| Notice: `RecordedNotice` | Recorded aggregate entry carrying root, immutable server time and substantive namespace-local commit position. Not a receipt-only value or nested entity; deliberately retained |
| Notice: `NoticeRepository` | Domain persistence/removal port; `getByIdentity(NoticeId)` takes the complete scoped identity, not a commit position |
| Notice/Command: `RecordNotice`, `RemoveNotices`; Query/`GetNotice`, `ListNotices`, `SummarizeNotices`, `RecoverNotices`; Event/`NoticeRecorded` | Messages, not values. Recovery's coherent read model remains Application-owned |
| [Subscription](https://github.com/johnnickell/fight-common/tree/develop/src/Domain/Mcp/Subscription): `SubscriberSelection`; Query/`ListSubscribers`, `SummarizeSubscribers` | Selection value and Query messages. Technical registrations/heartbeats remain Adapter records, not new aggregates/entities |
| [Value](https://github.com/johnnickell/fight-common/tree/develop/src/Domain/Mcp/Value): `ObservationReference`, `ObservationName` | Genuine lexical values: exact 1–128-byte opaque instrumentation reference and bounded namespaced activity/metric name. Neither unifies Notice's different 1–96-byte slash-capable grammar |

There are no additional nested entities in this affected boundary. Application read DTOs, current grant snapshots,
coherent recovery windows and protocol output remain outside these Domain values. Domain repositories still do not
own transactions or disclosure policy. [Architecture](https://github.com/johnnickell/fight-common/blob/develop/docs/engineering/standards/Architecture.md) owns the general rule;
namespace relocation and concrete Tool homes remain separate TASK-00191 work.

## Equality and representations

Every listed value extends `ValueObject` directly or through the existing `UniqueId` base. Same-concrete-type
canonical text defines equality and hashing. `fromString(toString())` reconstructs the same value; JSON is deliberate:

- References/names and occurrences emit strings. Occurrence equality includes supplied fractional precision:
  `...56Z` and `...56.0Z` describe distinct evidence even when their instants agree.
- Structured values emit their existing `toArray()` shape, not double-encoded text. Canonical string form is JSON
  of that shape. Scalar kinds, decimal strings, nulls, empty lists/maps and target order retain their meanings.
- Scoped identity string/JSON forms are exact ordered tuples: `[project, session]`, `[project, phase]`,
  `[producer, fullUuid]`, `[scope, producer, localId]`. These are value representations, **not new database or
  message fields**. Storage and wire codecs still write the original component fields and exact bytes.
  Session/Phase identities with the same lexical tuple remain unequal because their roles differ. Hash collisions
  across types are allowed; a hash alone is not equality or authority.
- `UsageTotal` reconstructs its own compatible accumulated amount and four evidence counts. It does not apply
  the single-measurement 128-byte input bound to accumulated totals or convert missing evidence to zero.
- `ProtectedContent` text, JSON and hashes contain protected bytes and are for trusted transport/storage only,
  never safe logging or a reference-only event. Reconstruction enforces the general 65,536-byte UTF-8 bound;
  attaching content rechecks the actual capture (including the tighter 16,384-byte process-output bound and
  offset/end constraints). A reconstructed value grants neither capture nor read/storage permission.

Structural decoding is local to each owning `fromArray()`/`fromString()` representation. The miscellaneous
`ObservationFields` utility is removed, not renamed as a generic validator or service. `ObservationName` and
`ObservationReference` own only their actual lexical concepts; Notice rules remain independently owned.

## Recorded roots and compatibility

`RecordedObservation` is removed. `Observation::recorded()` already owns immutable server time;
`ObservationRecorded::fromRecorded(Observation)` now takes that recorded root directly and rejects unrecorded
roots. The recorder still reconstructs submitted content to discard any caller-chosen recording time, persists the
recorded root/content atomically and constructs the reference-only fact after actual commit. Matching retained
retries do not redispatch. Notice's namespace-local positive commit position is different knowledge and remains in
`RecordedNotice`; no common receipt, ordering abstraction or recovery semantics are inferred.

Every local tag (`1.1.0`, `v1.0.0` through `v1.4.0`) was inspected: none contains these affected execution/Notice
paths. These are direct revisions to never-tagged provisional APIs, with no aliases, deprecations or migrations.
Released UUID/ValueObject, messaging, audit, transaction and Event Store contracts are unchanged. Database schema,
wire/storage fields, permissions, retention, protected separation and post-commit failure meanings remain unchanged.

```php
use Fight\Common\Domain\Mcp\Notice\NoticeId;
use Fight\Common\Domain\Mcp\Phase\PhaseId;
use Fight\Common\Domain\Mcp\Session\SessionId;

$sessionIdentity = SessionId::fromStrings('project:One', 'subject');
$phaseIdentity = PhaseId::fromStrings('project:One', 'subject');
assert(!$sessionIdentity->equals($phaseIdentity));
assert($sessionIdentity->equals(SessionId::fromString($sessionIdentity->toString())));
$noticeIdentity = NoticeId::fromStrings('scope/a', 'producer', 'notice:One');
// Repository calls: getById($sessionIdentity), getById($phaseIdentity), getByIdentity($noticeIdentity).
// None of these values authenticates a producer or grants recording/read/removal permission.
```
