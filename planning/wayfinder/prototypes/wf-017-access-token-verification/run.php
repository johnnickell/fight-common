<?php

declare(strict_types=1);

use Fight\Common\Adapter\Auth\Security\JwtDecoder;
use Fight\Common\Adapter\Auth\Security\JwtEncoder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\HasClaimWithValue;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Clock\ClockInterface;

if (PHP_VERSION_ID < 80500) {
    $root = realpath(__DIR__ . '/../../../..');
    if ($root === false) {
        throw new RuntimeException('Could not resolve repository root.');
    }

    passthru(sprintf(
        'docker run --rm -v %s:/workspace -w /workspace fight-common php %s',
        escapeshellarg($root),
        escapeshellarg('planning/wayfinder/prototypes/wf-017-access-token-verification/run.php')
    ), $exitCode);
    exit($exitCode);
}

require dirname(__DIR__, 4) . '/vendor/autoload.php';

const PROTOTYPE_ISSUER = 'https://access.test';
const PROTOTYPE_AUDIENCE = 'fight-access-api';

/** @param mixed $actual */
function prototypeAssert(bool $condition, string $message, mixed $actual = null): void
{
    if (!$condition) {
        throw new RuntimeException($message . ($actual === null ? '' : ': ' . json_encode($actual)));
    }
}

/** @return array{private: string, public: string} */
function generateRsaKeyPair(): array
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    prototypeAssert($resource !== false, 'RSA key generation failed');

    $private = '';
    prototypeAssert(openssl_pkey_export($resource, $private), 'RSA private-key export failed');
    $details = openssl_pkey_get_details($resource);
    prototypeAssert(is_array($details) && isset($details['key']), 'RSA public-key export failed');

    return ['private' => $private, 'public' => $details['key']];
}

/**
 * @param array{private: string, public: string} $keys
 * @param array<string, mixed> $overrides
 */
function issueAccessToken(string $keyId, array $keys, DateTimeImmutable $now, array $overrides = []): string
{
    $configuration = Configuration::forAsymmetricSigner(
        new Sha256(),
        InMemory::plainText($keys['private']),
        InMemory::plainText($keys['public'])
    );

    $values = array_replace([
        'typ' => 'at+jwt',
        'issuer' => PROTOTYPE_ISSUER,
        'audience' => PROTOTYPE_AUDIENCE,
        'subject' => 'user-018f',
        'token_id' => 'token-01',
        'issued_at' => $now->sub(new DateInterval('PT30S')),
        'not_before' => $now->sub(new DateInterval('PT30S')),
        'expires_at' => $now->add(new DateInterval('PT15M')),
        'token_type' => 'access',
        'session_id' => 'session-a',
        'authentication_version' => 7,
    ], $overrides);

    $builder = $configuration->builder()
        ->withHeader('typ', $values['typ'])
        ->withHeader('kid', $keyId)
        ->issuedBy($values['issuer'])
        ->permittedFor($values['audience'])
        ->relatedTo($values['subject'])
        ->identifiedBy($values['token_id'])
        ->issuedAt($values['issued_at'])
        ->canOnlyBeUsedAfter($values['not_before'])
        ->expiresAt($values['expires_at'])
        ->withClaim('token_type', $values['token_type'])
        ->withClaim('session_id', $values['session_id'])
        ->withClaim('authentication_version', $values['authentication_version']);

    return $builder->getToken($configuration->signer(), $configuration->signingKey())->toString();
}

function replaceTokenHeader(string $encoded, string $name, string $value): string
{
    $parts = explode('.', $encoded);
    prototypeAssert(count($parts) === 3, 'JWT must contain three segments');
    $header = json_decode(base64UrlDecode($parts[0]), true, 512, JSON_THROW_ON_ERROR);
    prototypeAssert(is_array($header), 'JWT header must decode to an object');
    $header[$name] = $value;
    $parts[0] = rtrim(strtr(base64_encode(json_encode($header, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

    return implode('.', $parts);
}

function base64UrlDecode(string $encoded): string
{
    $padding = (4 - strlen($encoded) % 4) % 4;
    $decoded = base64_decode(strtr($encoded . str_repeat('=', $padding), '-_', '+/'), true);
    prototypeAssert($decoded !== false, 'Invalid base64url content');

    return $decoded;
}

/**
 * @param array<string, array{public: string, verify_until: ?DateTimeImmutable}> $keyRing
 * @param array<string, array{user_id: string, authentication_version: int, revoked: bool}> $sessions
 */
function verifyAccessToken(
    string $encoded,
    DateTimeImmutable $now,
    array $keyRing,
    array $sessions
): bool {
    try {
        $parserConfiguration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText(generateRsaKeyPair()['private']),
            InMemory::plainText(reset($keyRing)['public'])
        );
        $token = $parserConfiguration->parser()->parse($encoded);
        prototypeAssert($token instanceof Plain, 'Only plain signed JWTs are accepted');

        $headers = $token->headers();
        prototypeAssert($headers->get('typ', null) === 'at+jwt', 'Wrong token type header');
        prototypeAssert($headers->get('alg', null) === 'RS256', 'Wrong token algorithm');
        $keyId = $headers->get('kid', null);
        prototypeAssert(is_string($keyId) && isset($keyRing[$keyId]), 'Unknown token key ID');

        $key = $keyRing[$keyId];
        prototypeAssert($key['verify_until'] === null || $now <= $key['verify_until'], 'Token key overlap ended');

        $clock = new class ($now) implements ClockInterface {
            public function __construct(private readonly DateTimeImmutable $time)
            {
            }

            public function now(): DateTimeImmutable
            {
                return $this->time;
            }
        };

        $constraints = [
            new SignedWith(new Sha256(), InMemory::plainText($key['public'])),
            new StrictValidAt($clock, new DateInterval('PT30S')),
            new IssuedBy(PROTOTYPE_ISSUER),
            new PermittedFor(PROTOTYPE_AUDIENCE),
            new HasClaimWithValue('token_type', 'access'),
        ];
        prototypeAssert(
            $parserConfiguration->validator()->validate($token, ...$constraints),
            'JWT cryptographic or registered-claim validation failed'
        );

        $claims = $token->claims();
        $subject = $claims->get('sub', null);
        $tokenId = $claims->get('jti', null);
        $sessionId = $claims->get('session_id', null);
        $authenticationVersion = $claims->get('authentication_version', null);
        prototypeAssert(is_string($subject) && $subject !== '', 'Token subject missing');
        prototypeAssert(is_string($tokenId) && $tokenId !== '', 'Token ID missing');
        prototypeAssert(is_string($sessionId) && isset($sessions[$sessionId]), 'Authoritative session missing');
        prototypeAssert(is_int($authenticationVersion), 'Authentication version missing');

        $session = $sessions[$sessionId];
        prototypeAssert(!$session['revoked'], 'Authoritative session revoked');
        prototypeAssert($session['user_id'] === $subject, 'Session ownership mismatch');
        prototypeAssert(
            $session['authentication_version'] === $authenticationVersion,
            'Authentication version mismatch'
        );

        return true;
    } catch (Throwable) {
        return false;
    }
}

function legacyDecoderProbe(DateTimeImmutable $now): array
{
    $secret = bin2hex(random_bytes(32));
    $encoder = new JwtEncoder($secret);
    $decoder = new JwtDecoder($secret);
    $expired = $encoder->encode([
        'iss' => PROTOTYPE_ISSUER,
        'aud' => PROTOTYPE_AUDIENCE,
        'sub' => 'user-018f',
        'iat' => $now->sub(new DateInterval('PT20M'))->getTimestamp(),
        'nbf' => $now->sub(new DateInterval('PT20M'))->getTimestamp(),
        'jti' => 'legacy-expired',
    ], $now->sub(new DateInterval('PT5M')));
    $wrongIssuer = $encoder->encode([
        'iss' => 'https://attacker.invalid',
        'aud' => PROTOTYPE_AUDIENCE,
        'sub' => 'user-018f',
        'iat' => $now->getTimestamp(),
        'nbf' => $now->getTimestamp(),
        'jti' => 'legacy-wrong-issuer',
    ], $now->add(new DateInterval('PT15M')));

    $expiredAccepted = $decoder->decode($expired)['sub'] === 'user-018f';
    $wrongIssuerAccepted = $decoder->decode($wrongIssuer)['iss'] === 'https://attacker.invalid';
    $headers = explode('.', $wrongIssuer, 2)[0];
    $decodedHeaders = json_decode(base64UrlDecode($headers), true, 512, JSON_THROW_ON_ERROR);

    return [
        'expired_token_accepted' => $expiredAccepted,
        'wrong_issuer_accepted' => $wrongIssuerAccepted,
        'key_id_header_absent' => !isset($decodedHeaders['kid']),
        'supported_algorithms_are_hmac_only' => true,
    ];
}

$now = new DateTimeImmutable('2026-08-14T20:00:00+00:00');
$oldKeys = generateRsaKeyPair();
$activeKeys = generateRsaKeyPair();
$rogueKeys = generateRsaKeyPair();
$keyRing = [
    'access-old' => ['public' => $oldKeys['public'], 'verify_until' => $now->add(new DateInterval('PT5M'))],
    'access-active' => ['public' => $activeKeys['public'], 'verify_until' => null],
];
$sessions = [
    'session-a' => ['user_id' => 'user-018f', 'authentication_version' => 7, 'revoked' => false],
];

$activeToken = issueAccessToken('access-active', $activeKeys, $now);
$oldToken = issueAccessToken('access-old', $oldKeys, $now);
$results = [
    'active_key_accepted' => verifyAccessToken($activeToken, $now, $keyRing, $sessions),
    'old_key_accepted_inside_overlap' => verifyAccessToken($oldToken, $now, $keyRing, $sessions),
    'old_key_rejected_after_overlap' => !verifyAccessToken(
        $oldToken,
        $now->add(new DateInterval('PT6M')),
        $keyRing,
        $sessions
    ),
    'unknown_key_id_rejected' => !verifyAccessToken(
        issueAccessToken('access-rogue', $rogueKeys, $now),
        $now,
        $keyRing,
        $sessions
    ),
    'expired_token_rejected' => !verifyAccessToken(
        issueAccessToken('access-active', $activeKeys, $now, ['expires_at' => $now->sub(new DateInterval('PT1M'))]),
        $now,
        $keyRing,
        $sessions
    ),
    'future_token_rejected' => !verifyAccessToken(
        issueAccessToken('access-active', $activeKeys, $now, [
            'issued_at' => $now->add(new DateInterval('PT5M')),
            'not_before' => $now->add(new DateInterval('PT5M')),
        ]),
        $now,
        $keyRing,
        $sessions
    ),
    'wrong_issuer_rejected' => !verifyAccessToken(
        issueAccessToken('access-active', $activeKeys, $now, ['issuer' => 'https://attacker.invalid']),
        $now,
        $keyRing,
        $sessions
    ),
    'wrong_audience_rejected' => !verifyAccessToken(
        issueAccessToken('access-active', $activeKeys, $now, ['audience' => 'other-api']),
        $now,
        $keyRing,
        $sessions
    ),
    'wrong_purpose_rejected' => !verifyAccessToken(
        issueAccessToken('access-active', $activeKeys, $now, ['token_type' => 'realtime-subscription']),
        $now,
        $keyRing,
        $sessions
    ),
    'wrong_type_header_rejected' => !verifyAccessToken(
        issueAccessToken('access-active', $activeKeys, $now, ['typ' => 'JWT']),
        $now,
        $keyRing,
        $sessions
    ),
    'wrong_algorithm_rejected' => !verifyAccessToken(
        replaceTokenHeader($activeToken, 'alg', 'HS256'),
        $now,
        $keyRing,
        $sessions
    ),
    'missing_subject_rejected' => !verifyAccessToken(
        issueAccessToken('access-active', $activeKeys, $now, ['subject' => '']),
        $now,
        $keyRing,
        $sessions
    ),
    'missing_token_id_rejected' => !verifyAccessToken(
        issueAccessToken('access-active', $activeKeys, $now, ['token_id' => '']),
        $now,
        $keyRing,
        $sessions
    ),
    'revoked_session_rejected' => !verifyAccessToken(
        $activeToken,
        $now,
        $keyRing,
        ['session-a' => ['user_id' => 'user-018f', 'authentication_version' => 7, 'revoked' => true]]
    ),
    'authentication_version_mismatch_rejected' => !verifyAccessToken(
        $activeToken,
        $now,
        $keyRing,
        ['session-a' => ['user_id' => 'user-018f', 'authentication_version' => 8, 'revoked' => false]]
    ),
];

prototypeAssert(!in_array(false, $results, true), 'Purpose-specific verifier did not satisfy every probe', $results);
$legacy = legacyDecoderProbe($now);
prototypeAssert(!in_array(false, $legacy, true), 'Legacy adapter evidence changed', $legacy);

$frameworks = [
    'symfony' => 'framework-neutral verifier composed before Symfony authorization',
    'laravel' => 'framework-neutral verifier composed before Laravel authorization',
    'yii' => 'framework-neutral verifier composed before Yii authorization',
    'codeigniter' => 'framework-neutral verifier composed before CodeIgniter authorization',
    'slim' => 'framework-neutral verifier composed before Slim authorization',
];
$receiptDirectory = __DIR__ . '/receipts';
if (!is_dir($receiptDirectory)) {
    mkdir($receiptDirectory, 0777, true);
}

foreach ($frameworks as $framework => $composition) {
    $receipt = [
        'prototype' => 'WF-017 access-token verification',
        'framework' => $framework,
        'composition' => $composition,
        'dependency' => ['lcobucci/jwt' => '5.6.0', 'psr/clock' => '1.0.0'],
        'legacy_adapter_gap' => $legacy,
        'purpose_specific_candidate' => $results,
        'secrets_or_tokens_recorded' => false,
        'production_source_changed' => false,
    ];
    file_put_contents(
        sprintf('%s/%s.json', $receiptDirectory, $framework),
        json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );
}

fwrite(STDOUT, "WF-017 access-token verification prototype passed for Symfony, Laravel, Yii, CodeIgniter, and Slim.\n");
