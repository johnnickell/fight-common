<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Laravel;

use Closure;
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
use Illuminate\Foundation\Http\Events\RequestHandled;
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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
    public static int $encodingCalls = 0;

    public static ?int $encodingFailures = 0;

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_persistent_encoding_faults_return_a_terminal_native_response_without_recursive_reporting(): void
    {
        require dirname(__DIR__, 3).'/Fixture/Http/jsend-encoding.php';
        self::$encodingFailures = null;
        $reports = [];
        $logged = [];
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)));
        $failure = new MethodNotAllowedHttpException(['GET'], 'password=synthetic-secret');

        $response = $this->request($app, $failure);

        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
        self::assertFalse($response->headers->has('Allow'));
        self::assertSame([$failure], $reports);
        self::assertSame([['HTTP failure', ['type' => $failure::class]]], $logged);
        self::assertSame(2, self::$encodingCalls);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_encoding_faults_preserve_final_processing_and_later_independent_requests(): void
    {
        require dirname(__DIR__, 3).'/Fixture/Http/jsend-encoding.php';
        $reports = [];
        $logged = [];
        $rawLogged = [];
        $hooks = [];
        $handled = [];
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)));
        $app->instance(LoggerInterface::class, $this->logger($rawLogged));
        $handler = $app->make(ExceptionHandler::class);
        $handler->reportable(static function (Throwable $failure) use (&$hooks): void {
            $hooks[] = 'reportable';
        });
        $handler->renderable(static function (Throwable $failure) use (&$hooks): Response {
            $hooks[] = 'renderable';

            return new Response('private hook output');
        });
        $app['events']->listen(RequestHandled::class, static function (RequestHandled $event) use (&$handled): void {
            $handled[] = $event->response;
        });
        $app->instance('terminal-after', new class {
            public function handle(Request $request, Closure $next): Response
            {
                $response = $next($request);
                $response->headers->set('X-Processed', 'yes');

                return $response;
            }
        });
        $failures = [];
        foreach ([1, 2, null] as $index => $budget) {
            self::$encodingCalls = 0;
            self::$encodingFailures = $budget;
            $failure = new HttpException(401, 'password=synthetic-secret', headers: [
                'WWW-Authenticate' => 'Basic realm="selected"',
                'X-Leak' => 'synthetic-secret',
            ]);
            $failures[] = $failure;
            $path = '/terminal/'.$index;
            $app['router']->get($path, static function () use ($failure): never {
                throw $failure;
            })->middleware('terminal-after');

            $response = (new Kernel($app, $app['router']))->handle(Request::create($path, server: [
                'HTTP_ACCEPT' => 'application/json',
            ]));

            $this->assertTerminalResponse($response);
            self::assertSame('yes', $response->headers->get('X-Processed'));
            self::assertSame($response, $handled[$index]);
            self::assertSame(2, self::$encodingCalls);
            self::assertSame($failures, $reports);
            self::assertCount($index + 1, $logged);
            self::assertSame([], $rawLogged);
            self::assertSame([], $hooks);
        }

        self::$encodingFailures = 0;
        $laterFailure = new MethodNotAllowedHttpException(['GET'], 'later=synthetic-secret');
        $laterResponse = $this->request($app, $laterFailure);
        self::assertSame($handler, $app->make(ExceptionHandler::class));
        self::assertInstanceOf(JSendResponse::class, $laterResponse);
        self::assertSame(405, $laterResponse->getStatusCode());
        self::assertSame('GET', $laterResponse->headers->get('Allow'));
        self::assertSame('{"status":"fail","data":{"message":"Method not allowed."}}', $laterResponse->getContent());
        self::assertSame([...$failures, $laterFailure], $reports);
        self::assertSame($laterResponse, $handled[3]);
        self::assertSame([], $rawLogged);
        self::assertSame([], $hooks);

        $app['router']->get('/successful', static fn (): Response => new Response('unchanged', 202));
        $success = (new Kernel($app, $app['router']))->handle(Request::create('/successful'));
        self::assertSame(202, $success->getStatusCode());
        self::assertSame('unchanged', $success->getContent());
        self::assertCount(4, $reports);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_direct_render_contains_two_and_persistent_faults_with_one_original_report(): void
    {
        require dirname(__DIR__, 3).'/Fixture/Http/jsend-encoding.php';
        $reports = [];
        $logged = [];
        $app = $this->application($this->diagnostics($reports, $this->logger($logged)));
        $handler = $app->make(ExceptionHandler::class);
        $request = Request::create('/', server: ['HTTP_ACCEPT' => 'application/json']);
        $failures = [];
        foreach ([2, null] as $budget) {
            self::$encodingFailures = $budget;
            self::$encodingCalls = 0;
            $failure = new HttpException(503, 'private=synthetic-secret', headers: ['Retry-After' => '15']);
            $failures[] = $failure;

            $this->assertTerminalResponse($handler->render($request, $failure));

            self::assertSame(2, self::$encodingCalls);
            $handler->report($failure);
            self::assertSame($failures, $reports);
        }
        self::$encodingFailures = 0;
        $failure = new PermissionDenied('later=synthetic-secret');
        $response = $handler->render($request, $failure);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('{"status":"fail","data":{"message":"Permission denied."}}', $response->getContent());
        self::assertSame([...$failures, $failure], $reports);
        self::assertCount(3, $logged);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_failed_sink_or_projector_and_encoder_are_terminal_without_retrying_collaborators(): void
    {
        require dirname(__DIR__, 3).'/Fixture/Http/jsend-encoding.php';
        foreach (['sink', 'projector'] as $stage) {
            foreach ([false, true] as $direct) {
                $diagnostics = new class implements SafeHttpDiagnostics {
                    public bool $fail = false;
                    public array $reports = [];
                    public int $deliveries = 0;

                    public function report(Throwable $failure): void
                    {
                        $this->reports[] = $failure;
                        if ($this->fail) {
                            throw new RuntimeException('sink=password=synthetic-secret');
                        }
                        $this->deliveries++;
                    }
                };
                $projector = new class implements SafeHttpProjector {
                    public bool $fail = false;
                    public int $calls = 0;

                    public function project(Throwable $failure, int $status): ?SafeHttpPresentation
                    {
                        $this->calls++;
                        if ($this->fail) {
                            throw new RuntimeException('projector=password=synthetic-secret');
                        }

                        return new SafeHttpPresentation(message: 'Deliberately public.');
                    }
                };
                $diagnostics->fail = $stage === 'sink';
                $projector->fail = $stage === 'projector';
                $app = $this->application($diagnostics, $projector);
                $rawLogged = [];
                $app->instance(LoggerInterface::class, $this->logger($rawLogged));
                $handler = $app->make(ExceptionHandler::class);
                $request = Request::create('/', server: ['HTTP_ACCEPT' => 'application/json']);
                $failure = new HttpException(405, 'private=synthetic-secret', headers: ['Allow' => 'GET']);
                self::$encodingCalls = 0;
                self::$encodingFailures = null;

                $response = $direct ? $handler->render($request, $failure) : $this->request($app, $failure);

                $this->assertTerminalResponse($response);
                self::assertSame(1, self::$encodingCalls);
                self::assertSame([$failure], $diagnostics->reports);
                self::assertSame($stage === 'sink' ? 0 : 1, $diagnostics->deliveries);
                self::assertSame($stage === 'sink' ? 0 : 1, $projector->calls);
                $handler->report($failure);
                $this->assertTerminalResponse($handler->render($request, $failure));
                self::assertSame([$failure], $diagnostics->reports);
                self::assertSame(2, self::$encodingCalls);
                self::assertSame($stage === 'sink' ? 0 : 2, $projector->calls);
                self::assertSame([], $rawLogged);

                self::$encodingFailures = 0;
                $diagnostics->fail = false;
                $projector->fail = false;
                $laterFailure = new PermissionDenied('later=synthetic-secret');
                $laterResponse = $this->request($app, $laterFailure);
                self::assertSame($handler, $app->make(ExceptionHandler::class));
                self::assertSame(403, $laterResponse->getStatusCode());
                self::assertSame('{"status":"fail","data":{"message":"Deliberately public."}}', $laterResponse->getContent());
                self::assertSame([$failure, $laterFailure], $diagnostics->reports);
                self::assertSame($stage === 'sink' ? 1 : 2, $diagnostics->deliveries);
                self::assertSame([], $rawLogged);
            }
        }
    }

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

    private function assertTerminalResponse(Response $response): void
    {
        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
        self::assertSame(['status' => 'error', 'message' => 'Internal server error.'], $response->getData(true));
        foreach (['WWW-Authenticate', 'Allow', 'Retry-After', 'X-Leak'] as $header) {
            self::assertFalse($response->headers->has($header));
        }
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
