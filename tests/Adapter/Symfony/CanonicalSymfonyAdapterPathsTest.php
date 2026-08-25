<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Symfony;

use Fight\Common\Adapter\EventSubscriber\SymfonyExceptionSubscriber as LegacySymfonyExceptionSubscriber;
use Fight\Common\Adapter\EventSubscriber\SymfonyValidationSubscriber as LegacySymfonyValidationSubscriber;
use Fight\Common\Adapter\Filesystem\SymfonyFilesystem as LegacySymfonyFilesystem;
use Fight\Common\Adapter\HttpKernel\ErrorController as LegacyErrorController;
use Fight\Common\Adapter\HttpKernel\JsonRequestMiddleware as LegacyJsonRequestMiddleware;
use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Adapter\Http\Symfony\Controller\ErrorController;
use Fight\Common\Adapter\Http\Symfony\EventSubscriber\SymfonyExceptionSubscriber;
use Fight\Common\Adapter\Http\Symfony\EventSubscriber\SymfonyValidationSubscriber;
use Fight\Common\Adapter\Middleware\Symfony\JsonRequestMiddleware;
use Fight\Common\Adapter\Routing\SymfonyUrlGenerator as LegacySymfonyUrlGenerator;
use Fight\Common\Adapter\Routing\Symfony\SymfonyUrlGenerator;
use Fight\Common\Application\Validation\ValidationService;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(SymfonyExceptionSubscriber::class)]
#[CoversClass(SymfonyValidationSubscriber::class)]
#[CoversClass(JsonRequestMiddleware::class)]
#[CoversClass(ErrorController::class)]
#[CoversClass(SymfonyFilesystem::class)]
#[CoversClass(SymfonyUrlGenerator::class)]
final class CanonicalSymfonyAdapterPathsTest extends UnitTestCase
{
    public function test_that_each_canonical_symfony_adapter_path_is_loadable(): void
    {
        foreach (
            [
                SymfonyExceptionSubscriber::class,
                SymfonyValidationSubscriber::class,
                JsonRequestMiddleware::class,
                ErrorController::class,
                SymfonyFilesystem::class,
                SymfonyUrlGenerator::class
            ] as $class
        ) {
            self::assertTrue(class_exists($class));
        }
    }

    public function test_that_the_canonical_exception_subscriber_registers_through_its_attribute_and_tag(): void
    {
        $attributes = (new \ReflectionClass(SymfonyExceptionSubscriber::class))->getAttributes(AsEventListener::class);
        self::assertCount(1, $attributes);

        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)->addTag('kernel.event_subscriber');
        $container->register(ErrorController::class, ErrorController::class)->setPublic(true);
        $container->register(SymfonyExceptionSubscriber::class, SymfonyExceptionSubscriber::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->compile();

        self::assertArrayHasKey('kernel.event_subscriber', $container->getDefinition(SymfonyExceptionSubscriber::class)->getTags());
    }

    public function test_that_both_validation_subscriber_identities_register_as_kernel_subscribers(): void
    {
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)->addTag('kernel.event_subscriber');
        $container->register(ValidationService::class, ValidationService::class)->setPublic(true);
        $container->register(SymfonyValidationSubscriber::class, SymfonyValidationSubscriber::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->register(LegacySymfonyValidationSubscriber::class, LegacySymfonyValidationSubscriber::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->compile();

        self::assertArrayHasKey('kernel.event_subscriber', $container->getDefinition(SymfonyValidationSubscriber::class)->getTags());
        self::assertArrayHasKey('kernel.event_subscriber', $container->getDefinition(LegacySymfonyValidationSubscriber::class)->getTags());
    }

    public function test_that_legacy_filesystem_and_url_generator_identities_preserve_public_behavior(): void
    {
        $filesystem = new LegacySymfonyFilesystem();
        self::assertFalse($filesystem->exists('/definitely-not-a-fight-common-file'));

        /** @var UrlGeneratorInterface $inner */
        $inner = $this->mock(UrlGeneratorInterface::class);
        $inner->shouldReceive('generate')
            ->with('account', ['id' => 42], UrlGeneratorInterface::ABSOLUTE_URL)
            ->once()
            ->andReturn('https://example.test/account/42');

        $generator = new LegacySymfonyUrlGenerator($inner);
        self::assertSame(
            'https://example.test/account/42?tab=security',
            $generator->generate('account', ['id' => 42], ['tab' => 'security'], true)
        );
    }

    public function test_that_the_canonical_exception_subscriber_translates_a_json_kernel_exception(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SymfonyExceptionSubscriber(new ErrorController()));
        $request = new Request();
        $request->headers->set('Accept', 'application/json');

        /** @var HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new RuntimeException('broken'));
        $dispatcher->dispatch($event, KernelEvents::EXCEPTION);

        self::assertSame(500, $event->getResponse()?->getStatusCode());
        self::assertSame('{"status":"error","message":"broken"}', $event->getResponse()?->getContent());
    }

    public function test_that_the_canonical_exception_subscriber_ignores_non_json_requests(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SymfonyExceptionSubscriber(new ErrorController()));

        /** @var HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $event = new ExceptionEvent(
            $kernel,
            new Request([], [], [], [], [], ['HTTP_ACCEPT' => 'text/plain']),
            HttpKernelInterface::MAIN_REQUEST,
            new RuntimeException('broken')
        );
        $dispatcher->dispatch($event, KernelEvents::EXCEPTION);

        self::assertNull($event->getResponse());
    }

    public function test_that_the_canonical_exception_subscriber_accepts_xml_http_requests(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SymfonyExceptionSubscriber(new ErrorController()));
        $request = new Request();
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        /** @var HttpKernelInterface $kernel */
        $kernel = $this->mock(HttpKernelInterface::class);
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new RuntimeException('broken'));
        $dispatcher->dispatch($event, KernelEvents::EXCEPTION);

        self::assertSame(500, $event->getResponse()?->getStatusCode());
    }

    public function test_that_the_canonical_error_controller_uses_typed_jsend_data_for_validation_errors(): void
    {
        $response = (new ErrorController())->handle(new ValidationException(['email' => ['Email is required']]));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('{"status":"fail","data":{"email":["Email is required"]}}', $response->getContent());
    }

    public function test_that_the_canonical_error_controller_preserves_http_exception_status_codes(): void
    {
        $response = (new ErrorController())->handle(new HttpException(429, 'Slow down'));

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('{"status":"error","message":"Slow down"}', $response->getContent());
    }

    public function test_that_legacy_paths_remain_loadable_without_runtime_deprecation(): void
    {
        $deprecations = [];
        set_error_handler(
            static function (int $severity, string $message) use (&$deprecations): bool {
                if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                    $deprecations[] = $message;
                }

                return false;
            }
        );

        try {
            foreach (
                [
                    LegacySymfonyExceptionSubscriber::class,
                    LegacySymfonyValidationSubscriber::class,
                    LegacyJsonRequestMiddleware::class,
                    LegacyErrorController::class,
                    LegacySymfonyFilesystem::class,
                    LegacySymfonyUrlGenerator::class
                ] as $class
            ) {
                self::assertTrue(class_exists($class));
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
        self::assertTrue(is_a(LegacyJsonRequestMiddleware::class, JsonRequestMiddleware::class, true));
        self::assertTrue(is_a(LegacySymfonyFilesystem::class, SymfonyFilesystem::class, true));
        self::assertTrue(is_a(LegacySymfonyUrlGenerator::class, SymfonyUrlGenerator::class, true));
        self::assertFalse(is_a(LegacySymfonyExceptionSubscriber::class, SymfonyExceptionSubscriber::class, true));
        self::assertTrue(is_a(LegacySymfonyValidationSubscriber::class, SymfonyValidationSubscriber::class, true));
        self::assertFalse(is_a(LegacyErrorController::class, ErrorController::class, true));
    }
}
