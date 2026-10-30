# Agent and MCP subsystem namespaces

All agent tracking and MCP-owned runtime capabilities have canonical homes under `Fight\Common\Domain\Mcp`,
`Fight\Common\Application\Mcp` and `Fight\Common\Adapter\Mcp`. This includes recording and inspecting execution,
retained notices, durable Task lifecycle, subscription tracking, protocol serving, Tools, Resources, Prompts,
Skills and the legacy AI-operation/webhook APIs. MCP is the subsystem boundary, not just the transport facade.
TASK-00177 established this map for the develop capabilities; durable Task-input additions follow the same
`Domain\Mcp\Task` and `Application\Mcp\Task` homes. See [durable Task input](task-input.md).
Complete [MCP Tasks serving](mcp-tasks.md) adds `McpTasks`, `McpTaskTool` and `McpTaskHandle` under
`Application\Mcp\Task`, preserving the released ordinary Tool and interactive resume signatures.
Ordinary stateless form elicitation and destructive confirmation remain unchanged.

Domain remains framework-free and organized by its actual concepts. Application coordinates through inward
contracts and the normal buses; adapters own persistence, cryptography, transport and native framework integration.
Grouping these capabilities does not require an MCP HTTP connection to use their shared Commands or Queries.
It does not turn a Task into a job executor, an observation into Workflow authority or a notice into authoritative state.

## Complete capability relocation map

Paths below omit the common `Fight\Common\` prefix. Each row covers its whole develop subtree, including values,
messages, events, repositories, policies, handlers, projections, exceptions and implementation helpers.

| Previous subtree | Canonical subtree |
| --- | --- |
| `Domain\Execution\Session` | `Domain\Mcp\Session` |
| `Domain\Execution\Phase` | `Domain\Mcp\Phase` |
| `Domain\Execution\Observation` | `Domain\Mcp\Observation` |
| `Domain\Execution\Activity` | `Domain\Mcp\Activity` |
| `Domain\Execution\Detail` | `Domain\Mcp\Detail` |
| `Domain\Execution\Usage` | `Domain\Mcp\Usage` |
| `Domain\Execution\Value` | `Domain\Mcp\Value` |
| `Domain\Notice` | `Domain\Mcp\Notice` |
| `Domain\Task` | `Domain\Mcp\Task` |
| `Domain\Subscription` | `Domain\Mcp\Subscription` |
| `Application\Execution` | `Application\Mcp\Execution` |
| `Application\Notice` | `Application\Mcp\Notice` |
| `Application\Task` | `Application\Mcp\Task` |
| `Adapter\Execution` | `Adapter\Mcp\Execution` |
| `Adapter\Notice` | `Adapter\Mcp\Notice` |
| `Adapter\Task` | `Adapter\Mcp\Task` |

Existing `Application\Mcp` branches remain in place. The moved execution/notices/Task Application classes merge
with their existing MCP Tool branches, rather than adding forwarding services around their use cases.
`Application\Mcp\Subscription` and `Adapter\Mcp\Subscription` were already grouped correctly. Their Domain
selection/Queries now join the same subsystem. Adapter tests and capability-specific test fixtures move with their
subjects; existing suite membership and independent-process worker behavior are retained.

For example, the caller changes its import, not the use case or bus pipeline:

```php
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;

$view = $queries->fetch(new GetExecution($projectId, $sessionId));
```

These tracking subtrees have not appeared in a tagged release through `v1.4.0`. Their provisional names change
directly: no development-snapshot aliases, deprecations or migrations are added. Message payload type identities
follow their new PHP namespaces, including the dotted `Fight.Common.Domain.Mcp...` representation. External MCP
method/Tool names, safe output schemas, stored table names, retention, current admission, transaction ownership,
post-commit ordering and documented failure/recovery guarantees are unchanged. A namespace move does not grant
access or guarantee delivery/execution. Existing persisted development snapshots are not a supported migration format.

## Released names and legacy AI operations

The [nine released HTTP adapter names](components/mcp/index.md#canonical-adapter-namespaces-and-1x-compatibility)
remain functional deprecated compatibility entry points throughout 1.x. Internal HTTP helpers have no public
old-name compatibility promise.

Three older AI APIs also receive canonical MCP homes while their released names remain functional:

| Released deprecated name | Canonical legacy name |
| --- | --- |
| `Domain\Auth\AiOperation` | `Domain\Mcp\Operation\AiOperation` |
| `Application\Auth\WebhookDispatcher` | `Application\Mcp\Operation\WebhookDispatcher` |
| `Adapter\Auth\Hmac\HmacWebhookDispatcher` | `Adapter\Mcp\Operation\Hmac\HmacWebhookDispatcher` |

These APIs were already deprecated in 1.2; the relocation does **not** revive them, postpone their existing
2.0 removal, register an MCP Tool or authorize `deploy`, migrations or another action. Both old and
canonical legacy declarations remain deprecated. Existing old-interface implementations still satisfy the new
parent port. The final old HMAC class remains an implementation of its released old port and delegates behavior
to the canonical compatibility parent; that parent's non-finality is not a consumer extension promise. The old
AI payload factories keep their old concrete return, validation, array/JSON and private serialized state.
No runtime deprecation notices are emitted. Consumers own endpoint selection, signing configuration and authority.

## General-purpose dependencies

Messaging, validation, value primitives, generic authentication/signing, OAuth resource protection, HTTP clients,
Socket/Mercure publishing and other general infrastructure keep their established namespaces. They are dependencies
of the agent subsystem, not agent-owned implementations. Only the explicit released compatibility declarations
remain as agent/MCP entry points outside `Mcp`; all canonical agent/MCP implementations are grouped inside it.
The old names are supported bridges, not competing owners or copies of the tracking model.

This organization changes neither optional dependency floors nor the layer matrix. Namespace/promise inventory,
existing product suites, real database/concurrency journeys and live HTTP/native-response behavior verify the move.
Package release certification and deployed consumer qualification remain separate authorized work.
