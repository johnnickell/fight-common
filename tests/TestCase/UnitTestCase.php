<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Class UnitTestCase
 */
abstract class UnitTestCase extends TestCase
{
    /**
     * Sets up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Tears down the test environment
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Creates a mock object
     * 
     * Arguments are passed as-is to Mockery::mock()
     */
    protected function mock(): MockInterface
    {
        $args = func_get_args();

        return Mockery::mock(...$args);
    }
}
