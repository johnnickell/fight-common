<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Security;

use DateTimeImmutable;
use Fight\Common\Adapter\Auth\Security\JwtDecoder;
use Fight\Common\Adapter\Auth\Security\JwtEncoder;
use Fight\Common\Application\Auth\Exception\TokenException;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JwtDecoder::class)]
class JwtDecoderTest extends UnitTestCase
{
    private string $hexSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hexSecret = bin2hex(random_bytes(32));
    }

    public function test_that_decode_returns_correct_payload_from_token_produced_by_encoder(): void
    {
        $encoder = new JwtEncoder($this->hexSecret);
        $decoder = new JwtDecoder($this->hexSecret);

        $token = $encoder->encode(['sub' => 'user-123'], new DateTimeImmutable('+1 hour'));
        $claims = $decoder->decode($token);

        self::assertSame('user-123', $claims['sub']);
    }

    public function test_that_decode_throws_for_token_signed_with_different_key(): void
    {
        $otherSecret = bin2hex(random_bytes(32));
        $encoder = new JwtEncoder($otherSecret);
        $decoder = new JwtDecoder($this->hexSecret);

        $token = $encoder->encode(['sub' => 'user-1'], new DateTimeImmutable('+1 hour'));

        $this->expectException(TokenException::class);
        $decoder->decode($token);
    }

    public function test_that_decode_throws_for_invalid_token_string(): void
    {
        $decoder = new JwtDecoder($this->hexSecret);

        $this->expectException(TokenException::class);
        $decoder->decode('not.a.valid.jwt.string');
    }

    public function test_that_unsupported_algorithm_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        new JwtDecoder($this->hexSecret, 'RS256');
    }
}
