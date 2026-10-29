# Optional retained-notice publication

`Application\Mcp\Notice\Publication` adds one explicit, synchronous, single-topic attempt through the existing
`Application\Socket\Publisher::push()` / `PrivatePublisher::pushPrivate()` ports. Optional
`Adapter\Socket\MercureHubPublisher` and `PrivateMercureHubPublisher` translate it into a Symfony Mercure `Update`.
There is no automatic registration, new business Command/event, socket abstraction, store, outbox or retry worker.

## Composition and committed evidence

First compose [safe recording](safe-notices.md), [shared inspection](notice-inspection.md) and
[recovery](notice-recovery.md). Route `RecordNotice` synchronously to `RecordNoticeHandler` / `NoticeRecorder` using
`DbalNoticeStore`; route `GetNotice` to `GetNoticeHandler` with fresh `NoticeReadAdmission` and `DbalNoticeReader`.
The store owns an actual transaction and rejects enclosing caller transactions, including savepoints. The reader
requires idle committed isolation. Use the same notice namespace and committed database for both capabilities;
a separate idle reader connection is supported. Never substitute a queued CommandBus or a Query handler returning
caller-asserted saved payloads. These collaborators are trusted composition, not an untrusted remote API.

```php
use Fight\Common\Adapter\Socket\MercureHubPublisher;
use Fight\Common\Adapter\Socket\PrivateMercureHubPublisher;
use Fight\Common\Application\Mcp\Notice\Publication\NoticePublication;
use Fight\Common\Application\Mcp\Notice\Publication\RecordNoticeAndPublish;

// $commands and $queries have the synchronous/shared handlers described above.
// $policy and $diagnostics are consumer implementations; $hub is a HubInterface.
$output = new NoticePublication(
    $queries,
    $policy,
    $diagnostics,
    new MercureHubPublisher($hub),
    new PrivateMercureHubPublisher($hub)
);
$status = (new RecordNoticeAndPublish($commands, $output))->record($recordNotice);
```

`record()` executes the existing Command before calling `publish(new GetNotice(scope, producer, id))`.
`publish()` accepts only identity selection, reconstructs retained content through the shared authorized Query,
and then requests a separate current publication decision. It never accepts arbitrary asserted committed bodies,
reads a referenced target, runs SQL or creates a competing read/recovery policy. Removal or read revocation before
reconstruction returns `not_available`. Publication neither starts nor commits/rolls back a transaction; hub I/O
occurs after the owned recording commit. Consumers must keep callbacks free of enclosing database transactions
and use ordinary provider/SQL timeouts: finite data bounds cannot preempt blocked I/O.

Recording failures propagate unchanged and make **no publication attempt**. `NoticeStorageFailed` retains its
existing safe text and uncertainty flag; uncertain commit acknowledgment does not prove rollback. Existing
`CommittedNoticeDispatchFailed` means storage committed but the recording fact's later dispatch failed; it also
propagates unchanged, without running this optional output. The consumer may explicitly attempt `publish()` later
or retry the original Command. No catch-all converts either recording exception into a publication status.

## Independent current topic/disclosure policy

Implement `NoticePublicationPolicy::currentGrant(NoticeView): ?NoticePublicationGrant` using trusted current
consumer context. Return null to deny. Read permission, scope/producer IDs, intended recipients, a claimed
`SUPER_ADMIN` label and a private flag are **not** broadcast authority. The policy must authorize disclosure to
the entire selected topic's audience, not merely the caller who read the notice. This decision runs separately
on every attempt, including retries. It must authorize the mandatory scope/producer/notice identity too.

For example, after the consumer's independent whole-audience check, return:

```php
use Fight\Common\Application\Mcp\Notice\Publication\NoticePublicationGrant;

$grant = NoticePublicationGrant::create(
    'https://feed.example/notices',
    true,
    ['topic', 'type', 'occurred_at', 'subject', 'recipients', 'author', 'command', 'event', 'action'],
    ['app/label']
);
```

`create(topic, private, fields: [], metadata: [])` selects only stored fields. The mandatory wire shape is
`{"version":1,"scope":"...","producer":"...","id":"..."}`. Optional top-level fields are `topic`, `type`,
`occurred_at`, `recorded_at`; optional `relationships` fields are `subject`, `recipients`, `author`, `command`,
`event`, `action`. Selected metadata keys appear under `metadata`. Unselected fields/containers are absent,
not fabricated nulls. Selected unknown references/occurrence remain null; unknown recipients (null) remain distinct
from a known broadcast (empty list). Producer, author, subject, intended recipients and causes never collapse into
one identity or become a confirmed audience. The transport topic is independently authorized, not automatically
copied from the stored topic. This JSON is not an MCP envelope, wire transcript or full Task state.

Consumers own safe source metadata, redaction, topic vocabulary, hub/browser authentication and authorization.
Structural validation cannot universally detect secrets. Even a private update cannot establish every subscriber's
current entitlement, prevent a concurrent permission change or retract already delivered bytes. Topic names and
identity/causation metadata are themselves protected disclosure. No automatic target/Resource/file/network read occurs.

## Finite limits and selection

All limits reject, never truncate or silently publish incomplete data:

| Boundary | Limit |
|---|---|
| Transport topic | At most 256 ASCII bytes; `https://` plus DNS-style labels of 1–63 alphanumeric/hyphen bytes (alphanumeric ends), `/`, and path characters `A–Z a–z 0–9 . _ ~ / -` |
| Deliberate topic subset exclusions | No userinfo, port, query, fragment, percent encoding, Unicode host/path, wildcards or URI templates; no DNS/network lookup or reachability promise |
| Field list | Unique list of at most the 10 exact `NoticePublicationGrant::FIELDS` names; no arbitrary replacement values |
| Metadata list | At most 16 unique existing namespaced keys under `NoticeMetadata`'s existing 64-byte/key constraints; missing selected keys reject |
| Reference occurrences | At most 32: mandatory scope, producer and ID count 3; selected topic/type each count 1; each nonnull scalar relationship or recipient entry counts 1 |
| Encoded data | At most 4,096 bytes of the actual `json_encode(..., JSON_THROW_ON_ERROR)` string, including default Unicode/slash/quote/backslash escaping and all keys/containers |
| Sends | Zero or one publisher invocation per attempt, never a multi-topic partial fan-out |

Retained notices keep their own existing independent bounds. A valid retained notice can be too large for broadcast;
select fewer permitted fields or report `invalid`, never silently trim it. Success is the chosen publisher returning,
not a hub/browser receipt. The existing adapters do not supply a Mercure replay ID from notice identity; consumers
must not infer replay or exactly-once delivery.

## Safe outcomes and private diagnostics

`NoticePublicationStatus` is a backed enum with fixed safe values:

| Value | Meaning |
|---|---|
| `published` | Exactly one selected publisher returned normally; no receipt/processing acknowledgment |
| `not_available` | Shared GetNotice returned null (unknown, concealed or removed are indistinguishable) |
| `denied` | Current independent policy returned null |
| `unsupported` | The required public/private capability was not supplied |
| `invalid` | Grant/mapping validation raised DomainException, including unavailable fields or exceeded bounds |
| `failed` | Shared read, unexpected policy/composition, encoding or publisher failed; external outcome may be uncertain |

No status contains exception text, notice bodies, credentials or diagnostic data. Invalid mapping and unexpected
failures invoke consumer `NoticePublicationDiagnostics::record(Throwable)`; ordinary denied/unavailable/unsupported
outcomes do not. The sink receives **private** exceptions, including existing Mercure `SocketException` messages
and causes that may contain credentials. It must redact before logging/reporting; never serialize that Throwable
to clients. Sink failure is contained and leaves the original `invalid`/`failed` status, never success or rollback.
Status alone intentionally does not expose private failure stage/details; use redaction-aware diagnostics internally.

Private selection calls only `pushPrivate()`. Missing private support, denial, invalid mapping and hub failure
never try `push()`. Public selection likewise requires its explicit capability; no automatic adapter activation.

## Repeats, uncertainty, recovery and optionality

There is no delivery ledger, guaranteed eventual publication or automatic retry. Explicit repeated `publish()` calls
reconstruct and reauthorize again and can duplicate sends. Identical-content recording retries deduplicate storage
but deliberately repeat this optional attempt; they do not redispatch `NoticeRecorded`. Response loss, a crash after
commit/before send, or a publisher throwing after an external effect can leave delivery uncertain. If removed data
was deliberately re-recorded under the same identity, later publication selects the currently retained incarnation,
not a lease on the earlier version. Consumers own scheduling, idempotent processing and retry decisions.

Use shared `SummarizeNotices`, `ListNotices`, `GetNotice` and `RecoverNotices` Queries for initial/history/reconnect
reads. They independently reauthorize on each call. Recovery preserves existing complete/incomplete/uncertain and
continuation-restart semantics under changed grants, removal or lost history. Neither an empty page, a successful
send nor hub replay establishes complete catch-up, client receipt or recovery of never-recorded application changes.
Consumers own current-data reload, presentation and polling cadence.

Ordinary recording, shared Queries, `notices.recover` polling and guarded MCP HTTP need no publication object,
policy, publisher or hub. Omitting this capability resolves none of them. `symfony/mercure` remains an optional
suggested/dev dependency, never a production requirement for the shared feed. MCP live support is a separate
consumer of retained notices, not a dependency of this output.

## Compatibility and evidence

The six new public Application declarations are additive and provisional until tagged: `NoticePublication`,
`RecordNoticeAndPublish`, `NoticePublicationGrant` (named factory), `NoticePublicationStatus` (native enum), and
consumer-implementable `NoticePublicationPolicy` / `NoticePublicationDiagnostics`. Their consumer operations are
classified in `compatibility/manifest.json`; no released signature, publisher/adapter behavior, CommandBus/QueryBus
contract, schema, dependency floor or existing suppression changes. No development-snapshot migration is added.

Direct Unit tests cover selection, current decisions, safe failures and exact/over limits including escaped Unicode.
`NoticePublicationJourneyTest` uses real SQLite/MySQL/PostgreSQL recording, inspection/recovery/removal and both
actual Common Mercure adapters with a controlled `HubInterface`. It proves committed visibility before policy/hub
I/O, repeated/failed publication with retained recovery, and Mercure-free guarded polling. This is package translation
and persistence evidence, not deployed hub/browser delivery, consumer audience-policy correctness or Agent OS/Pi
qualification. No deployed receipt is required. See TASK-00165's Work receipt for exact commands, counts and warnings.
