# Framework support and activation

This is the normative consumer contract for framework integrations. It describes the supported
composition surface; adapter conformance and a booted starter receipt remain the evidence needed
for a framework support claim. Select only the capabilities your application uses. Fight Common
does not install frameworks or optional providers for a consumer, and it does not publish an
aggregate provider that activates every optional adapter.

## Support window

Fight Common currently supports Symfony components `^8.1`, Laravel `^13.0`, CodeIgniter `^4.7`,
Slim `^4.15`, and the current Yii 3 package set. The Yii set is `yiisoft/di ^1.4`,
`yiisoft/config ^1.6`, `yiisoft/yii-http ^1.1`, `yiisoft/event-dispatcher ^1.1`,
`yiisoft/router ^4.0`, `yiisoft/router-fastroute`, `yiisoft/view`, `yiisoft/view-twig`,
`yiisoft/validator`, `yiisoft/mailer ^6.1`, `yiisoft/cache ^3.2`, `yiisoft/db` with its chosen
driver, `yiisoft/session`, and `yiisoft/log`.

The policy starts current-only. When a framework ships its next stable major, its owning starter
must prove the new and previous lines before the range widens; when the oldest line stops being
maintained, Fight Common tightens by dropping it. The window never exceeds two maintained majors.
Yii 3 has no equivalent major-line widening rule, while CodeIgniter 4 maintains only its latest
line and `^4.7` already admits later 4.x minors. PHP 8.6 and a new Symfony, Laravel, Slim, or
CodeIgniter line are re-resolution triggers: re-resolve the affected starter's lowest and latest
dependency sets before publishing a changed claim.

`^8.2 || ^8.1` is the Symfony widen form after Symfony 8.2 is stable; Laravel similarly becomes
`^14.0 || ^13.0` after Laravel 14 is stable, and Slim becomes `^5.0 || ^4.15` after Slim 5 is
stable. Yii 2, Slim 3, CodeIgniter 4.6, Laravel 12 and earlier, and Symfony 7.4/6.4 are not
supported by this contract.

## Reading the matrix

- **ship** means Fight Common publishes a clear reusable adapter or bounded activation seam; it
  must have complete conformance and starter evidence before the corresponding 1.2 claim.
- **prototype** means a native API is being evaluated against the complete Fight contract. A
  failed prototype names its missing operation or value and retains the listed tested fallback;
  it is never silently treated as support.
- **wire** means an accepted PSR contract, neutral Fight service, or existing provider adapter is
  used unchanged. It is a deliberate composition, not a missing framework adapter.

Framework-free Domain values, collections, specifications, messages, repositories, and neutral
Application services are wired directly unless a framework lifecycle or infrastructure translation is required.

| Capability | Symfony | Laravel | Yii | CodeIgniter | Slim |
| --- | --- | --- | --- | --- | --- |
| Authentication and security | **wire** neutral HMAC, JWT, PHP passwords | **ship** password hash/validation; wire HMAC/JWT | **wire** neutral seams | **wire** neutral seams | **wire** neutral seams |
| Cache | **wire** canonical PSR-6/16 | **ship** native cache | **wire** standard cache/PSR lane | **ship** native `CacheInterface` | **wire** canonical PSR-6/16 |
| Service container | **ship** compiler passes | **ship** bounded providers | **ship** configuration groups/providers | **ship** service delegates | **ship** Fight registrars/PSR-11 |
| UnitOfWork and persistence | **ship** Doctrine UoW/data types | **ship** native transactional UoW | **ship** Yii DB UoW | **ship** native transactional UoW | **wire** Doctrine |
| Event store and repositories | **wire** DBAL/in-memory/logging | **wire** shared providers | **wire** shared providers | **wire** shared providers | **wire** DBAL/Doctrine |
| Async commands and events | **ship** Messenger + neutral handlers | **ship** complete Fight Queue envelopes | unavailable for stable 1.2; experimental neutral handlers only | **ship** official Queue envelopes | **wire** Messenger + neutral handlers |
| Synchronous messaging | **wire** buses, routers, pipelines, dispatcher | **wire** unchanged | **wire** unchanged | **wire** unchanged | **wire** unchanged |
| File storage | **wire** Flysystem | **prototype** native; Flysystem fallback | **wire** Flysystem | **wire** Flysystem | **wire** Flysystem |
| Local filesystem | **ship** Symfony Filesystem | **prototype** native; Symfony fallback | **prototype** native; Symfony fallback | **prototype** native; Symfony fallback | **wire** Symfony Filesystem |
| File transfer | **wire** FTP/SFTP/logging/null | **wire** unchanged | **wire** unchanged | **wire** unchanged | **wire** unchanged |
| Outbound HTTP | **wire** Guzzle + PSR-18 | **prototype** native; Guzzle/PSR-18 fallback | **wire** PSR/provider lane | **wire** Guzzle/PSR-18 | **wire** Guzzle/PSR-18 |
| Request, response, middleware | **ship** middleware/controller/JSend | **ship** JSend/error response | **wire** PSR-15/17 lane | **ship** JSend/error response | **wire** PSR-15/17 lane |
| Observability | **wire** PSR-3, health, audit, metrics | **wire** PSR-3; **prototype** Pulse metrics | **wire** logging/health/audit/metrics | **wire** PSR-3/health/audit/metrics | **wire** PSR-3/health/audit/metrics |
| Process | **ship** Symfony Process | **prototype** native; Symfony fallback | **wire** Symfony Process | **wire** Symfony Process | **wire** Symfony Process |
| Scheduling | **wire** portable Scheduler | **wire** portable Scheduler | **wire** portable Scheduler | **wire** portable Scheduler | **wire** portable Scheduler |
| URL generation and routing | **ship** Symfony generator | **ship** native generator | **ship** native generator | **ship** native generator | **ship** named-route generator |
| Mail | **ship** Symfony Mailer | **ship** native mail | **prototype** Yii Mail; Symfony fallback | **prototype** native; Symfony fallback | **wire** Symfony Mailer |
| SMS | **wire** Twilio/logging/null | **wire** unchanged | **wire** unchanged | **wire** unchanged | **wire** unchanged |
| Socket/private publication | **wire** Mercure/port | **ship** native broadcast | **wire** Mercure/providers | **wire** Mercure/providers | **wire** Mercure/providers |
| Templating | **ship** Twig/PHP engines | **ship** Blade | **prototype** Yii View; Twig fallback | **prototype** native View; Twig/PHP fallback | **wire** Twig |
| Validation and pure Application services | **wire** Fight services | **wire** unchanged | **wire** unchanged | **wire** unchanged | **wire** unchanged |

## Install and activate one capability

All framework and provider packages are Composer suggestions (and Fight Common development dependencies), never
Fight Common production requirements. A starter requires its selected runtime stack. Use the exact optional
package for the selected capability: `laravel/framework`, `codeigniter4/framework`, `codeigniter4/queue`,
`slim/slim`, `symfony/messenger`, `yiisoft/config`, `yiisoft/di`, and `yiisoft/router` are the framework seams
listed in `composer.json`; Symfony, Doctrine, Guzzle, Flysystem, Twig, Twilio, and Mercure remain independently
selectable provider packages.

Symfony applications add only the relevant compiler pass from
`Fight\Common\Adapter\ServiceContainer\Symfony` (command handler/filter, query handler/filter, event
subscriber, template helper, or event mapping provider), then configure the corresponding Symfony component.
Messenger uses the canonical Messenger buses and serializer; Doctrine, Mailer, Filesystem, Process, and Routing
remain their own selected integrations. There is no Fight Common Symfony bundle.

Laravel applications register only the relevant provider under
`Fight\Common\Adapter\ServiceContainer\Laravel`: messaging, persistence, security, cache, HTTP, routing,
templating, mail, broadcasting, file storage, filesystem, HTTP client, process, metrics, or logging. Do not
enable unrelated providers through application auto-discovery.

Yii applications select only the relevant `YiiCapabilityConfiguration` group and matching bounded provider
under `Fight\Common\Adapter\ServiceContainer\Yii` (persistence, routing, messaging, HTTP, mail, view, or
filesystem). Standard cache/logging and the selected provider lane remain direct composition. Yii Mail, View,
and Filesystem disclose their fallback because their native APIs have not proven the whole Fight contract.

CodeIgniter applications expose only the selected delegate from their own `Config\Services.php`:
`MessagingServices`, `PersistenceServices`, `CacheServices`, `RoutingServices`, `MailServices`,
`TemplateServices`, or `FilesystemServices`. The latter three intentionally return the proven Symfony/Twig
fallbacks where the native API lacks required metadata, template-helper, or filesystem operations.

Slim and standalone applications use Fight's concrete PSR-11 container and explicit capability registrars,
then choose shared providers. Slim adds only named-route URL translation; it does not receive branded copies of
shared HTTP, cache, mail, filesystem, or process adapters.

## Queue, authentication, and PSR boundaries

Stable queue adapters transport one complete Fight `CommandMessage` or `EventMessage`—identity, creation time,
payload, and metadata—and delegate it to a neutral synchronous handler. Delivery is at-least-once; post-commit
submission is used when native support exists but is not an atomic outbox. Broker selection, topology, retry and
failure policy, worker supervision, and durable outbox design are starter-owned. Event handlers must tolerate
repeated complete fan-out.

Stable Yii Queue is unavailable for 1.2, not skipped. `yiisoft/queue` plus a production broker must first have
compatible stable releases and prove serialization, acknowledgement, retry, failure, signal, and long-running
state behavior. A starter-owned experimental transport may use neutral handlers; an additive stable adapter can
be considered for 1.3 only after that evidence exists.

Fight Common adapts exact password, HMAC, JWT, and boolean authenticator seams only. Guards, challenges,
sessions, identities, principals, and authorization are downstream integration concerns owned by Fight AccessControl
and the application; they are not lossily placed behind the PSR-7 boolean `Authenticator`.

PSR-3 and PSR-7 are direct contracts, PSR-11 is implemented by Fight's container, and PSR-15/17 are shared HTTP
adapters. PSR-6 is the canonical cache-pool boundary and PSR-16 is its separate read-through adapter; neither is
a claim about unrelated PSR contracts. The PSR-18 view losslessly exposes Fight's synchronous `send()` operation,
but an arbitrary synchronous-only PSR-18 client does not satisfy Fight's async-capable HTTP client. Fight
messaging deliberately does not claim PSR-14 compatibility because its envelope and delivery semantics differ.

## Release boundary

Every **ship** item needs adapter conformance plus a booted starter receipt before it supports a 1.2 framework
claim. A prototype may fail only when its documented tested **wire** fallback remains; otherwise that framework
claim is blocked. Compatible new adapters, including Yii Queue after its gate, may be additive in 1.3. Legacy
name removal and incompatible inward contract changes are reserved for 2.0.
