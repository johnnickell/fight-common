<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Error\Exception;

use Error;
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
use Fight\Common\Application\Mcp\Tool\McpToolFailureMap;
use Fight\Common\Domain\Exception\Catchable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Exception\SystemException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;
use TypeError;

#[CoversClass(ApplicationException::class)]
#[CoversClass(AuthenticationRequired::class)]
#[CoversClass(PermissionDenied::class)]
#[CoversClass(ResourceNotFound::class)]
#[CoversClass(StateConflict::class)]
#[CoversClass(ResourceGone::class)]
#[CoversClass(PreconditionFailed::class)]
#[CoversClass(InvalidInput::class)]
#[CoversClass(RuleViolation::class)]
#[CoversClass(RateLimitExceeded::class)]
#[CoversClass(ServerError::class)]
#[CoversClass(ServiceUnavailable::class)]
class ApplicationExceptionTest extends UnitTestCase
{
    /**
     * @return list<class-string<ApplicationException>>
     */
    private static function categories(): array
    {
        return [
            AuthenticationRequired::class,
            PermissionDenied::class,
            ResourceNotFound::class,
            StateConflict::class,
            ResourceGone::class,
            PreconditionFailed::class,
            InvalidInput::class,
            RuleViolation::class,
            RateLimitExceeded::class,
            ServerError::class,
            ServiceUnavailable::class,
        ];
    }

    public function test_that_every_category_preserves_diagnostics_and_all_inherited_catches(): void
    {
        foreach (self::categories() as $category) {
            $cause = new Error('private cause');
            $failure = new $category('private token', -73, $cause);
            self::assertSame('private token', $failure->getMessage());
            self::assertSame(-73, $failure->getCode());
            self::assertSame($cause, $failure->getPrevious());
            self::assertInstanceOf($category, $failure);
            self::assertInstanceOf(ApplicationException::class, $failure);
            self::assertInstanceOf(SystemException::class, $failure);
            self::assertInstanceOf(Catchable::class, $failure);
            self::assertInstanceOf(\Exception::class, $failure);
            self::assertInstanceOf(Throwable::class, $failure);
            self::assertSame('', (new $category())->getMessage());
            self::assertSame(0, (new $category())->getCode());
        }
    }

    public function test_that_declared_categories_are_caught_by_base_and_native_throwable(): void
    {
        foreach (self::categories() as $category) {
            $failure = new $category('private');
            try {
                throw $failure;
            } catch (ApplicationException $caught) {
                self::assertSame($failure, $caught);
            }

            try {
                throw $failure;
            } catch (Throwable $caught) {
                self::assertSame($failure, $caught);
            }
        }
    }

    public function test_that_each_category_supports_consumer_subtypes_and_specific_catches(): void
    {
        $subtypes = [
            new class extends AuthenticationRequired {
            },
            new class extends PermissionDenied {
            },
            new class extends ResourceNotFound {
            },
            new class extends StateConflict {
            },
            new class extends ResourceGone {
            },
            new class extends PreconditionFailed {
            },
            new class extends InvalidInput {
            },
            new class extends RuleViolation {
            },
            new class extends RateLimitExceeded {
            },
            new class extends ServerError {
            },
            new class extends ServiceUnavailable {
            },
        ];
        foreach (self::categories() as $index => $category) {
            self::assertInstanceOf($category, $subtypes[$index]);
            self::assertInstanceOf(ApplicationException::class, $subtypes[$index]);
        }

        $failure = new ConsumerPermissionDenied('diagnostic');
        try {
            throw $failure;
        } catch (PermissionDenied $caught) {
            self::assertSame($failure, $caught);
            self::assertInstanceOf(ApplicationException::class, $caught);
        }
    }

    public function test_that_retry_hints_preserve_nullable_zero_positive_and_maximum_values(): void
    {
        foreach ([RateLimitExceeded::class, ServiceUnavailable::class] as $category) {
            foreach ([null, 0, 12, PHP_INT_MAX] as $seconds) {
                $failure = new $category('private', 17, new Error('cause'), $seconds);
                self::assertSame($seconds, $failure->retryDelaySeconds());
                self::assertSame(17, $failure->getCode());
            }
        }
    }

    public function test_that_negative_retry_delay_is_rejected_for_both_categories(): void
    {
        foreach ([RateLimitExceeded::class, ServiceUnavailable::class] as $category) {
            try {
                new $category(retryDelaySeconds: -1);
                self::fail('Negative delay accepted');
            } catch (DomainException $exception) {
                self::assertSame('Retry delay must be nonnegative.', $exception->getMessage());
            }
        }
    }

    public function test_that_native_type_rejection_is_not_wrapped(): void
    {
        foreach ([RateLimitExceeded::class, ServiceUnavailable::class] as $category) {
            try {
                (new \ReflectionClass($category))->newInstanceArgs(['', 0, null, 'invalid']);
                self::fail('Invalid type accepted');
            } catch (TypeError $exception) {
                self::assertInstanceOf(TypeError::class, $exception);
            }
        }
    }

    public function test_that_mcp_failure_mapping_remains_exact_class_even_for_subtypes(): void
    {
        $map = new McpToolFailureMap([PermissionDenied::class => 'Consumer-approved text']);
        self::assertSame('Consumer-approved text', $map->messageFor(new PermissionDenied('private')));
        self::assertNull($map->messageFor(new ConsumerPermissionDenied('private')));
        self::assertNull((new McpToolFailureMap())->messageFor(new PermissionDenied('private')));
    }
}

class ConsumerPermissionDenied extends PermissionDenied
{
}
