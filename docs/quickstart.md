# Framework-Neutral Quick Start

This is one complete, portable order-processing journey. It keeps business rules in the Domain,
coordinates them through Application ports, and selects in-memory adapters only at composition time.
No framework or database is required to understand the flow.

## Prerequisites

You need PHP, Composer, and an application where you can define a small order model and compose its
services. Install Fight Common with `composer require johnnickell/fight-common`.

The example deliberately uses a token supplied by a payment provider, never raw card data. Its
application-owned types live beside your application code; Fight Common supplies the messaging,
transaction, and synchronous adapter contracts used to compose them.

## Process an order

The fixture creates customer `CUSTOMER-42`, order `ORDER-1001`, a two-item cart, the token
`provider-token-for-customer-42`, and payment reference `PAYMENT-1001`. The fake payment processor
is configured to return `succeeded` immediately.

First compose the Domain model, Application handlers and ports, and in-memory adapters. `OrderProcessed`
is published after the unit of work callback succeeds; the Application subscriber translates it into
`FulfillOrder`.

```php-inline { #quick-start-composition }
--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:order-processing-composition"
```

Then send the Domain command through the public command bus.

```php-inline { #quick-start-process-order }
--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:process-order"
```

Running the exact fixture returns:

`Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested.`

## Complete executable example

The two steps above are deliberately short. For a copyable, runnable journey, save the following
consumer-owned example as `order-processing.php` and run it in a project that has installed Fight
Common. It contains every model, port, handler, in-memory adapter, and entrypoint used above; no
framework setup or hidden application code is required.

Add the PHP opening tag before the copied source, then run `php order-processing.php`.

??? example "Complete `order-processing.php`"
    ```php-inline { #quick-start-complete-example }
    --8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:complete-order-processing-example"
    ```
## Ownership and flow

**Domain** owns `CustomerId`, `OrderId`, `Item`, `ShoppingCart`, `Order`, the tokenized
`PaymentMethod`, `PaymentReference`, `PaymentStatus`, repository ports, serializable `ProcessOrder`
and `FulfillOrder` commands, `OrderProcessed`, and `PaymentNotSuccessful`.

**Application** owns `PaymentProcessor`, `FulfillmentRequester`, `ProcessOrderHandler`,
`FulfillOrderHandler`, and the `OrderProcessedSubscriber` that translates the event into a follow-up
command. `ProcessOrderHandler` loads the cart, asks the payment port to create a stable reference,
and stores the order inside `TransactionalUnitOfWork`. `FulfillOrderHandler` reloads the order and
checks that stored reference before asking its fulfillment port to do any work.

**Adapter and composition** own the in-memory repositories, configurable fake payment processor,
fake fulfillment requester, demonstration transaction adapter, and Fight's
`InMemoryCommandRouter`, `RoutingCommandBus`, and `SimpleEventDispatcher`. Replace only these edges
when your application selects a database, payment provider, framework container, or transport.

## Payment guard and retries

Fulfillment is fail-closed. Only `succeeded` requests fulfillment. Both `pending` and `failed`
throw `PaymentNotSuccessful` and make no fulfillment request. With the synchronous dispatcher in this
journey, the subscriber failure is reported to the original `ProcessOrder` caller as
`EventDispatchFailed`; inspect its recorded failure to find the `PaymentNotSuccessful` cause. The
order remains stored with its payment reference, so a later `FulfillOrder` retry succeeds after the
payment status becomes `succeeded`.

!!! warning "Production transaction boundary"
    `DemoTransactionalUnitOfWork` restores only the supplied in-memory repository state when its
    callback fails. It cannot atomically commit an external payment processor with order persistence.
    A production adapter needs an explicit idempotency, reconciliation, and transaction policy for
    that boundary.

An asynchronous payment adapter must arrange redelivery or retry of `FulfillOrder` when the provider
reports completion. This Quick Start implements no queue, saga, or durable outbox.

## Continue

- [Architecture](../architecture/index.md) explains the inward Adapter → Application → Domain dependency direction.
- [Messaging](../components/messaging/index.md) details commands, events, buses, handlers, and dispatchers.
- [Repositories](../components/repositories/index.md) covers the `TransactionalUnitOfWork` boundary and production adapters.
- [Framework Support](../frameworks/framework-support/index.md) separates supported framework activation from this portable composition.
