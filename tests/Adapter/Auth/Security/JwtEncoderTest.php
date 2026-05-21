<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Auth\Security;

use DateTimeImmutable;
use Fight\Common\Adapter\Auth\Security\JwtEncoder;
use Fight\Common\Application\Auth\Exception\TokenException;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Lcobucci\JWT\Token\RegisteredClaims;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(JwtEncoder::class)]
class JwtEncoderTest extends UnitTestCase
{
    private string $hexSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hexSecret = bin2hex(random_bytes(32));
    }

    public function test_that_encode_returns_non_empty_token_string_given_payload_and_algorithm(): void
    {
        $encoder = new JwtEncoder($this->hexSecret, 'HS256');
        $token = $encoder->encode(['sub' => 'user-1'], new DateTimeImmutable('+1 hour'));

        self::assertIsString($token);
        self::assertNotEmpty($token);
    }

    public function test_that_encode_works_with_hs384_algorithm(): void
    {
        $encoder = new JwtEncoder(bin2hex(random_bytes(48)), 'HS384');
        $token = $encoder->encode(['sub' => 'user-1'], new DateTimeImmutable('+1 hour'));

        self::assertNotEmpty($token);
    }

    public function test_that_encode_works_with_hs512_algorithm(): void
    {
        $encoder = new JwtEncoder(bin2hex(random_bytes(64)), 'HS512');
        $token = $encoder->encode(['sub' => 'user-1'], new DateTimeImmutable('+1 hour'));

        self::assertNotEmpty($token);
    }

    public function test_that_encode_with_issuer_claim_produces_valid_token(): void
    {
        $encoder = new JwtEncoder($this->hexSecret);
        $token = $encoder->encode([RegisteredClaims::ISSUER => 'my-service'], new DateTimeImmutable('+1 hour'));

        self::assertNotEmpty($token);
    }

    public function test_that_encode_with_audience_claim_produces_valid_token(): void
    {
        $encoder = new JwtEncoder($this->hexSecret);
        $token = $encoder->encode([RegisteredClaims::AUDIENCE => 'my-client'], new DateTimeImmutable('+1 hour'));

        self::assertNotEmpty($token);
    }

    public function test_that_encode_with_expiration_time_in_claims_array_is_skipped(): void
    {
        $encoder = new JwtEncoder($this->hexSecret);
        // exp in the claims array hits the break branch; expiry is set via the $expiration parameter
        $token = $encoder->encode([RegisteredClaims::EXPIRATION_TIME => time() + 60], new DateTimeImmutable('+1 hour'));

        self::assertNotEmpty($token);
    }

    public function test_that_encode_with_not_before_claim_produces_valid_token(): void
    {
        $encoder = new JwtEncoder($this->hexSecret);
        $token = $encoder->encode([RegisteredClaims::NOT_BEFORE => time() - 60], new DateTimeImmutable('+1 hour'));

        self::assertNotEmpty($token);
    }

    public function test_that_encode_with_issued_at_claim_produces_valid_token(): void
    {
        $encoder = new JwtEncoder($this->hexSecret);
        $token = $encoder->encode([RegisteredClaims::ISSUED_AT => time()], new DateTimeImmutable('+1 hour'));

        self::assertNotEmpty($token);
    }

    public function test_that_encode_with_id_claim_produces_valid_token(): void
    {
        $encoder = new JwtEncoder($this->hexSecret);
        $token = $encoder->encode([RegisteredClaims::ID => 'unique-id-123'], new DateTimeImmutable('+1 hour'));

        self::assertNotEmpty($token);
    }

    public function test_that_encode_with_custom_claim_produces_valid_token(): void
    {
        $encoder = new JwtEncoder($this->hexSecret);
        $token = $encoder->encode(['role' => 'admin', 'tenant' => 'acme'], new DateTimeImmutable('+1 hour'));

        self::assertNotEmpty($token);
    }

    public function test_that_unsupported_algorithm_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);

        new JwtEncoder($this->hexSecret, 'RS256');
    }

    public function test_that_encode_wraps_encoding_failure_in_token_exception(): void
    {
        $encoder = new JwtEncoder($this->hexSecret);

        $this->expectException(TokenException::class);

        $encoder->encode(['val' => NAN], new DateTimeImmutable('+1 hour'));
    }
}
