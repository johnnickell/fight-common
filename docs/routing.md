# Routing

No stand-alone router is provided. The `UrlGenerator` interface allows application-layer services to generate URLs without coupling to a specific framework. The Symfony adapter is included for projects that use Symfony's routing.

```
Application\Routing
├── UrlGenerator (interface)
└── Exception\
    ├── UrlGenerationException
    ├── RouteNotFoundException
    ├── MissingParametersException
    └── InvalidParameterException

Adapter\Routing
└── SymfonyUrlGenerator
```

---

## Table of Contents

1. [UrlGenerator Interface](#urlgenerator-interface)
2. [SymfonyUrlGenerator](#symfonyurlgenerator)
3. [Exceptions](#exceptions)

---

## UrlGenerator Interface

`Fight\Common\Application\Routing\UrlGenerator`

```php
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

`Fight\Common\Adapter\Routing\SymfonyUrlGenerator`

Wraps `Symfony\Component\Routing\Generator\UrlGeneratorInterface`. Translates Symfony's routing exceptions into the application-layer exception hierarchy.

```php
use Fight\Common\Adapter\Routing\SymfonyUrlGenerator;
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

```php
use Fight\Common\Application\Routing\Exception\UrlGenerationException;

try {
    $url = $generator->generate('user_show', ['id' => 5]);
} catch (UrlGenerationException $e) {
    // handle error
}
```
