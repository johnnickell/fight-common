<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Persistence\Doctrine\Type;

use Fight\Common\Domain\Observability\AuditEntryId;

/**
 * Class AuditEntryIdDataType
 */
class AuditEntryIdDataType extends ValueObjectDataType
{
    public const string TYPE_NAME = 'audit_entry_id';

    protected const string VALUE_CLASS = AuditEntryId::class;
    protected const bool USES_GUID_DECLARATION = true;
}
