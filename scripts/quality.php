<?php

declare(strict_types=1);

use Fight\Common\Tooling\QualityRunner;

require dirname(__DIR__).'/vendor/autoload.php';

exit(new QualityRunner()->run());
