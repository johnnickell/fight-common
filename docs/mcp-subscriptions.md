# Live MCP Tool and Resource changes

Explicit `Application\Mcp\Subscription\McpSubscriptions` composition enables MCP `2026-07-28`
[`subscriptions/listen`](https://modelcontextprotocol.io/specification/2026-07-28/basic/patterns/subscriptions)
on the **same guarded POST endpoint**. The default remains direct JSON, with separately opt-in Tool progress.
No GET subscription endpoint, `resources/subscribe`, Prompt/Task notification, automatic Resource read, broker,
Mercure dependency, subscriber-inspection Query or saved resumable subscription is introduced.

## Compose the complete path

1. Install `Adapter\Notice\Dbal\DbalNoticeSchema` and
   `Adapter\Mcp\Subscription\DbalSubscriptionSchema` on an exclusive **idle deployment connection**.
   These are separate reserved namespaces. Installation is not a per-request migration or a live-stream transaction.
   Partial installations need operator recovery; MySQL DDL is not atomic.
2. Route `Domain\Notice\Query\RecoverNotices` to `Application\Notice\QueryHandler\RecoverNoticesHandler`, using
   current consumer `NoticeReadAdmission`, `NoticeRecovery`, a dedicated `DbalNoticeStore` connection and a shared
   dedicated `NoticeContinuationProtector` key set. See [retained recovery](notice-recovery.md).
3. Supply `McpSubscriptionAdmission` and a trusted request-scoped `NoticeSelection`. Neither comes from the listen
   arguments. `subscriber()` returns a bounded trusted reference, not a Bearer credential. `allows()` must freshly
   permit the **entire** signal, including topic, Resource, identities and all source notice metadata. The null-notice
   opening check must also conceal denied/unknown Resource identities and catalog availability. Catalog activity
   needs its own grant: permission to list one visible Tool is not permission to learn that a hidden catalog changed.
4. Supply an idle dedicated connection to `DbalSubscriptionStore`; never share an active caller transaction or hold
   any transaction across a stream or network wait. Consumers own connection pools, SQL timeouts and cleanup scheduling.
5. Pass `McpSubscriptions` as the optional **third registry constructor argument**. For example, with existing
   `$queries`, `$admission`, `$selection`, `$capabilities` and `$serverInfo`:

   ```php
   $tracking = new DbalSubscriptionStore($trackingConnection);
   $subscriptions = new McpSubscriptions(
       $queries,
       $tracking,
       $admission,
       $selection,
       tools: true,
       resources: true
   );
   $registry = new McpCapabilityRegistry($serverInfo, $capabilities, $subscriptions);
   ```

   Import these names from `Fight\Common\Adapter\Mcp\Subscription` and
   `Fight\Common\Application\Mcp\Subscription`, respectively; registry is in `Application\Mcp`.
   The matching serving capabilities **must exist**. Set `tools: false` or `resources: false` when absent/disabled;
   at least one must be enabled. Existing serving capabilities continue to declare their ordinary false flags;
   the registry derives `tools.listChanged`, `resources.listChanged` and `resources.subscribe` from this actual
   composition. Arbitrary true declarations, contradictory same-family metadata, unsupported Prompt flags and
   consumer ownership of `subscriptions/listen` when the live composition is enabled still reject. No global registry validation is disabled.
6. Construct the existing `Adapter\Http\Mcp\McpRequestHandler` and response factory/emitter normally. The registry's
   exact composition is used by that handler; no additional progressive flag is required for subscriptions.
   The semantic responder alone is **not** a subscription transport. Keep authentication outside the handler;
   Origin, body/depth bounds, mirror validation, protocol version, POST negotiation and invocation admission run
   before stream selection. Invalid filters produce ordinary safe invalid-parameters JSON, not an admitted stream.

This is opt-in request-scoped composition. Do not reuse a subscriber/admission/selection object between unrelated
callers. No client-declared capabilities, recipient ID, human privilege label or opening handshake is authority.

## Wire contract and mapping

Send `notifications` as an object in `subscriptions/listen` parameters. All fields are optional:

| Requested field | Accepted when | Safe recorded type | Emitted method |
| --- | --- | --- | --- |
| `toolsListChanged: true` | Tools enabled and currently permitted | `mcp.tools.list_changed` | `notifications/tools/list_changed` |
| `resourcesListChanged: true` | Resources enabled and currently permitted | `mcp.resources.list_changed` | `notifications/resources/list_changed` |
| `resourceSubscriptions: [uri, …]` | Resources enabled and each exact URI currently permitted | `mcp.resources.updated` with `mcp/uri` metadata | `notifications/resources/updated` |

These three explicit notice-type conventions select **safe changes**, not arbitrary wire payloads. Consumers still
own their notice topics, recording permission and vocabulary. No fields except the exact matched Resource URI are
copied to the wire: no notice body, topic, ID, producer, recipient, cause, metadata extension or server exception.
The admission receives the whole safe notice for independent current disclosure permission. Unknown types and
unrequested families are ignored, not mapped from their names. Notice source authorization and signal permission
are separate checks; neither is replaced by the other.

`notifications/subscriptions/acknowledged` is always the first frame of a successfully opened stream. Its
`notifications` object is only the supported/currently permitted requested subset. `false`, omitted fields and
empty arrays request nothing; duplicates are collapsed in first-occurrence order. The requested tracking snapshot
preserves false/empty distinctions (with duplicate URI canonicalization). Prompts are structurally recognized but
never accepted in this slice. Unknown filter names, wrong types, extra parameters and oversized references reject.
Empty, unsupported and denied requests may acknowledge `{}` identically; this does not disclose why a case was omitted.
Permission changing during opening prevents acknowledgment rather than emitting an obsolete accepted set.

Resource references are absolute RFC 3986 URIs, at most 2,048 UTF-8 bytes with no raw ASCII whitespace/control bytes;
at most 16 references may be submitted, counting duplicates before collapse. Matching is **exact byte equality**,
without normalization, percent decoding, wildcard/template expansion, path containment or network inference. No target
is opened. Retained notice scalar metadata currently limits the mapped `mcp/uri` value to 128 bytes; accepting a
longer listen reference does not enlarge that recording contract. Consumers must use their existing supported safe
notice/Resource vocabulary, not truncate or alias a longer URI.

Acknowledgment, every notification and graceful completion carry the originating JSON-RPC ID **with its exact
integer/string type** in `params._meta["io.modelcontextprotocol/subscriptionId"]` or completion `result._meta`.
The ID is neither a unique storage key nor authority. Each execution gets separate cryptographically random
registration and owner identities; two callers using `7` do not share state, scope or frames.

The finite server lifetime ends with the original JSON-RPC response:

```json
{"jsonrpc":"2.0","id":7,"result":{"resultType":"complete","_meta":{"io.modelcontextprotocol/subscriptionId":7}}}
```

There is no event name, SSE ID, terminal sentinel or invented MCP keepalive notification. Idle keepalives are
transport comments (`: keepalive`). Server completion means only graceful technical closure, not receipt/processing,
complete application capture or successful recovery. Disconnect/cancellation suppresses completion. Source,
tracking, permission-provider or framing failure closes without a successful completion; unexpected private
failures go only to the consumer redaction-aware `McpDiagnostics`. A broken diagnostic sink emits nothing.

## Shared source, start boundary and recovery

`McpSubscriptions` fetches the shared authorized `RecoverNotices` Query through `QueryBus::fetch()`, never HTTP-layer
SQL or an in-process event listener. The optional `startAtCurrent: true` Query argument establishes the coherent
committed high-water boundary, returns **no historical notices**, and excludes earlier history loss from this new
interval. It cannot accompany a continuation. Subsequent ordinary recovery calls reuse the returned confidential
continuation with the same selection, batch/scan sizes, caller, policy epoch, grant snapshot and serving configuration.
`start_at_current` is part of the provisional Query's serialized shape; the ordinary polling Tool remains unchanged
and does not expose this option.

A live stream requires qualified `complete` source assessment: trusted caller, current stable policy epoch and
explicit permission to use the namespace-wide continuity evidence. **Do not grant global evidence merely to enable
streaming**. Partial/unproven compositions report uncertainty and cannot open a healthy follow; use independent
polling/current-data reload instead. The current implementation conservatively ends the stream on any incomplete
batch as well as uncertainty/loss: overload beyond one complete 32-notice/100-scanned-row batch is a recovery
condition, not silent live dropping. It does not advance a public recovery checkpoint or claim omitted output was
sent. This trades availability for a small, honest live boundary.

Independent recording processes become visible through shared storage. Concurrent deletion is assessed by recovery's
coherent window/loss contract; namespace retirement, revoked grants/changed epoch, expired continuation, missing
state or source failure ends delivery. Recording after an exhausted boundary may appear in the next poll; there is
no retained snapshot, permission lease or promise to bridge the interval before opening. A batch contains at most
32 whole safe notices, with at most 100 examined rows and the recovery service's default 65,536 encoded-byte result
budget. Only one bounded batch and one frame are pending. Permission is checked after tracking heartbeat and directly
before each mapped emission, including rows fetched before a previous frame paused the producer. Previously sent
bytes cannot be retracted.

Stream failure **never removes retained notices**. Reconnect creates a new independent registration and acknowledgment,
not replay. Use `notices.recover` or the shared recovery Query with its independent current authorization and qualified
history/access limits to recover retained data. An incomplete/uncertain recovery requires consumer current-data reload;
Common supplies neither a receipt ledger nor exactly-once delivery/processing. See [recovery](notice-recovery.md).

## Technical tracking and operational bounds

Reserved version-one `fight_mcp_subscription_state` serializes short capacity/lifecycle transactions;
`fight_mcp_subscriptions` stores random registration ID/owner, trusted subscriber, trusted notice selection,
requested/accepted filters, opened/observed/expiry/closed timestamps. No credentials, protected bodies, JSON-RPC ID
or per-notice historical audience is saved. SQLite, MySQL/InnoDB/binary collation and PostgreSQL use public DBAL 4.4+
schema APIs. The expiry index supports bounded cleanup. No development-schema migration or historical audience API
is supplied; future inspection must apply separate authorization.

- Default at most **256 fresh observed open registrations** per namespace; `maxActive` accepts 1–256. Total retained
  registrations are capped at **4,096**, including abandoned/closed rows. Capacity checks serialize with writes.
- Timestamps use supplied shared-wall-clock integer seconds in 1–2,147,483,647. The stream renews a **five-second**
  observation lease on source/delivery boundaries. Heartbeats require matching owner, open row, nondecreasing time
  and unexpired freshness. A same-second heartbeat is valid on MySQL even if its affected-row count is zero.
- `close(id, owner, now)` is owner-bound and idempotent; late heartbeats never reopen closed/expired rows. Reconnect
  always gets new identities. Abrupt death without finally leaves `closed_at = 0`; once `expires_at <= now` it is
  **stale/uncertain**, not confidently active or confirmed connected. Recent observation proves neither connectivity
  nor permission, receipt, processing or future liveness. Clock skew/rollback invalidates these assumptions; use
  synchronized clocks and conservative operator interpretation.
- Schedule trusted `cleanup(now, limit)` explicitly with limit **1–256**. It deletes at most that many closed or
  expired rows, serialized with opening/heartbeat/closure. Expired rows cannot revive after deletion. Closed/stale
  rows may disappear on cleanup; there is no historical retention promise. Full retained capacity rejects opening
  until cleanup, rather than accumulating an unbounded audience.
- Any storage exception is terminal for that store instance. Opening failure sends no acknowledgment. Renewal or
  closure failure stops healthy delivery; failed final closure does not send completion. An uncertain failed close
  leaves the last observed row to expire. No live transaction owns or commits caller work; active caller connections
  reject before begin. No implicit retry can disguise uncertain tracking.
- Stream lifetime defaults to **30 seconds**, configurable **1–120**. Wait defaults to **200,000 microseconds**,
  configurable **10,000–1,000,000**. A hard **12,000 work checks** also bounds production even independently of the
  clock. Deadline/cancellation is checked between source calls and queued items. Recovery's default token TTL is
  300 seconds, longer than the maximum stream lifetime; consumers must preserve a sufficient configured TTL.
- Maximum actual JSON/SSE frame defaults to **16,384 bytes**, configurable **256–65,536**, including escaping,
  multibyte request identity and metadata/framing. Over-limit frames fail, never truncate. The transport comment is
  13 bytes. A producer pauses after one frame until the emitter resumes; no unbounded queue or catch-up buffer.

These are cooperative **library work and observed-state bounds**, not preemptible OS I/O or a hard count of TCP
sockets. Consumers must also bound worker/process/socket admission, request/SQL/write timeouts, proxy buffers and
memory. A blocked SAPI write or consumer Query/policy callback cannot be interrupted by PHP's next lifetime check.
A stalled writer can outlive freshness; it must successfully renew before producing another frame when resumed.
Kernel/proxy/client buffering can retain already written bytes and is not permission-cached production.

## Live/native runtime qualification

The existing single-use `McpEventStream` now drives either Tool execution or subscription execution; no adapter
falls back to string-casting a subscription. `McpResponseEmitter` consumes frames through write/flush. Symfony and
Laravel share native `StreamedResponse`; CodeIgniter retains the original body for its native send phase; Yii and
Slim use the shared PSR emitter. Keep output buffering and zlib compression **disabled**, and do not attach
Content-Length/Content-Encoding. Unsupported buffering is rejected before production. Casting/`getContents()` is
explicit buffering, not supported live delivery; do not route subscriptions through a generic buffering responder.

Package journeys use real PHP cli-server processes with buffering/compression disabled, raw TCP clients, independent
recording processes and actual SQLite/MySQL/PostgreSQL state. They observe acknowledgment/updates before closure,
simultaneous scoped reused IDs, fresh revocation, source interruption with polling recovery, native delivery, idle
socket closure and abrupt process death followed by stale/late-owner checks and cleanup. A paused client test is not
a claim of saturated-kernel backpressure or instant disconnect detection. Unit tests separately prove bounded encoded
frames, queued reauthorization/cancellation and source/tracking failure. Existing direct JSON and opt-in Tool-progress
journeys remain in the complete product gate. These are package proofs, not deployed Agent OS/Pi, proxy/server,
client receipt or application processing qualification.

## Compatibility

Compatibility is assessed against **tagged v1.4.0**, not previous unreleased snapshots. The compatibility manifest
classifies six additive public declarations: `McpSubscriptions`, immutable `McpSubscriptionFilter` (named
`fromRequest()` factory), consumer-implementable `McpSubscriptionAdmission` and `McpSubscriptionStore`, and final
`DbalSubscriptionSchema`/`DbalSubscriptionStore`. Public classes are callable/constructible but not extensible;
interfaces are callable/implementable, not class-construction or inheritance promises. `McpSubscriptionExecution`
is internal with no consumer-operation promise. Schema `TABLES` names the two reserved technical tables.
The DBAL schema/store and the third optional registry argument are additive opt-in APIs. Existing handler constructor
arguments and default behavior remain; the response factory/event stream gain an optional execution argument.
`McpEventStream` and subscription execution are internal, not a new consumer PSR buffering contract. Reserved
subscription method ownership and derived enabled flags apply only to this new protocol composition; an existing
custom same-named capability remains usable without the live composition, and arbitrary true subscription flags
were already rejected. Notice Query/recovery APIs were not in v1.4.0 and remain provisional:
the current version-one Query directly includes `start_at_current`, with no obsolete snapshot codec, alias or
migration. Existing released CQRS, Tools, Resources, progress and native integration contracts remain intact.
