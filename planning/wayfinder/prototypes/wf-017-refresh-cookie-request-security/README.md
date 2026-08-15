# WF-017 refresh-cookie request-security prototype

> **PROTOTYPE — wipeable runtime evidence, not supported starter middleware.**

## Question

Can the five starter request boundaries enforce one fail-closed browser policy for refresh and logout requests
that carry the opaque refresh cookie, without trusting `Host`, `X-Forwarded-Host`, permissive CORS, or sibling
subdomains?

## Run

From this directory, install the retained dependencies and run the probe:

```bash
composer install
php run.php
```

The runner uses Symfony HttpFoundation, Illuminate HTTP, CodeIgniter HTTP, and the selected PSR-7 request
boundary for Yii and Slim. It writes one machine-readable receipt per framework under `receipts/`.

## Passing verdict

All five lanes accept a `POST` JSON request only when `Origin` exactly matches the explicitly configured
application origin. When `Sec-Fetch-Site` is present it must be `same-origin`; an exact `Origin` remains the
required fallback when Fetch Metadata is absent. `same-site`, `cross-site`, missing, and `null` origins fail
closed. A spoofed `X-Forwarded-Host` cannot redefine the trusted target.

Issue and clear an opaque, host-only `__Host-fight_refresh` cookie with `Path=/`, `Secure`, `HttpOnly`, and
`SameSite=Strict`, and no `Domain`. Refresh and logout remain same-origin browser endpoints with no credentialed
cross-origin CORS. The guard runs before loading or rotating the credential; a valid request with no cookie is
then unauthenticated.

This selects a small shared request-security decision with thin starter-owned request adapters. Laravel 13's
native origin-only request-forgery mode supplies its first native layer; a small starter guard retains the
stricter exact-`Origin` requirement shared with the other middleware/filter pipelines. It does not justify a
Fight Common runtime abstraction.

The policy follows the OWASP CSRF guidance for Fetch Metadata, exact source/target origin checks, and
host-only `SameSite` cookies, plus Laravel 13's documented origin-verification mode:

- <https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html>
- <https://laravel.com/docs/13.x/csrf#origin-verification>

## Deliberate limits

- This is native request-object seam evidence, not five booted application kernels or verified middleware order.
- It does not prove browser cookie behavior, TLS termination, trusted-proxy framework configuration, CORS
  response headers, multi-tab refresh coordination, token rotation, or database locking.
- Non-browser clients should use bearer credentials; they do not receive an exception to the cookie endpoint's
  exact-origin policy.
- Production origin configuration, local-development HTTPS, browser automation, and human UAT remain
  consumer-project evidence.
