# Functional test pyramid (TASK-00212)

## Proving boundaries

Functional is the critical transport/composition set, not the exhaustive persistence matrix. The target is
106 expanded executions, including provider rows. Native non-executing enumeration and the focused Functional
run both report exactly 106. Full verification and independent acceptance remain distinct from this table.

| Functional family | Executions | Boundary/risk retained |
|---|---:|---|
| Existing smaller journeys, excluding malformed Resource metadata variants | 51 | Event Sourcing, guarded endpoint, OAuth, streaming emitters, Prompt/Resource/Skill composition, confirmation races, safe error rendering and quick-start composition |
| McpExecutionInspectionJourneyTest | 6 | Every existing method, SQLite configured storage: full-UUID/producer/project/session/phase intersections, independent current permissions, safe failures and live inspection |
| McpProtectedExecutionJourneyTest | 6 | Every existing method, SQLite configured storage: independent body/reference/detail authority, chunk/page boundaries, capture truthfulness, removal/no resurrection and safe corruption failure |
| McpTaskJourneyTest | 12 | Three native JSON compositions; three independent cancellation outcomes; response loss on all three drivers; submission, publication and storage failure |
| McpTaskLiveJourneyTest | 19 | Six native emitters; failed/cancelled outcomes; idle disconnect/death/graceful completion; all nine fault-by-phase source races; tracking storage failure |
| McpSubscriptionJourneyTest | 12 | Isolation on SQLite/MySQL; routed operator inspection; Prompt authority/recovery; six native emitters; abrupt death/owner expiry; removal/overload versus replay |
| **Total** | **106** | No new loops around omitted provider rows |

Configured storage is used for the representative native journeys. Driver and name-selection correctness is
proved independently below, rather than multiplying each HTTP journey by every physical table selection.
All nine live source race fault/phase combinations remain genuine socket executions; none is replaced by a mock.

## Assertion-preserving persistence consolidation

The former Notice and Task nontransport journeys already use real DBAL connections, routed buses and independent
workers. They belong beside their persistence adapters, not in the HTTP suite. Their test bodies and assertions
are retained. Each behavior/race now runs on **every driver** with fully configured tables, including quoted
Notice `Order`. Default/partial/full table-selection products are factored into dedicated selection tests,
not repeated through every lifecycle/race. This eliminates redundant schema installation, seeding and child
processes, not just a suite label. No reduction in cost is attributed to relocation itself.

The mapping rule is exact: every original method maps to the identically named method in the corresponding
`Dbal*IntegrationTest` below; every original scenario dimension (fault, lock order, outcome, status message,
terminal winner) is retained. Only the orthogonal name-selection dimension is consolidated. Source assertion
bodies remain reviewable in the move diff. Offline token comparison against maintenance base
`ffacc22569f3495a6cc060295ca8d0f6f4b91051` verifies all 51 relocated test-method bodies (50 persistence,
plus Resource metadata) unchanged except relocated worker paths: 645 explicit assertion/fail call sites,
plus their called helper assertions. Detailed before/destination method IDs, body SHA-256 values and the
comparison command are retained in TASK-00212's Engineer-02 `assertion-body-map.json`. The source move diff
and that body comparison, not matching class names, establish preservation.

| Former Functional class | Integration destination under `tests/Adapter/Mcp/Persistence/` | Retained assertion/contract groups |
|---|---|---|
| NoticeRecordingJourneyTest | Notice/Dbal/DbalNoticeRecordingIntegrationTest.php | Reopen/identity/unknown relationships and occurrence precision; current independent admission before retry lookup; every related-write/precommit rollback; actual constraint atomicity; postcommit dispatch/lost acknowledgment; caller transaction/capacity ceiling; all five delayed-writer race modes |
| NoticeInspectionJourneyTest | Notice/Dbal/DbalNoticeInspectionIntegrationTest.php | Full selection and current caller grants; summary/history/detail independence; no mutation or access lease; fresh commits/disappearance; explicit dimension overflow |
| NoticePublicationJourneyTest | Notice/Dbal/DbalNoticePublicationIntegrationTest.php | Real Mercure adapters/current publication policy; safe relationships and recovery; no precommit output/caller enlistment; polling/revocation/removal independent of optional delivery; retained content after dispatch failure |
| NoticeRecoveryJourneyTest | Notice/Dbal/DbalNoticeRecoveryIntegrationTest.php | Multibatch authorized committed order; grants/context/hidden-loss integrity; truthful retirement/missing evidence; finite scan/input/guard/storage failures; exact encoded budgets; all three record/remove/recover lock orders |
| NoticeRemovalJourneyTest | Notice/Dbal/DbalNoticeRemovalIntegrationTest.php | Exact bounded selection; maximum batch/no oversized mutation; independent preflight; bounded body-free loss; all delete/loss/commit failures; actual constraint rollback; caller-work preservation; all eleven serialization/revocation/rollback race modes |
| NoticeTableSelectionJourneyTest | Notice/Dbal/DbalNoticeTableSelectionIntegrationTest.php | **Unreduced** all-driver full/partial/individual selections; populated-default isolation for identity, comparison, inspection, recovery and removal; only selected installation; missing/partial selected storage never falls back |
| TaskLifecycleJourneyTest | Task/Dbal/DbalTaskLifecycleIntegrationTest.php | All five coherent states on fresh connections; rollback/uncertain commit/postcommit failure; caller work; timestamp/retention/poll ceilings; accepted context depth; all eight retry/terminal races; removal/retry/no resurrection; independent policy/expiry; all eight removal lock-order modes |
| TaskInputJourneyTest | Task/Dbal/DbalTaskInputIntegrationTest.php | Issuance/reopen/partial admission/protected single consumption; all three status-message shapes; rollback/uncertainty/postcommit failure; actual constraint atomic batch; supersession/expiry/finite identity; all ten contender/terminal/revocation modes |
| TaskCancellationJourneyTest | Task/Dbal/DbalTaskCancellationIntegrationTest.php | Three actual consumer outcomes with private intent; rollback/response loss/postcommit faults; both orders of every cancellation/input/acquire/issue/terminal operation; revocation; all twelve input/terminal/order combinations |

Task physical-name equivalence is independently exercised by
`DbalConfiguredTaskIntegrationTest::test_that_each_selection_owns_lifecycle_and_committed_reads_without_default_fallback`:
it installs twice, populates defaults, poisons unselected default state, reopens the selected namespace, issues,
supplies, observes, cancels, acquires and records; it asserts rollback, terminal conflict, expiry and unchanged
default bytes. Its other methods preserve missing/incompatible-storage denial. Notice's dedicated selection
methods above exercise default versus partial and individually overridden state/notice/relationship/comparison
tables, including missing selected storage. No driver is removed from those products.

## Native product factorization

Execution HTTP methods retain all their assertions once. Real backend/name matrices remain in
`DbalConfiguredRecordingIntegrationTest`, `DbalConfiguredInspectionIntegrationTest`, `DbalExecutionInspectionIntegrationTest`,
`DbalActivityReaderIntegrationTest`, `DbalDetailReaderIntegrationTest` and `DbalDetailRemovalIntegrationTest`.
Those prove storage selection, full identity intersections, live reads, safe corruption and removal at the adapter
seam without rebuilding an HTTP fixture for each table spelling. Application access/Query/Tool tests remain the
owner of deterministic projection, admission and independent detail/reference/body rules; their direct coverage
is not replaced by the representative HTTP cases.

Task native journeys retain their assertion bodies, three response-loss driver variants and every framework
composition. `DbalConfiguredTaskIntegrationTest` owns the storage product; the three Task integration families
above preserve every driver race and outcome. `tests/Domain/Mcp/Task/TaskTest.php` and
`tests/Application/Mcp/Task/{TaskHandlerTest,TaskInputTest,TaskCancellationTest,TaskProjectionTest}.php`
continue proving deterministic transitions, admission, single consumption and disclosure at their existing
owning seams. Neither the direct-coverage denominator nor coverage metadata is narrowed.

Subscription heartbeat-clock-crossing and same-second ownership/transaction assertions move to focused real
DBAL Integration. Capacity contention keeps every backend, mode, transport callback and delayed-bootstrap
variant at that same seam: its existing body/emitter supplier path does not need a socket server. It retains
real competing processes, lock waits, freshness/expiry, bounded failure and recovery assertions. The routed
operator journey remains Functional because it actually uses native streams. Configured subscription storage
continues independently in `DbalConfiguredSubscriptionIntegrationTest`.

Malformed Resource metadata variants move to `tests/Adapter/Mcp/Http/McpResourceMetadataIntegrationTest.php`,
a focused HTTP/Resource composition test retaining the same complete body and all five provider rows. They construct a bad provider rather than an external transport; the genuine
Resource discovery journey remains Functional.

## Orchestration and evidence

Response loss is deliberately ordered after an independently observed committed Task but before response emission.
The controlled red/green evidence proves the old fixture marker dependency, not the unknown historical
certification cause. Subscription isolation prepares its independent producer before opening either stream,
then releases publication only after both acknowledgments and independent stored identities are observed.
The retained regression deliberately spends 2.2 seconds bootstrapping that producer: opening finite two-second
streams first fails, while the readiness-first order passes. The producer's readiness and release waits are
bounded and process diagnostics survive failure; the two-second stream lifetime is unchanged. This removes
producer bootstrap from the interval under test, not the possibility of legitimate expiry under arbitrary
scheduler starvation. Separate expiry/ownership/contention tests continue proving that permitted outcome.
No global deadline or retry policy is relaxed. Per-run case inventories, assertion mapping checks, regressions, counts and actual timings belong in
TASK-00212's ignored handoff. Native enumeration is Unit **8,004**, Integration **1,345**, Functional **106**
versus the qualified historical/non-executed baseline **8,004 / 920 / 1,286**. The dominant Notice/Task
persistence families change from 846 executions to 366; the 18 dedicated Notice selection executions remain
unreduced. Overall 755 redundant product executions are consolidated, not 1,180 contracts deleted.
The exact 106 IDs are in the Engineer-02 `functional-list.txt`; each provider expansion inherits its method's
boundary/risk in the table above. No product test enforces a magic suite count. No fresh unchanged baseline is required and relocation is not a speedup measurement.
