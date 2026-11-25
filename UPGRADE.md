# Upgrade from Fight Common v1.5.1 to v2.0

Start from [published v1.5.1](https://github.com/johnnickell/fight-common/releases/tag/v1.5.1). PHP 8.5+ and the existing dependency floors are unchanged. Some 1.x classes, property reads and constants are gone, and repository page constructors now reject inputs their `strict()` factories already rejected.

## Before changing the Composer constraint

1. Search your code, tests and configuration for the names below. Update PHP imports and type declarations, literal container IDs, factories, compiler passes, Doctrine registrations and transport settings. Rebuild generated containers, proxies and caches as applicable. Where v1.5.1 already supports the replacement, make and test the change there first.
2. Inventory queues (including retries and failed messages), cached values, and stored frames containing PHP objects or class-name strings. An import change does not rewrite any of them. Stop old producers and drain work or export affected data while v1.5.1 can still read it. For objects you need to retain, export explicit arrays or JSON and reconstruct them with supported 2.0 constructors or factories. Convert only trusted application-owned data; never deserialize untrusted PHP objects or blindly replace strings inside PHP serialization. Invalidate disposable caches instead of converting them. Do not share affected native frames between old and new workers.
3. Rehearse the upgrade and recheck the affected application paths with representative data before changing the deployed `johnnickell/fight-common` constraint. Restart workers on a consistent version and rebuild invalidated caches. Common does not move data, rename tables or execute consumer migrations. Keep a rollback plan for any consumer-owned conversion.

Names below are relative to `Fight\Common\` unless written in full. The [removal inventory](docs/maintenance/v2-removal-inventory.md) links each deprecated contract to its released evidence.

## MCP

### Foundation and HTTP adapters

The canonical MCP foundation types now own their declarations; the old root names no longer alias them. Within `Application\Mcp\`, move the following imports and literal service IDs:

| Old name under `Application\Mcp\` | Replacement under `Application\Mcp\` |
| --- | --- |
| `McpCapability`, `McpCapabilityRegistry`, `McpMetadataAwareCapability` | `Capability\McpCapability`, `Capability\McpCapabilityRegistry`, `Capability\McpMetadataAwareCapability` |
| `McpInvocationGuard`, `McpMirrorDeclaration`, `McpOriginPolicy`, `McpRequestMirrors` | `Http\McpInvocationGuard`, `Http\McpMirrorDeclaration`, `Http\McpOriginPolicy`, `Http\McpRequestMirrors` |
| `McpJsonResponse`, `McpProtocolError`, `McpRequest`, `McpRequestDecoder`, `McpRequestLimits`, `McpRequestMetadata`, `McpResult`, `McpServerInfo` | `Protocol\McpJsonResponse`, `Protocol\McpProtocolError`, `Protocol\McpRequest`, `Protocol\McpRequestDecoder`, `Protocol\McpRequestLimits`, `Protocol\McpRequestMetadata`, `Protocol\McpResult`, `Protocol\McpServerInfo` |
| `McpProtocolException` | `Protocol\Exception\McpProtocolException` |
| `McpProgressReporter` | `Tool\McpProgressReporter` |

For example, change `use Fight\Common\Application\Mcp\McpCapabilityRegistry;` to `use Fight\Common\Application\Mcp\Capability\McpCapabilityRegistry;`. Update parameter and return types in your capability, mirror and progress implementations and update exception catches. `Application\Mcp\McpResponder` and `Application\Mcp\McpDiagnostics` stay at the root. The canonical 1.x imports already worked, but PHP could still record the **old** declaration name in native frames. Inspect saved identities even if your source already uses the new imports. The namespace change does not rename MCP methods or JSON-RPC fields.

| Removed adapter namespace | Replacement namespace | Classes (same basenames) |
| --- | --- | --- |
| `Adapter\Http\Mcp\` | `Adapter\Mcp\Http\` | `DenyAllMcpOriginPolicy`, `ExactMcpOriginPolicy`, `McpHeaderValidator`, `McpOrigin`, `McpRequestHandler`, `McpResponseEmitter`, `McpResponseFactory` |
| `Adapter\Http\CodeIgniter\` | `Adapter\Mcp\CodeIgniter\` | `McpResponse` |
| `Adapter\Http\Symfony\` | `Adapter\Mcp\Symfony\` | `McpResponseFactory` |

Update request-handler wiring and the corresponding policies/response factories together. This is not a reason to change your allowed origins or OAuth policy. See [MCP namespaces](docs/mcp-namespaces.md).

### Limits and skill files

Read each listed field through the method with the same name: for example, `$limits->maxDepth` becomes `$limits->maxDepth()`. Constructor argument names and defaults still apply.

| Type under `Application\Mcp\` | Getters replacing public fields |
| --- | --- |
| `Protocol\McpRequestLimits` (formerly also `McpRequestLimits`) | `maxBytes()`, `maxDepth()` |
| `Resource\McpResourceLimits` | `maxDescriptorBytes()`, `maxDescriptors()`, `maxProviders()`, `maxResultBytes()`, `maxUriBytes()`, `pageSize()` |
| `Resource\McpResourceReadLimits` | `maxContentBytes()`, `maxResultBytes()` |
| `Skill\McpSkillDiscoveryLimits` | `maxEntries()`, `maxEntryBytes()`, `maxResultBytes()`, `maxUriBytes()`, `pageSize()` |
| `Skill\McpSkillFile` | `bytes()`, `digest()`, `mimeType()`, `path()`, `text()` |
| `Skill\McpSkillLimits` | `maxDepth()`, `maxEntryBytes()`, `maxFiles()`, `maxFrontmatterBytes()`, `maxTotalBytes()` |

### DBAL table selections

Replace the removed `TABLES` constants with calls on the same table selection you pass to the schema and runtime adapters. The namespaces below are relative to `Adapter\Mcp\Persistence\`. Each listed accessor returns a table-name object; call `->name()` on it when you need the string, for example `$taskTables->taskTable()->name()`.

| Removed constant | Selection in the same namespace | Accessors |
| --- | --- | --- |
| `Execution\Dbal\DbalObservationSchema::TABLES` | `DbalObservationTables` | `schemaTable()`, `sessionTable()`, `phaseTable()`, `observationTable()`, `contentTable()` |
| `Notice\Dbal\DbalNoticeSchema::TABLES` | `DbalNoticeTables` | `stateTable()`, `noticeTable()`, `relationshipTable()`, `comparisonTable()` |
| `Subscription\Dbal\DbalSubscriptionSchema::TABLES` | `DbalSubscriptionTables` | `stateTable()`, `subscriptionTable()` |
| `Task\Dbal\DbalTaskSchema::TABLES` | `DbalTaskTables` | `stateTable()`, `taskTable()` |

Do not substitute default names for a configured selection. Keep the existing table and index names and preserve writer/reader wiring. If your application uses the default selection, construct that selection with no arguments, for example `new DbalTaskTables()`, rather than copying the old constant. See [table names](docs/table-names.md).

## OAuth and safe HTTP properties

Replace each public field read with its namesake getter. For example, `$failure->status` becomes `$failure->status()`; `$claims->grantedScopes` becomes `$claims->grantedScopes()`. Named construction arguments are unchanged where public construction is supported.

| Type | Getters replacing public fields |
| --- | --- |
| `Application\Auth\OAuth\OAuthResourceMetadata` | `authorizationServers()`, `metadataUrl()`, `resource()`, `scopesSupported()` |
| `Application\Auth\OAuth\OAuthResourceServer` | `requirements()` |
| `Application\Auth\OAuth\OAuthScopeSet` | `values()` |
| `Application\Auth\OAuth\OAuthTokenRequirements` | `activeKeyIds()`, `allowedAlgorithms()`, `clockSkewSeconds()`, `metadata()`, `requiredClaimTypes()`, `tokenType()` |
| `Application\Auth\OAuth\OAuthValidatedClaims` | `algorithm()`, `claims()`, `grantedScopes()`, `keyId()`, `tokenType()` |
| `Adapter\Http\Error\SafeHttpFailure` | `metadata()`, `status()` |
| `Adapter\Http\Error\SafeHttpMetadata` | `allow()`, `challenge()`, `retryAfterSeconds()` |
| `Adapter\Http\Error\SafeHttpPresentation` | `data()`, `message()`, `metadata()` |

This also applies to the MCP properties above: generic public-property enumeration, reflection-based hydration, direct `json_encode($object)` and native PHP property scopes are not substitutes for the supported getters or an explicit wire projection. Check stored instances before upgrading, not just direct reads.

## HTTP responses, exception handling and validation

| Removed | Replacement |
| --- | --- |
| `Adapter\EventSubscriber\SymfonyExceptionSubscriber`, `Adapter\Http\Symfony\EventSubscriber\SymfonyExceptionSubscriber` | `Adapter\Http\Symfony\EventSubscriber\SafeSymfonyExceptionSubscriber` |
| `Adapter\HttpKernel\ErrorController`, `Adapter\Http\Symfony\Controller\ErrorController` | `Adapter\Http\Symfony\Controller\SafeErrorController` |
| `Adapter\Http\Laravel\Controller\ErrorController` | `Adapter\Http\Laravel\SafeLaravelExceptionHandler` |
| `Adapter\Middleware\Psr15\JSendErrorMiddleware` | `Adapter\Middleware\Psr15\SafeJSendErrorMiddleware` |
| `Adapter\EventSubscriber\SymfonyValidationSubscriber` | `Adapter\Http\Symfony\EventSubscriber\SymfonyValidationSubscriber` |
| `Adapter\HttpKernel\JsonRequestMiddleware` | `Adapter\Middleware\Symfony\JsonRequestMiddleware` |
| `Adapter\HttpFoundation\JSendResponse` | `Adapter\Http\Symfony\JSendResponse` |

- **Symfony:** Remove the old controller and listener IDs together. Explicitly register the safe services and kernel exception event tag per the [Symfony wiring](docs/safe-http-errors.md#symfony-native-exception-lifecycle). Keep your separate validation subscriber, using its canonical name. Check JSON and native HTML negotiation, validation status and headers. Do not run both error listeners on the same path.

  There is no drop-in replacement for subclasses of the old `ErrorController` or `SymfonyExceptionSubscriber`: the safe classes are final. Move your custom behavior into consumer-owned services that compose the safe components instead of extending them. For direct named calls, replace `$controller->handle(exception: $exception)` with `$safeController->handle(failure: $exception)` on the configured `SafeErrorController`.

  For either old subscriber, replace `errorController:` with `errors:`, for example `$safeListener = new SafeSymfonyExceptionSubscriber(errors: $safeController)` with a configured `SafeErrorController`. In Symfony service `arguments:`, rename the explicit `$errorController` key to `$errors` and point it at the safe controller service.

  The safe listener does not implement Symfony's `EventSubscriberInterface` or expose `getSubscribedEvents()`. For programmatic wiring, replace `addSubscriber()` with `$dispatcher->addListener(KernelEvents::EXCEPTION, [$safeListener, 'onKernelException'], 64)`, using `Symfony\Component\HttpKernel\KernelEvents`. Use that registration **or** the service tag, not both. If your integration requires `EventSubscriberInterface`, provide your own subscriber that delegates to the safe listener at the same priority instead of also registering the safe listener separately.

- **Laravel:** Bind `Illuminate\Contracts\Debug\ExceptionHandler` to the safe handler in your bootstrap if you used the old Common exception path. Keep `Adapter\ServiceContainer\Laravel\HttpServiceProvider` if you rely on its `JSendResponse` singleton; it does not install an exception handler. Prefer Laravel's configured report-and-render lifecycle. Check [reporting and native HTML delegation](docs/safe-http-errors.md#laravel-native-report-and-render-lifecycle).

  There is no drop-in replacement for a direct `$controller->handle($failure)` call, including one using `exception:`. If you must render directly, resolve the configured `ExceptionHandler` and call `$handler->render($request, $failure)` with the actual Laravel request. The safe handler uses `$request->expectsJson()` to select safe JSend and delegates non-JSON rendering to Laravel. Do not assume the result is always a JSON response. If your own API must return JSON regardless of negotiation, keep a consumer-owned responder that emits a fixed generic error or deliberately public data; do not rewrite the incoming request to force JSON or forward raw exception data.

- **PSR-15:** Compose the safe middleware with a JSend response factory, safe error policy, redacting diagnostics and, if needed, a deliberately public projector. If you used the old `responseFactory:` argument, pass the factory as `responses:` (Symfony-style named service key `$responses` instead of `$responseFactory`) and supply `policy:` and `diagnostics:`. The middleware call remains `process($request, $handler)`. Follow the [wiring example](docs/safe-http-errors.md). For all safe adapters, do not copy raw exception messages, validation bags or response headers into the public projection. Verify required challenges/Allow headers, error payloads and diagnostic redaction before switching.
- **Symfony JSend:** Do not just rename the import. Replace `success(data: $rawArray)` or `fail(data: $rawArray)` with `success(presentation: $publicView)` or `fail(presentation: $publicView)`. The view implements `Domain\Type\Arrayable` and returns deliberately public data from `toArray()`. For `error()`, use `presentation:` for optional typed data; keep `message:`, `statusCode:`, `code:` and `headers:` as applicable. The typed `fail()` requires a view and cannot emit `data: null`. If a client still requires that exact body or a custom `options:` encoding, own that response in your application.

  Check custom media types before switching. The old `Adapter\HttpFoundation\JSendResponse` preserved a supplied `Content-Type`; the typed Symfony factories (`success()`, `fail()`, `error()` and `fromEnvelope()`) force `application/json`, even if you pass `Content-Type` in `headers:`. If clients require a vendor media type for that same JSend body, set it after creation, for example `$response->headers->set('Content-Type', 'application/vnd.example+json')`, or keep a consumer-owned response. Verify the media type, status and body together.

  To render an `Application\Http\JSend\JSendEnvelope` directly, call `JSendResponse::fromEnvelope($envelope, statusCode: 202)` with the intended HTTP status. Use `Application\Http\JSend\JSendStatus` for status constants (for example, `JSendStatus::FAIL->value` for the string `fail`) and `JSendEnvelope::DEFAULT_ENCODING_OPTIONS` for the old default encoding flags. Instead of `isSuccess()`/`isFail()`/`isError()`, use the known envelope kind before rendering, or read `status` from the decoded body. Replace the removed `$response->getData(array: $array, depth: $depth)` with `json_decode((string) $response->getContent(), associative: $array, depth: $depth)`. To keep the old no-argument defaults, use `associative: true` and the default depth of 512.

## Repositories and Doctrine

Both constructors below remain public with the same parameter names. Test your current inputs with the `strict()` factories on v1.5.1 before upgrading. In 2.0 invalid values raise `Fight\Common\Domain\Exception\DomainException` through either construction path. PHP still enforces argument types. Do not treat a validation failure as permission to run an unbounded query.

### Pagination

`Domain\Repository\Pagination` requires positive resolved `page` and `perPage` and a representable integer offset `(page - 1) * perPage`. Only omitted or `null` bounds select the independent defaults (page 1, size 100); zero no longer selects them. A negative bound or overflow is rejected. Ordering values must be actual strings equal to `ASC` or `DESC`, case-insensitively. They normalize to uppercase; unknown, padded or non-string values no longer coerce or fall back to `ASC`. Consumers still own ordering-field eligibility and database limits.

```php
use Fight\Common\Domain\Repository\Pagination;

// Before: zero meant defaults; an unknown direction silently became ASC.
// $page = new Pagination(page: 0, perPage: 0, orderings: ['title' => 'unknown']);

// After, if default bounds and ascending title order were intended:
$page = new Pagination(page: null, perPage: null, orderings: ['title' => 'ASC']);
```

### ResultSet

`Domain\Repository\ResultSet` requires positive `page` and `perPage`, nonnegative `totalRecords` and an `ArrayList` count no greater than either `perPage` or `totalRecords`. If rows and totals disagree, fix the query or obtain a consistent count; do not inflate totals. Empty, partial and out-of-range pages are still allowed when the local count bounds hold, and an empty result with zero total still reports one page. Construction does not copy or continuously validate the shared list. Output keys stay the same, but `totalPages()` now uses exact integer arithmetic; a total and size both equal to `PHP_INT_MAX` give one page rather than two on 64-bit PHP.

```php
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Repository\ResultSet;

$empty = ArrayList::of(Order::class); // Use your own record type.
// Before: new ResultSet(page: 0, perPage: 0, totalRecords: 0, records: $empty)
// After, for an actual request for page 1 with size 25 and no matching rows:
$result = new ResultSet(page: 1, perPage: 25, totalRecords: 0, records: $empty);
```

If an affected page or result object is saved, reconstruct it from corrected inputs rather than relying on native hydration to run validation. See the [repository contracts](docs/repositories.md).

### Transaction boundary

Replace `Application\Repository\UnitOfWork` and its standalone `commit()` with `Application\Repository\TransactionalUnitOfWork::commitTransactional()`. Replace `Adapter\Repository\DoctrineUnitOfWork` with `Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork` if using an EntityManager, or compose `Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork` for a DBAL-only connection. This is not just a renamed commit: put the operation inside the callback, preserve the intended transaction/connection, and check exception propagation and rollback. Do not assume external effects roll back with the database.

```php
// Before: $repository->save($order); $unitOfWork->commit();
$transactionalUnitOfWork->commitTransactional(function () use ($repository, $order): void {
    $repository->save($order);
});
```

### Doctrine type registrations

Replace `Adapter\Doctrine\` with `Adapter\Persistence\Doctrine\Type\` for these declarations:

- `AuditEntryIdDataType`, `EmailAddressDataType`, `JsonObjectDataType`, `MessageDataType`, `MetaDataType`
- `MbStringObjectDataType`, `MbStringTextDataType`, `StringObjectDataType`, `StringTextDataType`
- `TypeDataType`, `UriDataType`, `UrlDataType`, `UuidDataType`

For example, register `Fight\Common\Adapter\Persistence\Doctrine\Type\EmailAddressDataType::class` for `common_email_address`. Change the PHP class in type registrations, not the registered type name, `TYPE_NAME` value, database schema or stored encoding. Avoid registering both implementations under one name. Rebuild generated Doctrine metadata if it refers to the old class.

## Messaging, container, cache, files and routing

| Removed | Replacement |
| --- | --- |
| `Adapter\Messaging\Command\Async\MessengerCommandBus` | `Adapter\Messaging\Symfony\MessengerCommandBus` |
| `Adapter\Messaging\Event\Async\MessengerEventDispatcher` | `Adapter\Messaging\Symfony\MessengerEventDispatcher` |
| `Adapter\Messaging\Handler\SymfonyCommandMessageHandler` | `Adapter\Messaging\Handler\CommandMessageHandler` |
| `Adapter\Messaging\Handler\SymfonyEventMessageHandler` | `Adapter\Messaging\Handler\EventMessageHandler` |
| `Adapter\Messaging\Serializer\SymfonyMessageSerializer` | `Adapter\Messaging\Symfony\Serializer\SymfonyMessageSerializer` |
| `Adapter\Cache\PsrCache` | `Adapter\Cache\Psr6\Psr6Cache` |
| `Adapter\Filesystem\SymfonyFilesystem` | `Adapter\Filesystem\Symfony\SymfonyFilesystem` |
| `Adapter\Routing\SymfonyUrlGenerator` | `Adapter\Routing\Symfony\SymfonyUrlGenerator` |

Move these compiler pass classes from `Adapter\DependencyInjection\` to `Adapter\ServiceContainer\Symfony\` without changing their basenames: `CommandFilterCompilerPass`, `CommandHandlerCompilerPass`, `EventMappingProviderCompilerPass`, `EventSubscriberCompilerPass`, `QueryFilterCompilerPass`, `QueryHandlerCompilerPass`, `TemplateHelperCompilerPass`.

For example, in cache composition change `use Fight\Common\Adapter\Cache\PsrCache;` to `use Fight\Common\Adapter\Cache\Psr6\Psr6Cache;` and update the container key, factory return type, `new` call and any lookups together. The PSR-6 pool and logger arguments are unchanged; this does not replace a separate PSR-16 binding.

Change Messenger service IDs, handler registrations and the configured transport serializer together before restarting workers. Keep the selected body serializer and the existing `X-Message-Stamp-*` format. The serializer move itself does not change that format; bodies or native stamp headers that contain removed class identities still need the pre-upgrade queue check. See [Messenger wiring](docs/messaging.md).

## Serialization

Replace concrete `Domain\Serialization\JsonSerializer` and `Domain\Serialization\PhpSerializer` with `Application\Serialization\JsonSerializer` and `Application\Serialization\PhpSerializer`. The Domain interfaces such as `Domain\Serialization\Serializer` and `Domain\Serialization\Serializable` remain. Check saved envelopes for removed payload class names, even in JSON, and inspect embedded native objects and stamps for affected class names or field visibility. Export or drain affected trusted frames before upgrade rather than assuming an import change can decode them. See [serialization](docs/serialization.md).

## Scheduler

Replace `new Application\Scheduler\Scheduler(...)` with `Application\Scheduler\Scheduler::withProcessRunner(...)`, even for callable-only schedules. The old `processFactory` parameter and implicit Symfony Process bridge are gone. Compose an `Application\Process\ProcessRunner` in the application, such as `Adapter\Process\Symfony\SymfonyProcessRunner`, or implement the port for a custom runner.

```php
use Fight\Common\Application\Scheduler\Scheduler;

// Before: new Scheduler($timezone, $tempDirectory, $logger, $mailService, $fromEmail);
$scheduler = Scheduler::withProcessRunner(
    timezone: $timezone,
    tempDirectory: $tempDirectory,
    processRunner: $processRunner,
    logger: $logger,
    mailService: $mailService,
    fromEmail: $fromEmail,
);
```

Keep job registration and scheduling options. Update the service factory and check output, nonzero exit handling, locking and failure notifications before switching scheduled workers. See [Scheduler composition](docs/scheduler.md#portable-processrunner-composition).

## Legacy AI webhook flow — no replacement

Remove both sets of retired names:

| Layer | Removed names |
| --- | --- |
| Adapter | `Adapter\Auth\Hmac\HmacWebhookDispatcher`, `Adapter\Mcp\Operation\Hmac\HmacWebhookDispatcher` |
| Application | `Application\Auth\WebhookDispatcher`, `Application\Mcp\Operation\WebhookDispatcher` |
| Domain | `Domain\Auth\AiOperation`, `Domain\Mcp\Operation\AiOperation` |

Stop traffic into this flow and drain or retire its old request/worker payloads while the old version is available. Remove dispatcher bindings, job handlers and consumer-owned endpoint wiring. MCP Tools are not a drop-in replacement; a replacement workflow needs its own application design and authorization. General HMAC authentication is not removed.

## Base64URL: optional addition, not a migration

`Fight\Common\Domain\base64_url_encode()` emits unpadded Base64URL. `Fight\Common\Domain\base64_url_decode()` accepts canonical unpadded text or exactly correct trailing padding; empty text decodes to `''`, and malformed input returns `false`. Existing MCP discovery cursors and Sodium interaction state keep their formats and caller-level rejection behavior. Do not swap these functions in for standard Base64, signature/authentication checks or protocol validation. No existing tokens, configuration or stored bytes need conversion for this addition. See [Base64URL helpers](docs/values.md#base64url-helpers).
