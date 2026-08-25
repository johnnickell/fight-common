<?php

declare(strict_types=1);

require $argv[2];
require $argv[3];
require $argv[1];

$paths = [
    'Fight\\Common\\Adapter\\EventSubscriber\\SymfonyExceptionSubscriber',
    'Fight\\Common\\Adapter\\EventSubscriber\\SymfonyValidationSubscriber',
    'Fight\\Common\\Adapter\\HttpKernel\\JsonRequestMiddleware',
    'Fight\\Common\\Adapter\\HttpKernel\\ErrorController',
    'Fight\\Common\\Adapter\\Filesystem\\SymfonyFilesystem',
    'Fight\\Common\\Adapter\\Routing\\SymfonyUrlGenerator',
    'Fight\\Common\\Adapter\\Http\\Symfony\\EventSubscriber\\SymfonyExceptionSubscriber',
    'Fight\\Common\\Adapter\\Http\\Symfony\\EventSubscriber\\SymfonyValidationSubscriber',
    'Fight\\Common\\Adapter\\Middleware\\Symfony\\JsonRequestMiddleware',
    'Fight\\Common\\Adapter\\Http\\Symfony\\Controller\\ErrorController',
    'Fight\\Common\\Adapter\\Filesystem\\Symfony\\SymfonyFilesystem',
    'Fight\\Common\\Adapter\\Routing\\Symfony\\SymfonyUrlGenerator'
];

foreach ($paths as $path) {
    class_exists($path) || throw new RuntimeException(sprintf('Symfony adapter path is unavailable: %s', $path));
}
