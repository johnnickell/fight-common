<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Http\Symfony;

use Error;
use Fight\Common\Adapter\Http\Error\SafeHttpDiagnostics;
use Fight\Common\Adapter\Http\Error\SafeHttpFailure;
use Fight\Common\Adapter\Http\Error\SafeHttpErrorPolicy;
use Fight\Common\Adapter\Http\Error\SafeHttpMetadata;
use Fight\Common\Adapter\Http\Error\SafeHttpPresentation;
use Fight\Common\Adapter\Http\Error\SafeHttpProjector;
use Fight\Common\Adapter\Http\Symfony\Controller\SafeErrorController;
use Fight\Common\Adapter\Http\Symfony\EventSubscriber\SafeSymfonyExceptionSubscriber;
use Fight\Common\Adapter\Http\Symfony\EventSubscriber\SymfonyExceptionSubscriber;
use Fight\Common\Adapter\Http\Symfony\JSendResponse;
use Fight\Common\Adapter\Http\Symfony\SymfonyHttpFailure;
use Fight\Common\Application\Attribute\Validation;
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
use Fight\Common\Application\Validation\ValidationService;
use Fight\Common\Domain\Exception\ValidationException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Psr\Log\AbstractLogger;
use Stringable;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\ErrorListener;
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
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

#[CoversClass(SafeHttpFailure::class)]
#[CoversClass(SafeErrorController::class)]
#[CoversClass(SafeSymfonyExceptionSubscriber::class)]
#[CoversClass(SymfonyHttpFailure::class)]
final class SafeSymfonyErrorTest extends UnitTestCase
{
    public static int $encodingCalls = 0;

    public static ?int $encodingFailures = 0;

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_kernel_contains_persistent_encoding_faults_without_recursive_reporting(): void
    {
        require dirname(__DIR__, 3).'/Fixture/Http/jsend-symfony-encoding.php';
        self::$encodingFailures = null;
        $reports = [];
        $failure = new MethodNotAllowedHttpException(['GET'], 'password=synthetic-secret');

        $response = $this->dispatch($failure, 'application/json', $this->diagnostics($reports));

        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
        self::assertFalse($response->headers->has('Allow'));
        self::assertSame([$failure], $reports);
        self::assertSame(2, self::$encodingCalls);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_direct_controller_bounds_one_two_and_persistent_encoding_faults(): void
    {
        require dirname(__DIR__, 3).'/Fixture/Http/jsend-symfony-encoding.php';
        $reports = [];
        $projector = new class implements SafeHttpProjector {
            public int $calls = 0;

            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                $this->calls++;

                return new SafeHttpPresentation(message: 'Deliberately public.', data: ['selected' => 'projection']);
            }
        };
        $controller = new SafeErrorController(new SafeHttpErrorPolicy(), $this->diagnostics($reports), $projector);
        $failures = [];
        foreach ([1, 2, null] as $budget) {
            self::$encodingCalls = 0;
            self::$encodingFailures = $budget;
            $failure = new HttpException(503, 'password=synthetic-secret', headers: ['Retry-After' => '15']);
            $failures[] = $failure;

            $this->assertTerminalResponse($controller->handle($failure));

            self::assertSame(2, self::$encodingCalls);
            self::assertSame($failures, $reports);
            self::assertSame(count($failures), $projector->calls);
        }
        self::$encodingFailures = 0;
        $later = new PermissionDenied('later=synthetic-secret');
        $response = $controller->handle($later);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('{"status":"fail","data":{"selected":"projection"}}', $response->getContent());
        self::assertSame([...$failures, $later], $reports);
        self::assertSame(4, $projector->calls);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_encoding_faults_preserve_kernel_final_processing_and_later_requests(): void
    {
        require dirname(__DIR__, 3).'/Fixture/Http/jsend-symfony-encoding.php';
        $reports = [];
        $rawLogged = [];
        $logger = new class ($rawLogged) extends AbstractLogger {
            public function __construct(private array &$logged)
            {
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logged[] = (string) $message;
            }
        };
        $dispatcher = $this->dispatcher($this->diagnostics($reports));
        $dispatcher->addSubscriber(new ErrorListener(null, $logger));
        $delegations = 0;
        $dispatcher->addListener(KernelEvents::EXCEPTION, static function ($event) use (&$delegations): void {
            $delegations++;
            $event->setResponse(new Response('delegated HTML', 299));
            $event->allowCustomResponseCode();
        }, -200);
        $processed = [];
        $dispatcher->addListener(KernelEvents::RESPONSE, static function (ResponseEvent $event) use (&$processed): void {
            $event->getResponse()->headers->set('X-Processed', 'yes');
            $processed[] = $event->getResponse();
        });
        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), handleAllThrowables: true);
        $failures = [];
        foreach ([1, 2, null] as $index => $budget) {
            self::$encodingCalls = 0;
            self::$encodingFailures = $budget;
            $failure = new HttpException(401, 'password=synthetic-secret', headers: [
                'WWW-Authenticate' => 'Basic realm="selected"',
                'X-Leak' => 'synthetic-secret',
            ]);
            $failures[] = $failure;
            $request = $this->request($failure);

            $response = $kernel->handle($request);
            $response->prepare($request);

            $this->assertTerminalResponse($response);
            self::assertSame('yes', $response->headers->get('X-Processed'));
            self::assertSame($response, $processed[$index]);
            self::assertSame(2, self::$encodingCalls);
            self::assertSame($failures, $reports);
            self::assertSame([], $rawLogged);
            self::assertSame(0, $delegations);
        }
        self::$encodingFailures = 0;
        $later = new MethodNotAllowedHttpException(['GET', 'POST'], 'later=synthetic-secret');
        $response = $kernel->handle($this->request($later));
        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET, POST', $response->headers->get('Allow'));
        self::assertSame('{"status":"fail","data":{"message":"Method not allowed."}}', $response->getContent());
        self::assertSame($response, $processed[3]);
        self::assertSame([...$failures, $later], $reports);
        self::assertSame([], $rawLogged);

        $html = $kernel->handle($this->request(new RuntimeException('delegated=synthetic-secret'), 'text/html'));
        self::assertSame(299, $html->getStatusCode());
        self::assertSame('delegated HTML', $html->getContent());
        self::assertSame(1, $delegations);
        self::assertCount(1, $rawLogged);
        self::assertCount(4, $reports);
        $request = Request::create('/successful');
        $request->attributes->set('_controller', static fn (): Response => new Response('unchanged', 202));
        $success = $kernel->handle($request);
        self::assertSame(202, $success->getStatusCode());
        self::assertSame('unchanged', $success->getContent());
        self::assertSame($success, $processed[5]);
        self::assertCount(4, $reports);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_that_failed_sink_or_projector_plus_encoder_is_terminal_without_collaborator_retries(): void
    {
        require dirname(__DIR__, 3).'/Fixture/Http/jsend-symfony-encoding.php';
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

                        return new SafeHttpPresentation(data: ['selected' => 'projection']);
                    }
                };
                $diagnostics->fail = $stage === 'sink';
                $projector->fail = $stage === 'projector';
                $controller = new SafeErrorController(new SafeHttpErrorPolicy(), $diagnostics, $projector);
                $dispatcher = new EventDispatcher();
                $dispatcher->addListener(KernelEvents::EXCEPTION, [
                    new SafeSymfonyExceptionSubscriber($controller), 'onKernelException',
                ], 64);
                $rawCallbacks = [];
                $dispatcher->addListener(KernelEvents::EXCEPTION, static function () use (&$rawCallbacks): void {
                    $rawCallbacks[] = 'unsafe fallback';
                }, 0);
                $kernel = new HttpKernel($dispatcher, new ControllerResolver(), handleAllThrowables: true);
                $failure = new HttpException(405, 'private=synthetic-secret', headers: ['Allow' => 'GET']);
                self::$encodingCalls = 0;
                self::$encodingFailures = null;

                $response = $direct ? $controller->handle($failure) : $kernel->handle($this->request($failure));

                $this->assertTerminalResponse($response);
                self::assertSame(1, self::$encodingCalls);
                self::assertSame([$failure], $diagnostics->reports);
                self::assertSame($stage === 'sink' ? 0 : 1, $diagnostics->deliveries);
                self::assertSame($stage === 'sink' ? 0 : 1, $projector->calls);
                self::assertSame([], $rawCallbacks);

                self::$encodingFailures = 0;
                $diagnostics->fail = false;
                $projector->fail = false;
                $later = new PermissionDenied('later=synthetic-secret');
                $response = $direct ? $controller->handle($later) : $kernel->handle($this->request($later));
                self::assertSame(403, $response->getStatusCode());
                self::assertSame('{"status":"fail","data":{"selected":"projection"}}', $response->getContent());
                self::assertSame([$failure, $later], $diagnostics->reports);
                self::assertSame($stage === 'sink' ? 1 : 2, $diagnostics->deliveries);
                self::assertSame($stage === 'sink' ? 1 : 2, $projector->calls);
                self::assertSame([], $rawCallbacks);
            }
        }
    }

    public function test_that_kernel_conceals_a_synthetic_secret_in_the_opted_in_json_path(): void
    {
        $failure = new RuntimeException('password=synthetic-secret', 401, new RuntimeException('cause=synthetic-secret'));
        $reports = [];
        $response = $this->dispatch($failure, 'application/json', $this->diagnostics($reports));

        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
        self::assertSame([$failure], $reports);
    }

    public function test_that_kernel_maps_every_application_failure_without_leaking_diagnostics(): void
    {
        $cases = [
            [new AuthenticationRequired('secret'), 500, 'error', 'Internal server error.'],
            [new PermissionDenied('secret'), 403, 'fail', 'Permission denied.'],
            [new class ('secret') extends PermissionDenied {}, 403, 'fail', 'Permission denied.'],
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
        foreach ($cases as [$failure, $status, $shape, $message]) {
            $reports = [];
            $response = $this->dispatch($failure, 'application/json', $this->diagnostics($reports));
            self::assertSame($status, $response->getStatusCode());
            $body = json_decode((string) $response->getContent(), true);
            self::assertSame($shape, $body['status']);
            self::assertSame($shape === 'fail' ? ['message' => $message] : $message, $body[$shape === 'fail' ? 'data' : 'message']);
            self::assertStringNotContainsString('secret', (string) $response->getContent());
            self::assertSame([$failure], $reports);
        }
    }

    public function test_that_kernel_maps_supported_native_failures_and_only_applicable_headers(): void
    {
        $cases = [
            [new BadRequestHttpException('secret'), 400, 'Invalid input.'],
            [new UnauthorizedHttpException('Basic realm="test"', 'secret'), 401, 'Authentication required.'],
            [new AccessDeniedHttpException('secret'), 403, 'Permission denied.'],
            [new NotFoundHttpException('secret'), 404, 'Resource not found.'],
            [new MethodNotAllowedHttpException(['GET', 'POST'], 'secret'), 405, 'Method not allowed.'],
            [new NotAcceptableHttpException('secret'), 406, 'Not acceptable.'],
            [new ConflictHttpException('secret'), 409, 'State conflict.'],
            [new GoneHttpException('secret'), 410, 'Resource gone.'],
            [new PreconditionFailedHttpException('secret'), 412, 'Precondition failed.'],
            [new HttpException(413, 'secret'), 413, 'Request too large.'],
            [new UnsupportedMediaTypeHttpException('secret'), 415, 'Unsupported media type.'],
            [new UnprocessableEntityHttpException('secret'), 422, 'Rule violation.'],
            [new TooManyRequestsHttpException(message: 'secret'), 429, 'Too many requests.'],
            [new TooManyRequestsHttpException(message: 'secret', headers: ['Retry-After' => '0']), 429, 'Too many requests.'],
            [new HttpException(502, 'secret'), 502, 'Internal server error.'],
            [new ServiceUnavailableHttpException(message: 'secret', headers: ['Retry-After' => '15']), 503, 'Service unavailable.'],
            [new HttpException(504, 'secret'), 504, 'Internal server error.'],
        ];
        foreach ($cases as [$failure, $status, $message]) {
            $reports = [];
            $response = $this->dispatch($failure, 'application/json', $this->diagnostics($reports));
            self::assertSame($status, $response->getStatusCode());
            $body = json_decode((string) $response->getContent(), true);
            self::assertSame($status >= 500 ? 'error' : 'fail', $body['status']);
            self::assertSame($message, $status >= 500 ? $body['message'] : $body['data']['message']);
            self::assertStringNotContainsString('secret', (string) $response->getContent());
            self::assertSame([$failure], $reports);
        }

        $reports = [];
        $auth = $this->dispatch(new UnauthorizedHttpException('Basic realm="test"'), 'application/json', $this->diagnostics($reports));
        self::assertSame('Basic realm="test"', $auth->headers->get('WWW-Authenticate'));
        $method = $this->dispatch(new MethodNotAllowedHttpException(['GET', 'POST']), 'application/json', $this->diagnostics($reports));
        self::assertSame('GET, POST', $method->headers->get('Allow'));
        $retry = $this->dispatch(new TooManyRequestsHttpException(message: 'secret', headers: ['Retry-After' => '0']), 'application/json', $this->diagnostics($reports));
        self::assertSame('0', $retry->headers->get('Retry-After'));
    }

    public function test_that_invalid_or_unsupported_native_metadata_fails_closed_after_kernel_processing(): void
    {
        $cases = [
            new HttpException(401, 'secret'),
            new HttpException(405, 'secret'),
            new HttpException(401, 'secret', headers: ['WWW-Authenticate' => "Basic\r\nX-Leak: secret"]),
            new HttpException(405, 'secret', headers: ['Allow' => "GET\r\nX-Leak: secret"]),
            new HttpException(429, 'secret', headers: ['Retry-After' => '999999999999999999999999']),
            new HttpException(503, 'secret', headers: ['Retry-After' => 'tomorrow']),
            new HttpException(503, 'secret', headers: ['Retry-After' => '-2']),
            new HttpException(429, 'secret', headers: ['Retry-After' => '1.0']),
            new HttpException(418, 'secret', headers: ['X-Leak' => 'secret']),
            new RuntimeException('secret', 404),
        ];
        foreach ($cases as $failure) {
            $reports = [];
            $response = $this->dispatch($failure, 'application/json', $this->diagnostics($reports));
            self::assertSame(500, $response->getStatusCode());
            self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
            self::assertFalse($response->headers->has('X-Leak'));
            self::assertFalse($response->headers->has('WWW-Authenticate'));
            self::assertFalse($response->headers->has('Allow'));
            self::assertSame([$failure], $reports);
        }
    }

    public function test_that_native_translation_rejects_duplicate_header_and_unknown_subtype(): void
    {
        $ordinary = new RuntimeException('secret');
        self::assertSame($ordinary, SymfonyHttpFailure::translate($ordinary));
        self::assertInstanceOf(SafeHttpFailure::class, SymfonyHttpFailure::translate(new HttpException(413)));

        $subtype = new class (404) extends HttpException {};
        self::assertSame($subtype, SymfonyHttpFailure::translate($subtype));

        $duplicate = new HttpException(405, headers: ['Allow' => 'GET', 'allow' => 'POST']);
        $reports = [];
        $response = $this->dispatch($duplicate, 'application/json', $this->diagnostics($reports));
        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($response->headers->has('Allow'));

        $arrayHeader = new HttpException(401, headers: ['WWW-Authenticate' => ['Basic realm="bad"']]);
        $response = $this->dispatch($arrayHeader, 'application/json', $this->diagnostics($reports));
        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function test_that_validation_subscriber_failure_is_fixed_without_public_field_data(): void
    {
        $reports = [];
        $dispatcher = $this->dispatcher($this->diagnostics($reports));
        $dispatcher->addSubscriber(new \Fight\Common\Adapter\Http\Symfony\EventSubscriber\SymfonyValidationSubscriber(new ValidationService()));
        $request = Request::create('/validate', 'POST', [], server: ['HTTP_ACCEPT' => 'application/json']);
        $request->attributes->set('_controller', [new class {
            #[Validation(rules: [['field' => 'email', 'label' => 'password=synthetic-secret', 'rules' => 'required']])]
            public function handle(): Response
            {
                return new Response('should not execute');
            }
        }, 'handle']);
        $response = (new HttpKernel($dispatcher, new ControllerResolver(), handleAllThrowables: true))->handle($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('{"status":"fail","data":{"message":"Invalid input."}}', $response->getContent());
    }

    public function test_that_json_preference_and_xhr_select_safe_handling_but_html_delegates(): void
    {
        foreach (['application/json;q=0.9,text/html;q=0.8', 'application/json', 'text/html,application/json;q=0.9', null] as $accept) {
            $reports = [];
            $response = $this->dispatch(new RuntimeException('secret'), $accept, $this->diagnostics($reports), false);
            if ($accept === null || str_starts_with($accept, 'text/html')) {
                self::assertSame(299, $response->getStatusCode());
                self::assertSame([], $reports);
            } else {
                self::assertSame(500, $response->getStatusCode());
                self::assertCount(1, $reports);
            }
        }
        $reports = [];
        $response = $this->dispatch(new RuntimeException('secret'), 'text/html', $this->diagnostics($reports), false, true);
        self::assertSame(500, $response->getStatusCode());
        self::assertCount(1, $reports);
    }

    public function test_that_explicit_container_composition_selects_only_the_safe_listener(): void
    {
        $reports = [];
        $container = new ContainerBuilder();
        $container->register(SafeHttpDiagnostics::class, $this->diagnostics($reports)::class)
            ->setSynthetic(true)->setPublic(true);
        $container->register(SafeHttpErrorPolicy::class, SafeHttpErrorPolicy::class)->setPublic(true);
        $container->register(SafeErrorController::class, SafeErrorController::class)->setAutowired(true)->setPublic(true);
        $container->register(SafeSymfonyExceptionSubscriber::class, SafeSymfonyExceptionSubscriber::class)
            ->setAutowired(true)->setPublic(true)
            ->addTag('kernel.event_listener', [
                'event' => KernelEvents::EXCEPTION, 'method' => 'onKernelException', 'priority' => 64,
            ]);
        $container->compile();
        $container->set(SafeHttpDiagnostics::class, $this->diagnostics($reports));
        self::assertSame(64, $container->getDefinition(SafeSymfonyExceptionSubscriber::class)
            ->getTag('kernel.event_listener')[0]['priority']);
        self::assertFalse($container->has(SymfonyExceptionSubscriber::class));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, [
            $container->get(SafeSymfonyExceptionSubscriber::class), 'onKernelException',
        ], 64);
        $request = Request::create('/', server: ['HTTP_ACCEPT' => 'application/json']);
        $request->attributes->set('_controller', static function (): never {
            throw new RuntimeException('password=synthetic-secret');
        });
        $response = (new HttpKernel($dispatcher, new ControllerResolver(), handleAllThrowables: true))->handle($request);
        self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
        self::assertCount(1, $reports);
    }

    public function test_that_framework_error_listener_does_not_duplicate_json_reporting(): void
    {
        $logged = [];
        $logger = new class ($logged) extends AbstractLogger {
            public function __construct(private array &$logged)
            {
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logged[] = (string) $message;
            }
        };
        $reports = [];
        $redacting = new class ($logger, $reports) implements SafeHttpDiagnostics {
            public function __construct(private AbstractLogger $logger, private array &$reports)
            {
            }

            public function report(Throwable $failure): void
            {
                $this->reports[] = $failure;
                // Restrict the trusted payload before any logger/formatter sees it.
                $this->logger->error('HTTP failure', ['type' => $failure::class]);
            }
        };
        $dispatcher = $this->dispatcher($redacting);
        $dispatcher->addSubscriber(new ErrorListener(null, $logger));
        $dispatcher->addListener(KernelEvents::EXCEPTION, static function ($event): void {
            $event->setResponse(new Response('delegated', 299));
            $event->allowCustomResponseCode();
        }, -200);
        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), handleAllThrowables: true);
        foreach (['application/json', 'text/html'] as $accept) {
            $request = Request::create('/secret', server: ['HTTP_ACCEPT' => $accept]);
            $request->attributes->set('_controller', static function (): never {
                throw new RuntimeException('password=synthetic-secret');
            });
            $response = $kernel->handle($request);
            self::assertSame($accept === 'application/json' ? 500 : 299, $response->getStatusCode());
        }
        self::assertCount(1, $reports);
        self::assertCount(2, $logged);
        self::assertSame('HTTP failure', $logged[0]);
        self::assertStringContainsString('synthetic-secret', $logged[1]);
    }

    public function test_that_trusted_projection_and_reporting_failures_are_owned_once(): void
    {
        $reports = [];
        $projector = new class implements SafeHttpProjector {
            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                if ($status === 401) {
                    return new SafeHttpPresentation(metadata: new SafeHttpMetadata(challenge: 'Basic realm="consumer"'));
                }
                return new SafeHttpPresentation(data: ['field' => ['Public-safe text.']]);
            }
        };
        $response = $this->dispatch(new ValidationException(['field' => ['secret']]), 'application/json', $this->diagnostics($reports), true, false, $projector);
        self::assertSame('{"status":"fail","data":{"field":["Public-safe text."]}}', $response->getContent());
        $response = $this->dispatch(new AuthenticationRequired('secret'), 'application/json', $this->diagnostics($reports), true, false, $projector);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Basic realm="consumer"', $response->headers->get('WWW-Authenticate'));
        self::assertCount(2, $reports);

        $broken = new class implements SafeHttpDiagnostics {
            public function report(Throwable $failure): void
            {
                throw new RuntimeException('secret');
            }
        };
        $response = $this->dispatch(new PermissionDenied('secret'), 'application/json', $broken);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());

        $invalid = new class implements SafeHttpProjector {
            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                return new SafeHttpPresentation(data: ['private' => $failure]);
            }
        };
        $response = $this->dispatch(new PermissionDenied('secret'), 'application/json', $this->diagnostics($reports), true, false, $invalid);
        self::assertSame(500, $response->getStatusCode());
        self::assertCount(3, $reports);

        $custom = new class implements SafeHttpProjector {
            public function project(Throwable $failure, int $status): ?SafeHttpPresentation
            {
                return new SafeHttpPresentation(message: 'Temporarily offline.', data: ['retry' => false]);
            }
        };
        $response = $this->dispatch(new ServiceUnavailable('secret'), 'application/json', $this->diagnostics($reports), true, false, $custom);
        self::assertSame(['status' => 'error', 'message' => 'Temporarily offline.', 'data' => ['retry' => false]], json_decode((string) $response->getContent(), true));
        self::assertCount(4, $reports);
    }

    private function assertTerminalResponse(Response $response): void
    {
        self::assertInstanceOf(JSendResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"status":"error","message":"Internal server error."}', $response->getContent());
        foreach (['Allow', 'WWW-Authenticate', 'Retry-After', 'X-Leak'] as $header) {
            self::assertFalse($response->headers->has($header));
        }
    }

    private function request(Throwable $failure, string $accept = 'application/json'): Request
    {
        $request = Request::create('/secret', server: ['HTTP_ACCEPT' => $accept, 'HTTP_X_DEBUG' => 'true']);
        $request->attributes->set('_controller', static function () use ($failure): never {
            throw $failure;
        });

        return $request;
    }

    /**
     * @param list<Throwable> $reports
     */
    private function diagnostics(array &$reports): SafeHttpDiagnostics
    {
        return new class ($reports) implements SafeHttpDiagnostics {
            public function __construct(private array &$reports)
            {
            }

            public function report(Throwable $failure): void
            {
                $this->reports[] = $failure;
            }
        };
    }

    private function dispatcher(SafeHttpDiagnostics $diagnostics, ?SafeHttpProjector $projector = null): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, [new SafeSymfonyExceptionSubscriber(
            new SafeErrorController(new SafeHttpErrorPolicy(), $diagnostics, $projector)
        ), 'onKernelException'], 64);
        return $dispatcher;
    }

    private function dispatch(
        Throwable $failure,
        ?string $accept,
        SafeHttpDiagnostics $diagnostics,
        bool $catch = true,
        bool $xhr = false,
        ?SafeHttpProjector $projector = null
    ): Response {
        $dispatcher = $this->dispatcher($diagnostics, $projector);
        if (!$catch) {
            $dispatcher->addListener(KernelEvents::EXCEPTION, static function ($event): void {
                $event->setResponse(new Response('delegated', 299));
                $event->allowCustomResponseCode();
            }, -128);
        }
        $kernel = new HttpKernel($dispatcher, new ControllerResolver(), handleAllThrowables: true);
        $request = Request::create('/secret', server: array_filter([
            'HTTP_ACCEPT' => $accept,
            'HTTP_X_REQUESTED_WITH' => $xhr ? 'XMLHttpRequest' : null,
            'HTTP_X_DEBUG' => 'true',
        ]));
        $request->attributes->set('_controller', static function () use ($failure): never {
            throw $failure;
        });

        return $kernel->handle($request);
    }
}
