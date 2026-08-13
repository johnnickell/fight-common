# WF-014 Fight Common contract and compatibility audit research

**Research date:** 2026-08-12
**Scope:** Evidence and decision inputs for
[`WF-014`](../tickets/WF-014-fight-common-contract-and-compatibility-audit.md). This note does not
implement a shim, rename a class, change a constructor or Composer constraint, or resolve a downstream
framework-composition ticket.

## Executive finding

Fight Common can target `1.2.0`, but it cannot certify that version yet. The current tree contains 404
production declarations in the three runtime layers (131 Domain, 166 Application, and 107 Adapter), while
the authoritative `1.1.0` baseline contains 363. The 41 additions are additive Event Sourcing and
observability declarations, but the current `Scheduler` constructor is not additive: it replaced the
optional third argument (`?LoggerInterface`) with a required `ProcessRunner`, shifted all later positional
arguments, and removed the optional sixth `processFactory` argument. That is a source- and construction-
compatibility break under the accepted compatibility policy.

The HTTP surface also needs a two-level repair. `JSendResponse` is currently a Symfony native response that
accepts only raw arrays. The closed WF-011 contract instead requires a framework-neutral typed JSend payload
plus five native response adapters, while retaining a deprecated raw-array and old-namespace path through
`1.x`. `Arrayable` currently promises only `array<string,mixed>`, and `ResultSet` hard-codes
`Collection<int,object>` even though tests construct scalar result sets. Those generic contracts need an
additive static-analysis repair before `ResultSet<TData>` can be the typed JSend collection boundary.

Twenty Adapter files import Symfony classes. Seventeen of those files are public framework adapters in
namespaces that do not say `Symfony`; seven are compiler passes. Two additional public classes,
`SymfonyCommandMessageHandler` and `SymfonyEventMessageHandler`, are documented Symfony Messenger adapters
but have no direct Symfony import. The migration scope therefore contains nineteen Symfony-semantic
declarations. (Two directly importing files are already under a `Symfony` segment and Mercure remains
provider-qualified.) The existing Adapter tree is generally
capability-first, but it is not consistently framework-second. Fully qualifying those public FQCNs is a
rename and therefore cannot stand alone in `1.2.0`: the old FQCNs need functional aliases or forwarding
shims, deprecation metadata in the minor release, and removal only in `2.0.0`.

The release evidence is incomplete by construction today. There is no committed
`compatibility/manifest.json`; the local build proves the lock or latest resolution only; hosted CI proves
latest-compatible only; and no lowest, isolated-framework, or combined-framework resolution exists. The
work below is therefore a compatibility input, not certification.

## Evidence method and limits

- Live source at commit `230c88543d4b1f55163d9a3b2791a3ce1d1575dd` is primary evidence. The public
  baseline is the repository's authoritative published `1.1.0` source, identified by ADR 0009 as commit
  `fdd4806` (`planning/adr/0009-public-api-manifest-baseline.md:8-18`).
- The inventory includes every top-level production declaration under `src/Domain`, `src/Application`, and
  `src/Adapter`, plus the production-autoloaded Domain functions. It does not claim that every public method
  or protected hook has already received its required callable/constructible/extensible/implementable
  classification; ADR 0009 explicitly requires that human classification
  (`planning/adr/0009-public-api-manifest-baseline.md:20-39`).
- “Current”, “experimental”, “obsolete”, “duplicated”, and “missing composition” below are audit
  classifications for WF-014 planning. Only `@internal`, accepted ADRs, closed tickets, and documented
  public contracts are normative today.
- External facts use official documentation or first-party source manifests. Framework versions remain a
  WF-015 decision; the manifests cited here establish dependency consequences, not the final supported-line
  ranges.

## Verified facts

### Authoritative Domain declaration inventory (131 declarations plus 13 functions)

Every declaration below is production-autoloaded. Unless already `@internal` at the `1.1.0` baseline, it is
inside the grandfathered public surface; later declarations require deliberate manifest classification
(`planning/adr/0009-public-api-manifest-baseline.md:11-18`). References are `src/Domain/<path>:<line>`.

- **Auth:** `AiOperation:13`, `Nonce:12`, `NonceRepository:12`; **Auth/Exception:**
  `NonceAlreadyConsumedException:12`.
- **Collection concrete types:** `ArrayList:22`, `ArrayQueue:20`, `ArrayStack:20`, `HashSet:21`,
  `HashTable:24`, `LinkedDeque:21`, `LinkedQueue:21`, `LinkedStack:21`, `SortedSet:28`, `SortedTable:29`.
- **Collection/Chain:** `Bucket:10`, `ItemBucket:10`, `KeyValueBucket:10`, `SetBucketChain:13`,
  `TableBucketChain:15`, `TerminalBucket:10`; **Collection/Iterator:** `ArrayQueueIterator:14`,
  `ArrayStackIterator:14`, `GeneratorIterator:21`; **Collection/Tree:** `BinarySearchTree:18`,
  `RedBlackNode:10`, `RedBlackSearchTree:22`; **Collection/Traits:** `ItemTypeMethods:10`,
  `KeyValueTypeMethods:10`.
- **Collection/Comparison:** `ComparableComparator:14`, `FloatComparator:13`, `FunctionComparator:13`,
  `IntegerComparator:13`, `StringComparator:13`.
- **Collection/Contract:** `Collection:17`, `Deque:18`, `ItemCollection:13`, `ItemList:21`,
  `KeyValueCollection:14`, `OrderedItemCollection:16`, `OrderedKeyValueCollection:17`, `OrderedSet:19`,
  `OrderedTable:20`, `Queue:18`, `Set:17`, `Stack:18`, `Table:18`.
- **EventSourcing:** `AggregateDefinition:15`, `AggregateRoot:13`, `EventMapper:19`, `EventMapping:12`,
  `EventMappingProvider:12`, `EventSourcedAggregate:13`, `EventSourcedRepository:17`, `EventStore:19`,
  `MappedEvent:12`, `StoredEvent:15`, `StreamId:14`, `Upcaster:12`; **EventSourcing/Exception:**
  `EventMappingException:14`, `OptimisticConcurrencyException:13`, `UnrecognizedEventException:13`.
- **Exception:** `AssertionException:10`, `Catchable:10`, `DomainException:10`, `ImmutableException:10`,
  `IndexException:10`, `KeyException:10`, `LookupException:10`, `MethodCallException:10`,
  `OperationException:10`, `RangeException:10`, `RuntimeException:10`, `SystemException:12`,
  `TypeException:10`, `UnderflowException:10`, `ValidationException:12`, `ValueException:10`.
- **Identity:** `Identifier:13`, `IdentifierFactory:10`, `UniqueId:18`.
- **Messaging:** `BaseMessage:15`, `Message:18`, `MessageId:12`, `MessageType:10`, `Meta:21`, `Payload:13`;
  **Messaging/Command:** `Command:12`, `CommandMessage:20`; **Messaging/Event:** `AllEvents:13`,
  `CommandFailedEvent:13`, `Event:12`, `EventMessage:20`; **Messaging/Query:** `Query:12`,
  `QueryMessage:20`.
- **Observability:** `AuditEntry:15`, `AuditEntryId:12`, `AuditRepository:15`, `HealthReport:14`,
  `HealthResult:12`, `HealthStatus:13`.
- **Repository:** `Pagination:10`, `ResultSet:19`; **Serialization:** `JsonSerializer:13`,
  `PhpSerializer:13`, `Serializable:12`, `Serializer:12`; **Specification:** `AndSpecification:10`,
  `CompositeSpecification:10`, `NotSpecification:10`, `OrSpecification:10`, `Specification:10`.
- **Type:** `Arrayable:10`, `Comparable:10`, `Comparator:10`, `Equatable:10`, `Type:15`; **Utility:**
  `ClassName:10`, `FastHasher:10`, `Validate:18`, `VarPrinter:16`.
- **Value:** `Value:24`, `ValueObject:21`; **Value/Basic:** `JsonObject:16`, `MbStringObject:26`,
  `StringObject:26`; **Value/Basic/Traits:** `StringOffsets:12`; **Value/DateTime:** `Timezone:16`;
  **Value/Identifier:** `Uuid:18`; **Value/Internet:** `EmailAddress:14`, `Uri:20`, `Url:13`.
- **Autoloaded functions** (`src/Domain/functions.php`): `string:24`, `mb_string:32`, `json_string:42`,
  `json_data:52`, `uuid:60`, `uri:70`, `url:80`, `email:90`, `array_list:102`, `hash_set:114`,
  `hash_table:132`, `array_stack:150`, and `array_queue:168`.

The only declaration-level `@internal` marker found in Domain is absent: the `@internal` occurrences on
`Type::validate`, `Uuid::validate`, `Uri::filter`, and two red-black-tree helpers mark members, not their
declaring type (`src/Domain/Type/Type.php:15-23`, `src/Domain/Value/Identifier/Uuid.php:40-48`,
`src/Domain/Value/Internet/Uri.php:72-80`, `src/Domain/Collection/Tree/RedBlackSearchTree.php:350-382`).

### Authoritative Application declaration inventory (166)

References are `src/Application/<path>:<line>`.

- **Attribute:** `Validation:13`.
- **Auth:** `Authenticator:13`, `RequestService:13`, `WebhookDispatcher:13`; **Auth/Security:**
  `PasswordHasher:12`, `PasswordValidator:10`, `TokenDecoder:12`, `TokenEncoder:13`; **Auth/Exception:**
  `AuthException:12`, `CredentialsException:10`, `PasswordException:10`, `TokenException:10`.
- **Cache:** `Cache:12`; **Cache/Exception:** `CacheException:12`.
- **EventSourcing:** `EventPublicationFailure:19`, `EventPublicationHandlerFailure:14`,
  `EventPublicationRunner:17`, `ProjectionCheckpointStore:12`, `ProjectionRunner:14`, `Projector:18`,
  `PublicationCursorStore:12`, `PublicationFailureRecorder:12`.
- **FileStorage:** `FileStorage:13`, `StorageService:15`; **FileStorage/Exception:**
  `DuplicateStorageException:14`, `FileStorageException:12`, `StorageNotFoundException:14`.
- **FileTransfer:** `FileTransferService:15`; **FileTransfer/Transport:** `FileTransport:13`;
  **FileTransfer/Resource:** `Resource:13`, `ResourceType:10`; **FileTransfer/Exception:**
  `FileTransferException:12`.
- **Filesystem:** `Filesystem:12`; **Filesystem/Exception:** `FileNotFoundException:14`,
  `FilesystemException:13`.
- **HttpClient:** `HttpService:20`; **HttpClient/Transport:** `HttpClient:15`; **HttpClient/Message:**
  `MessageFactory:14`, `Promise:14`, `StreamFactory:13`, `UriFactory:13`; **HttpClient/Exception:**
  `Exception:12`, `HttpException:16`, `NetworkException:10`, `RequestException:13`, `TransferException:12`.
- **HttpFoundation:** `HttpMethod:10`, `HttpStatus:10`.
- **Mail:** `MailService:15`; **Mail/Transport:** `MailTransport:13`; **Mail/Message:** `Attachment:10`,
  `MailFactory:10`, `MailMessage:10`, `Priority:10`; **Mail/Exception:** `MailException:12`.
- **Messaging/Command:** `AsynchronousCommandBus:10`, `CommandBus:14`, `CommandFilter:13`,
  `CommandHandler:13`, `SynchronousCommandBus:10`; **Messaging/Event:**
  `AsynchronousEventDispatcher:10`, `EventDispatchFailed:14`, `EventDispatcher:14`,
  `EventHandlerFailure:14`, `EventSubscriber:10`, `SynchronousEventDispatcher:10`;
  **Messaging/Query:** `QueryBus:14`, `QueryFilter:13`, `QueryHandler:13`.
- **Observability:** `AuditLog:12`, `HealthAggregator:12`, `HealthCheck:12`, `MetricsCollector:10`.
- **Process:** `Process:10`, `ProcessBuilder:13`, `ProcessErrorBehavior:10`, `ProcessRunner:12`;
  **Process/Exception:** `ProcessException:12`, `ProcessFailedException:10`.
- **Repository:** `UnitOfWork:12`.
- **Routing:** `UrlGenerator:12`; **Routing/Exception:** `InvalidParameterException:10`,
  `MissingParametersException:10`, `RouteNotFoundException:10`, `UrlGenerationException:12`.
- **Scheduler:** `Scheduler:38`; **Scheduler/Exception:** `LockException:10`, `SchedulerException:12`.
- **Service:** `Container:16`; **Service/Exception:** `NotFoundException:13`.
- **Sms:** `SmsService:15`; **Sms/Transport:** `SmsTransport:13`; **Sms/Message:** `SmsFactory:13`,
  `SmsMessage:12`; **Sms/Exception:** `SmsException:12`.
- **Socket:** `Publisher:12`; **Socket/Exception:** `SocketException:12`.
- **Templating:** `TemplateEngine:13`, `TemplateHelper:10`; **Templating/Exception:**
  `DuplicateHelperException:14`, `TemplateNotFoundException:14`, `TemplatingException:12`.
- **Validation coordinators/data:** `BasicValidator:12`, `ErrorMessages:10`, `RulesParser:14`,
  `ValidationContext:15`, `ValidationCoordinator:68`, `ValidationResult:14`, `ValidationService:17`,
  `Validator:10`; **Validation/Data:** `ApplicationData:18`, `ErrorData:19`, `InputData:18`;
  **Validation/Exception:** `ValidationException:14`; **Validation/Specification:**
  `EqualFieldsSpecification:15`, `RequiredFieldSpecification:15`, `SameFieldsSpecification:15`,
  `SingleFieldSpecification:16`.
- **Validation/Rule:** `CountExact:13`, `CountMax:13`, `CountMin:13`, `CountRange:13`, `InList:13`,
  `IsAlnum:13`, `IsAlnumDashed:13`, `IsAlpha:13`, `IsAlphaDashed:13`, `IsBlank:13`, `IsDateTime:14`,
  `IsDigits:13`, `IsEmail:13`, `IsEmpty:13`, `IsFalse:13`, `IsFalsy:12`, `IsIpAddress:13`,
  `IsIpV4Address:13`, `IsIpV6Address:13`, `IsJson:13`, `IsListOf:13`, `IsMatch:13`,
  `IsNaturalNumber:13`, `IsNotBlank:13`, `IsNull:13`, `IsNumeric:13`, `IsScalar:13`, `IsTimezone:14`,
  `IsTrue:13`, `IsTruthy:12`, `IsType:13`, `IsUri:13`, `IsUrn:13`, `IsUuid:13`,
  `IsWholeNumber:13`, `KeyIsset:13`, `KeyNotEmpty:13`, `LengthExact:13`, `LengthMax:13`,
  `LengthMin:13`, `LengthRange:13`, `NumberExact:13`, `NumberMax:13`, `NumberMin:13`,
  `NumberRange:13`, `StringContains:13`, `StringEndsWith:13`, `StringStartsWith:13`.

No Application declaration is marked `@internal`. Application depends only on Domain, PHP, accepted PSRs,
and the explicit `CronExpression` exception, matching the accepted layer policy
(`planning/specs/00008-PRD.md:78-93`).

### Exhaustive Adapter inventory (107 declarations)

References are `src/Adapter/<path>:<line>`. “Port” names in parentheses identify the inward contract where
one exists; framework extension points and translators are still legitimate adapters under the accepted
definition (`planning/specs/00008-PRD.md:90-95`).

- **Auth/Hmac:** `HmacAuthenticator:18` (`Authenticator`), `HmacKeyGenerator:12`, `HmacMethods:12`,
  `HmacRequestService:14` (`RequestService`), `HmacWebhookDispatcher:16` (`WebhookDispatcher`).
- **Auth/Security:** `JwtDecoder:22` (`TokenDecoder`), `JwtEncoder:22` (`TokenEncoder`),
  `PhpPasswordHasher:13` (`PasswordHasher`), `PhpPasswordValidator:12` (`PasswordValidator`).
- **Cache:** `PsrCache:16` (`Cache`).
- **DependencyInjection:** `CommandFilterCompilerPass:18`, `CommandHandlerCompilerPass:17`,
  `EventMappingProviderCompilerPass:15`, `EventSubscriberCompilerPass:17`, `QueryFilterCompilerPass:18`,
  `QueryHandlerCompilerPass:17`, `TemplateHelperCompilerPass:18` (Symfony compiler extension points).
- **Doctrine types:** `AuditEntryIdDataType:19`, `EmailAddressDataType:19`, `JsonObjectDataType:19`,
  `MbStringObjectDataType:19`, `MbStringTextDataType:19`, `MessageDataType:20`, `MetaDataType:19`,
  `StringObjectDataType:19`, `StringTextDataType:19`, `TypeDataType:19`, `UriDataType:19`,
  `UrlDataType:19`, `UuidDataType:19`.
- **EventSourcing/Dbal:** `DbalEventStore:30`, `DbalEventStoreSchema:15`,
  `DbalProjectionCheckpointStore:17`, `DbalProjectionCheckpointStoreSchema:15`,
  `DbalPublicationCursorStore:17`, `DbalPublicationCursorStoreSchema:15`,
  `DbalPublicationFailureRecorder:17`, `DbalPublicationFailureRecorderSchema:15`.
- **EventSourcing/InMemory:** `InMemoryEventRecord:17`, `InMemoryEventStore:21`,
  `InMemoryProjectionCheckpointStore:15`, `InMemoryPublicationCursorStore:15`,
  `InMemoryPublicationFailureRecorder:15`; **EventSourcing/Logging:**
  `LoggingPublicationFailureRecorder:18`.
- **EventSubscriber:** `SymfonyExceptionSubscriber:16`, `SymfonyValidationSubscriber:18`.
- **FileStorage:** `FlysystemStorage:18`; **FileTransfer/Ftp:** `FtpFileTransport:18`;
  **FileTransfer/Sftp:** `SftpFileTransport:18`; **FileTransfer/Logging:** `LoggingFileTransport:14`;
  **FileTransfer/Null:** `NullFileTransport:12`.
- **Filesystem:** `SymfonyFilesystem:17` (`Filesystem`).
- **HttpClient/Guzzle:** `GuzzleClient:18`, `GuzzleMessageFactory:17`, `GuzzlePromise:20`,
  `GuzzleStreamFactory:16`, `GuzzleUriFactory:16`; **HttpClient/Logging:** `LoggingHttpClient:18`.
- **HttpFoundation:** `JSendResponse:14`; **HttpKernel:** `ErrorController:16`,
  `JsonRequestMiddleware:18`.
- **Mail/Logging:** `LoggingMailTransport:15`; **Mail/Null:** `NullMailTransport:13`;
  **Mail/Symfony:** `SymfonyAttachment:13`, `SymfonyMailFactory:14`, `SymfonyMailTransport:20`.
- **Messaging/Command/Async:** `MessengerCommandBus:16`; **Messaging/Command:**
  `MetricsCommandFilter:15`; **Messaging/Command/Sync:** `CommandPipeline:17`, `RoutingCommandBus:15`;
  **Messaging/Command/Sync/Routing:** `CommandRouter:14`, `InMemoryCommandRouter:16`,
  `ServiceAwareCommandRouter:17`.
- **Messaging/Event/Async:** `MessengerEventDispatcher:17`; **Messaging/Event/Sync:**
  `ServiceAwareEventDispatcher:18`, `SimpleEventDispatcher:21`; **Messaging/Handler:**
  `SymfonyCommandMessageHandler:14`, `SymfonyEventMessageHandler:14`.
- **Messaging/Query:** `MetricsQueryFilter:15`, `QueryPipeline:17`, `RoutingQueryBus:15`;
  **Messaging/Query/Routing:** `InMemoryQueryRouter:16`, `QueryRouter:14`,
  `ServiceAwareQueryRouter:17`; **Messaging/Serializer:** `SymfonyMessageSerializer:29`.
- **Observability/Audit:** `LoggingAuditLog:15`, `NullAuditLog:13`; **Observability/Health:**
  `DatabaseHealthCheck:16`, `HealthReporter:14`, `HttpEndpointHealthCheck:17`;
  **Observability/Metrics:** `NullMetricsCollector:12`, `StatsDMetricsCollector:16`,
  `UdpMetricSender:17`.
- **Process/Symfony:** `SymfonyProcessRunner:24`; **Repository:** `DoctrineRepository:15`,
  `DoctrineUnitOfWork:13`; **Repository/Doctrine:** `DoctrineAuditRepository:21`,
  `DoctrineNonceRepository:21`; **Repository/InMemory:** `InMemoryNonceRepository:15`.
- **Routing:** `SymfonyUrlGenerator:21`; **Sms/Logging:** `LoggingSmsTransport:15`; **Sms/Null:**
  `NullSmsTransport:13`; **Sms/Twilio:** `TwilioSmsTransport:16`; **Socket:**
  `MercureHubPublisher:16`; **Templating:** `DelegatingEngine:15`, `PhpEngine:17`, `TwigEngine:17`.

`SymfonyAttachment` and `UdpMetricSender` are the only whole Adapter declarations marked `@internal`
(`src/Adapter/Mail/Symfony/SymfonyAttachment.php:13-20`,
`src/Adapter/Observability/Metrics/UdpMetricSender.php:12-19`). Adapter-local router interfaces are public
declarations today even though they are outward implementation seams, not Application ports.

### Classification and current composition gaps

| Classification | Verified members | Decision consequence |
| --- | --- | --- |
| Current, portable core | All Domain contracts; Application messaging, validation, repository, HTTP-client, storage, transfer, filesystem, mail, SMS, socket, process, scheduling, auth, cache, templating, routing, and observability ports/services | Preserve and compose. Domain/Application remain framework-neutral. |
| Current, portable adapters | In-memory, null, logging, HMAC/PHP/JWT, Guzzle, Flysystem, FTP/SFTP, DBAL/Event Sourcing, StatsD, Twilio, Twig/PHP templating | Reuse across starters where their dependency is selected; do not reimplement per framework. |
| Current, Symfony-specific but incompletely named | The 19 unqualified Symfony-semantic Adapter declarations listed below | Add framework-qualified FQCNs; retain old public FQCNs as deprecated compatibility paths in `1.x`. |
| New in the `1.2` candidate | 41 Event Sourcing/event-dispatch/observability declarations relative to `1.1.0` | Deliberately classify each public/internal in the manifest and bind documented behavior fixtures. |
| Experimental | Scheduler command execution is documented with an “experimental-sync” example, while its required runner constructor is already enforced (`docs/scheduler.md:311-327`; `tests/Application/Scheduler/SchedulerTest.php:33-41`) | The example label does not erase a public constructor. Treat Scheduler as current and repair compatibility. |
| Obsolete/deprecation candidates | `Adapter\HttpFoundation\JSendResponse`, unqualified Symfony namespaces, raw-array JSend entry points | Keep functional through at least one released minor; announce deprecation in `1.2`; remove only in `2.0` (`planning/adr/0011-non-structural-compatibility-policy.md:40-46`). |
| Duplicated | `Domain\ValidationException` and `Application\Validation\Exception\ValidationException` are distinct historical names; Adapter-local `CommandRouter`/`QueryRouter` sit beside inward bus contracts; `Application\HttpFoundation` and `Adapter\HttpFoundation` both embed a Symfony capability name | Do not merge blindly. Audit consumer meaning; namespace repair is safer than signature consolidation in `1.2`. |
| Missing viable composition | Five native JSend responses; Laravel/Yii/CodeIgniter/Slim native HTTP and framework wiring; native transaction adapters beyond Doctrine; framework-native URL generation, queues, and service registration; isolated framework locks | Fill only proven gaps. WF-015/WF-017 must test native composition before authorizing new shared adapters. |

There is no evidence that the current contracts as a group are obsolete. The primary gap is composition, not
the absence of ports. WF-009 explicitly measures full support by complete consumer journeys rather than equal
adapter counts (`../tickets/WF-009-portability-destination-and-release-boundaries.md:10-18`).

### Exact Scheduler compatibility analysis

Published `1.1.0` constructor:

```php
__construct(
    Timezone $timezone,
    string $tempDirectory,
    ?LoggerInterface $logger = null,
    ?MailService $mailService = null,
    string $fromEmail = '',
    ?Closure $processFactory = null,
)
```

Current constructor:

```php
__construct(
    Timezone $timezone,
    string $tempDirectory,
    ProcessRunner $processRunner,
    ?LoggerInterface $logger = null,
    ?MailService $mailService = null,
    string $fromEmail = '',
)
```

Evidence: current signature and use are `src/Application/Scheduler/Scheduler.php:48-55,245-277`; the current
test explicitly requires non-null argument three (`tests/Application/Scheduler/SchedulerTest.php:33-41`);
the accepted architecture PRD nevertheless says the existing registration/execution API remains compatible
(`planning/specs/00008-PRD.md:84-89`).

Breaks by consumer style:

1. `new Scheduler($tz, $dir)` now throws `ArgumentCountError`.
2. `new Scheduler($tz, $dir, $logger)` now throws `TypeError`, because argument three changed type and
   meaning.
3. Every positional argument from three through six changed position or disappeared.
4. Named `logger`, `mailService`, and `fromEmail` calls remain name-compatible only if the consumer also adds
   a required `processRunner`; named `processFactory` no longer exists.
5. Consumers supplying a custom `processFactory` lost their construction seam. Replacing that seam with a
   `ProcessRunner` is architecturally sound but still incompatible.

**Additive repair input:** restore the complete published constructor shape for `1.2` and obtain the
`ProcessRunner` without inserting a required parameter before existing optional parameters. The narrowest
options to prototype are (a) retain `processFactory` and adapt it internally behind `ProcessRunner`, while
adding an optional final `?ProcessRunner $processRunner = null`; or (b) add a named factory such as
`Scheduler::withProcessRunner(...)` and preserve the old constructor. The default fallback must reproduce the
`1.1` command behavior, not silently disable command jobs. If no compatible default can be implemented without
reintroducing an Application-to-Symfony dependency, defer the required-constructor form to `2.0.0`. Making the
third parameter nullable is insufficient because it still reinterprets positional logger calls.

### Typed JSend, `Arrayable`, `ResultSet`, encoding, and native responses

Verified current behavior:

- `JSendResponse` extends Symfony `JsonResponse`, accepts `?array` for success/fail/error data, defaults
  success data to `null`, omits error `data` and `code` when null, and exposes status predicates
  (`src/Adapter/HttpFoundation/JSendResponse.php:14-155`). Tests lock those raw-array shapes and status codes
  (`tests/Adapter/HttpFoundation/JSendResponseTest.php:15-148`).
- Default encoding options are decimal `79`: JSON hex escaping plus unescaped slashes
  (`src/Adapter/HttpFoundation/JSendResponse.php:16-28`). Symfony constructs with its own options first, then
  `setEncodingOptions()` decodes and re-encodes (`vendor/symfony/http-foundation/JsonResponse.php:30-49,123-163`).
  PHP documents that `json_encode()` recursively encodes values, requires UTF-8 strings, returns `string|false`,
  and is affected by the supplied flags ([PHP `json_encode`](https://www.php.net/manual/en/function.json-encode.php)).
- `Arrayable::toArray()` is fixed to `array<string,mixed>` (`src/Domain/Type/Arrayable.php:10-17`). That is
  unsuitable for list-valued presentation data and cannot express its output shape as a template.
- `ResultSet` says `Collection<int,object>` and accepts `ArrayList<object>`, but its own tests use
  `ArrayList<string>` (`src/Domain/Repository/ResultSet.php:14-41`;
  `tests/Domain/Repository/ResultSetTest.php:19-46`). Runtime item typing is stored as a nullable type string
  (`src/Domain/Collection/Traits/ItemTypeMethods.php:10-36`). Its array/JSON representation is the accepted
  pagination object (`src/Domain/Repository/ResultSet.php:114-135`; `docs/repositories.md:63-106`).
- Existing Symfony exception mapping incorrectly sends expected missing/denied HTTP failures through
  `JSendResponse::error`, while WF-011 classifies expected request/auth/resource failures as `fail`
  (`docs/validation.md:307-333`; `../tickets/WF-011-versioned-http-jsend-and-presentation-contracts.md:19-32`).

Decision input for the final API:

1. Add a framework-neutral immutable JSend payload/envelope type that owns `status`, typed `data`, optional
   error `message`, and optional integer `code`. It must not extend a native response. Preserve the three
   established serialized shapes; the original JSend contract requires `status` and `data` for success/fail,
   and `status` plus `message` for error, with optional `code` and `data` (the historical specification is
   mirrored by [RFC 8522 section 3.1](https://www.rfc-editor.org/rfc/rfc8522.html#section-3.1)).
2. Make `Arrayable` covariantly describe both key and value output, e.g. `@template TKey of array-key` and
   `@template TValue`, with `@return array<TKey,TValue>`. This is PHPDoc-only/additive at runtime, but every
   implementer must pass static analysis before release.
3. Make `ResultSet` `@template TData of Arrayable` for the WF-011 presentation boundary, or preserve its
   broader existing runtime `T` and introduce a separately typed presentation result contract. Narrowing
   current `ResultSet` from object/scalar records to `Arrayable` at runtime would be incompatible. The safe
   `1.2` path is PHPDoc generics plus projection at the JSend payload boundary.
4. Convert one `Arrayable` item with `toArray()` and a `ResultSet<Arrayable>` by mapping each record's
   `toArray()` while preserving `page`, `per_page`, `total_pages`, `total_records`, and `records`. Do not pass
   domain aggregates to this boundary.
5. Keep raw arrays accepted in `1.2` on deprecated entry points; do not emit a runtime deprecation warning
   unless its behavior and tests are deliberately accepted, because warnings are behavioral changes
   (`planning/adr/0011-non-structural-compatibility-policy.md:40-46`). Require typed data only in `2.0`.
6. Put encoding policy in the neutral payload serializer (UTF-8, failure behavior, stable semantic keys) and
   make native adapters responsible for HTTP status, headers/content type, body construction, and conversion
   to their native response. Semantic JSON shape is stable; whitespace and object-key order are not unless
   explicitly promoted (`planning/adr/0011-non-structural-compatibility-policy.md:17-27`). Preserve option `79`
   on the deprecated Symfony path; a change in escaping is a behavioral contract decision.
7. Add exactly the closed-ticket native adapters:
   `Adapter\Http\Symfony\JSendResponse`, `Laravel`, `Yii`, `CodeIgniter`, and `Slim`. Each consumes the same
   neutral payload and does no application lookup or projection
   (`../tickets/WF-011-versioned-http-jsend-and-presentation-contracts.md:33-57`).

### All unqualified Symfony Adapter namespaces

Twenty files import Symfony directly. `SymfonyMailTransport` and `SymfonyProcessRunner` are already
framework-second; `MercureHubPublisher` is deliberately provider-qualified; the remaining seventeen direct
importers are not. `SymfonyCommandMessageHandler` and `SymfonyEventMessageHandler` add two more
Symfony-semantic declarations: they do not import Symfony, but the public messaging documentation defines
them as Symfony Messenger handlers (`docs/messaging.md:750-770,926-935`). The complete migration scope is
therefore nineteen declarations across seven capability groups.

| Current namespace/family | Symfony-dependent declarations | Additive `1.2` destination | `2.0` consequence |
| --- | --- | --- | --- |
| `Adapter\DependencyInjection` | Seven compiler passes (`src/Adapter/DependencyInjection/*.php:5-18`) | `Adapter\DependencyInjection\Symfony\*` or, more consistently, capability-first destinations such as `Adapter\Messaging\Symfony\DependencyInjection\*`; old FQCN shims/aliases | Remove old FQCNs after one released minor |
| `Adapter\EventSubscriber` | `SymfonyExceptionSubscriber`, `SymfonyValidationSubscriber` (`src/Adapter/EventSubscriber/*.php:5-18`) | `Adapter\Http\Symfony\EventSubscriber\*`; keep deprecated old FQCNs | Remove old FQCNs; correct expected 4xx to typed JSend `fail` under versioned behavior |
| `Adapter\Filesystem` | `SymfonyFilesystem` (`src/Adapter/Filesystem/SymfonyFilesystem.php:5-17`) | `Adapter\Filesystem\Symfony\SymfonyFilesystem`; old shim | Remove old FQCN |
| `Adapter\HttpFoundation` | `JSendResponse` (`src/Adapter/HttpFoundation/JSendResponse.php:5-14`) | `Adapter\Http\Symfony\JSendResponse` consuming neutral payload; old class remains functional | Remove `HttpFoundation` FQCN and raw-array-only API |
| `Adapter\HttpKernel` | `ErrorController`, `JsonRequestMiddleware` (`src/Adapter/HttpKernel/*.php:5-18`) | `Adapter\Http\Symfony\ErrorController` and `...\JsonRequestMiddleware`; old shims | Remove old FQCNs |
| `Adapter\Messaging\Command\Async`, `...Event\Async`, `...Serializer`, `...Handler` | `MessengerCommandBus`, `MessengerEventDispatcher`, `SymfonyMessageSerializer`, `SymfonyCommandMessageHandler`, `SymfonyEventMessageHandler` (`src/Adapter/Messaging/**:5-29`) | `Adapter\Messaging\Symfony\...`; retain old public paths | Remove old FQCNs; generic `Async` and unqualified Handler cannot remain the only framework identity |
| `Adapter\Routing` | `SymfonyUrlGenerator` (`src/Adapter/Routing/SymfonyUrlGenerator.php:5-21`) | `Adapter\Routing\Symfony\SymfonyUrlGenerator`; old shim | Remove old FQCN |
| Already qualified | `Adapter\Mail\Symfony\{SymfonyAttachment,SymfonyMailFactory,SymfonyMailTransport}` and `Adapter\Process\Symfony\SymfonyProcessRunner` (only the transport and runner import Symfony directly) | No namespace move required | None from qualification alone |

`Adapter\Socket\MercureHubPublisher` also imports `Symfony\Component\Mercure`, but “Mercure” is the selected
protocol/package capability rather than a Symfony-framework composition root
(`src/Adapter/Socket/MercureHubPublisher.php:5-18`). It does not require a `Symfony` segment merely because
the component vendor is Symfony. The same distinction keeps `Adapter\Filesystem\Symfony` qualified because
that implementation is explicitly named `SymfonyFilesystem`, while `Adapter\HttpClient\Guzzle` is qualified
by provider.

PHP `class_alias()` can preserve class names but not interfaces/traits/enums uniformly as a complete policy,
and aliases affect reflection identity. Forwarding shims may be required where constructor, extension, or
static-return behavior must remain exact. The compatibility manifest must classify each old and new FQCN and
tests must construct both. A namespace rename without the old functional path is major under ADR 0013
(`planning/adr/0013-compatibility-manifest-and-certification-composition.md:10-17`).

### Capability-first, framework-second assessment

The desired standard is:

```text
Adapter\<Capability>\<Framework-or-Provider>\<Type>
```

The tree already follows it for `Auth/Hmac`, `Auth/Security`, `EventSourcing/Dbal`, `FileTransfer/{Ftp,Sftp}`,
`HttpClient/Guzzle`, `Mail/Symfony`, `Process/Symfony`, `Sms/Twilio`, and null/logging variants. It fails the
framework-second test in the seven rows above. It also has provider-only top-level families (`Doctrine`,
`DependencyInjection`, `EventSubscriber`, `HttpFoundation`, `HttpKernel`) that obscure capability ownership.

Additive changes: introduce new correctly qualified classes; add five native JSend adapters; add missing
framework adapters only after prototype evidence; add suggestions and dev-only verification packages; add
aliases/shims and deprecation documentation.

Non-additive changes: delete or rename an old FQCN without a shim; narrow JSend from arrays to `Arrayable`;
reinterpret Scheduler positional parameters; remove `processFactory`; change established serialized field
semantics; require optional framework packages at consumer runtime; merge exception/router types in a way
that removes distinct contracts. Those belong in `2.0` unless an exact compatibility mechanism makes the
consumer-visible effect additive.

### Composer consequences for five frameworks

Current production `require` contains only PHP and seven PSR packages (`composer.json:5-13`). Every optional
adapter package lives in root `require-dev` and `suggest` (`composer.json:14-69`), which matches WF-009's
ownership decision. Composer defines `require` as packages without which installation cannot succeed,
`require-dev` as root-only development/test dependencies omitted by `--no-dev`, and `suggest` as informational
optional enhancements ([Composer schema: package links](https://getcomposer.org/doc/04-schema.md#package-links)).

Consequences:

- **`require`:** do not add Laravel, Yii, CodeIgniter, Slim, or Symfony framework packages. Keep only neutral
  contracts used directly by Domain/Application or unconditional runtime code. A framework package in
  `require` would force every consumer to install it and could exclude a previously valid install, which is a
  major effect under `planning/adr/0011-non-structural-compatibility-policy.md:29-35`.
- **`require-dev`:** add explicit ranges for each framework/package needed to load and test the five native
  response/composition adapters. Do not use only a monolithic framework package if the adapter actually
  targets a smaller official component. Root dev requirements must be mutually resolvable for the combined
  lane, while isolated per-framework Composer fixtures prove each supported range independently.
- **`suggest`:** add each exact consumer package that activates an optional adapter, with a capability-specific
  reason and the supported range in documentation (the value itself is free text, not a constraint). Retain
  existing adapter suggestions and correct stale descriptions as adapters move.

Framework-specific inputs from current first-party manifests:

| Framework | First-party dependency facts | Fight Common consequence |
| --- | --- | --- |
| Symfony | Symfony 8.1 requires PHP `>=8.4.1`, provides PSR implementations, and replaces individual Symfony components ([Symfony 8.1 `composer.json`](https://github.com/symfony/symfony/blob/8.1/composer.json)) | Prefer exact component dev requirements already used; verify both a full Symfony composition and component-only consumers. Current `^8.0` components resolve to 8.1 under PHP 8.5. |
| Laravel | Laravel 13 requires PHP `^8.3` and permits Symfony 7.4 or 8.0 components ([Laravel 13 `composer.json`](https://github.com/laravel/framework/blob/13.x/composer.json)) | An exact Laravel range belongs in dev/suggest only. Its Symfony overlap makes the combined lane important; do not assume a standalone Symfony lock proves Laravel compatibility. |
| Yii 3 | The official app currently supports PHP `8.2 - 8.5`, PSR-7/17/11/15 contracts, and many independently versioned `yiisoft/*` packages ([Yii app `composer.json`](https://github.com/yiisoft/app/blob/master/composer.json)) | Test the chosen app/component set, not a stale umbrella `yiisoft/yii-web` assumption; prefer PSR-native JSend adaptation and Yii-native DI/config wiring. |
| CodeIgniter 4 | Current first-party develop manifest requires PHP `^8.2`, `psr/log ^3`, and few unconditional packages ([CodeIgniter 4 `composer.json`](https://github.com/codeigniter4/CodeIgniter4/blob/develop/composer.json)) | Add the exact installable framework package/range in dev/suggest; expect native HTTP/DI/database contracts rather than Symfony components. Verify MySQL/MariaDB and PostgreSQL starter composition separately. |
| Slim 4 | Slim 4 supports PHP through `~8.5.0`, requires PSR-7/11/15/17 contracts, and deliberately leaves the PSR-7 implementation optional ([Slim 4 `composer.json`](https://github.com/slimphp/Slim/blob/4.x/composer.json)) | The Slim adapter should return PSR-7 through an injected response/stream factory; the isolated lock must select an explicit PSR-7 implementation. Do not make `slim/psr7` an unconditional Fight Common runtime dependency. |

The final version ranges remain unknown until WF-015 resolves “current and previous maintained line” security
and mutual-solvability evidence. The current root pins PHP platform `8.5.4` (`composer.json:85-97`), so it
cannot alone prove framework-declared lower PHP versions; Fight Common itself already requires `>=8.5`.

### Evidence matrix required to certify `1.2.0`

| Lane | Resolution input | Required proof | Current state |
| --- | --- | --- | --- |
| Public API baseline | Authoritative `1.1.0` at `fdd4806` plus candidate | Complete generated inventory; intentional manifest; operation-level structural diff; old/new FQCN and Scheduler consumer probes; behavioral/serialization fixtures | **Missing.** ADR accepted, but `compatibility/manifest.json` does not exist. |
| Repository locked | Tracked `composer.lock`; `composer install` | Full `./bin/quality`, MySQL/PostgreSQL, exact coverage, adapter fixtures, no lock drift | **Available locally:** `bin/build:5,39-51`; not run for this planning-only note. |
| Lowest permitted | Manifest constraints resolved with `composer update --prefer-lowest --prefer-stable` in a disposable lock | Full quality gate and all designated adapters; record exact package versions; no ignored platform/security requirements | **Missing.** No script or workflow lane. Composer documents `--prefer-lowest` as the minimum-constraint test ([Composer CLI](https://getcomposer.org/doc/03-cli.md#update-u-upgrade)). |
| Latest permitted | Fresh resolution from `composer.json` | Full quality gate and exact generated lock receipt | **Available but incomplete for portability:** `bin/build --latest` (`bin/build:7-8`) and hosted CI (`.github/workflows/tests.yml:63-67`). |
| Isolated framework locks | Five minimal fixture roots, each requiring Fight Common candidate plus one framework/native-response dependency set | Lowest and latest solve for each; adapter contract/functional journey; native response, DI, routing, transaction, and documented capability wiring | **Missing.** No fixture roots or workflows. |
| Combined dependencies | Root/dev or dedicated fixture containing all five exact supported ranges and all optional adapters | `composer update` latest and lowest where meaningful; no conflict hidden by isolated locks; full quality/adapter suite | **Missing.** Current dev set covers Symfony components but none of Laravel/Yii/CodeIgniter/Slim. |
| Consumer compatibility | Minimal code fixtures compiled/run against `1.1.0` and candidate | Published Scheduler positional/named construction; old namespace construction; raw JSend arrays; public interfaces implemented by consumer stubs | **Missing.** Current Scheduler test proves the new incompatible signature rather than compatibility. |
| Package surface/archive | Candidate Composer metadata and exported archive | Production autoload, `--no-dev` install, no accidental framework hard dependency, suggestions present, archive/source equivalence | **Planned by ADR 0013/0014; not currently executable as a certification command.** |

ADR 0013 requires these surfaces to be composed rather than delegated to one checker
(`planning/adr/0013-compatibility-manifest-and-certification-composition.md:22-48`). The current quality command
is necessary but not sufficient (`bin/quality:5-37`).

## Recommendations and decision inputs

1. **Keep `1.2.0` conditional.** Authorize it only if Scheduler preserves every `1.1` construction style,
   old Symfony-related FQCNs and raw-array JSend remain functional, and every additive declaration is
   intentionally classified. Otherwise move the incompatible pieces to `2.0.0` rather than relabeling them.
2. **Define one neutral JSend payload before five responses.** This prevents five adapters from inventing
   serialization. Native adapters should translate only HTTP status/headers/body and native return type.
3. **Repair generics without narrowing runtime inputs.** Generalize `Arrayable`'s key/value PHPDoc and
   `ResultSet<T>` accurately first; enforce `TData : Arrayable` at the typed payload projection seam, not by
   rejecting existing scalar/object ResultSets in a minor release.
4. **Namespace by capability then framework/provider.** Introduce new paths additively and choose aliases
   versus forwarding shims per operation promise. Treat compiler passes and event subscribers as messaging or
   HTTP capability adapters, not a miscellaneous Symfony bucket.
5. **Prototype before adding adapters.** If Laravel/Yii/CodeIgniter/Slim can bind an existing port directly in
   the starter, document that composition instead of adding a redundant Fight Common class. Shared adapters
   require repeatable capability logic or a native translation seam.
6. **Make dependency evidence executable.** Add repository-owned isolated/combined Composer fixtures and a
   lowest lane; bind exact lock receipts to certification. Framework `suggest` entries are discoverability,
   not proof that the code loads.

## Downstream contract-to-capability worksheet

WF-015 through WF-017 should copy and complete one row per framework. “Existing composition” must name a
concrete class or direct container binding; “new adapter” requires evidence that native wiring cannot satisfy
the port cleanly.

| Capability | Canonical Domain/Application contract | Existing reusable adapter/composition | Framework-native seam to inspect | Evidence required before new shared adapter |
| --- | --- | --- | --- | --- |
| Commands | `CommandBus`, sync/async variants, handlers/filters | `RoutingCommandBus`, `CommandPipeline`, in-memory/service-aware routers; Symfony Messenger async | Native bus/queue/container handler map | Dispatch + exactly-one handler + filter order + failure behavior |
| Queries | `QueryBus`, `QueryHandler`, `QueryFilter` | `RoutingQueryBus`, `QueryPipeline`, in-memory/service-aware routers | Native container or deterministic handler map | Return type, filter order, missing/duplicate route behavior |
| Events | `EventDispatcher`, sync/async variants, `EventSubscriber` | `SimpleEventDispatcher`, `ServiceAwareEventDispatcher`, Symfony Messenger async | Native events/queue or portable dispatcher | Two-phase ordering, fan-out failure aggregation, async acknowledgement boundary |
| Event Sourcing | Domain Event Store/aggregate/mapping contracts; Application runners/stores | DBAL, in-memory, logging adapters; Symfony mapping compiler pass | DBAL/container/worker wiring | Store conformance, projection/publication cursors, failure recording, transaction behavior |
| Persistence | Domain repositories/`Pagination`/`ResultSet`; Application `UnitOfWork` | Doctrine repositories/UoW, in-memory nonce | Doctrine, Eloquent, Yii DB/AR, CI Model/Query Builder, Slim-selected stack | Aggregate hydration, atomic commit/rollback, nullable find, pagination semantics |
| HTTP client | `HttpClient`, factories, `Promise`, `HttpService` | Guzzle and logging adapters; PSR contracts | Framework client or PSR-18 binding | Sync/async/error translation and PSR message compatibility |
| HTTP/JSend | Neutral typed payload to add; `Arrayable`, `ResultSet` | Old Symfony `HttpFoundation\JSendResponse` only | Native response plus PSR-7 factories for Slim/Yii | Exact success/fail/error JSON, encoding, headers, status, null/optional fields |
| Routing/URL | `UrlGenerator` | `SymfonyUrlGenerator` | Native named-route generator | Parameter/missing/route exception translation and absolute/relative behavior |
| Validation | `ValidationService`, coordinator, rules, data/result types | Portable implementation; Symfony subscribers | Native request extraction/exception middleware only | Same field-error data; expected failures map to JSend `fail` |
| Authentication | `Authenticator`, `RequestService`, password/token/webhook ports | HMAC, PHP password, JWT | Native principal/provider/middleware/cookie/session | Shared identity remains authoritative; no framework interface in shared Domain |
| Cache | `Cache` | `PsrCache` | PSR-6/native cache binding | TTL, miss, delete/clear and exception behavior |
| File storage | `FileStorage`, `StorageService` | Flysystem | Native storage or Flysystem binding | Named backend selection and content operation parity |
| Filesystem | `Filesystem` | Symfony filesystem | Native/PHP implementation if selected | Local path operations and exception translation |
| File transfer | `FileTransport`, `FileTransferService` | FTP, SFTP, logging, null | Usually portable direct binding | Send/get/list/resource semantics; no framework adapter without a seam |
| Mail | `MailTransport`, `MailFactory`, `MailService` | Symfony, logging, null | Laravel/Yii/CI/native mailer | Attachments, priority, sender/recipient, failure translation |
| SMS | `SmsTransport`, `SmsFactory`, `SmsService` | Twilio, logging, null | Usually direct binding | Message construction and transport failure behavior |
| Templating | `TemplateEngine`, `TemplateHelper` | Twig, PHP, delegating | Twig/Blade/Yii view/CI view/Slim-selected renderer | Render/context/helper registration and missing-template behavior |
| Process | `Process`, `ProcessBuilder`, `ProcessRunner` | Symfony process runner | Portable binding unless native runner is superior | stdout/stderr, concurrency, stop-on-error, exit/failure semantics |
| Scheduler | `Scheduler` plus `ProcessRunner` | Scheduler core + Symfony process runner | Native schedule registration/runner or portable Scheduler | `1.1` constructor compatibility, due/lock/output/notification behavior |
| Observability | `AuditLog`, `HealthCheck`, `HealthAggregator`, `MetricsCollector`; Domain health/audit data | logging/null audit, health reporter/checks, StatsD/null metrics | PSR logger, framework health endpoint/metrics hooks | Stable health status aggregation, audit context, counter/gauge/timing behavior |
| Socket/push | `Publisher` | Mercure | Framework/websocket-selected publisher | Publish payload/topic/error translation |
| DI/composition | PSR-11 `Container`; public handler/subscriber contracts | Lightweight Container, Symfony compiler passes | Symfony config, Laravel provider, Yii provider/DI, CI Services, Slim explicit config | Every chosen binding loadable without unrelated optional packages |

Per framework, append these columns in its research note: **selected maintained versions**, **exact Composer
constraint**, **native facility**, **existing Fight Common binding**, **starter-owned wiring**, **new shared
adapter (yes/no + reason)**, **lowest lock receipt**, **latest lock receipt**, **functional journey**, and
**remaining unknown**.

## Remaining unknowns

- The final supported current/previous lines and exact package names/ranges for all five frameworks; WF-015
  owns this and must include security/maintenance status plus actual Composer solving.
- Whether every grandfathered non-final class is intentionally extensible, and whether Adapter-local router
  interfaces are intentionally implementable. That requires manifest-level history/consumer evidence, not a
  visibility inference.
- Whether any known downstream consumer constructs Scheduler positionally, supplies `processFactory`, extends
  `ErrorController`, or reflects exact old FQCNs. The compatibility policy preserves those supported
  operations even if the consumer audit finds no examples, but usage determines shim test priority.
- Whether a default portable `ProcessRunner` can preserve `1.1` Scheduler command behavior without an
  unconditional Symfony dependency. A focused prototype must answer this before choosing repair versus 2.0
  deferral.
- The exact neutral JSend payload class name, generic variance, error-data type, and invalid UTF-8/failure
  policy. WF-011 fixed the semantic direction, not these PHP signatures.
- Which framework-native adapters are genuinely reusable enough for Fight Common rather than starter-owned
  composition. WF-015/WF-017 walking slices must supply that evidence.
- Whether the combined five-framework dependency set can resolve at both lowest and latest bounds. No current
  lock proves it.

## Resolution boundary

This note supplies evidence and recommendations only. It does not close WF-014, authorize `1.2.0`, or choose
the framework lines. Any accepted decisions must be copied into the canonical ticket/map through the normal
HITL planning workflow before implementation.
