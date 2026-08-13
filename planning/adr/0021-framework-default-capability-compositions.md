# ADR 0021: Framework Default Capability Compositions and Starter-Owned Integration

- Status: accepted
- Date: 2026-08-12

## Decision

Each starter owns **one opinionated, documented, Composer-installable default composition** for every
public Fight Common Application contract. Framework-native facilities are preferred where natural,
portable Fight Common adapters are reused where framework independence is natural, and a well-supported
Composer package fills a real framework gap. No capability is recorded as unsupported: a capability
without a first-party native facility receives a **recommended composition** built from existing portable
adapters and Composer-installable packages.

### Worksheet policy

The capability worksheet records **no new shared adapter** for every row. A new Fight Common class is
authorized only if a WF-017 prototype finds a translation seam that starter composition cannot express.
Equal class counts are not a goal; complete documented consumer journeys are the measure of support.

### Integration responsibilities without a Symfony bundle

Fight Common ships no Symfony bundle. Each framework owns its integration in the starter:

- **Laravel** — a ServiceProvider with `extra.laravel` auto-discovery;
- **Yii 3** — a configuration provider via `yiisoft/config` (`extra.config-plugin` groups and
  `Yiisoft\Di\ServiceProviderInterface`);
- **CodeIgniter 4** — `Config\Services` extending `CodeIgniter\Config\BaseService`;
- **Slim** — an explicit container (php-di definitions and `AppFactory::createFromContainer()`);
- **Symfony** — projects own service loading, autoconfiguration, compiler-pass registration, aliases, and
  environment configuration.

None of these are shared Fight Common packages.

### The one opinionated Slim stack

`slim/slim ^4.15`, `slim/psr7 ^1.8`, `php-di/php-di ^7.1`, `slim/twig-view ^3.4`, `doctrine/orm ^3.6`,
`doctrine/dbal ^4.4`, `symfony/messenger ^7.4 || ^8.0`, `symfony/console ^7.4 || ^8.0`,
`symfony/http-foundation ^7.4 || ^8.0`, `slim/csrf ^1.5`, `symfony/validator ^7.4 || ^8.0`,
`symfony/mailer ^7.4 || ^8.0`, `symfony/process ^7.4 || ^8.0`, `monolog/monolog ^3.10`,
`guzzlehttp/guzzle ^8.0`, `league/flysystem ^3.35`, `symfony/cache ^7.4 || ^8.0`. SMS, metrics/health,
event-store, and scheduling have no native Slim facility and compose portable Fight Common adapters
(Twilio/StatsD/null/logging) or the portable Scheduler.

### Async and event composition defaults

- **Laravel** — the default is native Queue (`queue:work`, connections, retries, `failed_jobs`) and
  event discovery through the ServiceProvider. Where domain events must be forwarded onto Laravel's
  native machinery, the starter composes an adapter event around the Fight Common domain event; the exact
  shape is settled at implementation time.
- **Yii 3** — `yiisoft/queue` and its adapters have no stable release and cannot be a hard dependency.
  Async dispatch composes the synchronous portable buses with a starter-owned transport, designed behind a
  **forward-compatible seam**: a future stable `yiisoft/queue` (or any transport) must plug in without
  breaking the public contract. Whether the seam survives prototype evidence is confirmed during WF-017.
- **CodeIgniter 4** — the official `codeigniter4/queue` and `codeigniter4/tasks` packages are the default;
  the portable buses and portable Scheduler remain the fallback.
- **Slim** — Symfony Messenger from the opinionated stack provides async command/event dispatch.

### SPA host templating defaults

- **Laravel** — Blade;
- **Symfony** — Twig;
- **Yii 3** — `yiisoft/view` with the Twig integration;
- **CodeIgniter 4** — the native View (the View Parser is not Twig);
- **Slim** — Twig via `slim/twig-view` (no native facility).

### Recommended compositions replacing blockers

Capabilities without a first-party native facility, with their recommended Composer-installable defaults:

- **CodeIgniter 4** — queue `codeigniter4/queue`; scheduler `codeigniter4/tasks`; process portable
  `SymfonyProcessRunner`; SMS `TwilioSmsTransport`; metrics/health StatsD/null plus `HealthReporter`;
  event-store DBAL/in-memory/logging stores.
- **Yii 3** — HTTP client portable `HttpClient` port plus an existing adapter; process
  `SymfonyProcessRunner`; scheduler portable `Scheduler`; metrics/health StatsD/null; cache a PSR-6 bridge
  over PSR-16 `yiisoft/cache`; async per the forward-compatible seam above.
- **Laravel** — event-store DBAL/in-memory/logging stores; metrics StatsD/null, with Laravel Pulse as a
  documented alternative.
- **Symfony** — metrics/health StatsD/null plus `HealthReporter`; event-store composed from Messenger,
  persistence, and EventDispatcher.
- **Slim** — every non-HTTP capability from the opinionated stack or portable adapters.

## Consequences

Starter composition roots carry the integration burden, keeping Fight Common lean and framework-neutral.
Every capability now has a documented path to install, so consumers never face a silent omission. The
Laravel adapter-event shape and the Yii forward-compatible seam are the two composition details expected to
mature during WF-017 prototype work; neither changes the public contract before evidence exists. The
compositions are defaults, not obligations to test every replaceable package combination.

## Rejected Alternatives

Adding a new shared Fight Common adapter for each gap was rejected because existing native seams and
portable adapters already satisfy the contracts, and a new class is authorized only by prototype evidence
of an inexpressible translation seam.

Bundling a Symfony Fight Common bundle was rejected because Symfony projects already own service loading,
autoconfiguration, compiler-pass registration, aliases, and environment configuration; a shared bundle
would duplicate that composition and couple the portable core to a framework.

Forcing one stack (Doctrine, Twig, Symfony Messenger) onto every framework was rejected because each
framework's native facility provides the simpler composition where it exists.
