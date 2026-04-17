<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mail\Message;

/**
 * Class MailMessage
 */
final class MailMessage
{
    public const string DEFAULT_CHARSET = 'utf-8';
    public const string CONTENT_TYPE_HTML = 'text/html';
    public const string CONTENT_TYPE_PLAIN = 'text/plain';

    private ?string $subject = null;
    private array $from = [];
    private array $to = [];
    private array $replyTo = [];
    private array $cc = [];
    private array $bcc = [];
    private array $content = [];
    private ?array $sender = null;
    private ?string $returnPath = null;
    private string $charset = self::DEFAULT_CHARSET;
    private Priority $priority;
    private ?int $timestamp = null;
    private ?int $maxLineLength = null;
    private array $attachments = [];

    /**
     * Constructs MailMessage
     */
    public function __construct()
    {
        $this->setPriority(Priority::NORMAL);
    }

    /**
     * Creates instance
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * Sets the subject
     */
    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Retrieves the subject
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * Adds a From address
     */
    public function addFrom(string $address, ?string $name = null): static
    {
        $this->from[] = [
            'address' => $address,
            'name'    => $name
        ];

        return $this;
    }

    /**
     * Retrieves From addresses
     *
     * Each address is an array with the following keys:
     *
     * * address => string
     * * name    => string|null
     */
    public function getFrom(): array
    {
        return $this->from;
    }

    /**
     * Adds a To address
     */
    public function addTo(string $address, ?string $name = null): static
    {
        $this->to[] = [
            'address' => $address,
            'name'    => $name
        ];

        return $this;
    }

    /**
     * Retrieves To addresses
     *
     * Each address is an array with the following keys:
     *
     * * address => string
     * * name    => string|null
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * Adds a Reply-To address
     */
    public function addReplyTo(string $address, ?string $name = null): static
    {
        $this->replyTo[] = [
            'address' => $address,
            'name'    => $name
        ];

        return $this;
    }

    /**
     * Retrieves Reply-To addresses
     *
     * Each address is an array with the following keys:
     *
     * * address => string
     * * name    => string|null
     */
    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    /**
     * Adds a CC address
     */
    public function addCc(string $address, ?string $name = null): static
    {
        $this->cc[] = [
            'address' => $address,
            'name'    => $name
        ];

        return $this;
    }

    /**
     * Retrieves CC addresses
     *
     * Each address is an array with the following keys:
     *
     * * address => string
     * * name    => string|null
     */
    public function getCc(): array
    {
        return $this->cc;
    }

    /**
     * Adds a BCC address
     */
    public function addBcc(string $address, ?string $name = null): static
    {
        $this->bcc[] = [
            'address' => $address,
            'name'    => $name
        ];

        return $this;
    }

    /**
     * Retrieves BCC addresses
     *
     * Each address is an array with the following keys:
     *
     * * address => string
     * * name    => string|null
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    /**
     * Adds a content part
     *
     * Example content type: "text/plain" or "text/html"
     */
    public function addContent(string $content, string $contentType, ?string $charset = null): static
    {
        $charset = $charset ?: $this->getCharset();

        $this->content[] = [
            'content'      => $content,
            'content_type' => $contentType,
            'charset'      => $charset
        ];

        return $this;
    }

    /**
     * Retrieves the content parts
     *
     * Each content part is an array with the following keys:
     *
     * * content      => string
     * * content_type => string
     * * charset      => string
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * Sets the sender
     *
     * If set, this has a higher precedence than a from address.
     */
    public function setSender(string $address, ?string $name = null): static
    {
        $this->sender = [
            'address' => $address,
            'name'    => $name
        ];

        return $this;
    }

    /**
     * Retrieves the sender
     *
     * The sender is an array with the following keys:
     *
     * * address => string
     * * name    => string|null
     */
    public function getSender(): ?array
    {
        return $this->sender;
    }

    /**
     * Sets the return path
     *
     * If set, this determines where bounces should go.
     */
    public function setReturnPath(string $returnPath): static
    {
        $this->returnPath = $returnPath;

        return $this;
    }

    /**
     * Retrieves the return path
     */
    public function getReturnPath(): ?string
    {
        return $this->returnPath;
    }

    /**
     * Sets the character set
     */
    public function setCharset(string $charset): static
    {
        $this->charset = $charset;

        return $this;
    }

    /**
     * Retrieves the character set
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * Sets the priority
     */
    public function setPriority(Priority $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Retrieves the priority
     */
    public function getPriority(): Priority
    {
        return $this->priority;
    }

    /**
     * Sets the UNIX timestamp
     */
    public function setTimestamp(int $timestamp): static
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Retrieves the UNIX timestamp
     */
    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    /**
     * Sets the max line length
     *
     * Max line length should be a positive integer less than 998.
     */
    public function setMaxLineLength(int $maxLineLength): static
    {
        // Each line of characters MUST be no more than 998 characters,
        // and SHOULD be no more than 78 characters, excluding the CRLF.
        // https://tools.ietf.org/html/rfc5322#section-2.1.1
        $maxLineLength = (int) abs($maxLineLength);

        if ($maxLineLength > 998) {
            $maxLineLength = 998;
        }

        $this->maxLineLength = $maxLineLength;

        return $this;
    }

    /**
     * Retrieves the max line length
     */
    public function getMaxLineLength(): ?int
    {
        return $this->maxLineLength;
    }

    /**
     * Adds an attachment
     */
    public function addAttachment(Attachment $attachment): static
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * Retrieves the file attachments
     *
     * @return array<Attachment>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }
}
