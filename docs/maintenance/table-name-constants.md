# Migrate public table-name constants to explicit selections

The four public schema `TABLES` constants below are **deprecated in PHPDoc** and targeted for removal in
**v2.0.0**. They remain readable with exactly the same array values, order and visibility; this change emits
no runtime deprecation warning and performs no database operation. The constants describe fixed defaults,
**not the names selected for a configured schema or runtime adapter**.

The first deprecating release is **unassigned**. New deprecations ship in a minor release, and the existing
contract must remain supported for at least one released minor before actual major-version removal. The
v2.0.0 target does not bypass that window or mean removal has already happened. This follow-up marks the
constants only; implementation of their future removal is separate work.

## Complete constant-to-selection map

All class paths below are relative to `Fight\Common\Adapter\Mcp\Persistence\`.
Each accessor returns a `DbalTableName`: use `name()` for its unquoted name, `quoted($platform)` for SQL,
and `schemaName()` when passing it to DBAL schema APIs. Share the actual selection with every participating
schema, adapter, reader and independent process rather than reconstructing it from a default constant.

| Deprecated constant | Existing selection and accessor order | Unchanged constant values |
|---|---|---|
| `Execution\Dbal\DbalObservationSchema::TABLES` | `DbalObservationTables`: `schemaTable()`, `sessionTable()`, `phaseTable()`, `observationTable()`, `contentTable()` | `fight_execution_schema`, `fight_execution_sessions`, `fight_execution_phases`, `fight_execution_observations`, `fight_execution_contents` |
| `Task\Dbal\DbalTaskSchema::TABLES` | `DbalTaskTables`: `stateTable()`, `taskTable()` | `fight_task_state`, `fight_tasks` |
| `Notice\Dbal\DbalNoticeSchema::TABLES` | `DbalNoticeTables`: `stateTable()`, `noticeTable()`, `relationshipTable()`, `comparisonTable()` | `fight_notice_state`, `fight_notices`, `fight_notice_relationships`, `fight_notice_comparisons` |
| `Subscription\Dbal\DbalSubscriptionSchema::TABLES` | `DbalSubscriptionTables`: `stateTable()`, `subscriptionTable()` | `fight_mcp_subscription_state`, `fight_mcp_subscriptions` |

For example, replace a default-only `DbalSubscriptionSchema::TABLES[1]` lookup with
`$subscriptionTables->subscriptionTable()->name()`, using the same `$subscriptionTables` already supplied to
installation, tracking and inspection. This observes the actual configured name. When the intent is expressly
to inspect defaults, construct the corresponding selection with no arguments and use those same accessors.
Do not add another public constant or assume a default name is the current selection.

## Unchanged behavior and inventory boundary

No constant is removed or renamed, and existing internal index-compatibility comparisons and default-control
tests remain unchanged. Schema versions, payloads, permissions, locking, retention and failure behavior are not
altered. Changing a selection still does not migrate, copy, rename or install storage at runtime.

The production inventory found **four constants containing thirteen physical table names**, with no additional
public scalar or implicit-public physical-table-name constant. Event Sourcing table names are supplied through
its existing selection objects, not public table-name constants. ORM `nonces` / `audit_entries` remain native
mapping metadata, and Doctrine `TYPE_NAME` constants identify types, not physical tables; neither is deprecated
by this change. Private/protected constants, columns, index/constraint names and test-only fixtures are outside
this public-constant deprecation.

See [execution storage composition](../execution-observations.md),
[Task storage](../durable-tasks.md#configurable-durable-storage),
[Notice storage](../safe-notices.md#explicit-physical-table-selection),
[subscription storage](../mcp-subscriptions.md#explicit-physical-table-selection), and the
[complete eight-table lifecycle composition](../lifecycle-storage.md).
