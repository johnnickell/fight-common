<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Comparison;

use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Type\Comparator;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class ComparableComparator
 */
final class ComparableComparator implements Comparator
{
    /**
     * @inheritDoc
     */
    public function compare(mixed $object1, mixed $object2): int
    {
        assert(Validate::implementsInterface($object1, Comparable::class));
        assert(Validate::areSameType($object1, $object2));

        return $object1->compareTo($object2);
    }
}
