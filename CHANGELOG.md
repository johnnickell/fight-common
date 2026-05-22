# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-05-22

### Added

**Domain layer**
- Value objects: `StringObject`, `MbStringObject`, `JsonObject`, `EmailAddress`, `Uri`, `Url`, `Uuid`, `UniqueId`, `MessageId`
- Typed collection hierarchy: `ArrayList`, `HashSet`, `HashTable`, `SortedSet`, `SortedTable`, `ArrayStack`, `LinkedStack`, `ArrayQueue`, `LinkedQueue`, `LinkedDeque`
- Ordered structures backed by `RedBlackSearchTree` with floor, ceiling, rank, and range operations
- Specification pattern via `CompositeSpecification` with `and()`, `or()`, `not()` combinators
- CQRS domain message primitives: `CommandMessage`, `QueryMessage`, `EventMessage`, `Meta`
- Domain serialization: `JsonSerializer`, `PhpSerializer`, and `Serializable` interface
- Domain exception hierarchy: `DomainException`, `TypeException`, `ValueException`, `AssertionException`, and more
- Type system: `Comparable`, `Comparator`, `Equatable`, `Arrayable`, `Type`

**Application layer**
- `CommandBus` and `QueryBus` with pipeline middleware support (`CommandFilter`, `QueryFilter`)
- `EventDispatcher` with synchronous and asynchronous variants
- Declarative validation via `#[Validation]` attribute with 60+ built-in rules
- `ValidationService`, `ValidationCoordinator`, and `ValidationResult`
- PSR-11 compatible `Container` with singleton and factory registration
- Ports (interfaces) for: `Auth`, `Cache`, `FileStorage`, `Filesystem`, `HttpClient`, `Mail`, `Routing`, `Socket`, `Templating`, `Repository`

**Adapter layer**
- 11 custom Doctrine DBAL types: `UuidDataType`, `UriDataType`, `UrlDataType`, `EmailAddressDataType`, `StringObjectDataType`, `StringTextDataType`, `MbStringObjectDataType`, `MbStringTextDataType`, `JsonObjectDataType`, `TypeDataType`, `MessageDataType`
- `DoctrineUnitOfWork` adapter
- 6 Symfony DI compiler passes: `CommandHandlerCompilerPass`, `CommandFilterCompilerPass`, `QueryHandlerCompilerPass`, `QueryFilterCompilerPass`, `EventSubscriberCompilerPass`, `TemplateHelperCompilerPass`
- `SymfonyValidationSubscriber` — auto-wires `#[Validation]` for Symfony controller actions
- Guzzle HTTP adapter: `GuzzleHttpClient`, `GuzzleMessageFactory`, `GuzzleStreamFactory`, `GuzzleUriFactory`
- `LoggingHttpClient` — PSR-3 logging decorator for any `HttpClient`
- JWT adapter via `lcobucci/jwt`: `JwtEncoder`, `JwtDecoder`
- Password adapter: `PhpPasswordHasher`, `PhpPasswordValidator`
- HMAC request signing: `HmacAuthenticator`, `HmacMethods`, `HmacKeyGenerator`
- `FlysystemStorage` — file storage adapter via `league/flysystem`
- `SymfonyFilesystem` — local filesystem adapter via `symfony/filesystem`
- `SymfonyMailTransport` and `SymfonyAttachment` via `symfony/mailer`
- `LoggingMailTransport` and `NullMailTransport`
- `PhpEngine`, `TwigEngine`, `DelegatingEngine` — template engine adapters
- `SymfonyUrlGenerator` — URL generation via `symfony/routing`
- `MercureHubPublisher` — real-time push via `symfony/mercure`
- `JSendResponse` and `JsonRequestMiddleware` via `symfony/http-foundation` / `symfony/http-kernel`
- `PsrCache` — cache-through adapter for any PSR-6 pool
- `SimpleEventDispatcher` and `ServiceAwareEventDispatcher`

**Quality**
- 100% statement coverage enforced via PHPUnit `requireCoverageMetadata`
- PHP 8.5+ required
- PSR-3, PSR-6, PSR-7, PSR-11, PSR-17, PSR-18 interface compliance

[Unreleased]: https://github.com/johnnickell/fight-common/compare/1.0.0...HEAD
[1.0.0]: https://github.com/johnnickell/fight-common/releases/tag/1.0.0
