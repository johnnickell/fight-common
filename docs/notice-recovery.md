# Bounded retained-notice recovery

`Domain\Notice\Query\RecoverNotices` returns a typed `Application\Notice\Data\NoticeRecoveryBatch` through
`QueryBus::fetch()`. Explicit `Application\Mcp\Notice\RecoverNoticesTool` registration supplies `notices.recover`
on the existing guarded MCP HTTP endpoint. It is **not** `subscriptions/poll`, a stream, Resource read, acknowledgment,
mutation Tool or Mercure adapter. Only successfully recorded, still-retained [safe notices](safe-notices.md) can be
returned. An application change whose recording failed cannot be recovered.

## Composition and authorization

Imports below are beneath `Fight\Common\`:

```php
$store = new Adapter\Notice\Dbal\DbalNoticeStore($dedicatedIdleConnection);
$protector = new Adapter\Notice\SodiumNoticeContinuationProtector($dedicatedKeys, $activeKeyId);
$recovery = new Application\Notice\Service\NoticeRecovery($store, $protector);
$handler = new Application\Notice\QueryHandler\RecoverNoticesHandler($trustedReadAdmission, $recovery);
$router->registerHandler(Domain\Notice\Query\RecoverNotices::class, $handler);
$selection = Domain\Notice\NoticeSelection::create(['project-a'], ['topic' => 'catalog/tools']);
$batch = $queryBus->fetch(new Domain\Notice\Query\RecoverNotices($selection));
$next = $queryBus->fetch(new Domain\Notice\Query\RecoverNotices($selection, $batch->continuation()));
$tool = new Application\Mcp\Notice\RecoverNoticesTool($queryBus);
```

Install the existing DBAL schema exclusively. Use an idle committed-isolation connection as for recording/removal;
outer transactions, dirty reads, missing/corrupt storage and failed commits reject. SQLite, MySQL and PostgreSQL
share the same serialized storage contract. `NoticeStore::readWindow()` now also accepts a list of 0–16 scopes,
merging bounded rows in ascending committed position under **one** state-row lock. The existing string scope input
remains supported. An empty scope list reads no bodies. This is trusted storage, not a public grant.

Reuse `NoticeReadAdmission::currentAccess(selection)` from [inspection](notice-inspection.md). Every call obtains
fresh whole-notice grants, intersected with the caller selection, before any disclosure. It must additionally supply
`NoticeAccess::create(scopes, filters, recoveryIdentity: new NoticeRecoveryIdentity(caller, revision, namespaceEvidence))`:

- `caller`: trusted current caller reference, 1–96 ASCII bytes. Agents and humans are independently resolved.
- `revision`: nullable trusted access epoch reference with the same bound. A non-null value asserts that the
  consumer changes it on **every intervening permission change**, including revoke/restore, changes in identity
  membership, policy or reference eligibility. Equal current grants alone do not establish historical continuity.
- `namespaceEvidence`: default **false**. True is an explicit permission to learn global namespace history-loss
  evidence, including effects in other scopes. Ordinary scope/body grants do not imply this permission. Set false
  when global evidence would disclose concealed activity, even if the caller currently has all selected notices.

Identity, epoch and grants must come from the **same trusted current admission snapshot**, never Query arguments,
MCP metadata, a supplied role, recipient, reference, prior result or token. Missing caller context fails before
storage. Missing epoch or namespace-evidence permission permits current authorized notices but always reports
`uncertain`, before and after concealed activity. Common supplies no principal model, permission epoch store,
allow-all policy or stronger proof of consumer assertions. Consumers must provide an honest epoch or use null.
Admission is invocation-local, not a permission lease; already disclosed bytes cannot be retracted.

Register `$tool` explicitly in `McpToolRegistry`, then ordinary `McpToolDiscovery` and `McpToolInvocation` with a
consumer `McpToolAvailability`, and the existing `McpRequestHandler` with Origin policy, invocation guard, diagnostics
and PSR response factories. No endpoint or route is registered by this feature. Availability is rechecked independently
from Query access: visible Tool metadata never grants notice access. Discovery performs no notice Query. No other
capability family, live adapter or event subscriber is required. No public Command, receipt or domain event is emitted.

## Selection, continuation and completion

Reuse every `NoticeSelection` scope/filter/relationship/time rule from inspection, including exact producer, topic,
type, subject, recipient, author and separate causation references. All predicates are conjunctive. Half-open time
selection compares instants without changing the returned source occurrence precision. Selected scope grants restrict
storage reads before reconstruction; other trusted and caller predicates filter each bounded candidate before projection.
This bounds reconstructed work, not database query-planner cost or response latency; consumers own statement/lock timeouts.

Query constructor: `(NoticeSelection, ?string continuation = null, int batchSize = 32, int scanLimit = 100,
bool startAtCurrent = false)`.
`fromArray()` requires exactly `selection`, `continuation`, `batch_size`, `scan_limit`, `start_at_current`.
The opt-in current-boundary mode returns no historical notices and excludes earlier loss from the new interval;
it cannot accompany a continuation. It is used by [live subscriptions](mcp-subscriptions.md), not exposed by the
ordinary polling Tool. Its continuation remains bound to the ordinary batch/scan/selection/access configuration.
Null with the default false starts/restarts from position
zero over **current retained data**, not a live-only high-water shortcut. Empty token rejects. There is no caller-supplied
raw position. Use unchanged selection, batch and scan settings for continuation; changing any requires a new start.

`NoticeRecoveryBatch` exposes `notices()`, `status()`, `continuation()` and the same three snake_case projection fields.
Each notice is a complete admitted `NoticeView`: safe metadata/relationships plus server `recorded_at`, never private
positions, comparison bytes, global counters, loss counts, concealed scope relationships or deleted bodies.
The opaque continuation **identifies the boundary actually examined**, not merely the last returned notice. It is
always returned, including completion, and can be used for later polling. It is neither a processing acknowledgment
nor an MCP catalog cursor. Do not decode/log it or infer public counts from it.

Rows, committed high water and conservative loss watermark are read under recording/removal's serialization lock.
One bounded call assesses that observation point; no snapshot/lock survives the call. Positions are namespace-local
**committed order**, not timestamps, UUIDs, causality or global order across unrelated stores. The read performs a
no-change state-row UPDATE only to synchronize; business rows/evidence stay unchanged.

| Status | Qualified meaning |
| --- | --- |
| `complete` | Demonstrated caller/selection/access continuity and permission for namespace evidence; no known loss in the qualified interval; every eligible retained notice through the encrypted examined boundary was included and the observed committed boundary was exhausted |
| `incomplete` | With that trusted continuity/evidence context, either the scan/batch has not exhausted the observed boundary or conservative global loss prevents proving recovery of the interval |
| `uncertain` | Current authorized data only; access continuity or permission to use namespace evidence is unavailable. This status does not vary with concealed global loss or exhaustion |

An empty result **alone proves nothing**. At the exact scan limit, exhaustion needs either the last examined position
being the observed high water or a subsequent bounded call; otherwise it remains incomplete (or uncertain). At a batch
limit, the continuation never crosses the next omitted eligible notice. Nonmatching candidates may be examined and
advanced past. No eligible notice is silently truncated or dropped. Multiple batches can establish genuine completion
in a stable, unbroken, suitably authorized history. Later committed notices remain recoverable with the same token;
completion says nothing about future writes, unrecorded application changes, receipt or processing.

Known global loss is conservative: it may concern nonmatching data, and remains sticky in that recovery chain. Removal
of an already examined position does not invalidate its subsequent suffix. Removal after the examined boundary, including
the last notice, prevents complete recovery even on an empty feed. A restart at zero does not erase known loss. With
only a global watermark there is no safe way to prove a narrower deleted history; use consumer current-data reload.
For partial/unproven context there is deliberately no public `has_more`, exhausted flag, gap reason or examined count;
continue a bounded number of calls according to consumer policy, or reload, rather than interpreting uncertain empty
batches as success. Grants newly widened or narrowed change the binding and reject old continuation so older newly
eligible notices cannot be silently skipped. A changed caller/epoch, selected context, retired namespace, invalid/expired
token or unavailable state similarly requires restart/evidence recovery, not an unsupported complete claim.

Rows/loss evidence cannot straddle a committed removal. Concurrent changes after the observation point may invalidate a
future continuation, not the earlier qualified statement. No retention lease, deleted-body archive, frozen selection,
subscription, automatic retry, exactly-once processing or automatic target/resource fetch exists.

## Protection, finite budgets and safe failure

`NoticeContinuationProtector` is an Application port requiring authenticated confidentiality. The optional
`SodiumNoticeContinuationProtector` needs `ext-sodium`; it uses XChaCha20-Poly1305 with a random 24-byte nonce,
dedicated protocol associated data, and 1,024-byte zero-padded state. State must be at most 1,023 bytes without raw NUL.
Tokens are fixed **1,432 ASCII bytes**, including `v1`, an eight-character key ID and canonical standard Base64 ciphertext.
Padding hides counter/state length; Base64 alone or a signature would not meet this contract. Public key IDs are exactly
`[a-zA-Z][a-zA-Z0-9_-]{7}`. Configure 1–8 keys of exactly 32 random bytes; the active key must exist. Keep keys outside
requests/storage and dedicate them to this notice store/protocol. Retain old keys only for the desired outstanding
lifetime; unknown/retired keys fail safely. No fallback cipher or unsigned token mode exists.

Encrypted version-one state binds the namespace, examined position, sticky loss, expiry, canonical caller/epoch/grants,
selection, batch/scan settings, TTL and result budget. No notice content or concealed scope catalog is included. Current
admission is still required on every call; token possession grants nothing. `NoticeRecovery` TTL defaults to 300 seconds,
configurable **1–900**; each successful call issues a fresh expiring checkpoint. Consumers synchronize clocks. Expired or
future-out-of-policy state rejects; replay within lifetime is permitted and reads current authorized data, not a server-side
acknowledgment ledger. A live caller can renew; old keys/epochs/namespace retirement terminate old checkpoints.

| Budget | Exact bound / behavior |
| --- | --- |
| Selection | 0–16 scopes, 14 allowed filters; references 1–96 bytes, existing strict UTC times |
| Continuation input | Null or nonempty string of at most 2,048 bytes; malformed/unauthenticated state fails |
| Scan | 1–100 reconstructed retained rows per coherent window; default 100 |
| Batch | 1–32 complete notices; default 32; each underlying safe Notice at most 6,144 encoded bytes |
| Query projection | Default 65,536 bytes under ordinary `json_encode`, configurable 2,048–262,144 via `NoticeRecovery::maxResultBytes` constructor parameter |
| Complete MCP response | Default 1,048,576 bytes; Tool constructor can narrow to 1–1,048,576 bytes |
| HTTP input | Existing shared `McpRequestLimits`, default 1 MiB encoded request, bounded reading/depth |

Tool arguments are exactly `scopes` (required), optional `filters`, nonempty `continuation`, `batch_size`, `scan_limit`;
unknown fields reject even on direct Tool invocation. Input schema bounds strings/counts; shared Query validates the
reference grammar and instants. Output schema defines complete notices/status/continuation. Metadata is an object,
including `{}` for empty metadata; null recipients and broadcast `[]` stay distinct. Structured content and its matching
text companion represent the **same complete result**. `McpToolOutput::boundedStructured()` carries the final limit
through schema validation, text duplication, escaping, request ID, actual central server metadata and outer JSON-RPC
envelope. Output-schema/JSON safety alone is not that limit. No JSend wire envelope is used.

Projection overflow, a single notice that cannot fit, final encoded overflow, storage/integrity failures and token/context
errors fail the entire request **without returning an advanced continuation**, not empty success or truncation. Retry
with the same last successfully returned checkpoint and unchanged settings after resolving the failure; changing serving
settings requires restarting. Query failures use fixed `NoticeReadFailed` text, “Retained notices could not be read
completely.” Causes are private. The default MCP path returns sanitized `-32603` / “Internal error.”, while schema
rejection uses the existing Tool input failure. Unknown/hidden Tools share invalid parameters; unknown/denied notice
selections disclose no rows. Consumers may explicitly map fixed failure text under existing Tool failure mapping but
must never publish token internals, storage exceptions or diagnostics. Safe failures do not prove expiry or absence.

Consumers own cadence, bounded continuation attempts, UI/reload policy and any processing acknowledgment. On incomplete
loss, uncertainty or restart, reload **current authorized data** as appropriate; restarting does not recover deleted notices.

## Compatibility and evidence boundary

Seven new declarations are public and opt-in: final `RecoverNotices`, `NoticeRecoveryBatch`, `NoticeRecoveryIdentity`,
`NoticeRecovery`, `RecoverNoticesHandler`, `RecoverNoticesTool`, and `SodiumNoticeContinuationProtector`.
`NoticeContinuationProtector` is an eighth, consumer-implementable public interface; `NoticeToolSchemas` is internal.
The eight public declarations promise documented construction/calls, not subclass extension; the interface promises
implementation, not construction. `compatibility/manifest.json` classifies them. `NoticeAccess` gains optional trusted
recovery context; `NoticeStore::readWindow` widens to a scope list for coherent merged ordering. These are **unreleased
provisional Notice API revisions** against tagged releases, not a compatibility guarantee for old development adapter
implementations. No existing released declaration, MCP seam, schema/index, dependency floor or framework behavior changes;
no development migration, alias or codec is introduced. Sodium was already optional; this composition also requires it.

Direct tests qualify query/data/identity/handler/continuation/Tool bounds and exact owned statements. Routed functional
journeys seed through recording, remove through the independent removal Command, and use real SQLite/MySQL/PostgreSQL.
They cover publication failure, multi-batch committed order, delayed commits, both recovery/removal lock orders, fresh
access/caller/epoch/selection changes, complete/incomplete/uncertain outcomes, final deletion, retired/missing evidence,
concealed sentinels, scan exhaustion and safe failures. Exact encoded-envelope tests hold only the encryption nonce output
constant at the protector port to isolate byte arithmetic, while still using actual DBAL Query/guarded HTTP composition;
ordinary journeys exercise real randomized authenticated encryption. Escaping, multibyte data, compatible text, central
metadata and request-ID overhead are included. A controlled failing dispatcher is **not live MCP/Mercure delivery proof**.
No Agent OS/Pi/browser/client/hub deployment, consumer policy correctness, package release or production qualification is
claimed. TASK-00161 owns Work gate receipts and the separate independent Review/QA checkpoints.
