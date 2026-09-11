CodeIgniter applications select Fight Common capabilities from their own `app/Config/Services.php`. Fight Common
does not provide an aggregate CodeIgniter provider, route definitions, templates, mail content, credentials, or
operations policy.

## Ownership and installation

Require Fight Common, CodeIgniter, and only the provider packages used by the application. Add
`codeigniter4/queue` only when selecting the native Queue adapters; the Symfony Mailer, Twig, and Symfony
Filesystem packages are independent fallbacks rather than implicit framework requirements.

```bash
composer require johnnickell/fight-common codeigniter4/framework
```

The [project-codeigniter starter](https://github.com/johnnickell/project-codeigniter) owns the booted
application composition. Fight Common owns the reusable delegates and adapters. Application code continues to
depend on the portable contracts described by the component guides.

## Activate selected services

Expose only the methods the application uses from its `Config\\Services` subclass. The bounded delegates are
`MessagingServices`, `PersistenceServices`, `CacheServices`, `RoutingServices`, `MailServices`,
`TemplateServices`, and `FilesystemServices`. There is no discovery step and no all-capabilities provider.

## Native cache and routing

`CodeIgniterCache` adapts CodeIgniter's native `CacheInterface`, including cached `null` values, read-through TTL,
deletion, and clearing. The optional PSR cache bridge remains a valid composition for applications that deliberately
select it; it is neither required by nor a replacement for the native adapter.

```php
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Config\BaseService;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\CacheServices;
use Fight\Common\Application\Cache\MutableCache;

final class Services extends BaseService
{
    public static function fightMutableCache(bool $getShared = true): MutableCache
    {
        if ($getShared) {
            return static::getSharedInstance('fightMutableCache');
        }

        $cache = static::cache();
        assert($cache instanceof CacheInterface);

        return CacheServices::mutableCache($cache);
    }
}
```

`RoutingServices::routing()` composes `CodeIgniterUrlGenerator` with the application route collection and selected
base URL. It reverses named routes, appends RFC 3986 query values, and emits an absolute URL only when requested.

## Queue messaging

Install `codeigniter4/queue`, configure the application's Queue connection, and expose the selected command bus
or event dispatcher through `MessagingServices`. The adapter enqueues a complete Fight message. The configured
job resolves `CommandMessageHandler` or `EventMessageHandler`, which delegates to the application's synchronous
Fight bus or dispatcher.

Delivery is at least once. A retried event repeats its complete synchronous fan-out, so every handler must
tolerate duplicates. The application owns the Queue job aliases, queue names, broker, retry and failure policy,
worker supervision, and durable outbox design. Queue submission is not an atomic outbox.

## Native JSend response conversion

Pass the controller-owned native response to `JSendResponse`; it preserves the selected status and headers while
using CodeIgniter's unencoded JSON body path for the exact neutral envelope bytes.

```php
return \Fight\Common\Adapter\Http\CodeIgniter\JSendResponse::success(
    $this->response,
    $presentation,
    201,
    ['X-Request-ID' => $requestId],
);
```

## Proven fallbacks

CodeIgniter's native email and view APIs do not expose the full Fight mail metadata and template-helper contracts;
its native file object does not expose the complete filesystem operation set. Projects therefore compose the tested
fallbacks independently:

- `MailServices` returns `SymfonyMailFactory` and `SymfonyMailTransport`.
- `TemplateServices` returns the existing `TwigEngine` from an application-owned Twig environment.
- `FilesystemServices` returns `SymfonyFilesystem`.

CodeIgniter's native logger already implements PSR-3. Inject it directly into the existing logging adapters rather
than adding a Fight-branded wrapper. Guzzle/PSR-18, Flysystem, Symfony Process, Twilio, Mercure, health, audit, and
metrics retain their existing provider compositions.

## Operational ownership

Keep CodeIgniter routes, filters, error handling, credentials, cache backend, database transactions, Queue
workers, mail transport, templates, filesystem roots, and monitoring in the application. Health, audit, and
metrics are portable Fight capabilities; CodeIgniter supplies the configured PSR-3 logger and application
lifecycle around them.

Use the [framework support matrix](../framework-support/index.md) to distinguish shipped native adapters from
direct provider wiring and tested fallbacks. Use the [Messaging](../../components/messaging/index.md),
[Routing](../../components/routing/index.md), [Cache](../../components/cache/index.md), and
[Mail](../../components/mail/index.md) guides for their behavior and failure contracts.
