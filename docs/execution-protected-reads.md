# Separately authorized captured detail

Protected inspection uses the existing Command/Query buses and [recording storage](execution-observations.md),
not a new capture system. `Domain\Mcp\Detail\Query\ListCapturedMessages` and `GetCapturedDetail` have
Application Detail handlers and `Service\DetailReader`; `Adapter\Mcp\Execution\Dbal\DbalDetailReader` implements
actual SQLite/MySQL/PostgreSQL reads. These Queries are independent of [safe inspection](execution-reads.md).
[Ordinary authorized removal](execution-detail-removal.md) shares this storage; it is separate from these read-only
Queries. [Explicit protected MCP Tools](mcp-execution-inspection.md#protected-detail-opt-in-and-compatibility)
reuse these same Queries with independent Tool availability and detail admission; neither the reference nor Tool
availability grants body access. No MCP removal Tool, UI, hidden reasoning or retention engine is included.

## Selection and current permission

`DetailSelection::create(projectId, sessionId, phaseId, producerId, activityId)` selects one exact recorded
activity. References use recording's case-sensitive 1–128 ASCII-byte grammar; activity UUID is a complete
canonical lowercase UUID. Scope includes the producer because UUIDs alone are not globally unique identities.
The selected session, phase, activity and detail must have consistent retained relationships. Kind and detail
identity are additionally required for individual reads. Guessed, cross-project, cross-session, cross-phase,
cross-activity, cross-producer, absent and concealed targets return the same **null**, without body or metadata.
A kind mismatch also returns null. Invalid stored relationships fail closed, never fabricate a link.

Consumers implement **`Application\Mcp\Execution\Detail\Service\DetailReadAdmission`**. Each handler calls
`currentAccess(DetailSelection): DetailAccess` afresh for **every** page/chunk, including saved envelopes and
missing targets. This is a distinct port, not `ExecutionReadAdmission` or recording admission. The consumer owns
authentication, current policy, human/Agent differences and synchronization of revocation. The decision must
remain effective for the invocation. Earlier success, a reference, timeline permission and a human's permission
never confer Agent body access. No caller grants are serialized into Domain Queries.

`DetailAccess::create(projects, phaseGrants: [], metadataGrants: [], bodyGrants: [])` supplies:

- Up to **16** project keys, each `null` for the whole project or **0–100** explicit session references.
- Optional project phase restrictions, **0–100** phase IDs. Omitted phases are unrestricted within the project/
  session grant; an empty list permits no phases. Restrictions never add scope.
- Separate explicit metadata and body maps, each using existing project keys and `null` for all detail identities
  in scope or **0–100** exact `producer/uuid` keys. Omitted projects and empty lists grant **nothing**. Duplicate
  keys collapse. Body permission without metadata permission reveals nothing; metadata without body permission
  reveals only permitted references/availability and `body_permitted: false, chunk: null`.

The implementation reuses `ExecutionAccess` only for bounded structural validation and SQL scope intersection;
it never asks the timeline admission or imports its grants. Metadata constraints apply in SQL before detail
rows are decoded or counted. Body columns are selected only for currently body-permitted page members/individual
reads. A metadata grant includes disclosure of the selected activity's existence and permitted linked-detail
count; the activity's summary, usage, model and parent relationship are not returned. No hidden count is emitted.

## Exact Queries and finite bounds

`ListCapturedMessages(selection, page: null, perPage: 32, orderings: [])` returns nullable
`Application\Mcp\Execution\Detail\Data\MessagePage`. Pagination is strict: defaults **1/32** (explicit null size
also means 32), size **1–32**, offset **0–1,000,000**. Integer overflow rejects before multiplication. Only
`recorded_at` ASC/DESC ordering is accepted; there are no arbitrary filters. It lists **message** captures linked
to the selected activity, with the full current metadata-permitted message total, `page`, `per_page` and typed
`messages()`. An admitted existing activity with no matching permitted messages returns an empty page, total
zero. No metadata grants returns null, as does a concealed/missing activity. Out-of-range pages preserve total.
Each permitted message includes its first **at most 1,024 raw bytes** if separately body-permitted; use the
individual Query to continue it. Tool/output observations never count as messages.

`GetCapturedDetail(selection, detailId: ObservationId, kind: DetailKind, offset: 0, length: 4096)` returns nullable
`CapturedDetail`. Supported kinds are exactly message, tool arguments, tool result and process output. Offset is
an integer **0–65,536**, length **1–4,096**. The offset is relative to this **one captured body**, including for
an output segment; it is not an absolute process stream position. For retained non-null bytes, offset beyond the
actual body length fails safely with `ExecutionReadFailed`; exact-end offset succeeds with an empty final chunk.
Unknown fields, malformed serialized shapes, invalid UUIDs/kinds/ranges/directions and unsupported orderings
raise fixed-text `DomainException` without echoing inputs. No offset-plus-length arithmetic can overflow these
finite validated ranges. Native PHP type errors are separate from value validation.

Both Queries implement exact `toArray()`/`fromArray()` and ordinary QueryMessage serialization. The list shape
is `selection, page, per_page, orderings`; the individual shape is `selection, detail_id, kind, offset, length`.
Selection contains exactly the five snake-case IDs above. Consumers must not deserialize authority from inputs.

## Bytes, content types and continuation

`CapturedDetail::toArray()` contains `reference` (the existing permitted `DetailReference` representation),
`body_permitted` and nullable typed-assembly `chunk`. A chunk has:

| Field | Meaning |
| --- | --- |
| `encoding` | Always `base64`; encoding is neither encryption nor redaction. |
| `data` | Canonical standard Base64 of exactly this chunk's captured bytes. |
| `offset`, `bytes` | Relative starting byte offset and actual raw byte count. |
| `total_bytes` | Current retained body length, disclosed only with body permission and a retained non-null body. |
| `next_offset` | Next relative byte offset, or null at the end of **this captured body**. |

Decode Base64 strictly and concatenate bytes at the reported coordinates. A chunk may split a UTF-8 character;
do not independently decode each raw chunk as text. Reconstruct the allowed complete byte sequence before text
interpretation. Original recording requires valid UTF-8 even for non-text media types; this API does **not**
extend recording to arbitrary binary payloads. CRLF, empty strings and multibyte bytes survive exactly. Media
type is preserved as declared, not inferred or transformed. Treat captured content as untrusted consumer data.

Recording bounds remain **65,536 raw bytes** per message/tool body and **16,384** per output segment. Each
individual response contains at most 4,096 raw bytes (Base64 at most 5,464 characters). A message page contains
at most 32,768 raw bytes (32 individually encoded initial chunks). Fixed bounded reference fields and Base64
keep these DBAL `toArray()` results below **262,144 default PHP JSON-encoded bytes**, excluding any consumer
transport envelope. No extensions/activity summaries are included. Result constructors are trusted assembly,
not untrusted decoders; alternate `DetailReader` implementations must preserve these limits and permissions.

The reader loads at most one bounded stored body per returned item, not all matching bodies. It scans permitted
safe detail metadata in **100-row keyset batches**, retaining only the requested page; totals include all permitted
matching message rows. Memory and output are finite; work grows with retained evidence. Consumers own database
sizing, query admission and timeouts. These are not constant-cost queries or arbitrary large-content streaming.

## Capture, disappearance and live ordering

Reference capture retains content type, original complete/partial/absent/unavailable/expired information and output
stream/index/absolute offset coordinates. Complete/partial capture is `retained` only when its content row is
present; otherwise availability is `missing` with **unknown reason**, not automatically expired/never captured.
Explicit absent/unavailable/expired markers survive missing rows. A null-body absence marker has a null chunk;
a captured **empty string** has a real chunk with empty data, zero total/bytes and null continuation. Corrupt body
representations and storage failures are errors, not absent or empty success. Metadata-only `retained` describes
row presence, not body validation or permission.

An output segment can be partial, discontinuous or unavailable. Null `next_offset` proves only the end of that
retained segment/body, **not complete process output or a full transcript**. Supplied coordinates do not establish
continuity between segments. Neither surrounding success nor absent output proves an unobserved result.

Message order defaults to immutable server recording time descending, then **full observation UUID COMB descending**.
Selection fixes one producer, so no cross-producer tie-break is needed. ASC reverses both fields. Canonical UTC
recording text retains six server microseconds on SQLite/MySQL/PostgreSQL. Occurrence text separately retains
its supplied zero-to-six fractional digits; late arrivals order by receipt, not occurrence. UUID suffix ties are
deterministic, never causal/commit order, complete chronology or a global checkpoint.

Each request independently supplies selection and current decisions. Separate subject, metadata-batch and body
statements are live, not a response-wide snapshot. Concurrent removal can change a response's total/availability;
allowed body reads recheck content-row presence at the actual body read. Live offset pages can repeat/skip rows
after insertions/removal. A prior result or continuation grants no frozen selection, remembered search, permission
cache or retention lease. Deleted content is not kept alive to complete a browsing sequence. Earlier returned
values are caller-owned historical data, not a promise of future access. The separate
[removal Command](execution-detail-removal.md#live-reads-and-recording-retries) shares recording's serialization
boundary and has integrated real-database read/retry evidence; protected-read tests also retain owned storage fixtures.

Reads issue no business writes, Commands or domain events, begin no transaction and change no isolation settings.
`ExecutionReadFailed` uses the existing fixed public message. Causes/traces are private diagnostic context and may
include captured data; never expose them publicly or log raw Queries/results as safe metadata. Consumer capture
selection and pre-submission redaction remain mandatory; structure validation is not a universal secret detector.

## Composition and compatibility

Compose a consumer implementation of `DetailReadAdmission` separately from safe admission, then register both
handlers on the ordinary Query router:

```php
use Fight\Common\Adapter\Mcp\Execution\Dbal\DbalDetailReader;
use Fight\Common\Application\Mcp\Execution\Detail\QueryHandler\GetCapturedDetailHandler;
use Fight\Common\Application\Mcp\Execution\Detail\QueryHandler\ListCapturedMessagesHandler;
use Fight\Common\Domain\Mcp\Detail\DetailSelection;
use Fight\Common\Domain\Mcp\Detail\Query\GetCapturedDetail;
use Fight\Common\Domain\Mcp\Detail\Query\ListCapturedMessages;

$protectedReader = new DbalDetailReader($connection);
$router->registerHandler(GetCapturedDetail::class, new GetCapturedDetailHandler($protectedAdmission, $protectedReader));
$router->registerHandler(ListCapturedMessages::class, new ListCapturedMessagesHandler($protectedAdmission, $protectedReader));
$selection = DetailSelection::create('project', 'session', 'phase', 'producer', $activityUuid);
$page = $queries->fetch(new ListCapturedMessages($selection, page: 1, perPage: 10));
$detail = $queries->fetch(new GetCapturedDetail($selection, $detailId, $kind, offset: 0, length: 4096));
```

TASK-00153 adds **12 opt-in public declarations**: `DetailSelection`, both Queries, both handlers, `DetailAccess`,
`DetailReadAdmission`, `DetailReader`, `ContentChunk`, `CapturedDetail`, `MessagePage` and `DbalDetailReader`.
Only the two ports promise consumer implementation; all concrete additions are final. Selection/access/chunk
factories own construction; declared Query/handler/result/adapter constructors and public methods are callable.
The compatibility manifest classifies each addition and the inventory delta. There are no schema/index changes,
new dependencies, migrations, aliases, historical codecs or removals. Existing tagged CQRS, recording, safe-read,
DBAL 4.4+ and Pagination contracts remain unchanged. Execution remains provisional until tagged; compatibility is
against actual releases, not development snapshots. Existing release-wide manifest/operation-fingerprint follow-ups
remain release-owned; this is not certification, dashboard or deployed-consumer qualification. The separate
protected MCP presentation supplies package-owned guarded HTTP evidence, not actual consumer-client acceptance.
