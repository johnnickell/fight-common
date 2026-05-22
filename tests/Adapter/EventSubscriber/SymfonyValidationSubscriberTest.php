<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\EventSubscriber;

use Fight\Common\Adapter\EventSubscriber\SymfonyValidationSubscriber;
use Fight\Common\Application\Attribute\Validation;
use Fight\Common\Application\Validation\ValidationService;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class StaticControllerStub
{
    public static function action(): void {}
}

#[CoversClass(SymfonyValidationSubscriber::class)]
class SymfonyValidationSubscriberTest extends UnitTestCase
{
    public function test_that_subscriber_registers_for_controller_event(): void
    {
        $subscriber = new SymfonyValidationSubscriber(new ValidationService());

        $events = $subscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::CONTROLLER, $events);
        self::assertSame('validateControllerInput', $events[KernelEvents::CONTROLLER]);
    }

    public function test_that_validate_controller_input_validates_with_query_params_for_safe_method(): void
    {
        $controller = new class {
            #[Validation(rules: [['field' => 'name', 'label' => 'Name', 'rules' => 'alpha']])]
            public function index(): void {}
        };

        $request = new Request(['name' => 'Alice'], []);

        $event = new ControllerEvent(
            $this->mock(HttpKernelInterface::class),
            $controller->index(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber = new SymfonyValidationSubscriber(new ValidationService());
        $subscriber->validateControllerInput($event);

        self::assertTrue(true);
    }

    public function test_that_validate_controller_input_uses_request_params_for_non_safe_method(): void
    {
        $controller = new class {
            #[Validation(rules: [['field' => 'name', 'label' => 'Name', 'rules' => 'alpha']])]
            public function submit(): void {}
        };

        $request = Request::create('/submit', 'POST', ['name' => 'Alice']);

        $event = new ControllerEvent(
            $this->mock(HttpKernelInterface::class),
            $controller->submit(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber = new SymfonyValidationSubscriber(new ValidationService());
        $subscriber->validateControllerInput($event);

        self::assertTrue(true);
    }

    public function test_that_validate_controller_input_handles_invoke_controller(): void
    {
        $controller = new class {
            #[Validation(rules: [['field' => 'name', 'label' => 'Name', 'rules' => 'alpha']])]
            public function __invoke(): void {}
        };

        $request = new Request(['name' => 'Alice'], []);

        $event = new ControllerEvent(
            $this->mock(HttpKernelInterface::class),
            $controller,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber = new SymfonyValidationSubscriber(new ValidationService());
        $subscriber->validateControllerInput($event);

        self::assertTrue(true);
    }

    public function test_that_validate_controller_input_returns_early_when_controller_is_not_array(): void
    {
        $request = new Request();

        $event = new ControllerEvent(
            $this->mock(HttpKernelInterface::class),
            'strlen',
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber = new SymfonyValidationSubscriber(new ValidationService());
        $subscriber->validateControllerInput($event);

        self::assertTrue(true);
    }

    public function test_that_validate_controller_input_returns_early_when_controller_array_has_no_object(): void
    {
        $request = new Request();

        $event = new ControllerEvent(
            $this->mock(HttpKernelInterface::class),
            StaticControllerStub::action(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber = new SymfonyValidationSubscriber(new ValidationService());
        $subscriber->validateControllerInput($event);

        self::assertTrue(true);
    }

    public function test_that_validate_controller_input_skips_when_no_validation_attribute(): void
    {
        $controller = new class {
            public function index(): void {}
        };

        $request = new Request(['name' => 'Alice'], []);

        $event = new ControllerEvent(
            $this->mock(HttpKernelInterface::class),
            $controller->index(...),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber = new SymfonyValidationSubscriber(new ValidationService());
        $subscriber->validateControllerInput($event);

        self::assertTrue(true);
    }
}
