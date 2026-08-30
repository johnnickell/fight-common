<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mail\Laravel;

use Fight\Common\Adapter\Mail\Laravel\FightMailMailable;
use Fight\Common\Adapter\Mail\Laravel\LaravelMailFactory;
use Fight\Common\Adapter\Mail\Laravel\LaravelMailTransport;
use Fight\Common\Application\Mail\Message\Attachment;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Test\Common\TestCase\Mail\MailTransportConformanceTestCase;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Throwable;

#[CoversClass(LaravelMailTransport::class)]
#[CoversClass(LaravelMailFactory::class)]
#[CoversClass(FightMailMailable::class)]
final class LaravelMailTransportTest extends MailTransportConformanceTestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function deliver(MailMessage $message): array
    {
        $email = null;

        /** @var Mailer&MockInterface $mailer */
        $mailer = $this->mock(Mailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (mixed $mailable) use (&$email): bool {
                if (!$mailable instanceof Mailable) {
                    return false;
                }

                $mailable->build();
                $email = new Email();

                foreach ($mailable->callbacks as $callback) {
                    $callback($email);
                }

                return true;
            }))
            ->andReturnNull();

        new LaravelMailTransport($mailer)->send($message);

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
        /** @var Mailer&MockInterface $mailer */
        $mailer = $this->mock(Mailer::class);
        $mailer->shouldReceive('send')->once()->andThrow($failure);

        return new LaravelMailTransport($mailer);
    }

    protected function attachmentFromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        return (new LaravelMailFactory())->createAttachmentFromString($body, $fileName, $contentType, $embedId);
    }

    protected function attachmentFromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        return (new LaravelMailFactory())->createAttachmentFromPath($path, $fileName, $contentType, $embedId);
    }

    public function test_that_send_leaves_optional_message_values_unset_when_they_are_not_provided(): void
    {
        $delivered = $this->deliver(MailMessage::create());

        self::assertNull($delivered['subject']);
        self::assertSame([], $delivered['from']);
        self::assertSame([], $delivered['to']);
        self::assertSame([], $delivered['reply_to']);
        self::assertSame([], $delivered['cc']);
        self::assertSame([], $delivered['bcc']);
        self::assertNull($delivered['html']);
        self::assertNull($delivered['text']);
        self::assertNull($delivered['sender']);
        self::assertNull($delivered['return_path']);
        self::assertNull($delivered['timestamp']);
        self::assertSame([], $delivered['attachments']);
    }

    public function test_that_factory_creates_message_and_embed_id(): void
    {
        $factory = new LaravelMailFactory();

        self::assertInstanceOf(MailMessage::class, $factory->createMessage());
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $factory->generateEmbedId());
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
}
