# Dependency Injection Container

A lightweight PSR-11 compatible DI container for stand-alone applications. When using Symfony, prefer its DI container — this is for no-framework contexts such as CLI scripts, daemons, micro-applications, or testing harnesses.

```
Application\Service
├── Container                     (implements PSR-11 ContainerInterface + ArrayAccess)
└── Exception\NotFoundException   (implements PSR-11 NotFoundExceptionInterface)
```

---

## Table of Contents

1. [When to Use This Container](#when-to-use-this-container)
2. [PSR-11 Compliance](#psr-11-compliance)
3. [Services (Shared)](#services-shared)
4. [Factories (Prototype)](#factories-prototype)
5. [Parameters](#parameters)
6. [ArrayAccess](#arrayaccess)
7. [Complete Example](#complete-example)
8. [NotFoundException](#notfoundexception)

---

## When to Use This Container

- Stand-alone CLI scripts or daemons that need simple wiring
- Micro-applications where Symfony's full DI is overkill
- Testing harnesses that need a lightweight service locator
- **Not needed** when you already have Symfony's container — use theirs instead

---

## PSR-11 Compliance

`Fight\Common\Application\Service\Container` implements `Psr\Container\ContainerInterface`:

```php
public function get(string $id): mixed;
public function has(string $id): bool;
```

`get()` throws `NotFoundException` (implements `Psr\Container\NotFoundExceptionInterface`) when the requested service ID has not been registered.

---

## Services (Shared)

`set(string $id, callable $factory): void`

The factory receives the container as its only argument. The return value is cached — subsequent `get($id)` calls return the **same instance**.

```php
use Fight\Common\Application\Service\Container;

$container = new Container();

$container->set('logger', function (Container $c): LoggerInterface {
    return new Monolog\Logger('app');
});

$logger = $container->get('logger');      // same instance every time
```

Use `set()` for objects that should be singletons within the container: database connections, repositories, routers, mailers, etc.

---

## Factories (Prototype)

`factory(string $id, callable $factory): void`

Same signature as `set()`, but the factory is invoked **fresh on every `get()` call**.

```php
$container->factory('request', fn ($c) => Request::fromGlobals());

$req1 = $container->get('request');   // new instance
$req2 = $container->get('request');   // different instance
```

Use `factory()` for objects that must not be shared: request objects, command instances, or any stateful value that should be created anew each time.

---

## Parameters

Simple key-value store completely separate from services. Parameters do not overlap with `get()`/`has()`.

```php
$container->setParameter('db.host', 'localhost');
$container->setParameter('db.name', 'myapp');
$container->setParameter('db.port', 3306);

$container->getParameter('db.host');              // 'localhost'
$container->getParameter('db.port', 5432);        // 3306 (default unused)
$container->getParameter('db.user', 'root');      // 'root' (no such parameter)
$container->hasParameter('db.host');              // true
$container->removeParameter('db.host');
```

---

## ArrayAccess

`Container` implements `ArrayAccess`, which delegates to the parameter store:

```php
$container['db.host'] = 'localhost';

echo $container['db.host'];                       // 'localhost'
isset($container['db.host']);                     // true
unset($container['db.host']);
```

This is a convenience shorthand for parameter access — it does **not** interact with `set()`/`get()` services.

---

## Complete Example

A stand-alone application wired through the container:

```php
use Fight\Common\Application\Service\Container;

$c = new Container();

// --- Parameters ---
$c->setParameter('db.host', 'localhost');
$c->setParameter('db.name', 'myapp');
$c->setParameter('db.user', 'root');
$c->setParameter('db.pass', 'secret');

// --- Shared services ---
$c->set('pdo', function (Container $c): PDO {
    return new PDO(
        sprintf(
            'mysql:host=%s;dbname=%s',
            $c->getParameter('db.host'),
            $c->getParameter('db.name')
        ),
        $c->getParameter('db.user'),
        $c->getParameter('db.pass')
    );
});

$c->set('user_repository', function (Container $c): UserRepository {
    return new UserRepository($c->get('pdo'));
});

$c->set('mailer', function (Container $c): MailerInterface {
    return new SmtpMailer('smtp.example.com', 587);
});

// --- Prototype factories ---
$c->factory('request', fn (Container $c) => Request::fromGlobals());

// --- Usage ---
$repo = $c->get('user_repository');
$users = $repo->findAll();

$mailer = $c->get('mailer');
$mailer->send(new WelcomeEmail($users[0]));
```

---

## NotFoundException

`Fight\Common\Application\Service\Exception\NotFoundException`

Extends `\Exception` and implements `Psr\Container\NotFoundExceptionInterface`. Thrown when `get()` is called with a service ID that has not been registered via `set()` or `factory()`.

```php
use Fight\Common\Application\Service\Exception\NotFoundException;

try {
    $c->get('nonexistent');
} catch (NotFoundException $e) {
    echo $e->getMessage();       // "Service 'nonexistent' not found."
}
```

Note that `getParameter()` does **not** throw — it returns the provided default (or `null`) for missing keys.
