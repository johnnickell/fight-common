# Inspect current MCP subscriber observations

`Domain\Mcp\Subscription\Query\SummarizeSubscribers` and `ListSubscribers` run through ordinary
`QueryBus::fetch()`. They inspect the **existing** [live MCP tracking](mcp-subscriptions.md) records, not a second
lifecycle store, a dashboard, MCP Tool, saved subscription or notice-delivery ledger. No Prompt capability is needed.
Queries perform no heartbeat, cleanup, transaction, Command, event or notice/content read.

## Composition and independent current permission

Import selection/Queries from `Fight\Common\Domain\Mcp\Subscription` / `Query`, handlers from
`Fight\Common\Application\Mcp\Subscription\QueryHandler`, permission/reader contracts from its `Service`
namespace, results from `Data`, and `DbalSubscriberReader` from `Fight\Common\Adapter\Mcp\Subscription`.

```php
$reader = new DbalSubscriberReader($inspectionConnection, 'production-eu-registration-db');
foreach ([
    new SummarizeSubscribersHandler($inspectionAdmission, $reader),
    new ListSubscribersHandler($inspectionAdmission, $reader)
] as $handler) {
    $router->registerHandler($handler::queryRegistration(), $handler);
}
$queries = new RoutingQueryBus($router);
$selection = SubscriberSelection::create(['project-a']);
$summary = $queries->fetch(new SummarizeSubscribers($selection));
$page = $queries->fetch(new ListSubscribers($selection, page: 1, perPage: 8));
```

The example uses the existing `Adapter\Messaging\Query\Routing\InMemoryQueryRouter` and
`Adapter\Messaging\Query\RoutingQueryBus`; any conforming QueryBus can route these handlers. Schema installation
belongs to the existing lifecycle owner, not the reader. Use an **idle connection with committed isolation** on
SQLite, MySQL or PostgreSQL. Active caller transactions and READ_UNCOMMITTED reject without committing or rolling
back caller work. No reader lock or transaction spans statements or requests.

Consumers implement **separate** `SubscriberReadAdmission::currentAccess(SubscriberSelection): SubscriberAccess`.
It resolves trusted identity and current inspection policy on **every** summary/page, including retries and empty
pages. Neither listen/notice permission, a requested subscriber ID, a protocol request ID, previous page, Agent/human
relationship nor a `SUPER_ADMIN` label authorizes inspection. Common supplies no role names or principal model.

`SubscriberAccess::create($scopes, $registrations = null)` grants disclosure of **all** metadata of matching
registrations: subscriber identity, every requested filter (including rejected Resource references), accepted subset,
notice selection with its topics/relationships, and observed timestamps/state. Granting a scope is deliberately a
complete-metadata inspection grant, not merely permission to listen to that scope. If any field/reference cannot
be disclosed, the consumer must exclude that entire registration, using current exact registration restrictions
or denying the scope. Do not construct this value from Query arguments. Exact restrictions contain 0–4,096 random
32-lowercase-hex registration IDs; null permits all registrations in the granted scopes, while `[]` denies all.
No partial row, concealed-row count or global freshness statistic is returned.

A registration's **entire nonempty opening notice scope set** must fit both the caller's selected scopes and the
trusted scopes. For example a stream covering A+B is not disclosed by a grant/selection for A alone. Empty scope
sets have no positive scope membership and are excluded; they are not assigned an invented deployment/tenant scope.
Both admission and caller predicates apply before counts, identity distinctness, extrema and page inclusion.
Denied/unknown selections return the same zero summary/empty page. A wholly denied or empty selection performs no
storage probe and does not reveal storage availability. Ordinary admitted empty results still validate storage;
missing/corrupt schema or failed SQL must not become empty success.

## Selection and finite limits

`SubscriberSelection::create($scopes, $filters = [])` accepts 0–16 exact notice-scope references, canonicalizes
unique scopes bytewise, and validates an explicit conjunctive filter allowlist:

| Field | Meaning / bound |
| --- | --- |
| `registration_id` | One exact random technical ID: 32 lowercase hex characters, not JSON-RPC correlation |
| `subscriber` | One exact trusted opening subscriber reference |
| `state` | `recently_observed`, `disconnected` or `stale_uncertain` |
| `topic` | Exact explicitly selected opening notice topic; absent/unrestricted topics do not match an exact filter |
| `requested_filter`, `accepted_filter` | Exact top-level notification field **presence**, not truthiness; false/empty requested fields remain present |
| `requested_resource`, `accepted_resource` | Exact member of `resourceSubscriptions`; UTF-8, 1–2,048 bytes, no raw ASCII whitespace/control bytes |
| `opened_from`, `opened_until` | Half-open opening-time bounds, integer Unix seconds 1–2,147,483,647; reversed bounds reject |

References (subscriber, topic, filter name, scope) retain Notice's 1–96-byte ASCII reference grammar:
`[A-Za-z0-9][A-Za-z0-9._:/-]*`. Resource selection does not resolve or normalize a URI or grant Resource access.
Unknown fields, malformed shapes/types and unsafe order names/directions reject before database work. Serialized
Queries use exact snake_case fields; factories round-trip `selection`, and pages also `page`, `per_page`, `orderings`.

`ListSubscribers` uses `Pagination::strict()` with defaults **page 1 / per_page 16**, page size **1–16**, maximum offset
**1,000,000**, representable integer multiplication, and only **`opened_at` ASC/DESC**. Default is DESC. Immutable
opening seconds then the complete bytewise technical ID in the same direction break ties on all three databases.
No SQL ordering or field expression comes from unvalidated input. Pages beyond the current selection are empty but
still carry the full encountered matching total.

The adapter scans at most **32 payloads per batch**, each at most **65,536 stored encoded bytes**, without
materializing the registration corpus. It applies grants/predicates before retaining at most one page. A hard
**8,192 examined rows per call**, including concealed rows, bounds concurrent churn; exhaustion is failure, never a
truncated successful summary/page. Ordinary lifecycle storage caps retained registrations at 4,096. Summary memory
holds at most 8,192 bounded distinct identity keys, not complete rows, filters or reference joins. Scans are linear
in retained tracking size; consumers own database/worker timeouts and request admission. There is no new read index.

Encoded page projections are at most **262,144 bytes**, including scope/total/page metadata and all complete selected
rows with actual JSON escaping. An oversized page fails rather than dropping rows or shortening filters. Consumers
own any outer HTTP/MCP envelope budget; these Queries introduce neither transport. Summaries have three fixed state
buckets and bounded scalar fields, not an unbounded identity/topic/Resource dimension catalog.

## Results, count units and freshness

`SubscriberSummary::toArray()` returns:

- `subscriptions`: each encountered matching technical registration once, regardless of filter/reference counts.
- `subscriber_ids`: distinct exact trusted subscriber strings across the full selection. One subscriber with two
  simultaneous streams contributes two subscriptions and one subscriber identity. Identical strings across scopes
  are the same count unit; consumers needing scope-qualified identities must supply those trusted strings at opening.
- `states`: registration counts for the three fixed states, whose sum is `subscriptions`.
- `oldest_observed_at` / `latest_observed_at`: extrema of **permitted matches only**, or null for no matches.
- `scope`: configured `storage_scope`, invocation `queried_at`, `coverage: authorized_retained_registrations`, and
  `consistency: live`. The consumer-supplied label describes exactly the shared database namespace observed, not
  automatically all processes/deployments/regions. Local-only tracking cannot be labeled installation-wide.

`SubscriberPage` exposes typed `subscribers()` and `toArray()` with `subscribers`, full-selection `total`, `page`,
`per_page`, and the same scope metadata. Each immutable `SubscriberView` contains `registration_id`, trusted
`subscriber`, complete opening `notice_selection`, immutable StrictJson `requested` / `accepted` maps, integer
`opened_at`, `observed_at`, `expires_at`, nullable `closed_at` and derived `state`. PHP's stored empty-map `[]`
representation is projected as a JSON object `{}`. Nonempty nested JSON object/list distinctions remain intact.
Generic optional-family metadata is preserved without importing or invoking Prompt providers. Presence of a requested
field does not mean it was accepted; the accepted snapshot is not continuing permission. No owner secret, credential,
JSON-RPC ID, protected body, delivered-notice list or hidden global count is selected.

Lifecycle evidence is interpreted using one clock captured at invocation (default `time()`, optional trusted
`Closure(): int` for adapter tests/explicit composition):

| Evidence | State |
| --- | --- |
| `observed_at <= queried_at`, no recorded closure, `expires_at > queried_at` | `recently_observed` — **not proof of current connectivity** |
| Recorded closure at/before the clock | `disconnected` — observed technical closure, not client receipt |
| Open with expiry at/before the clock, or an observation in the future | `stale_uncertain` — no fabricated clean disconnect |

The existing owner-bound five-second freshness, non-resurrection, cleanup and synchronized/no-rollback clock
assumptions remain unchanged. Permission, receipt, processing and current client connectivity are never inferred.
The schema requires a trusted identity at opening; the reader never substitutes a protocol ID or infers absent
identity/context. Malformed required evidence produces explicit unavailable inspection, not fabricated unknown
identity, active state or a confidently disconnected record. Null closure means no observed closure, not proof of life.

Reads do **not** renew freshness or clean stale rows. Bounded explicit cleanup stays with `DbalSubscriptionStore`;
removed/cleaned rows are simply absent from this retained observation window. Absence proves neither no prior
subscription nor that a client received/processed anything. No archive/retention lease or per-notice audience exists.

## Live consistency and failures

Full-selection means all admitted matches encountered in the bounded scan, not only the returned page and not a
concealed installation total. With unchanged storage, summary counts and page totals agree. Multiple SQL statements
and later Queries are **live**, not one snapshot or remembered browser session: closure, expiry, cleanup, opening or
changed permission can change states/totals; pages may repeat or skip rows between calls. Opening time/ID ordering
is deterministic, not causal/commit order or recovery continuity. Concurrent cleanup may remove earlier rows; new
openings may fall before an already examined boundary. No frozen browsing or page-reset/navigation policy is supplied.

`Application\Mcp\Subscription\Exception\SubscriberReadFailed` has fixed public text
`Subscriber inspection unavailable.` and a private diagnostic cause. Admission failures, invalid/corrupt persisted
metadata, unsupported schema, idle/isolation violations, SQL failure, scan exhaustion and encoded response overflow
are failures, never successful empty/partial data. Consumers must redact diagnostic causes and choose their transport
failure response. No data is returned before the complete result is available.

## Qualification and nonvisual evidence

Direct Unit tests cover selections/Query serialization, strict pagination, permission constraints, safe typed
projection, observed states, distinct counts, multi-batch pages, failure/result/scan bounds and exact read-only row
preservation. Real package SQLite/MySQL/PostgreSQL journeys open actual guarded HTTP streams, reuse JSON-RPC IDs,
inspect from an independent routed-Query process, distinguish multiple streams/one identity, preserve requested vs
accepted Resource and optional-family fields, revoke inspection access, restrict exact registrations, conceal another
scope, and observe concurrent clean disconnect. Actual abrupt worker SIGKILL leaves an unclosed row; later independent
Queries report stale/uncertain, unchanged by late failed heartbeat or the inspection itself, then empty after bounded
owner cleanup. Storage failure is separate from empty authorized results. Existing cross-process lifecycle lock and
failure journeys remain in the complete product gate.

These nonvisual Before/After lifecycle/Query transcripts are appropriate for a PHP library, not screenshots of a
nonexistent dashboard. They identify the backend, worker PIDs, HTTP frames/timestamps and before/after Query results;
task-owned ignored runs retain provenance, complete logs and tested-content mapping. This is package qualification,
not consumer authorization-policy correctness, a deployed SUPER_ADMIN/Agent interface or client receipt proof.

## Compatibility

Against tagged **v1.4.0**, the manifest classifies **fourteen additive public inspection declarations**:
`SubscriberSelection`, `SummarizeSubscribers`, `ListSubscribers`; `SubscriberView`, `SubscriberReadScope`,
`SubscriberSummary`, `SubscriberPage`; `SubscriberAccess`, `SubscriberReadAdmission`, `SubscriberReader`;
`SubscriberReadFailed`; both Query handlers; and `DbalSubscriberReader`. All classes are final, callable and
constructible through their public constructor or named factories, not extensible/implementable. Both interfaces
are callable/consumer-implementable, not constructible or class-inheritance promises. No existing released API,
schema, index, dependency floor, lifecycle behavior, default MCP composition or protocol flag changes. The current
unreleased technical schema is read directly, with no development migration, alias, duplicate store or projection.
No release certification, publication or deployment follows from product qualification.
