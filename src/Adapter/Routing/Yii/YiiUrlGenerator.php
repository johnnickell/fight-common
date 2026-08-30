<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Routing\Yii;

use Fight\Common\Application\Routing\Exception\InvalidParameterException;
use Fight\Common\Application\Routing\Exception\MissingParametersException;
use Fight\Common\Application\Routing\Exception\RouteNotFoundException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Common\Application\Routing\UrlGenerator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Yiisoft\Router\RouteNotFoundException as NativeRouteNotFoundException;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Class YiiUrlGenerator
 */
readonly class YiiUrlGenerator implements UrlGenerator
{
    /**
     * Constructs YiiUrlGenerator
     */
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * Generates a route URL and translates Yii generation failures
     *
     * @inheritDoc
     */
    public function generate(string $name, array $parameters = [], array $query = [], bool $absolute = false): string
    {
        try {
            if ($absolute) {
                return $this->urlGenerator->generateAbsolute($name, $parameters, $query);
            }

            return $this->urlGenerator->generate($name, $parameters, $query);
        } catch (Throwable $throwable) {
            if ($throwable instanceof NativeRouteNotFoundException) {
                throw new RouteNotFoundException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }

            if ($throwable instanceof InvalidArgumentException) {
                throw new InvalidParameterException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }

            if ($throwable instanceof RuntimeException) {
                if (
                    str_starts_with($throwable->getMessage(), 'Route `')
                    && str_contains($throwable->getMessage(), 'expects at least argument values')
                ) {
                    throw new MissingParametersException($throwable->getMessage(), $throwable->getCode(), $throwable);
                }

                if (str_starts_with($throwable->getMessage(), 'Argument value for [')) {
                    throw new InvalidParameterException($throwable->getMessage(), $throwable->getCode(), $throwable);
                }
            }

            throw new UrlGenerationException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }
}
