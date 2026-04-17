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
    /**
     * Constructs MailService
     */
    public function __construct(private MailTransport $transport, private MailFactory $factory)
    {
    }

    /**
     * @inheritDoc
     */
    public function send(MailMessage $message): void
    {
        $this->transport->send($message);
    }

    /**
     * @inheritDoc
     */
    public function createMessage(): MailMessage
    {
        return $this->factory->createMessage();
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
        return $this->factory->createAttachmentFromString(
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
        return $this->factory->createAttachmentFromPath(
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
        return $this->factory->generateEmbedId();
    }
}
