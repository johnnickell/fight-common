<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mail\Symfony;

use Fight\Common\Application\Mail\Exception\MailException;
use Fight\Common\Application\Mail\Message\Attachment;

/**
 * Class SymfonyAttachment
 */
final readonly class SymfonyAttachment implements Attachment
{
    /**
     * Constructs SymfonyAttachment
     *
     * @internal
     */
    private function __construct(
        private mixed $body,
        private string $fileName,
        private string $contentType,
        private string $embedId,
        private bool $inline
    ) {
    }

    /**
     * Creates instance from content string
     *
     * @throws MailException When an error occurs
     */
    public static function fromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): SymfonyAttachment {
        $inline = true;

        if ($embedId === null) {
            $embedId = bin2hex(random_bytes(16));
            $inline = false;
        }

        return new self($body, $fileName, $contentType, $embedId, $inline);
    }

    /**
     * Creates instance from a local file path
     *
     * @throws MailException When an error occurs
     */
    public static function fromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): SymfonyAttachment {
        $inline = true;

        if ($embedId === null) {
            $embedId = bin2hex(random_bytes(16));
            $inline = false;
        }

        $handle = @fopen($path, 'r', false);
        if ($handle === false) {
            $message = sprintf('Unable to open path: %s', $path);
            throw new MailException($message);
        }

        return new self($handle, $fileName, $contentType, $embedId, $inline);
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->embedId;
    }

    /**
     * @inheritDoc
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * @inheritDoc
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @inheritDoc
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @inheritDoc
     */
    public function getDisposition(): string
    {
        if ($this->inline) {
            return 'inline';
        }

        return 'attachment';
    }

    /**
     * @inheritDoc
     */
    public function embed(): string
    {
        return sprintf('cid:%s', $this->embedId);
    }
}
