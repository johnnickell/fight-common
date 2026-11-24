<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Fight\Common\Tooling\QualityRunner;

// Load only dev tooling so malformed product PHP is first reported by the syntax stage.
$composerDirectory = dirname(__DIR__).'/vendor/composer';
require_once $composerDirectory.'/ClassLoader.php';
$prefixes = require $composerDirectory.'/autoload_psr4.php';
$toolingPrefix = 'Fight\\Common\\Tooling\\';
$loader = new ClassLoader();
$loader->setPsr4($toolingPrefix, $prefixes[$toolingPrefix] ?? []);
$loader->register();

exit(new QualityRunner()->run());
