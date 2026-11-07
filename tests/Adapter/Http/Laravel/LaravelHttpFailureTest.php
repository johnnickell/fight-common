<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Laravel;

use Fight\Common\Adapter\Http\Error\SafeHttpFailure;
use Fight\Common\Adapter\Http\Laravel\LaravelHttpFailure;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

#[CoversClass(LaravelHttpFailure::class)]
final class LaravelHttpFailureTest extends UnitTestCase
{
    public function test_that_explicit_native_types_map_only_their_own_status_and_metadata(): void
    {
        $cases = [
            [new AuthenticationException('secret'), 401],
            [new AuthorizationException('secret'), 403],
            [(new AuthorizationException('secret'))->withStatus(403), 403],
            [(new AuthorizationException('secret'))->asNotFound(), 404],
            [new BadRequestHttpException('secret'), 400],
            [new UnauthorizedHttpException('Basic realm="safe"', 'secret'), 401],
            [new AccessDeniedHttpException('secret'), 403],
            [new NotFoundHttpException('secret'), 404],
            [new MethodNotAllowedHttpException(['GET', 'POST'], 'secret'), 405],
            [new NotAcceptableHttpException('secret'), 406],
            [new ConflictHttpException('secret'), 409],
            [new GoneHttpException('secret'), 410],
            [new PreconditionFailedHttpException('secret'), 412],
            [new PostTooLargeException('secret'), 413],
            [new UnsupportedMediaTypeHttpException('secret'), 415],
            [new UnprocessableEntityHttpException('secret'), 422],
            [new ThrottleRequestsException('secret'), 429],
            [new TooManyRequestsHttpException(message: 'secret'), 429],
            [new ServiceUnavailableHttpException(message: 'secret'), 503],
            [new HttpException(502, 'secret'), 502],
            [new HttpException(504, 'secret'), 504],
        ];
        foreach ($cases as [$failure, $status]) {
            $translated = LaravelHttpFailure::translate($failure);
            self::assertInstanceOf(SafeHttpFailure::class, $translated);
            self::assertSame($status, $translated->status());
            self::assertSame('', $translated->getMessage());
        }

        $retry = LaravelHttpFailure::translate(new ThrottleRequestsException('secret', headers: [
            'X-Discard' => 'secret', 'Retry-After' => '0'
        ]));
        self::assertSame(0, $retry->metadata()->retryAfterSeconds());
        $max = LaravelHttpFailure::translate(new HttpException(503, headers: ['Retry-After' => (string) PHP_INT_MAX]));
        self::assertSame(PHP_INT_MAX, $max->metadata()->retryAfterSeconds());
        $challenge = LaravelHttpFailure::translate(new UnauthorizedHttpException('Basic realm="safe"'));
        self::assertSame('Basic realm="safe"', $challenge->metadata()->challenge());
        $allow = LaravelHttpFailure::translate(new MethodNotAllowedHttpException(['GET', 'POST']));
        self::assertSame('GET, POST', $allow->metadata()->allow());
    }

    public function test_that_native_validation_is_distinct_from_common_validation_and_rejects_mutated_status(): void
    {
        $validator = (new Factory(new Translator(new ArrayLoader(), 'en')))->make(['field' => null], ['field' => 'required']);
        $validator->fails();
        $failure = new ValidationException($validator);
        self::assertSame(422, LaravelHttpFailure::translate($failure)->status());
        $failure->status = 400;
        self::assertSame($failure, LaravelHttpFailure::translate($failure));
    }

    public function test_that_unknown_exception_status_subtype_and_forged_authorization_intent_are_not_mapped(): void
    {
        $failures = [
            new RuntimeException('secret', 404),
            new HttpException(418, 'secret'),
            new class (404) extends HttpException {},
            (new AuthorizationException('secret'))->withStatus(429),
        ];
        foreach ($failures as $failure) {
            self::assertSame($failure, LaravelHttpFailure::translate($failure));
        }
    }

    public function test_that_ambiguous_or_malformed_native_protocol_metadata_is_rejected(): void
    {
        $failures = [
            new HttpException(405, headers: ['Allow' => 'GET', 'allow' => 'POST']),
            new HttpException(405, headers: ['Allow' => ['GET']]),
            new HttpException(405, headers: ['Allow' => "GET\r\nX-Leak: secret"]),
            new HttpException(401, headers: ['WWW-Authenticate' => "Basic\r\nX-Leak: secret"]),
            new HttpException(429, headers: ['Retry-After' => 'tomorrow']),
            new HttpException(429, headers: ['Retry-After' => '-1']),
            new HttpException(429, headers: ['Retry-After' => '01']),
            new HttpException(503, headers: ['Retry-After' => '999999999999999999999999']),
        ];
        foreach ($failures as $failure) {
            try {
                LaravelHttpFailure::translate($failure);
                self::fail('Malformed native metadata should be rejected.');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
