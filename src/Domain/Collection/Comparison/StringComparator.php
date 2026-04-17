<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Collection\Comparison;

use Fight\Common\Domain\Type\Comparator;
use Fight\Common\Domain\Utility\Validate;

/**
 * Class StringComparator
 */
final class StringComparator implements Comparator
{
    /**
     * @inheritDoc
     */
    public function compare(mixed $object1, mixed $object2): int
    {
        assert(Validate::isString($object1));
        assert(Validate::isString($object2));

        $comp = strnatcmp((string) $object1, (string) $object2);

        return $comp <=> 0;
    }
}
