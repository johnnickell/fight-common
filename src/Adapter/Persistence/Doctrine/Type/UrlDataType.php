<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Fight\Common\Domain\Value\Internet\Url;

/**
 * Class UrlDataType
 */
class UrlDataType extends ValueObjectDataType
{
    public const string TYPE_NAME = 'common_url';

    protected const string VALUE_CLASS = Url::class;
}
