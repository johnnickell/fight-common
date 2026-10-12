<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Error;

use Error;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpFailure;
use Fight\Common\Adapter\Http\Error\SafeHttpMetadata;
use Fight\Common\Adapter\Http\Error\SafeHttpPresentation;
use Fight\Common\Application\Error\Exception\ApplicationException;
use Fight\Common\Application\Error\Exception\AuthenticationRequired;
use Fight\Common\Application\Error\Exception\InvalidInput;
use Fight\Common\Application\Error\Exception\PermissionDenied;
use Fight\Common\Application\Error\Exception\PreconditionFailed;
use Fight\Common\Application\Error\Exception\RateLimitExceeded;
use Fight\Common\Application\Error\Exception\ResourceGone;
use Fight\Common\Application\Error\Exception\ResourceNotFound;
use Fight\Common\Application\Error\Exception\RuleViolation;
use Fight\Common\Application\Error\Exception\ServerError;
use Fight\Common\Application\Error\Exception\ServiceUnavailable;
use Fight\Common\Application\Error\Exception\StateConflict;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Test\Common\TestCase\UnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(SafeHttpErrorPolicy::class)]
class SafeHttpErrorPolicyTest extends UnitTestCase
{
    public function test_that_status_recognizes_only_declared_intent_and_subtypes(): void
    {
        $policy = new SafeHttpErrorPolicy();
        $cases = [
            [new AuthenticationRequired('private'), 401],
            [new PermissionDenied('private'), 403],
            [new ResourceNotFound('private'), 404],
            [new StateConflict('private'), 409],
            [new ResourceGone('private'), 410],
            [new PreconditionFailed('private'), 412],
            [new InvalidInput('private'), 400],
            [new ValidationException(['private' => ['password=secret']]), 400],
            [new RuleViolation('private'), 422],
            [new RateLimitExceeded('private'), 429],
            [new ServerError('private'), 500],
            [new ServiceUnavailable('private'), 503],
            [new class ('private') extends PermissionDenied {}, 403],
            [new class ('private') extends ApplicationException {}, 500],
            [new RuntimeException('private', 404, new ResourceNotFound('private')), 500],
            [new Error('private'), 500],
        ];

        foreach ($cases as [$failure, $status]) {
            self::assertSame($status, $policy->status($failure), $failure::class);
        }
    }

    public function test_that_present_uses_fixed_safe_messages_and_expected_jsend_shapes(): void
    {
        $policy = new SafeHttpErrorPolicy();
        $cases = [
            [new InvalidInput('secret'), 400, 'Invalid input.'],
            [new PermissionDenied('secret'), 403, 'Permission denied.'],
            [new ResourceNotFound('secret'), 404, 'Resource not found.'],
            [new StateConflict('secret'), 409, 'State conflict.'],
            [new ResourceGone('secret'), 410, 'Resource gone.'],
            [new PreconditionFailed('secret'), 412, 'Precondition failed.'],
            [new RuleViolation('secret'), 422, 'Rule violation.'],
            [new RateLimitExceeded('secret'), 429, 'Too many requests.'],
            [SafeHttpFailure::badRequest('secret'), 400, 'Invalid input.'],
            [SafeHttpFailure::notAcceptable('secret'), 406, 'Not acceptable.'],
            [SafeHttpFailure::payloadTooLarge('secret'), 413, 'Request too large.'],
            [SafeHttpFailure::unsupportedMediaType('secret'), 415, 'Unsupported media type.'],
            [new ValidationException(['token' => ['secret']]), 400, 'Invalid input.'],
        ];
        foreach ($cases as [$failure, $status, $message]) {
            [$actual, $envelope, $headers] = $policy->present($failure);
            self::assertSame($status, $actual);
            self::assertSame(['status' => 'fail', 'data' => ['message' => $message]], $envelope->toArray());
            self::assertSame([], $headers);
            self::assertStringNotContainsString('secret', $envelope->toJson());
        }
        foreach ([new RuntimeException('secret', 404), new Error('secret'), new ServerError('secret')] as $failure) {
            [$status, $envelope, $headers] = $policy->present($failure);
            self::assertSame(500, $status);
            self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], $envelope->toArray());
            self::assertSame([], $headers);
        }
        [$status, $envelope] = $policy->present(new ServiceUnavailable('secret'));
        self::assertSame(503, $status);
        self::assertSame(['status' => 'error', 'message' => 'Service unavailable.'], $envelope->toArray());
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], $policy->fallback()[1]->toArray());
    }

    public function test_that_present_requires_correct_protocol_metadata_and_never_copies_diagnostics(): void
    {
        $policy = new SafeHttpErrorPolicy();
        $cases = [
            [SafeHttpFailure::authenticationRequired('Basic realm="api"', 'secret'), 401, 'WWW-Authenticate', 'Basic realm="api"'],
            [SafeHttpFailure::methodNotAllowed('GET, POST', 'secret'), 405, 'Allow', 'GET, POST'],
            [SafeHttpFailure::tooManyRequests(0, 'secret'), 429, 'Retry-After', '0'],
            [SafeHttpFailure::unavailable(PHP_INT_MAX, 'secret'), 503, 'Retry-After', (string) PHP_INT_MAX],
        ];
        foreach ($cases as [$failure, $status, $header, $value]) {
            [$actual, $envelope, $headers] = $policy->present($failure);
            self::assertSame($status, $actual);
            self::assertSame($value, $headers[$header]);
            self::assertCount(1, $headers);
            self::assertStringNotContainsString('secret', $envelope->toJson());
        }
        [$status, , $headers] = $policy->present(new AuthenticationRequired('secret'), new SafeHttpPresentation(metadata: new SafeHttpMetadata(challenge: 'Bearer realm="example"')));
        self::assertSame(401, $status);
        self::assertSame(['WWW-Authenticate' => 'Bearer realm="example"'], $headers);

        foreach ([
            [new AuthenticationRequired('secret'), null],
            [new PermissionDenied('secret'), new SafeHttpMetadata(challenge: 'Basic')],
            [new InvalidInput('secret'), new SafeHttpMetadata(allow: 'GET')],
            [new RuleViolation('secret'), new SafeHttpMetadata(retryAfterSeconds: 1)],
        ] as [$failure, $metadata]) {
            try {
                $policy->present($failure, new SafeHttpPresentation(metadata: $metadata));
                self::fail('Expected incompatible or missing HTTP metadata.');
            } catch (InvalidArgumentException $expected) {
                self::assertNotSame('', $expected->getMessage());
            }
        }
    }

    public function test_that_present_requires_explicit_adoption_of_neutral_retry_hints(): void
    {
        foreach ([new RateLimitExceeded('secret', retryDelaySeconds: 0), new ServiceUnavailable('secret', retryDelaySeconds: PHP_INT_MAX)] as $failure) {
            self::assertSame([], (new SafeHttpErrorPolicy())->present($failure)[2]);
            self::assertSame(['Retry-After' => (string) $failure->retryDelaySeconds()], (new SafeHttpErrorPolicy(true))->present($failure)[2]);
        }
        self::assertSame([], (new SafeHttpErrorPolicy(true))->present(new ServiceUnavailable('secret'))[2]);
    }

    public function test_that_present_accepts_only_deliberate_plain_public_data(): void
    {
        $policy = new SafeHttpErrorPolicy();
        [$status, $envelope] = $policy->present(new RuleViolation('secret'), new SafeHttpPresentation('Public refusal.', ['field' => ['Safe notice.']]));
        self::assertSame(422, $status);
        self::assertSame(['status' => 'fail', 'data' => ['field' => ['Safe notice.']]], $envelope->toArray());
        [, $envelope] = $policy->present(new ServerError('secret'), new SafeHttpPresentation('Public maintenance notice.', ['reference' => 42]));
        self::assertSame(['status' => 'error', 'message' => 'Public maintenance notice.', 'data' => ['reference' => 42]], $envelope->toArray());
        [, $envelope] = $policy->present(new ServerError('secret'), new SafeHttpPresentation('Public maintenance notice.'));
        self::assertSame(['status' => 'error', 'message' => 'Public maintenance notice.'], $envelope->toArray());
    }
}
