<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Notice;

use Fight\Common\Adapter\Mcp\Notice\SodiumNoticeContinuationProtector;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SodiumNoticeContinuationProtector::class)]
final class SodiumNoticeContinuationProtectorTest extends UnitTestCase
{
    public function test_that_fixed_length_authenticated_confidential_tokens_are_randomized_and_support_bounded_key_rotation(): void
    {
        $key = str_repeat('k', 32); $codec = new SodiumNoticeContinuationProtector(['testkey1' => $key], 'testkey1');
        $plain = 'CONCEALED scope caller highwater'; $first = $codec->seal($plain);
        self::assertSame(1432, strlen($first)); self::assertNotSame($first, $codec->seal($plain));
        self::assertStringNotContainsString($plain, base64_decode(substr($first, 12)));
        self::assertSame($plain, $codec->open($first));
        $rotated = new SodiumNoticeContinuationProtector(['testkey1' => $key, 'testkey2' => str_repeat('n', 32)], 'testkey2');
        self::assertSame($plain, $rotated->open($first));
        self::assertSame(str_repeat('x', 1023), $rotated->open($rotated->seal(str_repeat('x', 1023))));
        self::assertSame(1432, strlen($codec->seal('')));
        try { $codec->seal(str_repeat('x', 1024)); self::fail('Oversized state'); } catch (DomainException) { self::assertTrue(true); }
        $tampered = $first; $tampered[100] = $tampered[100] === 'a' ? 'b' : 'a';
        $short = 'v1.testkey1.'.base64_encode(str_repeat('a', 24).sodium_crypto_aead_xchacha20poly1305_ietf_encrypt('wrong padded length', 'fight.notice.recovery.v1testkey1', str_repeat('a', 24), $key));
        $forged = 'v1.testkey1.'.base64_encode(str_repeat('a', 24).sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(str_repeat('x', 1024), 'wrong protocol', str_repeat('a', 24), $key));
        foreach (['', str_repeat('x', 2049), 'v2'.substr($first, 2), 'v1.retired1'.substr($first, 11), substr($first, 0, -1).'!', $tampered, $short, $forged] as $token) {
            try { $codec->open($token); self::fail('Unauthenticated state'); } catch (DomainException $error) { self::assertSame('Invalid notice continuation.', $error->getMessage()); }
        }
    }

    public function test_that_invalid_configuration_rejects_without_weakening_the_cipher_or_bounds(): void
    {
        foreach ([[[], 'testkey1'], [['testkey1' => 'short'], 'testkey1'], [['bad' => str_repeat('k', 32)], 'bad'], [array_fill_keys(array_map(static fn ($i) => 'testkey'.$i, range(1, 9)), str_repeat('k', 32)), 'testkey1'], [['testkey1' => str_repeat('k', 32)], 'unknown1']] as $args) {
            try { new SodiumNoticeContinuationProtector(...$args); self::fail('Invalid config'); } catch (DomainException) { self::assertTrue(true); }
        }
    }
}
