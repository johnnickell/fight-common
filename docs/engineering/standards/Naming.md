# Naming

Use readable, responsibility-specific names from CONTEXT.md. A name identifies intent, returned data, a past
fact, or a capability; brevity must not obscure what it represents, and it need not explain the entire implementation.

| Concept | Preferred examples | Rule |
|---|---|---|
| Command | `RegisterUser` | Imperative business intent |
| Query | `GetUserById`, `GetUserByEmail`, `ListUsers` | Requested data, with clear selection |
| Event | `UserRegistered` | Past-tense business fact |
| Command/query handler | `RegisterUserHandler`, `GetUserByIdHandler` | Corresponding message plus Handler |
| Capability contract | `MailTransport`, `UserRepository` | No Interface suffix |
| Concrete service | `SymfonyMailTransport` | Distinguishing implementation adjective |
| Specification | `UniqueEmailSpecification` | Business condition, not mechanism |
| HTTP Action | `RegisterUserAction` | One interaction |
| Safe output | `UserView` | Explicit public/use-case data |
| Repository lookup | `getById`, `getByEmail` | Nullable return is expressed in the contract; no forced find/get distinction |
| Entity behavior | `canLogIn` | State-based domain concept owned by the entity |
| Event subscriber | `SendWelcomeEmailSubscriber` | Name the reaction/policy it owns, not a generic event forwarder |
| Exception factory | `DuplicateEmailException::fromEmail` | Contextual construction and message formatting inside the exception |

## Variables, properties and parameters

Throughout project-owned code, including existing code, tests and maintained examples, variable, property,
parameter and local names must identify their actual responsibility. Name a collaborator by its capability,
not a plural collection-like shorthand or vague service label:

| Responsibility | Use | Avoid for that collaborator |
|---|---|---|
| Task persistence | `$taskRepository` | `$tasks`, `$repository`, `$store` |
| Query dispatch | `$queryBus` | `$queries`, `$bus` |
| Command dispatch | `$commandBus` | `$commands`, `$bus` |
| Event dispatch | `$eventDispatcher` | `$events`, `$dispatcher` |

A collection of Task instances may be `$tasks`; a repository is not that collection. Likewise, a query result,
transaction boundary, selected identity, encoder or admission decision needs its own descriptive name. Keep
property, constructor dependency and local usage consistent with the responsibility; do not apply mechanical
search-and-replace where the same short word denotes different things.

Released PHP parameter names are part of named-argument compatibility. Preserve those public names unless an
explicit compatible migration or future-major decision authorizes changing them. Descriptive private properties
and local aliases can improve internal clarity without renaming a released parameter. Never-released signatures
may be corrected directly; do not add compatibility aliases for development snapshots. Record a necessary
released-name exception rather than silently breaking callers or weakening the general rule.

[Architecture](Architecture.md#aggregate-ownership-and-namespaces) owns type placement and the single ownership
rule; naming changes do not authorize moving types, changing wire/database keys or renaming message/Tool IDs.
[TASK-00192](../../../planning/tasks/00192-TASK.md) owns the codebase-wide correction; this standard does not
claim it has already happened.

PHP properties and frontend data properties are camelCase. Database columns and HTTP JSON fields are snake_case. Map at boundaries, including nested data. Use available `string()` conversion helpers where suitable; do not change an established external schema without a compatibility decision.

Import referenced classes, functions, and constants rather than using fully qualified names throughout method bodies. Keep use statements alphabetically ordered according to the project Fight coding standard. Available helpers such as `string()` and `array_list()` are preferred where they express the intended operation clearly; avoid gratuitous wrapping.

Review naming as one cohesive criterion backed by precise examples. A novel protocol adapter's class/path names remain a design decision until its responsibilities are known.
