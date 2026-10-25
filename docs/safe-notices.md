# Durable safe change notices

This opt-in capability records bounded change **notices**, not authoritative application state, execution
observations or an Event Store. `Domain\Notice\Command\RecordNotice` uses the existing void
`CommandBus::execute()` contract. Callers supply a stable notice ID; successful return follows actual commit.
No public history/recovery Query, removal Command, MCP/Mercure adapter, automatic capture or outbox is provided.

## Representation and bounds

`Notice::fromArray()` requires exactly these fields. Unknown fields, bodies and nested metadata reject.

| Field | Version-one meaning and finite bound |
| --- | --- |
| `version` | Integer `1`; unsupported versions reject |
| `id`, `producer`, `scope`, `topic`, `type` | Each 1–96 ASCII bytes matching `[A-Za-z0-9][A-Za-z0-9._:/-]*`; exact case-sensitive strings, no trimming or normalization |
| `occurred_at` | Null means unknown; otherwise strict valid UTC `YYYY-MM-DDTHH:MM:SS[.fraction]Z`, years 0001–9999, zero through six supplied fractional digits retained by `NoticeOccurrence` |
| `metadata` | `NoticeMetadata`: at most 16 namespaced ASCII keys, 1–64 bytes, `[a-z][a-z0-9.-]*/[a-z][a-z0-9._-]*`; scalar string/native integer/Boolean/null only |
| Metadata string / total | At most 128 UTF-8 bytes, no ASCII controls; at most 2,048 bytes for the sorted JSON map, including escaping |
| `relationships` | `NoticeRelationships`: exactly `subject`, `recipients`, `author`, `command`, `event`, `action`; each non-null individual reference uses the same 96-byte reference contract |
| Recipients | Null means unknown; empty list explicitly means broadcast/no named recipients; otherwise at most 32 unique references, sorted bytewise as an unordered set |
| Complete notice | At most **6,144 encoded bytes** in `Notice::comparison()`, including relationships, keys, JSON escaping and version |
| Recovery read | `readAfter(scope, position, limit)`: exclusive position 0–2,147,483,647, limit 1–100; at most 100 bounded records, never an unbounded iterator |

Subject Agent, intended recipient Agents, author, instrumentation producer, and each causation Command/event/action
remain independently supplied references. All may be unknown except producer/scope/identity/topic/type. There is no
Agent directory lookup or inference from a topic, connection, planning TASK, protocol request or subscription ID.
Recipients are not a saved subscriber audience. Consumer vocabulary determines notification type/topic meaning.

Metadata is a small safe-label channel, **not** messages, tool arguments/results, process output, full MCP Task state,
or wire transcripts. Lists, objects, floats and arbitrary extra fields are rejected. Structural checks cannot identify
all secrets or distinguish a short protected body disguised as a label: consumers must select/redact safe metadata
before constructing a notice. Never serialize an arbitrary application object into label strings.

`Notice` and its typed metadata/relationships/occurrence are immutable snapshots, including caller-owned PHP element
references. `toArray()` returns independent values; metadata key ordering and recipient ordering are canonical.
Unknown occurrence, precision changes (`.12` versus `.120`), null versus empty recipients, scalar kinds and every
relationship distinction are significant retry content.

## Trusted composition and Command path

Install the optional DBAL dependency (`^4.4` remains supported). During exclusive deployment, on an idle connection:

```php
(new DbalNoticeSchema())->install($connection);
$store = new DbalNoticeStore($connection);
$recorder = new NoticeRecorder($store, $trustedAdmission, $eventDispatcher);
$handler = new RecordNoticeHandler($recorder);
$router->registerHandler(RecordNotice::class, $handler);
$commandBus->execute(RecordNotice::fromArray($safeNoticeData));
```

Imports are `Adapter\Notice\Dbal\{DbalNoticeSchema,DbalNoticeStore}`,
`Application\Notice\Service\{NoticeAdmission,NoticeRecorder,NoticeStore}`,
`Application\Notice\CommandHandler\RecordNoticeHandler`, and `Domain\Notice\Command\RecordNotice`, beneath
`Fight\Common\`. `$router` may be `InMemoryCommandRouter`, paired with `RoutingCommandBus`. No automatic container
registration is implied. Supply a dedicated DBAL connection, not an unrelated ORM unit of work.

The consumer implements `NoticeAdmission::admit(Notice): void` using **trusted current invocation context**. It must
validate the actual producer, scope, topic/type/content policy and **all** supplied subject/recipient/author/causation
references; throw fixed `NoticeRejected` for denial. Submitted IDs, metadata, `SUPER_ADMIN` labels and existence of a
previous record are never credentials. The recorder invokes admission inside the serialization boundary on every
original and retry, before concealed identity/content lookup and before any notice/relationship/comparison/allocation
change. Acquiring the database lock itself executes a no-change state-row UPDATE. A waiting retry is admitted only
after acquiring that lock, so consumer policy must be read freshly rather than cached when the request started.
There is no allow-all implementation or implicit principal resolution.

Admission, reference validation and recording are distinct from any future read/removal authorization. Public
exceptions and fact references must still be projected under the consumer's disclosure policy; do not expose exception
causes or traces to clients. Admission implementers must not throw private diagnostic text as public denial.

## Transaction, identity and recovery order

`NoticeStore` extends `TransactionalUnitOfWork`: one storage capability owns both persistence and its real transaction,
so application composition cannot accidentally enlist a different repository connection. The DBAL adapter supports
actual SQLite, MySQL and PostgreSQL only. Its reserved schema namespace is:

- `fight_notice_state`: singleton version and last allocated position, also the shared serialization lock;
- `fight_notices`: tuple identity, immutable server time, bounded scalar notice content and dedicated position;
- `fight_notice_relationships`: separate bounded explicit relationship representation;
- `fight_notice_comparisons`: version plus complete exact canonical comparison bytes, not merely an identity or hash.

Related writes and position update commit atomically. Foreign keys cascade dependent relationship/comparison rows
when a future authorized removal deletes a notice. MySQL uses InnoDB and binary collation; accepted ASCII references
have no whitespace/padding ambiguity. SQLite enables foreign keys; consumers configure busy/lock timeouts. Schema
composition uses public DBAL schema-manager APIs, without a new internal-API exception, migration or dependency floor.
`addTo()` supports consumer-owned deployment schemas; seed singleton `(1, 1, 0)` when managing deployment yourself.
`install()` creates an empty namespace or validates the current version marker and identity/recovery indexes; it is
not an exhaustive drift repair/fingerprint or online migration. Partial schemas/unsupported versions reject. MySQL
DDL may partially succeed: stop and recover exclusively rather than rerun recording against incomplete storage.

The tuple **scope + producer + notice ID** owns retry identity. Under the lock, a currently admitted equal version-one
comparison deduplicates without modifying recording time/position or dispatching again. Changed accepted content
raises `NoticeConflict`, with no content disclosure or overwrite. No exception differences are exposed before current
admission. Comparison includes all submitted fields/relationships, never server time or position. Equal retries work
after reconstruction and at position exhaustion; a response interrupted after commit can be retried under current
admission while comparison evidence remains.

Positions are independent signed-portable positive integers **1–2,147,483,647**, scoped to **one installed namespace
in one database**, shared across its producers/scopes. State allocation is explicitly serialized by a state-row write
lock acquired before reads and held through actual commit. It is not ordinary auto-increment/UUID/timestamp order.
A later visible committed position cannot precede a lower-position commit that is still outstanding. Rollback also
rolls back allocation; an admitted retry after rollback receives a position after any subsequently committed notice.
At capacity, new recordings fail without wrapping/coercion; the consumer must plan capacity before exhaustion.
No cross-database order, causality, clock monotonicity, receipt, subscriber processing or exactly-once effect follows.
Direct SQL writers bypassing the shared lock are unsupported. No hidden transient retry is performed.

`RecordedNotice` exposes immutable `notice()`, `position()` and `recordedAt()`. Server recording time is chosen inside
the lock, represented as strict UTC with six fractional digits; it is not the commit instant and does not establish
microsecond clock accuracy. Source occurrence precision is independent and never padded in submitted content.
Neither time controls recovery ordering. Unknown time is not manufactured from the server receipt.

Construction requires an idle connection. Commands reject unsupported enclosing transactions **before beginning or
writing** and never commit/roll back caller work or acknowledge a released savepoint as durable. This includes an ORM
transaction on the same DBAL connection. A different ORM connection is not automatically enlisted either. Do not wrap
this handler in another transactional filter. `commitTransactional()` callbacks are trusted store operations and must
not manipulate transaction lifecycle or call external services. `requireOwnedTransaction()` guards writes; idle
`readAfter()` refuses active transactions, including its own uncommitted writes, and closed stores. Dirty-read
isolation (`READ_UNCOMMITTED` configured through DBAL) rejects; consumers must not bypass isolation through raw SQL.

## Failures and retained reconstruction

Domain structural rejection uses fixed `DomainException` diagnostics. Current denial is `NoticeRejected`; admitted
changed content is `NoticeConflict`. DBAL write failures roll back the owned transaction and become fixed
`NoticeStorageFailed(false, privateCause)`. Begin, commit-acknowledgment or rollback failure closes the store with
`outcomeUncertain() === true`. Cleanup does not establish absence: the database may already have committed. Discard
that boundary/connection, reconstruct storage, and retry explicitly under current admission. No fact is dispatched
for an uncertain acknowledgment. Read/hydration failures use fixed `NoticeStorageFailed`; corrupt/unsupported
comparison or mismatched identity evidence fails closed, never successful empty history.

For a newly acknowledged commit only, `Domain\Notice\Event\NoticeRecorded` passes through existing
`EventDispatcher::trigger()`. Its exact version-one payload contains `id`, `producer`, `scope`, `position`, `recorded_at`
and `version`, **not** topic, labels, recipients, causation, protected data or Command envelope metadata. It describes
recording, not the change's causation. This is a trusted fact, not permission to broadcast even its references.

Later dispatcher/subscriber failure raises `CommittedNoticeDispatchFailed`, whose `event()` retains that safe fact.
The notice remains committed and reconstructable. A matching retry does **not** redispatch, even after publication
failure or uncertain acknowledgment. Dispatch may be lost between commit and trigger. There is no automatic replay,
receipt ledger, outbox, atomicity with the originating application mutation or guarantee that every change produced
a notice. A retained notice cannot repair an earlier event/change that never recorded one.

`NoticeStore::readAfter()` is a trusted storage-level bounded reconstruction seam: exact scope, ascending exclusive
committed position, live retained records. It is **not** a public authorized Query, opaque continuation, completeness
claim or lease. It does not authorize identities/topics or report current retained-history loss; an empty result is
not complete catch-up. Future TICKET-00046 owns public selection/access/token/completeness semantics. No reverse
dependency on that implementation exists.

TASK-00159 must use the **same** `DbalNoticeStore::commitTransactional()` lock and shared `connection()` maintenance
seam to atomically delete bounded selected notices and update bounded access-safe history-loss evidence, including
an empty feed. It must retain the position high-water mark and coordinate all three related rows/comparison lifetime.
This TASK supplies no public deletion or speculative history-loss claims. Current comparison evidence lives only
with the retained notice and is not a perpetual tombstone/archive. Removing it ends duplicate suppression; a later
explicit admitted same-ID submission is new recording at a new server time/position, not restoration of the removed
notice or its recovery history. Consumers own retention duration, eligibility, legal holds, scheduling, archival,
resource timeouts and operational recovery. Never purge rows directly and claim complete recovery.

## Compatibility and public surface

All **17 new declarations** are additive opt-in public APIs classified in `compatibility/manifest.json`:

- Domain: `Notice`, `NoticeMetadata`, `NoticeRelationships`, `NoticeOccurrence`, `RecordedNotice`,
  `Command\RecordNotice`, `Event\NoticeRecorded` (seven final immutable classes).
- Application: `Service\NoticeAdmission`, `Service\NoticeStore` (consumer-implementable interfaces),
  `Service\NoticeRecorder`, `CommandHandler\RecordNoticeHandler`, and four final exceptions
  `NoticeRejected`, `NoticeConflict`, `NoticeStorageFailed`, `CommittedNoticeDispatchFailed` (eight declarations).
- Adapter: `Dbal\DbalNoticeSchema`, `Dbal\DbalNoticeStore` (two final composition/persistence classes).

All classes support their documented public construction/factories and callable methods, not subclass extension;
interfaces promise implementation, not class construction/extension. Private state is not a serialization contract.
Version-one payload/schema are provisional until first tagged, with no development compatibility aliases/migrations.
Existing released CQRS, UnitOfWork, DBAL/Event Store, MCP and framework contracts are unchanged. No Composer constraint,
PHPStan suppression, coverage exclusion or consumer policy is added. Package evidence is not deployed-consumer,
public polling, MCP stream or Mercure/hub proof.

## Verification

Direct model/storage/handler tests exercise finite/exact/over bounds, immutable reference snapshots, unknowns,
comparison, caller transaction rejection, uncertainty and post-commit failure. `NoticeRecordingJourneyTest` routes
Commands through the real handler and actual disposable SQLite/MySQL/PostgreSQL, reconnects repositories, injects
failures across all related writes/commit, and uses independent processes paused after allocation but before commit.
Those workers prove concurrent equal/conflicting retries, fresh admission after waiting, rollback/retry positions
and prefix-safe recovery. Mocks alone or skipped server cases do not qualify an adapter. See TASK-00158 for the
current local build receipt and independent-review/QA state; tests do not establish delivery or consumer deployment.
