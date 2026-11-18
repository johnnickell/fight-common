# Quick Start { #framework-neutral-quick-start }

Use Fight Common to process an order and request fulfillment. A command asks the app to do something.
An event tells other parts of the app what happened. This example works without a framework.

## Install Fight Common

You need PHP 8.5 or later and [Composer](https://getcomposer.org/), PHP’s package manager. In your project, run:

~~~bash
composer require johnnickell/fight-common
~~~

## Pick your framework

You can skip this section and run the example without a framework. If you want to see how a full app is set up,
look at the starter for your framework.

!!! note "Starter release status"
    The five starters are meant to be the quickest way to begin after their `1.0.0` releases are published.
    They are still being prepared for that release. The clone commands below are previews, not stable installs.

<div class="atlas-framework-picker">
  <a href="https://github.com/johnnickell/project-symfony"><strong>Symfony</strong><code>git clone https://github.com/johnnickell/project-symfony.git my-app</code></a>
  <a href="https://github.com/johnnickell/project-laravel"><strong>Laravel</strong><code>git clone https://github.com/johnnickell/project-laravel.git my-app</code></a>
  <a href="https://github.com/johnnickell/project-yii"><strong>Yii</strong><code>git clone https://github.com/johnnickell/project-yii.git my-app</code></a>
  <a href="https://github.com/johnnickell/project-codeigniter"><strong>CodeIgniter</strong><code>git clone https://github.com/johnnickell/project-codeigniter.git my-app</code></a>
  <a href="https://github.com/johnnickell/project-slim"><strong>Slim</strong><code>git clone https://github.com/johnnickell/project-slim.git my-app</code></a>
</div>

## The supporting domain type

Use your own types for data your app cares about. `OrderId` rejects IDs in the wrong format when you create it,
so commands and events can carry an ID that has already been checked.

<span class="atlas-code-filename">src/Domain/Order/OrderId.php</span>

~~~php { #quick-start-order-id }
<?php

declare(strict_types=1);

namespace App\Domain\Order;

use Fight\Common\Domain\Exception\DomainException;

--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:order-id"
~~~

The full example also uses `CustomerId`, `PaymentMethod`, `ShoppingCart`, and `Order`. Interfaces for storage,
payment, and fulfillment are called ports. Your app owns these types and interfaces, not Fight Common.

## The command

`ProcessOrder` asks the app to process an order. Fight Common’s `Command` interface lets the app send the request
for work now or pass it to a worker later. The message itself does not decide which handler runs.

<span class="atlas-code-filename">src/Domain/Order/ProcessOrder.php</span>

~~~php { #quick-start-process-order-command }
<?php

declare(strict_types=1);

namespace App\Domain\Order;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\Command;

--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:process-order-command"
~~~

## The event

`OrderProcessed` says the order was saved. It carries the order ID, not instructions to fulfill it.
Other parts of the app decide what happens next.

<span class="atlas-code-filename">src/Domain/Order/OrderProcessed.php</span>

~~~php { #quick-start-order-processed-event }
<?php

declare(strict_types=1);

namespace App\Domain\Order;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Event\Event;

--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:order-processed-event"
~~~

## The command handler

`ProcessOrderHandler` loads the cart, gets a stable payment reference, and saves the order in a database transaction.
It sends `OrderProcessed` only after `commitTransactional()` returns successfully. That way, listeners do not see an
order whose save was rolled back.

<span class="atlas-code-filename">src/Application/Order/ProcessOrderHandler.php</span>

~~~php { #quick-start-process-order-handler }
<?php

declare(strict_types=1);

namespace App\Application\Order;

use App\Domain\Order\Order;
use App\Domain\Order\OrderProcessed;
use App\Domain\Order\OrderRepository;
use App\Domain\Order\PaymentProcessor;
use App\Domain\Order\ProcessOrder;
use App\Domain\Order\ShoppingCartRepository;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:process-order-handler"
~~~

## The follow-up command

The app can retry fulfillment on its own. `FulfillOrder` carries only the order ID. The app can send the command
right away or hand it to a worker later.

<span class="atlas-code-filename">src/Domain/Order/FulfillOrder.php</span>

~~~php { #quick-start-fulfill-order-command }
<?php

declare(strict_types=1);

namespace App\Domain\Order;

use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\Command;

--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:fulfill-order-command"
~~~

## The event subscriber

The subscriber listens for `OrderProcessed` and sends `FulfillOrder`. The app makes this choice without putting
command routing in the event or in its business rules.

<span class="atlas-code-filename">src/Application/Order/OrderProcessedSubscriber.php</span>

~~~php { #quick-start-order-processed-subscriber }
<?php

declare(strict_types=1);

namespace App\Application\Order;

use App\Domain\Order\FulfillOrder;
use App\Domain\Order\OrderProcessed;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\EventMessage;

--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:order-processed-subscriber"
~~~

## The fulfillment handler

`FulfillOrderHandler` loads the stored order and checks its payment reference. It requests fulfillment only after
it confirms that payment succeeded. A pending or failed payment stops the request.

<span class="atlas-code-filename">src/Application/Order/FulfillOrderHandler.php</span>

~~~php { #quick-start-fulfill-order-handler }
<?php

declare(strict_types=1);

namespace App\Application\Order;

use App\Domain\Order\FulfillOrder;
use App\Domain\Order\FulfillmentRequester;
use App\Domain\Order\OrderRepository;
use App\Domain\Order\PaymentNotSuccessful;
use App\Domain\Order\PaymentProcessor;
use App\Domain\Order\PaymentStatus;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:fulfill-order-handler"
~~~

## Wire the application

Register both handlers and connect the event subscriber. In your own app, replace the example’s storage and fake
services with adapters: code that connects to the database and services you use.

<span class="atlas-code-filename">config/order-processing.php</span>

~~~php-inline { #quick-start-composition }
--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:order-processing-composition"
~~~

## Dispatch the command

Create the values your app needs and send the command through Fight Common’s `CommandBus`. The bus finds the
right handler, so the caller does not have to pick one.

<span class="atlas-code-filename">public/process-order.php</span>

~~~php-inline { #quick-start-dispatch }
--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:dispatch-process-order"
~~~

For the deterministic documentation fixture, the result is:

~~~text
Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested.
~~~

#### Where to go next

- Read [Architecture](../architecture/index.md) for the Adapter → Application → Domain dependency rule.
- Read [Messaging](../components/messaging/index.md) for buses, routers, handlers, and delivery semantics.
- Read [Repositories](../components/repositories/index.md) for transactional persistence boundaries.
- Review [Framework Support](../frameworks/framework-support/index.md) before selecting framework adapters.
