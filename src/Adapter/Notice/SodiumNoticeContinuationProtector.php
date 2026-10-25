<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Notice;

use Fight\Common\Application\Notice\Service\NoticeContinuationProtector;
use Fight\Common\Domain\Exception\DomainException;
use Throwable;

/**
 * Class SodiumNoticeContinuationProtector
 */
final readonly class SodiumNoticeContinuationProtector implements NoticeContinuationProtector
{
    private const string AAD = 'fight.notice.recovery.v1';
    private const int PADDED_BYTES = 1024;

    /**
     * @var array<string, string>
     */
    private array $keys;

    /**
     * Constructs SodiumNoticeContinuationProtector
     *
     * Dedicated 32-byte keys must not be reused for other protocols or stores
     *
     * @phpstan-param array<string, string> $keys
     */
    public function __construct(array $keys, private string $activeKey)
    {
        if (!extension_loaded('sodium') || count($keys) < 1 || count($keys) > 8 || !isset($keys[$activeKey])) {
            throw new DomainException('Invalid notice continuation configuration.');
        }

        foreach ($keys as $id => $key) {
            if (preg_match('/\A[a-zA-Z][a-zA-Z0-9_-]{7}\z/D', $id) !== 1 || strlen($key) !== 32) {
                throw new DomainException('Invalid notice continuation configuration.');
            }
        }

        $this->keys = array_map(static fn (string $key): string => $key, $keys);
    }

    /**
     * @inheritDoc
     */
    public function seal(string $plaintext): string
    {
        if (strlen($plaintext) > self::PADDED_BYTES - 1 || str_contains($plaintext, "\0")) {
            throw new DomainException('Invalid notice continuation.');
        }

        $nonce = random_bytes(24);
        $payload = str_pad($plaintext, self::PADDED_BYTES, "\0");
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $payload,
            self::AAD.$this->activeKey,
            $nonce,
            $this->keys[$this->activeKey]
        );

        return 'v1.'.$this->activeKey.'.'.base64_encode($nonce.$cipher);
    }

    /**
     * @inheritDoc
     */
    public function open(string $token): string
    {
        try {
            if (
                strlen($token) !== 1432
                || preg_match('/\Av1\.([a-zA-Z][a-zA-Z0-9_-]{7})\.(.+)\z/D', $token, $parts) !== 1
            ) {
                throw new DomainException('Invalid notice continuation.');
            }

            $bytes = base64_decode($parts[2], true);
            if ($bytes === false || base64_encode($bytes) !== $parts[2] || !isset($this->keys[$parts[1]])) {
                throw new DomainException('Invalid notice continuation.');
            }

            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                substr($bytes, 24),
                self::AAD.$parts[1],
                substr($bytes, 0, 24),
                $this->keys[$parts[1]]
            );
            if ($plain === false || strlen($plain) !== self::PADDED_BYTES) {
                throw new DomainException('Invalid notice continuation.');
            }

            return rtrim($plain, "\0");
        } catch (Throwable $throwable) {
            throw new DomainException('Invalid notice continuation.', previous: $throwable);
        }
    }
}
