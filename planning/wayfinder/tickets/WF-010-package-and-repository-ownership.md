# Define the package and repository ownership model

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Establish the portability destination and release boundaries](WF-009-portability-destination-and-release-boundaries.md)

## Question

Which code is shared, which code belongs to each framework starter, and where does implementation
planning become authoritative?

## Resolution

Create a public `johnnickell/fight-access-control` Composer package. Its first release owns the
framework-neutral Domain and Application behavior for Users, Roles, Permissions, authentication,
password recovery, email verification, sessions, and authorization. Agents, feature flags, and
specialized audit workflows are deferred. The shared package is the sole source for these layers;
the starters do not copy them.

Each public starter requires Fight Common and Fight AccessControl:

- `johnnickell/project-symfony`;
- `johnnickell/project-laravel`;
- `johnnickell/project-yii`;
- `johnnickell/project-codeigniter`; and
- `johnnickell/project-slim`.

Each starter owns its framework-native composition root, HTTP actions and presentation data,
security principal/provider, persistence records or mappings, repository adapters, migrations,
reference catalog, fixtures, console entry points, SPA host, complete `/client`, deployment
configuration, tests, and documentation.

Fight Common owns reusable Application contracts and generic or framework-specific adapters. It does
not select an adapter at runtime. A project binds synchronous or asynchronous buses, transports,
repositories, and other infrastructure in its composition root. Domain and Application code must be
movable to another framework in principle by replacing HTTP, persistence, security, and configuration
adapters.

Symfony receives no Fight Common bundle. The Symfony starter owns namespace loading,
autoconfiguration, tags, compiler-pass registration, aliases, and environment configuration. Laravel
uses service providers. Yii uses its native configuration-provider and DI model. CodeIgniter uses
`Config\Services` and module discovery. Slim uses explicit configuration in the recommended Fight
Common Container, optionally loading deterministic generated handler maps.

Fight Common's `planning/` is the umbrella authority until a new repository exists. Creation tickets
must establish an `AGENTS.md`, architecture rules, planning workflow, and quality gate in each new
repository. Detailed implementation and release ownership then moves to that repository; the umbrella
map retains dependency links but does not become a competing source of truth.

Each repository may begin privately during initial framework and security testing, then becomes public when
its owner judges the project ready for external inspection. A coordinated announcement of the fully supported
suite waits until all five starters meet their gates, while useful public `0.x.y` releases may be published
earlier. Moving a stable state to `main` does not itself require a version tag.
