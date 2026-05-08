<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Mail\Message;

use Fight\Common\Application\Mail\Message\Attachment;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Message\Priority;
use Fight\Test\Common\TestCase\UnitTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MailMessage::class)]
class MailMessageTest extends UnitTestCase
{
    public function test_that_construction_sets_default_state(): void
    {
        $message = new MailMessage();

        self::assertNull($message->getSubject());
        self::assertSame([], $message->getFrom());
        self::assertSame([], $message->getTo());
        self::assertSame([], $message->getReplyTo());
        self::assertSame([], $message->getCc());
        self::assertSame([], $message->getBcc());
        self::assertSame([], $message->getContent());
        self::assertSame([], $message->getAttachments());
        self::assertNull($message->getSender());
        self::assertNull($message->getReturnPath());
        self::assertSame(MailMessage::DEFAULT_CHARSET, $message->getCharset());
        self::assertSame(Priority::NORMAL, $message->getPriority());
        self::assertNull($message->getTimestamp());
        self::assertNull($message->getMaxLineLength());
    }

    public function test_that_create_returns_a_mail_message_instance(): void
    {
        $message = MailMessage::create();

        self::assertInstanceOf(MailMessage::class, $message);
    }

    public function test_that_set_subject_sets_subject_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->setSubject('Hello World');

        self::assertSame($message, $result);
        self::assertSame('Hello World', $message->getSubject());
    }

    public function test_that_add_from_appends_address_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->addFrom('sender@example.com', 'Sender Name');

        self::assertSame($message, $result);
        self::assertSame([['address' => 'sender@example.com', 'name' => 'Sender Name']], $message->getFrom());
    }

    public function test_that_add_to_appends_address_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->addTo('recipient@example.com', 'Recipient');

        self::assertSame($message, $result);
        self::assertSame([['address' => 'recipient@example.com', 'name' => 'Recipient']], $message->getTo());
    }

    public function test_that_add_reply_to_appends_address_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->addReplyTo('reply@example.com');

        self::assertSame($message, $result);
        self::assertSame([['address' => 'reply@example.com', 'name' => null]], $message->getReplyTo());
    }

    public function test_that_add_cc_appends_address_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->addCc('cc@example.com');

        self::assertSame($message, $result);
        self::assertSame([['address' => 'cc@example.com', 'name' => null]], $message->getCc());
    }

    public function test_that_add_bcc_appends_address_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->addBcc('bcc@example.com');

        self::assertSame($message, $result);
        self::assertSame([['address' => 'bcc@example.com', 'name' => null]], $message->getBcc());
    }

    public function test_that_add_content_uses_explicit_charset_when_provided(): void
    {
        $message = new MailMessage();

        $message->addContent('<p>Hello</p>', MailMessage::CONTENT_TYPE_HTML, 'iso-8859-1');

        self::assertSame([
            ['content' => '<p>Hello</p>', 'content_type' => 'text/html', 'charset' => 'iso-8859-1']
        ], $message->getContent());
    }

    public function test_that_add_content_falls_back_to_message_charset_when_none_provided(): void
    {
        $message = new MailMessage();

        $message->addContent('Hello', MailMessage::CONTENT_TYPE_PLAIN);

        self::assertSame([
            ['content' => 'Hello', 'content_type' => 'text/plain', 'charset' => MailMessage::DEFAULT_CHARSET]
        ], $message->getContent());
    }

    public function test_that_add_content_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->addContent('Hello', MailMessage::CONTENT_TYPE_PLAIN);

        self::assertSame($message, $result);
    }

    public function test_that_set_sender_sets_sender_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->setSender('sender@example.com', 'Sender');

        self::assertSame($message, $result);
        self::assertSame(['address' => 'sender@example.com', 'name' => 'Sender'], $message->getSender());
    }

    public function test_that_set_return_path_sets_return_path_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->setReturnPath('bounces@example.com');

        self::assertSame($message, $result);
        self::assertSame('bounces@example.com', $message->getReturnPath());
    }

    public function test_that_set_charset_sets_charset_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->setCharset('iso-8859-1');

        self::assertSame($message, $result);
        self::assertSame('iso-8859-1', $message->getCharset());
    }

    public function test_that_set_priority_sets_priority_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->setPriority(Priority::HIGH);

        self::assertSame($message, $result);
        self::assertSame(Priority::HIGH, $message->getPriority());
    }

    public function test_that_set_timestamp_sets_timestamp_and_returns_same_instance(): void
    {
        $message = new MailMessage();

        $result = $message->setTimestamp(1700000000);

        self::assertSame($message, $result);
        self::assertSame(1700000000, $message->getTimestamp());
    }

    public function test_that_set_max_line_length_stores_value_within_limit(): void
    {
        $message = new MailMessage();

        $result = $message->setMaxLineLength(78);

        self::assertSame($message, $result);
        self::assertSame(78, $message->getMaxLineLength());
    }

    public function test_that_set_max_line_length_clamps_to_998_when_exceeded(): void
    {
        $message = new MailMessage();

        $message->setMaxLineLength(1200);

        self::assertSame(998, $message->getMaxLineLength());
    }

    public function test_that_set_max_line_length_applies_abs_to_negative_values(): void
    {
        $message = new MailMessage();

        $message->setMaxLineLength(-80);

        self::assertSame(80, $message->getMaxLineLength());
    }

    public function test_that_add_attachment_appends_attachment_and_returns_same_instance(): void
    {
        $message = new MailMessage();
        /** @var MockInterface|Attachment $attachment */
        $attachment = $this->mock(Attachment::class);

        $result = $message->addAttachment($attachment);

        self::assertSame($message, $result);
        self::assertSame([$attachment], $message->getAttachments());
    }
}
