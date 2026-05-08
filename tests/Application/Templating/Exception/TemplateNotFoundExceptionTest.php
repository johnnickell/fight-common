<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Templating\Exception;

use Fight\Common\Application\Templating\Exception\TemplateNotFoundException;
use Fight\Common\Application\Templating\Exception\TemplatingException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TemplateNotFoundException::class)]
class TemplateNotFoundExceptionTest extends UnitTestCase
{
    public function test_that_construction_with_message_and_template_sets_both(): void
    {
        $exception = new TemplateNotFoundException('Template not found: home.html', 'home.html');

        self::assertSame('Template not found: home.html', $exception->getMessage());
        self::assertSame('home.html', $exception->getTemplate());
    }

    public function test_that_get_template_returns_null_when_no_template_provided(): void
    {
        $exception = new TemplateNotFoundException('Template not found');

        self::assertNull($exception->getTemplate());
    }

    public function test_that_from_name_creates_instance_with_formatted_message_and_correct_template(): void
    {
        $exception = TemplateNotFoundException::fromName('emails/welcome.html');

        self::assertSame('emails/welcome.html', $exception->getTemplate());
        self::assertStringContainsString('emails/welcome.html', $exception->getMessage());
    }

    public function test_that_template_not_found_exception_extends_templating_exception(): void
    {
        $exception = new TemplateNotFoundException('Template not found: home.html', 'home.html');

        self::assertInstanceOf(TemplatingException::class, $exception);
    }
}
