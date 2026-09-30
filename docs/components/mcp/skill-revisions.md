# Immutable Skill revisions as Resources

Common can validate a complete static Skill revision and serve its files through the existing
[guarded Resources endpoint](index.md#exact-authorized-resource-reads). This is **Resource support only**:
`skills/list`, `skills/get`, `io.modelcontextprotocol/skills` advertisement and extension composition are not yet
provided. Do not advertise the extension yourself without implementing its complete contract. Directory RPC,
subscriptions, rendering, execution, catalog storage and host activation are not provided.

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
(`uri`, `digest`, `size` per member). This is neutral metadata suitable for later Skills discovery, not a current
wire method. `resources()` returns descriptors in bytewise URI order without reading/re-hashing content;
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
file bytes. Ordinary quoted keys, merge-looking **values**, comments and literal/folded scalar content remain data. Quote date-like values when they should be strings; do not
rely on implicit timestamp conversion. No includes, PHP objects or constants are executed. Alternative parser
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
authority, not try encoded paths; stale Resource cursors restart the listing. Skills list/get pagination and
extension prerequisite validation remain the next slice, not a hidden promise of this provider.

## Evidence boundary

Owned direct tests cover structural/frontmatter rejection, bounds, exact 512-member/16-MiB snapshots, original-byte
hashing, retained revisions and changed availability. The real guarded-handler journey covers Resource coexistence,
selective root/nested reads, binary/empty content, safe errors, authentication/mirrors/Origin/guard rejection, and
no Skills advertisement. Official Resource scenarios provide scenario-specific wire checks; they do not prove
Skills extension conformance, a real Pi client, production storage, cached-content safety or consumer permissions.

Authorities: MCP `2026-07-28`; the static Skills contract at
[`ext-skills@b0b3272`](https://github.com/modelcontextprotocol/ext-skills/blob/b0b3272f1d4c01a79c8171252c70b06dcada18bf/specification/stable/skills.mdx)
and the [Agent Skills format](https://agentskills.io/specification). No base-protocol upgrade or automatic upstream
format migration is implied.
