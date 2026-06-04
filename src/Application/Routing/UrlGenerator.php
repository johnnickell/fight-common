<?php

declare(strict_types=1);

namespace Fight\Common\Application\Routing;

use Fight\Common\Application\Routing\Exception\UrlGenerationException;

/**
 * Interface UrlGenerator
 */
interface UrlGenerator
{
     /**
      * Generates a URL for the given route and parameters
      *
      * @param string $name
      * @param array<string, mixed> $parameters
      * @param array<string, mixed> $query
      * @param boolean $absolute
      *
      * @throws UrlGenerationException When an error occurs
      */
    public function generate(string $name, array $parameters = [], array $query = [], bool $absolute = false): string;
}
