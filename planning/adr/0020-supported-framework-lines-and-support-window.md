# ADR 0020: Supported Framework Lines and the Current-Only Support Window

- Status: accepted
- Date: 2026-08-12

## Decision

Fight Common supports each consumer framework's **current stable major line only**, then widens to
**current + previous** when the framework ships its next major version, and tightens (drops the oldest
line) when the framework stops maintaining it. The supported window is never wider than two majors per
framework. This is a deliberate Fight Common policy, not the frameworks' own policies: CodeIgniter 4
officially maintains only the latest line, Yii 3 has no line model, and Slim 3 is architecturally
incompatible with the PSR-7 v2 contracts Fight Common already uses.

The selected supported lines and exact Composer constraints are:

| Framework | Supported range (current-only now) | Widen trigger (current + previous) | Excluded |
| --- | --- | --- | --- |
| Symfony | components `^8.1` | `^8.2 || ^8.1` when 8.2 ships (≈Nov 2026) | 7.4 LTS, 6.4 |
| Laravel | `laravel/framework ^13.0` | `^14.0 || ^13.0` when Laravel 14 ships (≈Q1 2027) | 12.x, 11.x |
| Yii 3 | current `yiisoft/*` package set (`yiisoft/di ^1.4`, `yiisoft/config ^1.6`, `yiisoft/yii-http ^1.1`, `yiisoft/event-dispatcher ^1.1`, `yiisoft/router ^4.0`, `yiisoft/router-fastroute`, `yiisoft/view`, `yiisoft/view-twig`, `yiisoft/validator`, `yiisoft/mailer ^6.1`, `yiisoft/cache ^3.2`, `yiisoft/db` + `yiisoft/db-mysql`/`yiisoft/db-pgsql`, `yiisoft/session`, `yiisoft/log`) | Not applicable — no Yii 3 line model | `yiisoft/yii-web` (unreleased), Yii 2.0 |
| CodeIgniter 4 | `codeigniter4/framework ^4.7` | `^4.7` already admits future 4.x minors; CI4 maintains only the latest line | 4.6.x (EOL, no PHP 8.5 support) |
| Slim | `slim/slim ^4.15` + the opinionated stack (ADR 0021) | `^5.0 || ^4.15` when Slim 5 reaches stable | 3.x (PSR-7 v1) |

Fight Common's own Symfony components pin the current **`^8.1`** line. Laravel 13's `^7.4 || ^8.0`
Symfony floor and the Slim stack's `^7.4 || ^8.0` floors both resolve to Symfony 8.1 under PHP 8.5, so no
lower Symfony floor is needed while the window is current-only. All ranges are PHP-8.5-current with a
PHP 8.6 re-resolution horizon (Guzzle `<8.6`, Slim `~8.5.0`, Symfony `polyfill-php86`).

Composer verification uses the five real starter repositories, each requiring the Fight Common candidate
plus its own framework constraint set and resolving lowest and latest. Fight Common's root `require-dev`
proves only Fight Common's own adapter dependency graph; it is not a combined starter project. When a widen
trigger fires, the owning starter repository proves the newly previous line before the widened range is
published. A contradictory line (Laravel 12 caps Symfony at `^7.2`; Slim 3 caps at PSR-7 v1) remains outside
the supported window while Fight Common pins Symfony `^8.1`.

## Consequences

The certification burden stays small while the initial repository lanes land, because only one line per
framework is proven initially. The ranges are time-sensitive: maintenance status drives the widen and
tighten triggers, and both dates are tracked as explicit policy inputs rather than inferred from tag
ordering. Dropping a line is a deliberate, documented event, not a silent constraint change.

The ranges are PHP-8.5-current with a PHP 8.6 horizon. Arrival of PHP 8.6, Slim 5, CodeIgniter 4.8, or
Laravel 14 requires rerunning the affected repository-owned resolutions before publication.

## Rejected Alternatives

Certifying the frameworks' own maintained lines (Symfony 7.4 LTS, Laravel 12, CodeIgniter 4.6, Slim 3)
was rejected because the previous lines contradict Fight Common's current Symfony or PSR-7 floor and,
in the case of CodeIgniter 4.6, an EOL line without PHP 8.5 support.

Adopting the widened `^new || ^current` union immediately was rejected because the current-only window
simplifies the initial repository-owned support lanes and the union is a drop-in only when the framework's next major
ships.

Widening the window beyond two majors or never tightening was rejected because Fight Common certifies
every supported line with repository-owned tests; an unbounded window multiplies that burden without matching
consumer need.
