<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Fight\Common\Domain\Value\Basic\MbStringObject;

/**
 * Class MbStringTextDataType
 */
class MbStringTextDataType extends ValueObjectDataType
{
    public const string TYPE_NAME = 'common_mb_string_text';

    protected const string VALUE_CLASS = MbStringObject::class;
    protected const bool USES_CLOB_DECLARATION = true;
    protected const bool EMPTY_PHP_VALUE_IS_NULL = false;
}
