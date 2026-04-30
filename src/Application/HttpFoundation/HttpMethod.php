<?php

declare(strict_types=1);

namespace Fight\Common\Application\HttpFoundation;

/**
 * Class HttpMethod
 */
final class HttpMethod
{
    public const string HEAD = 'HEAD';
    public const string GET = 'GET';
    public const string POST = 'POST';
    public const string PUT = 'PUT';
    public const string PATCH = 'PATCH';
    public const string DELETE = 'DELETE';
    public const string PURGE = 'PURGE';
    public const string OPTIONS = 'OPTIONS';
    public const string TRACE = 'TRACE';
    public const string CONNECT = 'CONNECT';
}
