<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Fight\Common\Domain\Value\Internet\Uri;

/**
 * Class UriDataType
 */
class UriDataType extends ValueObjectDataType
{
    public const string TYPE_NAME = 'common_uri';

    protected const string VALUE_CLASS = Uri::class;
}
