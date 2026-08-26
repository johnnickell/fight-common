<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Fight\Common\Domain\Value\Identifier\Uuid;

/**
 * Class UuidDataType
 */
class UuidDataType extends ValueObjectDataType
{
    public const string TYPE_NAME = 'common_uuid';

    protected const string VALUE_CLASS = Uuid::class;
    protected const bool USES_GUID_DECLARATION = true;
}
