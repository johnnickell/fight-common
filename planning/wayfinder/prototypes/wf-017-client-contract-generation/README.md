# WF-017 client-contract generation prototype

> **PROTOTYPE — wipeable schema/type evidence, not a supported client package.**

## Question

Can one authoritative OpenAPI document generate the HTTP view types used by every starter, while versioned
JSON Schemas generate a discriminated realtime-envelope union? Can the normal client build fail before stale
generated types silently diverge from either contract?

## Run

From the Fight Common repository root:

```bash
planning/wayfinder/prototypes/wf-017-client-contract-generation/run.sh
```

The runner uses a pinned Node image, installs the locked prototype tools, validates representative public
envelopes, type-checks a narrowing consumer, proves a source-schema change fails the generation check, and
rewrites one machine-readable receipt per framework under `receipts/`.

## Candidate boundary

- OpenAPI 3.1 is authoritative for HTTP paths, JSend bodies, pagination, and views such as `UserView`.
- JSON Schema 2020-12 is authoritative for explicitly public realtime envelopes. Literal `event_name` and
  `schema_version` fields form the TypeScript discriminant; each payload remains event-specific and closed.
- Generated TypeScript is committed for review and checked by regeneration. Client code imports the generated
  contracts and does not maintain handwritten mirrors.
- All five starters consume the same source schemas and generated output. Framework-native actions and
  transports remain responsible only for producing the already-selected wire shapes.

## Verdict

The candidate passes. `openapi-typescript` generates named `UserView`, JSend, pagination, and route types
from OpenAPI 3.1. `json-schema-to-typescript` generates a discriminated `RealtimeEnvelope` union from JSON
Schema 2020-12 while AJV rejects an undeclared domain field. A strict TypeScript fixture narrows each event
by its stable public `event_name` and compiles without handwritten mirrors.

The check regenerates both outputs in memory and compares them byte for byte with the committed files. A
probe that adds required `display_name` to the OpenAPI `UserView` changes the generated output and makes the
check fail. All five receipts therefore carry the same HTTP and realtime SHA-256 digests. This supports one
framework-neutral schema source and one client-generation command in each starter; it does not justify a
Fight Common runtime abstraction.

## Deliberate limits

- The schemas cover only the already-prototyped users-list view plus candidate users-page and current-user
  topic families. They are not a complete AccessControl API catalog.
- The candidate current-user event name and payload are prototype inputs, not an implementation authorization.
- This does not select a runtime validator for production, generate an API client, run React, start Mercure or
  Reverb, or prove browser reconnect/refetch behavior.
- It does not change Fight Common or Fight AccessControl production source.
