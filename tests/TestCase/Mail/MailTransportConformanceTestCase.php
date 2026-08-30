<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Mail;

use Fight\Common\Application\Mail\Exception\MailException;
use Fight\Common\Application\Mail\Message\Attachment;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Message\Priority;
use Fight\Common\Application\Mail\Transport\MailTransport;
use Fight\Test\Common\TestCase\UnitTestCase;
use RuntimeException;
use Throwable;

/**
 * Defines common observable mail-transport behavior for framework adapters.
 */
abstract class MailTransportConformanceTestCase extends UnitTestCase
{
    /**
     * Delivers one message and returns its normalized downstream representation.
     *
     * @return array<string, mixed>
     */
    abstract protected function deliver(MailMessage $message): array;

    /**
     * Returns a transport whose native mailer throws the supplied failure.
     */
    abstract protected function failingTransport(Throwable $failure): MailTransport;

    public function test_that_send_preserves_the_complete_message_envelope_and_content(): void
    {
        $delivered = $this->deliver(
            MailMessage::create()
                ->setSubject('Conformance subject')
                ->addFrom('from@example.com', 'From Name')
                ->addTo('to@example.com', 'To Name')
                ->addReplyTo('reply@example.com', 'Reply Name')
                ->addCc('cc@example.com', 'Cc Name')
                ->addBcc('bcc@example.com', 'Bcc Name')
                ->addContent('<p>HTML body</p>', MailMessage::CONTENT_TYPE_HTML, 'iso-8859-1')
                ->addContent('Plain body', MailMessage::CONTENT_TYPE_PLAIN, 'us-ascii')
                ->setSender('sender@example.com', 'Sender Name')
                ->setReturnPath('bounce@example.com')
                ->setPriority(Priority::HIGH)
                ->setTimestamp(1_700_000_000)
                ->setMaxLineLength(72)
        );

        self::assertSame('Conformance subject', $delivered['subject']);
        self::assertSame([['address' => 'from@example.com', 'name' => 'From Name']], $delivered['from']);
        self::assertSame([['address' => 'to@example.com', 'name' => 'To Name']], $delivered['to']);
        self::assertSame([['address' => 'reply@example.com', 'name' => 'Reply Name']], $delivered['reply_to']);
        self::assertSame([['address' => 'cc@example.com', 'name' => 'Cc Name']], $delivered['cc']);
        self::assertSame([['address' => 'bcc@example.com', 'name' => 'Bcc Name']], $delivered['bcc']);
        self::assertSame('<p>HTML body</p>', $delivered['html']);
        self::assertSame('iso-8859-1', $delivered['html_charset']);
        self::assertSame('Plain body', $delivered['text']);
        self::assertSame('us-ascii', $delivered['text_charset']);
        self::assertSame(['address' => 'sender@example.com', 'name' => 'Sender Name'], $delivered['sender']);
        self::assertSame('bounce@example.com', $delivered['return_path']);
        self::assertSame(Priority::HIGH->value, $delivered['priority']);
        self::assertSame(1_700_000_000, $delivered['timestamp']);
        self::assertSame(72, $delivered['max_line_length']);
    }

    public function test_that_send_preserves_attached_and_embedded_files(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mail-conformance-');
        self::assertIsString($path);
        file_put_contents($path, 'path body');

        $message = MailMessage::create()
            ->addFrom('from@example.com')
            ->addTo('to@example.com')
            ->addContent('Body', MailMessage::CONTENT_TYPE_PLAIN)
            ->addAttachment($this->attachmentFromString('inline body', 'inline.txt', 'text/plain', 'asset@example'))
            ->addAttachment($this->attachmentFromPath($path, 'path.txt', 'text/plain'));

        try {
            $delivered = $this->deliver($message);
        } finally {
            unlink($path);
        }

        self::assertSame(
            [
                [
                    'body'        => 'inline body',
                    'file_name'   => 'inline.txt',
                    'content_type' => 'text/plain',
                    'disposition' => 'inline',
                    'id'          => 'asset@example',
                ],
                [
                    'body'        => 'path body',
                    'file_name'   => 'path.txt',
                    'content_type' => 'text/plain',
                    'disposition' => 'attachment',
                    'id'          => null,
                ],
            ],
            $delivered['attachments']
        );
    }

    public function test_that_send_accepts_a_legacy_embed_id_without_an_at_sign(): void
    {
        $delivered = $this->deliver(
            MailMessage::create()
                ->addFrom('from@example.com')
                ->addTo('to@example.com')
                ->addContent('Body', MailMessage::CONTENT_TYPE_PLAIN)
                ->addAttachment($this->attachmentFromString('inline body', 'inline.txt', 'text/plain', 'my-embed'))
        );

        self::assertCount(1, $delivered['attachments']);
        self::assertSame('inline body', $delivered['attachments'][0]['body']);
        self::assertSame('inline', $delivered['attachments'][0]['disposition']);
    }

    public function test_that_send_translates_native_failures_without_losing_the_original_failure(): void
    {
        $failure = new RuntimeException('native mail failure');

        try {
            $this->failingTransport($failure)->send(MailMessage::create());
            self::fail('Expected the native mail failure to be translated.');
        } catch (MailException $mailException) {
            self::assertSame('native mail failure', $mailException->getMessage());
            self::assertSame($failure, $mailException->getPrevious());
        }
    }

    abstract protected function attachmentFromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment;

    abstract protected function attachmentFromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment;
}
