<?php

declare(strict_types=1);

namespace Fight\Common\Application\Error\Exception;

use Fight\Common\Domain\Exception\SystemException;

/**
 * Class ApplicationException
 *
 * Diagnostic messages, codes and causes are not public response data.
 */
abstract class ApplicationException extends SystemException
{
}
