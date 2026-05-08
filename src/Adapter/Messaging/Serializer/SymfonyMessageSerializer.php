<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Messaging\Serializer;

use ErrorException;
use Fight\Common\Domain\Serialization\Serializable;
use Fight\Common\Domain\Serialization\Serializer as DomainSerializer;
use Fight\Common\Domain\Utility\ClassName;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;

use function addslashes;
use function ini_set;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_starts_with;
use function stripslashes;

/**
 * Class SymfonyMessageSerializer
 */
final readonly class SymfonyMessageSerializer implements SerializerInterface
{
    private const string STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    /**
     * Constructs SymfonyMessageSerializer
     */
    public function __construct(private DomainSerializer $serializer)
    {
    }

    /**
     * @inheritDoc
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body'])) {
            throw new MessageDecodingFailedException(
                'Encoded envelope should have at least a "body" and some "headers"'
            );
        }

        $stamps = $this->decodeStamps($encodedEnvelope);
        $body = $encodedEnvelope['body'];

        try {
            $message = $this->serializer->deserialize($body);
        } catch (Throwable $e) {
            throw new MessageDecodingFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return new Envelope($message, $stamps);
    }

    /**
     * @inheritDoc
     */
    public function encode(Envelope $envelope): array
    {
        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        $message = $envelope->getMessage();

        if (!$message instanceof Serializable) {
            throw new RuntimeException('Message must implement Serializable');
        }

        $body = $this->serializer->serialize($message);

        $headers = [];

        foreach ($envelope->all() as $class => $stamps) {
            $name = self::STAMP_HEADER_PREFIX . ClassName::short($class);
            $headers[$name] = addslashes(serialize($stamps));
        }

        return [
            'body'    => $body,
            'headers' => $headers
        ];
    }

    /**
     * @throws MessageDecodingFailedException|ErrorException
     */
    private function decodeStamps(array $encodedEnvelope): array
    {
        $stamps = [];
        $headers = $encodedEnvelope['headers'] ?? [];

        foreach ($headers as $name => $value) {
            if (!str_starts_with((string) $name, self::STAMP_HEADER_PREFIX)) {
                continue;
            }

            $data = $this->safelyUnserialize(stripslashes((string) $value));

            foreach ($data as $stamp) {
                $stamps[] = $stamp;
            }
        }

        return $stamps;
    }

    /**
     * @throws MessageDecodingFailedException|ErrorException
     */
    private function safelyUnserialize(string $contents): mixed
    {
        if ('' === $contents) {
            throw new MessageDecodingFailedException('Could not decode an empty message using PHP serialization.');
        }

        $prevUnserializeHandler = ini_set('unserialize_callback_func', self::class . '::handleUnserializeCallback');
        $prevErrorHandler = set_error_handler(static function (
            int $type,
            string $msg,
            string $file,
            int $line,
            array $context = []
        ) use (&$prevErrorHandler): bool {
            if (__FILE__ === $file && !in_array($type, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                throw new ErrorException($msg, 0, $type, $file, $line);
            }

            return $prevErrorHandler ? $prevErrorHandler($type, $msg, $file, $line, $context) : false;
        });

        try {
            return unserialize($contents);
        } catch (Throwable $e) {
            if ($e instanceof MessageDecodingFailedException) {
                throw $e;
            }

            throw new MessageDecodingFailedException('Could not decode Envelope: ' . $e->getMessage(), 0, $e);
        } finally {
            restore_error_handler();
            ini_set('unserialize_callback_func', $prevUnserializeHandler);
        }
    }

    /**
     * @throws MessageDecodingFailedException
     */
    public static function handleUnserializeCallback(string $class): never
    {
        throw new MessageDecodingFailedException(sprintf('Message class "%s" not found during decoding', $class));
    }
}
