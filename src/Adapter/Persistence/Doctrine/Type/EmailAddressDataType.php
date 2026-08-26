<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Fight\Common\Domain\Value\Internet\EmailAddress;

/**
 * Class EmailAddressDataType
 */
class EmailAddressDataType extends ValueObjectDataType
{
    public const string TYPE_NAME = 'common_email_address';

    protected const string VALUE_CLASS = EmailAddress::class;
}
