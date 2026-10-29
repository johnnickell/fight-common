<?php

declare(strict_types=1);

namespace Fight\Common\Domain\Mcp\Notice;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class NoticeRelationships
 */
final readonly class NoticeRelationships
{
    /**
     * Constructs NoticeRelationships
     *
     * @phpstan-param list<string>|null $recipients
     */
    private function __construct(
        private ?string $subject,
        private ?array $recipients,
        private ?string $author,
        private ?string $command,
        private ?string $event,
        private ?string $action
    ) {
    }

    /**
     * Creates explicit independent relationships with null unknowns and empty broadcast recipients
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['action', 'author', 'command', 'event', 'recipients', 'subject']) {
            throw new DomainException('Invalid notice relationships.');
        }

        foreach (['subject', 'author', 'command', 'event', 'action'] as $key) {
            if ($data[$key] !== null) {
                Notice::reference($data[$key]);
            }
        }

        $recipients = $data['recipients'];
        if ($recipients !== null) {
            if (
                !is_array($recipients) || !array_is_list($recipients) || count($recipients) > 32
            ) {
                throw new DomainException('Invalid notice recipients.');
            }

            $snapshot = [];
            foreach ($recipients as $recipient) {
                $snapshot[] = Notice::reference($recipient);
            }

            sort($snapshot, SORT_STRING);
            if (count(array_unique($snapshot)) !== count($snapshot)) {
                throw new DomainException('Duplicate notice recipient.');
            }

            $recipients = $snapshot;
        }

        return new self(
            $data['subject'],
            $recipients,
            $data['author'],
            $data['command'],
            $data['event'],
            $data['action']
        );
    }

    /**
     * Returns the explicit relationships without inferring an audience or authority
     *
     * @return array{subject: ?string, recipients: ?list<string>, author: ?string,
     *     command: ?string, event: ?string, action: ?string}
     */
    public function toArray(): array
    {
        return [
            'subject'    => $this->subject,
            'recipients' => $this->recipients,
            'author'     => $this->author,
            'command'    => $this->command,
            'event'      => $this->event,
            'action'     => $this->action
        ];
    }
}
