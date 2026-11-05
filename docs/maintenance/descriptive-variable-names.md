# Descriptive variable names and retained contracts

[TASK-00192](https://github.com/johnnickell/fight-common/blob/develop/planning/tasks/00192-TASK.md) implements a semantic naming pass, not a type-suffix rule.
Repositories, buses, transaction boundaries, readers, factories and stores are distinguished from their returned
collections/data. Existing mathematical operands, parser indices and well-established framework callback idioms
remain concise. No message/Tool identity, schema, wire key, operation ordering or business rule changes.
Independent Review and QA own acceptance; this inventory is implementation documentation, not their verdict.

## Inventory and method

The checked boundary is every tracked owned executable source and maintained example, including:

| Boundary | Coverage and disposition |
| --- | --- |
| `src` | All 943 PHP files and the shipped PHPCS ruleset. Complete variable/property/declaration/use inventories, actual type/caller inspection for ambiguous roles, and tagged public-name comparison. Includes retained legacy namespace implementations and aliases. |
| `tests` | All 663 PHP files and 17 PHPCS `.inc` fixtures, including documentation fixtures, anonymous doubles, closure captures, HTTP/database workers and fixture property callers. The historical serialized-foundation JSON remains immutable evidence. |
| `release` | All eight PHP files, including the maintained/legacy consumer probes. Maintainer-only `Fight\Release` collaborators are renamed directly; no release-process tests or certification run are added. |
| Root PHP | All four Deptrac/Rector PHP configuration files. Configuration names already describe their roles. |
| Python | All 20 `scripts/*.py` / `scripts/local_tooling/*.py` files, `bin/.disposable-database-lifecycle.py` and Python `bin/database-runtime`; embedded Python in Dockerfiles/release/docs is included. |
| Shell | All 20 shell `bin` entry points, `.githooks/pre-commit`, and maintained shell fragments; embedded PHP in `bin/coverage` is included. Environment/CLI/receipt identifiers remain contracts. |
| Browser/templates | Both owned `docs/javascripts` files, the CSS custom-property surface and all eight HTML/Jinja templates. DOM locals clarify selectors/elements; MkDocs/Jinja context and Material's `document$` remain supplier contracts. |
| Maintained examples | All current guide/component code fences, include-backed `tests/Documentation` examples and fresh refactor examples. Bad-name illustrations in Naming/TASK-00192 are intentional counterexamples, not overlooked collaborators. |
| Declarative contracts | Composer/autoload, compatibility manifest, Compose/workflows, ORM XML, local-tooling configuration, PHPCS/PHPStan/PHPUnit/MkDocs configuration and Worktrunk hooks are checked for name bindings; their keys are not variable rename targets. |

The canonical ignored Engineer handoff retains the complete per-path inventories, identifier locations, source
hashes, changed-path mapping, tag observations and actual check results. Every owned path is accounted for;
this is not a sampled regex claim. Complete lexical/declaration coverage does not mean every repeated body was
manually read in full or that this chore is a general behavioral/security audit. Ambiguous candidates were traced
to responsibilities and callers before correction. Vendor/generated output, Git data, ignored runs, immutable
historical snapshots/receipts, third-party quoted contracts and nonexecutable artwork are excluded; owned generators
and validators are included. No unreadable owned path was encountered.

## Corrected responsibilities

- [Task handlers](https://github.com/johnnickell/fight-common/tree/develop/src/Application/Mcp/Task) retain explicit
  `taskRepository`, `committedTaskReader`, `transactionalUnitOfWork`, admissions, policies and `eventDispatcher`.
  Their fixtures/callers now distinguish those capabilities too. `McpTasks` is `taskCapability`, not a repository
  or Task collection; actual Task/Notice/event arrays keep collection names.
- [Execution/Notice handlers](https://github.com/johnnickell/fight-common/tree/develop/src/Application/Mcp) name
  their operation-specific reader/admission/recorder/remover; publication uses `queryBus`, `commandBus` and
  `noticePublication`. Subscription composition uses `subscriptionStore`, `subscriptionAdmission` and
  `taskDisclosureGuard`. Provisional constructor names change directly, with owned named callers updated.
- [DBAL MCP adapters](https://github.com/johnnickell/fight-common/tree/develop/src/Adapter/Mcp/Persistence)
  distinguish `recordingTransaction`, `executionReadSupport`, Table builders and SQL strings/rows.
  [Concrete Tools](https://github.com/johnnickell/fight-common/tree/develop/src/Adapter/Mcp/Tool) distinguish
  validated input, parsed arguments and their typed result projections.
- [Messaging](https://github.com/johnnickell/fight-common/tree/develop/src/Adapter/Messaging) distinguishes
  metric collectors, query results, handler-service IDs, registration tuples, loaded services and decoded stamps.
  Laravel's native job dispatcher is `jobDispatcher`, not Fight's event dispatcher.
- [Other adapters](https://github.com/johnnickell/fight-common/tree/develop/src/Adapter) and
  [Application services](https://github.com/johnnickell/fight-common/tree/develop/src/Application) distinguish
  mail/SMS/file transports, factories, OAuth/token handoff, HTTP error projection, publication/checkpoint stores,
  native renderers and process/output roles. The storage registry is `storagesByKey`, not one storage backend.
- [Domain collections/values](https://github.com/johnnickell/fight-common/tree/develop/src/Domain) clarify matching
  versus nonmatching partitions, key comparisons, URI components, UUID timestamp units, serialization envelopes
  and formatted output without changing their arithmetic, bytes or ordering.
- Current guides use the same distinctions. Tooling locals clarify receipt paths, lane checkout paths, Docker
  containers/mounts/networks, source/runtime fingerprints and placed shapes. Receipt/configuration keys and
  native resource ownership checks are unchanged.

## Released named-argument exceptions

Compatibility was checked against actual supported tags `v1.0.0`, canonical bare `1.1.0`, `v1.2.0`, `v1.3.0`
and `v1.4.1`, with `v1.4.0` additionally inspected. Earlier development snapshots are not release contracts.
The exact names below remain supported, even where another name would better convey responsibility. Descriptive
private fields are initialized from the retained arguments; there are no new public aliases/wrappers or methods.
Changing a retained public name needs a separate future-major/API decision, not another internal cleanup.
Rector's constructor-promotion rule remains enabled, with `rename_property: false`: it may promote matching names,
but must not rename a released argument merely to match a descriptive private field. This reconciles automation
with the named-argument contract without disabling the rule or narrowing the gate.

Paths below are relative to `src/`; each tag link is the released source authority. The unchanged public spelling
is the exception, not the newly descriptive private implementation.

| Released source / constructor | Retained parameters → descriptive private fields |
| --- | --- |
| [HmacAuthenticator](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Auth/Hmac/HmacAuthenticator.php), [HmacRequestService](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Auth/Hmac/HmacRequestService.php) | `public` → `publicCredential`; `private` remains hex shared-secret input. These are not asymmetric key names. |
| [Legacy HmacWebhookDispatcher](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Auth/Hmac/HmacWebhookDispatcher.php), now implemented in `Adapter/Mcp/Operation/Hmac` | `client` / `factory` / `signer` → `httpClient` / `messageFactory` / `requestSigner`; legacy inherited construction remains unchanged |
| [MetricsCommandFilter](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Command/MetricsCommandFilter.php), [MetricsQueryFilter](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Query/MetricsQueryFilter.php) | `metrics` → `metricsCollector` |
| [MailService](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Application/Mail/MailService.php), [SmsService](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Application/Sms/SmsService.php) | `transport` → `mailTransport` / `smsTransport`; MailService `factory` → `mailFactory` |
| [ValidationService](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Application/Validation/ValidationService.php) | `coordinator` → `validationCoordinator` |
| [EventPublicationRunner](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Application/EventSourcing/EventPublicationRunner.php), [ProjectionRunner](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Application/EventSourcing/ProjectionRunner.php) | `cursorStore` → `publicationCursorStore`; `failureRecorder` → `publicationFailureRecorder`; `checkpointStore` → `projectionCheckpointStore` |
| [LoggingPublicationFailureRecorder](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/EventSourcing/Logging/LoggingPublicationFailureRecorder.php) | `recorder` → `publicationFailureRecorder` |
| [LoggingFileTransport](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/FileTransfer/Logging/LoggingFileTransport.php), [LoggingMailTransport](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Mail/Logging/LoggingMailTransport.php), [LoggingSmsTransport](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Sms/Logging/LoggingSmsTransport.php) | `transport` → capability-specific transport field |
| [HttpEndpointHealthCheck](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Observability/Health/HttpEndpointHealthCheck.php) | `client` → `httpClient`; `factory` → `messageFactory` |
| [GuzzleClient](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/HttpClient/Guzzle/GuzzleClient.php), [TwilioSmsTransport](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Sms/Twilio/TwilioSmsTransport.php) | `client` → `httpClient` / `twilioClient` |
| [LaravelCommandBus](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Laravel/LaravelCommandBus.php), [LaravelEventDispatcher](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Laravel/LaravelEventDispatcher.php) | `dispatcher` → `jobDispatcher` |
| [Messenger adapters](https://github.com/johnnickell/fight-common/tree/v1.4.1/src/Adapter/Messaging/Symfony) and [Command/Async](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Command/Async/MessengerCommandBus.php), [Event/Async](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Event/Async/MessengerEventDispatcher.php) | `sender` → `messageSender`, canonical and legacy implementations |
| [OAuthResponseFactory](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Http/OAuth/OAuthResponseFactory.php) | `responses` / `streams` → `responseFactory` / `streamFactory` |
| [OAuthMetadataHandler](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Http/OAuth/OAuthMetadataHandler.php), [OAuthMiddleware](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Http/OAuth/OAuthMiddleware.php) | `responses` → `oauthResponseFactory`; middleware `server` → `resourceServer` |
| [OAuthResourceServer](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Application/Auth/OAuth/OAuthResourceServer.php) | `validator` → `tokenValidator`; `handoff` → `claimsHandoff` |
| [SafeJSendErrorMiddleware](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Middleware/Psr15/SafeJSendErrorMiddleware.php), [SafeErrorController](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Http/Symfony/Controller/SafeErrorController.php), [SafeLaravelExceptionHandler](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Http/Laravel/SafeLaravelExceptionHandler.php) | `responses` → `responseFactory` where present; `policy` → `httpErrorPolicy`; `projector` → `httpErrorProjector` |
| [SafeSymfonyExceptionSubscriber](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Http/Symfony/EventSubscriber/SafeSymfonyExceptionSubscriber.php) | `errors` → `errorController` |
| [YiiTemplateEngine](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Templating/Yii/YiiTemplateEngine.php), [LaravelBladeTemplateEngine](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Templating/Laravel/LaravelBladeTemplateEngine.php), [TwigEngine](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Templating/TwigEngine.php) | `view` → `viewRenderer` / `viewFactory`; `environment` → `twigEnvironment` |
| [StatsDMetricsCollector](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Observability/Metrics/StatsDMetricsCollector.php) | `sender` → `metricSender` |
| [Legacy SymfonyMessageSerializer](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Serializer/SymfonyMessageSerializer.php), [canonical wrapper](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Symfony/Serializer/SymfonyMessageSerializer.php) | `serializer` → `messageSerializer` / `transportSerializer` |
| [MCP response factory](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Http/Mcp/McpResponseFactory.php), [request handler](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Http/Mcp/McpRequestHandler.php) | `responses` / `streams` → `responseFactory` / `streamFactory`; `guard` → `invocationGuard`, including retained legacy construction |
| [McpToolInvoker](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Application/Mcp/Tool/McpToolInvoker.php) | `validation` / `failures` / `metadata` → `validationService` / `toolFailureMap` / `toolMetadataFilter`; the later never-tagged `tasks` argument instead becomes `taskCapability` directly |
| [McpToolInteraction](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Application/Mcp/Tool/Interaction/McpToolInteraction.php) | `protector` / `confirmations` → `stateProtector` / `confirmationStore` |
| [McpSkillDiscovery](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Application/Mcp/Skill/McpSkillDiscovery.php), [McpSkillResources](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Application/Mcp/Skill/McpSkillResources.php) | `provider` / `resources` → `skillResources` / `resourceDiscovery`; `streams` → `streamFactory`; real Resource collections remain `resources` |

Other exact released signature exceptions remain unchanged:

- `Domain/Messaging/{Command/CommandMessage,Event/EventMessage,Query/QueryMessage}::__construct(..., Meta $data)`
  and `Message::withMeta(Meta $data)` retain `data` (metadata), as in
  [v1.4.1 Message](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Domain/Messaging/Message.php).
- `ServiceAwareCommandRouter::registerHandler(..., $serviceName)` and
  `ServiceAwareQueryRouter::registerHandler(..., $serviceName)` retain the service-ID parameter, unlike their
  renamed locals/maps; see the released [router](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Query/Routing/ServiceAwareQueryRouter.php).
- [ContainerCapabilityRegistrar](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/ServiceContainer/Fight/ContainerCapabilityRegistrar.php)
  retains public `services`, `factories`, `commandHandlers`, `queryHandlers`, `eventSubscribers`, `filters`,
  `collaborators`, `helpers` and `transportFactory` arguments. Private helpers and resolved locals are descriptive.
- [SafeLaravelExceptionHandler](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Http/Laravel/SafeLaravelExceptionHandler.php)
  retains framework/public `report($e)`, `shouldReport($e)` and `render($request, $e)` names.
- [ArrayQueueIterator](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Domain/Collection/Iterator/ArrayQueueIterator.php)
  retains public constructor `cap`. Other released value/collection parameters, including UUID `msb` and tree
  range `lo`/`hi`, remain their actual supported mathematical/API idioms.

## Saved state, extension and dynamic-name exceptions

| Exact source | Retained name and reason |
| --- | --- |
| [QueuedCommandMessage](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Laravel/QueuedCommandMessage.php), [QueuedEventMessage](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Laravel/QueuedEventMessage.php) | Private `message` is Laravel-serialized job state, not freely renameable implementation. Old jobs need that exact mangled field. A rename needs a separate saved-state/queue migration decision. |
| [SimpleEventDispatcher](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Messaging/Event/Sync/SimpleEventDispatcher.php) | Protected `handlers`, `sorted` and `sortHandlers($eventType)` are released subclass contracts; `sorted` is a callable cache, not a compliance claim. A rename needs future-major extension-contract authorization. |
| [ArrayQueue](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Domain/Collection/ArrayQueue.php), [ArrayQueueIterator](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Domain/Collection/Iterator/ArrayQueueIterator.php) | Private `cap` slots retain exact default serialized state. This is a conservative concrete saved-object hazard, not evidence that every private service graph is supported saved state. A field rename needs an explicit compatibility decision. |
| [PhpEngine::evaluate](https://github.com/johnnickell/fight-common/blob/v1.4.1/src/Adapter/Templating/PhpEngine.php) | `evalFile`, `evalData`, `bufferLevel`, `content` and surrounding locals interact with `extract(..., EXTR_SKIP)` and required consumer templates. Existing evaluation scope/collision behavior is preserved. |
| [Released public-property migration map](public-property-getters.md) and [MCP foundation map](mcp-foundation-namespaces.md) | Public/protected properties, runtime alias/reflection identity and private saved protocol/value state remain unchanged. Getter adoption is not property-removal permission. |

ORM property/column names, Validation/McpToolInfo attributes, Symfony named DI argument strings, PHP alias scanner
`alias` variables, native/framework function-double contracts and externally meaningful array keys are retained.
Test-fixture property names are owned APIs: their actual callers, dynamic flag assignment and explicit closure
captures are reconciled instead of being called release exceptions. Where `compact()` previously depended on a
renamed local, explicit original output keys preserve the transcript. No runtime compatibility magic is introduced.
