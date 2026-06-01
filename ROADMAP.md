# Roadmap

## Milestone A — PhpEngine Hardening ✅

`src/Adapter/Templating/PhpEngine.php` had several correctness, security, and design issues that have been addressed:

- **Output buffer leak on exception** — `evaluate()` now wraps `require` in try/finally so `ob_get_clean()` is always called even when the template throws
- **`ob_get_clean()` null-check** — `endBlock()` and `evaluate()` both guard against `ob_get_clean()` returning `false` (throws `TemplatingException`)
- **Path traversal protection** — `getTemplatePath()` and `exists()` validate resolved paths against allowed base directories via `realpath()`; symlink escapes raise `TemplateNotFoundException`
- **Hash cache key** — replaced `hash('sha256', $file)` with the direct file path as the cache key, avoiding unnecessary hashing
- **Parent-template inheritance** — the dead `@codeCoverageIgnore`d code in `render()` was properly implemented: `extends()` called during template evaluation now triggers parent rendering, enabling layout inheritance via child blocks
- **`$template` mutation bug** — `getTemplatePath()` no longer mutates the `$template` parameter inside the `foreach` loop (uses a local `$resolved` variable instead)
- **`TemplatingException` coverage** — added dedicated test with `#[CoversClass]` attribution

---

## Milestone B — Observability Layer ✅

The library has decorator-pattern logging (HTTP client, mail transport, cache) but no architectural observability contracts. This milestone makes observability a first-class concern at the application layer, with adapters providing the infrastructure.

### Health checks

`src/Application/Observability/HealthCheck.php`

```
interface HealthCheck
{
    public function check(): HealthStatus;
    public function name(): string;
}
```

An aggregator collects `N` health check implementations and returns a `HealthReport` (overall status + per-check results). Adapter implementations: database ping, queue depth, external HTTP endpoint reachability. The port lives in `Application/`; adapters live in `Adapter/`.

### Metrics

`src/Application/Observability/MetricsCollector.php`

```
interface MetricsCollector
{
    public function increment(string $metric, array $tags = []): void;
    public function gauge(string $metric, float $value, array $tags = []): void;
    public function histogram(string $metric, float $value, array $tags = []): void;
}
```

Command bus and query bus middleware inject this automatically, emitting handler latency and error counts without touching handler code. Adapters: null (default), Prometheus, StatsD.

### Audit log

`src/Application/Observability/AuditLog.php`

Structured business-fact records — `who`, `what`, `when`, `context` — distinct from PSR-3 debug logging. Audit entries are meaningful domain events, not implementation noise. AI tools can query audit logs to understand system state, replay events, or diagnose anomalies.

### AI tool access

Exposing `HealthReport` + `AuditLog` over an HTTP endpoint (secured with HMAC, see Milestone C) gives AI agents read access to system state without SSH or log file access. The agent can assess health before making changes, verify a deployment succeeded, or surface anomalies.

---

## Milestone C — HMAC-Secured AI Operations API ✅

Build on the existing HMAC auth adapters (`src/Adapter/Auth/Hmac/`):

- **`WebhookDispatcher`** — signs outbound webhook payloads with HMAC; the receiving end uses the existing `HmacAuthenticator` to verify
- **AI operations request format** — standardize `{action, payload, timestamp, signature}`; example actions: `health_check`, `clear_cache`, `run_migration`, `deploy`
- **Nonce tracking** — `HmacAuthenticator` already validates timestamp tolerance; add nonce storage to prevent replay within that window
- **Fix inconsistent error handling** — `HmacAuthenticator` throws for most failures but returns `false` for signature mismatch; standardize to always throw

This is the "local to production updates with AI assistance" pattern: the AI agent signs requests with a pre-shared key, the production system verifies the signature and executes the action. HMAC ensures the request originated from an authorized source and was not tampered with in transit.

---

## Milestone D — Static Analysis & Code Quality

Add PHPStan static analysis to catch type errors, dead code, and implicit `mixed` types before they reach runtime:

- Configure PHPStan at level 6, scanning `src/`
- Add to CI workflow
- Fix all issues found
- Ratchet level up to max, fixing iteratively
- Decide on bleeding-edge ruleset by comparing error counts

---

## Milestone E — SMS Notification Adapters

Port definitions and adapter implementations for sending SMS messages:

- `Application/Notifications/SmsSender.php` — send interface
- `Application/Notifications/SmsMessage.php` — message value object
- `Adapter/Notifications/TwilioSmsSender.php`
- `Adapter/Notifications/AwsSnsSmsSender.php`

Adapters will be designed after Milestone D is complete.
