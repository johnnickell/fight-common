<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mail\CodeIgniter;

use CodeIgniter\Email\Email;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Records the native metadata gaps that require the complete Symfony Mailer fallback. */
#[CoversNothing]
final class CodeIgniterMailPrototypeCapabilityTest extends UnitTestCase
{
    public function test_that_native_email_does_not_offer_required_fight_message_metadata_operations(): void
    {
        self::assertFalse(method_exists(Email::class, 'setSender'));
        self::assertFalse(method_exists(Email::class, 'setReturnPath'));
        self::assertFalse(method_exists(Email::class, 'setTimestamp'));
    }
}
