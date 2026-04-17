<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Hmac;

use Exception;

/**
 * Class HmacKeyGenerator
 */
final class HmacKeyGenerator
{
    /**
     * Generates a secure key to use with HMAC authentication
     *
     * The key generated may be used as a public or private key
     *
     * @throws Exception
     */
    public static function generateSecureRandom(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
