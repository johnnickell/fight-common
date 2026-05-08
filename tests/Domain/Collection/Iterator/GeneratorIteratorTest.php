<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Collection\Iterator;

use Exception;
use Fight\Common\Domain\Collection\Iterator\GeneratorIterator;
use Fight\Common\Domain\Exception\MethodCallException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GeneratorIterator::class)]
class GeneratorIteratorTest extends UnitTestCase
{
    public function test_that_rewind_allows_iteration_more_than_once()
    {
        $iterator = new GeneratorIterator(function () {
            for ($i = 0; $i < 10; $i++) {
                yield $i => $i;
            }
        });

        $count = 0;
        foreach ($iterator as $key => $value) {
            $count++;
        }

        self::assertFalse($iterator->valid());

        foreach ($iterator as $key => $value) {
            $count++;
        }
    }

    public function test_that_valid_returns_true_with_valid_position()
    {
        $iterator = new GeneratorIterator(function () {
            for ($i = 0; $i < 10; $i++) {
                yield $i => $i;
            }
        });

        self::assertTrue($iterator->valid());
    }

    public function test_that_current_returns_first_yielded_value()
    {
        $iterator = new GeneratorIterator(function () {
            for ($i = 0; $i < 10; $i++) {
                yield $i => $i;
            }
        });

        self::assertSame(0, $iterator->current());
    }

    public function test_that_key_returns_first_yielded_key()
    {
        $iterator = new GeneratorIterator(function () {
            for ($i = 0; $i < 10; $i++) {
                yield $i => $i;
            }
        });

        self::assertSame(0, $iterator->key());
    }

    public function test_that_next_advances_to_next_position()
    {
        $iterator = new GeneratorIterator(function () {
            for ($i = 0; $i < 10; $i++) {
                yield $i => $i;
            }
        });

        $iterator->next();

        self::assertSame(1, $iterator->key());
    }

    public function test_that_send_injects_value_to_generator()
    {
        $iterator = new GeneratorIterator(function () {
            $buffer = '';
            while (true) {
                $buffer .= (yield $buffer);
            }
        });

        $iterator->send('Hello');
        $iterator->send(' ');
        $iterator->send('World');

        self::assertSame('Hello World', $iterator->current());
    }

    public function test_that_throw_sends_an_exception_into_generator()
    {
        $iterator = new GeneratorIterator(function () {
            $buffer = '';
            while (true) {
                try {
                    $buffer .= (yield $buffer);
                } catch (Exception $e) {
                    $buffer .= $e->getMessage();
                }
            }
        });

        $iterator->throw(new Exception('Oops!'));
        $iterator->send(' ');
        $iterator->send('Hello');
        $iterator->send(' ');
        $iterator->send('World');

        self::assertSame('Oops! Hello World', $iterator->current());
    }

    public function test_that_get_return_throws_when_generator_not_initialized(): void
    {
        $iterator = new GeneratorIterator(function () {
            yield 1;
        });

        $this->expectException(MethodCallException::class);
        $iterator->getReturn();
    }

    public function test_that_get_return_returns_value_after_generator_completes(): void
    {
        $iterator = new GeneratorIterator(function () {
            yield 1;
            return 'finished';
        });

        foreach ($iterator as $_) {
        }

        self::assertSame('finished', $iterator->getReturn());
    }
}
