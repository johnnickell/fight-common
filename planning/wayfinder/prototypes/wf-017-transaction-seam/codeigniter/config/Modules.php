<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Modules\Modules as BaseModules;

final class Modules extends BaseModules
{
    public $enabled = false;
    public $discoverInComposer = false;
    public $composerPackages = [];
    public $aliases = [];
}
