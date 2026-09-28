<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mcp\Sodium;

use Fight\Common\Adapter\Mcp\Sodium\SodiumMcpStateProtector;
use Fight\Common\Application\Mcp\Tool\Interaction\McpStateProtector;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(SodiumMcpStateProtector::class)]
final class SodiumMcpStateProtectorTest extends UnitTestCase
{
    public function test_that_state_is_authenticated_confidential_randomized_and_stateless(): void
    {
        $key = random_bytes(32);
        $protector = new SodiumMcpStateProtector(1, [1 => $key]);
        $plaintext = '{"caller":"private-caller","tool":"private-tool","arguments":{"id":"private-id"}}';
        $first = $protector->seal($plaintext);
        $second = $protector->seal($plaintext);
        self::assertNotSame($first, $second);
        self::assertStringStartsWith('1.1.', $first);
        self::assertSame($plaintext, new SodiumMcpStateProtector(1, [1 => $key])->open($first));
        self::assertSame($plaintext, $protector->open($first));
        $bytes = base64_decode(strtr(explode('.', $first)[2], '-_', '+/'));
        self::assertSame(strlen($plaintext) + 40, strlen($bytes));
        foreach (['private-caller', 'private-tool', 'private-id', 'arguments'] as $secret) {
            self::assertStringNotContainsString($secret, $first);
            self::assertStringNotContainsString($secret, $bytes);
        }
    }

    public function test_that_rotation_reads_old_tokens_but_emits_only_the_active_version(): void
    {
        $old = random_bytes(32);
        $new = random_bytes(32);
        $token = new SodiumMcpStateProtector(1, [1 => $old])->seal('bound data');
        $rotated = new SodiumMcpStateProtector(2, [1 => $old, 2 => $new]);
        self::assertSame('bound data', $rotated->open($token));
        self::assertStringStartsWith('1.2.', $rotated->seal('new state'));
        $this->expectException(DomainException::class);
        new SodiumMcpStateProtector(2, [2 => $new])->open($token);
    }

    #[DataProvider('badRings')]
    public function test_that_invalid_key_rings_fail_at_composition(int $active, array $keys): void
    {
        $this->expectException(DomainException::class);
        new SodiumMcpStateProtector($active, $keys);
    }

    public static function badRings(): iterable
    {
        $key = str_repeat('k', 32);
        yield 'empty' => [1, []];
        yield 'missing active' => [2, [1 => $key]];
        yield 'too many' => [1, array_fill(1, 5, $key)];
        yield 'zero' => [0, [0 => $key]];
        yield 'negative' => [-1, [-1 => $key]];
        yield 'too large' => [1000000000, [1000000000 => $key]];
        yield 'non-version label' => [1, [1 => $key, 'caller' => $key]];
        yield 'short key' => [1, [1 => 'short']];
    }

    #[DataProvider('badTokens')]
    public function test_that_malformed_and_oversized_tokens_fail_closed(string $token): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid interaction state.');
        new SodiumMcpStateProtector(1, [1 => str_repeat('k', 32)])->open($token);
    }

    public static function badTokens(): iterable
    {
        yield [''];
        yield ['2.1.AAAA'];
        yield ['1.01.AAAA'];
        yield ['1.9.AAAA'];
        yield ['1.1.AAA='];
        yield ['1.1.A'];
        yield ['1.1.AAAA'];
        yield ['1.1.'.str_repeat('A', 53).'B'];
        yield ['1.1.'.str_repeat('A', 54)];
        yield [str_repeat('x', McpStateProtector::MAX_STATE_BYTES + 1)];
    }

    public function test_that_nonce_ciphertext_tag_and_key_version_tampering_are_rejected(): void
    {
        $key = random_bytes(32);
        $protector = new SodiumMcpStateProtector(1, [1 => $key, 2 => $key]);
        $token = $protector->seal('protected');
        $bytes = base64_decode(strtr(explode('.', $token)[2], '-_', '+/'));
        foreach ([0, 24, strlen($bytes) - 1] as $offset) {
            $changed = $bytes;
            $changed[$offset] = chr(ord($changed[$offset]) ^ 1);
            $tampered = '1.1.'.rtrim(strtr(base64_encode($changed), '+/', '-_'), '=');
            try {
                $protector->open($tampered);
                self::fail('Tampered authenticated state was accepted');
            } catch (DomainException $error) {
                self::assertSame('Invalid interaction state.', $error->getMessage());
            }
        }
        $this->expectException(DomainException::class);
        $protector->open('1.2.'.explode('.', $token)[2]);
    }

    public function test_that_the_exact_token_byte_limit_is_enforced_on_emission_and_reading(): void
    {
        $protector = new SodiumMcpStateProtector(1, [1 => str_repeat('k', 32)]);
        $plaintext = str_repeat('x', 49109);
        $token = $protector->seal($plaintext);
        self::assertSame(McpStateProtector::MAX_STATE_BYTES, strlen($token));
        self::assertSame($plaintext, $protector->open($token));
        $this->expectException(DomainException::class);
        $protector->seal($plaintext.'x');
    }
}
