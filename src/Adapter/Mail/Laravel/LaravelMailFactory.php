<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mail\Laravel;

use Fight\Common\Adapter\Mail\Symfony\SymfonyAttachment;
use Fight\Common\Application\Mail\Message\Attachment;
use Fight\Common\Application\Mail\Message\MailFactory;
use Fight\Common\Application\Mail\Message\MailMessage;

/**
 * Class LaravelMailFactory
 *
 * Creates Fight mail values for Laravel's Symfony-backed mailer.
 */
final class LaravelMailFactory implements MailFactory
{
    /**
     * Creates a Fight mail message
     */
    public function createMessage(): MailMessage
    {
        return MailMessage::create();
    }

    /**
     * Creates an attachment from its string body
     */
    public function createAttachmentFromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        return SymfonyAttachment::fromString($body, $fileName, $contentType, $embedId);
    }

    /**
     * Creates an attachment from a file path
     */
    public function createAttachmentFromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment {
        return SymfonyAttachment::fromPath($path, $fileName, $contentType, $embedId);
    }

    /**
     * Generates an embed identifier
     */
    public function generateEmbedId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
