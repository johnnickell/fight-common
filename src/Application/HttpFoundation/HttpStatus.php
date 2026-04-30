<?php

declare(strict_types=1);

namespace Fight\Common\Application\HttpFoundation;

/**
 * Class HttpStatus
 */
final class HttpStatus
{
    public const int CONTINUE = 100;
    public const int SWITCHING_PROTOCOLS = 101;
    public const int PROCESSING = 102;
    public const int OK = 200;
    public const int CREATED = 201;
    public const int ACCEPTED = 202;
    public const int NON_AUTHORITATIVE_INFORMATION = 203;
    public const int NO_CONTENT = 204;
    public const int RESET_CONTENT = 205;
    public const int PARTIAL_CONTENT = 206;
    public const int MULTI_STATUS = 207;
    public const int ALREADY_REPORTED = 208;
    public const int IM_USED = 226;
    public const int MULTIPLE_CHOICES = 300;
    public const int MOVED_PERMANENTLY = 301;
    public const int FOUND = 302;
    public const int SEE_OTHER = 303;
    public const int NOT_MODIFIED = 304;
    public const int USE_PROXY = 305;
    public const int RESERVED = 306;
    public const int TEMPORARY_REDIRECT = 307;
    public const int PERMANENT_REDIRECT = 308;
    public const int BAD_REQUEST = 400;
    public const int UNAUTHORIZED = 401;
    public const int PAYMENT_REQUIRED = 402;
    public const int FORBIDDEN = 403;
    public const int NOT_FOUND = 404;
    public const int METHOD_NOT_ALLOWED = 405;
    public const int NOT_ACCEPTABLE = 406;
    public const int PROXY_AUTHENTICATION_REQUIRED = 407;
    public const int REQUEST_TIMEOUT = 408;
    public const int CONFLICT = 409;
    public const int GONE = 410;
    public const int LENGTH_REQUIRED = 411;
    public const int PRECONDITION_FAILED = 412;
    public const int REQUEST_ENTITY_TOO_LARGE = 413;
    public const int REQUEST_URI_TOO_LONG = 414;
    public const int UNSUPPORTED_MEDIA_TYPE = 415;
    public const int REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    public const int EXPECTATION_FAILED = 417;
    public const int I_AM_A_TEAPOT = 418;
    public const int ENHANCE_YOUR_CALM = 420;
    public const int MISDIRECTED_REQUEST = 421;
    public const int UNPROCESSABLE_ENTITY = 422;
    public const int LOCKED = 423;
    public const int FAILED_DEPENDENCY = 424;
    public const int UNORDERED_COLLECTION = 425;
    public const int UPGRADE_REQUIRED = 426;
    public const int PRECONDITION_REQUIRED = 428;
    public const int TOO_MANY_REQUESTS = 429;
    public const int REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    public const int UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    public const int INTERNAL_SERVER_ERROR = 500;
    public const int NOT_IMPLEMENTED = 501;
    public const int BAD_GATEWAY = 502;
    public const int SERVICE_UNAVAILABLE = 503;
    public const int GATEWAY_TIMEOUT = 504;
    public const int VERSION_NOT_SUPPORTED = 505;
    public const int VARIANT_ALSO_NEGOTIATES = 506;
    public const int INSUFFICIENT_STORAGE = 507;
    public const int LOOP_DETECTED = 508;
    public const int NOT_EXTENDED = 510;
    public const int NETWORK_AUTHENTICATION_REQUIRED = 511;
}
