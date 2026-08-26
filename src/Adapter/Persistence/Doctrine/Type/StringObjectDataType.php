<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Fight\Common\Domain\Value\Basic\StringObject;

/**
 * Class StringObjectDataType
 */
class StringObjectDataType extends ValueObjectDataType
{
    public const string TYPE_NAME = 'common_string';

    protected const string VALUE_CLASS = StringObject::class;
    protected const bool EMPTY_PHP_VALUE_IS_NULL = false;
}
