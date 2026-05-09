# HTTP Client

A transport-abstraction layer for making HTTP requests. The Application layer defines PSR-7
message factories, a transport contract, and a promise interface; the Adapter layer provides
a Guzzle implementation and a PSR-3 logging decorator. An `HttpService` facade combines
transport + all factories into a single dependency.

```
Application\HttpClient
├── HttpService                         — Facade: HttpClient + MessageFactory +
│                                          StreamFactory + UriFactory
├── Transport\
│   └── HttpClient (interface)          — send(), sendAsync()
├── Message\
│   ├── MessageFactory (interface)      — createRequest(), createResponse()
│   ├── StreamFactory (interface)       — createStream()
│   ├── UriFactory (interface)          — createUri()
│   └── Promise (interface)             — then(), getState(), getResponse(),
│                                          getException(), wait()
└── Exception\
    ├── Exception (interface)           — Marker
    ├── TransferException               — Base runtime exception
    ├── RequestException                — Has getRequest()
    ├── HttpException                   — Has getResponse(), getStatusCode()
    └── NetworkException                — Connection-level failure

Adapter\HttpClient
├── Guzzle\
│   ├── GuzzleClient                   — HttpClient → Guzzle ClientInterface
│   ├── GuzzlePromise                  — Promise → Guzzle PromiseInterface
│   ├── GuzzleMessageFactory           — MessageFactory → Guzzle PSR-7
│   ├── GuzzleStreamFactory            — StreamFactory → Guzzle PSR-7
│   └── GuzzleUriFactory               — UriFactory → Guzzle PSR-7
└── Logging\
    └── LoggingHttpClient              — Decorator: logs request/response then delegates

Application\HttpFoundation
├── HttpMethod                         — String constants (GET, POST, ...)
└── HttpStatus                         — Integer constants (OK, NOT_FOUND, ...)
```

---

## Table of Contents

1. [HttpClient (Transport)](#httpclient-transport)
2. [HttpService (Facade)](#httpservice-facade)
3. [Message Factories](#message-factories)
4. [Promise](#promise)
5. [Guzzle Adapter](#guzzle-adapter)
6. [LoggingHttpClient](#logginghttpclient)
7. [Exception Hierarchy](#exception-hierarchy)
8. [HttpFoundation Primitives](#httpfoundation-primitives)
9. [Installation](#installation)
10. [Symfony Configuration](#symfony-configuration)
11. [Usage Examples](#usage-examples)

---

## HttpClient (Transport)

`Fight\Common\Application\HttpClient\Transport\HttpClient`

```php
interface HttpClient
{
    /** @throws Exception */
    public function send(RequestInterface $request, array $options = []): ResponseInterface;

    /** @throws Exception */
    public function sendAsync(RequestInterface $request, array $options = []): Promise;
}
```

### Implementations

| Implementation | Namespace | Purpose |
|---|---|---|
| `GuzzleClient` | `Adapter\HttpClient\Guzzle` | Production — wraps Guzzle `ClientInterface` |
| `LoggingHttpClient` | `Adapter\HttpClient\Logging` | Dev — logs request/response then delegates |

---

## HttpService (Facade)

`Fight\Common\Application\HttpClient\HttpService`

Implements `HttpClient`, `MessageFactory`, `StreamFactory`, and `UriFactory`, delegating
every method to its injected dependency. This is the recommended way to depend on HTTP in
application services — one dependency gives you transport, message creation, streams, and
URI parsing.

```php
final readonly class HttpService implements HttpClient, MessageFactory, StreamFactory, UriFactory
{
    public function __construct(
        private HttpClient $httpClient,
        private MessageFactory $messageFactory,
        private StreamFactory $streamFactory,
        private UriFactory $uriFactory,
    ) {}
}
```

```php
class UserApiService
{
    public function __construct(private HttpService $http) {}

    public function fetchUser(int $id): array
    {
        $request = $this->http->createRequest('GET', "/users/{$id}");
        $response = $this->http->send($request);

        return json_decode((string) $response->getBody(), true);
    }
}
```

---

## Message Factories

### MessageFactory

`Fight\Common\Application\HttpClient\Message\MessageFactory`

```php
interface MessageFactory
{
    public function createRequest(
        string $method,
        string|UriInterface $uri,
        array $headers = [],
        mixed $body = null,
        string $protocol = '1.1'
    ): RequestInterface;

    public function createResponse(
        int $status = 200,
        array $headers = [],
        mixed $body = null,
        string $protocol = '1.1',
        ?string $reason = null
    ): ResponseInterface;
}
```

### StreamFactory

`Fight\Common\Application\HttpClient\Message\StreamFactory`

```php
interface StreamFactory
{
    /** @throws DomainException */
    public function createStream(mixed $body = null): StreamInterface;
}
```

### UriFactory

`Fight\Common\Application\HttpClient\Message\UriFactory`

```php
interface UriFactory
{
    /** @throws DomainException */
    public function createUri(string $uri): UriInterface;
}
```

### Adapter Implementations

All three factories have a single Guzzle adapter:

| Factory | Implementation | PSR-7 Library |
|---|---|---|
| `MessageFactory` | `GuzzleHttp\Psr7\Request` / `Response` | `guzzlehttp/psr7` |
| `StreamFactory` | `GuzzleHttp\Psr7\Utils::streamFor()` | `guzzlehttp/psr7` |
| `UriFactory` | `GuzzleHttp\Psr7\Utils::uriFor()` | `guzzlehttp/psr7` |

```php
$factory = new GuzzleMessageFactory();
$request = $factory->createRequest('POST', '/api/orders', [
    'Content-Type' => 'application/json',
], json_encode($orderData));
```

---

## Promise

`Fight\Common\Application\HttpClient\Message\Promise`

Represents the eventual result of an asynchronous HTTP operation.

```php
interface Promise
{
    public const PENDING   = 'pending';
    public const FULFILLED = 'fulfilled';
    public const REJECTED  = 'rejected';

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): static;
    public function getState(): string;

    /** @throws MethodCallException */
    public function getResponse(): ResponseInterface;

    /** @throws MethodCallException */
    public function getException(): Throwable;

    public function wait(): void;
}
```

| Method | Returns | Notes |
|---|---|---|
| `then()` | `static` | Returns a NEW promise with chained callbacks |
| `getState()` | `string` | One of `PENDING`, `FULFILLED`, `REJECTED` |
| `getResponse()` | `ResponseInterface` | Throws `MethodCallException` unless `FULFILLED` |
| `getException()` | `Throwable` | Throws `MethodCallException` unless `REJECTED` |
| `wait()` | `void` | Synchronously resolves / rejects |

---

## Guzzle Adapter

### GuzzleClient

`Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient`

```php
final class GuzzleClient implements HttpClient
{
    public function __construct(protected ClientInterface $client) {}
}
```

Wraps any Guzzle `ClientInterface`. `send()` calls `sendAsync()` then `wait()`, re-throwing
any non-Fight exception as `TransferException`.

### GuzzlePromise

`Fight\Common\Adapter\HttpClient\Guzzle\GuzzlePromise`

Wraps a Guzzle `PromiseInterface`. On rejection, converts Guzzle exceptions to the Fight
exception hierarchy:

| Guzzle Exception | Fight Exception |
|---|---|
| `ConnectException` | `NetworkException` |
| `RequestException` (has response) | `HttpException` |
| `RequestException` (no response) | `RequestException` |
| Other `GuzzleException` | `TransferException` |
| Non-Guzzle `Throwable` | `RuntimeException` |

### GuzzleMessageFactory, GuzzleStreamFactory, GuzzleUriFactory

Simple adapters that delegate to `guzzlehttp/psr7` classes:

```php
$messageFactory = new GuzzleMessageFactory();
$streamFactory  = new GuzzleStreamFactory();
$uriFactory     = new GuzzleUriFactory();
```

---

## LoggingHttpClient

`Fight\Common\Adapter\HttpClient\Logging\LoggingHttpClient`

A decorator that logs every request and response via PSR-3 before delegating to the inner
client. Configurable log level (default `LogLevel::DEBUG`).

```php
final readonly class LoggingHttpClient implements HttpClient
{
    public function __construct(
        private HttpClient $httpClient,
        private LoggerInterface $logger,
        private string $logLevel = LogLevel::DEBUG,
    ) {}
}
```

**Logged data:**

- **Request**: method, URI, protocol version, headers, body content
- **Response** (on fulfill): status code, reason phrase, protocol version, headers, body content (stream is rewound after reading)
- **Exception** (on reject): exception message and full exception object

```php
$client = new LoggingHttpClient(
    new GuzzleClient(new GuzzleHttp\Client()),
    $logger,
    LogLevel::INFO
);
```

---

## Exception Hierarchy

```
Throwable
 └── Domain\Exception\SystemException
      └── Domain\Exception\RuntimeException
           └── TransferException ──── implements Exception (marker)
                └── RequestException ── has getRequest()
                     ├── HttpException ── has getResponse(), getStatusCode(), create()
                     └── NetworkException
```

| Exception | When Thrown | Key Methods |
|---|---|---|
| `TransferException` | Base transport failure | — |
| `RequestException` | Request-level error | `getRequest(): RequestInterface` |
| `HttpException` | Non-2xx response received | `getResponse()`, `getStatusCode()`, `static create()` |
| `NetworkException` | Connection refused / DNS failure | `getRequest()` (inherited) |

**HttpException::create()** builds a message in a normalized format:

```
[url]:/api/users [http method]:GET [status code]:404 [reason phrase]:Not Found
```

---

## HttpFoundation Primitives

### HttpMethod

`Fight\Common\Application\HttpFoundation\HttpMethod`

String constant class for HTTP methods:

```php
HttpMethod::GET;    // 'GET'
HttpMethod::POST;   // 'POST'
HttpMethod::DELETE; // 'DELETE'
```

All standard methods: `HEAD`, `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `PURGE`, `OPTIONS`,
`TRACE`, `CONNECT`.

### HttpStatus

`Fight\Common\Application\HttpFoundation\HttpStatus`

Integer constant class for HTTP status codes:

```php
HttpStatus::OK;                    // 200
HttpStatus::CREATED;              // 201
HttpStatus::NOT_FOUND;            // 404
HttpStatus::I_AM_A_TEAPOT;        // 418
HttpStatus::INTERNAL_SERVER_ERROR; // 500
```

Covers all standard codes 100–511 plus `I_AM_A_TEAPOT` (418) and `ENHANCE_YOUR_CALM` (420).

---

## Installation

The HTTP client layer depends on PSR-7 interfaces (`psr/http-message`) and PSR-17 factory
interfaces (`psr/http-factory`), which are required by the library. The Guzzle adapter
additionally requires:

```bash
composer require guzzlehttp/guzzle guzzlehttp/psr7
```

These provide the `ClientInterface`, `PromiseInterface`, and concrete PSR-7 implementations
that the adapters delegate to.

---

## Symfony Configuration

```yaml
# config/packages/common_http_client.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    # --- Guzzle client (PSR-18 compatible) ---
    GuzzleHttp\ClientInterface:
        class: GuzzleHttp\Client
        arguments:
            $config:
                base_uri: '%env(API_BASE_URI)%'
                timeout:  5.0
                connect_timeout: 2.0
                http_errors: false       # let the adapter handle status codes

    # --- PSR-7 factories ---
    Fight\Common\Adapter\HttpClient\Guzzle\GuzzleMessageFactory: ~
    Fight\Common\Adapter\HttpClient\Guzzle\GuzzleStreamFactory: ~
    Fight\Common\Adapter\HttpClient\Guzzle\GuzzleUriFactory: ~

    # --- Transport ---
    Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient: ~

    Fight\Common\Adapter\HttpClient\Logging\LoggingHttpClient:
        decorates: Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient
        arguments:
            - '@.inner'
            - '@logger'
            - 'info'

    # --- Facade ---
    Fight\Common\Application\HttpClient\HttpService:
        arguments:
            - '@Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient'
            - '@Fight\Common\Adapter\HttpClient\Guzzle\GuzzleMessageFactory'
            - '@Fight\Common\Adapter\HttpClient\Guzzle\GuzzleStreamFactory'
            - '@Fight\Common\Adapter\HttpClient\Guzzle\GuzzleUriFactory'

    # --- Interface aliases ---
    Fight\Common\Application\HttpClient\Transport\HttpClient:
        alias: Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient

    Fight\Common\Application\HttpClient\Message\MessageFactory:
        alias: Fight\Common\Adapter\HttpClient\Guzzle\GuzzleMessageFactory

    Fight\Common\Application\HttpClient\Message\StreamFactory:
        alias: Fight\Common\Adapter\HttpClient\Guzzle\GuzzleStreamFactory

    Fight\Common\Application\HttpClient\Message\UriFactory:
        alias: Fight\Common\Adapter\HttpClient\Guzzle\GuzzleUriFactory
```

Environment-specific overrides:

```yaml
# config/packages/dev/common_http_client.yaml
services:
    GuzzleHttp\ClientInterface:
        class: GuzzleHttp\Client
        arguments:
            $config:
                base_uri: '%env(DEV_API_BASE_URI)%'
                timeout:  10.0
                verify:   false

# config/packages/test/common_http_client.yaml
services:
    # Swap the logging decorator for a lightweight client in tests
    Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient:
        class: GuzzleHttp\Client
        arguments:
            $config:
                base_uri: 'http://localhost'
                timeout:  0.5
                handler:  '@test.http.handler'   # MockHandler for stubbing responses
```

---

## Usage Examples

### Basic GET Request

```php
use Fight\Common\Application\HttpClient\HttpService;
use Fight\Common\Application\HttpFoundation\HttpMethod;

class UserApiService
{
    public function __construct(private HttpService $http) {}

    public function getUser(int $id): array
    {
        $request = $this->http->createRequest(HttpMethod::GET, "/users/{$id}");
        $response = $this->http->send($request);

        return json_decode((string) $response->getBody(), true);
    }
}
```

### POST with JSON Body

```php
class OrderApiService
{
    public function __construct(private HttpService $http) {}

    public function createOrder(array $data): array
    {
        $request = $this->http->createRequest(
            'POST',
            '/orders',
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );

        $response = $this->http->send($request);

        return json_decode((string) $response->getBody(), true);
    }
}
```

### Async Request

```php
$promise = $this->http->sendAsync($request);

// Attach callbacks
$promise = $promise->then(
    function (ResponseInterface $response) {
        // handle response
        return $response;
    },
    function (Throwable $exception) {
        // handle error
        throw $exception;
    }
);

// Wait for resolution
$promise->wait();

if ($promise->getState() === Promise::FULFILLED) {
    $response = $promise->getResponse();
}
```

### Exception Handling

```php
use Fight\Common\Application\HttpClient\Exception\HttpException;
use Fight\Common\Application\HttpClient\Exception\NetworkException;

try {
    $response = $this->http->send($request);
} catch (HttpException $e) {
    // Non-2xx response
    $status = $e->getStatusCode();     // 404, 500, etc.
    $body   = $e->getResponse()->getBody();
} catch (NetworkException $e) {
    // Connection failure
    $uri = $e->getRequest()->getUri();
}
```

### Logging Decorator

```php
use Fight\Common\Adapter\HttpClient\Logging\LoggingHttpClient;
use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient;

$inner = new GuzzleClient(new GuzzleHttp\Client(['base_uri' => 'https://api.example.com']));
$client = new LoggingHttpClient($inner, $logger, LogLevel::DEBUG);

$request  = (new GuzzleMessageFactory())->createRequest('GET', '/health');
$response = $client->send($request);
// Logs: method, URI, headers, body → then status, reason, response headers, body
```

### Testing with a Mock Client

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Fight\Common\Adapter\HttpClient\Guzzle\GuzzleClient;

$mock = new MockHandler([
    new Response(200, [], json_encode(['id' => 1])),
    new Response(404, [], 'Not Found'),
]);

$handler = HandlerStack::create($mock);
$client  = new GuzzleClient(new Client(['handler' => $handler]));

$service = new HttpService(
    $client,
    new GuzzleMessageFactory(),
    new GuzzleStreamFactory(),
    new GuzzleUriFactory()
);

$response = $service->send($request);  // 200 response
$response = $service->send($request);  // 404 response
```
