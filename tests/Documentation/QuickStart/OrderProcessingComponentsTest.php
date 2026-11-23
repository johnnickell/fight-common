<?php

declare(strict_types=1);

namespace Fight\Test\Common\Documentation\QuickStart;

use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Utility\ClassName;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

require_once __DIR__.'/OrderProcessingExample.php';

#[CoversNothing]
final class OrderProcessingComponentsTest extends UnitTestCase
{
    public function test_that_the_order_handler_uses_cart_total_and_stores_payment_reference(): void
    {
        $orders = new InMemoryOrderRepository();
        $payments = $this->payments(PaymentStatus::SUCCEEDED);
        $orderId = OrderId::fromString('ORDER-1001');
        $this->process($orders, $payments, new SimpleEventDispatcher(), $orderId);

        self::assertSame(3600, $payments->processedPayments()[0]['amount']);
        self::assertSame('provider-token', $payments->processedPayments()[0]['payment_method']->providerToken());
        self::assertSame('PAYMENT-1001', $orders->get($orderId)->paymentReference()->toString());
    }

    public function test_that_failed_persistence_leaves_no_order_and_emits_no_postcommit_fact(): void
    {
        $orders = new InMemoryOrderRepository();
        $orders->failSavingAfterPersistingWith(new RuntimeException('The documented persistence callback failed.'));
        $payments = $this->payments(PaymentStatus::SUCCEEDED);
        $orderId = OrderId::fromString('ORDER-1001');
        $events = new SimpleEventDispatcher();
        $events->addHandler(ClassName::underscore(OrderProcessed::class), static fn () => self::fail('A rolled back order may not publish a fact.'));

        try {
            $this->process($orders, $payments, $events, $orderId);
            self::fail('The transaction failure must be rethrown.');
        } catch (RuntimeException $failure) {
            self::assertSame('The documented persistence callback failed.', $failure->getMessage());
        }
        self::assertSame('ORDER-1001', $orders->lastSavedOrder()?->id()->toString());
        self::assertCount(1, $payments->processedPayments());
        self::assertSame([], $payments->statusChecks());
        $this->expectException(DomainException::class);
        $orders->get($orderId);
    }

    #[DataProvider('unsettledPayments')]
    public function test_that_the_fulfillment_handler_refuses_to_submit_unsettled_orders(PaymentStatus $status): void
    {
        $orders = new InMemoryOrderRepository();
        $payments = $this->payments($status);
        $orderId = OrderId::fromString('ORDER-1001');
        $fulfillment = new FakeFulfillmentRequester();
        $this->process($orders, $payments, new SimpleEventDispatcher(), $orderId);
        $handler = new FulfillOrderHandler($orders, $payments, $fulfillment);

        try {
            $handler->handle(CommandMessage::create(new FulfillOrder($orderId)));
            self::fail('Unsettled payments may not request fulfillment.');
        } catch (PaymentNotSuccessful) {
            self::assertSame([], $fulfillment->requestedOrders());
            self::assertCount(1, $payments->statusChecks());
            self::assertSame('ORDER-1001', $orders->get($orderId)->id()->toString());
        }
    }

    public static function unsettledPayments(): iterable
    {
        yield 'pending' => [PaymentStatus::PENDING];
        yield 'failed' => [PaymentStatus::FAILED];
    }

    private function payments(PaymentStatus $status): FakePaymentProcessor
    {
        return new FakePaymentProcessor(PaymentReference::fromString('PAYMENT-1001'), $status);
    }

    private function process(
        InMemoryOrderRepository $orders,
        FakePaymentProcessor $payments,
        SimpleEventDispatcher $events,
        OrderId $orderId
    ): void {
        $customerId = CustomerId::fromString('CUSTOMER-42');
        $handler = new ProcessOrderHandler(
            new InMemoryShoppingCartRepository(ShoppingCart::forCustomer($customerId, Item::create('COFFEE-BEANS', 1800, 2))),
            $orders,
            $payments,
            new DemoTransactionalUnitOfWork([$orders]),
            $events
        );
        $handler->handle(CommandMessage::create(new ProcessOrder($customerId, $orderId, PaymentMethod::tokenized('provider-token'))));
    }
}
