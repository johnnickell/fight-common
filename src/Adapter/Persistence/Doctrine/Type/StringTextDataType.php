<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Fight\Common\Domain\Value\Basic\StringObject;

/**
 * Class StringTextDataType
 */
class StringTextDataType extends ValueObjectDataType
{
    public const string TYPE_NAME = 'common_string_text';

    protected const string VALUE_CLASS = StringObject::class;
    protected const bool USES_CLOB_DECLARATION = true;
    protected const bool EMPTY_PHP_VALUE_IS_NULL = false;
}
