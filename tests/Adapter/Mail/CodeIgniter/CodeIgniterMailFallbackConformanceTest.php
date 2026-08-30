<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mail\CodeIgniter;

use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
use Fight\Test\Common\Adapter\Mail\Symfony\SymfonyMailTransportTest;
use PHPUnit\Framework\Attributes\CoversClass;

/** The selected CodeIgniter mail fallback retains the complete Fight mail transport contract. */
#[CoversClass(SymfonyMailTransport::class)]
final class CodeIgniterMailFallbackConformanceTest extends SymfonyMailTransportTest
{
}
