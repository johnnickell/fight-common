<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use Fight\Common\Adapter\ServiceContainer\CodeIgniter\TemplateServices;
use Fight\Common\Application\Templating\TemplateEngine;
use RuntimeException;
use Twig\Environment;

/** Project-owned template-only Config\Services fixture. */
final class Services extends BaseService
{
    public static function fightTemplateEngine(bool $getShared = true): TemplateEngine
    {
        if ($getShared) {
            return static::getSharedInstance('fightTemplateEngine');
        }

        return TemplateServices::templateEngine(static::fightTwig());
    }

    private static function fightTwig(): Environment
    {
        $twig = static::get('fightTwigCollaborator');

        if (! $twig instanceof Environment) {
            throw new RuntimeException('The project Twig collaborator is unavailable.');
        }

        return $twig;
    }
}
