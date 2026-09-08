<?php

declare(strict_types=1);

namespace Fight\Test\Common\Documentation;

use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Messaging\Event\EventDispatchFailed;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use App\QuickStart\CustomerId;
use App\QuickStart\DemoTransactionalUnitOfWork;
use App\QuickStart\FakeFulfillmentRequester;
use App\QuickStart\FakePaymentProcessor;
use App\QuickStart\FulfillOrder;
use App\QuickStart\FulfillOrderHandler;
use App\QuickStart\InMemoryOrderRepository;
use App\QuickStart\InMemoryShoppingCartRepository;
use App\QuickStart\Item;
use App\QuickStart\OrderId;
use App\QuickStart\OrderProcessingExample;
use App\QuickStart\OrderProcessed;
use App\QuickStart\OrderProcessedSubscriber;
use App\QuickStart\PaymentMethod;
use App\QuickStart\PaymentNotSuccessful;
use App\QuickStart\PaymentReference;
use App\QuickStart\PaymentStatus;
use App\QuickStart\ProcessOrder;
use App\QuickStart\ProcessOrderHandler;
use App\QuickStart\ShoppingCart;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use RuntimeException;

require_once __DIR__.'/QuickStart/OrderProcessingExample.php';

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
    }

    public function test_that_process_order_uses_the_cart_total_and_stores_the_payment_reference(): void
    {
        $customerId = CustomerId::fromString('CUSTOMER-42');
        $orderId = OrderId::fromString('ORDER-1001');
        $orders = new InMemoryOrderRepository();
        $payments = new FakePaymentProcessor(
            PaymentReference::fromString('PAYMENT-1001'),
            PaymentStatus::SUCCEEDED,
        );
        $handler = new ProcessOrderHandler(
            new InMemoryShoppingCartRepository(
                ShoppingCart::forCustomer($customerId, Item::create('COFFEE-BEANS', 1800, 2)),
            ),
            $orders,
            $payments,
            new DemoTransactionalUnitOfWork([$orders]),
            new SimpleEventDispatcher(),
        );

        $command = new ProcessOrder($customerId, $orderId, PaymentMethod::tokenized('provider-token'));
        $handler->handle(CommandMessage::create($command));

        self::assertSame(3600, $payments->processedPayments()[0]['amount']);
        self::assertSame('provider-token', $payments->processedPayments()[0]['payment_method']->providerToken());
        self::assertSame('PAYMENT-1001', $orders->get($orderId)->paymentReference()->toString());
    }

    public function test_that_demo_transaction_restores_repository_state_when_its_callback_fails(): void
    {
        $customerId = CustomerId::fromString('CUSTOMER-42');
        $orderId = OrderId::fromString('ORDER-1001');
        $orders = new InMemoryOrderRepository();
        $unitOfWork = new DemoTransactionalUnitOfWork([$orders]);

        try {
            $unitOfWork->commitTransactional(
                static function () use ($customerId, $orderId, $orders): never {
                    $orders->save(
                        \App\QuickStart\Order::fromCart(
                            $orderId,
                            ShoppingCart::forCustomer($customerId, Item::create('FILTERS', 700, 1)),
                            PaymentReference::fromString('PAYMENT-1001'),
                        ),
                    );

                    throw new RuntimeException('The documented persistence callback failed.');
                },
            );
            self::fail('The transaction callback failure must be rethrown.');
        } catch (RuntimeException $failure) {
            self::assertSame('The documented persistence callback failed.', $failure->getMessage());
        }

        $this->expectException(DomainException::class);
        $orders->get($orderId);
    }

    public function test_that_process_order_round_trips_its_serializable_payload(): void
    {
        $command = ProcessOrder::fromArray(
            [
                'customer_id' => 'CUSTOMER-42',
                'order_id' => 'ORDER-1001',
                'payment_method' => 'provider-token',
            ],
        );

        self::assertSame(
            [
                'customer_id' => 'CUSTOMER-42',
                'order_id' => 'ORDER-1001',
                'payment_method' => 'provider-token',
            ],
            $command->toArray(),
        );
    }

    public function test_that_fulfill_order_and_order_processed_round_trip_their_serializable_payloads(): void
    {
        $orderId = OrderId::fromString('ORDER-1001');

        self::assertSame(['order_id' => 'ORDER-1001'], FulfillOrder::fromArray(['order_id' => 'ORDER-1001'])->toArray());
        self::assertSame(['order_id' => 'ORDER-1001'], OrderProcessed::fromArray(['order_id' => 'ORDER-1001'])->toArray());
        self::assertSame('ORDER-1001', (new FulfillOrder($orderId))->orderId->toString());
        self::assertSame('ORDER-1001', (new OrderProcessed($orderId))->orderId->toString());
    }

    public function test_that_post_commit_event_publication_routes_fulfillment_exactly_once(): void
    {
        $journey = $this->journey(PaymentStatus::SUCCEEDED);

        $journey['commands']->execute(
            new ProcessOrder($journey['customer_id'], $journey['order_id'], PaymentMethod::tokenized('provider-token')),
        );

        self::assertSame(
            [$journey['orders']->get($journey['order_id'])],
            $journey['fulfillment']->requestedOrders(),
        );
        self::assertCount(1, $journey['payments']->statusChecks());
        self::assertSame('PAYMENT-1001', $journey['orders']->get($journey['order_id'])->paymentReference()->toString());
    }

    public function test_that_pending_payment_is_wrapped_and_does_not_request_fulfillment(): void
    {
        $journey = $this->journey(PaymentStatus::PENDING);

        $this->assertPaymentFailureFor($journey);

        self::assertSame([], $journey['fulfillment']->requestedOrders());
        self::assertCount(1, $journey['payments']->statusChecks());
        self::assertSame('ORDER-1001', $journey['orders']->get($journey['order_id'])->id()->toString());
    }

    public function test_that_failed_payment_is_wrapped_and_does_not_request_fulfillment(): void
    {
        $journey = $this->journey(PaymentStatus::FAILED);

        $this->assertPaymentFailureFor($journey);

        self::assertSame([], $journey['fulfillment']->requestedOrders());
        self::assertCount(1, $journey['payments']->statusChecks());
        self::assertSame('ORDER-1001', $journey['orders']->get($journey['order_id'])->id()->toString());
    }

    public function test_that_fulfillment_can_be_retried_after_payment_succeeds(): void
    {
        $journey = $this->journey(PaymentStatus::PENDING);

        $this->assertPaymentFailureFor($journey);
        $journey['payments']->configureStatus(PaymentStatus::SUCCEEDED);
        $journey['commands']->execute(new FulfillOrder($journey['order_id']));

        self::assertSame(
            [$journey['orders']->get($journey['order_id'])],
            $journey['fulfillment']->requestedOrders(),
        );
        self::assertCount(2, $journey['payments']->statusChecks());
    }

    public function test_that_transaction_failure_restores_the_order_and_suppresses_publication_and_fulfillment(): void
    {
        $journey = $this->journey(PaymentStatus::SUCCEEDED);
        $journey['orders']->failSavingAfterPersistingWith(new RuntimeException('The documented persistence callback failed.'));

        try {
            $journey['commands']->execute(
                new ProcessOrder($journey['customer_id'], $journey['order_id'], PaymentMethod::tokenized('provider-token')),
            );
            self::fail('The transaction failure must be rethrown before event publication.');
        } catch (RuntimeException $failure) {
            self::assertSame('The documented persistence callback failed.', $failure->getMessage());
        }

        self::assertSame('ORDER-1001', $journey['orders']->lastSavedOrder()?->id()->toString());
        self::assertCount(1, $journey['payments']->processedPayments());
        self::assertSame([], $journey['payments']->statusChecks());
        self::assertSame([], $journey['fulfillment']->requestedOrders());
        $this->expectException(DomainException::class);
        $journey['orders']->get($journey['order_id']);
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
        $orders = new InMemoryOrderRepository();
        $payments = new FakePaymentProcessor(PaymentReference::fromString('PAYMENT-1001'), $paymentStatus);
        $fulfillment = new FakeFulfillmentRequester();
        $router = new InMemoryCommandRouter();
        $commands = new RoutingCommandBus($router);
        $events = new SimpleEventDispatcher();
        $events->register(new OrderProcessedSubscriber($commands));
        $router->registerHandlers([
            ProcessOrder::class => new ProcessOrderHandler(
                new InMemoryShoppingCartRepository(
                    ShoppingCart::forCustomer($customerId, Item::create('COFFEE-BEANS', 1800, 1)),
                ),
                $orders,
                $payments,
                new DemoTransactionalUnitOfWork([$orders]),
                $events,
            ),
            FulfillOrder::class => new FulfillOrderHandler($orders, $payments, $fulfillment),
        ]);

        return [
            'commands' => $commands,
            'customer_id' => $customerId,
            'fulfillment' => $fulfillment,
            'order_id' => $orderId,
            'orders' => $orders,
            'payments' => $payments,
        ];
    }
}
