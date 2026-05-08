<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mail;

use Override;
use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Mail\Message\Attachment;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MailService::class)]
class MailServiceTest extends UnitTestCase
{
    /** @var MockInterface|MailTransport */
    private $transport;
    /** @var MockInterface|MailFactory */
    private $factory;
    private MailService $service;

    #[Override]
    protected function setUp(): void
    {
        $this->transport = $this->mock(MailTransport::class);
        $this->factory = $this->mock(MailFactory::class);
        $this->service = new MailService($this->transport, $this->factory);
    }

    public function test_that_construction_creates_service_implementing_both_interfaces(): void
    {
        self::assertInstanceOf(MailTransport::class, $this->service);
        self::assertInstanceOf(MailFactory::class, $this->service);
    }

    public function test_that_send_delegates_to_transport(): void
    {
        $message = new MailMessage();

        $this->transport
            ->shouldReceive('send')
            ->once()
            ->with($message);

        $this->service->send($message);
    }

    public function test_that_create_message_delegates_to_factory_and_returns_message(): void
    {
        $message = new MailMessage();

        $this->factory
            ->shouldReceive('createMessage')
            ->once()
            ->andReturn($message);

        $result = $this->service->createMessage();

        self::assertSame($message, $result);
    }

    public function test_that_create_attachment_from_string_delegates_to_factory_and_returns_attachment(): void
    {
        /** @var MockInterface|Attachment $attachment */
        $attachment = $this->mock(Attachment::class);

        $this->factory
            ->shouldReceive('createAttachmentFromString')
            ->once()
            ->with('data', 'file.pdf', 'application/pdf', 'embed-1')
            ->andReturn($attachment);

        $result = $this->service->createAttachmentFromString('data', 'file.pdf', 'application/pdf', 'embed-1');

        self::assertSame($attachment, $result);
    }

    public function test_that_create_attachment_from_path_delegates_to_factory_and_returns_attachment(): void
    {
        /** @var MockInterface|Attachment $attachment */
        $attachment = $this->mock(Attachment::class);

        $this->factory
            ->shouldReceive('createAttachmentFromPath')
            ->once()
            ->with('/tmp/file.pdf', 'file.pdf', 'application/pdf', null)
            ->andReturn($attachment);

        $result = $this->service->createAttachmentFromPath('/tmp/file.pdf', 'file.pdf', 'application/pdf');

        self::assertSame($attachment, $result);
    }

    public function test_that_generate_embed_id_delegates_to_factory_and_returns_string(): void
    {
        $this->factory
            ->shouldReceive('generateEmbedId')
            ->once()
            ->andReturn('cid:abc123');

        $result = $this->service->generateEmbedId();

        self::assertSame('cid:abc123', $result);
    }
}
