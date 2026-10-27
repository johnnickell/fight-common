# Authorized MCP Prompts

`Application\Mcp\Prompt` supplies an explicit opt-in, read-only `prompts/list` and `prompts/get` capability for
MCP `2026-07-28`. It uses the existing registry, semantic responder and guarded HTTP endpoint; it creates no route,
principal, token issuer, database, template engine or connection session. Package tests prove Common behavior,
not Pi, browser or consumer deployment qualification.

## Composition and public API

Implement `McpPrompt::info(): McpPromptInfo` and `get(array $arguments): McpPromptOutput` for each named Prompt.
`info()` supplies immutable metadata only. Registration calls it once, snapshots that definition and rejects
conflicting ownership, including concealed entries. Registration and listing never call `get()`.

```php
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Prompt\McpPrompt;
use Fight\Common\Application\Mcp\Prompt\McpPromptDiscovery;
use Fight\Common\Application\Mcp\Prompt\McpPromptInfo;
use Fight\Common\Application\Mcp\Prompt\McpPromptOutput;

$review = new class implements McpPrompt {
    public function info(): McpPromptInfo
    {
        return McpPromptInfo::fromArray([
            'name' => 'review.task',
            'description' => 'Review one task using the linked procedure',
            'arguments' => [['name' => 'id', 'required' => true]]
        ]);
    }

    public function get(array $arguments): McpPromptOutput
    {
        return McpPromptOutput::fromArray(['messages' => [
            ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Review task '.$arguments['id']]],
            ['role' => 'assistant', 'content' => [
                'type' => 'resource_link', 'uri' => 'skill://catalog/v1/review/SKILL.md', 'name' => 'Review procedure'
            ]]
        ]]);
    }
};

// Consumer-owned request-scoped McpPromptAvailability; obtain identity from trusted authentication, not arguments.
$prompts = new McpPromptDiscovery([$review], $currentPromptAccess, $dedicatedCursorSecret, 'consumer-review');
$registry = new McpCapabilityRegistry(new McpServerInfo('Consumer', '1'), [$prompts]);
```

Pass this registry to the existing [guarded HTTP composition](components/mcp/index.md#guarded-http).
Tools, Resources and Skills may be explicitly registered alongside it, but none is required by Prompts. Common
advertises `prompts: {listChanged: false}` only when composed. Explicit optional
[`McpSubscriptions(..., prompts: true)` composition](mcp-subscriptions.md#optional-prompt-catalog-signals) derives
`listChanged: true` only with the actual serving family and guarded live path. It authorizes catalog signals
separately and never calls content providers; ordinary list/get has no subscription dependency.
There is no Completion, multimedia, interaction,
subscription or asynchronous Tasks claim. No new framework wiring is needed. Continue to supply authentication,
Origin policy, invocation guard and redaction-aware diagnostics. `prompts/get` requires `Mcp-Name` matching its
body `name`, plus the normal protocol-version/method mirrors. Client `_meta` and arguments carry no authority.

`McpPromptAvailability::isAvailable(McpPromptInfo): bool` is called afresh before listing disclosure and independently
before direct retrieval. A caller need not list first. A previous definition, cache hint, link or cursor never
permits `get()`. Consumer implementations are trusted application code, not sandboxed templates. Static instructions
need no invented business Query. When content needs application data, inject `QueryBus` and call `fetch()` with the
shared application Query; its handler retains independent current business authorization. Do not introduce adapter
SQL, duplicate permission policy, Commands or domain events just to serve instructions.

## Definitions and arguments

`McpPromptInfo::fromArray()` accepts exactly `name`, optional string `title`/`description`, and optional `arguments`.
Each argument accepts exactly `name`, optional string `title`/`description` and optional Boolean `required` (default
false). Names are case-sensitive, unnormalized, 1–128 ASCII bytes matching `[A-Za-z][A-Za-z0-9_.-]*`. Argument names
must be unique. Unsupported metadata (including icons and arbitrary `_meta`) rejects, not silently disappears.
All strings must be valid Unicode. Empty descriptive strings are retained.

`prompts/get` accepts only `name` and optional object `arguments`. Omission means an empty object, not a remembered
argument set. All supplied values must be strings. Declared optional absence and empty strings remain distinct;
required arguments must be present but may be empty. Missing required, undeclared, wrong-type or oversized values
fail **before** the selected provider runs. Common does not coerce values or interpolate templates. The provider
receives only the supplied validated `array<string, string>`; `McpPromptInfo::accepts(StrictJson)` exposes the same
predicate without granting authority. Unsupported interaction/request state is rejected as an unknown parameter.

## Complete messages and links

`McpPromptOutput::fromArray()` accepts only `messages` and optional string `description`. Messages are a complete
ordered list; an empty list is a legitimate complete result. Each message contains exactly `role` and one `content`
object. Roles are only `user` or `assistant`; content is either:

- `{"type":"text","text":"..."}` with original valid UTF-8 bytes, including CRLF and empty text;
- `{"type":"resource_link", ...}` with existing `McpResourceInfo` metadata validation and limits, including an
  absolute URI, string name and supported optional Resource fields. The complete link metadata must itself be
  safe for this recipient. No metadata is fetched from the target.

System/developer roles, embedded Resource/document bodies, image/audio blocks, arbitrary message/content fields
and malformed Resource metadata reject the entire result. Nothing is silently dropped, truncated, or relabeled a
successful empty result. `toArray()` on Info/Output returns immutable nested `StrictJson` object nodes, not mutable
caller-owned objects; JSON serialization preserves object/list distinctions.

A successful get returns `resultType: complete`, the complete messages/description, private zero-TTL cache hints,
and the responder's central `_meta.io.modelcontextprotocol/serverInfo`. Text is instruction content, not permission,
Agent identity, persona, Workflow authority or independent acceptance. Hosts own insertion, approval and model use.
Links perform no Resource/network/filesystem read, Skill loading/activation, Tool/model execution, Command or event.
A later `resources/read` reauthorizes independently, including denial after successful Prompt retrieval. Repeated
retrieval promises neither deterministic content from arbitrary consumer code nor deterministic model behavior.

## Stateless catalog pages and cache hints

`prompts/list` accepts only optional string `cursor`. Omission or `""` starts/restarts from the beginning. Current
availability filters the snapshot before bytewise canonical-name ordering, hashing and count pagination. Only
absence of `nextCursor` means completion. An empty catalog is an empty complete page with no continuation.

The 48-character URL-safe Base64 token is a four-byte offset plus HMAC-SHA256. It binds the **visible** complete
metadata, endpoint scope, page size, result budget and response budget with a versioned Prompt-specific purpose.
It encodes no hidden catalog, principal or authority. Separate serving processes with the same composition/key can
continue without connection history. Changes only to concealed entries do not affect it; visible metadata/access
changes, foreign scope/key/budgets, tampering and invalid offsets reject safely. On rejection discard the cursor
and restart with no cursor. There is no permission cache or frozen browsing session. Consumer availability must
be stable within each call; Common cannot make concurrent policy changes atomic with arbitrary provider execution.

Both list and get always emit `ttlMs: 0` and `cacheScope: private`; this initial API has no cache override. Hints are
not a permission grant and do not authorize cross-caller reuse. Consumers own any outer caching policy.

## Finite budgets

All byte limits use PHP's actual byte lengths, not characters or declared Content-Length. Encoded limits use the
same default `JSON_THROW_ON_ERROR` encoding as the response, including Unicode/control/quote/backslash escaping.

| Boundary | Limit |
|---|---|
| Definition | 16,384 encoded bytes; at most 32 argument declarations; JSON depth setting 8 |
| Argument input | At most 32 supplied fields; each value at most 16,384 raw UTF-8 bytes; complete encoded argument object at most 65,536 bytes |
| Output | At most 128 messages; each text at most 65,536 raw bytes; complete encoded output (including description/links) at most 262,144 bytes; JSON depth setting 40 |
| Resource link | Existing default `McpResourceInfo` bounds: URI 4,096 raw bytes, complete metadata 16,384 encoded bytes, depth setting 32 |
| Cursor configuration | Secret 32–4,096 bytes; nonempty scope at most 128 bytes; use a dedicated secret, never a credential/access token |

`McpPromptLimits` supplies finite consumer-selected serving bounds:

| Constructor field | Default | Supported range |
|---|---:|---:|
| `maxPrompts` | 1,000 | 1–10,000 (an empty supplied catalog is allowed) |
| `maxCatalogBytes` | 1,048,576 | 1–16,777,216 |
| `pageSize` | 100 | 1–1,000 |
| `maxResultBytes` | 1,048,576 | 1–16,777,216 |
| `maxResponseBytes` | 1,048,576 | 1–16,777,216 |

Catalog bytes sum complete encoded definitions, including concealed entries. Registration validates the entire
bounded catalog; listing materializes only this bounded metadata, never bodies. Each page is atomic and count-based,
not dynamically byte-packed. An entry/page that cannot fit its serving budget fails instead of returning a partial
entry, an empty looping page, or a misleading continuation. Consumers must select budgets appropriate to their page
size and metadata; byte overflow is an explicit failure, not successful completion.

Both list/get reuse `McpResult::boundedComplete()` and its metadata-retained budget. `McpMetadataAwareCapability`
receives the current responder's actual metadata on every call, including when one capability is shared by multiple
responders. `withResponseLimit()` additionally guards the **actual complete encoded JSON-RPC success response** at
`McpJsonResponse`, including request-ID escaping, central server metadata, resultType, wrappers, commas and all other
fields. Raw output and result-only bounds do not replace this envelope check. Direct metadata-free `handle()` cannot
qualify an enclosing response; use the responder/guarded HTTP boundary. Existing unbounded capabilities are unchanged.
Safe error envelopes use existing protocol behavior and are not subject to the success budget (which may be too
small even for an error). Existing bounded HTTP input/depth limits, upstream buffering/admission/timeouts and trusted
consumer source acquisition remain separate; validation cannot undo allocations or sandbox arbitrary provider code.

## Safe failures and evidence

- Invalid outer parameters, declared-argument failures, invalid/stale cursors and unknown/denied names share fixed
  `-32602`, `Invalid params.`, without argument declarations, hidden definitions or private diagnostic data.
- Provider/storage/availability failures and invalid/oversized output/result/response use fixed `-32603`,
  `Internal error.`, diagnosed once through the existing redaction-aware boundary. Consumer providers/policy cannot
  inject their own public `McpProtocolException`; Common wraps it as internal failure. Failure is never empty success.
- Direct capability calls propagate errors; `McpResponder::respond()` or guarded HTTP owns sanitization. Registration
  errors are composition failures. Existing authentication, mirror, Origin, invocation and parse failures stay intact.

`McpPromptValuesTest` and `McpPromptDiscoveryTest` cover definitions, argument and output boundaries, lazy calls,
full-catalog validation, deterministic multi-page termination, stale/tampered/foreign cursors, safe failure ownership,
and exact independent result/envelope budgets. `McpPromptJourneyTest` proves actual authenticated Slim → package
PSR guarded endpoint → selected provider → routed `RoutingQueryBus`/`InMemoryQueryRouter` → Query handler, plus static
content, complete messages, changed Prompt/Query/Resource access, independent later Skill Resource reads, coexistence
and actual guarded-HTTP encoded-response boundaries. Synthetic fixtures do not claim database or client qualification.

## Compatibility and public surface

TASK-00157 adds six public declarations, all absent from tagged releases through `v1.4.0`:

| Declaration | Callable | Constructible | Extensible | Implementable |
|---|---|---|---|---|
| `McpPrompt` | yes | no | no | yes |
| `McpPromptAvailability` | yes | no | no | yes |
| `McpPromptInfo` | yes | named factory | no (final) | no |
| `McpPromptOutput` | yes | named factory | no (final) | no |
| `McpPromptLimits` | no methods beyond constructor | yes | no (final) | no |
| `McpPromptDiscovery` | yes | yes | no (final) | no |

These are additive opt-in package promises, classified in `compatibility/manifest.json`; they remain provisional
until tagged, not backward-compatibility obligations to earlier development snapshots. No existing public signature,
Tools/Resources/Skills behavior, Composer dependency, schema, persistence format or endpoint is changed. Existing
bounded-result/actual-central-metadata/final-response seams are reused without shared production changes. No release
version or consumer deployment qualification is implied. Optional catalog-change delivery is separately documented
and qualified under [live subscriptions](mcp-subscriptions.md); it does not change these serving interfaces or grant
later retrieval access.
