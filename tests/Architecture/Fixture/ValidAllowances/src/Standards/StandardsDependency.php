<?php

declare(strict_types=1);

namespace Fight\Common\Standards;

use DateTimeImmutable;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\ClassHelper;

final readonly class StandardsDependency
{
    public function __construct(
        private DateTimeImmutable $createdAt,
        private File $file,
        private Sniff $sniff,
        private ClassHelper $classHelper,
    ) {}
}
