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
 *
 * @deprecated since 1.2.0, use Fight\Common\Adapter\Messaging\Symfony\Serializer\SymfonyMessageSerializer
 */
final readonly class SymfonyMessageSerializer implements SerializerInterface
{
    private const string STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    private DomainSerializer $messageSerializer;

    /**
     * Constructs SymfonyMessageSerializer
     */
    public function __construct(DomainSerializer $serializer)
    {
        $this->messageSerializer = $serializer;
    }

    /**
     * Fails decoding when an unserialized class is unavailable
     *
     * @throws MessageDecodingFailedException
     */
    public static function handleUnserializeCallback(string $class): never
    {
        throw new MessageDecodingFailedException(sprintf('Message class "%s" not found during decoding', $class));
    }

    /**
     * Decodes a transport envelope
     *
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
            $message = $this->messageSerializer->deserialize($body);
        } catch (Throwable $throwable) {
            throw new MessageDecodingFailedException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        return new Envelope($message, $stamps);
    }

    /**
     * Encodes a transport envelope
     *
     * @inheritDoc
     */
    public function encode(Envelope $envelope): array
    {
        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        $message = $envelope->getMessage();

        if (!$message instanceof Serializable) {
            throw new RuntimeException('Message must implement Serializable');
        }

        $body = $this->messageSerializer->serialize($message);

        $headers = [];

        foreach ($envelope->all() as $class => $stamps) {
            $name = self::STAMP_HEADER_PREFIX.ClassName::short($class);
            $headers[$name] = addslashes(serialize($stamps));
        }

        return [
            'body'    => $body,
            'headers' => $headers
        ];
    }

    /**
     * Decodes serialized transport stamps from envelope headers
     *
     * @param array{body: string, headers?: array<string, string>} $encodedEnvelope
     *
     * @return array<int, object>
     *
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

            $decodedStamps = $this->safelyUnserialize(stripslashes((string) $value));

            foreach ($decodedStamps as $stamp) {
                $stamps[] = $stamp;
            }
        }

        return $stamps;
    }

    /**
     * Decodes serialized contents while converting PHP errors
     *
     * @throws MessageDecodingFailedException
     * @throws ErrorException
     */
    private function safelyUnserialize(string $contents): mixed
    {
        if ('' === $contents) {
            throw new MessageDecodingFailedException('Could not decode an empty message using PHP serialization.');
        }

        $previousUnserializeCallback = ini_set('unserialize_callback_func', self::class.'::handleUnserializeCallback');
        $previousErrorHandler = set_error_handler(static function (
            int $errorType,
            string $errorMessage,
            string $file,
            int $line,
            array $context = []
        ) use (&$previousErrorHandler): bool {
            if (__FILE__ === $file && !in_array($errorType, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                throw new ErrorException($errorMessage, 0, $errorType, $file, $line);
            }

            return $previousErrorHandler ? $previousErrorHandler(
                $errorType,
                $errorMessage,
                $file,
                $line,
                $context
            ) : false;
        });

        try {
            return unserialize($contents);
        } catch (Throwable $throwable) {
            if ($throwable instanceof MessageDecodingFailedException) {
                throw $throwable;
            }

            throw new MessageDecodingFailedException(
                'Could not decode Envelope: '.$throwable->getMessage(),
                0,
                $throwable
            );
        } finally {
            restore_error_handler();
            ini_set('unserialize_callback_func', $previousUnserializeCallback);
        }
    }
}
