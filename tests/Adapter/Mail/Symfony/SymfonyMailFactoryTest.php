<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mail\Symfony;

use Fight\Common\Adapter\Mail\Symfony\SymfonyAttachment;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SymfonyMailFactory::class)]
class SymfonyMailFactoryTest extends UnitTestCase
{
    public function test_that_create_message_returns_mail_message(): void
    {
        $factory = new SymfonyMailFactory();

        $message = $factory->createMessage();

        self::assertInstanceOf(MailMessage::class, $message);
    }

    public function test_that_create_attachment_from_string_returns_symfony_attachment(): void
    {
        $factory = new SymfonyMailFactory();

        $attachment = $factory->createAttachmentFromString('body', 'file.txt', 'text/plain');

        self::assertInstanceOf(SymfonyAttachment::class, $attachment);
        self::assertSame('body', $attachment->getBody());
        self::assertSame('file.txt', $attachment->getFileName());
        self::assertSame('text/plain', $attachment->getContentType());
        self::assertSame('attachment', $attachment->getDisposition());
    }

    public function test_that_create_attachment_from_string_with_embed_id_returns_inline_attachment(): void
    {
        $factory = new SymfonyMailFactory();

        $attachment = $factory->createAttachmentFromString('body', 'file.txt', 'text/plain', 'embed-123');

        self::assertSame('cid:embed-123', $attachment->embed());
        self::assertSame('inline', $attachment->getDisposition());
    }

    public function test_that_create_attachment_from_path_returns_symfony_attachment(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'smt_');
        file_put_contents($file, 'file-content');

        $factory = new SymfonyMailFactory();
        $attachment = $factory->createAttachmentFromPath($file, 'file.txt', 'text/plain');

        self::assertInstanceOf(SymfonyAttachment::class, $attachment);
        self::assertSame('file.txt', $attachment->getFileName());
        self::assertSame('text/plain', $attachment->getContentType());

        unlink($file);
    }

    public function test_that_generate_embed_id_returns_hex_string(): void
    {
        $factory = new SymfonyMailFactory();

        $id = $factory->generateEmbedId();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $id);
    }
}
