# Define starter product, governance, and documentation standards

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Define the package and repository ownership model](WF-010-package-and-repository-ownership.md), [Define the versioned HTTP, JSend, and presentation contracts](WF-011-versioned-http-jsend-and-presentation-contracts.md)

## Question

What must each public starter contain so it is a useful project foundation rather than a disposable
adapter demonstration?

## Resolution

Each starter is both a Composer `create-project` package and a GitHub template repository. It contains
the complete backend and complete editable React source in `/client`. The five clients begin with the
same journeys and HTTP contract but are copied project foundations, not consumers of a shared runtime
frontend package. A project may rename, move, replace, or delete `/client`.

The framework's native view layer serves the SPA shell: Twig for Symfony, Blade for Laravel, Yii views
for Yii, CodeIgniter views for CodeIgniter, and the selected Slim renderer. Authentication and
administration use JSON HTTP actions rather than framework-rendered login forms. The design remains
API-first and CQRS-oriented without violating familiar framework structure.

Cookie-backed JSON authentication is the required same-origin SPA default. An optional stateless
profile provides short-lived access JWTs and refresh credentials after its security contract is
proved. Registration is configurable and disabled by default. The shared initial journeys include
login, logout, current identity, password recovery, email verification, and User, Role, and Permission
administration.

Each starter owns one framework-native migration history expressed portably and verified against
PostgreSQL and MySQL or MariaDB. SQLite may be offered for convenient local use but is not the only
production persistence proof. Database-specific migration branches are exceptional and isolated.

An idempotent native console command creates the first administrator through shared AccessControl
behavior. No default privileged credentials are shipped. Preserve the existing uppercase
`PermissionName` and `ROLE_*` `RoleName` formats exactly.

Reference-data reconciliation follows the Fight CMS pattern. Stable catalog UUIDs identify
project-owned Permissions and Roles. Synchronization creates missing owned records, performs
intentional owned renames and assignment reconciliation, rejects ID/name collisions, supports dry-run,
and leaves all non-catalog records and assignments untouched. Fight AccessControl owns reusable
reconciliation behavior; each starter owns its catalog and native command.

Every repository gets an authoritative agent-neutral `AGENTS.md` with architecture invariants,
planning workflow, vertical-slice rules, prohibited shortcuts, and exact completion criteria.
Tool-specific files contain only additive tooling notes. One noninteractive `./bin/build` is the
canonical local gate and CI delegates to the same behavior.

The intended quality baseline includes strict locked dependencies, the official Fight Common PHPCS
standard when complete, baseline-free static analysis, Domain <- Application <- Adapter architecture
enforcement, Rector, exact 100% production statement coverage, unit/integration/contract/functional
tests, schema and migration validation, container validation, generated OpenAPI drift checks, frontend
lint/type/test/build gates, and independent standards and specification review.

Documentation is a release gate. Every starter independently documents installation, create-project,
Docker and local development, database configuration, `/client`, first administrator, authentication,
optional JWT, AccessControl, every supported Fight Common capability, migration and reference-data
operations, extension seams, deployment, upgrades, supported versions, OpenAPI, troubleshooting, and
adapter replacement. Documentation verification checks links, required pages, executable examples
where practical, and generated-contract drift.
