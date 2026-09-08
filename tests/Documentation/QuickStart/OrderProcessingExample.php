<?php

declare(strict_types=1);

// --8<-- [start:complete-order-processing-example]

namespace App\QuickStart;

use Fight\Common\Adapter\Messaging\Command\Sync\Routing\InMemoryCommandRouter;
use Fight\Common\Adapter\Messaging\Command\Sync\RoutingCommandBus;
use Fight\Common\Adapter\Messaging\Event\Sync\SimpleEventDispatcher;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventDispatcher;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Application\Repository\TransactionalUnitOfWork;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Messaging\Command\Command;
use Fight\Common\Domain\Messaging\Command\CommandMessage;
use Fight\Common\Domain\Messaging\Event\Event;
use Fight\Common\Domain\Messaging\Event\EventMessage;
use LogicException;
use Throwable;

$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
$isDirectExecution = is_string($scriptFilename) && realpath($scriptFilename) === __FILE__;

if ($isDirectExecution) {
    require $_SERVER['FIGHT_AUTOLOAD'] ?? __DIR__.'/vendor/autoload.php';
}

/**
 * Executable framework-neutral Quick Start journey.
 *
 * These application types deliberately live in the documentation fixture: they
 * demonstrate how a consumer composes Fight's public contracts without adding
 * an order-processing API to Fight Common itself.
 */
final class OrderProcessingExample
{
    /**
     * Processes the deterministic cart used by the Quick Start.
     *
     * @return non-empty-string
     */
    public static function process(): string
    {
        return self::run()->message();
    }

    public static function run(): OrderProcessingResult
    {
        // --8<-- [start:order-processing-composition]
        $customerId = CustomerId::fromString('CUSTOMER-42');
        $orderId = OrderId::fromString('ORDER-1001');
        $cart = ShoppingCart::forCustomer(
            $customerId,
            Item::create('COFFEE-BEANS', 1800, 1),
            Item::create('FILTERS', 700, 2),
        );

        $carts = new InMemoryShoppingCartRepository($cart);
        $orders = new InMemoryOrderRepository();
        $payments = new FakePaymentProcessor(
            PaymentReference::fromString('PAYMENT-1001'),
            PaymentStatus::SUCCEEDED,
        );
        $unitOfWork = new DemoTransactionalUnitOfWork([$orders]);
        $fulfillment = new FakeFulfillmentRequester();

        $router = new InMemoryCommandRouter();
        $commands = new RoutingCommandBus($router);
        $events = new SimpleEventDispatcher();
        $events->register(new OrderProcessedSubscriber($commands));
        $router->registerHandlers([
            ProcessOrder::class => new ProcessOrderHandler($carts, $orders, $payments, $unitOfWork, $events),
            FulfillOrder::class => new FulfillOrderHandler($orders, $payments, $fulfillment),
        ]);
        // --8<-- [end:order-processing-composition]

        // --8<-- [start:process-order]
        $commands->execute(
            new ProcessOrder(
                $customerId,
                $orderId,
                PaymentMethod::tokenized('provider-token-for-customer-42'),
            ),
        );
        // --8<-- [end:process-order]

        $order = $orders->get($orderId);

        if ($fulfillment->requestedOrders() !== [$order]) {
            throw new LogicException('The Quick Start fulfillment request was not recorded exactly once.');
        }

        return new OrderProcessingResult($order, $payments, $fulfillment);
    }
}

final readonly class OrderProcessingResult
{
    public function __construct(
        private Order $order,
        private FakePaymentProcessor $payments,
        private FakeFulfillmentRequester $fulfillment,
    ) {
    }

    public function message(): string
    {
        return sprintf(
            'Order %s processed for %s; fulfillment requested.',
            $this->order->id()->toString(),
            $this->order->customerId()->toString(),
        );
    }

    public function order(): Order
    {
        return $this->order;
    }

    public function payments(): FakePaymentProcessor
    {
        return $this->payments;
    }

    public function fulfillment(): FakeFulfillmentRequester
    {
        return $this->fulfillment;
    }
}

final readonly class CustomerId
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/\ACUSTOMER-[1-9][0-9]*\z/D', $value) !== 1) {
            throw new DomainException('Customer IDs must use the CUSTOMER-<number> format.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}

final readonly class OrderId
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/\AORDER-[1-9][0-9]*\z/D', $value) !== 1) {
            throw new DomainException('Order IDs must use the ORDER-<number> format.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}

final readonly class Item
{
    private function __construct(private string $sku, private int $unitPrice, private int $quantity)
    {
    }

    public static function create(string $sku, int $unitPrice, int $quantity): self
    {
        if (trim($sku) === '') {
            throw new DomainException('An item SKU is required.');
        }

        if ($unitPrice < 1) {
            throw new DomainException('An item price must be positive.');
        }

        if ($quantity < 1) {
            throw new DomainException('An item quantity must be positive.');
        }

        return new self($sku, $unitPrice, $quantity);
    }

    public function subtotal(): int
    {
        return $this->unitPrice * $this->quantity;
    }

    public function sku(): string
    {
        return $this->sku;
    }
}

final readonly class ShoppingCart
{
    /** @param list<Item> $items */
    private function __construct(private CustomerId $customerId, private array $items)
    {
    }

    public static function forCustomer(CustomerId $customerId, Item ...$items): self
    {
        if ($items === []) {
            throw new DomainException('A shopping cart must contain at least one item.');
        }

        return new self($customerId, $items);
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    /** @return list<Item> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return array_sum(array_map(static fn(Item $item): int => $item->subtotal(), $this->items));
    }
}

final readonly class PaymentMethod
{
    private function __construct(private string $providerToken)
    {
    }

    public static function tokenized(string $providerToken): self
    {
        if (trim($providerToken) === '') {
            throw new DomainException('A tokenized payment method is required.');
        }

        return new self($providerToken);
    }

    public function providerToken(): string
    {
        return $this->providerToken;
    }
}

final readonly class PaymentReference
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/\APAYMENT-[1-9][0-9]*\z/D', $value) !== 1) {
            throw new DomainException('Payment references must use the PAYMENT-<number> format.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
}

final readonly class Order
{
    /** @param list<Item> $items */
    private function __construct(
        private OrderId $id,
        private CustomerId $customerId,
        private array $items,
        private int $total,
        private PaymentReference $paymentReference,
    ) {
    }

    public static function fromCart(OrderId $id, ShoppingCart $cart, PaymentReference $paymentReference): self
    {
        return new self($id, $cart->customerId(), $cart->items(), $cart->total(), $paymentReference);
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    /** @return list<Item> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function paymentReference(): PaymentReference
    {
        return $this->paymentReference;
    }
}

interface ShoppingCartRepository
{
    public function getForCustomer(CustomerId $customerId): ShoppingCart;
}

interface OrderRepository
{
    public function save(Order $order): void;

    public function get(OrderId $orderId): Order;
}

final readonly class ProcessOrder implements Command
{
    public function __construct(
        public CustomerId $customerId,
        public OrderId $orderId,
        public PaymentMethod $paymentMethod,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        return new self(
            CustomerId::fromString(self::requiredString($data, 'customer_id')),
            OrderId::fromString(self::requiredString($data, 'order_id')),
            PaymentMethod::tokenized(self::requiredString($data, 'payment_method')),
        );
    }

    /** @return array{customer_id: string, order_id: string, payment_method: string} */
    public function toArray(): array
    {
        return [
            'customer_id' => $this->customerId->toString(),
            'order_id' => $this->orderId->toString(),
            'payment_method' => $this->paymentMethod->providerToken(),
        ];
    }

    /** @param array<string, mixed> $data */
    private static function requiredString(array $data, string $key): string
    {
        if (!isset($data[$key]) || !is_string($data[$key])) {
            throw new DomainException(sprintf('ProcessOrder requires a string "%s".', $key));
        }

        return $data[$key];
    }
}

final readonly class FulfillOrder implements Command
{
    public function __construct(public OrderId $orderId)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        return new self(OrderId::fromString(self::requiredString($data, 'order_id')));
    }

    /** @return array{order_id: string} */
    public function toArray(): array
    {
        return ['order_id' => $this->orderId->toString()];
    }

    /** @param array<string, mixed> $data */
    private static function requiredString(array $data, string $key): string
    {
        if (!isset($data[$key]) || !is_string($data[$key])) {
            throw new DomainException(sprintf('FulfillOrder requires a string "%s".', $key));
        }

        return $data[$key];
    }
}

final readonly class OrderProcessed implements Event
{
    public function __construct(public OrderId $orderId)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        if (!isset($data['order_id']) || !is_string($data['order_id'])) {
            throw new DomainException('OrderProcessed requires a string "order_id".');
        }

        return new self(OrderId::fromString($data['order_id']));
    }

    /** @return array{order_id: string} */
    public function toArray(): array
    {
        return ['order_id' => $this->orderId->toString()];
    }
}

final class PaymentNotSuccessful extends DomainException
{
}

interface PaymentProcessor
{
    public function process(int $amount, PaymentMethod $paymentMethod): PaymentReference;

    public function status(PaymentReference $paymentReference): PaymentStatus;
}

final readonly class ProcessOrderHandler implements CommandHandler
{
    public function __construct(
        private ShoppingCartRepository $carts,
        private OrderRepository $orders,
        private PaymentProcessor $payments,
        private TransactionalUnitOfWork $unitOfWork,
        private EventDispatcher $events,
    ) {
    }

    public static function commandRegistration(): string
    {
        return ProcessOrder::class;
    }

    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();

        if (!$command instanceof ProcessOrder) {
            throw new LogicException('ProcessOrderHandler received an unsupported command.');
        }

        $cart = $this->carts->getForCustomer($command->customerId);
        $paymentReference = $this->payments->process($cart->total(), $command->paymentMethod);

        $this->unitOfWork->commitTransactional(
            function () use ($command, $cart, $paymentReference): void {
                $this->orders->save(Order::fromCart($command->orderId, $cart, $paymentReference));
            },
        );

        $this->events->trigger(new OrderProcessed($command->orderId));
    }
}

/**
 * Application-owned translation from a business event to its follow-up command.
 */
final readonly class OrderProcessedSubscriber implements EventSubscriber
{
    public function __construct(private CommandBus $commands)
    {
    }

    public static function eventRegistration(): array
    {
        return [OrderProcessed::class => 'onOrderProcessed'];
    }

    public function onOrderProcessed(EventMessage $eventMessage): void
    {
        $event = $eventMessage->payload();

        if (!$event instanceof OrderProcessed) {
            throw new LogicException('OrderProcessedSubscriber received an unsupported event.');
        }

        $this->commands->execute(new FulfillOrder($event->orderId));
    }
}

interface FulfillmentRequester
{
    public function request(Order $order): void;
}

final readonly class FulfillOrderHandler implements CommandHandler
{
    public function __construct(
        private OrderRepository $orders,
        private PaymentProcessor $payments,
        private FulfillmentRequester $fulfillment,
    ) {
    }

    public static function commandRegistration(): string
    {
        return FulfillOrder::class;
    }

    public function handle(CommandMessage $commandMessage): void
    {
        $command = $commandMessage->payload();

        if (!$command instanceof FulfillOrder) {
            throw new LogicException('FulfillOrderHandler received an unsupported command.');
        }

        $order = $this->orders->get($command->orderId);

        if ($this->payments->status($order->paymentReference()) !== PaymentStatus::SUCCEEDED) {
            throw new PaymentNotSuccessful(
                sprintf('Payment %s is not successful.', $order->paymentReference()->toString()),
            );
        }

        $this->fulfillment->request($order);
    }
}

final class InMemoryShoppingCartRepository implements ShoppingCartRepository
{
    /** @var array<string, ShoppingCart> */
    private array $carts = [];

    public function __construct(ShoppingCart ...$carts)
    {
        foreach ($carts as $cart) {
            $this->carts[$cart->customerId()->toString()] = $cart;
        }
    }

    public function getForCustomer(CustomerId $customerId): ShoppingCart
    {
        return $this->carts[$customerId->toString()]
            ?? throw new DomainException('No shopping cart exists for '.$customerId->toString().'.');
    }
}

interface TransactionalState
{
    public function snapshot(): mixed;

    public function restore(mixed $snapshot): void;
}

final class InMemoryOrderRepository implements OrderRepository, TransactionalState
{
    /** @var array<string, Order> */
    private array $orders = [];
    private ?Order $lastSavedOrder = null;
    private ?Throwable $saveFailureAfterPersisting = null;

    public function save(Order $order): void
    {
        $this->orders[$order->id()->toString()] = $order;
        $this->lastSavedOrder = $order;

        if ($this->saveFailureAfterPersisting !== null) {
            throw $this->saveFailureAfterPersisting;
        }
    }

    public function failSavingAfterPersistingWith(Throwable $failure): void
    {
        $this->saveFailureAfterPersisting = $failure;
    }

    public function get(OrderId $orderId): Order
    {
        return $this->orders[$orderId->toString()]
            ?? throw new DomainException('No order exists for '.$orderId->toString().'.');
    }

    public function lastSavedOrder(): ?Order
    {
        return $this->lastSavedOrder;
    }

    /** @return array<string, Order> */
    public function snapshot(): array
    {
        return $this->orders;
    }

    /** @param array<string, Order> $snapshot */
    public function restore(mixed $snapshot): void
    {
        $this->orders = self::ordersFromSnapshot($snapshot);
    }

    /** @return array<string, Order> */
    private static function ordersFromSnapshot(mixed $snapshot): array
    {
        if (!is_array($snapshot)) {
            throw new LogicException('An in-memory order repository snapshot must be an array.');
        }

        return $snapshot;
    }
}

/**
 * Demonstration-only transaction adapter. It restores the supplied in-memory
 * repository state if the callback fails; it cannot make an external payment
 * processor atomic with that state.
 */
final class DemoTransactionalUnitOfWork implements TransactionalUnitOfWork
{
    private bool $active = false;

    /** @param list<TransactionalState> $repositories */
    public function __construct(private array $repositories)
    {
    }

    public function commitTransactional(callable $operation): mixed
    {
        if ($this->active) {
            throw new LogicException('Nested transactional execution is not supported.');
        }

        $snapshots = [];
        foreach ($this->repositories as $repository) {
            $snapshots[] = $repository->snapshot();
        }

        $this->active = true;

        try {
            return $operation();
        } catch (Throwable $failure) {
            foreach ($this->repositories as $index => $repository) {
                $repository->restore($snapshots[$index]);
            }

            throw $failure;
        } finally {
            $this->active = false;
        }
    }

    public function isClosed(): bool
    {
        return false;
    }
}

final class FakePaymentProcessor implements PaymentProcessor
{
    /** @var list<array{amount: int, payment_method: PaymentMethod}> */
    private array $processedPayments = [];
    /** @var list<PaymentReference> */
    private array $statusChecks = [];

    public function __construct(
        private readonly PaymentReference $paymentReference,
        private PaymentStatus $paymentStatus,
    ) {
    }

    public function process(int $amount, PaymentMethod $paymentMethod): PaymentReference
    {
        if ($amount < 1) {
            throw new DomainException('A payment amount must be positive.');
        }

        $this->processedPayments[] = ['amount' => $amount, 'payment_method' => $paymentMethod];

        return $this->paymentReference;
    }

    public function status(PaymentReference $paymentReference): PaymentStatus
    {
        if ($paymentReference->toString() !== $this->paymentReference->toString()) {
            throw new DomainException('The fake processor does not know '.$paymentReference->toString().'.');
        }

        $this->statusChecks[] = $paymentReference;

        return $this->paymentStatus;
    }

    public function configureStatus(PaymentStatus $paymentStatus): void
    {
        $this->paymentStatus = $paymentStatus;
    }

    /** @return list<array{amount: int, payment_method: PaymentMethod}> */
    public function processedPayments(): array
    {
        return $this->processedPayments;
    }

    /** @return list<PaymentReference> */
    public function statusChecks(): array
    {
        return $this->statusChecks;
    }
}

final class FakeFulfillmentRequester implements FulfillmentRequester
{
    /** @var list<Order> */
    private array $requestedOrders = [];

    public function request(Order $order): void
    {
        $this->requestedOrders[] = $order;
    }

    /** @return list<Order> */
    public function requestedOrders(): array
    {
        return $this->requestedOrders;
    }
}

if ($isDirectExecution) {
    echo OrderProcessingExample::process().PHP_EOL;
}

// --8<-- [end:complete-order-processing-example]
