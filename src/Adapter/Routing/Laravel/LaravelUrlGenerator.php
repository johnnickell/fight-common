<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Routing\Laravel;

use Fight\Common\Application\Routing\Exception\InvalidParameterException;
use Fight\Common\Application\Routing\Exception\MissingParametersException;
use Fight\Common\Application\Routing\Exception\RouteNotFoundException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Common\Application\Routing\UrlGenerator;
use Illuminate\Contracts\Routing\UrlGenerator as NativeUrlGenerator;
use Illuminate\Routing\Exceptions\UrlGenerationException as NativeUrlGenerationException;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollectionInterface;
use InvalidArgumentException;
use Symfony\Component\Routing\Exception\RouteNotFoundException as NativeRouteNotFoundException;
use Throwable;

/**
 * Class LaravelUrlGenerator
 *
 * Adapts Laravel named-route URL generation to the application routing port.
 */
readonly class LaravelUrlGenerator implements UrlGenerator
{
    /**
     * Constructs LaravelUrlGenerator
     */
    public function __construct(
        private NativeUrlGenerator $urlGenerator,
        private ?RouteCollectionInterface $routes = null
    ) {
    }

    /** @inheritDoc */
    public function generate(string $name, array $parameters = [], array $query = [], bool $absolute = false): string
    {
        try {
            $url = $this->urlGenerator->route($name, $parameters, $absolute);
            $this->assertRouteParametersMatchConstraints($name, $parameters);

            if ($query === []) {
                return $url;
            }

            $queryString = http_build_query($query, encoding_type: PHP_QUERY_RFC3986);

            return $url.(str_contains($url, '?') ? '&' : '?').$queryString;
        } catch (NativeRouteNotFoundException $exception) {
            throw new RouteNotFoundException($exception->getMessage(), $exception->getCode(), $exception);
        } catch (NativeUrlGenerationException $exception) {
            throw new MissingParametersException($exception->getMessage(), $exception->getCode(), $exception);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidParameterException($exception->getMessage(), $exception->getCode(), $exception);
        } catch (UrlGenerationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new UrlGenerationException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Validates Laravel route parameter constraints before exposing a URL
     *
     * @param string               $name
     * @param array<string, mixed> $parameters
     */
    private function assertRouteParametersMatchConstraints(string $name, array $parameters): void
    {
        $route = $this->routes?->getByName($name);

        if (!$route instanceof Route) {
            return;
        }

        foreach ($route->wheres as $parameter => $constraint) {
            if (!array_key_exists($parameter, $parameters)) {
                continue;
            }

            $matches = preg_match('{^(?:'.$constraint.')$}D', (string) $parameters[$parameter]);

            if ($matches !== 1) {
                throw new InvalidParameterException(
                    sprintf('Route parameter [%s] does not match its required constraint.', $parameter)
                );
            }
        }
    }
}
