<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Symfony;

use Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem;
use Fight\Common\Adapter\Http\Symfony\EventSubscriber\SymfonyValidationSubscriber;
use Fight\Common\Adapter\Middleware\Symfony\JsonRequestMiddleware;
use Fight\Common\Adapter\Routing\Symfony\SymfonyUrlGenerator;
use Fight\Common\Application\Validation\ValidationService;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(SymfonyValidationSubscriber::class)]
#[CoversClass(JsonRequestMiddleware::class)]
#[CoversClass(SymfonyFilesystem::class)]
#[CoversClass(SymfonyUrlGenerator::class)]
final class CanonicalSymfonyAdapterPathsTest extends UnitTestCase
{
    public function test_that_each_canonical_symfony_adapter_path_is_loadable(): void
    {
        foreach (
            [
                SymfonyValidationSubscriber::class,
                JsonRequestMiddleware::class,
                SymfonyFilesystem::class,
                SymfonyUrlGenerator::class
            ] as $class
        ) {
            self::assertTrue(class_exists($class));
        }
    }

    public function test_that_the_validation_subscriber_registers_as_kernel_subscribers(): void
    {
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)->addTag('kernel.event_subscriber');
        $container->register(ValidationService::class, ValidationService::class)->setPublic(true);
        $container->register(SymfonyValidationSubscriber::class, SymfonyValidationSubscriber::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->compile();

        self::assertArrayHasKey('kernel.event_subscriber', $container->getDefinition(SymfonyValidationSubscriber::class)->getTags());
    }

    public function test_that_filesystem_and_url_generator_adapters_preserve_public_behavior(): void
    {
        $filesystem = new SymfonyFilesystem();
        self::assertFalse($filesystem->exists('/definitely-not-a-fight-common-file'));

        /** @var UrlGeneratorInterface $inner */
        $inner = $this->mock(UrlGeneratorInterface::class);
        $inner->shouldReceive('generate')
            ->with('account', ['id' => 42], UrlGeneratorInterface::ABSOLUTE_URL)
            ->once()
            ->andReturn('https://example.test/account/42');

        $generator = new SymfonyUrlGenerator($inner);
        self::assertSame(
            'https://example.test/account/42?tab=security',
            $generator->generate('account', ['id' => 42], ['tab' => 'security'], true)
        );
    }

}
