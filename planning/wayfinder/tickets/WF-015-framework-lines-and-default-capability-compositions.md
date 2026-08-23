# Select supported framework lines and default capability compositions

**Labels:** `wayfinder:research`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Audit Fight Common contracts and the 1.2 compatibility envelope](WF-014-fight-common-contract-and-compatibility-audit.md)
**Research:** [WF-015 research note](../research/WF-015-framework-lines-and-default-capability-compositions-research.md)
**Decisions:** [ADR 0020 — Supported Framework Lines and the Current-Only Support Window](../../adr/0020-supported-framework-lines-and-support-window.md), [ADR 0021 — Framework Default Capability Compositions and Starter-Owned Integration](../../adr/0021-framework-default-capability-compositions.md)

## Question

Which exact framework versions and native, portable, or third-party facilities form the one supported
default composition for each public Fight Common contract in each starter?

## Must decide

- current and previous maintained Laravel, Yii 3, CodeIgniter 4, Slim, and Symfony lines that satisfy
  Fight Common's PHP and Composer constraints;
- independent repository-owned lowest/latest resolution that detects framework upgrade conflicts;
- native container and service registration, explicit handler maps, and optional build-time discovery;
- synchronous and asynchronous command and event dispatch, queue transports, retries, failure stores,
  worker commands, and operational monitoring;
- routing and URL generation, native SPA host templating, validation, mail, cache, HTTP clients,
  storage, SMS, process execution, scheduling, logging, metrics, health, and event-storage composition;
- Laravel ServiceProvider, Yii configuration provider, CodeIgniter `Config\Services`, and Slim explicit
  container responsibilities without adding a Symfony bundle;
- the one opinionated Slim stack, including Doctrine, Twig, queue, console, session, and security
  packages; and
- explicit unsupported blockers, if any, that make a framework integration incomplete rather than
  silently omitting a public contract.

## Decisions

### Supported-line policy (decided 2026-08-12)

Fight Common starts by supporting only the **current** stable major line of each framework. When a
framework ships a new major, Fight Common widens to the **current + previous** major (the immediately
preceding one) and drops the oldest. The supported window is never wider than two majors per framework.
This is a deliberate Fight Common policy, not the frameworks' own policies: CodeIgniter 4 officially
maintains only the latest line, Yii 3 has no line model, and Slim 3 is architecturally incompatible with
the PSR-7 v2 contracts Fight Common already uses.

### Selected supported lines and exact Composer constraints

| Framework | Supported range (current-only now) | Widen trigger (current + previous) | Excluded |
| --- | --- | --- | --- |
| Symfony | components `^8.1` | `^8.2 || ^8.1` when 8.2 ships (≈Nov 2026) | 7.4 LTS, 6.4 |
| Laravel | `laravel/framework ^13.0` | `^14.0 || ^13.0` when Laravel 14 ships (≈Q1 2027) | 12.x, 11.x |
| Yii 3 | current `yiisoft/*` package set (`yiisoft/di ^1.4`, `yiisoft/config ^1.6`, `yiisoft/yii-http ^1.1`, `yiisoft/event-dispatcher ^1.1`, `yiisoft/router ^4.0`, `yiisoft/router-fastroute`, `yiisoft/view`, `yiisoft/view-twig`, `yiisoft/validator`, `yiisoft/mailer ^6.1`, `yiisoft/cache ^3.2`, `yiisoft/db` + `yiisoft/db-mysql`/`yiisoft/db-pgsql`, `yiisoft/session`, `yiisoft/log`) | Not applicable — no Yii 3 line model | `yiisoft/yii-web` (unreleased), Yii 2.0 |
| CodeIgniter 4 | `codeigniter4/framework ^4.7` | `^4.7` already admits future 4.x minors; CI4 maintains only the latest line | 4.6.x (EOL, no PHP 8.5 support) |
| Slim | `slim/slim ^4.15` + the opinionated stack below | `^5.0 || ^4.15` when Slim 5 reaches stable | 3.x (PSR-7 v1) |

Fight Common's own Symfony components pin the current **`^8.1`** line. Laravel 13's `^7.4 || ^8.0`
Symfony floor and the Slim stack's `^7.4 || ^8.0` floors all resolve to Symfony 8.1 under PHP 8.5, so
no lower Symfony floor is needed while the policy is current-only. All ranges are PHP-8.5-current with a
PHP 8.6 horizon (Guzzle `<8.6`, Slim `~8.5.0`).

### Composer verification lanes

The five real starter repositories each require the Fight Common candidate plus their own framework constraint
set and resolve lowest and latest. Fight Common retains no nested framework roots and no combined starter
project. The known upgrade conflicts are outside the current window: Laravel 12 caps Symfony at `^7.2` and
Slim 3 caps at PSR-7 v1, so neither is supported while Fight Common pins Symfony `^8.1`. When a widen trigger
fires, the owning starter repository proves the newly previous line before the range widens.

### Default composition worksheet

The per-framework capability worksheet in the linked research note records, for every public Fight
Common contract, the framework-native facility, the existing reusable Fight Common binding, starter-owned
wiring, the new-shared-adapter decision, the functional journey, and the remaining unknown. The
"new shared adapter" decision is **no for every row**: native seams and existing portable adapters
satisfy the contracts, and a new Fight Common class is authorized only if a WF-017 prototype finds a
translation seam that starter composition cannot express.

### The one opinionated Slim stack

`slim/slim ^4.15`, `slim/psr7 ^1.8`, `php-di/php-di ^7.1`, `slim/twig-view ^3.4`, `doctrine/orm ^3.6`,
`doctrine/dbal ^4.4`, `symfony/messenger ^7.4 || ^8.0`, `symfony/console ^7.4 || ^8.0`,
`symfony/http-foundation ^7.4 || ^8.0`, `slim/csrf ^1.5`, `symfony/validator ^7.4 || ^8.0`,
`symfony/mailer ^7.4 || ^8.0`, `symfony/process ^7.4 || ^8.0`, `monolog/monolog ^3.10`,
`guzzlehttp/guzzle ^8.0`, `league/flysystem ^3.35`, `symfony/cache ^7.4 || ^8.0`. SMS, metrics/health,
event-store, and scheduling have no native Slim facility and compose portable Fight Common adapters
(Twilio/StatsD/null/logging) or the portable Scheduler.

### Integration responsibilities without a Symfony bundle

Laravel gets a ServiceProvider with `extra.laravel` auto-discovery; Yii 3 gets a configuration provider
via `yiisoft/config` (`extra.config-plugin` groups and `Yiisoft\Di\ServiceProviderInterface`); CodeIgniter
4 gets `Config\Services` extending `CodeIgniter\Config\BaseService`; Slim gets an explicit container
(`php-di` definitions and `AppFactory::createFromContainer()`); Symfony projects own service loading,
autoconfiguration, compiler-pass registration, aliases, and environment configuration. None of these are
shared Fight Common packages.

### Capabilities without a first-party native facility

Every capability has a recommended, documented, Composer-installable default composition; none is
unsupported (see [ADR 0021](../../adr/0021-framework-default-capability-compositions.md)).

- **CodeIgniter 4:** no native queue, process execution, scheduler, SMS, metrics/health, or event-store
  facility (queue and scheduling exist only as separate official packages); the View Parser is not Twig.
  Recommended: `codeigniter4/queue`, `codeigniter4/tasks`, portable `SymfonyProcessRunner`,
  `TwilioSmsTransport`, StatsD/null metrics plus `HealthReporter`, DBAL/in-memory/logging stores, native View.
- **Yii 3:** `yiisoft/queue` and its adapters have no stable release (dev-only) and cannot be a hard
  dependency; no native HTTP client, process, scheduler, or metrics/health facility; `yiisoft/cache` is
  PSR-16 only and needs a PSR-6 bridge for the Fight Common cache port. Recommended: synchronous portable
  buses behind a forward-compatible async seam, portable `HttpClient` port plus an existing adapter,
  `SymfonyProcessRunner`, portable `Scheduler`, StatsD/null metrics, a PSR-6 bridge over `yiisoft/cache`.
- **Laravel:** no native event store; no first-party Prometheus metrics (Laravel Pulse is the official
  observability product). Recommended: DBAL/in-memory/logging stores, StatsD/null metrics (Pulse documented
  as an alternative).
- **Symfony:** no first-party metrics/health component; no first-party event-store component. Recommended:
  StatsD/null metrics plus `HealthReporter`; event sourcing composes Messenger + persistence + EventDispatcher.
- **Slim:** no native facility for any non-HTTP capability; the PSR-7/17 implementation and container are
  deliberately left to the stack. Recommended: the opinionated stack plus portable adapters.

## Resolution boundary

This ticket is closed. It produces the decision record ([ADR 0020](../../adr/0020-supported-framework-lines-and-support-window.md),
[ADR 0021](../../adr/0021-framework-default-capability-compositions.md)), the supported-composition
worksheet with primary-source evidence, and the permanent specification
([PRD-00015](../../specs/00015-PRD.md)). It did not install framework packages, create starter repositories,
or implement adapters. WF-024 and ADR 0024 later superseded its blanket no-new-shared-adapter premise. Fight
Common implementation now proceeds through T-00057, T-00058, and T-00070 through T-00075; T-00055 remains
closed `wontfix`, and PRD-00016 and PRD-00018 retain repository ownership of booted starter journeys.
