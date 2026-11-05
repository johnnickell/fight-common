<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mail;

use Fight\Common\Application\Mail\Message\Attachment;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;

/**
 * Class MailService
 */
final readonly class MailService implements MailTransport, MailFactory
{
    private MailTransport $mailTransport;
    private MailFactory $mailFactory;

    /**
     * Constructs MailService
     */
    public function __construct(MailTransport $transport, MailFactory $factory)
    {
        $this->mailTransport = $transport;
        $this->mailFactory = $factory;
    }

    /**
     * @inheritDoc
     */
    public function send(MailMessage $message): void
    {
        $this->mailTransport->send($message);
    }

    /**
     * @inheritDoc
     */
    public function createMessage(): MailMessage
    {
        return $this->mailFactory->createMessage();
    }

    /**
     * @inheritDoc
     */
    public function createAttachmentFromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        return $this->mailFactory->createAttachmentFromString(
            $body,
            $fileName,
            $contentType,
            $embedId
        );
    }

    /**
     * @inheritDoc
     */
    public function createAttachmentFromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        return $this->mailFactory->createAttachmentFromPath(
            $path,
            $fileName,
            $contentType,
            $embedId
        );
    }

    /**
     * @inheritDoc
     */
    public function generateEmbedId(): string
    {
        return $this->mailFactory->generateEmbedId();
    }
}
