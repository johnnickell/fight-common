<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Comparison;

use Closure;
use Fight\Common\Domain\Type\Comparator;

/**
 * Class FunctionComparator
 */
final class FunctionComparator implements Comparator
{
    protected Closure $function;

    /**
     * Constructs FunctionComparator
     */
    public function __construct(callable $function)
    {
        $this->function = Closure::fromCallable($function);
    }

    /**
     * @inheritDoc
     */
    public function compare(mixed $object1, mixed $object2): int
    {
        return (int) call_user_func($this->function, $object1, $object2);
    }
}
