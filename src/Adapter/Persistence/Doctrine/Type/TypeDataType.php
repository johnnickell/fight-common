<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Fight\Common\Domain\Type\Type;

/**
 * Class TypeDataType
 */
class TypeDataType extends ValueObjectDataType
{
    public const string TYPE_NAME = 'common_type';

    protected const string VALUE_CLASS = Type::class;
    protected const string VALUE_FACTORY = 'create';
}
