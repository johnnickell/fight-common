# WF-015 Framework lines and default capability compositions research

**Research date:** 2026-08-12
**Scope:** Evidence and decision inputs for
[`WF-015`](../tickets/WF-015-framework-lines-and-default-capability-compositions.md). This note does
not install framework packages, create starter repositories, implement adapters, alter Composer
constraints, or produce actual Composer locks. It selects the exact supported dependency ranges and
default composition per capability so the WF-014 fixture tickets and WF-017 prototypes can execute
their evidence lanes.

**Supported-line policy (decided 2026-08-12):** Fight Common starts by supporting only the **current**
stable major line of each framework. When a framework ships a new major version, Fight Common widens to
support the **current + previous** major (the immediately-preceding one) and drops the oldest. The
supported window is never wider than two majors per framework. This note's constraint sets and worksheet
follow that phased policy; the "previous" versions recorded below (Symfony 7.4, Laravel 12, Slim 3,
CodeIgniter 4.6) are context for when the window widens, not ranges to certify now.

## Executive finding

Fight Common supports each framework's **current stable major only**, then widens to **current +
previous** when the framework ships its next major. This is a deliberate policy choice for Fight Common,
not the frameworks' own policies: CodeIgniter 4 officially maintains only the latest line, Yii 3 has no
line model at all, and Slim 3 is architecturally incompatible with the PSR-7 v2 contracts Fight Common
already uses. Starting current-only keeps the certification and fixture burden small while the `1.2`
lanes land; the widen trigger is each framework's next major.

The current supported lines at research time are:

- **Symfony 8.1** (`php >=8.4.1`), constraint `^8.1`. Widen to `^8.2 || ^8.1` when Symfony 8.2 ships
  (expected November 2026); 7.4 LTS is not a supported range.
- **Laravel 13** (`php ^8.3`), constraint `^13.0`. Widen to `^14.0 || ^13.0` when Laravel 14 ships
  (expected around Q1 2027); 12.x (bugfix support ends 2026-08-13) is not a supported range.
- **Yii 3** as the current `yiisoft/*` package set (`php 8.2 - 8.5`); there is no previous Yii 3 line.
- **CodeIgniter 4.7** (`php ^8.2`), constraint `^4.7`. CI4 maintains only the latest line, so 4.6.x is
  EOL and never becomes a supported range; `^4.7` naturally resolves to future 4.x minors.
- **Slim 4.15** (`php ~8.5.0` ceiling), constraint `^4.15`. Widen to `^5.0 || ^4.15` when Slim 5 reaches
  stable (only `5.x-dev` today); Slim 3 is out of scope because it caps at `psr/http-message ^1.0`.

Second, the "one supported default composition per contract per starter" decision must tolerate a real
**PHP 8.6 horizon**. Guzzle 8 (Latest) declares `>=7.4,<8.6`, Slim 4's `~8.5.0` excludes 8.6+, and
Symfony has already released `symfony/polyfill-php86`; Fight Common pins PHP `8.5.4` today
(`composer.json:85-97`). The selected ranges below are all resolvable and PHP-8.5-safe now; because the
policy is current-only, Fight Common's own Symfony components can pin the current `^8.1` line instead of
a two-line union, which simplifies the combined Composer lane.

Third, every framework already has a **native seam for nearly every capability**; the genuine
composition gaps are narrow: CodeIgniter has no native queue/process/scheduler/SMS/metrics/event-store,
Yii has no released queue and no process/scheduler/metrics native facility, Slim has no native
non-HTTP stack, Laravel has no event store or first-party Prometheus metrics, and Symfony has no
first-party metrics/health or event-store component. The worksheet below records the default
composition for each of these, and the "new shared adapter" column stays **no** for every row —
the WF-014 handoff says starter composition and existing portable adapters come first, and nothing in
the primary sources shows a translation seam that requires a new Fight Common class.

## Evidence method and limits

- Primary sources only: first-party `composer.json` manifests per release branch or tag in the framework
  repositories (`github.com/symfony/symfony`, `github.com/laravel/framework`,
  `github.com/yiisoft/app` plus `yiisoft/*` package manifests, `github.com/codeigniter4/CodeIgniter4`,
  `github.com/slimphp/Slim`), official support/release pages, official component documentation, and
  Packagist metadata endpoints (`repo.packagist.org/p2/...`).
- Version numbers and PHP constraints were read from fetched manifests or Packagist metadata on
  2026-08-12/13. Where a doc page contradicted a manifest (Symfony 8.1 minimum PHP: site says 8.4.0,
  manifest says `>=8.4.1`), the manifest is cited as authoritative.
- Maintenance status is time-sensitive and drives the widen triggers: Laravel 13 is current until Laravel
  14 ships (≈Q1 2027); Symfony 8.1 is current until 8.2 ships (≈November 2026); CodeIgniter 4.7 is the
  only maintained CI4 line; Slim 4 is current until Slim 5 reaches stable (only `5.x-dev` today). These
  dates bound how long each single-line range is "current."
- No Composer solve was run. Lock receipts are recorded here as the constraint sets the fixture tickets
  must solve; the actual lowest/latest receipts belong to the T-0005x fixture implementation and WF-017
  prototypes, per the WF-014 ticket's Composer verification layout
  (`../tickets/WF-014-fight-common-contract-and-compatibility-audit.md:180-195`).
- Fight Common facts (PHP `>=8.5`, platform `8.5.4`, current `require-dev` Symfony constraints) are read
  from the local `composer.json:5-13,14-44,85-97` and the authoritative
  [WF-014 research note](WF-014-fight-common-contract-and-compatibility-audit-research.md:418-452).

## Verified facts

### Symfony

- Maintained lines as of 2026-08-12 (source of truth:
  [symfony.com/releases](https://symfony.com/releases) and machine-readable
  [versions.json](https://symfony.com/versions.json), which reports `latest: 8.1.4`, `lts: 7.4.16`,
  `dev: 8.2.0`):
  - **8.1** current stable, `php >=8.4.1`
    ([8.1 composer.json](https://github.com/symfony/symfony/blob/8.1/composer.json)), bugfix until
    January 2027.
  - **7.4 LTS**, `php >=8.2` ([7.4 composer.json](https://github.com/symfony/symfony/blob/7.4/composer.json)),
    bugfix until November 2028, security until November 2029.
  - 6.4 LTS (`php >=8.1`) still bugfix-maintained until November 2026; 8.0 (`php >=8.4`) is already EOL
    (July 2026); 8.2 is under development (release November 2026).
- All maintained lines run on PHP 8.5; Symfony's policy is that every released PHP major is supported
  throughout a line's lifetime
  ([PHP compatibility policy](https://symfony.com/doc/current/contributing/community/releases.html#php-compatibility)).
  Both 7.4 and 8.x `composer.json` require `symfony/polyfill-php85`, confirming PHP 8.5 is an intended
  target line.
- Component versions always equal the framework line version (monorepo `replace` block in
  [8.1 composer.json](https://github.com/symfony/symfony/blob/8.1/composer.json)).
- Under the phased policy the supported range is the current **8.1** line only, constraint `^8.1`;
  widen to `^8.2 || ^8.1` when 8.2 ships. The `^7.4 || ^8.0` floor is the cross-version idiom Symfony's
  split packages use (e.g. [framework-bundle 8.1 composer.json](https://github.com/symfony/framework-bundle/blob/8.1/composer.json)),
  so the widened form is a drop-in later; it is not needed for certification now.

### Laravel

- Official policy is uniform: bug fixes 18 months, security fixes 2 years, for every release
  ([Laravel 13.x release notes](https://laravel.com/docs/13.x/releases)). The premise of longer even-major
  support is not supported by the current docs.
- **13.x** current: `php ^8.3` ([13.x composer.json](https://github.com/laravel/framework/blob/13.x/composer.json)),
  released 2026-03-17, current patch 13.25.0 (2026-08-11); **12.x** previous: `php ^8.2`
  ([12.x composer.json](https://github.com/laravel/framework/blob/12.x/composer.json)), bug fixes until
  2026-08-13, security until 2027-02-24; **11.x** EOL (security ended 2026-03-12). Under the phased
  policy only **13.x** is a supported range; **12.x** becomes the widened range only if a Laravel 14
  bump happens before Laravel 12's security window closes.
- Neither manifest has an upper PHP bound; both lines resolve under PHP 8.5.
- **Symfony overlap (relevant only for the supported Laravel 13 lane):** Laravel 13.x requires Symfony
  components at `^7.4.0 || ^8.0.0` (console, error-handler, finder, http-foundation, http-kernel, mailer,
  mime, routing, uid, var-dumper) and `symfony/process ^7.4.5 || ^8.0.5`; that floor is compatible with
  Fight Common's own `^8.1` Symfony pin. (Laravel 12.x would cap Symfony at `^7.2.0` only, which is why
  12.x must not share the combined lane while Fight Common pins `^8.1`
  ([13.x composer.json](https://github.com/laravel/framework/blob/13.x/composer.json),
  [12.x composer.json](https://github.com/laravel/framework/blob/12.x/composer.json)).
- Third-party integration is ServiceProvider plus `composer.json` `extra.laravel` auto-discovery
  ([Laravel package discovery](https://laravel.com/docs/13.x/packages)).

### Yii 3

- Yii 3 is a package ecosystem, not a monolithic version. Official guide:
  [what is Yii](https://yiisoft.github.io/docs/guide/intro/what-is-yii.html). The canonical package set is
  the `yiisoft/app` skeleton, currently **1.4.0** with `php: "8.2 - 8.5"`
  ([Packagist](https://repo.packagist.org/p2/yiisoft/app.json),
  [master composer.json](https://github.com/yiisoft/app/blob/master/composer.json)).
- `yiisoft/yii-web` has zero releases on Packagist and must not be depended on
  ([Packagist](https://repo.packagist.org/p2/yiisoft/yii-web.json)); the current web core is
  `yiisoft/yii-http` (1.1.1, PHP `8.1 - 8.5`).
- PHP 8.5 support was added across the ecosystem in December 2025; verified current majors include
  `yiisoft/di ^1.4` (8.1-8.5), `yiisoft/config ^1.6` (8.1-8.5), `yiisoft/router ^4.0` (8.1-8.5),
  `yiisoft/view` + `yiisoft/view-twig` (8.1-8.5), `yiisoft/validator` (8.1-8.5),
  `yiisoft/mailer ^6.1` (8.1-8.5), `yiisoft/cache ^3.2` (8.1-8.5), `yiisoft/db` + `yiisoft/db-mysql` +
  `yiisoft/db-pgsql` (8.1-8.5), `yiisoft/session` (8.0-8.5), `yiisoft/log` (`^8.0`, no cap), and
  `yiisoft/yii-http` (8.1-8.5). Citations in the agent fact packs above; each manifest fetched from
  `repo.packagist.org/p2/<package>.json` or `github.com/yiisoft/<package>/blob/master/composer.json`.
- Flags: `yiisoft/queue` and its official adapters (`yiisoft/queue-amqp`, `yiisoft/queue-redis`) have no
  stable release (dev-only) and cannot be a hard dependency of a stable library; `yiisoft/event-dispatcher`
  master caps PHP at `8.0 - 8.4` while its only release (1.1.0) is `^8.0` (installable on 8.5, not yet
  8.5-verified).
- Library integration is a **configuration provider** via `yiisoft/config`, a Composer plugin assembling
  `params`, `di`, `di-providers`, `di-delegates`, `events`, `routes`, and `bootstrap` groups declared in
  `extra.config-plugin`, with providers implementing `Yiisoft\Di\ServiceProviderInterface`
  ([configuration guide](https://yiisoft.github.io/docs/guide/concept/configuration.html),
  [designing packages](https://yiisoft.github.io/docs/guide/structure/designing-packages.html)).
- Symfony overlap: the app skeleton pulls `symfony/console ^7.4.7 || ^8.0.7`; `yiisoft/mailer-symfony`
  pulls `symfony/mailer ^6||^7||^8` and `symfony/mime ^6.3||^7||^8`. These are compatible with Fight
  Common's existing `symfony/mailer ^8.0` and `symfony/process ^7.0` dev requirements. Yii does not use
  Symfony routing, messenger, or dependency-injection.

### CodeIgniter 4

- Official policy: "we only maintain the latest version"
  ([Server Requirements](https://codeigniter.com/user_guide/intro/requirements.html)). Only **4.7.x** is
  maintained; current is **4.7.4** (2026-07-07), `php ^8.2`
  ([composer.json v4.7.4](https://github.com/codeigniter4/CodeIgniter4/blob/v4.7.4/composer.json)).
- "PHP 8.5 requires CodeIgniter 4.7.0 or later" (official requirements note). The previous line 4.6.x
  (final 4.6.5, 2026-02-01) is EOL and does not support PHP 8.5
  ([requirements 4.6.5](https://github.com/codeigniter4/CodeIgniter4/blob/v4.6.5/user_guide_src/source/intro/requirements.rst)).
- Recommended constraint `^4.7` (matches the official app starter's
  [composer.json](https://github.com/codeigniter4/appstarter/blob/master/composer.json)); `^4.6` would
  admit an unmaintained line incompatible with `php >=8.5`.
- CodeIgniter has no Symfony dependencies (requires only `php ^8.2`, `ext-intl`, `ext-mbstring`,
  `laminas/laminas-escaper ^2.18`, `psr/log ^3.0`), so it adds nothing to the combined Symfony lane
  ([composer.json v4.7.4](https://github.com/codeigniter4/CodeIgniter4/blob/v4.7.4/composer.json)).
- Integration is via `Config\Services` (extending `CodeIgniter\Config\BaseService`, auto-discovered),
  `config()` helpers, and module auto-discovery in `app/Config/Modules.php`
  ([services](https://codeigniter.com/user_guide/concepts/services.html),
  [modules](https://codeigniter.com/user_guide/general/modules.html),
  [composer packages](https://codeigniter.com/user_guide/extending/composer_packages.html)).
- No native facilities: queue (official separate package `codeigniter4/queue`), scheduling (official
  separate package `codeigniter4/tasks`), SMS, process execution, metrics/health, event-store; the View
  Parser is not Twig.

### Slim

- Official support table lists exactly two lines: 3.x and 4.x
  ([SECURITY.md](https://github.com/slimphp/Slim/blob/4.x/SECURITY.md)). Current: **4.15.2** (2026-05-22,
  a security release fixing CVE-2026-48157). Previous: **3.13.0** (2026-04-28, `php ^8.1`).
- Slim 4 PHP constraint is `~7.4.0 || ~8.0.0 || ... || ~8.5.0` — a real `~8.5.0` ceiling; PHP 8.5 support
  arrived in 4.15.1; PHP 8.6+ is rejected until a 4.x bump
  ([4.x composer.json](https://github.com/slimphp/Slim/blob/4.x/composer.json),
  [4.15.1 release](https://github.com/slimphp/Slim/releases/tag/4.15.1)).
- Slim 4 requires only PSR contracts plus `nikic/fast-route ^1.3`, and deliberately leaves the PSR-7/17
  implementation and container optional (`suggest`: `slim/psr7`, `php-di/php-di`)
  ([4.x composer.json](https://github.com/slimphp/Slim/blob/4.x/composer.json),
  [installation](https://slimframework.com/docs/v4/start/installation.html)).
- Slim 3 is architecturally different (PSR-7 v1 only, Pimple container), so dual 3.x/4.x support would
  require shims; the recommended supported surface is **4.x only**.
- Slim 5 exists only as `5.x-dev` (`php ~8.2.0-~8.5.0`, PSR container/http-message v2) with no stable tag
  and no support-table entry — not a supported line.
- Integration: explicit container composition via Slim-Skeleton's `app/{dependencies,middleware,routes,
  settings}.php` and `AppFactory::createFromContainer()`; default skeleton is PHP-DI `^6.4` + Monolog
  `^2.9`, which are older than current majors and are treated as floors
  ([Slim-Skeleton composer.json](https://github.com/slimphp/Slim-Skeleton/blob/main/composer.json)).

### Recommended exact Composer constraint sets (fixture inputs)

| Framework | Fixture constraint (first-party or verified) | Widen trigger (current + previous) | Primary sources |
| --- | --- | --- | --- |
| Symfony | components `^8.1` (e.g. `symfony/messenger ^8.1`, `symfony/dependency-injection ^8.1`, `symfony/http-foundation`, `symfony/routing`, `symfony/mailer`, `symfony/validator`, `symfony/cache`, `symfony/http-client`, `symfony/filesystem`, `symfony/process`, `symfony/event-dispatcher`, `twig/twig ^3.0`) | `^8.2 || ^8.1` when 8.2 ships (≈Nov 2026) | [8.1 composer.json](https://github.com/symfony/symfony/blob/8.1/composer.json), [framework-bundle](https://github.com/symfony/framework-bundle/blob/8.1/composer.json) |
| Laravel | `laravel/framework ^13.0` | `^14.0 || ^13.0` when Laravel 14 ships (≈Q1 2027) | [13.x composer.json](https://github.com/laravel/framework/blob/13.x/composer.json), [releases](https://laravel.com/docs/13.x/releases) |
| Yii 3 | individual packages: `yiisoft/di ^1.4`, `yiisoft/config ^1.6`, `yiisoft/yii-http ^1.1`, `yiisoft/event-dispatcher ^1.1`, `yiisoft/router ^4.0`, `yiisoft/router-fastroute`, `yiisoft/view`, `yiisoft/view-twig ^3.x`, `yiisoft/validator`, `yiisoft/mailer ^6.1`, `yiisoft/cache ^3.2`, `yiisoft/db` + `yiisoft/db-mysql`/`yiisoft/db-pgsql`, `yiisoft/session`, `yiisoft/log` (each `^current-major`; all PHP 8.5-verified) | Not applicable — no Yii 3 line model | [app composer.json](https://github.com/yiisoft/app/blob/master/composer.json), package manifests above |
| CodeIgniter 4 | `codeigniter4/framework ^4.7` | `^4.7` already admits future 4.x minors; CI4 maintains only the latest line | [composer.json v4.7.4](https://github.com/codeigniter4/CodeIgniter4/blob/v4.7.4/composer.json), [appstarter](https://github.com/codeigniter4/appstarter/blob/master/composer.json) |
| Slim | `slim/slim ^4.15` + opinionated stack (below) | `^5.0 || ^4.15` when Slim 5 reaches stable | [4.x composer.json](https://github.com/slimphp/Slim/blob/4.x/composer.json) |

### Composer resolution strategy inputs

- **Isolated locks:** five minimal fixture roots (per WF-014
  `tickets/WF-014-...:180-195`), each requiring the Fight Common candidate plus exactly one framework's
  constraint set above. Resolve lowest and latest; record resolved versions and lock digest. Nothing here
  changes that layout; this note only supplies the exact constraint sets.
- **Combined root lane:** the existing root `require-dev` is the mutually resolvable set. Current-only
  support keeps it simple: Laravel 13 needs Symfony `^7.4 || ^8.0` (compatible with Fight Common's `^8.1`
  pin); Yii pulls `symfony/console` and `symfony/mailer`; Slim's opinionated stack pulls the Symfony
  stack; CodeIgniter contributes nothing; Guzzle 8 caps at `<8.6` PHP; Slim 4 caps at `~8.5.0`. The
  combined lane must be resolved at both latest and lowest where meaningful.
- **Framework upgrade conflict detection:** the known conflict is the widened window, not today's lane.
  Laravel 12 caps Symfony at `^7.2` and Slim 3 caps at PSR-7 v1, which is why neither can enter a lane
  where Fight Common pins Symfony `^8.1`; when the window widens after a major bump, each added previous
  line gets its own isolated fixture proof and the combined lane is re-resolved rather than extended
  blindly.
- PHP 8.6 horizon: Guzzle `>=7.4,<8.6`, Slim `~8.5.0`, and Symfony `polyfill-php86` mean the ranges are
  PHP-8.5-current; re-run resolution when PHP 8.6 or Slim 5/CodeIgniter 4.8/Laravel 14 land.

### The one opinionated Slim stack

All packages verified PHP-8.5-compatible on 2026-08-12:

| Capability | Package | Constraint | Source |
| --- | --- | --- | --- |
| Framework | `slim/slim` | `^4.15` | [4.x composer.json](https://github.com/slimphp/Slim/blob/4.x/composer.json) |
| PSR-7/17 | `slim/psr7` | `^1.8` | [Packagist](https://packagist.org/packages/slim/psr7) |
| Container | `php-di/php-di` | `^7.1` | [Packagist](https://packagist.org/packages/php-di/php-di) |
| Templating | `slim/twig-view` | `^3.4` | [Packagist](https://packagist.org/packages/slim/twig-view) |
| ORM | `doctrine/orm` | `^3.6` | [Packagist](https://packagist.org/packages/doctrine/orm) |
| DBAL | `doctrine/dbal` | `^4.4` | [Packagist](https://packagist.org/packages/doctrine/dbal) |
| Queue | `symfony/messenger` | `^7.4 || ^8.0` | [Packagist](https://packagist.org/packages/symfony/messenger) |
| Console | `symfony/console` | `^7.4 || ^8.0` | [Packagist](https://packagist.org/packages/symfony/console) |
| Session/security foundation | `symfony/http-foundation` | `^7.4 || ^8.0` | [Packagist](https://packagist.org/packages/symfony/http-foundation) |
| CSRF | `slim/csrf` | `^1.5` | [Packagist](https://packagist.org/packages/slim/csrf) |
| Validation | `symfony/validator` | `^7.4 || ^8.0` | [Packagist](https://packagist.org/packages/symfony/validator) |
| Mail | `symfony/mailer` | `^7.4 || ^8.0` | [Packagist](https://packagist.org/packages/symfony/mailer) |
| Process | `symfony/process` | `^7.4 || ^8.0` | [Packagist](https://packagist.org/packages/symfony/process) |
| Logging | `monolog/monolog` | `^3.10` | [Packagist](https://packagist.org/packages/monolog/monolog) |
| HTTP client | `guzzlehttp/guzzle` | `^8.0` | [Packagist](https://packagist.org/packages/guzzlehttp/guzzle) |
| Storage | `league/flysystem` | `^3.35` | [Packagist](https://packagist.org/packages/league/flysystem) |
| Cache (Doctrine metadata) | `symfony/cache` | `^7.4 || ^8.0` | [Doctrine cookbook](https://slimframework.com/docs/v4/cookbook/database-doctrine.html) |

No native Slim facility exists for SMS, metrics/health, event-store, or scheduling; those capabilities
compose portable Fight Common adapters (Twilio/StatsD/null/logging) or the portable Scheduler rather than
a new stack package.

## Recommendations and decision inputs

1. **Support current-only now; widen after each major bump.** Certify Symfony `^8.1`, Laravel `^13.0`,
   CodeIgniter `^4.7`, Slim `^4.15`, and the current Yii 3 `yiisoft/*` set. Do not certify Symfony 7.4,
   Laravel 12, CodeIgniter 4.6, or Slim 3 as supported lines: 4.6 is EOL, and the others would force a
   lower Symfony/PSR-7 floor onto the combined lane. Widen to `^8.2 || ^8.1`, `^14.0 || ^13.0`, and
   `^5.0 || ^4.15` when the corresponding next major ships.
2. **Pin Fight Common's own Symfony components to `^8.1` (the current line).** Laravel 13's
   `^7.4 || ^8.0` Symfony floor and the Slim stack's `^7.4 || ^8.0` floors all resolve to Symfony 8.1
   under PHP 8.5, so no `^7.2` or `^7.4` floor is needed while the policy is current-only.
3. **Do not admit a previous line that contradicts the current lane.** Laravel 12 (Symfony `^7.2`) and
   Slim 3 (PSR-7 v1) cannot share the combined lane while Fight Common pins Symfony `^8.1`. When a widen
   trigger fires, give each new previous line its own isolated fixture proof and re-resolve the combined
   lane before publishing the widened range.
4. **Treat the WF-014 worksheet as the composition contract and record "no new shared adapter" for every
   row.** Every native seam or existing portable adapter below satisfies the contract; a new Fight Common
   class is authorized only if a WF-017 prototype finds a translation seam that starter composition cannot
   express.
5. **Do not hard-depend on `yiisoft/queue` (dev-only) or any unreleased package.** Yii 3 async dispatch
   defers to the sync portable buses or a starter-owned transport until a stable release exists.
6. **Document the supported window as current-only with explicit widen triggers:** each supported range
   is the current line today, and the widened `^new || ^current` form is adopted only when the framework's
   next major ships (Symfony 8.2 ≈November 2026, Laravel 14 ≈Q1 2027, Slim 5 stable, CodeIgniter 4.8).
   The ranges are PHP-8.5-current with a PHP 8.6 horizon.
7. **Do not add a Symfony bundle.** Symfony starters own service loading, autoconfiguration, compiler-pass
   registration, aliases, and environment configuration; Yii gets a config provider; CodeIgniter gets
   `Config\Services`; Slim gets an explicit container; Laravel gets a ServiceProvider with auto-discovery.
   All are starter-owned wiring, not shared Fight Common packages
   (`../tickets/WF-015-framework-lines-and-default-capability-compositions.md:23-27`,
   `../fight-framework-portability-map.md:116-119`).

## Downstream contract-to-capability worksheet

Per the WF-014 handoff, every capability × framework row records the framework-native facility, the
existing Fight Common binding, starter-owned wiring, the new-shared-adapter decision, the functional
journey, and the remaining unknown. Selected maintained versions and exact Composer constraints are the
framework header block above; lowest/latest lock receipts are captured by the fixture tickets and WF-017
prototypes, not by this planning note.

"Existing binding" names the reusable Fight Common class or port from the authoritative
[WF-014 adapter inventory](WF-014-fight-common-contract-and-compatibility-audit-research.md:175-231).

### Symfony

| Capability | Native facility | Existing Fight Common binding | Starter-owned wiring | New shared adapter | Remaining unknown |
| --- | --- | --- | --- | --- | --- |
| Commands/queries/events | `symfony/messenger` (transports, retries, failure transport, `messenger:consume`); `symfony/event-dispatcher` | `MessengerCommandBus`, `MessengerEventDispatcher`, `SymfonyMessageSerializer`, handlers, compiler passes | Autoconfigured handler/subscriber registration; compiler-pass registration | No — adapters exist | Retry/failure-transport mapping to Fight Common failure behavior |
| Event Sourcing | Messenger + persistence + EventDispatcher (no first-party store) | DBAL/in-memory/logging stores and cursors; `EventMappingProviderCompilerPass` | Mapping compiler-pass registration; DBAL schema wiring | No | — |
| Persistence | `doctrine/orm` + `doctrine/dbal` | `DoctrineRepository`, `DoctrineUnitOfWork`, Doctrine types | XML mapping + compiler passes; connection config | No | — |
| HTTP/JSend | Symfony HttpFoundation | New `Adapter\Http\Symfony\JSendResponse` + `ErrorController`/`JsonRequestMiddleware` | Response adapters in controllers; exception subscriber | No — WF-011/014 authorized | — |
| Routing/URL | `symfony/routing` | `SymfonyUrlGenerator` | Named-route config; router | No | — |
| Validation | `symfony/validator` | Portable `ValidationService` + `SymfonyValidationSubscriber` | Request extraction; subscriber registration | No | Expected-failure mapping to JSend fail |
| Auth | `symfony/security` not required | HMAC/PHP-password/JWT adapters | Security wiring is starter-owned (WF-016) | No | WF-016 |
| Cache | `symfony/cache` (PSR-6) | `PsrCache` | Cache pool config | No | — |
| Storage/filesystem | `league/flysystem`; `symfony/filesystem` | `FlysystemStorage`, `SymfonyFilesystem` | Service config | No | — |
| File transfer | FTP/SFTP transports (portable) | `FtpFileTransport`, `SftpFileTransport` | Service config | No | — |
| Mail | `symfony/mailer` | `SymfonyMailFactory`/`SymfonyMailTransport` | Mailer config | No | — |
| SMS | Twilio transport (portable) | `TwilioSmsTransport` | Twilio client config | No | — |
| Templating | Twig | `TwigEngine`, `PhpEngine`, `DelegatingEngine`; `TemplateHelperCompilerPass` | Twig config; helper registration | No | — |
| Process | `symfony/process` | `SymfonyProcessRunner` | Service config | No | — |
| Scheduler | `symfony/scheduler` optional; portable Scheduler default | `Scheduler` + `SymfonyProcessRunner` | Scheduler config; messenger-based schedule if chosen | No | WF-014 constructor repair evidence |
| Observability | Monolog/PSR-3; no first-party metrics/health | `LoggingAuditLog`, `HealthReporter`/checks, StatsD/null metrics | PSR logger wiring; health endpoint | No | — |
| Socket/push | Mercure | `MercureHubPublisher` | Mercure client config | No | — |
| DI/composition | `symfony/dependency-injection` | Symfony compiler passes (32-declaration migration per WF-014) | Project-owned service loading; no bundle | No | Compiler-pass identity probes (WF-014) |

### Laravel

| Capability | Native facility | Existing Fight Common binding | Starter-owned wiring | New shared adapter | Remaining unknown |
| --- | --- | --- | --- | --- | --- |
| Commands/queries/events | Queue (`queue:work`, connections, retries, `failed_jobs`); Events with discovery | `RoutingCommandBus`, `SimpleEventDispatcher`, `ServiceAwareEventDispatcher` | Provider-registered handler maps; queue connection config | No — native bus/queue or portable dispatcher | Handler-map vs native discovery choice |
| Event Sourcing | None native (Events + DB only) | DBAL/in-memory/logging stores | DB service registration; migrations | No | — |
| Persistence | Eloquent | Portable repositories; Doctrine only if selected | Eloquent hydration/wiring (WF-017 prototype) | No | Eloquent aggregate hydration + native transactions (WF-017) |
| HTTP/JSend | HttpFoundation responses | New `Adapter\Http\Laravel\JSendResponse` | Response factories in controllers | No — WF-011/014 authorized | — |
| Routing/URL | Routing + `url()` helpers | Portable `UrlGenerator` port; Symfony adapter is Symfony-only | Native route + URL generation | No | Named-route translation |
| Validation | Validation service | Portable `ValidationService` | Native request validation binding | No | Same field-error data shape |
| Auth | Auth/guard system | HMAC/PHP-password/JWT adapters | Guard wiring (WF-016) | No | WF-016 |
| Cache | Cache repository (PSR-6/16 adapters) | `PsrCache` | Native cache store config | No | — |
| Storage/filesystem | Storage (Flysystem) | `FlysystemStorage` | Native disk config | No | — |
| File transfer | None native | `FtpFileTransport`, `SftpFileTransport` | Service config | No | — |
| Mail | Mail (Mailer) | `SymfonyMailTransport` family | Native mail config | No | — |
| SMS | Notifications (Vonage channel) | `TwilioSmsTransport` | Notification channel wiring | No | — |
| Templating | Blade (no distinct SPA-host facility; starter kits) | `TwigEngine`, `PhpEngine` | Blade config / Vite host | No | SPA host templating choice |
| Process | `Illuminate\Process` | `SymfonyProcessRunner` | Native process wrapper | No | — |
| Scheduler | Console kernel scheduling | `Scheduler` + runner | Native schedule registration | No | WF-014 constructor repair |
| Observability | Logging; **Laravel Pulse** (no first-party Prometheus) | `LoggingAuditLog`, `HealthReporter`/checks, StatsD/null metrics | PSR logger wiring; health route (`health: '/up'`) | No | Prometheus exporter choice |
| Socket/push | Broadcasting | `MercureHubPublisher` | Broadcast driver config | No | — |
| DI/composition | ServiceProvider + auto-discovery | Portable `Container` | Provider registers Fight Common services | No | — |

### Yii 3

| Capability | Native facility | Existing Fight Common binding | Starter-owned wiring | New shared adapter | Remaining unknown |
| --- | --- | --- | --- | --- | --- |
| Commands/queries/events | `yiisoft/event-dispatcher` (PSR-14) + `yiisoft/yii-event`; queue unreleased | `RoutingCommandBus`, `SimpleEventDispatcher`, `ServiceAwareEventDispatcher` | `events` config group; class→listener map; `di-providers` | No | `yiisoft/queue` has no stable release — async deferral |
| Event Sourcing | None native | DBAL/in-memory/logging stores | DB service registration; migrations | No | — |
| Persistence | `yiisoft/db` + `yiisoft/db-mysql`/`yiisoft/db-pgsql` | Portable repositories | DB connection config; AR wiring (WF-017) | No | Yii DB aggregate hydration + native transactions (WF-017) |
| HTTP/JSend | PSR-7 (runner-http) + PSR-15 | New `Adapter\Http\Yii\JSendResponse` | Response factory in handlers | No — WF-011/014 authorized | PSR-7 factory binding |
| Routing/URL | `yiisoft/router` (`UrlGeneratorInterface`, FastRoute) | Portable `UrlGenerator` port | Route config group | No | URL generation translation |
| Validation | `yiisoft/validator` | Portable `ValidationService` | Validator config | No | Same field-error data shape |
| Auth | `yiisoft/security` (session-based) | HMAC/PHP-password/JWT adapters | Security config (WF-016) | No | WF-016 |
| Cache | `yiisoft/cache` (PSR-16 only) | `PsrCache` (PSR-6) | PSR-6 bridge via `yiisoft/cache` handlers | No | PSR-6↔PSR-16 bridging |
| Storage/filesystem | None native | `FlysystemStorage`, portable Filesystem | Flysystem config | No | — |
| File transfer | None native | `FtpFileTransport`, `SftpFileTransport` | Service config | No | — |
| Mail | `yiisoft/mailer` + `yiisoft/mailer-symfony` (Symfony Mailer driver) | `SymfonyMailTransport` family | `di-providers` config | No | — |
| SMS | None native | `TwilioSmsTransport` | Twilio config | No | — |
| Templating | `yiisoft/view` (+ `yiisoft/view-twig`) | `TwigEngine`, `PhpEngine` | View config; theme paths | No | — |
| Process | None native | `SymfonyProcessRunner` | Service config | No | — |
| Scheduler | None native | `Scheduler` + runner | Console command + cron | No | WF-014 constructor repair |
| Observability | `yiisoft/log` (PSR-3); no metrics/health | `LoggingAuditLog`, `HealthReporter`/checks, StatsD/null metrics | PSR logger wiring | No | — |
| Socket/push | None native | `MercureHubPublisher` | Mercure config | No | — |
| DI/composition | `yiisoft/di` + `yiisoft/config` provider plugin | Portable `Container` | Config provider (`extra.config-plugin` groups `di`, `di-providers`, `events`, `routes`, `bootstrap`) | No | — |

### CodeIgniter 4

| Capability | Native facility | Existing Fight Common binding | Starter-owned wiring | New shared adapter | Remaining unknown |
| --- | --- | --- | --- | --- | --- |
| Commands/queries/events | Events (`Events` pub/sub); queue is a separate official package (`codeigniter4/queue`) | `RoutingCommandBus`, `SimpleEventDispatcher`, `ServiceAwareEventDispatcher` | Service-registered handler maps; queue package only if adopted | No — portable buses | Queue package adoption |
| Event Sourcing | None native | DBAL/in-memory/logging stores | DB config; migrations | No | — |
| Persistence | Query Builder/Model | Portable repositories | Native model wiring (WF-017) | No | CI Model aggregate hydration + native transactions (WF-017) |
| HTTP/JSend | Response + PSR-7 | New `Adapter\Http\CodeIgniter\JSendResponse` | Response construction in controllers | No — WF-011/014 authorized | — |
| Routing/URL | `Routes` + `url_helper`/`site_url()` | Portable `UrlGenerator` port | Native route config | No | URL generation translation |
| Validation | Validation service | Portable `ValidationService` | Native validation rules | No | Same field-error data shape |
| Auth | Shield (official auth package) | HMAC/PHP-password/JWT adapters | Shield wiring (WF-016) | No | WF-016 |
| Cache | Cache service | `PsrCache` | Native handler config | No | PSR-6 adapter package |
| Storage/filesystem | Filesystem helper + `File`/`FileCollection` | `FlysystemStorage`, portable Filesystem | Native helper wiring | No | — |
| File transfer | None native | `FtpFileTransport`, `SftpFileTransport` | Service config | No | — |
| Mail | Email class | `SymfonyMailTransport` family (or `NullMailTransport`) | Native email config | No | — |
| SMS | **No native facility** | `TwilioSmsTransport` | Twilio config | No | — |
| Templating | View (View Parser is not Twig) | `PhpEngine`, `TwigEngine` (if Twig added) | Native view config | No | Twig-vs-native-view choice |
| Process | **No native facility** | `SymfonyProcessRunner` | Service config | No | — |
| Scheduler | Separate official package `codeigniter4/tasks` | `Scheduler` + runner | Tasks config | No | — |
| Observability | Log service (PSR-3); no metrics/health | `LoggingAuditLog`, `HealthReporter`/checks, StatsD/null metrics | PSR logger wiring | No | — |
| Socket/push | None native | `MercureHubPublisher` | Mercure config | No | — |
| DI/composition | `Config\Services` (auto-discovery) | Portable `Container` | `Config\Services` extending `BaseService`; `config()` helpers | No | — |

### Slim

| Capability | Native facility | Existing Fight Common binding | Starter-owned wiring | New shared adapter | Remaining unknown |
| --- | --- | --- | --- | --- | --- |
| Commands/queries/events | None native; `symfony/messenger` (stack) | `RoutingCommandBus`, `SimpleEventDispatcher`, `ServiceAwareEventDispatcher`, `MessengerCommandBus`/`MessengerEventDispatcher` | Container handler maps; messenger transport config | No | — |
| Event Sourcing | None native; Doctrine + messenger | DBAL/in-memory/logging stores | DB config; migrations; doctrine container wiring | No | — |
| Persistence | Doctrine (`doctrine/orm` + `doctrine/dbal` stack) | `DoctrineRepository`, `DoctrineUnitOfWork`, Doctrine types | Doctrine config (cookbook layout); `slim/psr7` + container | No | — |
| HTTP/JSend | PSR-7 + `slim/psr7` factories | New `Adapter\Http\Slim\JSendResponse` | Response factory in `app/dependencies.php` | No — WF-011/014 authorized | — |
| Routing/URL | FastRoute + `RouteParser` (`urlFor`) | Portable `UrlGenerator` port | Route config in `app/routes.php` | No | URL generation translation |
| Validation | `symfony/validator` (stack) | Portable `ValidationService` | Validator container binding | No | Same field-error data shape |
| Auth | None native; starter middleware (WF-016) | HMAC/PHP-password/JWT adapters | Security middleware (WF-016) | No | WF-016 |
| Cache | `symfony/cache` (stack) | `PsrCache` | Cache container binding | No | — |
| Storage/filesystem | `league/flysystem` (stack) | `FlysystemStorage` | Flysystem container binding | No | — |
| File transfer | None native | `FtpFileTransport`, `SftpFileTransport` | Service config | No | — |
| Mail | `symfony/mailer` (stack) | `SymfonyMailTransport` family | Mailer container binding | No | — |
| SMS | **No native facility** | `TwilioSmsTransport` | Twilio container binding | No | — |
| Templating | `slim/twig-view` (Twig) | `TwigEngine`, `DelegatingEngine` | Twig container binding | No | — |
| Process | `symfony/process` (stack) | `SymfonyProcessRunner` | Process container binding | No | — |
| Scheduler | None native; messenger-based or portable | `Scheduler` + runner | Scheduler container binding; console schedule | No | WF-014 constructor repair |
| Observability | Monolog (stack); no metrics/health | `LoggingAuditLog`, `HealthReporter`/checks, StatsD/null metrics | Monolog container binding; health route | No | — |
| Socket/push | None native | `MercureHubPublisher` | Mercure container binding | No | — |
| DI/composition | `php-di/php-di` explicit container | Portable `Container` | `app/dependencies.php` definitions; `AppFactory::createFromContainer()` | No | — |

## Remaining unknowns

- The exact fixture directory layout and lock receipts (lowest/latest per framework and combined) are
  owned by the WF-014 fixture tickets and WF-017; this note fixes only the constraint sets.
- Whether the widen triggers arrive within the planning window (Symfony 8.2 ≈Nov 2026, Laravel 14 ≈Q1
  2027, Slim 5 stable, CodeIgniter 4.8) and when Fight Common itself must drop the oldest line after a
  widened window. A tighten trigger — dropping the previous line when the framework stops maintaining it,
  as CI4 already does — is also unset.
- Whether `yiisoft/queue` reaches a stable release within the planning window, and whether Laravel's
  handler map or its discovery is the default async composition.
- Whether CodeIgniter's official `codeigniter4/queue` and `codeigniter4/tasks` packages are adopted in the
  CI4 starter or the portable buses/Scheduler win the default.
- Exact native transaction and aggregate-hydration evidence for Eloquent, Yii DB, and CI Models — WF-017
  prototype territory (`../tickets/WF-017-persistence-unit-of-work-and-walking-slice-prototypes.md`).
- SPA host templating per starter (Blade/View/Twig) is a WF-013 product decision, not yet final.
- PHP 8.6 timing (Guzzle `<8.6`, Slim `~8.5.0`, Symfony polyfill-php86) and the Slim 5 / CodeIgniter 4.8 /
  Laravel 14 arrival will require re-resolving these ranges.

## Resolution boundary

This note supplies evidence and the exact supported dependency ranges and default compositions only. It
does not install or lock packages, create starters, or implement adapters. The supported-line decision,
`suggest` wording, fixture layout, and starter stacks were accepted through the HITL grilling flow
(`Grill-With-Docs-Skill`), which closed WF-015 and recorded the decisions in
[ADR 0020](../../adr/0020-supported-framework-lines-and-support-window.md) and
[ADR 0021](../../adr/0021-framework-default-capability-compositions.md).
