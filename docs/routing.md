# Routing

No stand-alone router is provided. The `UrlGenerator` interface allows application-layer services to
generate URLs without coupling to a specific framework. Adapters are shipped for Symfony, Laravel,
Yii, Slim, and CodeIgniter.

```
Application\Routing
├── UrlGenerator (interface)
└── Exception\
    ├── UrlGenerationException
    ├── RouteNotFoundException
    ├── MissingParametersException
    └── InvalidParameterException

Adapter\Routing
├── Symfony\SymfonyUrlGenerator
├── Laravel\LaravelUrlGenerator
├── Yii\YiiUrlGenerator
├── Slim\SlimUrlGenerator
└── CodeIgniter\CodeIgniterUrlGenerator
```

---

## Table of Contents

1. [UrlGenerator Interface](#urlgenerator-interface)
2. [SymfonyUrlGenerator](#symfonyurlgenerator)
3. [Exceptions](#exceptions)

---

## UrlGenerator Interface

`Fight\Common\Application\Routing\UrlGenerator`

```php-inline
interface UrlGenerator
{
    public function generate(
        string $name,
        array $parameters = [],
        array $query = [],
        bool $absolute = false
    ): string;
}
```

| Parameter | Description |
|---|---|
| `$name` | The route name (e.g. `user_show`) |
| `$parameters` | Route requirement values (e.g. `['id' => 5]` → `/user/5`) |
| `$query` | Extra query parameters appended after `?` (e.g. `['page' => 2]`) |
| `$absolute` | `true` for absolute URL, `false` for relative path |

Throws `UrlGenerationException` (or a subclass) on failure.

---

## SymfonyUrlGenerator

`Fight\Common\Adapter\Routing\Symfony\SymfonyUrlGenerator`

Wraps `Symfony\Component\Routing\Generator\UrlGeneratorInterface`. Translates Symfony's routing exceptions into the application-layer exception hierarchy.

```php-inline
use Fight\Common\Adapter\Routing\Symfony\SymfonyUrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

$inner = new UrlGeneratorInterface(/* ... */);
$generator = new SymfonyUrlGenerator($inner);

// Relative path
$generator->generate('user_show', ['id' => 5]);
// → /user/5

// Absolute URL with query
$generator->generate('search', ['q' => 'hello'], ['page' => 2], absolute: true);
// → https://example.com/search/hello?page=2
```

### Exception Mapping

| Symfony Exception | Application Exception |
|---|---|
| `RouteNotFoundException` | `RouteNotFoundException` |
| `MissingMandatoryParametersException` | `MissingParametersException` |
| `InvalidParameterException` | `InvalidParameterException` |
| Any other `Throwable` | `UrlGenerationException` |

## Framework adapters

All adapters accept route parameters separately from query parameters and translate native failures
into the Fight exception hierarchy, but their native routers are not format-compatible:

- `LaravelUrlGenerator` uses named Laravel routes, optionally validates supplied values against the
  route collection's constraints, and appends RFC 3986 query parameters.
- `YiiUrlGenerator` delegates relative or absolute generation to Yii's native generator and preserves
  Yii's separate query argument.
- `SlimUrlGenerator` uses Slim's route parser and a consumer-supplied base URI; it stringifies route
  and query values because Slim's parser contract is string-based.
- `CodeIgniterUrlGenerator` reverses a named route using positional parameter values and joins the
  configured base URL only for absolute output.

Laravel and Yii include service providers; CodeIgniter exposes `RoutingServices`; Slim and Symfony
are explicit composition. Route declaration, host trust, scheme, base URL, and request-context policy
remain owned by the consuming application.

---

## Exceptions

`Fight\Common\Application\Routing\Exception`

```
UrlGenerationException extends SystemException   (base)
├── RouteNotFoundException                       (route name does not exist)
├── MissingParametersException                   (mandatory parameters not provided)
└── InvalidParameterException                    (parameter type or value mismatch)
```

Catch `UrlGenerationException` to handle any URL generation failure:

```php-inline
use Fight\Common\Application\Routing\Exception\UrlGenerationException;

try {
    $url = $generator->generate('user_show', ['id' => 5]);
} catch (UrlGenerationException $e) {
    // handle error
}
```
