<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Router\RouteCollectionInterface;
use Fight\Common\Adapter\Routing\CodeIgniter\CodeIgniterUrlGenerator;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\RoutingServices;
use Fight\Common\Application\Routing\UrlGenerator;
use RuntimeException;

/** Project-owned routing-only Config\Services fixture. */
final class Services extends BaseService
{
    public static function fightCodeIgniterUrlGenerator(bool $getShared = true): CodeIgniterUrlGenerator
    {
        if ($getShared) {
            return static::getSharedInstance('fightCodeIgniterUrlGenerator');
        }

        return RoutingServices::urlGenerator(static::fightRoutes(), 'https://fight.example');
    }

    public static function fightUrlGenerator(bool $getShared = true): UrlGenerator
    {
        return static::fightCodeIgniterUrlGenerator($getShared);
    }

    private static function fightRoutes(): RouteCollectionInterface
    {
        $routes = static::get('fightRoutesCollaborator');

        if (! $routes instanceof RouteCollectionInterface) {
            throw new RuntimeException('The project route collection collaborator is unavailable.');
        }

        return $routes;
    }
}
