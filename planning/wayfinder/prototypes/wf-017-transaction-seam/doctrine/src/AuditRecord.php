<?php

declare(strict_types=1);

namespace Prototype;

final readonly class AuditRecord
{
    public function __construct(private string $id) {}
}
