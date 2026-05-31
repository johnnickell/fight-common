# Roadmap

## v1.1 — PhpEngine Hardening ✅

`src/Adapter/Templating/PhpEngine.php` had several correctness, security, and design issues that have been addressed:

- **Output buffer leak on exception** — `evaluate()` now wraps `ob_start()/require/ob_get_clean()` in try/finally so the buffer is always cleaned up even when the template throws
- **`ob_get_clean()` null-check** — `endBlock()` now guards against `ob_get_clean()` returning `false` (throws `TemplatingException`)
- **Path traversal protection** — `getTemplatePath()` validates resolved paths against allowed base directories via `realpath()`
- **Hash cache key** — replaced `hash('sha256', $file)` with the direct file path as the cache key
- **Parent-template inheritance** — the dead `@codeCoverageIgnore`d code in `render()` was properly implemented: `extends()` called during template evaluation now triggers parent rendering, enabling layout inheritance via child blocks
- **`$template` mutation bug** — `getTemplatePath()` no longer mutates the `$template` parameter inside the `foreach` loop
- **`TemplatingException` coverage** — added dedicated test with `#[CoversClass]` attribution

---

## v1.2 — Observability Layer ✅

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

Exposing `HealthReport` + `AuditLog` over an HTTP endpoint (secured with HMAC, see v1.3) gives AI agents read access to system state without SSH or log file access. The agent can assess health before making changes, verify a deployment succeeded, or surface anomalies.

---

## v1.3 — HMAC-Secured AI Operations API ✅

Build on the existing HMAC auth adapters (`src/Adapter/Auth/Hmac/`):

- **`WebhookDispatcher`** — signs outbound webhook payloads with HMAC; the receiving end uses the existing `HmacAuthenticator` to verify
- **AI operations request format** — standardize `{action, payload, timestamp, signature}`; example actions: `health_check`, `clear_cache`, `run_migration`, `deploy`
- **Nonce tracking** — `HmacAuthenticator` already validates timestamp tolerance; add nonce storage to prevent replay within that window
- **Fix inconsistent error handling** — `HmacAuthenticator` throws for most failures but returns `false` for signature mismatch; standardize to always throw

This is the "local to production updates with AI assistance" pattern: the AI agent signs requests with a pre-shared key, the production system verifies the signature and executes the action. HMAC ensures the request originated from an authorized source and was not tampered with in transit.

---

## v1.4 — Transport Adapters

Restore adapters from earlier versions of the library when a concrete use case arises:

- SMS messaging (Twilio, AWS SNS)
- SMTP file transfer
- FTP adapter

These are deferred until needed.
