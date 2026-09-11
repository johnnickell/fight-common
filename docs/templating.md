# Templating

A `TemplateEngine` contract with native PHP, Twig, Laravel Blade, and Yii View implementations,
plus a `DelegatingEngine` that routes by supported template name. View helpers are injectable via
the `TemplateHelper` contract.

```
Application\Templating
├── TemplateEngine (interface)
├── TemplateHelper (interface)
└── Exception\
    ├── TemplatingException
    ├── TemplateNotFoundException
    └── DuplicateHelperException

Adapter\Templating
├── PhpEngine             — native PHP with extends/blocks
├── TwigEngine            — Twig adapter
├── Laravel\LaravelBladeTemplateEngine — Laravel Blade adapter
├── Yii\YiiTemplateEngine — Yii View adapter
└── DelegatingEngine      — routes to sub-engines by supports()
```

---

## Table of Contents

1. [TemplateEngine Interface](#templateengine-interface)
2. [TemplateHelper Interface](#templatehelper-interface)
3. [PhpEngine](#phpengine)
4. [TwigEngine](#twigengine)
5. [DelegatingEngine](#delegatingengine)
6. [Exceptions](#exceptions)

---

## TemplateEngine Interface

`Fight\Common\Application\Templating\TemplateEngine`

```php-inline
interface TemplateEngine
{
    public function render(string $template, array $data = []): string;
    public function exists(string $template): bool;
    public function supports(string $template): bool;
    public function addHelper(TemplateHelper $helper): void;
    public function hasHelper(TemplateHelper $helper): bool;
}
```

- `render()` — evaluates the template with the given data, returns the output string
- `exists()` — checks whether the template can be resolved
- `supports()` — checks whether this engine can handle the template (typically by file extension)
- `addHelper()` / `hasHelper()` — manage named view helpers

Throws `TemplatingException` on render failure.

---

## TemplateHelper Interface

`Fight\Common\Application\Templating\TemplateHelper`

```php-inline
interface TemplateHelper
{
    public function getName(): string;
}
```

Helpers are identified by name and registered on an engine. Each implementation retrieves the helper by name and makes it available in the template context. A helper can provide any number of public methods for use in templates.

```php-inline
use Fight\Common\Application\Templating\TemplateHelper;

final class AssetHelper implements TemplateHelper
{
    public function getName(): string
    {
        return 'asset';
    }

    public function path(string $name): string
    {
        return '/assets/' . $name;
    }
}
```

Registered on any engine:

```php-inline
$engine->addHelper(new AssetHelper());
```

---

## PhpEngine

`Fight\Common\Adapter\Templating\PhpEngine`

A full native PHP template engine with template inheritance, a block system, HTML escaping, and name-based helper access. No external dependencies.

### Construction

```php-inline
use Fight\Common\Adapter\Templating\PhpEngine;

$engine = new PhpEngine(
    paths: ['/var/www/templates', '/var/www/vendor/templates'],
    helpers: [new AssetHelper()]
);
```

Paths are searched in order. The colon separator in template names is converted to `DIRECTORY_SEPARATOR`:
`Controller:action.php` → `Controller/action.php`.

### Rendering

```php-inline
$engine->render('Controller:action.php', ['name' => 'Alice']);
```

The data array is extracted into the template scope. The key `this` is reserved and throws `TemplatingException` if present.

### Template Inheritance

A child template declares its parent with `$this->extends()`:

```php-inline
<!-- Controller/action.php -->
<?php $this->extends('Layout:base.php'); ?>

<?php $this->startBlock('content'); ?>
<h1>Hello, <?= $this->escape($name) ?></h1>
<?php $this->endBlock(); ?>
```

```php-inline
<!-- Layout/base.php -->
<!DOCTYPE html>
<html>
<body>
<?php $this->outputContent('content', 'Default content'); ?>
</body>
</html>
```

`extends()` must be called at the top of the template. The engine resolves the parent chain recursively — a parent can itself extend another template.

### Block System

| Method | Purpose |
|---|---|
| `startBlock(string $name)` | Begins capturing output into a named block |
| `endBlock()` | Stops capturing, stores content (first-definition-wins) |
| `hasBlock(string $name)` | Checks if a block is defined |
| `setContent(string $name, string $content)` | Overwrites block content programmatically |
| `getContent(string $name, ?string $default): ?string` | Retrieves block content |
| `outputContent(string $name, ?string $default): bool` | Echoes block content |

**First-definition-wins semantics:** When a child overrides a block, the child's content is used. If the child does not override, the parent's content (set via `startBlock`/`endBlock` in the parent) persists. This is enforced by `endBlock()` only storing content when `$this->blocks[$name]` is empty.

### Escaping

```php-inline
$this->escape($userInput);   // htmlspecialchars with ENT_QUOTES | ENT_SUBSTITUTE, UTF-8
```

### Helper Access

```php-inline
$this->has('asset');         // bool
$this->get('asset');         // TemplateHelper instance
$this->get('asset')->path('style.css');
```

Throws `TemplatingException` if the helper is not registered.

### Template Loading & Caching

Templates are resolved to absolute file paths on first access and cached internally. `loadTemplate()` → `getTemplatePath()` iterates the configured paths and returns the first readable file match. Throws `TemplateNotFoundException` if no path matches.

```php-inline
$engine->exists('Controller:action.php');     // checks all paths
```

---

## TwigEngine

`Fight\Common\Adapter\Templating\TwigEngine`

Wraps a Twig `Environment`. Supports `.twig` templates.

```php-inline
use Fight\Common\Adapter\Templating\TwigEngine;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader('/var/www/templates');
$twig = new Twig\Environment($loader);

$engine = new TwigEngine($twig);
```

| Method | Behavior |
|---|---|
| `render()` | Delegates to `$environment->render()`, wraps Twig errors in `TemplatingException` |
| `exists()` | Delegates to `$environment->getLoader()->exists()` |
| `supports()` | Returns `true` for templates ending in `.twig` |
| `addHelper()` | Stores the helper and adds it as a Twig global (`$environment->addGlobal($name, $helper)`) |

```php-inline
$engine->addHelper(new AssetHelper());
// In Twig: {{ asset.path('style.css') }}
```

---

## DelegatingEngine

`Fight\Common\Adapter\Templating\DelegatingEngine`

Routes templates to sub-engines based on the `supports()` check. Useful when a project uses multiple template formats.

```php-inline
use Fight\Common\Adapter\Templating\DelegatingEngine;
use Fight\Common\Adapter\Templating\PhpEngine;
use Fight\Common\Adapter\Templating\TwigEngine;

$engine = new DelegatingEngine([
    new PhpEngine(['/var/www/templates']),
    new TwigEngine($environment),
]);

// Routes by file extension
$engine->render('page.php');          // → PhpEngine
$engine->render('page.html.twig');    // → TwigEngine
```

### Helper Injection

Helpers registered on the `DelegatingEngine` are not immediately forwarded to sub-engines. Instead, they are stored locally and lazily injected into the resolved sub-engine at `render()` time:

```php-inline
$engine->addHelper(new AssetHelper());
// On render: $resolvedEngine->addHelper($helper) is called for each stored helper
```

This means sub-engines only receive helpers when they actually render, and each sub-engine gets all the delegates' helpers.

### Routing

`getEngine()` iterates sub-engines in order and returns the first match:

```php-inline
$engine->supports('page.php');       // true (PhpEngine supports .php)
$engine->exists('page.php');         // false if no path can resolve it
```

Throws `TemplatingException` if no engine `supports()` the template.

## Framework composition

- Laravel's `ViewServiceProvider` binds `TemplateEngine` to `LaravelBladeTemplateEngine` using the
  configured view factory and consumer-owned template root. It supports only `.blade.php` names,
  verifies the resolved file remains beneath that root, and exposes helpers through Laravel shared data.
- Yii's `ViewServiceProvider` binds `YiiTemplateEngine` after
  `YiiCapabilityConfiguration::view()` supplies the native view and template root. It supports PHP
  templates, clears Yii view state for each render, and sets helpers as view parameters.
- CodeIgniter ships a proven Twig fallback through `TemplateServices::templateEngine()`; it does not
  claim native view-format parity.
- Symfony, Slim, and framework-free applications compose `PhpEngine`, `TwigEngine`, or
  `DelegatingEngine` explicitly.

Template syntax is not portable across these engines. The portable seam is `render()`, `exists()`,
`supports()`, and helper registration. Select the engine in the composition root and keep template
names compatible with that selected implementation.

---

## Exceptions

`Fight\Common\Application\Templating\Exception`

| Exception | Extends | Purpose |
|---|---|---|
| `TemplatingException` | `SystemException` | Base for all templating errors |
| `TemplateNotFoundException` | `TemplatingException` | Template file could not be resolved |
| `DuplicateHelperException` | `TemplatingException` | Two helpers registered with the same name |

```php-inline
throw TemplateNotFoundException::fromName('Controller:missing.php');
// "Template not found: Controller:missing.php"

throw DuplicateHelperException::fromName('asset');
// "Duplicate helper: asset"
```

- `TemplateNotFoundException::getTemplate(): ?string`
- `DuplicateHelperException::getName(): ?string`
