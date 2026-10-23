# Authorized execution overviews

`Domain\Execution\Session\Query` supplies `SummarizeExecutions` and `ListExecutions`; their
`Application\Execution\Session\QueryHandler` handlers coordinate the existing `QueryBus::fetch()` use cases.
`Adapter\Execution\Session\Dbal\DbalExecutionReader` reads the
[recording tables](execution-observations.md) directly on SQLite, MySQL and PostgreSQL. These are safe observation
views, not authoritative Workflow state. Selected-execution/phase/activity inspection, protected reads/removal,
MCP and UI are separate capabilities.

## Ownership and layers

An execution in this overview is a recorded **Session** subject. `Read` is not an aggregate or a bounded context.
Session, Phase and Observation remain the ordinary aggregate roots; an overview neither combines them into a
new aggregate nor adds read methods to their write repositories.

| Owner | Declarations and responsibility |
| --- | --- |
| `Domain\Execution\Session` | `ExecutionSelection` validates the requested session selection; `Query\ListExecutions` and `Query\SummarizeExecutions` are Domain payloads, not Application coordinators. |
| `Domain\Execution\Usage` | `UsageTotal` owns compatible exact measurement arithmetic beside `UsageMeasurement`; it is a supporting value, not a new aggregate. |
| `Application\Execution\Session` | `ExecutionReadAdmission`, `ExecutionAccess` and `ExecutionReader` coordinate current caller scope and the dedicated read port. `ExecutionView`, `ExecutionPage` and `ExecutionSummary` are safe use-case projections, not aggregate roots. |
| `Application\Execution\Session\QueryHandler` | The two handlers obtain fresh access and delegate the selected read. |
| `Application\Execution\Session\Exception` | `ExecutionReadFailed` exposes the fixed failure without leaking diagnostics. |
| `Adapter\Execution\Session\Dbal` | `DbalExecutionReader` implements the Session read port against existing storage. |

Queries and their selection have no Application dependencies. Caller grants stay outside Domain payloads.
Summary results may include related phase/activity evidence without transferring those aggregates' ownership to
Session. Future selected-session Queries belong with Session; phase/activity Queries belong with their actual
Phase or Observation owner, not a generic `Read` peer.

## Selection, current admission and limits

`ExecutionSelection::create(projects, filters: [])` accepts a list of **0–16** exact project references. Empty
means no selection, never all projects. References retain recording's case-sensitive 1–128-byte ASCII grammar.
Duplicates collapse and projects sort bytewise. The only filters are exact `session_id` and `consumer_reference`;
either or both may be supplied. Unsupported fields, body filters, malformed values and extra serialized fields
raise fixed-text `DomainException`s without echoing inputs.

Consumers implement `ExecutionReadAdmission::currentAccess(selection): ExecutionAccess` from trusted **current
invocation context**. `ExecutionAccess::create()` takes at most 16 project keys, each mapped to `null` for a whole
project or a list of **0–100** permitted session IDs. An empty list grants nothing. All-numeric project references
remain valid despite PHP's numeric array-key conversion. Common intersects these grants with the supplied selection
and filters **in SQL before every count or row read**. A guessed/hidden/unselected project or session yields no
matches, with no existence distinction. IDs, performing Agents, message metadata, prior pages and earlier success
are not grants. Admission is called again for every summary/list request, even empty selections. Human permissions
do not imply Agent permissions; authentication, policy and revocation synchronization belong to the consumer.
The decision must remain effective throughout that invocation; never deserialize grants from Query input.

`ListExecutions(selection, page: null, perPage: null, orderings: [])` uses `Pagination::strict()` (defaults 1/100).
Page and size must be positive, size at most **100**, and offset at most **1,000,000**. Integer offset overflow
rejects before multiplication. Only `latest_recorded_at` ordering is accepted, with strict ASC/DESC directions;
all SQL fields are fixed allowlists, not interpolated caller expressions. Query `toArray()`/`fromArray()` retain
selection and normalized pagination, **not caller authority**. Each call independently supplies its selection.

## Statistics and safe results

`ExecutionSummary::toArray()` contains:

- `executions`: the count of permitted session subjects scanned across the **entire selection**, not a page.
- `outcomes`: counts of `unknown`, `succeeded`, `failed`, `cancelled`. Each execution contributes once, using the
  latest retained **session** observation; phase/activity/detail outcomes never establish execution completion.
  No session evidence means unknown. Later unknown evidence remains unknown rather than retaining an older success.
- `activities` and `activities_without_usage`: retained activity observations and those with no measurement entries.
  No activity/metric evidence is not measured zero and does not establish complete capture.
- `usage`: up to **128** bytewise ordered `(metric, unit, provenance)` groups. Each `UsageTotal` contains exact
  decimal-string `measured` (null if nothing was measured) and separate `complete`, `partial`, `unknown`,
  `unavailable` entry counts under `counts`. Complete and partial measurements add only their reported values;
  unknown/unavailable add nothing. Real zero remains `"0"`. Units/provenance never mix or convert. These counts
  describe submitted evidence, not which executions have a complete metric or a billing total. An absent metric
  has no group; absence on another activity is not silently classified complete.
- `latest_recorded_at`: maximum retained server receipt time encountered for selected executions, or null when
  none remains; `capture_completeness` is always `unknown`.

Retries do not multiply usage because the recording identity is unique. Different admitted identities are different
observations and may represent overlapping consumer measurements; Common does not infer semantic deduplication.
No phase/activity joins multiply execution counts. Exact Decimal arithmetic never uses float/database numeric SUM.
More than 128 groups, unsupported/corrupt stored evidence, arithmetic failure or storage failure raises
`ExecutionReadFailed`; **no partial summary is returned**. Its public message is fixed. The chained cause is private
diagnostic context, never a public error projection. Do not expose exception traces or causes to clients.

`ExecutionPage::executions()` returns typed `ExecutionView`s; `toArray()` returns those rows plus `total`, `page`,
`per_page`. Each row contains only the session reference, observed outcome and `latest_recorded_at`. Typed methods
are `session()`, `outcome()`, `latestRecordedAt()`. Summary/page constructors are trusted result assembly, not
untrusted input decoders. Output never includes extensions, activity text, model data, detail bodies, arguments,
results or process output. The adapter never reads `fight_execution_contents`.

Summary memory/output are finite: session and observation SQL batches contain at most **100** records each;
only the current batches and at most 128 usage totals are retained. There is no hidden cap on matched executions
or observations, no unbounded execution-result materialization and no page-local approximation. Decimal's existing
1,024-digit/scale limits also bound totals. Work grows with retained evidence; this initial direct-read adapter
uses per-execution observation batches and correlated SQL MAX/latest lookups. Consumers own query admission,
timeouts and database sizing; this is not a constant-cost analytics projection.

## Ordering, freshness and live consistency

Executions default to maximum retained recording time **descending**, then project ID **ascending**, session ID
**ascending**, both bytewise. Explicit ASC reverses only the time field. Missing times sort after known times in
DESC and before known times in ASC. This is an execution ordering basis, not observation causal order.

Where an observation determines the outcome, order is immutable recording time descending, **full canonical UUID
COMB descending**, then producer reference bytewise descending only for exact cross-producer UUID/time ties.
Receipt strings preserve six supplied server microseconds on all three databases. Occurrence time is separate and
never used to choose latest evidence. Random UUID suffixes resolve ties, not chronology. Receipt time is neither
commit order, complete external history nor a safe global checkpoint.

The reader starts no transaction, writes no business data, dispatches no Commands/events and does not alter the
connection's isolation. A page's total and row selection are **separate SQL reads**. A summary scans keyset batches
and separately reads each execution's observations; it has **no response-wide snapshot guarantee**. At ordinary
autocommit isolation, concurrent recording/removal can change evidence between statements, even within one response;
summary freshness and usage can therefore reflect different instants. A supplied connection's transaction/isolation
may provide stronger consistency, but Common does not claim it. On unchanged data, all matching retained evidence
contributes exactly once. No concurrent change is concealed as a frozen full-selection result.

Across requests, insertion/removal may change totals, repeat rows or skip rows with live offset pagination.
Access is reevaluated independently. Separate summary/list/detail calls do not share a browsing session or frozen
search. Persisted freshness says only what the reads encountered: not full producer capture, external completeness,
authoritative terminal state, projection checkpoint or review acceptance.

## Direct DBAL and Query bus composition

Use the same installed version-one recording namespace and consumer-owned connection. No new tables, indexes,
projection workers or Event Store are required. Compose the current caller admission and reader explicitly:

```php
use Fight\Common\Adapter\Execution\Session\Dbal\DbalExecutionReader;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Execution\Session\ExecutionReadAdmission;
use Fight\Common\Application\Execution\Session\QueryHandler\ListExecutionsHandler;
use Fight\Common\Application\Execution\Session\QueryHandler\SummarizeExecutionsHandler;
use Fight\Common\Domain\Execution\Session\ExecutionSelection;
use Fight\Common\Domain\Execution\Session\Query\ListExecutions;
use Fight\Common\Domain\Execution\Session\Query\SummarizeExecutions;

/** @var ExecutionReadAdmission $currentCallerAdmission Consumer implementation, not Query input */
$reader = new DbalExecutionReader($connection);
$router = new InMemoryQueryRouter();
$router->registerHandler(SummarizeExecutions::class, new SummarizeExecutionsHandler($currentCallerAdmission, $reader));
$router->registerHandler(ListExecutions::class, new ListExecutionsHandler($currentCallerAdmission, $reader));
$queries = new RoutingQueryBus($router);
$selection = ExecutionSelection::create(['project'], ['consumer_reference' => 'build:147']);
$summary = $queries->fetch(new SummarizeExecutions($selection));
$page = $queries->fetch(new ListExecutions($selection, page: 1, perPage: 20));
```

Future transports must consume these shared Queries, not implement their own admission or direct database overview.
Do not reuse recording admission as a caller read grant or infer protected-body permission from timeline permission.

## Compatibility and public surface

All **14** new declarations are additive opt-in public API: `ExecutionSelection`, `ExecutionAccess`,
`ExecutionReadAdmission`, `ExecutionReader`, `ExecutionReadFailed`, `ExecutionView`, `ExecutionSummary`,
`ExecutionPage`, `UsageTotal`, the two Queries, their two handlers and `DbalExecutionReader`.
Only the two ports promise consumer implementation; final classes do not promise extension. Named factories own
selection/access/usage construction; result/Query/handler/adapter constructors are callable as declared. All state
is private. Existing tagged APIs, QueryBus envelopes/metadata, recording payloads, schema and Pagination behavior
are unchanged. The pre-release ownership correction replaces the earlier `Application\Execution\Read` names
and relocates the DBAL reader directly; no backward shim for a never-released development snapshot is introduced.
Query envelope payload-type identifiers and handler registrations use the new Domain Session FQCNs. Discard or
recreate envelopes from the earlier development snapshot; those provisional type names are not released aliases.

`compatibility/manifest.json` records this TASK's declarations and inventory deltas. Existing release-wide
bookkeeping gaps and operation-shape fingerprint regeneration remain release-owner follow-ups, not a claim of
release certification or consumer runtime qualification. No package/release-process tests are added.
