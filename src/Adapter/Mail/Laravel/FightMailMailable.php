<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mail\Laravel;

use DateTime;
use DateTimeZone;
use Fight\Common\Application\Mail\Message\MailMessage;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Class FightMailMailable
 *
 * Reusable Laravel mailable that preserves the Fight message contract.
 */
final class FightMailMailable extends Mailable
{
    /**
     * Constructs FightMailMailable
     */
    public function __construct(private readonly MailMessage $message)
    {
    }

    /**
     * Builds the Laravel mailable
     */
    public function build(): static
    {
        return $this->html(' ')->withSymfonyMessage(function (Email $email): void {
            $subject = $this->message->getSubject();
            if ($subject !== null) {
                $email->subject($subject);
            }

            foreach ($this->message->getFrom() as $from) {
                $email->addFrom(new Address($from['address'], (string) $from['name']));
            }

            foreach ($this->message->getTo() as $to) {
                $email->addTo(new Address($to['address'], (string) $to['name']));
            }

            foreach ($this->message->getReplyTo() as $replyTo) {
                $email->addReplyTo(new Address($replyTo['address'], (string) $replyTo['name']));
            }

            foreach ($this->message->getCc() as $cc) {
                $email->addCc(new Address($cc['address'], (string) $cc['name']));
            }

            foreach ($this->message->getBcc() as $bcc) {
                $email->addBcc(new Address($bcc['address'], (string) $bcc['name']));
            }

            foreach ($this->message->getContent() as $content) {
                if ($content['content_type'] === MailMessage::CONTENT_TYPE_HTML) {
                    $email->html($content['content'], $content['charset']);
                } else {
                    $email->text($content['content'], $content['charset']);
                }
            }

            $sender = $this->message->getSender();
            if ($sender !== null) {
                $email->sender(new Address($sender['address'], (string) $sender['name']));
            }

            $returnPath = $this->message->getReturnPath();
            if ($returnPath !== null) {
                $email->returnPath(new Address($returnPath));
            }

            $email->priority($this->message->getPriority()->value);

            $timestamp = $this->message->getTimestamp();
            if ($timestamp !== null) {
                $email->date(DateTime::createFromFormat('U', (string) $timestamp, new DateTimeZone('UTC')));
            }

            $maxLineLength = $this->message->getMaxLineLength();
            if ($maxLineLength !== null) {
                $email->getHeaders()->setMaxLineLength($maxLineLength);
            }

            foreach ($this->message->getAttachments() as $attachment) {
                if ($attachment->getDisposition() === 'inline') {
                    if (!str_contains($attachment->getId(), '@')) {
                        $email->embed(
                            $attachment->getBody(),
                            $attachment->getId(),
                            $attachment->getContentType()
                        );

                        continue;
                    }

                    $email->addPart(
                        new DataPart(
                            $attachment->getBody(),
                            $attachment->getFileName(),
                            $attachment->getContentType()
                        )->asInline()->setContentId($attachment->getId())
                    );

                    continue;
                }

                $email->attach(
                    $attachment->getBody(),
                    $attachment->getFileName(),
                    $attachment->getContentType()
                );
            }
        });
    }
}
