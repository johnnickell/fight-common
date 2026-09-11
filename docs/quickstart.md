# Framework-Neutral Quick Start

Build one order-processing path from a command to a post-commit event and a follow-up command.
The example keeps business language in your code and uses Fight Common only for the messaging,
transaction, and adapter seams.

## Pick your framework

The portable example below works without a framework. If you prefer to begin from a complete
application skeleton, choose the repository that matches your runtime.

!!! note "Starter release status"
    The five starter repositories are the intended shortest path once their <code>1.0.0</code>
    releases are published. They are still being prepared for that release, so treat these clone
    commands as a preview of the coming workflow—not a current stable-install promise.

<div class="atlas-framework-picker">
  <a href="https://github.com/johnnickell/project-symfony"><strong>Symfony</strong><code>git clone https://github.com/johnnickell/project-symfony.git my-app</code></a>
  <a href="https://github.com/johnnickell/project-laravel"><strong>Laravel</strong><code>git clone https://github.com/johnnickell/project-laravel.git my-app</code></a>
  <a href="https://github.com/johnnickell/project-yii"><strong>Yii</strong><code>git clone https://github.com/johnnickell/project-yii.git my-app</code></a>
  <a href="https://github.com/johnnickell/project-codeigniter"><strong>CodeIgniter</strong><code>git clone https://github.com/johnnickell/project-codeigniter.git my-app</code></a>
  <a href="https://github.com/johnnickell/project-slim"><strong>Slim</strong><code>git clone https://github.com/johnnickell/project-slim.git my-app</code></a>
</div>

For today, start with PHP 8.5+ and Composer:

~~~bash
composer require johnnickell/fight-common
~~~

## The supporting domain type

Messages should carry your own validated domain types. <code>OrderId</code> rejects malformed
identifiers at construction, so every command, event, and handler receives an identifier it can trust.

<span class="atlas-code-filename">src/Domain/Order/OrderId.php</span>

~~~php { #quick-start-order-id }
<?php

declare(strict_types=1);

namespace App\Domain\Order;

use Fight\Common\Domain\Exception\DomainException;

--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:order-id"
~~~

The complete journey also uses application-owned <code>CustomerId</code>,
<code>PaymentMethod</code>, <code>ShoppingCart</code>, <code>Order</code>, repository ports, and
payment and fulfillment ports. Those types describe the order domain; they are not Fight Common classes.

## The command

<code>ProcessOrder</code> is the request to perform work. Implementing Fight's <code>Command</code>
contract makes it serializable for synchronous or asynchronous buses without putting routing logic
inside the message.

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

<code>OrderProcessed</code> records the business fact that the order was saved successfully. It
carries the order identity—not fulfillment instructions—so downstream application code can decide
what happens next.

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

<code>ProcessOrderHandler</code> coordinates the use case. It loads the cart, asks the payment port
for a stable reference, saves the order transactionally, and only then triggers
<code>OrderProcessed</code>. Dispatching the event after <code>commitTransactional()</code> returns
keeps subscribers from observing an order that was rolled back.

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

Fulfillment is separate work with its own retry boundary. <code>FulfillOrder</code> carries only the
order ID, so it can be dispatched immediately or transported to a worker later.

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

The subscriber translates the completed business fact into the next application request. That
translation belongs in Application code: the event stays factual and the Domain remains unaware of
command routing.

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

<code>FulfillOrderHandler</code> reloads authoritative order state and verifies the stored payment
reference before requesting fulfillment. A pending or failed payment stops the workflow instead of
turning an uncertain payment into an irreversible external action.

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

Composition is the Adapter edge. Register the two handlers, connect the event subscriber, and
replace the demonstration repositories and external-service fakes with adapters selected by your
application.

<span class="atlas-code-filename">config/order-processing.php</span>

~~~php-inline { #quick-start-composition }
--8<-- "tests/Documentation/QuickStart/OrderProcessingExample.php:order-processing-composition"
~~~

## Dispatch the command

The entry point creates application-owned values and sends the command through Fight's public
<code>CommandBus</code>. The caller does not select a handler directly.

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
