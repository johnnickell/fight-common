# Define the versioned HTTP, JSend, and presentation contracts

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Establish the portability destination and release boundaries](WF-009-portability-destination-and-release-boundaries.md)

## Question

Which routes, response envelopes, presentation types, and versioning boundaries must every starter
share?

## Resolution

All public application HTTP routes begin at `/api/v1/{capability}`. AccessControl begins with routes
under `/api/v1/access`, including session, users, roles, permissions, password recovery, and email
verification. Versioning is contained in the HTTP Adapter layer:

```text
Adapter\Http\Api\V1\Access\User\ReadUserAction
Adapter\Http\Api\V1\Access\Session\CreateSessionAction
```

Domain objects, Application commands, queries, handlers, repositories, and services are not versioned
because an HTTP representation changes. A V2 action is introduced only for a breaking HTTP-contract
change; additive lookup or resource behavior remains in V1. Different HTTP versions may invoke the
same Application behavior.

Every HTTP action returns JSend. Preserve the established envelope:

- `success` for completed operations, normally 2xx;
- `fail` for expected request, validation, authentication, authorization, missing-resource, or
  business-rule rejection, normally 4xx; and
- `error` for unexpected infrastructure or server failure, normally 5xx.

Collections pass `ResultSet::toArray()` as JSend `data`, preserving `page`, `per_page`, `total_pages`,
`total_records`, and `records`. Existing CMS actions that use `error` for expected 4xx conditions are
legacy inconsistencies rather than the new contract.

Fight Common adds a framework-neutral typed JSend payload and native response adapters:

```text
Adapter\Http\Symfony\JSendResponse
Adapter\Http\Laravel\JSendResponse
Adapter\Http\Yii\JSendResponse
Adapter\Http\CodeIgniter\JSendResponse
Adapter\Http\Slim\JSendResponse
```

The old `Adapter\HttpFoundation\JSendResponse` is deprecated behind an additive `1.x` compatibility
shim and removed in `2.0.0`. The new public namespace does not retain `HttpFoundation`.

The typed payload accepts named `Arrayable` presentation data, including a single item or
`ResultSet<TData>`. Raw arrays remain temporarily accepted and deprecated in `1.x`; `2.0.0` may require
typed data. HTTP actions return purpose-built versioned data types rather than aggregates or arbitrary
arrays. Pure projections use named constructors such as `UserData::fromUserView()`. They never fetch,
resolve services, or perform I/O.

Application query handlers resolve required collaborators and return complete framework-neutral read
models such as `UserView` or `ResultSet<UserView>`. The HTTP data object performs only a small pure
projection. Repositories and command handlers continue to use aggregates.

OpenAPI describes and verifies the exact JSend contract, status behavior, identifiers, timestamps,
nullability, pagination, and validation shapes. The complete React client uses only these HTTP actions;
framework-rendered login and password forms are not part of the starters.
