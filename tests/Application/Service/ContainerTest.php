<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Service;

use Fight\Common\Application\Service\Container;
use Fight\Common\Application\Service\Exception\NotFoundException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(Container::class)]
class ContainerTest extends UnitTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_that_set_registers_shared_factory_and_get_returns_same_instance(): void
    {
        $this->container->set('svc', fn() => new stdClass());

        $first = $this->container->get('svc');
        $second = $this->container->get('svc');

        self::assertSame($first, $second);
    }

    public function test_that_factory_registers_object_factory_and_get_returns_new_instance(): void
    {
        $this->container->factory('svc', fn() => new stdClass());

        $first = $this->container->get('svc');
        $second = $this->container->get('svc');

        self::assertNotSame($first, $second);
    }

    public function test_that_get_throws_not_found_exception_for_unregistered_id(): void
    {
        self::expectException(NotFoundException::class);
        self::expectExceptionMessage("Service 'unknown' not found.");

        $this->container->get('unknown');
    }

    public function test_that_has_returns_true_for_registered_id(): void
    {
        $this->container->set('svc', fn() => new stdClass());

        self::assertTrue($this->container->has('svc'));
    }

    public function test_that_has_returns_false_for_unregistered_id(): void
    {
        self::assertFalse($this->container->has('unknown'));
    }

    public function test_that_set_parameter_stores_value_and_get_parameter_returns_it(): void
    {
        $this->container->setParameter('key', 'value');

        self::assertSame('value', $this->container->getParameter('key'));
    }

    public function test_that_get_parameter_returns_null_for_missing_key(): void
    {
        self::assertNull($this->container->getParameter('missing'));
    }

    public function test_that_get_parameter_returns_provided_default_for_missing_key(): void
    {
        self::assertSame('fallback', $this->container->getParameter('missing', 'fallback'));
    }

    public function test_that_has_parameter_returns_true_for_existing_key(): void
    {
        $this->container->setParameter('key', 'value');

        self::assertTrue($this->container->hasParameter('key'));
    }

    public function test_that_has_parameter_returns_false_for_missing_key(): void
    {
        self::assertFalse($this->container->hasParameter('missing'));
    }

    public function test_that_remove_parameter_deletes_parameter_and_has_parameter_returns_false(): void
    {
        $this->container->setParameter('key', 'value');
        $this->container->removeParameter('key');

        self::assertFalse($this->container->hasParameter('key'));
    }

    public function test_that_offset_set_stores_value_via_array_syntax(): void
    {
        $this->container['key'] = 'value';

        self::assertSame('value', $this->container->getParameter('key'));
    }

    public function test_that_offset_get_returns_value_via_array_syntax(): void
    {
        $this->container->setParameter('key', 'value');

        self::assertSame('value', $this->container['key']);
    }

    public function test_that_offset_exists_returns_true_for_existing_parameter(): void
    {
        $this->container->setParameter('key', 'value');

        self::assertTrue(isset($this->container['key']));
    }

    public function test_that_offset_exists_returns_false_for_missing_parameter(): void
    {
        self::assertFalse(isset($this->container['missing']));
    }

    public function test_that_offset_unset_removes_parameter(): void
    {
        $this->container->setParameter('key', 'value');
        unset($this->container['key']);

        self::assertFalse($this->container->hasParameter('key'));
    }

    public function test_that_offset_get_returns_null_for_unregistered_key(): void
    {
        self::assertNull($this->container['unregistered']);
    }
}
