<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Fight\Common\Domain\Value\Basic\MbStringObject;

/**
 * Class MbStringObjectDataType
 */
class MbStringObjectDataType extends ValueObjectDataType
{
    public const string TYPE_NAME = 'common_mb_string';

    protected const string VALUE_CLASS = MbStringObject::class;
    protected const bool EMPTY_PHP_VALUE_IS_NULL = false;
}
