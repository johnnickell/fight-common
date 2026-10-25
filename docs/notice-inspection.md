# Authorized retained-notice inspection

This opt-in read path supplies summary → live history pages → independently selected detail through
`QueryBus::fetch()`. It reads the [dedicated safe notice storage](safe-notices.md) directly on SQLite, MySQL and
PostgreSQL. There is no Event Store, asynchronous projection, browser UI, automatic Resource/network read, MCP
Tool, stream or Mercure dependency. Reading sends no Command, business mutation, domain event or receipt.

## Composition and current admission

Register these three handlers on the ordinary Query router:

```php
$reader = new DbalNoticeReader($connection);
$router->registerHandlers([
    SummarizeNotices::class => new SummarizeNoticesHandler($trustedAdmission, $reader),
    ListNotices::class => new ListNoticesHandler($trustedAdmission, $reader),
    GetNotice::class => new GetNoticeHandler($trustedAdmission, $reader)
]);
$selection = NoticeSelection::create(['project-a'], ['topic' => 'catalog/tools']);
$summary = $queryBus->fetch(new SummarizeNotices($selection));
$page = $queryBus->fetch(new ListNotices($selection, 1, 32, ['recorded_at' => 'DESC']));
$detail = $queryBus->fetch(new GetNotice('project-a', 'catalog', 'change-42'));
```

Imports beneath `Fight\Common\` are:

- `Adapter\Notice\Dbal\DbalNoticeReader`;
- `Domain\Notice\NoticeSelection` and `Domain\Notice\Query\{SummarizeNotices,ListNotices,GetNotice}`;
- `Application\Notice\QueryHandler\{SummarizeNoticesHandler,ListNoticesHandler,GetNoticeHandler}`;
- `Application\Notice\Service\{NoticeReadAdmission,NoticeAccess,NoticeReader}`;
- results `Application\Notice\Data\{NoticeSummary,NoticePage,NoticeView}` and
  `Application\Notice\Exception\NoticeReadFailed`.

`$router` can be `Adapter\Messaging\Query\Routing\InMemoryQueryRouter`, with
`Adapter\Messaging\Query\RoutingQueryBus`. No automatic registration or principal lookup is implied. Install the
existing schema exclusively before use. Supply a dedicated idle DBAL connection with committed isolation;
active caller transactions and DBAL `READ_UNCOMMITTED` fail without changing the transaction or connection policy.
Consumers configure connection/statement timeouts and must not bypass isolation through raw SQL.

Implement `NoticeReadAdmission::currentAccess(NoticeSelection): NoticeAccess` from **trusted current invocation
context**. Each handler calls it afresh, including every page, direct lookup and repeat. `NoticeAccess::create(scopes,
filters)` supplies at most 16 scope grants, optionally narrowed by the same conjunctive exact filters below.
Caller selection and trusted restrictions are **intersected**, never substituted for one another. Empty grants deny
all. Query payloads carry no access objects or credentials. The input selection is a request, not authority.
Recording/removal permission, recipient membership, an Agent ID, topic name, a human's rights, `SUPER_ADMIN` and a
previous successful read grant nothing. Human and Agent policy remain independent consumer decisions.

A grant explicitly permits the **entire safe metadata and every supplied relationship** of matching notices. There
is no implicit field-level redaction: exclude notices whose complete content cannot be disclosed. The finite scope
and optional exact-filter grant model is not a universal policy language; if a consumer cannot express a safe grant,
it must deny or partition its safe-notice scopes, not broaden access. Common supplies no allow-all admission or
secret detector. Consumers still own capture/redaction, trusted identity, vocabulary and actual permission policy.
The immutable inward selection/access/read contracts can be reused by subsequent recovery work without depending
on it. No continuation, access-fingerprint or recovery promise is implemented here.

## Selection, order and finite bounds

`NoticeSelection::create(scopes, filters)` validates before dispatch. `fromArray()` accepts exactly `scopes` and
`filters`; all three Queries require their exact serialized key sets. Native PHP parameter type errors remain distinct
from value validation (`DomainException`). Lists must be lists, not maps. Unknown fields, nested values, null filter
values, unsupported order fields and arbitrary SQL fragments reject.

| Input | Contract |
| --- | --- |
| Scopes | List of 0–16 references; deduplicated and sorted bytewise; empty selects nothing |
| Exact filters | `id`, `producer`, `topic`, `type`, `subject`, `recipient`, `author`, `command`, `event`, `action` |
| Reference values | Same exact 1–96 ASCII-byte grammar as `Notice::reference()`, case-sensitive, no normalization |
| Time filters | `recorded_from`, `recorded_until`, `occurred_from`, `occurred_until` |
| Time meaning | UTC instants, years 0001–9999, zero through six fractional digits; normalized to six for comparison; `from` inclusive, `until` exclusive |
| Time ranges | Reversed rejects; equal endpoints select an empty interval; unknown occurrence fails any occurrence-time predicate |
| Combination | Every supplied filter must match; at most the 14 named filters; a recipient filter means membership in named recipients only |
| Page | `Pagination::strict()`, default page 1 and per-page 32; positive representable integers, per-page at most 32 and offset at most 1,000,000 |
| Ordering | Only `recorded_at` with ASC/DESC (case-normalized), default DESC; equal recording times use internal position in the same direction |
| Storage scan | Keyset batches of at most 100 rows; only requested page rows and finite summary dimensions are retained in PHP |
| Summary dimensions | At most 128 distinct topics and 128 distinct types; the 129th fails the **whole** summary, never truncates |
| Page encoding | At most 32 complete notices, each at most 6,144 encoded bytes, plus bounded recording/page fields: below `NoticePage::MAX_BYTES` (262,144 bytes) under ordinary `json_encode()` |

Ordering is deterministic for unchanged retained data; the private tie-breaker is **not returned**, nor are namespace,
high water, history-loss counters, comparison evidence, hidden-row counts or global positions. Ordering by recording
time is not causal order, occurrence order or a recovery continuation. All SQL identifiers/directions are fixed or
selected from validated constants. Scope and identity/producer constraints use bound parameters before decoding;
other caller and trusted predicates are applied to one reconstructed notice before any count or returned projection.
Reference/recipient representation has one row per notice: no relationship join can multiply matching counts.

Reads scan all admitted-scope candidates to preserve full-selection meaning without a JSON-provider dependency or
unbounded PHP materialization. Time/relationship predicates are evaluated in bounded PHP batches, not accelerated
by new provider-specific JSON indexes. The existing unique identity and scope/position indexes remain unchanged.
Summary/page work is linear in candidates (and database ordering can sort); these are bounded-memory, **not bounded
latency/scan-count** operations. Large histories require consumer operational limits/timeouts. A timeout/error fails,
never produces partial statistics or a false successful empty page. No hidden aggregate endpoint or competing
presentation SQL is needed.

## Results and safe absence/failure

`NoticeView` exposes immutable `notice()` and canonical `recordedAt()`. Its `toArray()` is the complete admitted
version-one Notice representation plus `recorded_at`, **without position**. The original `occurred_at` retains supplied
precision or null unknown. Subject Agent, producer, author, intended recipients and each causation Command/event/action
stay distinct. Recipients null means unknown; `[]` means broadcast/no named recipients. Neither case manufactures a
subscriber audience. Named recipients prove neither subscription, delivery, receipt nor processing. No protected
message/tool/output body, full MCP Task state, transcript or authority is inferred.

`NoticePage::notices()` returns at most the requested page size. `toArray()` contains `notices`, full matching `total`,
`page`, `per_page`. Constructor validation and detached array elements preserve finite rows even when the caller later
changes referenced PHP array elements. Page totals count notices once, including multi-recipient/multi-causation
notices; identical retained recording retries remain one notice. A page beyond the end is empty but keeps its
currently encountered full matching total.

`NoticeSummary::toArray()` contains:

- `total`: all currently admitted matching notices, not a current-page count;
- `topics`, `types`: complete bytewise-sorted lists of `{value, count}` (lists avoid numeric-reference JSON-key ambiguity);
- `audience`: counts of notices with `named`, `broadcast`, `unknown` recipients, not recipient counts;
- `unknown`: notice counts for missing `subject`, `author`, `causation` (all three causes null) and `occurrence`;
- `first_recorded_at`, `last_recorded_at`, `first_occurred_at`, `last_occurred_at`: canonical UTC extrema, null when
  there is no corresponding evidence. Occurrence extrema compare instants without altering the returned Notice's precision.

`GetNotice(scope, producer, id)` does not require a previous list. Unknown, guessed, cross-scope, currently denied,
revoked and disappeared identities all return **the same null**, without diagnostic or expiry reason. Denied/empty
summaries and pages have zero counts and no concealed metadata. No global summary is computed and then filtered.

Database/hydration/integrity/isolation failures, admission exceptions and exceeded summary dimensions become fixed
`NoticeReadFailed`: **“Retained notices could not be read completely.”** They never become empty success. Causes and
traces are private diagnostics; adapters must not serialize them. Ordinary denial is expressed with empty/narrowed
`NoticeAccess`, not a private policy exception. Results are public-safe only to the extent of the consumer's actual
metadata grants/redaction; structural validation cannot identify a secret disguised as a safe label.

## Live consistency, not recovery

Each request reads currently retained data under fresh admission. One summary or page uses several independent
SELECTs on an idle connection, starts no transaction/lock and promises no coherent frozen snapshot across batches or
between summary, pages and detail. Concurrent recording/deletion can change counts and rows; later pages can repeat
or skip rows relative to previous requests, and detail may disappear or be revoked. The total describes matches
encountered during that live traversal, not a linearizable instant. Access is invocation-local, not a retained grant
or a field cache. Already returned bytes are not retracted.

No empty page/absent detail proves expiry, receipt, complete capture, complete catch-up or successful application
processing. Successfully recorded retained notices are all that can be inspected; changes that never recorded a notice
are not inferred. There is no frozen search, retention lease, browser page reset policy, acknowledgment ledger,
mutation Tool or automatic fetch of referenced resources. TASK-00161 separately owns polling/continuation and
qualified history-loss recovery; inspection neither exposes its private storage evidence nor blocks on that work.

## Compatibility and public surface

These **15 declarations** are additive opt-in public APIs in `compatibility/manifest.json`:

- Domain (four): final immutable `NoticeSelection`, `Query\SummarizeNotices`, `Query\ListNotices`, `Query\GetNotice`;
- Application (ten): final `Service\NoticeAccess`, the two consumer-implementable interfaces
  `Service\NoticeReadAdmission`/`NoticeReader`, three final Query handlers, three final immutable Data classes and
  final `Exception\NoticeReadFailed`;
- Adapter (one): final `Dbal\DbalNoticeReader`.

Public classes promise their documented construction/factories and callable methods, not subclass extension.
Interfaces promise implementation, not construction. No released declaration, existing schema/index, dependency
constraint, runtime/framework integration, CQRS message/bus/event or write-store API changes. Notice APIs remain
provisional until first tagged; there are no development compatibility aliases, migrations or historical codecs.
The earlier 24 recording/removal declarations remain intact (39 Notice declarations in total). This package proof
does not qualify a deployed consumer, SUPER_ADMIN interface, browser, MCP client or Mercure hub.

## Verification

Direct tests cover selection/query/data/admission/handler/reader behavior and exact owned production statements.
`NoticeInspectionJourneyTest` uses the real recording Command (including identical retries), routed Queries and
actual SQLite/MySQL/PostgreSQL. It proves 105 matching notices across four pages, independent producers/scopes,
full-selection totals with multi-recipient/causation references, time ties/precision, every required filter, broadcast
and unknown cases, direct detail before listing, revoked/forged/denied access, concealed sentinel metadata, no
read-side writes/events, independent-connection live changes/disappearance and explicit storage/dimension failure.
Owned deletion fixtures demonstrate disappearance, not removal eligibility or polling recovery. Exact/over dimension,
page/offset/reference/time bounds and complete large 32-notice responses are checked without packaging/process tests.
TASK-00160 owns retained build/nonvisual Before/After receipts and its separate independent Review/QA checkpoint.
