# Immutable Skill revisions as Resources

Common can validate a complete static Skill revision and serve its files through the existing
[guarded Resources endpoint](index.md#exact-authorized-resource-reads). Resource-only compositions remain supported.
Explicitly compose `McpSkillDiscovery` for complete `skills/list`, exact `skills/get` and
`io.modelcontextprotocol/skills` advertisement. Directory RPC, subscriptions, rendering, execution, catalog storage
and host activation are not provided.

## Snapshot and integrity contract

**Contract: `fight-common.behavior.mcp-skill-revisions`**

`Application\Mcp\Skill\McpSkillFile::fromBytes()` accepts an explicit decoded relative path and an owned byte
string. It never follows a URL, opens a filesystem path or executes a script. Text must already be valid UTF-8;
use `text: false` for binary files. Optional `expectedSize` and `expectedDigest` publisher assertions must match
exactly. Digests use `sha256:` followed by 64 lowercase hexadecimal characters over the **original raw bytes**,
not JSON-escaped text or Base64. Empty files are supported. No Unicode or line-ending normalization occurs.

`McpSkillRevision::fromFiles()` accepts a root `SKILL.md` URI, the complete iterable of these immutable files,
and a frontmatter parser. It derives the manifest from those same bytes: no second independently mutable
manifest, file callback or storage lookup can drift from the snapshot. Every supplied file appears exactly once,
including the root, nested references, ordinary templates, scripts, assets and nested `SKILL.md` files. Duplicates,
missing root files, file/directory collisions, invalid paths, unsupported frontmatter and exceeded budgets fail
construction with `DomainException`; there is no partial accepted revision. Construction is not publication.

The root URI must be an absolute hierarchical URI ending in `/<frontmatter-name>/SKILL.md`. Supported forms include
`skill://catalog/revision-42/work/SKILL.md`, `https://example.test/revision-42/work/SKILL.md` and
`skill:/revision-42/work/SKILL.md`. The implementation accepts lowercase schemes, optional plain ASCII host/port,
no credentials/query/fragment, and canonically percent-encoded path segments. This deliberately rejects aliases,
encoded separators, dot segments and repeated-decoding escapes. Put revision identity **before** the name.
Authorities are opaque identifiers here, not network destinations. Relative member names preserve nested paths
and Unicode; each segment is encoded once. `%`, backslash, colon, `?`, `#`, controls, empty/dot segments and paths
over 2 KiB are rejected. Incoming reads use exact registered URIs, never decoded or normalized alternatives.

`entry()` returns immutable `StrictJson` containing `uri`, complete `frontmatter` and complete `resources`
(`uri`, `digest`, `size` per member). This is the same complete metadata exposed by Skills list/get when composed. `resources()` returns descriptors in bytewise URI order without reading/re-hashing content;
`file($uri)` returns only the exact immutable member, or null. These composition APIs are not authorization APIs.

Consumers own bounded acquisition before supplying strings, completeness of their source export, atomic catalog
publication, permanent association of bytes with revision addresses, retention and current-revision selection.
Common cannot detect a file omitted from the consumer's declared source set or remember URI reuse across process
restarts. Publish changed bytes at **new addresses**. Retain old snapshot objects to serve historical URIs; never
reconstruct an old address from a mutable “latest” callback. Within a composed provider, duplicate URI ownership
is rejected even across revisions. Memory retains the supplied bounded bytes; this is not a disk-backed store.

## Safe frontmatter parsing

`McpSkillFrontmatterParser` is an Application-owned contract. The optional
`Adapter\Mcp\Symfony\SymfonyMcpSkillFrontmatterParser` requires **`symfony/yaml:^8.1`**, explicitly installed by the
consumer; it is not a mandatory runtime dependency of unrelated Common capabilities. YAML 8.1 supplies parser
nesting limits and alias rejection before expansion. Domain/Application never depend on the concrete parser.

The root begins with `---` on its own line and has a closing `---` line (LF and CRLF supported). Only its bounded
frontmatter is parsed; the original complete file is served unchanged. The parser retains JSON object/list
identity and every authored JSON-representable field in accepted input, including unknown nested values. Duplicate keys
at every depth (including prior null values in flow mappings), aliases, merge keys, executable/custom tags,
non-finite numbers, unsupported objects/dates and excessive raw bytes, encoded bytes or nesting reject safely.
The Symfony adapter rejects any mapping key resolving to `<<`, including quoted, escaped and tag-decoded
spellings (such as `!!str '<<'` or `!!binary PDw=`): Symfony otherwise interprets even these literal-looking keys as merges and can silently
relocate fields or overwrite duplicates. This check precedes conversion; it neither expands merges nor rewrites
file bytes. Flow mappings accept single-token plain keys or single-line quoted keys, followed by a same-line
colon (optional horizontal spacing). Quote multiword flow keys: `{"long key": value}` preserves the full name;
`{long key: value}` rejects instead of accepting Symfony's shortened name. Comments/newlines between a key and
its colon, tagged/anchored flow keys and implicit mappings inside flow sequences reject before conversion.
Use explicit `{key: value}` mappings inside sequences. Flow values require commas between entries; multiline
plain flow values are unsupported. Quoted values and block literal/folded scalars can represent multiline text.
Ordinary quoted keys, merge-looking **values**, comments between entries or after values, multiword block keys
and literal/folded scalar content remain data. These bounded syntax restrictions do not permit silent field loss.
Quote date-like values when they should be strings; do not rely on implicit timestamp conversion. No includes, PHP objects or constants are executed. Alternative parser
implementations must satisfy the same safe, lossless contract; snapshot validation additionally checks their
returned JSON shape, depth, size and required fields.

The Agent Skills format requires a 1–64-character lowercase alphanumeric/hyphen name with no leading, trailing
or consecutive hyphens, matching the URI parent, and a nonblank description of at most 1,024 characters. Unicode
lowercase/uncased letters and decimal digits are supported. Optional `license` and `allowed-tools` are strings;
`compatibility` is a nonblank string of at most 500 characters; `metadata` is a string-valued mapping. Unknown
fields are not discarded. Preserving `allowed-tools` grants **no authority to execute anything**.

## Whole-Skill authorization and composition

**Contract: `fight-common.behavior.mcp-skill-resource-authorization`**

`McpSkillResources` implements the optional `McpProtectedResourceProvider` subtype. Its injected
`McpSkillAvailability` receives only the complete immutable entry, not file bytes, credentials, permission names
or a principal model. The decision covers the entire revision. Consumers own caller resolution and policy;
make the collaborator request-aware and keep its answer coherent within a request.

The existing `McpResourceDiscovery` remains the **only Resource method owner**. It enforces a protected provider's
decision **in addition to** its general Resource availability, so a permissive general policy cannot accidentally
bypass whole-Skill authorization. Internal enumeration/lookup retain concealed descriptors for duplicate ownership
and budget validation; public listing filters before pagination, and direct/historical reads check current
availability before `open()`. `McpSkillResources::open()` also rechecks before allocating a stream for direct
provider callers. Policy exceptions or inconsistent decisions during one operation fail closed; no cached grant
or partial Skill manifest is created. Applying an additional general policy cannot alter the stored entry.

```php
use Fight\Common\Adapter\Mcp\Symfony\SymfonyMcpSkillFrontmatterParser;
use Fight\Common\Application\Mcp\Resource\McpResourceDiscovery;
use Fight\Common\Application\Mcp\Resource\McpResourceLimits;
use Fight\Common\Application\Mcp\Resource\McpResourceReadLimits;
use Fight\Common\Application\Mcp\Skill\McpSkillFile;
use Fight\Common\Application\Mcp\Skill\McpSkillResources;
use Fight\Common\Application\Mcp\Skill\McpSkillRevision;

$metadataLimits = new McpResourceLimits();
$readLimits = new McpResourceReadLimits();
$revision = McpSkillRevision::fromFiles(
    'skill://catalog/revision-42/work/SKILL.md',
    [
        McpSkillFile::fromBytes('SKILL.md', "---\nname: work\ndescription: Read this revision\n---\n"),
        McpSkillFile::fromBytes('references/guide.md', "Exact Unicode 雪\r\n"),
        McpSkillFile::fromBytes('assets/pixel', "\0\xff", 'application/octet-stream', false)
    ],
    new SymfonyMcpSkillFrontmatterParser(),
    resourceLimits: $metadataLimits,
    readLimits: $readLimits
);
$skillFiles = new McpSkillResources([$revision], $currentWholeSkillDecision, $psrStreamFactory);
$resources = new McpResourceDiscovery(
    [$skillFiles, ...$otherReadableProviders],
    $currentResourceDecision,
    $cursorSecret,
    'consumer-catalog',
    $metadataLimits,
    readLimits: $readLimits
);
// Register $resources once in the existing McpCapabilityRegistry / guarded McpRequestHandler.
```

A client can read `SKILL.md` and then just `references/guide.md`. Only each selected file gets a response stream;
listing does not reopen all content. Binary uses Base64, empty files remain one content item, templates/scripts
are bytes only, and nested `SKILL.md` is neither parsed nor activated. Optional canonical digests on
`McpResourceContent::text()`/`binary()` verify consumed raw stream bytes before success; Skill reads always use
this protection, so even same-sized changed stream output cannot succeed. Returned streams close on success and
failure. Existing content factories without a digest keep their previous behavior.

Unknown and denied exact reads share central `-32602` with no protected URI reflection. Unexpected policy,
provider or integrity failures use sanitized `-32603` and consumer diagnostics, never Tool `isError`. Origin,
Resource URI mirrors, consumer authentication and invocation guards remain at the existing HTTP boundary.
Default zero-TTL/private hints convey no read authority; public caching remains an explicit consumer assertion
that the entire exposed content is caller-independent. Previously delivered knowledge cannot be revoked.

## Limits and recovery

| Bound | Default | Valid override |
| --- | --- | --- |
| Files per revision | 512 | 1–512 |
| Aggregate original bytes | 16 MiB | 1 byte–16 MiB |
| YAML raw and encoded frontmatter bytes | 64 KiB each | 1 byte–1 MiB |
| YAML/JSON nesting | 32 | 1–64 |
| Complete encoded entry | 4 MiB | At least the frontmatter budget, at most 16 MiB |
| Provider catalog files, including hidden/historical | 10,000 | 1–1,000,000 |

The 512-file/16-MiB defaults match the pinned static host interoperability envelope. Deployments can impose smaller
publication quotas. Individual files must also fit the **existing Resource limits**: defaults are 1 MiB raw and
8 MiB encoded per read, with validated overrides up to 8 MiB raw/64 MiB encoded. A revision may total 16 MiB across
several files; this does not promise a single 16-MiB Resource read. Pass the same Resource budgets during snapshot
construction and capability composition. Resource dispatch independently checks its actual current descriptors,
worst-case text/Base64 expansion, cache wrapper and central server metadata before advertisement/opening, then
checks the actual encoded result again. Nothing truncates or splits a complete entry to satisfy a bound.

Repair invalid publication input and construct a new complete snapshot, or tune validated budgets within these
ceilings. Use new revision addresses for changed content. Unknown/denied clients must obtain legitimate current
authority, not try encoded paths; stale Resource cursors restart the listing. Resource-only composition does not
advertise partial Skills support; add both methods together through the capability below.

## Complete Skills discovery and exact get

**Contract: `fight-common.behavior.mcp-skill-discovery`**

One `McpSkillDiscovery` owns `skills/list` and `skills/get` together. It consumes the **same** `McpSkillResources`
instance already serving revision files through one read-enabled `McpResourceDiscovery`. Construction rejects a
missing/read-disabled provider composition and inconsistent budgets. Registry construction rejects substituting a
different Resource capability, missing methods or unsupported optional feature claims. Independent Resources still
works without Skills. No Tool registration, new endpoint, route or framework adapter is required.

Continue the preceding composition:

```php
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscovery;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscoveryLimits;

$skills = new McpSkillDiscovery(
    $skillFiles,
    $resources,
    $dedicatedSkillsCursorSecret, // Stable secret of 32–4096 bytes, not an access credential.
    'consumer-skills',            // Nonempty catalog scope, at most 128 bytes.
    new McpSkillDiscoveryLimits(pageSize: 25)
);
$registry = new McpCapabilityRegistry(new McpServerInfo('Consumer', '1'), [$resources, $skills]);
// Supply this registry to the existing guarded McpRequestHandler; retain all HTTP safeguards.
```

1. `server/discover` advertises `extensions: {"io.modelcontextprotocol/skills": {}}` alongside `resources`.
2. Send `skills/list` with omitted parameters or an optional cursor. Each returned `skills[]` entry contains all
   authored frontmatter and the entire static manifest. No file reads are needed to complete an entry.
3. Send `skills/get` with `{"uri":"skill://catalog/revision-42/work/SKILL.md"}`. The `skill` result has exactly the
   list-entry shape, with no cursor. Get works **before any listing** and for a URI absent from a page. File URIs,
   encoded aliases, unknown revisions and unavailable roots do not become alternate lookup mechanisms.
4. Read only that root through `resources/read`, then a selected nested file when needed. Manifest sizes and
   digests describe exactly these bytes. Reading a template, script or nested Skill does not execute/activate it.

All requests still carry mandatory MCP `_meta`, protocol and method mirrors. `skills/get` has **no `Mcp-Name`
requirement**; `resources/read` retains its URI mirror. Authentication remains consumer composition, with Origin
and invocation guard checks before dispatch. No protected content is opened by discovery/get. Construction checks
neutral metadata/ownership before serving; unlike standalone Resource discovery it enumerates the shared catalog
at composition. Do not perform provider content reads during metadata enumeration.

Availability is reevaluated for every list/get and historical file read. The injected `McpSkillAvailability` owns
the whole revision; additionally, discovery/get checks the shared general Resource policy for every member. If
that policy conceals any member, the **whole entry** is concealed, not redacted. Consumers should normally place
Skill permission in the whole-Skill collaborator and let the general policy permit its files. Resource reads
still apply both existing decisions individually; use whole-Skill denial to revoke all file access together.
Policy answers must remain coherent within a request; concurrent changes across requests require fresh checks.

Provider `entries()` and `findEntry($uri)` expose neutral immutable metadata to composition code, not authorized
wire results. The capability applies current decisions before disclosure, hashing or pagination. Same-name entries
are not collapsed: exact root URI is identity. Stored snapshots retain original bytes and no routine list/get
reopens or rehashes file content. Consumer catalogs remain responsible for publication, retention and bounded
acquisition; replacing an immutable provider is an explicit composition operation, not a Common catalog write.

List/get use `resultType: complete`, `ttlMs: 0`, `cacheScope: private`. Constructor overrides accept a safe integer
TTL (0–9,007,199,254,740,991) and `private`/`public`. Public is a consumer assertion of caller-independent visibility,
not an inference from immutable content. Private reuse must remain within its authorization context; hints, URIs,
manifest hashes and cursors never grant authority. Unknown/denied get is the same sanitized `-32602`. Unexpected
provider/policy/budget failures use `-32603` with consumer diagnostics and no partial entry or Tool `isError`.

### Atomic pages, bounds and recovery

**Contract: `fight-common.behavior.mcp-skill-discovery-bounds`**

`McpSkillDiscoveryLimits` supplies finite validated bounds:

| Bound | Default | Valid override |
| --- | --- | --- |
| Maximum entries per page | 100 | 1–1,000 |
| Catalog entries including concealed revisions | 10,000 | 1–1,000,000 |
| Complete encoded entry | 4 MiB | 1 byte–16 MiB |
| Complete encoded result | 8 MiB | At least entry budget + 256 bytes, at most 64 MiB |
| Request/served root URI | 8 KiB | 1 byte–64 KiB |

The provider's file budget and snapshot/Resource limits remain independently enforced. Actual complete entries
must fit **both** list and get, including cache fields, a possible 48-character continuation, and the current
responder's actual central metadata. All entries, including concealed entries, are checked. Shared Resource
ownership, scan/descriptor budgets and worst-case encoded read budgets are checked without opening content at
composition and on each operation. An oversized server identity or inconsistent provider output cannot yield an
entry whose files the configured reader cannot represent. `handleWithMetadata` is the public optional
`McpMetadataAwareCapability` method for receiving the responder's actual central metadata per request;
`resourceDiscovery` and the Resource `serves`/`permits`/`validateCatalog` methods remain internal coordination
seams. Neither the optional metadata method nor those internal seams are consumer authorization APIs.

A page stops at its count **or byte** bound; entries remain atomic and no bytes/members are truncated. Results
retain an independent final encoded guard. Like Resource budgets, the result bound covers the JSON-encoded
`result`, not the outer JSON-RPC envelope. URI/cursor shape and length are bounded here; shared pre-decode HTTP
body limits remain TASK-00121, not a guarantee of this capability.

After current availability filtering, entries are ordered bytewise by exact root URI. A fixed 48-character opaque
cursor authenticates offset, endpoint/catalog scope, page-size/result budget and only the currently visible complete
entries. Concealed catalog changes do not change valid continuation. Byte-limited pages may contain fewer than the
configured count. Omitted/empty cursor restarts; **absence**, not truthiness, of `nextCursor` ends enumeration.
Malformed, forged, wrong-key/scope/budget or stale continuations return `-32602`: discard saved pages and restart,
without another human approval. Pagination is not a cross-request catalog snapshot or authorization cache.

## Evidence boundary

Owned direct tests cover structural/frontmatter rejection, bounds, exact 512-member/16-MiB snapshots, original-byte
hashing, retained revisions, current policy, atomic byte/count pages, safe failure and registry composition.
`McpSkillDiscoveryJourneyTest` drives the real guarded handler through discovery/list, direct get outside the first
page and selected root/nested reads, then changes availability and rejects every historical file URI. It preserves
unknown frontmatter, Unicode/CRLF, binary/empty files and consumer authentication/mirror/Origin/guard behavior.
The earlier Resource-only journey still proves that uncomposed Skills is not advertised.

Pinned official server Skills enumeration and manifest checks supplement this proof. Same-name revisions are
intentional adversarial fixtures, producing a name-uniqueness advisory. Existing Resource descriptors preserve
relative file names, omitted descriptions and caller-supplied MIME labels rather than rewriting TASK-00119's
metadata; the fixture's default text/plain root therefore produces three manifest SHOULD advisories (MIME, name,
description). Every applicable MUST check is exercised. Directory checks are genuinely not applicable because
`directoryRead` is not advertised. Wire checks cannot prove complete consumer source acquisition, arbitrary
provider immutability or safe client caches. No blanket conformance badge, Pi client qualification, production
storage/permissions, host origin trust, client no-prefetch/integrity enforcement or activation approval is claimed.
Tools coexistence/Resource-link acceptance and shared bounded ingress remain separately owned by TASK-00122/00121.

Authorities: MCP `2026-07-28`; the static Skills contract at
[`ext-skills@b0b3272`](https://github.com/modelcontextprotocol/ext-skills/blob/b0b3272f1d4c01a79c8171252c70b06dcada18bf/specification/stable/skills.mdx)
and the [Agent Skills format](https://agentskills.io/specification). No base-protocol upgrade or automatic upstream
format migration is implied.
