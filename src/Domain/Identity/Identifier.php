<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Identity;

use Fight\Common\Domain\Type\Comparable;
use Fight\Common\Domain\Value\Value;

/**
 * Interface Identifier
 */
interface Identifier extends Comparable, Value
{
}
