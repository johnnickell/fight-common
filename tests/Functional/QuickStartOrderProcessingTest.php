<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Test\Common\Documentation\QuickStart\CustomerId;
use Fight\Test\Common\Documentation\QuickStart\DemoTransactionalUnitOfWork;
use Fight\Test\Common\Documentation\QuickStart\FakeFulfillmentRequester;
use Fight\Test\Common\Documentation\QuickStart\FakePaymentProcessor;
use Fight\Test\Common\Documentation\QuickStart\FulfillOrder;
use Fight\Test\Common\Documentation\QuickStart\FulfillOrderHandler;
use Fight\Test\Common\Documentation\QuickStart\InMemoryOrderRepository;
use Fight\Test\Common\Documentation\QuickStart\InMemoryShoppingCartRepository;
use Fight\Test\Common\Documentation\QuickStart\Item;
use Fight\Test\Common\Documentation\QuickStart\OrderId;
use Fight\Test\Common\Documentation\QuickStart\OrderProcessingExample;
use Fight\Test\Common\Documentation\QuickStart\OrderProcessedSubscriber;
use Fight\Test\Common\Documentation\QuickStart\PaymentMethod;
use Fight\Test\Common\Documentation\QuickStart\PaymentNotSuccessful;
use Fight\Test\Common\Documentation\QuickStart\PaymentReference;
use Fight\Test\Common\Documentation\QuickStart\PaymentStatus;
use Fight\Test\Common\Documentation\QuickStart\ProcessOrder;
use Fight\Test\Common\Documentation\QuickStart\ProcessOrderHandler;
use Fight\Test\Common\Documentation\QuickStart\ShoppingCart;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

require_once dirname(__DIR__).'/Documentation/QuickStart/OrderProcessingExample.php';

#[CoversNothing]
final class QuickStartOrderProcessingTest extends UnitTestCase
{
    public function test_that_the_documented_cart_to_stored_order_journey_executes(): void
    {
        $result = OrderProcessingExample::run();

        self::assertSame(
            'Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested.',
            $result->message(),
        );
        self::assertSame($result->message(), OrderProcessingExample::process());
        self::assertSame(3200, $result->payments()->processedPayments()[0]['amount']);
        self::assertSame(
            'provider-token-for-customer-42',
            $result->payments()->processedPayments()[0]['payment_method']->providerToken(),
        );
        self::assertSame('PAYMENT-1001', $result->order()->paymentReference()->toString());
        self::assertSame([$result->order()], $result->fulfillment()->requestedOrders());
        self::assertCount(1, $result->fulfillment()->requestedOrders());
        self::assertCount(1, $result->payments()->statusChecks());
    }

    public function test_that_fulfillment_can_be_retried_after_payment_succeeds(): void
    {
        $journey = $this->journey(PaymentStatus::PENDING);

        $this->assertPaymentFailureFor($journey);
        self::assertSame([], $journey['fulfillment']->requestedOrders());
        self::assertCount(1, $journey['payments']->statusChecks());
        self::assertSame('ORDER-1001', $journey['orders']->get($journey['order_id'])->id()->toString());
        $journey['payments']->configureStatus(PaymentStatus::SUCCEEDED);
        $journey['commands']->execute(new FulfillOrder($journey['order_id']));

        self::assertSame(
            [$journey['orders']->get($journey['order_id'])],
            $journey['fulfillment']->requestedOrders(),
        );
        self::assertCount(2, $journey['payments']->statusChecks());
    }

    /**
     * @param array{commands: RoutingCommandBus, customer_id: CustomerId, fulfillment: FakeFulfillmentRequester, order_id: OrderId, orders: InMemoryOrderRepository, payments: FakePaymentProcessor} $journey
     */
    private function assertPaymentFailureFor(array $journey): void
    {
        try {
            $journey['commands']->execute(
                new ProcessOrder($journey['customer_id'], $journey['order_id'], PaymentMethod::tokenized('provider-token')),
            );
            self::fail('The event dispatcher must report the failed fulfillment subscriber.');
        } catch (EventDispatchFailed $failure) {
            self::assertCount(1, $failure->failures());
            self::assertInstanceOf(PaymentNotSuccessful::class, $failure->failures()[0]->throwable());
        }
    }

    /**
     * @return array{commands: RoutingCommandBus, customer_id: CustomerId, fulfillment: FakeFulfillmentRequester, order_id: OrderId, orders: InMemoryOrderRepository, payments: FakePaymentProcessor}
     */
    private function journey(PaymentStatus $paymentStatus): array
    {
        $customerId = CustomerId::fromString('CUSTOMER-42');
        $orderId = OrderId::fromString('ORDER-1001');
        $orderRepository = new InMemoryOrderRepository();
        $paymentProcessor = new FakePaymentProcessor(PaymentReference::fromString('PAYMENT-1001'), $paymentStatus);
        $fulfillmentRequester = new FakeFulfillmentRequester();
        $router = new InMemoryCommandRouter();
        $commandBus = new RoutingCommandBus($router);
        $eventDispatcher = new SimpleEventDispatcher();
        $eventDispatcher->register(new OrderProcessedSubscriber($commandBus));
        $router->registerHandlers([
            ProcessOrder::class => new ProcessOrderHandler(
                new InMemoryShoppingCartRepository(
                    ShoppingCart::forCustomer($customerId, Item::create('COFFEE-BEANS', 1800, 1)),
                ),
                $orderRepository,
                $paymentProcessor,
                new DemoTransactionalUnitOfWork([$orderRepository]),
                $eventDispatcher,
            ),
            FulfillOrder::class => new FulfillOrderHandler($orderRepository, $paymentProcessor, $fulfillmentRequester),
        ]);

        return [
            'commands' => $commandBus,
            'customer_id' => $customerId,
            'fulfillment' => $fulfillmentRequester,
            'order_id' => $orderId,
            'orders' => $orderRepository,
            'payments' => $paymentProcessor,
        ];
    }
}
