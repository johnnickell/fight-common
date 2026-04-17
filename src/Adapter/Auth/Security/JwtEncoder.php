<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Security;

use DateTimeImmutable;
use Fight\Common\Application\Auth\Exception\TokenException;
use Fight\Common\Application\Auth\Security\TokenEncoder;
use Fight\Common\Domain\Exception\DomainException;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Hmac\Sha384;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\RegisteredClaims;
use Throwable;

/**
 * Class JwtEncoder
 */
final class JwtEncoder implements TokenEncoder
{
    /**
     * Supported algorithms
     */
    private static array $algorithms = [
        'HS256' => Sha256::class,
        'HS384' => Sha384::class,
        'HS512' => Sha512::class
    ];

    private readonly Configuration $configuration;

    /**
     * Constructs JwtEncoder
     *
     * @throws DomainException When algorithm is not supported
     */
    public function __construct(string $hexSecret, string $algorithm = 'HS256')
    {
        $key = InMemory::plainText(hex2bin($hexSecret));

        if (!isset(static::$algorithms[$algorithm])) {
            $message = sprintf('Unsupported algorithm: %s', $algorithm);
            throw new DomainException($message);
        }

        $algorithmClass = static::$algorithms[$algorithm];
        $this->configuration = Configuration::forSymmetricSigner(new $algorithmClass(), $key);
    }

    /**
     * @inheritDoc
     */
    public function encode(array $claims, DateTimeImmutable $expiration): string
    {
        try {
            $builder = $this->configuration->builder()
                ->expiresAt($expiration);

            foreach ($claims as $key => $value) {
                switch ($key) {
                    case RegisteredClaims::ISSUER:
                        $builder = $builder->issuedBy((string) $value);
                        break;
                    case RegisteredClaims::SUBJECT:
                        $builder = $builder->relatedTo((string) $value);
                        break;
                    case RegisteredClaims::AUDIENCE:
                        $builder = $builder->permittedFor((string) $value);
                        break;
                    case RegisteredClaims::EXPIRATION_TIME:
                        break;
                    case RegisteredClaims::NOT_BEFORE:
                        $builder = $builder->canOnlyBeUsedAfter(
                            DateTimeImmutable::createFromFormat('U', (string) $value)
                        );
                        break;
                    case RegisteredClaims::ISSUED_AT:
                        $builder = $builder->issuedAt(
                            DateTimeImmutable::createFromFormat('U', (string) $value)
                        );
                        break;
                    case RegisteredClaims::ID:
                        $builder = $builder->identifiedBy((string) $value);
                        break;
                    default:
                        $builder = $builder->withClaim($key, $value);
                        break;
                }
            }

            $token = $builder->getToken(
                $this->configuration->signer(),
                $this->configuration->signingKey()
            );

            return $token->toString();
        } catch (Throwable $e) {
            throw new TokenException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
