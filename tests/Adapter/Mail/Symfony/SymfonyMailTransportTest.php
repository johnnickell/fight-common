<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mail\Symfony;

use DateTime;
use DateTimeZone;
use Fight\Common\Adapter\Mail\Symfony\SymfonyAttachment;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
use Fight\Common\Application\Mail\Exception\MailException;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Message\Priority;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[CoversClass(SymfonyMailTransport::class)]
class SymfonyMailTransportTest extends UnitTestCase
{
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

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_without_subject_does_not_set_subject(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_without_sender_skips_sender(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_without_return_path_skips_return_path(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_without_timestamp_skips_date(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_without_content_skips_content(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);
    }

    public function test_that_send_uses_overrides_when_provided(): void
    {
        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com');

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

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

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

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

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

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

        /** @var MockInterface|MailerInterface $mailer */
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
            ->addAttachment($attachment);

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

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

        /** @var MockInterface|MailerInterface $mailer */
        $mailer = $this->mock(MailerInterface::class);
        $mailer->shouldReceive('send')->once()->with(\Mockery::type(Email::class));

        $transport = new SymfonyMailTransport($mailer);
        $transport->send($message);

        unlink($file);
    }
}
