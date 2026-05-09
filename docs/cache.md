# Cache

A cache-through abstraction with a single operation: fetch a value by key, invoking a
loader callback on miss. The Application layer defines the port; the Adapter wraps any
PSR-6 cache pool.

```
Application\Cache
├── Cache (interface)       — read(string $key, callable $loader, int $ttl): mixed
└── Exception\
    └── CacheException       — extends SystemException

Adapter\Cache
└── PsrCache                — Cache → PSR-6 CacheItemPoolInterface
```

---

## Table of Contents

1. [Cache (Interface)](#cache-interface)
2. [PsrCache](#psrcache)
3. [CacheException](#cacheexception)
4. [Symfony Configuration](#symfony-configuration)
5. [Usage Examples](#usage-examples)

---

## Cache (Interface)

`Fight\Common\Application\Cache\Cache`

A single-method port that implements the cache-through pattern: if a value is cached,
return it; otherwise invoke `$loader()`, store the result, and return it.

```php
interface Cache
{
    /**
     * Fetches data from cache or loader function
     *
     * Callback signature:
     * function (): mixed {}
     *
     * @throws CacheException When an error occurs
     */
    public function read(string $key, callable $loader, int $ttl): mixed;
}
```

| Parameter | Type | Description |
|---|---|---|
| `$key` | `string` | Cache key |
| `$loader` | `callable` | Invoked on cache miss to produce the value |
| `$ttl` | `int` | Time-to-live in seconds |

---

## PsrCache

`Fight\Common\Adapter\Cache\PsrCache`

Wraps any PSR-6 `CacheItemPoolInterface` and a PSR-3 `LoggerInterface`. This is the sole
adapter implementation.

```php
final readonly class PsrCache implements Cache
{
    public function __construct(
        private CacheItemPoolInterface $cachePool,
        private LoggerInterface $logger
    ) {}
}
```

### Read flow

1. `$cachePool->getItem($key)` — fetch from pool
2. **Cache hit** → log `Cache HIT: "<key>"` at DEBUG, return `$cacheItem->get()`
3. **Cache miss** → log `Cache MISS: "<key>"` at DEBUG
   - Invoke `$loader()` to produce the value
   - `$cacheItem->set($results)` — store the value
   - `$cacheItem->expiresAfter($ttl)` — set TTL
   - `$cachePool->save($cacheItem)` — persist
   - Return `$cacheItem->get()`

All exceptions are caught and wrapped in `CacheException`.

---

## CacheException

`Fight\Common\Application\Cache\Exception\CacheException`

```php
class CacheException extends SystemException {}
```

An empty exception class. Thrown when any error occurs during cache read (pool failure,
loader failure, logger failure).

---

## Symfony Configuration

```yaml
# config/packages/common_cache.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    # --- PSR-6 cache pool (example: Symfony Cache) ---
    Symfony\Component\Cache\Adapter\AdapterInterface:
        class: Symfony\Component\Cache\Adapter\FilesystemAdapter
        arguments:
            - 'app.cache'
            - 0
            - '%kernel.cache_dir%/pools'

    Psr\Cache\CacheItemPoolInterface:
        alias: Symfony\Component\Cache\Adapter\AdapterInterface

    # --- PsrCache adapter ---
    Fight\Common\Adapter\Cache\PsrCache:
        arguments:
            - '@Psr\Cache\CacheItemPoolInterface'
            - '@logger'

    # --- Interface alias ---
    Fight\Common\Application\Cache\Cache:
        alias: Fight\Common\Adapter\Cache\PsrCache
```

---

## Usage Examples

### Caching a Database Query

```php
use Fight\Common\Application\Cache\Cache;

class UserRepository
{
    public function __construct(
        private Cache $cache,
        private Connection $db
    ) {}

    public function findById(int $id): ?array
    {
        return $this->cache->read(
            sprintf('user.%d', $id),
            fn () => $this->db->fetchAssociative('SELECT * FROM users WHERE id = ?', [$id]) ?: null,
            300  // 5 minutes
        );
    }
}
```

### Caching an API Response

```php
class WeatherService
{
    public function __construct(private Cache $cache, private HttpService $http) {}

    public function getForecast(string $city): array
    {
        return $this->cache->read(
            "weather.{$city}",
            function () use ($city) {
                $response = $this->http->send(
                    $this->http->createRequest('GET', "/weather/{$city}")
                );
                return json_decode((string) $response->getBody(), true);
            },
            600  // 10 minutes
        );
    }
}
```

### Testing with ArrayAdapter

```php
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Fight\Common\Adapter\Cache\PsrCache;

$pool   = new ArrayAdapter();
$logger = new NullLogger();
$cache  = new PsrCache($pool, $logger);

// First call — invokes loader
$result = $cache->read('key', fn () => 'computed', 60);
self::assertSame('computed', $result);

// Second call — returns cached value; loader is NOT invoked
$loader = $this->createMock(Callable::class);
$loader->expects($this->never())->method('__invoke');
$result = $cache->read('key', $loader, 60);
self::assertSame('computed', $result);
```
