# CodeIgniter capability composition

CodeIgniter applications select Fight Common capabilities from their own `app/Config/Services.php`. Fight Common
does not provide an aggregate CodeIgniter provider, route definitions, templates, mail content, credentials, or
operations policy.

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
