<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Mcp\Sodium;

use Fight\Common\Application\Mcp\Tool\Interaction\McpStateProtector;
use Fight\Common\Domain\Exception\DomainException;
use SensitiveParameter;

/**
 * Class SodiumMcpStateProtector
 */
final readonly class SodiumMcpStateProtector implements McpStateProtector
{
    /**
     * Constructs SodiumMcpStateProtector
     *
     * Supply dedicated random 32-byte keys, never authentication credentials or nonce keys.
     * At most four positive integer key versions are accepted, including the active version.
     *
     * @param integer           $activeVersion
     * @param array<array-key, string> $keys
     */
    public function __construct(private int $activeVersion, #[SensitiveParameter] private array $keys)
    {
        if (count($keys) > 4 || !isset($keys[$activeVersion])) {
            throw new DomainException('An active key and at most four validation keys are required.');
        }

        foreach ($keys as $version => $key) {
            if (!is_int($version) || $version < 1 || $version > 999999999 || strlen($key) !== 32) {
                throw new DomainException('State protection requires positive key versions and 32-byte keys.');
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function seal(#[SensitiveParameter] string $plaintext): string
    {
        $prefix = '1.'.$this->activeVersion.'.';
        // The fixed AEAD overhead is a 24-byte nonce and a 16-byte authentication tag.
        if (strlen($prefix) + (int) ceil((strlen($plaintext) + 40) * 4 / 3) > self::MAX_STATE_BYTES) {
            throw new DomainException('Interaction state exceeds the supported byte limit.');
        }

        $nonce = random_bytes(24);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            'fight.common.mcp.ordinary/'.$prefix,
            $nonce,
            $this->keys[$this->activeVersion]
        );

        return $prefix.rtrim(strtr(base64_encode($nonce.$ciphertext), '+/', '-_'), '=');
    }

    /**
     * @inheritDoc
     */
    public function open(#[SensitiveParameter] string $token): string
    {
        if (
            strlen($token) > self::MAX_STATE_BYTES
            || preg_match('/\A(1\.([1-9][0-9]{0,8})\.)([A-Za-z0-9_-]+)\z/D', $token, $parts) !== 1
            || !isset($this->keys[(int) $parts[2]])
        ) {
            throw new DomainException('Invalid interaction state.');
        }

        $bytes = base64_decode(strtr($parts[3], '-_', '+/'), true);
        if (
            $bytes === false || strlen($bytes) < 40
            || rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=') !== $parts[3]
        ) {
            throw new DomainException('Invalid interaction state.');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($bytes, 24),
            'fight.common.mcp.ordinary/'.$parts[1],
            substr($bytes, 0, 24),
            $this->keys[(int) $parts[2]]
        );
        if ($plaintext === false) {
            throw new DomainException('Invalid interaction state.');
        }

        return $plaintext;
    }
}
