<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Routing\CodeIgniter;

use CodeIgniter\Router\RouteCollectionInterface;
use Fight\Common\Application\Routing\Exception\InvalidParameterException;
use Fight\Common\Application\Routing\Exception\MissingParametersException;
use Fight\Common\Application\Routing\Exception\RouteNotFoundException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Common\Application\Routing\UrlGenerator;
use InvalidArgumentException;
use Throwable;

/**
 * Class CodeIgniterUrlGenerator
 */
final readonly class CodeIgniterUrlGenerator implements UrlGenerator
{
    /**
     * Constructs CodeIgniterUrlGenerator
     */
    public function __construct(
        private RouteCollectionInterface $routes,
        private string $baseUrl
    ) {
    }

    /** @inheritDoc */
    public function generate(string $name, array $parameters = [], array $query = [], bool $absolute = false): string
    {
        try {
            $path = $this->routes->reverseRoute($name, ...array_values($parameters));

            if (! is_string($path)) {
                throw new RouteNotFoundException(
                    sprintf('Route "%s" was not found.', $name)
                );
            }

            if ($query !== []) {
                $queryString = http_build_query($query, encoding_type: PHP_QUERY_RFC3986);
                $path .= (str_contains($path, '?') ? '&' : '?').$queryString;
            }

            return $absolute ? rtrim($this->baseUrl, '/').$path : $path;
        } catch (RouteNotFoundException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            if (str_starts_with($exception->getMessage(), 'Missing argument for ')) {
                throw new MissingParametersException($exception->getMessage(), $exception->getCode(), $exception);
            }

            throw new InvalidParameterException($exception->getMessage(), $exception->getCode(), $exception);
        } catch (Throwable $exception) {
            throw new UrlGenerationException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
