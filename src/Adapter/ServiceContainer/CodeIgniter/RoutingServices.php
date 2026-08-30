<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\ServiceContainer\CodeIgniter;

use CodeIgniter\Router\RouteCollectionInterface;
use Fight\Common\Adapter\Routing\CodeIgniter\CodeIgniterUrlGenerator;
use Fight\Common\Application\Routing\UrlGenerator;

/**
 * Class RoutingServices
 */
final class RoutingServices
{
    /**
     * Creates a native CodeIgniter URL generator
     */
    public static function urlGenerator(RouteCollectionInterface $routes, string $baseUrl): CodeIgniterUrlGenerator
    {
        return new CodeIgniterUrlGenerator($routes, $baseUrl);
    }

    /**
     * Creates the URL generator through its neutral contract
     */
    public static function routing(RouteCollectionInterface $routes, string $baseUrl): UrlGenerator
    {
        return self::urlGenerator($routes, $baseUrl);
    }
}
