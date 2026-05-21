<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mail\Symfony;

use Fight\Common\Adapter\Mail\Symfony\SymfonyAttachment;
use Fight\Common\Application\Mail\Exception\MailException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SymfonyAttachment::class)]
class SymfonyAttachmentTest extends UnitTestCase
{
    public function test_that_from_string_creates_attachment_without_embed_id(): void
    {
        $attachment = SymfonyAttachment::fromString('body', 'file.txt', 'text/plain');

        self::assertSame('body', $attachment->getBody());
        self::assertSame('file.txt', $attachment->getFileName());
        self::assertSame('text/plain', $attachment->getContentType());
        self::assertSame('attachment', $attachment->getDisposition());
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $attachment->getId());
        self::assertStringStartsWith('cid:', $attachment->embed());
    }

    public function test_that_from_string_with_embed_id_creates_inline_attachment(): void
    {
        $attachment = SymfonyAttachment::fromString('body', 'file.txt', 'text/plain', 'my-embed');

        self::assertSame('body', $attachment->getBody());
        self::assertSame('my-embed', $attachment->getId());
        self::assertSame('inline', $attachment->getDisposition());
        self::assertSame('cid:my-embed', $attachment->embed());
    }

    public function test_that_from_path_creates_attachment(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sat_');
        file_put_contents($file, 'file-body');

        $attachment = SymfonyAttachment::fromPath($file, 'doc.txt', 'application/octet-stream');

        self::assertSame('doc.txt', $attachment->getFileName());
        self::assertSame('application/octet-stream', $attachment->getContentType());
        self::assertSame('attachment', $attachment->getDisposition());
        self::assertIsResource($attachment->getBody());
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $attachment->getId());

        unlink($file);
    }

    public function test_that_from_path_with_embed_id_creates_inline_attachment(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sat_');
        file_put_contents($file, 'file-body');

        $attachment = SymfonyAttachment::fromPath($file, 'doc.txt', 'text/plain', 'embed-id');

        self::assertSame('inline', $attachment->getDisposition());
        self::assertSame('embed-id', $attachment->getId());
        self::assertSame('cid:embed-id', $attachment->embed());

        unlink($file);
    }

    public function test_that_from_path_throws_mail_exception_when_file_not_found(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Unable to open path');

        SymfonyAttachment::fromPath('/nonexistent/file.txt', 'file.txt', 'text/plain');
    }
}
