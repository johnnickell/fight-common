<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Routing\Slim;

use Fight\Common\Application\Routing\Exception\InvalidParameterException;
use Fight\Common\Application\Routing\Exception\RouteNotFoundException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Common\Application\Routing\UrlGenerator;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Slim\Interfaces\RouteCollectorInterface;
use Throwable;

/**
 * Class SlimUrlGenerator
 */
readonly class SlimUrlGenerator implements UrlGenerator
{
    /**
     * Constructs SlimUrlGenerator
     */
    public function __construct(
        private RouteCollectorInterface $routeCollector,
        private UriInterface $baseUri
    ) {
    }

    /** @inheritDoc */
    public function generate(string $name, array $parameters = [], array $query = [], bool $absolute = false): string
    {
        try {
            $routeParser = $this->routeCollector->getRouteParser();
            $parameters = $this->stringifyParameters($parameters);
            $query = $this->stringifyQuery($query);

            if ($absolute) {
                return $routeParser->fullUrlFor($this->baseUri, $name, $parameters, $query);
            }

            return $routeParser->urlFor($name, $parameters, $query);
        } catch (RuntimeException $e) {
            throw new RouteNotFoundException($e->getMessage(), $e->getCode(), $e);
        } catch (InvalidArgumentException $e) {
            throw new InvalidParameterException($e->getMessage(), $e->getCode(), $e);
        } catch (Throwable $e) {
            throw new UrlGenerationException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Converts route parameters to the native string representation
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, string>
     */
    private function stringifyParameters(array $parameters): array
    {
        return array_map(static fn(mixed $value): string => (string) $value, $parameters);
    }

    /**
     * Converts query parameters to the native string representation
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, string|array<array-key, string>>
     */
    private function stringifyQuery(array $query): array
    {
        return array_map(
            static function (mixed $value): string|array {
                if (is_array($value)) {
                    return array_map(static fn(mixed $item): string => (string) $item, $value);
                }

                return (string) $value;
            },
            $query
        );
    }
}
