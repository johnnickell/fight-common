<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Templating\CodeIgniter;

use CodeIgniter\View\View;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Records the native View contract gaps that require the complete PHP template fallback. */
#[CoversNothing]
final class CodeIgniterTemplatePrototypeCapabilityTest extends UnitTestCase
{
    public function test_that_native_view_does_not_offer_template_capability_introspection_or_helper_registration(): void
    {
        self::assertFalse(method_exists(View::class, 'exists'));
        self::assertFalse(method_exists(View::class, 'supports'));
        self::assertFalse(method_exists(View::class, 'addHelper'));
    }
}
