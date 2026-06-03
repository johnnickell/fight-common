<?php

declare(strict_types=1);

namespace Fight\Common\Application\Sms\Message;

use Fight\Common\Domain\Value\Internet\Url;

/**
 * Class SmsMessage
 */
final class SmsMessage
{
    private ?string $body = null;

    /** @var array<int, Url> */
    private array $media = [];

    /**
     * Constructs SmsMessage
     */
    public function __construct(private readonly string $to, private readonly string $from)
    {
    }

    /**
     * Creates instance
     */
    public static function create(string $to, string $from): static
    {
        return new static($to, $from);
    }

    /**
     * Retrieves the To phone number
     */
    public function getTo(): string
    {
        return $this->to;
    }

    /**
     * Retrieves the From phone number
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * Sets the message body
     */
    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Retrieves the message body
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Adds a media URL
     */
    public function addMedia(Url $url): static
    {
        $this->media[] = $url;

        return $this;
    }

    /**
     * Retrieves the media URLs
     *
     * @return array<int, Url>
     */
    public function getMedia(): array
    {
        return $this->media;
    }
}
