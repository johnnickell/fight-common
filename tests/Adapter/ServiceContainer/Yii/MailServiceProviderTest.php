<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\ServiceContainer\Yii;

use Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
use Fight\Common\Adapter\ServiceContainer\Yii\MailServiceProvider;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Mailer\MailerInterface;
use Yiisoft\Definitions\Reference;

#[CoversClass(MailServiceProvider::class)]
final class MailServiceProviderTest extends UnitTestCase
{
    public function test_that_get_definitions_binds_the_symfony_mail_fallback(): void
    {
        self::assertEquals(
            [
                MailFactory::class   => ['class' => SymfonyMailFactory::class],
                MailTransport::class => [
                    'class'         => SymfonyMailTransport::class,
                    '__construct()' => [Reference::to(MailerInterface::class)]
                ]
            ],
            (new MailServiceProvider())->getDefinitions()
        );
    }

    public function test_that_get_extensions_returns_no_extensions(): void
    {
        self::assertSame([], (new MailServiceProvider())->getExtensions());
    }
}
