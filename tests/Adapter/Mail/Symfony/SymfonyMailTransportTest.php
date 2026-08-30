<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mail\Symfony;

use Fight\Common\Adapter\Mail\Symfony\SymfonyAttachment;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
use Fight\Common\Application\Mail\Exception\MailException;
use Fight\Common\Application\Mail\Message\Attachment;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Message\Priority;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Test\Common\TestCase\Mail\MailTransportConformanceTestCase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\RawMessage;
use Throwable;

#[CoversClass(SymfonyMailTransport::class)]
class SymfonyMailTransportTest extends MailTransportConformanceTestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function deliver(MailMessage $message): array
    {
        $mailer = new class implements MailerInterface {
            public ?Email $delivered = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                if (!$message instanceof Email) {
                    throw new RuntimeException('Expected a Symfony Email.');
                }

                $this->delivered = $message;
            }
        };

        new SymfonyMailTransport($mailer)->send($message);
        $email = $mailer->delivered;
        self::assertInstanceOf(Email::class, $email);

        return [
            'subject'         => $email->getSubject(),
            'from'            => $this->addresses($email->getFrom()),
            'to'              => $this->addresses($email->getTo()),
            'reply_to'        => $this->addresses($email->getReplyTo()),
            'cc'              => $this->addresses($email->getCc()),
            'bcc'             => $this->addresses($email->getBcc()),
            'html'            => $email->getHtmlBody(),
            'html_charset'    => $email->getHtmlCharset(),
            'text'            => $email->getTextBody(),
            'text_charset'    => $email->getTextCharset(),
            'sender'          => $email->getSender() instanceof Address ? $this->address($email->getSender()) : null,
            'return_path'     => $email->getReturnPath()?->getAddress(),
            'priority'        => $email->getPriority(),
            'timestamp'       => $email->getDate()?->getTimestamp(),
            'max_line_length' => $email->getHeaders()->getMaxLineLength(),
            'attachments'     => array_map($this->attachment(...), $email->getAttachments()),
        ];
    }

    protected function failingTransport(Throwable $failure): MailTransport
    {
        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->andThrow($failure);

        return new SymfonyMailTransport($mailer);
    }

    protected function attachmentFromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        return SymfonyAttachment::fromString($body, $fileName, $contentType, $embedId);
    }

    protected function attachmentFromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        return SymfonyAttachment::fromPath($path, $fileName, $contentType, $embedId);
    }

    /**
     * @param list<Address> $addresses
     *
     * @return list<array{address: string, name: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_map($this->address(...), $addresses);
    }

    /**
     * @return array{address: string, name: string}
     */
    private function address(Address $address): array
    {
        return ['address' => $address->getAddress(), 'name' => $address->getName()];
    }

    /**
     * @return array{body: string, file_name: ?string, content_type: string, disposition: ?string, id: ?string}
     */
    private function attachment(DataPart $attachment): array
    {
        return [
            'body'         => $attachment->getBody(),
            'file_name'    => $attachment->getFilename(),
            'content_type' => $attachment->getContentType(),
            'disposition'  => $attachment->getDisposition(),
            'id'           => $attachment->hasContentId() ? $attachment->getContentId() : null,
        ];
    }

    public function test_that_send_builds_and_sends_email(): void
    {
        $message = MailMessage::create()
            ->setSubject('Test')
            ->addFrom('from@example.com', 'From')
            ->addTo('to@example.com', 'To')
            ->addReplyTo('reply@example.com', 'Reply')
            ->addCc('cc@example.com', 'CC')
            ->addBcc('bcc@example.com', 'BCC')
            ->addContent('<p>html</p>', MailMessage::CONTENT_TYPE_HTML)
            ->addContent('text body', MailMessage::CONTENT_TYPE_PLAIN)
            ->setSender('sender@example.com', 'Sender')
            ->setReturnPath('bounce@example.com')
            ->setTimestamp(1700000000)
            ->setPriority(Priority::HIGH)
            ->addAttachment(SymfonyAttachment::fromString('body', 'file.txt', 'text/plain'));

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_without_subject_does_not_set_subject(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_without_sender_skips_sender(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_without_return_path_skips_return_path(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_without_timestamp_skips_date(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_without_content_skips_content(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_uses_overrides_when_provided(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer, [
            'to'  => ['override@example.com'],
            'cc'  => [],
            'bcc' => [],
        ]);
        $transport->send($message);
    }

    public function test_that_send_uses_override_string_csv(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer, [
            'to'  => 'a@test.com,b@test.com',
            'cc'  => 'cc@test.com',
            'bcc' => 'bcc@test.com',
        ]);
        $transport->send($message);
    }

    public function test_that_send_uses_override_with_all_types(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer, [
            'to'  => ['a@test.com'],
            'cc'  => ['cc@test.com'],
            'bcc' => ['bcc@test.com'],
        ]);
        $transport->send($message);
    }

    public function test_that_send_wraps_exception_in_mail_exception(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->andThrow(new RuntimeException('mail failed'));

        $transport = new SymfonyMailTransport($mailer);

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('mail failed');

        $transport->send($message);
    }

    public function test_that_send_handles_inline_attachment(): void
    {
        $attachment = SymfonyAttachment::fromString('inline-body', 'img.png', 'image/png', 'embed-1');

        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com')
            ->addContent('<img src="cid:embed-1">', MailMessage::CONTENT_TYPE_HTML)
            ->addAttachment($attachment);

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->withArgs(
            static function (Email $email): bool {
                self::assertStringContainsString('Content-ID:', $email->toString());

                return true;
            }
        );

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_handles_attachment_from_path(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'smt_');
        file_put_contents($file, 'file-body');
        $attachment = SymfonyAttachment::fromPath($file, 'doc.pdf', 'application/pdf');
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com')
            ->addAttachment($attachment);

        /** @var MailerInterface&MockInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);

        unlink($file);
    }
}
