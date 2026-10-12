<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Laravel;

use Error;
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpMetadata;
use Fight\Common\Adapter\Http\Error\SafeHttpPresentation;
use Fight\Common\Adapter\Http\Error\SafeHttpProjector;
use Fight\Common\Adapter\Http\Laravel\JSendResponse;
use Fight\Common\Adapter\Http\Laravel\SafeLaravelExceptionHandler;
use Fight\Common\Adapter\ServiceContainer\Laravel\HttpServiceProvider;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Illuminate\View\ViewServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

#[CoversClass(SafeLaravelExceptionHandler::class)]
final class SafeLaravelExceptionHandlerTest extends UnitTestCase
{
    public function test_that_booted_laravel_report_and_render_conceal_synthetic_secrets(): void
    {
        $logged = [];
        $reports = [];
        $logger = $this->logger($logged);
        $diagnostics = $this->diagnostics($reports, $logger);
        $app = $this->application($diagnostics);
        $failure = new RuntimeException('password=synthetic-secret', 401, new RuntimeException('cause=synthetic-secret'));

        $response = $this->request($app, $failure);

        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
        self::assertSame([$failure], $reports);
        self::assertSame([['HTTP failure', ['type' => RuntimeException::class]]], $logged);
        self::assertFalse($app->bound(HttpServiceProvider::class));
    }

    public function test_that_booted_laravel_maps_all_application_categories_without_diagnostic_disclosure(): void
    {
        $cases = [
            [new AuthenticationRequired('secret'), 500, 'error', 'Internal server error.'],
            [new PermissionDenied('secret'), 403, 'fail', 'Permission denied.'],
            [new ResourceNotFound('secret'), 404, 'fail', 'Resource not found.'],
            [new StateConflict('secret'), 409, 'fail', 'State conflict.'],
            [new ResourceGone('secret'), 410, 'fail', 'Resource gone.'],
            [new PreconditionFailed('secret'), 412, 'fail', 'Precondition failed.'],
            [new InvalidInput('secret'), 400, 'fail', 'Invalid input.'],
            [new ValidationException(['field' => ['secret']]), 400, 'fail', 'Invalid input.'],
            [new RuleViolation('secret'), 422, 'fail', 'Rule violation.'],
            [new RateLimitExceeded('secret'), 429, 'fail', 'Too many requests.'],
            [new ServerError('secret'), 500, 'error', 'Internal server error.'],
            [new ServiceUnavailable('secret'), 503, 'error', 'Service unavailable.'],
            [new Error('secret'), 500, 'error', 'Internal server error.'],
        ];
        $reports = [];
        $logged = [];
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)));
        foreach ($cases as [$failure, $status, $shape, $message]) {
            $response = $this->request($app, $failure);
            $body = json_decode((string) $response->getContent(), true);
            self::assertSame($status, $response->getStatusCode());
            self::assertSame($shape, $body['status']);
            self::assertSame($message, $shape === 'fail' ? $body['data']['message'] : $body['message']);
            self::assertStringNotContainsString('secret', (string) $response->getContent());
            self::assertFalse($response->headers->has('WWW-Authenticate'));
        }
        self::assertSame(array_column($cases, 0), $reports);
        self::assertCount(count($cases), $logged);
    }

    public function test_that_booted_laravel_maps_native_refusals_with_required_metadata(): void
    {
        $translator = new Translator(new ArrayLoader(), 'en');
        $validator = (new Factory($translator))->make(['field' => null], ['field' => 'required']);
        $validator->fails();
        $nativeValidation = new LaravelValidationException($validator);
        $nativeValidation->response = new Response('password=secret');
        $methodFailure = new MethodNotAllowedHttpException(['GET', 'POST'], 'secret');
        $throttleFailure = new ThrottleRequestsException('secret', headers: ['Retry-After' => '15']);
        $cases = [
            [new AuthorizationException('secret'), 403, 'Permission denied.'],
            [(new AuthorizationException('secret'))->asNotFound(), 404, 'Resource not found.'],
            [$nativeValidation, 422, 'Rule violation.'],
            [new BadRequestHttpException('secret'), 400, 'Invalid input.'],
            [new AccessDeniedHttpException('secret'), 403, 'Permission denied.'],
            [new NotFoundHttpException('secret'), 404, 'Resource not found.'],
            [new NotAcceptableHttpException('secret'), 406, 'Not acceptable.'],
            [new ConflictHttpException('secret'), 409, 'State conflict.'],
            [new GoneHttpException('secret'), 410, 'Resource gone.'],
            [new PreconditionFailedHttpException('secret'), 412, 'Precondition failed.'],
            [new UnsupportedMediaTypeHttpException('secret'), 415, 'Unsupported media type.'],
            [new UnprocessableEntityHttpException('secret'), 422, 'Rule violation.'],
            [new TooManyRequestsHttpException(message: 'secret'), 429, 'Too many requests.'],
            [new ServiceUnavailableHttpException(message: 'secret'), 503, 'Service unavailable.'],
            [$methodFailure, 405, 'Method not allowed.'],
            [new PostTooLargeException('secret'), 413, 'Request too large.'],
            [$throttleFailure, 429, 'Too many requests.'],
            [new HttpException(502, 'secret'), 502, 'Internal server error.'],
            [new HttpException(504, 'secret'), 504, 'Internal server error.'],
        ];
        $reports = [];
        $logged = [];
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)));
        foreach ($cases as [$failure, $status, $message]) {
            $response = $this->request($app, $failure);
            $body = json_decode((string) $response->getContent(), true);
            self::assertSame($status, $response->getStatusCode());
            self::assertSame($message, $status >= 500 ? $body['message'] : $body['data']['message']);
            self::assertStringNotContainsString('secret', (string) $response->getContent());
        }
        self::assertSame('GET, POST', $this->request($app, $methodFailure)->headers->get('Allow'));
        self::assertSame('15', $this->request($app, $throttleFailure)->headers->get('Retry-After'));
        self::assertCount(count($cases), $reports);
        self::assertCount(count($cases), $logged);
    }

    public function test_that_native_and_application_challenges_require_trusted_metadata(): void
    {
        $reports = [];
        $logged = [];
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)));
        foreach ([new AuthenticationException('secret'), new HttpException(401, 'secret')] as $failure) {
            $response = $this->request($app, $failure);
            self::assertSame(500, $response->getStatusCode());
            self::assertFalse($response->headers->has('WWW-Authenticate'));
        }
        $allowed = $this->request($app, new UnauthorizedHttpException('Basic realm="safe"', 'secret'));
        self::assertSame(401, $allowed->getStatusCode());
        self::assertSame('Basic realm="safe"', $allowed->headers->get('WWW-Authenticate'));

        $projector = new class implements SafeHttpProjector {
            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                return new SafeHttpPresentation(metadata: new SafeHttpMetadata(challenge: 'Basic realm="consumer"'));
            }
        };
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)), $projector);
        $response = $this->request($app, new AuthenticationException('secret'));
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Basic realm="consumer"', $response->headers->get('WWW-Authenticate'));
        self::assertSame('{"status":"fail","data":{"message":"Authentication required."}}', $response->getContent());

        $native = $this->request($app, new UnauthorizedHttpException('Basic realm="safe"', 'secret'));
        self::assertSame(401, $native->getStatusCode());
        self::assertSame('Basic realm="consumer"', $native->headers->get('WWW-Authenticate'));
    }

    public function test_that_malformed_native_headers_and_unsupported_http_status_fail_closed_after_routing(): void
    {
        $reports = [];
        $logged = [];
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)));
        $failures = [
            new HttpException(401, headers: ['WWW-Authenticate' => "Basic\r\nX-Leak: secret"]),
            new HttpException(405, headers: ['Allow' => "GET\r\nX-Leak: secret"]),
            new HttpException(429, headers: ['Retry-After' => 'tomorrow']),
            new HttpException(503, headers: ['Retry-After' => '999999999999999999999']),
            new HttpException(418, 'secret', headers: ['X-Leak' => 'secret']),
            new RuntimeException('secret', 404),
        ];
        foreach ($failures as $failure) {
            $response = $this->request($app, $failure);
            self::assertSame(500, $response->getStatusCode());
            self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
            self::assertFalse($response->headers->has('X-Leak'));
            self::assertFalse($response->headers->has('WWW-Authenticate'));
            self::assertFalse($response->headers->has('Allow'));
            self::assertFalse($response->headers->has('Retry-After'));
        }
        self::assertSame($failures, $reports);
        self::assertCount(count($failures), $logged);
    }

    public function test_that_trusted_projection_and_report_failure_respect_one_reporting_owner(): void
    {
        $reports = [];
        $logged = [];
        $projector = new class implements SafeHttpProjector {
            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                if ($failure instanceof ValidationException) {
                    return new SafeHttpPresentation(data: ['field' => ['Deliberately public.']]);
                }

                return new SafeHttpPresentation(message: 'Temporarily offline.', data: ['retry' => false]);
            }
        };
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)), $projector);
        self::assertSame(
            '{"status":"fail","data":{"field":["Deliberately public."]}}',
            $this->request($app, new ValidationException(['field' => ['secret']]))->getContent()
        );
        self::assertSame(
            '{"status":"error","message":"Temporarily offline.","data":{"retry":false}}',
            $this->request($app, new ServiceUnavailable('secret'))->getContent()
        );
        self::assertCount(2, $reports);
        self::assertCount(2, $logged);

        $broken = new class implements SafeHttpDiagnostics {
            public int $attempts = 0;

            public function report(Throwable $failure): void
            {
                $this->attempts++;
                throw new RuntimeException('secret');
            }
        };
        $response = $this->request($this->application($broken), new PermissionDenied('secret'));
        self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
        self::assertSame(500, $response->getStatusCode());
        self::assertSame(1, $broken->attempts);

        $invalid = new class implements SafeHttpProjector {
            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                return new SafeHttpPresentation(data: ['private' => $failure]);
            }
        };
        $response = $this->request($this->application($this->diagnostics($reports, $this->logger($logged)), $invalid), new PermissionDenied('secret'));
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
        self::assertCount(3, $reports);
    }

    public function test_that_non_json_delegates_to_laravel_without_unredacted_reporting(): void
    {
        $reports = [];
        $logged = [];
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)));
        $failure = new NotFoundHttpException('secret');
        self::assertTrue($app->make(ExceptionHandler::class)->shouldReport($failure));
        $app['config']->set('app.debug', false);
        $response = $this->request($app, $failure, 'text/html');

        self::assertNotInstanceOf(JSendResponse::class, $response);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame([$failure], $reports);
        self::assertSame([['HTTP failure', ['type' => NotFoundHttpException::class]]], $logged);

        $direct = $app->make(ExceptionHandler::class)->render(Request::create('/', server: [
            'HTTP_ACCEPT' => 'text/html'
        ]), new NotFoundHttpException('secret'));
        self::assertNotInstanceOf(JSendResponse::class, $direct);
        self::assertSame(404, $direct->getStatusCode());
    }

    public function test_that_laravel_negotiation_and_route_middleware_preserve_safe_native_response(): void
    {
        $reports = [];
        $logged = [];
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)));
        $app->instance('safe-after', new class {
            public function handle(Request $request, \Closure $next): Response
            {
                $response = $next($request);
                $response->headers->set('X-Processed', 'yes');

                return $response;
            }
        });
        $failure = new MethodNotAllowedHttpException(['GET'], 'secret');
        $path = '/processed';
        $app['router']->get($path, static function () use ($failure): never {
            throw $failure;
        })->middleware('safe-after');
        $response = (new Kernel($app, $app['router']))->handle(Request::create($path, server: [
            'HTTP_ACCEPT' => 'application/json',
        ]));
        self::assertInstanceOf(Response::class, $response);
        self::assertSame('{"status":"fail","data":{"message":"Method not allowed."}}', $response->getContent());
        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET', $response->headers->get('Allow'));
        self::assertSame('yes', $response->headers->get('X-Processed'));
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame([$failure], $reports);

        $xhr = $this->request($app, new RuntimeException('secret'), '*/*', true);
        self::assertInstanceOf(Response::class, $xhr);
        self::assertSame('{"status":"error","message":"Internal server error."}', $xhr->getContent());
        self::assertSame(500, $xhr->getStatusCode());
    }

    /**
     * @param list<Throwable> $reports
     */
    private function diagnostics(array &$reports, AbstractLogger $logger): SafeHttpDiagnostics
    {
        return new class ($reports, $logger) implements SafeHttpDiagnostics {
            public function __construct(private array &$reports, private AbstractLogger $logger)
            {
            }

            public function report(Throwable $failure): void
            {
                $this->reports[] = $failure;
                $this->logger->error('HTTP failure', ['type' => $failure::class]);
            }
        };
    }

    /**
     * @param list<array{string, array<string, string>}> $logged
     */
    private function logger(array &$logged): AbstractLogger
    {
        return new class ($logged) extends AbstractLogger {
            public function __construct(private array &$logged)
            {
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logged[] = [(string) $message, $context];
            }
        };
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    private function application(SafeHttpDiagnostics $diagnostics, ?SafeHttpProjector $projector = null): Application
    {
        $app = new Application(__DIR__);
        $app->instance('config', new Repository([
            'app' => ['debug' => true, 'timezone' => 'UTC'],
            'view' => ['paths' => [], 'compiled' => sys_get_temp_dir()],
        ]));
        $app->register(FilesystemServiceProvider::class);
        $app->register(ViewServiceProvider::class);
        $app->instance('translator', new Translator(new ArrayLoader(), 'en'));
        $app->instance(LoggerInterface::class, new NullLogger());
        $app->instance(SafeHttpDiagnostics::class, $diagnostics);
        $app->instance(SafeHttpErrorPolicy::class, new SafeHttpErrorPolicy());
        if ($projector !== null) {
            $app->instance(SafeHttpProjector::class, $projector);
        }
        Facade::setFacadeApplication($app);
        $app->bootstrapWith([]);
        $app->boot();
        $app->singleton(ExceptionHandler::class, SafeLaravelExceptionHandler::class);

        return $app;
    }

    private function request(Application $app, Throwable $failure, string $accept = 'application/json', bool $xhr = false): Response
    {
        $path = '/secret/'.spl_object_id($failure);
        $app['router']->get($path, static function () use ($failure): never {
            throw $failure;
        });

        return (new Kernel($app, $app['router']))->handle(Request::create($path, server: [
            'HTTP_ACCEPT' => $accept,
            'HTTP_X_DEBUG' => 'true',
            'HTTP_X_REQUESTED_WITH' => $xhr ? 'XMLHttpRequest' : '',
        ]));
    }
}
