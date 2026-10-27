# Migrate released public-property reads to getters

The unreleased getter migration adds **50 typed, side-effect-free methods in 14 runtime classes**. Every method
has the same name as its corresponding property: change `$value->maxBytes` to `$value->maxBytes()`, for example.
There are no `get...` aliases or setters. Constructors, named arguments, factories, defaults and validation are
unchanged. The entire `Fight\Common\Standards` namespace is excluded.

## Deprecation and removal window

Direct reads of the properties below are deprecated **in documentation only**. They still work, remain public
and readonly, and emit no new runtime warning or notice. The actual first deprecating release is **unassigned**;
this page does not claim a release has shipped. When that release is assigned, record it here and in the release
notes. Keep the old interface supported for **at least one released minor before removal in a future major**.
A tag's existence alone does not establish that window. Removal or private conversion requires separately
authorized work; this migration does neither.

## Complete property-to-method map

Class paths are relative to `Fight\Common\`. In each row, **every** `$name` maps to `name()` with the same native
type and nullability. Array getters also retain the documented PHPStan element types.

| Class | Deprecated property → preferred method | Count |
|---|---|---:|
| `Adapter\Http\Error\SafeHttpFailure` | `$status` → `status()`; `$metadata` → `metadata()` | 2 |
| `Adapter\Http\Error\SafeHttpMetadata` | `$challenge` → `challenge()`; `$allow` → `allow()`; `$retryAfterSeconds` → `retryAfterSeconds()` | 3 |
| `Adapter\Http\Error\SafeHttpPresentation` | `$data` → `data()`; `$message` → `message()`; `$metadata` → `metadata()` | 3 |
| `Application\Auth\OAuth\OAuthResourceMetadata` | `$resource` → `resource()`; `$metadataUrl` → `metadataUrl()`; `$authorizationServers` → `authorizationServers()`; `$scopesSupported` → `scopesSupported()` | 4 |
| `Application\Auth\OAuth\OAuthResourceServer` | `$requirements` → `requirements()` | 1 |
| `Application\Auth\OAuth\OAuthScopeSet` | `$values` → `values()` | 1 |
| `Application\Auth\OAuth\OAuthTokenRequirements` | `$metadata` → `metadata()`; `$tokenType` → `tokenType()`; `$allowedAlgorithms` → `allowedAlgorithms()`; `$activeKeyIds` → `activeKeyIds()`; `$requiredClaimTypes` → `requiredClaimTypes()`; `$clockSkewSeconds` → `clockSkewSeconds()` | 6 |
| `Application\Auth\OAuth\OAuthValidatedClaims` | `$claims` → `claims()`; `$algorithm` → `algorithm()`; `$keyId` → `keyId()`; `$tokenType` → `tokenType()`; `$grantedScopes` → `grantedScopes()` | 5 |
| `Application\Mcp\McpRequestLimits` | `$maxBytes` → `maxBytes()`; `$maxDepth` → `maxDepth()` | 2 |
| `Application\Mcp\Resource\McpResourceLimits` | `$maxUriBytes` → `maxUriBytes()`; `$maxDescriptorBytes` → `maxDescriptorBytes()`; `$pageSize` → `pageSize()`; `$maxDescriptors` → `maxDescriptors()`; `$maxProviders` → `maxProviders()`; `$maxResultBytes` → `maxResultBytes()` | 6 |
| `Application\Mcp\Resource\McpResourceReadLimits` | `$maxContentBytes` → `maxContentBytes()`; `$maxResultBytes` → `maxResultBytes()` | 2 |
| `Application\Mcp\Skill\McpSkillDiscoveryLimits` | `$pageSize` → `pageSize()`; `$maxEntries` → `maxEntries()`; `$maxEntryBytes` → `maxEntryBytes()`; `$maxResultBytes` → `maxResultBytes()`; `$maxUriBytes` → `maxUriBytes()` | 5 |
| `Application\Mcp\Skill\McpSkillFile` | `$path` → `path()`; `$bytes` → `bytes()`; `$mimeType` → `mimeType()`; `$text` → `text()`; `$digest` → `digest()` | 5 |
| `Application\Mcp\Skill\McpSkillLimits` | `$maxFiles` → `maxFiles()`; `$maxTotalBytes` → `maxTotalBytes()`; `$maxFrontmatterBytes` → `maxFrontmatterBytes()`; `$maxDepth` → `maxDepth()`; `$maxEntryBytes` → `maxEntryBytes()` | 5 |

## Adoption and unchanged boundaries

```php
// Preferred read API; construction and named arguments have not changed.
$limits = new \Fight\Common\Application\Mcp\McpRequestLimits(maxBytes: 2097152, maxDepth: 128);
$byteBudget = $limits->maxBytes();
$depthBudget = $limits->maxDepth();

// Object getters retain immutable identity; they do not clone or reconstruct values.
$resource = $resourceServer->requirements()->metadata()->resource();
$scopes = $validatedClaims->grantedScopes()->values();
```

Getters return the existing storage, not duplicate state. Array results retain PHP value/copy-on-write semantics;
modifying a returned array, including nested arrays, cannot mutate the validated snapshot. Immutable object
results are the same instances as legacy reads. `text()` is the existing Boolean flag, not file content; use
`bytes()` for the original bytes, including binary or empty bytes. Getters perform no I/O, hashing, authorization,
validation, diagnostic logging or protocol dispatch.

Native PHP object/array serialization, public-field enumeration and protocol JSON shapes remain unchanged.
That compatibility is **not a new safe serialization API**: OAuth claims and generic object serialization are
not public presentation or logging data. Safe HTTP values still require explicitly consumer-projected public
data and valid status-specific metadata. OAuth validation, effective scope checks and claims-only handoff remain
mandatory; getter access establishes no authenticity or permission. MCP ingress, catalog, file, read and encoded
response budgets are unchanged.

Owned runtime callers and ordinary tests now use getters. Deliberate legacy reads remain in
`SafeHttpGetterCompatibilityTest`, `OAuthConfigurationTest`'s getter compatibility scenario,
`OAuthResourceServerTest`'s requirements compatibility scenario, and `McpGetterCompatibilityTest`.
These prove continued direct-read support, not preferred usage. Existing release consumer probes are retained,
not expanded into release-process tests. Provisional unreleased values and unrelated fixture fields are not
included in this migration.
