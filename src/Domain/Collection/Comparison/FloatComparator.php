<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Comparison;

use Fight\Common\Domain\Type\Comparator;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class FloatComparator
 */
final class FloatComparator implements Comparator
{
    /**
     * @inheritDoc
     */
    public function compare(mixed $object1, mixed $object2): int
    {
        assert(Validate::isFloat($object1));
        assert(Validate::isFloat($object2));

        return $object1 <=> $object2;
    }
}
