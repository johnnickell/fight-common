# Authorized execution inspection

`Domain\Execution\Session\Query` supplies `SummarizeExecutions` and `ListExecutions`; their
`Application\Execution\Session\QueryHandler` handlers coordinate the existing `QueryBus::fetch()` use cases.
`Adapter\Execution\Dbal\DbalExecutionReader` reads the
[recording tables](execution-observations.md) directly on SQLite, MySQL and PostgreSQL. These are safe observation
views, not authoritative Workflow state. Selected-execution summaries and phase pages use the same current
admission. Phase activity and permitted detail-reference metadata reuse that admission through `ListPhaseActivity`.
Protected body reads/removal, MCP and UI remain separate capabilities.

## Ownership and layers

An execution in this overview is a recorded **Session** subject. `Read` is not an aggregate or a bounded context.
Session, Phase and Observation remain the ordinary aggregate roots; an overview neither combines them into a
new aggregate nor adds read methods to their write repositories.

| Owner | Declarations and responsibility |
| --- | --- |
| `Domain\Execution\Session` | `ExecutionSelection` validates selection; `Query\ListExecutions`, `Query\SummarizeExecutions` and `Query\GetExecution` are Domain payloads, not Application coordinators. |
| `Domain\Execution\Phase` | `Query\ListExecutionPhases` owns bounded phase selection within an exact selected Session. |
| `Domain\Execution\Usage` | `UsageTotal` owns compatible exact measurement arithmetic beside `UsageMeasurement`; it is a supporting value, not a new aggregate. |
| `Application\Execution\Session\Service` | `ExecutionReadAdmission`, `ExecutionAccess` and `ExecutionReader` provide current caller scope and the dedicated read port. |
| `Application\Execution\Session\Data` | `ExecutionView`, `ExecutionPage`, `ExecutionSummary` and `ExecutionInspection` are safe use-case projections, not aggregate roots. |
| `Application\Execution\Session\QueryHandler` | The three handlers obtain fresh access and delegate the selected read. |
| `Application\Execution\Phase` | `Service\PhaseReader`, `Data\PhaseView`, `Data\PhasePage` and `QueryHandler\ListExecutionPhasesHandler` own the phase-page use case. |
| `Application\Execution\Observation\Data` | `ObservationView` projects only observed timing/outcome and recording provenance. |
| `Domain\Execution\Observation\Query` | `ListPhaseActivity` selects activity evidence owned by Observation within an exact phase. |
| `Application\Execution\Observation` | `Service\ActivityReader`, its Query handler, `Data\ActivityPage` and `Data\ActivityView` own safe activity browsing. |
| `Application\Execution\Detail\Data` | `DetailReference` projects permitted capture/availability metadata, never content or permission to read it. |
| `Application\Execution\Session\Exception` | `ExecutionReadFailed` exposes the fixed failure without leaking diagnostics. |
| `Adapter\Execution\Dbal` | `DbalExecutionReader`, `DbalPhaseReader` and `DbalActivityReader` implement the inward read ports alongside recording adapters; internal `DbalExecutionReadSupport` shares SQL constraints and bounded safe-evidence decoding. |

Queries and their selection have no Application dependencies. Caller grants stay outside Domain payloads.
Summary results may include related phase/activity evidence without transferring those aggregates' ownership to
Session. Selected-session Queries belong with Session; phase/activity Queries belong with their actual
Phase or Observation owner, not a generic `Read` peer. The DBAL adapters stay together under
`Adapter\Execution\Dbal`; the reader remains read-only, but needs no `Reader` subnamespace. Revisit deeper
adapter grouping before a tagged release if that directory grows enough to justify it.

**Readers are not replacement repositories.** Domain `SessionRepository`, `PhaseRepository`,
`ObservationRepository` and `ProtectedContentRepository` remain aggregate collection/persistence contracts.
This read port is justified by an actual permission-constrained, bounded projection across observation roots
and content-row presence, not by a rule that every aggregate needs a Reader. Putting caller grants or these
Application Data results into Domain repositories would invert ownership. `ActivityReader` is owned by the
activity use case under Application Observation; activity is an Observation variant, not another aggregate.
Its Query likewise stays under Domain Observation; the existing Domain Activity value is not turned into a
new collection/persistence owner. `DetailReference` remains with Detail. Consumers supply policy through the
existing single admission. DBAL translates those constraints and validates stored relationships; Domain
`DetailCapture::acceptsActivity()` owns the detail/activity invariant. No second authorizer, generic reporting
repository, new aggregate or broad repository refactor is introduced. This is a narrow provisional choice,
not a mandate to rename repositories elsewhere.

## Selection, current admission and limits

`ExecutionSelection::create(projects, filters: [])` accepts a list of **0–16** exact project references. Empty
means no selection, never all projects. References retain recording's case-sensitive 1–128-byte ASCII grammar.
Duplicates collapse and projects sort bytewise. The only filters are exact `session_id` and `consumer_reference`;
either or both may be supplied. Unsupported fields, body filters, malformed values and extra serialized fields
raise fixed-text `DomainException`s without echoing inputs.

Consumers implement `ExecutionReadAdmission::currentAccess(selection): ExecutionAccess` from trusted **current
invocation context**. `ExecutionAccess::create()` takes at most 16 project keys, each mapped to `null` for a whole
project or a list of **0–100** permitted session IDs. An empty list grants nothing. Its optional second argument,
`phaseGrants`, maps already supplied project keys to lists of **0–100** permitted phase IDs (unique within a project).
Omitting a project from `phaseGrants` leaves its phases unrestricted; an empty list conceals all its phase evidence.
Phase restrictions never grant a session or project. They apply to overview freshness/usage as well as selected
summaries, phase counts/rows and exposed parents. Session-level evidence remains available under a session grant.
All-numeric project references
remain valid despite PHP's numeric array-key conversion. Common intersects these grants with the supplied selection
and filters **in SQL before every count or row read**. A guessed/hidden/unselected project or session yields no
matches, with no existence distinction. IDs, performing Agents, message metadata, prior pages and earlier success
are not grants. Admission is called again for every overview, selected summary or phase-page request, even empty selections. Human permissions
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
are `session()`, `outcome()`, `latestRecordedAt()`. All result constructors are trusted result assembly, not
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

## Selected execution and phases

`GetExecution(projectId, sessionId)` supplies one exact project/session selection and returns nullable
`ExecutionInspection`. `toArray()` contains `execution` (the existing safe `ExecutionView` shape), `observation`
(the latest retained session state evidence, or null) and `summary` (the existing full-selection statistics shape,
restricted to this exact execution and its currently permitted phases). Statistics are not constrained by any
phase-page filters or pagination. The observed timing comes from **one latest state observation**, not inferred
minimum start, maximum end, activity timing or surrounding success.

`ListExecutionPhases(projectId, sessionId, page: null, perPage: null, filters: [], orderings: [])` returns nullable
`PhasePage`. It has the same strict default 1/100, maximum size **100** and maximum offset **1,000,000** as
`ListExecutions`. Only exact `phase_id`, `agent_id` and `name` filters are accepted; only `latest_recorded_at`
ASC/DESC ordering is accepted. Extra payload fields, invalid references, bounds, directions and unsupported fields
raise fixed-text Domain failures without echoing input. Each Query's exact array representation carries selection,
filters and normalized pagination, **never grants**. Both Query envelopes support ordinary serialization/routing.

A phase page contains typed `phases()` (`PhaseView`s) and `toArray()` fields `phases`, `total`, `page`, `per_page`.
Each phase row contains:

- `phase`: session/phase IDs, performing `agent_id`, name, attempt and only a permitted, same-project/same-session
  `parent_phase_id`. Null means no **disclosed** parent, not proof that the phase has no parent. Parent permission
  does not follow from child permission. Parent lookup is independently constrained before selection.
- `observation`: latest retained **phase state** evidence, excluding activity and detail observations, or null.
  The `ObservationView` shape contains only `observation_id`, instrumentation `producer_id`, `occurred_at`,
  `recorded_at`, nullable `started_at`/`ended_at`, and explicit observed `outcome`. Missing evidence is unknown,
  never success. No review acceptance or Workflow transition is inferred, even for a phase named `review` in a
  succeeded execution. Selected session observations use this same safe shape.
- `latest_recorded_at`: latest retained recording time for that phase across state/activity/detail evidence, or null.
  This freshness may be later than the state observation, and does not establish completion or complete capture.
- `models`: bytewise sorted distinct model references from retained phase activity, at most **128** per phase.
  Models remain separate from the performing Agent and recording producer; an empty list means no retained model
  reference, not proof that no model performed work. More than 128 fails the whole response rather than truncating.
  No activity summary, detail reference, extension or body is returned; this is not activity browsing.

Phase rows default to their latest retained observation's immutable recording time descending, then **full UUID**
descending, then producer bytewise descending. Exact cross-phase ties use phase ID bytewise ascending. Explicit ASC
reverses the first three fields only. Evidence-free phases sort last in DESC, first in ASC. Latest state selection
uses recording time/full UUID/producer descending independently of requested page ordering. Six recording-time
microseconds are retained on SQLite, MySQL and PostgreSQL. Occurrence time is separate; neither timestamp, UUID
suffix nor phase attempt supplies causality, commit ordering, Workflow authority or a global checkpoint.

Missing and concealed executions return the same **null** for both Queries, without metadata or total counts.
A permitted existing execution with no permitted/matching phases returns an **empty page with total zero**, not
null. Out-of-range pages preserve the current authorized matching total. Guessed phase filters and phases belonging
to another session/project return no matching rows. A retained subject with no state evidence remains inspectable
with null observation/freshness as applicable; missing usage is not zero. Storage, corrupt identity/relationship,
unsupported-version or finite-output-limit failure raises the existing fixed `ExecutionReadFailed`, never an empty
successful page or partial statistics. Do not publicly expose its chained diagnostic cause.

These readers issue no business writes/events and start no transaction. SQL constrains counts, selected rows and
observation batches **before** loading evidence. Phase pages read at most 100 rows plus per-row batches of 100
observations, retaining at most 128 model references per row. Full selected usage reuses the existing exact
metric/unit/provenance grouping and 128-group limit; no activity joins multiply totals. Work grows with retained
evidence, not merely page size. No protected storage is read. Consumer admission/timeouts/database sizing remain
necessary; finite output is not a constant-cost query guarantee.

Every call freshly obtains `ExecutionReadAdmission::currentAccess()`; earlier overview membership, a saved envelope
or a parent reference confers no authority. Consumers must supply all relevant current human/Agent distinctions
and keep that decision effective throughout the invocation. Totals, state, freshness, usage and phase rows use
separate live statements, not one response-wide snapshot. Inserts/removal may repeat/skip rows between pages;
summary and phase requests independently see changed data/permissions. There is no retained browsing session or
cross-request frozen result. Consumer transaction isolation may be stronger but is not promised by Common.

## Phase activity and permitted detail references

`Domain\Execution\Observation\Query\ListPhaseActivity(projectId, sessionId, phaseId, page: null, perPage: null,
filters: [], orderings: [])` returns nullable Application `ActivityPage`. The exact Query array contains those
three IDs, normalized `page`, `per_page`, `filters` and `orderings`, never authority. Strict defaults are **1/100**,
maximum size **100**, maximum offset **1,000,000**. Supported filters are exact `producer_id`, namespaced `kind`
and `model_id`; only `recorded_at` ASC/DESC is accepted. Invalid/extra fields and bounds raise fixed-text Domain
failures without echoing inputs. Body, summary-text and arbitrary SQL filtering are not supported.

`ExecutionReadAdmission::currentAccess()` is called on every invocation, including saved envelopes and missing
phases. Existing project/session/phase constraints first select a consistent phase. Missing, cross-project,
cross-session and concealed phases all return **null**, with no total. A permitted phase with no permitted
matching activity returns an empty page and total zero. Out-of-range pages retain the authorized matching total.
State and detail observations are not activity rows and do not contribute to activity totals.

`ExecutionAccess::create()` adds optional third/fourth arguments `activityGrants` and `detailGrants`. Each is a
map of already granted project keys to `null` or a list of **0–100** exact `producer_id/observation_uuid` keys.
Producer references cannot contain `/`; UUIDs must be full canonical lowercase strings. Lists snapshot and
deduplicate keys. These grants cannot authorize a different project, session or phase. `activityGrants` restricts
**activity browsing**, with omitted projects or null unrestricted and an empty list concealing all activity rows.
`detailGrants` explicitly permits **reference metadata disclosure**, with omitted projects or an empty list
denying all references and null permitting all references linked to otherwise visible activity. A detail key
from another producer/activity/scope grants nothing. SQL applies these constraints before decoding activity or
detail rows; hidden references are not read, counted or reported as hidden. This extends the same admission
result, not another permission-policy callback. Consumers must deliberately supply any human/Agent distinctions.

These are distinct use-case permissions: aggregate overview statistics retain their existing session/phase
scope and do not acquire activity-browsing/reference restrictions. Consumers must not grant aggregate inspection
if those aggregate counts/usage are themselves confidential. Activity row permission never grants reference
metadata, and reference disclosure never grants protected body retrieval. Current body-read policy remains a
separate TICKET-00042 use case; references and `retained` status are not credentials or leases.

`ActivityPage::activities()` returns typed `ActivityView`s; `toArray()` contains `activities`, authorized `total`,
`page`, `per_page`. Each row contains only `producer_id`, `observation_id`, `occurred_at`, `recorded_at`, performing
`agent_id`, the version-one safe `activity` (kind, consumer-redacted summary, model and exact bounded usage), and
`details`. Models, instrumentation producers and performing Agents remain distinct. Extensions, consumer/parent
relationships and protected bytes are never projected. Summary safety depends on consumer pre-submission
redaction; this is structural separation, not an arbitrary secret detector or independent review verdict.

`ActivityView::details()` returns typed `DetailReference`s. At most **32 permitted references per activity** are
returned, newest recording time/full UUID first; excess fails the entire Query, never truncates. The complete
`ActivityPage::toArray()` must fit **1,048,576 encoded bytes** using PHP `json_encode(..., JSON_THROW_ON_ERROR)`
default escaping. Excess raises `ExecutionReadFailed`; consumers requesting smaller pages can reduce ordinary
response size. A downstream protocol envelope may need additional independent bounds. Result constructors are
trusted assembly, not untrusted-input decoders or an enforcement bypass for custom reader implementations.

Each reference includes producer/full observation identity, occurrence/recording timestamps, versioned `capture`
metadata and `availability`. Capture retains original kind, content type, completeness, linked activity and
optional output coordinates. Complete/partial capture is `retained` only when the FK-linked content row exists;
otherwise it is `missing` (unknown reason, not inferred expiry or never-captured). Supplied `absent`, `unavailable`
and `expired` markers are preserved even after the content row disappears. No visible references means only
no currently disclosed retained reference, not proof that nothing was captured. `retained` describes row presence
under the recording contract, not body validation, completeness, current read permission or future availability.
No protected payload column is selected/decoded; only `EXISTS (SELECT 1 ...)` checks permitted row presence.
Missing content **tables**, corrupt/unsupported evidence, invalid relationships and storage errors fail with the
existing fixed `ExecutionReadFailed`, not an empty success or false absence. Its cause remains private diagnostics.
Without any detail-reference grant the adapter does not query the content table at all.

Activity ordering is immutable receipt time, **full UUID**, then bytewise producer, all descending by default;
ASC reverses all three. Actual six receipt microseconds survive SQLite/MySQL/PostgreSQL. Occurrence retains its
original zero-to-six fractional digits and never orders the page. Retried identities preserve receipt time.
Late arrivals, tied random suffixes and producer tie-breaks do not establish causality, commit order, Workflow
state, complete capture or a safe global checkpoint. Recording time in each returned row describes only that
persisted evidence, not an overall freshness/completeness guarantee.

The direct reader scans permitted observations in **100-row keyset batches**, filters safe kind/model metadata,
counts matching activity across the full phase, and retains at most the requested page. It then reads at most
33 permitted reference rows per selected activity (one lookahead for the 32 limit). Memory/output are finite,
but work grows with retained evidence and the full matching total is not page-local. No new schema/index,
projection worker or Event Store is introduced. Consumer admission, timeouts and sizing remain necessary.

No business mutation, Command, event, transaction or connection-isolation change occurs. Subject selection,
activity scan and reference/presence statements are live, **not a response-wide snapshot**. Concurrent changes
can affect one response's total/page/availability; inserts/removal may repeat or skip rows between pages.
Permission changes and content removal take effect on subsequent invocations with a fresh consumer decision.
The consumer keeps that decision effective for the invocation; there is no remembered browsing state, retained
content lease or promised integrated deletion/retry protocol here. Storage-level removal fixtures qualify safe
absence; public removal and its coordination with recording retries remain TICKET-00042.

## Direct DBAL and Query bus composition

Use the same installed version-one recording namespace and consumer-owned connection. No new tables, indexes,
projection workers or Event Store are required. Compose the current caller admission and reader explicitly:

```php
use Fight\Common\Adapter\Execution\Dbal\DbalExecutionReader;
use Fight\Common\Adapter\Messaging\Query\Routing\InMemoryQueryRouter;
use Fight\Common\Adapter\Messaging\Query\RoutingQueryBus;
use Fight\Common\Application\Execution\Session\QueryHandler\ListExecutionsHandler;
use Fight\Common\Application\Execution\Session\QueryHandler\SummarizeExecutionsHandler;
use Fight\Common\Application\Execution\Session\Service\ExecutionReadAdmission;
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

For selected navigation, add these explicit registrations to the same router/admission and connection:

```php
use Fight\Common\Adapter\Execution\Dbal\DbalPhaseReader;
use Fight\Common\Application\Execution\Phase\QueryHandler\ListExecutionPhasesHandler;
use Fight\Common\Application\Execution\Session\QueryHandler\GetExecutionHandler;
use Fight\Common\Domain\Execution\Phase\Query\ListExecutionPhases;
use Fight\Common\Domain\Execution\Session\Query\GetExecution;

$router->registerHandler(GetExecution::class, new GetExecutionHandler($currentCallerAdmission, $reader));
$router->registerHandler(
    ListExecutionPhases::class,
    new ListExecutionPhasesHandler($currentCallerAdmission, new DbalPhaseReader($connection))
);
$execution = $queries->fetch(new GetExecution('project', 'session'));
$phases = $queries->fetch(new ListExecutionPhases('project', 'session', page: 1, perPage: 20));
```

For activity, register this handler on the same router with the same current admission:

```php
use Fight\Common\Adapter\Execution\Dbal\DbalActivityReader;
use Fight\Common\Application\Execution\Observation\QueryHandler\ListPhaseActivityHandler;
use Fight\Common\Domain\Execution\Observation\Query\ListPhaseActivity;

$router->registerHandler(
    ListPhaseActivity::class,
    new ListPhaseActivityHandler($currentCallerAdmission, new DbalActivityReader($connection))
);
$activity = $queries->fetch(new ListPhaseActivity('project', 'session', 'phase', page: 1, perPage: 20));
```

To disclose reference metadata, the consumer's fresh admission may for example return
`ExecutionAccess::create(['project' => ['session']], ['project' => ['phase']], [], ['project' => null])`.
That trusted result is never Query input and grants no later body retrieval.

Future transports must consume these shared Queries, not implement their own admission or direct database overview.
Do not reuse recording admission as a caller read grant or infer protected-body permission from timeline permission.

## Compatibility and public surface

TASK-00150 introduced **14** additive opt-in public declarations: `ExecutionSelection`, `ExecutionAccess`,
`ExecutionReadAdmission`, `ExecutionReader`, `ExecutionReadFailed`, `ExecutionView`, `ExecutionSummary`,
`ExecutionPage`, `UsageTotal`, the two Queries, their two handlers and `DbalExecutionReader`.
Only its two ports promise consumer implementation; final classes do not promise extension. Named factories own
selection/access/usage construction; result/Query/handler/adapter constructors are callable as declared. All state
is private. Existing tagged APIs, QueryBus envelopes/metadata, recording payloads, schema and Pagination behavior
are unchanged. The pre-release ownership correction replaces the earlier `Application\Execution\Read` names
and relocates the DBAL reader directly; no backward shim for a never-released development snapshot is introduced.
Query envelope payload-type identifiers and handler registrations use the new Domain Session FQCNs. Discard or
recreate envelopes from the earlier development snapshot; those provisional type names are not released aliases.

TASK-00151 adds **10** opt-in public declarations: `GetExecution`, `ListExecutionPhases`, their two handlers,
`ExecutionInspection`, `ObservationView`, `PhaseView`, `PhasePage`, `PhaseReader` and `DbalPhaseReader`.
`PhaseReader` promises consumer implementation; final classes do not promise extension. Constructors and methods
are callable as declared; result constructors assemble trusted projections rather than validate untrusted input.
One additional declaration, `DbalExecutionReadSupport`, is explicitly internal, with no consumer-operation promise.
The existing provisional `ExecutionReader` port adds `inspect()` and `ExecutionAccess::create()` adds optional
phase restrictions; custom unreleased implementations must add the selected operation. No shim is provided for
previous development-only shapes. Session overview behavior without phase restrictions remains supported.
Tagged declarations, envelopes/metadata, recording payloads, schema/indexes and existing Pagination are unchanged;
there is no migration, asynchronous projection or Event Store dependency.

TASK-00152 adds **7** opt-in public declarations: `ListPhaseActivity`, its handler, `ActivityReader`,
`ActivityPage`, `ActivityView`, `DetailReference` and `DbalActivityReader`. Only `ActivityReader` promises consumer
implementation; all concrete additions are final with callable declared constructors/methods. The existing
provisional `ExecutionAccess` gains optional activity-browsing and reference-disclosure maps without changing
existing session/phase calls. Domain repositories, tagged APIs, recording/schema/indexes, Query envelopes,
Pagination and earlier safe overview/phase behavior remain unchanged. There is no alias/migration for unreleased
snapshots and no protected body API. Compatibility remains against actual tags (latest observed `v1.4.0`),
not development snapshots; these Execution declarations are absent from that tag and remain provisional.

`compatibility/manifest.json` records these TASKs' declarations and inventory deltas. Existing release-wide
bookkeeping gaps and operation-shape fingerprint regeneration remain release-owner follow-ups, not a claim of
release certification or consumer runtime qualification. No package/release-process tests are added.
