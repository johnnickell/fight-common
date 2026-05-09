# Templating

A `TemplateEngine` contract with three implementations — `PhpEngine` (native PHP templates with inheritance and blocks), `TwigEngine` (wraps Twig), and `DelegatingEngine` (routes by file extension). View helpers are injectable via the `TemplateHelper` contract.

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

```php
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

```php
interface TemplateHelper
{
    public function getName(): string;
}
```

Helpers are identified by name and registered on an engine. Each implementation retrieves the helper by name and makes it available in the template context. A helper can provide any number of public methods for use in templates.

```php
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

```php
$engine->addHelper(new AssetHelper());
```

---

## PhpEngine

`Fight\Common\Adapter\Templating\PhpEngine`

A full native PHP template engine with template inheritance, a block system, HTML escaping, and name-based helper access. No external dependencies.

### Construction

```php
use Fight\Common\Adapter\Templating\PhpEngine;

$engine = new PhpEngine(
    paths: ['/var/www/templates', '/var/www/vendor/templates'],
    helpers: [new AssetHelper()]
);
```

Paths are searched in order. The colon separator in template names is converted to `DIRECTORY_SEPARATOR`:
`Controller:action.php` → `Controller/action.php`.

### Rendering

```php
$engine->render('Controller:action.php', ['name' => 'Alice']);
```

The data array is extracted into the template scope. The key `this` is reserved and throws `TemplatingException` if present.

### Template Inheritance

A child template declares its parent with `$this->extends()`:

```php
<!-- Controller/action.php -->
<?php $this->extends('Layout:base.php'); ?>

<?php $this->startBlock('content'); ?>
<h1>Hello, <?= $this->escape($name) ?></h1>
<?php $this->endBlock(); ?>
```

```php
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

```php
$this->escape($userInput);   // htmlspecialchars with ENT_QUOTES | ENT_SUBSTITUTE, UTF-8
```

### Helper Access

```php
$this->has('asset');         // bool
$this->get('asset');         // TemplateHelper instance
$this->get('asset')->path('style.css');
```

Throws `TemplatingException` if the helper is not registered.

### Template Loading & Caching

Templates are resolved to absolute file paths on first access and cached internally. `loadTemplate()` → `getTemplatePath()` iterates the configured paths and returns the first readable file match. Throws `TemplateNotFoundException` if no path matches.

```php
$engine->exists('Controller:action.php');     // checks all paths
```

---

## TwigEngine

`Fight\Common\Adapter\Templating\TwigEngine`

Wraps a Twig `Environment`. Supports `.twig` templates.

```php
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

```php
$engine->addHelper(new AssetHelper());
// In Twig: {{ asset.path('style.css') }}
```

---

## DelegatingEngine

`Fight\Common\Adapter\Templating\DelegatingEngine`

Routes templates to sub-engines based on the `supports()` check. Useful when a project uses multiple template formats.

```php
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

```php
$engine->addHelper(new AssetHelper());
// On render: $resolvedEngine->addHelper($helper) is called for each stored helper
```

This means sub-engines only receive helpers when they actually render, and each sub-engine gets all the delegates' helpers.

### Routing

`getEngine()` iterates sub-engines in order and returns the first match:

```php
$engine->supports('page.php');       // true (PhpEngine supports .php)
$engine->exists('page.php');         // false if no path can resolve it
```

Throws `TemplatingException` if no engine `supports()` the template.

---

## Exceptions

`Fight\Common\Application\Templating\Exception`

| Exception | Extends | Purpose |
|---|---|---|
| `TemplatingException` | `SystemException` | Base for all templating errors |
| `TemplateNotFoundException` | `TemplatingException` | Template file could not be resolved |
| `DuplicateHelperException` | `TemplatingException` | Two helpers registered with the same name |

```php
throw TemplateNotFoundException::fromName('Controller:missing.php');
// "Template not found: Controller:missing.php"

throw DuplicateHelperException::fromName('asset');
// "Duplicate helper: asset"
```

- `TemplateNotFoundException::getTemplate(): ?string`
- `DuplicateHelperException::getName(): ?string`
