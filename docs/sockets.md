# Mercure Hub Publisher

The Mercure Hub Publisher implements the `Socket\Publisher` port using the Symfony Mercure component. It publishes real-time messages to a Mercure hub, enabling server-sent events for connected clients.

---

## Table of Contents

1. [Overview](#overview)
2. [Installing the Mercure Component](#installing-the-mercure-component)
3. [Wiring Up the Publisher](#wiring-up-the-publisher)
4. [Publishing Messages](#publishing-messages)
5. [Error Handling](#error-handling)
6. [Complete Example](#complete-example)

---

## Overview

The system is built from three cooperating pieces:

```
Application code
  └─► $publisher->push($topic, $message)
        └─► MercureHubPublisher::push()
              └─► HubInterface::publish(new Update($topic, $data))
                    └─► Mercure Hub
                          └─► SSE pushed to subscribed clients
```

**`Publisher` interface** — the application-layer port at `Fight\Common\Application\Socket\Publisher`. Defines a single method:

```php
public function push(string $topic, string $message): void;
```

**`MercureHubPublisher`** — the adapter at `Fight\Common\Adapter\Socket\MercureHubPublisher`. Takes a Symfony `HubInterface` and translates `push()` calls into `$hub->publish(new Update(...))`.

**`HubInterface`** — the Symfony Mercure component's current API (v0.5+). The older `Publisher`/`PublisherInterface` from the Mercure component is deprecated; this adapter uses the new `HubInterface` API.

---

## Installing the Mercure Component

This library declares `symfony/mercure` as a suggested dev dependency. Your project must add it to `require`:

```bash
composer require symfony/mercure
```

If you are running PHP 8.5 with Symfony 8.0, version `^0.7` is compatible.

---

## Wiring Up the Publisher

### Option 1: Using MercureBundle (recommended)

If you have `symfony/mercure-bundle` installed with autoconfigure enabled, the default Hub service is already available as `mercure.hub.default`. Register the adapter as an alias:

```yaml
# config/services.yaml
services:
    Fight\Common\Adapter\Socket\MercureHubPublisher:
        arguments:
            $hub: '@mercure.hub.default'

    Fight\Common\Application\Socket\Publisher:
        alias: Fight\Common\Adapter\Socket\MercureHubPublisher
```

Configure the hub URL and JWT provider in `mercure.yaml`:

```yaml
# config/packages/mercure.yaml
mercure:
    hubs:
        default:
            url: '%env(MERCURE_URL)%'
            public_url: '%env(MERCURE_PUBLIC_URL)%'
            jwt:
                secret: '%env(MERCURE_JWT_SECRET)%'
                publish: '*'
```

### Option 2: Manual service definition

If you are not using MercureBundle, create the Hub manually:

```yaml
# config/services.yaml
services:
    Symfony\Component\Mercure\Hub:
        arguments:
            $url: '%env(MERCURE_URL)%'
            $jwtProvider: '@mercure.jwt_provider'

    Fight\Common\Adapter\Socket\MercureHubPublisher:
        arguments:
            $hub: '@Symfony\Component\Mercure\Hub'

    Fight\Common\Application\Socket\Publisher:
        alias: Fight\Common\Adapter\Socket\MercureHubPublisher
```

The JWT provider must implement `Symfony\Component\Mercure\Jwt\TokenProviderInterface`. For development you can use `StaticTokenProvider`:

```yaml
services:
    Symfony\Component\Mercure\Jwt\StaticTokenProvider:
        arguments:
            $token: '%env(MERCURE_JWT_TOKEN)%'

    Symfony\Component\Mercure\Hub:
        arguments:
            $url: '%env(MERCURE_URL)%'
            $jwtProvider: '@Symfony\Component\Mercure\Jwt\StaticTokenProvider'
```

---

## Publishing Messages

### Basic Public Update

```php
use Fight\Common\Application\Socket\Publisher;

class BookController
{
    public function __construct(private Publisher $publisher)
    {
    }

    public function update(int $id): JsonResponse
    {
        // ... update the book ...

        $this->publisher->push(
            'https://example.com/books/' . $id,
            json_encode(['status' => 'updated']),
        );

        return new JsonResponse(['status' => 'success']);
    }
}
```

Topics are typically URL strings that clients subscribe to. The topic is passed directly to Mercure's `Update` object — it accepts both strings and arrays of strings.

### Private Updates

To send private updates (visible only to the target user), pass the topic and data as usual. Privacy is controlled by the Mercure JWT token, not by the `Publisher` interface. If you need to mark an update as private, construct the `Update` directly and call `$hub->publish()` instead:

```php
use Symfony\Component\Mercure\Update;

$this->hub->publish(new Update(
    'https://example.com/user/' . $userId . '/notifications',
    $message,
    private: true,
));
```

---

## Error Handling

`MercureHubPublisher::push()` wraps any exception thrown by `HubInterface::publish()` in a `SocketException`:

```php
use Fight\Common\Application\Socket\Exception\SocketException;

try {
    $this->publisher->push($topic, $message);
} catch (SocketException $e) {
    // Hub unreachable, JWT invalid, etc.
    // $e->getPrevious() contains the original exception
}
```

`SocketException` extends `SystemException` (a domain-level exception). Both extend PHP's `\RuntimeException`, so they can be caught at any level.

---

## Complete Example

The following example configures the Mercure hub, registers the publisher, and uses it in a controller to broadcast a notification when a book is updated.

### Configuration

```yaml
# config/packages/mercure.yaml
mercure:
    hubs:
        default:
            url: '%env(MERCURE_URL)%'
            jwt:
                secret: '%env(MERCURE_JWT_SECRET)%'
                publish: '*'
```

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Fight\Common\Adapter\Socket\MercureHubPublisher: ~

    Fight\Common\Application\Socket\Publisher:
        alias: Fight\Common\Adapter\Socket\MercureHubPublisher
```

### Controller

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Fight\Common\Application\Socket\Publisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class BookController extends AbstractController
{
    public function __construct(private Publisher $publisher)
    {
    }

    #[Route('/books/{id}', methods: ['PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // ... persist the update ...

        $this->publisher->push(
            'https://example.com/books/' . $id,
            json_encode([
                'title' => $data['title'] ?? null,
                'status' => 'updated',
            ]),
        );

        return new JsonResponse(['status' => 'success'], 200);
    }
}
```

### Client-Side Subscription

```javascript
const url = new URL('https://hub.example.com/.well-known/mercure');
url.searchParams.append('topic', 'https://example.com/books/1');

const eventSource = new EventSource(url);

eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Book updated:', data);
};
```
