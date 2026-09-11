# ADR 0018: Neutral JSend Envelope and Native Response Boundary

- Status: accepted
- Date: 2026-08-12

## Decision

Fight Common introduces immutable `Application\Http\JSend\JSendEnvelope` and `JSendStatus` types. The
envelope represents the semantic JSend `success`, `fail`, or `error` result without containing an HTTP
status, headers, a framework response, service lookup, or I/O. The name **JSend envelope** distinguishes it
from the existing Messaging term **Payload**.

The semantic factories are `success(?Arrayable $data = null)`, `fail(Arrayable $data)`, and
`error(string $message, ?Arrayable $data = null, ?int $code = null)`. Success always contains `data`,
including `null`; fail contains typed `data`; and error contains `message` while omitting `data` and integer
`code` when absent.

The base `Arrayable` contract promises `array<array-key, mixed>`, not a map and not a generic shape. Each
implementation declares its narrower actual return shape. `ResultSet<TRecord>` remains valid for arbitrary
scalar or object records and retains its existing runtime behavior. At the JSend boundary, a single
`Arrayable` presentation object serializes directly without pagination. A `ResultSet<TData>` is accepted only
when every record is `Arrayable`; each record is projected through `toArray()` while the established
pagination fields are preserved.

`JSendEnvelope` owns the final JSON body. `toJson()` uses the established encoding option `79` plus
`JSON_THROW_ON_ERROR`; invalid UTF-8 or otherwise unencodable presentation data throws `JsonException` before
a response is created. JSON whitespace and object-key ordering are not compatibility promises.

Each framework-native `JSendResponse` exposes
`fromEnvelope(JSendEnvelope $envelope, int $statusCode, array $headers = [])` plus ergonomic `success(...)`,
`fail(...)`, and `error(...)` factories. A controller chooses the exact HTTP status, while the adapter adds
status, headers, content type, and its native response object using the already-encoded envelope body. Native
adapters do not project records, perform service lookup, or re-encode JSON.

The old `Adapter\HttpFoundation\JSendResponse` remains functional and deprecated throughout `1.x`, including
raw-array entry points, its option-79 default, and caller-selected encoding options. It is removed in
`2.0.0`, when typed presentation data may become the only supported input.

## Consequences

All five framework adapters produce one semantic JSON contract while retaining native response types and
controller-selected HTTP outcomes. Expected request, validation, authentication, authorization,
missing-resource, and business-rule outcomes use JSend `fail`; unexpected infrastructure or server failures
use `error`.

Compatibility fixtures must cover the three envelope shapes, nullable and omitted fields, single
presentation data, paginated `ResultSet` projection, every native response type, selected HTTP status and
headers, encoding option `79`, invalid UTF-8 failure, and the deprecated raw-array Symfony behavior.

## Rejected Alternatives

Putting HTTP status in the envelope was rejected because one JSend status maps to several HTTP outcomes and
the controller owns that application-facing choice. Allowing each framework adapter to encode independently
was rejected because encoding and projection could drift across frameworks.

Requiring every envelope to contain a `ResultSet` was rejected because pagination is meaningful only for a
collection result. Narrowing `ResultSet` itself to `Arrayable` records was rejected because existing scalar
and arbitrary-object result sets remain valid in `1.x`.

Keeping `array<string, mixed>` on the base `Arrayable` interface was rejected because list implementations
such as `ArrayList<T>` return integer-keyed arrays. Accepting raw arrays on the new envelope was rejected in
favor of a typed presentation boundary; the deprecated response retains that compatibility during `1.x`.
