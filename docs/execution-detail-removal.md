# Remove captured detail, retain execution evidence

`Domain\Mcp\Detail\Command\RemoveCapturedDetail` removes one explicitly selected database-backed protected
content row through the unchanged **void** `CommandBus::execute()` contract. It complements
[protected Queries](execution-protected-reads.md), [safe Queries](execution-reads.md) and
[recording](execution-observations.md); it is not capture or a retention engine.

## Selection, admission and repeat behavior

```php
use Fight\Common\Domain\Mcp\Detail\Command\RemoveCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\DetailKind;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;

$selection = DetailSelection::create('project', 'session', 'phase', 'producer', $activityUuid);
$commands->execute(new RemoveCapturedDetail($selection, $detailId, DetailKind::MESSAGE));
```

The selection limit is **exactly one detail per Command**. There is no wildcard, list, page, age predicate,
unbounded scan or implicit cascade to sibling details. Use separate Commands for separately selected details;
that consumer sequence is **not** an atomic/recoverable maintenance job. Kinds are exactly `message`,
`tool_arguments`, `tool_result`, `process_output`; an output selection removes one captured segment, not a whole
process stream. Existing `DetailSelection` bounds apply: exact case-sensitive 1–128-byte ASCII project, session,
phase and producer references, and a canonical lowercase complete activity UUID. Detail identity is a typed
`ObservationId`. Serialized payload fields are exactly `selection`, `detail_id`, `kind`; no authority is included.
`fromArray()` rejects unknown/missing fields, lists, malformed UUIDs and unsupported kinds with fixed-text
DomainException. Native PHP type errors remain distinct from value validation.

Consumers implement **`Application\Mcp\Execution\Detail\Service\DetailRemovalAdmission::admit(RemoveCapturedDetail)`**.
It must check **current authenticated removal permission for the complete selected scope and identity**, including
project/session/phase/producer/activity/detail/kind restrictions as applicable. A trusted cleanup caller still
supplies permitted scope. Throw `DetailRemovalRejected` on denial. No permissive implementation is supplied.
Read grants, reference possession, prior successful reads/removals, recording permission, phase names and queued
message metadata grant **no removal authority**. Removal permission need not include body-read permission.
Admission is invoked afresh **inside the common serialization transaction before any record lookup**, even for
repeats and missing identities. Keep decisions effective for the invocation; consumers own revocation
synchronization, bounded policy lookup and trusted context reconstruction for async delivery. Admission must not
commit/roll back the transaction, perform unrelated writes or dispatch observed work.

After admission, `DetailRemover` loads the retained safe detail and its activity through `ObservationRepository`.
`DetailSelection::acceptsDetail()` checks project/session/phase/producer/activity/kind; `DetailCapture::acceptsActivity()`
checks the full retained same-producer/session/phase relationship, including consumer/Agent/parent/name/attempt.
The existing database FKs retain matching session/phase subjects and activity links. Wrong-scope, wrong-kind,
non-detail, missing and concealed identities all reject with the same **`DetailRemovalRejected`** and fixed text
`Captured detail removal was not admitted.` No stored metadata or body is included.

| State after current admission | Result |
| --- | --- |
| Matching safe identity and protected row retained | Delete that protected row; commit before returning void |
| Matching safe identity retained, protected row already gone | Successful no-op after relationship checks and a real transaction commit |
| Safe identity gone, unknown or inconsistent | Fixed concealed rejection, not a successful discovery probe |
| Permission revoked, including a repeat | Reject before lookups; earlier success is not permission |

Repeats do not replay a prior result or event. Concurrent removals serialize: a first successful deletion is
followed by an admitted no-op while safe identity remains. There is no count or body in the acknowledgment.

## Storage, transactions and failure

The operation deletes **only `fight_execution_contents`**, including its exact body/comparison representation
(or a null-body explicit-absence row). It deliberately retains safe detail identity, original capture metadata,
recording time and activity link, as well as activity/usage, session and phase summaries. There is no duplicate
body/digest in safe retry evidence. There are no link mutations to commit separately: the retained links remain
valid and Query availability depends on actual protected-row presence, not historical capture completeness.
No metadata purge, second body store, schema/index change, tombstone reason, migration or retention lease is added.

`ProtectedContentRepository::removeFor(detail)` is trusted storage, **not** public authorization. Its DBAL
implementation requires the **same `DbalRecordingTransaction` object** as participating recording repositories.
The remover calls `RecordingTransaction::commitTransactional()`; the adapter owns the connection, actual commit
and existing `fight_execution_schema` version-row write lock. Recording and removal take that common lock **before
reads** on SQLite, MySQL and PostgreSQL. It serializes schema-wide writers, trading throughput for integrity;
no global causal/commit order or high-throughput batching is promised.

Do not wrap removal in an ORM, Command filter or caller transaction. Already-open, nested, unowned and closed
composition rejects before admission/writes, without committing or rolling back unrelated caller-owned work.
An inner savepoint cannot acknowledge removal. Related database effects of one Command commit together or roll
back; successful void return follows the **real outer commit**, observable from an independent connection.
A failure after the actual delete rolls back that delete. It is not atomic with external files, archives,
retention-policy storage or a consumer's later maintenance/publication step.

Existing transaction failure categories apply unchanged:

- A DBAL operation failure with successful rollback raises `ObservationStorageFailed` with
  `outcomeUncertain() === false`. Other operation throwables propagate after rollback.
- Begin/commit acknowledgment or rollback failure raises uncertain `ObservationStorageFailed` and closes the
  boundary. A timeout/lost acknowledgment **does not prove absence or rollback**; deletion may already be committed.
  Discard that terminal boundary, inspect using currently authorized Queries or repeat the exact removal on a
  fresh idle boundary with fresh permission. Unknown identity may still reject; this is not job recovery.
- Public failure text contains no body. Causes/traces are private diagnostics; database exception context and
  consumer admission failures must not be exposed as public data or logged without the consumer's safe policy.

**No removal domain event is emitted.** This bounded storage operation introduces no removal-event catalog,
subscriber dependency, outbox, delivery retry or Workflow transition. Existing recording facts remain recording-owned.
Consequently no removal subscriber can turn success into a dispatch exception. Any consumer publication performed
later is outside this transaction and cannot undo deletion; it must not be described as storage rollback. Reads
continue to issue no business writes, mutation Commands or domain events.

## Live reads and recording retries

After committed removal, protected individual/message Queries retain permitted capture metadata but return no
chunk. Complete/partial capture becomes `availability: missing` with **unknown reason**. Supplied
absent/unavailable/expired markers remain exactly those facts, even without a protected row. Absence alone is not
expiration, proof of never-capture, successful process output or a complete transcript. Safe activity references
agree with actual row presence and never advertise a missing body as available. Session/phase summaries and safe
activity/usage remain unchanged. Removing bodies alone does not remove message identities from page totals.

Read statements are live, not a response-wide snapshot. An independent read before deletion commits can return
old committed bytes; one after commit observes absence (subject to the consumer's own explicit isolation/snapshot).
If a body was already fetched before removal, its caller-owned result cannot be retracted. Removal between
metadata and body statements can change availability in that response; body retrieval rechecks actual presence.
Later pages/chunks independently recheck permission/storage and acquire **no retention lease**. Do not retain
or restore deleted bytes merely to finish an older browse sequence. Identity purges/inserts may also shift live
offset pages; no multi-page frozen selection is promised. Use ordinary idle read connections for these live
semantics rather than a consumer-held older database snapshot.

Recording retries keep their existing owner and order of checks:

| Retained evidence at recording's lock acquisition | Explicit admitted recording behavior |
| --- | --- |
| Safe identity plus protected row | Same safe/body content deduplicates without new event; changed safe/body content conflicts |
| Safe identity retained, protected row removed | Changed safe metadata conflicts; matching safe metadata raises `ProtectedContentRemoved` regardless of supplied body, without comparison or restoration |
| Safe identity and protected comparison row deliberately purged | Deduplication ended; an explicit currently admitted submission can create new evidence/body with a new receipt time and one recording-event attempt, even if its body differs from the former submission |

Thus a retry winning the lock before removal can deduplicate/conflict, followed by deletion. Removal winning first
prevents an old retry from restoring a body while safe identity remains. Changing only body bytes after removal
cannot be compared to erased bytes and still raises `ProtectedContentRemoved`; no secret body hash/copy is retained.
A safe-metadata conflict remains detectable. A separately purged identity is not a permanent tombstone: if removal
wins while it is absent, removal rejects; a later explicit recording can succeed. If new recording wins first,
removal can delete its now-retained content. This is explicit submission under ended deduplication, not hidden
restoration for an old reader/retry. Current recording admission always runs again.

Identity purge is **not a new public capability here**. Consumer-owned maintenance must use the same serialization
boundary to claim these race guarantees and preserve FKs. Tests deliberately remove identity through an owned
storage fixture to establish the limit. Arbitrary SQL maintenance outside that boundary, database backups,
external archives, consumer logs and already returned bytes are outside this deletion guarantee.

Consumers own retention periods, eligibility, holds, scheduling, batching/recovery, archival coordination, database
roles/timeouts/backoff and whether an observation/removal failure interrupts their work. No completion-time cleanup
rule, human/Agent role hierarchy, MCP removal Tool, UI or Workflow authority is supplied.

## Composition

Reuse recording's already installed, idle database and boundary; do not create an independent content transaction:

```php
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalObservationRepository;
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalProtectedContentRepository;
use Fight\Common\Application\Mcp\Execution\Detail\CommandHandler\RemoveCapturedDetailHandler;
use Fight\Common\Application\Mcp\Execution\Detail\Service\DetailRemover;
use Fight\Common\Domain\Mcp\Detail\Command\RemoveCapturedDetail;

// $transaction is recording's DbalRecordingTransaction on its dedicated connection.
// $removalAdmission implements DetailRemovalAdmission using current trusted consumer context.
$remover = new DetailRemover(
    $transaction,
    new DbalObservationRepository($transaction),
    new DbalProtectedContentRepository($transaction),
    $removalAdmission
);
$router->registerHandler(RemoveCapturedDetail::class, new RemoveCapturedDetailHandler($remover));
```

Register on the consumer's ordinary Command router, independently from safe/protected Query admission and recording
admission. The registered handler is the public authorized path. Alternate storage implementations must preserve
the shared transaction/serialization and retained identity semantics; direct repository access remains trusted.

## Compatibility and public surface

TASK-00154 adds **five opt-in public declarations**: `RemoveCapturedDetail`, `RemoveCapturedDetailHandler`,
`DetailRemover`, implementable `DetailRemovalAdmission` and final `DetailRemovalRejected`. Concrete constructors
and declared methods are callable; only the admission port promises consumer implementation. Domain selection
adds `acceptsDetail()`; the existing provisional `ProtectedContentRepository` adds `removeFor()` and DBAL implements
it. Consumer implementations of this **unreleased** port must implement the operation before using this candidate;
no compatibility shim for older development snapshots is supplied. The compatibility manifest records the five
additions, inventory delta and authorized-removal behavior contract.

Compatibility is against tagged releases, not prior development commits. These Execution additions remain
provisional; tagged CQRS void bus behavior, recording/read results and admission, DBAL 4.4+ support, unrelated
public APIs and schema one remain intact. No dependency-floor, schema/index, migration, alias or codec change is
introduced. Real SQLite/MySQL/PostgreSQL package evidence covers all capture kinds/states, independent committed
visibility, rollback, outer transactions, uncertainty, repeated/missing selection, read/removal and recording races
with retained/purged comparison evidence. This is not release certification or deployed-consumer qualification;
inherited release-wide manifest/fingerprint follow-ups remain release-owned.
