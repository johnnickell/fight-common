<?php

declare(strict_types=1);

namespace Fight\Release\Application\Boundary;

use RuntimeException;

/**
 * Class ReleaseRuntimeTermination
 *
 * Helper process or protocol termination outside the governed release result vocabulary.
 */
final class ReleaseRuntimeTermination extends RuntimeException
{
}
