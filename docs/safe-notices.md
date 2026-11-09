# Durable safe change notices

This opt-in capability records bounded change **notices**, not authoritative application state, execution
observations or an Event Store. `Domain\Mcp\Notice\Command\RecordNotice` uses the existing void
`CommandBus::execute()` contract. Callers supply a stable notice ID; successful return follows actual commit.
`RemoveNotices` supplies independently authorized bounded removal. [Authorized inspection](notice-inspection.md)
adds public summary/history/detail Queries with fresh independent read admission. [Bounded recovery](notice-recovery.md)
adds the shared recovery Query and explicit guarded MCP polling Tool. Separately composed
[live MCP subscriptions](mcp-subscriptions.md) and [optional Mercure output](notice-publication.md) consume the
shared feed without becoming recording dependencies. Recording itself provides no MCP mutation Tool, automatic
capture, retention-policy engine or outbox.

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
| Reconstruction | `readAfter(scope, position, limit)` and `readWindow(scope, position, limit)`: exclusive position 0–2,147,483,647, limit 1–100; at most 100 bounded records, never an unbounded iterator |
| Removal selection | `NoticeRemovalSelection::fromArray()`: exactly integer `version: 1`, `namespace` (32 lowercase hexadecimal characters), and a list of 1–100 exact targets |
| Removal target | Exactly `scope`, `producer`, `id` (the same 1–96-byte reference contract) and integer `position` (1–2,147,483,647); duplicate positions or tuple identities reject |

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
$transactionalUnitOfWork = new DbalNoticeTransaction($connection);
$noticeRepository = new DbalNoticeRepository($transactionalUnitOfWork);
$noticeRecoveryReader = new DbalNoticeRecoveryReader($connection);
$noticeRecorder = new NoticeRecorder($noticeRepository, $transactionalUnitOfWork, $trustedAdmission, $eventDispatcher);
$recordNoticeHandler = new RecordNoticeHandler($noticeRecorder);
$commandRouter->registerHandler(RecordNotice::class, $recordNoticeHandler);
$commandBus->execute(RecordNotice::fromArray($safeNoticeData));
```

Imports are `Adapter\Mcp\Persistence\Notice\Dbal\{DbalNoticeSchema,DbalNoticeTransaction,DbalNoticeRepository,DbalNoticeRecoveryReader}`,
`Domain\Mcp\Notice\NoticeRepository`, `Application\Repository\TransactionalUnitOfWork`,
`Application\Mcp\Notice\Service\{NoticeAdmission,NoticeRecorder,NoticeRecoveryReader}`,
`Application\Mcp\Notice\CommandHandler\RecordNoticeHandler`, and `Domain\Mcp\Notice\Command\RecordNotice`, beneath
`Fight\Common\`. `$commandRouter` may be `InMemoryCommandRouter`, paired with `RoutingCommandBus`. No automatic container
registration is implied. Supply a dedicated DBAL connection, not an unrelated ORM unit of work.

The consumer implements `NoticeAdmission::admit(Notice): void` using **trusted current invocation context**. It must
validate the actual producer, scope, topic/type/content policy and **all** supplied subject/recipient/author/causation
references; throw fixed `NoticeRejected` for denial. Submitted IDs, metadata, `SUPER_ADMIN` labels and existence of a
previous record are never credentials. The recorder invokes admission inside the serialization boundary on every
original and retry, before concealed identity/content lookup and before any notice/relationship/comparison/allocation
change. Acquiring the database lock itself executes a no-change state-row UPDATE. A waiting retry is admitted only
after acquiring that lock, so consumer policy must be read freshly rather than cached when the request started.
There is no allow-all implementation or implicit principal resolution.

Admission, reference validation and recording are distinct from read/removal authorization. Public
exceptions and fact references must still be projected under the consumer's disclosure policy; do not expose exception
causes or traces to clients. Admission implementers must not throw private diagnostic text as public denial.

## Transaction, identity and recovery order

Domain `NoticeRepository` owns only aggregate identity lookup, append and exact removal; it neither extends
`TransactionalUnitOfWork` nor exposes recovery/reporting. `NoticeRecorder` and `NoticeRemover` inject the repository
and existing `TransactionalUnitOfWork` separately. `DbalNoticeRepository` takes the exact `DbalNoticeTransaction`
that owns its dedicated connection, preventing accidental writes under another transaction, even on that connection.
The transaction seam owns namespace locking, schema validation and Notice failure translation; reusable begin/commit/
rollback mechanics stay in TASK-00184's `Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork`.
`DbalNoticeRecoveryReader` separately owns coherent read transactions and bounded feed reconstruction. Authorized
live browsing remains `NoticeReader`/`DbalNoticeReader`, not a second role on the write repository or recovery port.
The DBAL adapters support actual SQLite, MySQL and PostgreSQL only. Their default schema namespace is:

- `fight_notice_state`: singleton version, last allocated position, random namespace incarnation and conservative
  `lost_through` boundary, also the shared serialization lock;
- `fight_notices`: tuple identity, immutable server time, bounded scalar notice content and dedicated position;
- `fight_notice_relationships`: separate bounded explicit relationship representation;
- `fight_notice_comparisons`: version plus complete exact canonical comparison bytes, not merely an identity or hash.

Related writes and position update commit atomically. Foreign keys cascade dependent relationship/comparison rows
when authorized removal deletes a notice. MySQL uses InnoDB and binary collation; accepted ASCII references
have no whitespace/padding ambiguity. SQLite enables foreign keys; consumers configure busy/lock timeouts. Schema
composition uses public DBAL schema-manager APIs, without a new internal-API exception, migration or dependency floor.
`addTo()` supports consumer-owned deployment schemas; seed singleton `1`, version `1`, last position `0`, a fresh
`bin2hex(random_bytes(16))` namespace and loss boundary `0` when managing a new deployment yourself.
`install()` creates an empty namespace or validates the current version marker, identity/recovery indexes,
namespace/loss and comparison-version columns, and dependent cascading FKs to the selected Notice parent; it is
not an exhaustive drift repair/fingerprint or online migration. Partial schemas/unsupported versions reject. MySQL
DDL may partially succeed: stop and recover exclusively rather than rerun recording against incomplete storage.

### Explicit physical table selection

The [eight-table lifecycle composition and SQL inventory](lifecycle-storage.md) connects this actual supplier to
configured subscriptions and Tasks in independent-process live journeys. Generic live signals still use authorized
Notice recovery; protected Task input/result/full state stays outside retained Notices. Naming adds no disclosure,
retention, replay or delivery promise.

`Adapter\Mcp\Persistence\Notice\Dbal\DbalNoticeTables` independently selects all four tables. Its constructor
arguments and exact defaults are `stateTable: 'fight_notice_state'`, `noticeTable: 'fight_notices'`,
`relationshipTable: 'fight_notice_relationships'` and `comparisonTable: 'fight_notice_comparisons'`.
Omitted arguments intentionally retain their own defaults; there is no inferred prefix or environment lookup.
Share one selection explicitly across schema installation and the separated adapters:

```php
$noticeTables = new DbalNoticeTables(
    stateTable: 'app_notice_state',
    noticeTable: 'app_notices',
    relationshipTable: 'app_notice_relationships',
    comparisonTable: 'app_notice_comparisons'
);
(new DbalNoticeSchema($noticeTables))->install($connection); // Explicit deployment action only.
$transactionalUnitOfWork = new DbalNoticeTransaction($connection, $noticeTables);
$noticeRepository = new DbalNoticeRepository($transactionalUnitOfWork); // Inherits its exact selection.
$noticeReader = new DbalNoticeReader($readerConnection, $noticeTables);
$noticeRecoveryReader = new DbalNoticeRecoveryReader($recoveryConnection, $noticeTables);
// Partial example: only the comparison name changes; the other three retain their defaults.
$partialNoticeTables = new DbalNoticeTables(comparisonTable: 'app_notice_comparisons');
```

Use the same explicit selection in independent connections/workers, inspection, recovery and publication
composition. No table knowledge enters Domain/Application contracts or grants recording, reading, removal or
publication permission. `DbalNoticeSchema::TABLES` remains the default-name inventory, not the selected names,
with deprecation first shipped in **released v1.5.0**, targeting separately authorized v2.0.0
removal after the released-minor window. Use the `DbalNoticeTables` accessors instead; see the
[complete constant migration map and release-window limits](maintenance/table-name-constants.md).
The transaction's `tables()` exposes its immutable selection for Adapter composition, not a runtime setter.

Shared `Adapter\Persistence\Doctrine\DbalTableName` accepts exactly `[A-Za-z_][A-Za-z0-9_]{0,62}` (1–63 ASCII
characters), preserving case. Ordinary reserved words work through schema/platform quoting. Qualified, prequoted,
unsafe and unsupported names reject with `InvalidArgumentException`, without SQL interpretation or fallback.
The selection rejects case-insensitive collisions among its four tables; consumers own cross-capability collisions,
connection/schema selection and privileges. Identity/recovery indexes retain their default names for the default
Notice table and gain a stable table-derived suffix for renamed Notice tables so independent installations can coexist.
Both dependent FKs always target the selected Notice table.

Configuration, constructors and schema definition perform no installation, rename, migration, data copy or
continuity reset. Runtime uses only selected names; missing/incompatible selected storage fails explicitly, never
falls back to populated defaults. `install()` rejects a partially present selected set rather than completing or
retargeting old FKs. A partial override can intentionally reuse omitted tables, so it is not an independent new
namespace; do not switch a populated installation one table at a time or mix state/comparisons from different
histories. Consumers own any operational data movement, lock/position coherence and deployment sequencing.
A fully new installed selection starts empty with a fresh namespace incarnation; it does not prove old history was
migrated or make an old continuation/removal target valid. Retain conservative recovery uncertainty/loss handling.

### Retained identity

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
this handler in another transactional filter. Auto-commit-off connections reject before callback/admission without
changing mode or caller work; default lazy connections need no extra setup. `commitTransactional()` callbacks are
trusted operations and must not manipulate transaction lifecycle or call external services. The notice transaction's
`requireOwnedTransaction()` guards repository writes. Recovery-reader `readAfter()` refuses active transactions,
including uncommitted notice writes, and a reader closed by its own uncertain read transaction. Dirty-read
isolation (`READ_UNCOMMITTED` configured through DBAL) rejects; consumers must not bypass isolation through raw SQL.

## Failures and retained reconstruction

Domain structural rejection uses fixed `DomainException` diagnostics. Current denial is `NoticeRejected`; admitted
changed content is `NoticeConflict`. DBAL write failures roll back the owned transaction and become fixed
`NoticeStorageFailed(false, privateCause)`. Begin, commit-acknowledgment or rollback failure closes the transaction
boundary with
`outcomeUncertain() === true`. Cleanup does not establish absence: the database may already have committed. Discard
that boundary/connection, reconstruct storage, and retry explicitly under current admission. No fact is dispatched
for an uncertain acknowledgment. Read/hydration failures use fixed `NoticeStorageFailed`; corrupt/unsupported
comparison or mismatched identity evidence fails closed, never successful empty history.

For a newly acknowledged commit only, `Domain\Mcp\Notice\Event\NoticeRecorded` passes through existing
`EventDispatcher::trigger()`. Its exact version-one payload contains `id`, `producer`, `scope`, `position`, `recorded_at`
and `version`, **not** topic, labels, recipients, causation, protected data or Command envelope metadata. It describes
recording, not the change's causation. This is a trusted fact, not permission to broadcast even its references.

Later dispatcher/subscriber failure raises `CommittedNoticeDispatchFailed`, whose `event()` retains that safe fact.
The notice remains committed and reconstructable. A matching retry does **not** redispatch, even after publication
failure or uncertain acknowledgment. Dispatch may be lost between commit and trigger. There is no automatic replay,
receipt ledger, outbox, atomicity with the originating application mutation or guarantee that every change produced
a notice. A retained notice cannot repair an earlier event/change that never recorded one.

`NoticeRecoveryReader::readAfter()` is a trusted storage-level bounded reconstruction seam: exact scope,
ascending exclusive
committed position, live retained records. It is **not** a public authorized Query, opaque continuation, completeness
claim or lease. It does not authorize identities/topics or report current retained-history loss; an empty result is
not complete catch-up. [Inspection Queries](notice-inspection.md) separately enforce public selection/current access;
[Bounded recovery](notice-recovery.md) supplies polling/token/completeness semantics. No reverse dependency on recovery exists.

## Bounded authorized removal

Compose the same repository and transaction boundary with an **independent** current permission implementation:

```php
$noticeRemover = new NoticeRemover($noticeRepository, $transactionalUnitOfWork, $trustedRemovalAdmission);
$commandRouter->registerHandler(RemoveNotices::class, new RemoveNoticesHandler($noticeRemover));
$selection = NoticeRemovalSelection::fromArray([
    'version' => 1,
    'namespace' => $trustedStorageWindow->namespace(),
    'targets' => [
        ['scope' => 'project-a', 'producer' => 'catalog', 'id' => 'change-42', 'position' => 42]
    ]
]);
$commandBus->execute(new RemoveNotices($selection));
```

Additional imports beneath `Fight\Common\` are Domain `Notice\NoticeRemovalSelection` and
`Notice\Command\RemoveNotices`, Application `Notice\Service\{NoticeRemover,NoticeRemovalAdmission}` and
`Notice\CommandHandler\RemoveNoticesHandler`. Selection normally comes from trusted storage/consumer selection;
inspection results intentionally omit storage positions/namespace and cannot construct removal targets by themselves.
No public continuation token is introduced here. Never give an untrusted caller raw global
storage state merely to construct this Command.

`NoticeRemovalAdmission::admit(selection)` must authorize **every** selected tuple using fresh trusted invocation
context before any identity lookup, even for missing/repeated targets. `admitRetained(Notice)` must independently
validate all currently permitted retained content/scope/relationships. Both run under recording's shared lock;
all matching retained records are admitted **before any deletion**. Throw fixed `NoticeRemovalRejected` on denial.
Recording/read grants, IDs, topics, `SUPER_ADMIN`, claimed cleanup identity and cutoffs are not removal grants.
There is no cutoff/predicate input or unbounded sweep. Consumers own retention eligibility, duration, legal holds,
scheduling, archival, resource timeouts and cleanup-job recovery.

The handler returns no existence/affected count. Missing tuples, wrong positions, replaced identities, stale
namespaces and permission denial all share fixed `NoticeRemovalRejected` **without any selected mutation**. This
avoids distinguishing a concealed retained notice from an unknown one through a successful missing-record no-op.
Current permission is required again on every repeat, including after revocation. A repeat after successful deletion
therefore rejects safely, just like unknown/denied; it is not a receipt and cannot explain whether the earlier request
committed. After a lost reply, inspect only separately authorized current state or reselect explicitly under consumer
policy. Matching tuple **and position** pins one incarnation: repeating old removal cannot delete a subsequent same-ID
recording. Targets in other producer/scope namespaces and unselected survivors remain unchanged.

One actual transaction deletes all matching notices, cascades relationships/comparison rows and advances
`lost_through` to the maximum of its prior value and positions actually deleted. It never resets/decrements
`last_position`. Any precommit failure rolls back the entire selection and loss evidence, including earlier
selected deletions. Commit/rollback uncertainty uses `NoticeStorageFailed::outcomeUncertain()` and closes the boundary
as for recording. Enclosing transactions reject before admission/writes without touching caller work. Success is
actual commit, not savepoint release. The comparison bytes live **only while the notice remains retained**: before
removal identical admitted retries deduplicate and conflicting ones reject; after committed removal both identical
and changed submissions can be explicitly re-admitted as **new** recording at a later position/new server time.
No old time/position or original retained notice is restored. There is no indefinite duplicate suppression or body
archive. A rolled-back removal retains the original comparison guarantees.

**No removal event is defined.** The void Command proves neither subscriber receipt nor processing, expiry,
business success or Workflow state. No dispatcher/replay/outbox is added; later consumer publisher failures cannot
undo successful removal and must not be described as storage rollback.

## Qualified storage recovery contract

`NoticeRecoveryReader::readWindow(scope, position, limit)` accepts a string scope or list of 0–16 scopes for a coherent
merged committed-order window, and returns Application `Notice\Data\NoticeRecoveryWindow` with
`notices()`, `namespace()`, `highWater()`, `lostThrough()` and `hasUnbrokenHistoryAfter(namespace, examinedPosition)`.
It is a **trusted storage seam**, not a public authorized Query, access grant or continuation format. On an idle
connection, `DbalNoticeRecoveryReader` owns the required real read transaction using the same namespace serialization
lock as recording/removal and reads rows plus validated state coherently. Its caller supplies no transaction object
or begin/read-state/read-rows/commit sequence. A snapshot cannot mix pre-deletion rows with post-deletion loss state,
or vice versa; its immutable row
list detaches caller-owned PHP array references. The read transaction performs a no-change lock UPDATE; it mutates
no business evidence. No lease survives its return. Ordinary idle
`readAfter()` remains available, but its rows alone cannot establish recovery continuity.

The single global loss boundary and namespace survive an entirely empty feed. Constant storage is **one state row**
with five bounded scalar columns; removal adds no per-ID/per-scope rows, deleted bodies, relationships, counts,
timestamps or comparison copies. The boundary deliberately overapproximates affected history: loss in an unrelated
scope can invalidate a global storage proof. `hasUnbrokenHistoryAfter()` is false for another namespace, an examined
position before loss, a negative position or one beyond high water. True establishes only that this namespace has
not lost retained storage **after** that already established boundary. It does not prove the caller examined all
eligible data, maintained the same current access/selection, exhausted a batch, received a notice or observed every
application change. A loss at the examined position itself does not invalidate the suffix after it.

**Access-safe projection is mandatory.** These namespace-wide counters and predicate results are not public data.
They must never disclose concealed activity, identities, counts or scopes through raw values, token plaintext or
state-dependent gap responses. If the caller is not authorized for the complete namespace, or continuity of its
selection/access is unproven, public recovery must conservatively report **uncertain independently of concealed
removals** unless TICKET-00046 establishes an access-safe narrower proof. For example, a scope-A-only caller cannot
be told that a scope-B deletion occurred; neither the raw watermark nor a new gap flag may reveal it. It may receive
the same conservative uncertainty before and after that concealed activity. Only a suitably authorized whole-namespace
composition can directly use the global proof, still requiring protected continuation, fresh admission, bounded
scanning/output and an identified examined boundary. TICKET-00046 owns those public semantics, including changed
permissions; this storage contract supplies no token/public completeness guarantee or reverse dependency.

Missing/corrupt evidence raises a failure, **never complete empty history**. Persisted positions are validated before
casting, so malformed SQLite values cannot turn unavailable loss evidence into zero. No ordinary operation retires
the state row, resets its loss boundary or reuses ordering. If exclusive operator recovery must retire loss evidence, preserve
high water, rotate to a fresh namespace and conservatively set loss through high water; old continuations become
uncertain/restart and stale removal selections reject. Unavailable state remains a failure until safely recovered.
Database recreation/loss invalidates continuity entirely; a new namespace is not proof of the previous history.
Unsupported direct SQL purges bypassing this contract cannot be certified complete. Empty results/absence alone
prove neither catch-up, expiry nor delivery.

## Compatibility and public surface

The current recording surface is opt-in and classified in `compatibility/manifest.json`.
`Notice::identity()` returns `NoticeId`, the exact scope/producer/local-ID tuple used by
`NoticeRepository::getByIdentity(NoticeId)`. Notice's independent reference grammar and all stored/wire bytes
remain unchanged. Metadata, relationships, occurrence and selections now extend ValueObject, with same-type
value equality/hash, stable text reconstruction and intentional structured JSON. Null unknown recipients remain
distinct from empty broadcast recipients. `RecordedNotice` deliberately retains its substantive commit position;
it is not deleted by analogy with execution's removed receipt. See the
[complete source-linked classification](maintenance/evidence-domain-values.md).

The declarations are:

- Domain: final immutable `Notice`, `NoticeId`, `NoticeMetadata`, `NoticeRelationships`, `NoticeOccurrence`, `RecordedNotice`,
  `Command\RecordNotice`, `Event\NoticeRecorded`, and consumer-implementable `NoticeRepository`.
- Application: consumer-implementable `Service\NoticeAdmission` and `Service\NoticeRecoveryReader`,
  final `Service\NoticeRecorder`, `CommandHandler\RecordNoticeHandler`, and four final exceptions
  `NoticeRejected`, `NoticeConflict`, `NoticeStorageFailed`, `CommittedNoticeDispatchFailed`.
- Adapter: final public `Dbal\DbalNoticeSchema`, `Dbal\DbalNoticeTransaction`, `Dbal\DbalNoticeRepository` and
  `Dbal\DbalNoticeRecoveryReader`. Final `Dbal\DbalNoticeRecords` is internal persisted-evidence validation,
  with no consumer construction, call, extension or implementation promise.

Removal adds **seven** opt-in public declarations: Domain `NoticeRemovalSelection` and `Command\RemoveNotices`;
Application `Service\NoticeRemovalAdmission`, `Service\NoticeRemover`, `CommandHandler\RemoveNoticesHandler`,
`Exception\NoticeRemovalRejected` and `Data\NoticeRecoveryWindow`. The admission interface is consumer-implementable;
the six classes are final with their documented factories/construction and callable methods. TASK-00186 replaces
combined `NoticeStore`/`DbalNoticeStore` directly after inventorying every current local tag (`1.1.0`, `v1.0.0` through
`v1.4.0`): none contains Notice declarations. The new repository/transaction/recovery interfaces and descriptive
constructor parameters revise **unreleased provisional** APIs directly; no released named parameter changes.
No development
migration, compatibility alias or historical schema codec is required; existing partial/old development schemas
must be recreated or recovered exclusively, not silently accepted. The current recording/removal APIs comprise
28 public declarations and one internal helper;
[inspection](notice-inspection.md#compatibility-and-public-surface)
adds 15 separate opt-in read declarations. [Recovery](notice-recovery.md#compatibility-and-evidence-boundary) adds eight
public declarations and one internal schema helper; the separate recovery reader accepts coherent scope lists.
[Optional publication](notice-publication.md#compatibility-and-evidence) adds six independent public Application
declarations without changing storage or publisher contracts.

All classes support their documented public construction/factories and callable methods, not subclass extension;
interfaces promise implementation, not class construction/extension. Private state is not a serialization contract.
Version-one payload/schema are provisional until first tagged, with no development compatibility aliases/migrations.
Existing released CQRS, UnitOfWork, DBAL/Event Store, MCP and framework contracts are unchanged. No Composer constraint,
PHPStan suppression, coverage exclusion or consumer policy is added. Package evidence is not deployed-consumer,
MCP stream or Mercure/hub proof; polling has its own [recovery evidence](notice-recovery.md#compatibility-and-evidence-boundary).

## Verification

Direct model/storage/handler tests exercise finite/exact/over bounds, immutable reference snapshots, unknowns,
comparison, caller transaction rejection, uncertainty and post-commit failure. `NoticeRecordingJourneyTest` routes
Commands through the real handler and actual disposable SQLite/MySQL/PostgreSQL, reconnects repositories, injects
failures across all related writes/commit, and uses independent processes paused after allocation but before commit.
Those workers prove concurrent equal/conflicting retries, fresh admission after waiting, rollback/retry positions
and prefix-safe recovery. Mocks alone or skipped server cases do not qualify an adapter. See TASK-00158 for the
recording build receipt and independent-review/QA state. TASK-00186 migrates these scenarios to separate repository,
transaction and recovery-reader composition; `DbalNoticeRepositoryTest` also proves rejection of unrelated generic
or Notice boundaries, caught nesting, auto-commit-off admission and preserved callback results.
`NoticeRemovalSelectionTest` and `DbalNoticeRemovalTest`
directly qualify removal bounds, immutability, preflight admission, rollback, storage loss, stale targets and handlers.
`NoticeRemovalJourneyTest` uses actual disposable SQLite/MySQL/PostgreSQL, seeds through the real recording Command,
checks survivors and empty reconstruction, performs 128 record/remove cycles per backend, injects deletion/loss/
commit faults including lost acknowledgment, preserves caller transactions and tests both lock orders with independent
processes. Its coherent-window race supplies downstream recovery fixtures without public polling. TASK-00159 owns
that removal build receipt and independent Review/QA handoff; tests do not establish delivery or consumer deployment.
