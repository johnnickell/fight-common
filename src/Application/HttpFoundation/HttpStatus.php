<?php

declare(strict_types=1);

namespace Fight\Common\Application\HttpFoundation;

/**
 * Enum HttpStatus
 */
enum HttpStatus: int
{
    case CONTINUE = 100;
    case SWITCHING_PROTOCOLS = 101;
    case PROCESSING = 102;
    case OK = 200;
    case CREATED = 201;
    case ACCEPTED = 202;
    case NON_AUTHORITATIVE_INFORMATION = 203;
    case NO_CONTENT = 204;
    case RESET_CONTENT = 205;
    case PARTIAL_CONTENT = 206;
    case MULTI_STATUS = 207;
    case ALREADY_REPORTED = 208;
    case IM_USED = 226;
    case MULTIPLE_CHOICES = 300;
    case MOVED_PERMANENTLY = 301;
    case FOUND = 302;
    case SEE_OTHER = 303;
    case NOT_MODIFIED = 304;
    case USE_PROXY = 305;
    case RESERVED = 306;
    case TEMPORARY_REDIRECT = 307;
    case PERMANENT_REDIRECT = 308;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case PAYMENT_REQUIRED = 402;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case NOT_ACCEPTABLE = 406;
    case PROXY_AUTHENTICATION_REQUIRED = 407;
    case REQUEST_TIMEOUT = 408;
    case CONFLICT = 409;
    case GONE = 410;
    case LENGTH_REQUIRED = 411;
    case PRECONDITION_FAILED = 412;
    case REQUEST_ENTITY_TOO_LARGE = 413;
    case REQUEST_URI_TOO_LONG = 414;
    case UNSUPPORTED_MEDIA_TYPE = 415;
    case REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    case EXPECTATION_FAILED = 417;
    case I_AM_A_TEAPOT = 418;
    case ENHANCE_YOUR_CALM = 420;
    case MISDIRECTED_REQUEST = 421;
    case UNPROCESSABLE_ENTITY = 422;
    case LOCKED = 423;
    case FAILED_DEPENDENCY = 424;
    case UNORDERED_COLLECTION = 425;
    case UPGRADE_REQUIRED = 426;
    case PRECONDITION_REQUIRED = 428;
    case TOO_MANY_REQUESTS = 429;
    case REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    case UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    case INTERNAL_SERVER_ERROR = 500;
    case NOT_IMPLEMENTED = 501;
    case BAD_GATEWAY = 502;
    case SERVICE_UNAVAILABLE = 503;
    case GATEWAY_TIMEOUT = 504;
    case VERSION_NOT_SUPPORTED = 505;
    case VARIANT_ALSO_NEGOTIATES = 506;
    case INSUFFICIENT_STORAGE = 507;
    case LOOP_DETECTED = 508;
    case NOT_EXTENDED = 510;
    case NETWORK_AUTHENTICATION_REQUIRED = 511;

    /**
     * Retrieves the HttpStatus text
     */
    public function text(): string
    {
        return match ($this) {
            HttpStatus::CONTINUE                        => 'Continue',
            HttpStatus::SWITCHING_PROTOCOLS             => 'Switching Protocols',
            HttpStatus::PROCESSING                      => 'Processing',
            HttpStatus::OK                              => 'OK',
            HttpStatus::CREATED                         => 'Created',
            HttpStatus::ACCEPTED                        => 'Accepted',
            HttpStatus::NON_AUTHORITATIVE_INFORMATION   => 'Non-Authoritative Information',
            HttpStatus::NO_CONTENT                      => 'No Content',
            HttpStatus::RESET_CONTENT                   => 'Reset Content',
            HttpStatus::PARTIAL_CONTENT                 => 'Partial Content',
            HttpStatus::MULTI_STATUS                    => 'Multi-Status',
            HttpStatus::ALREADY_REPORTED                => 'Already Reported',
            HttpStatus::IM_USED                         => 'IM Used',
            HttpStatus::MULTIPLE_CHOICES                => 'Multiple Choices',
            HttpStatus::MOVED_PERMANENTLY               => 'Moved Permanently',
            HttpStatus::FOUND                           => 'Found',
            HttpStatus::SEE_OTHER                       => 'See Other',
            HttpStatus::NOT_MODIFIED                    => 'Not Modified',
            HttpStatus::USE_PROXY                       => 'Use Proxy',
            HttpStatus::RESERVED                        => 'Reserved',
            HttpStatus::TEMPORARY_REDIRECT              => 'Temporary Redirect',
            HttpStatus::PERMANENT_REDIRECT              => 'Permanent Redirect',
            HttpStatus::BAD_REQUEST                     => 'Bad Request',
            HttpStatus::UNAUTHORIZED                    => 'Unauthorized',
            HttpStatus::PAYMENT_REQUIRED                => 'Payment Required',
            HttpStatus::FORBIDDEN                       => 'Forbidden',
            HttpStatus::NOT_FOUND                       => 'Not Found',
            HttpStatus::METHOD_NOT_ALLOWED              => 'Method Not Allowed',
            HttpStatus::NOT_ACCEPTABLE                  => 'Not Acceptable',
            HttpStatus::PROXY_AUTHENTICATION_REQUIRED   => 'Proxy Authentication Required',
            HttpStatus::REQUEST_TIMEOUT                 => 'Request Timeout',
            HttpStatus::CONFLICT                        => 'Conflict',
            HttpStatus::GONE                            => 'Gone',
            HttpStatus::LENGTH_REQUIRED                 => 'Length Required',
            HttpStatus::PRECONDITION_FAILED             => 'Precondition Failed',
            HttpStatus::REQUEST_ENTITY_TOO_LARGE        => 'Payload Too Large',
            HttpStatus::REQUEST_URI_TOO_LONG            => 'URI Too Long',
            HttpStatus::UNSUPPORTED_MEDIA_TYPE          => 'Unsupported Media Type',
            HttpStatus::REQUESTED_RANGE_NOT_SATISFIABLE => 'Range Not Satisfiable',
            HttpStatus::EXPECTATION_FAILED              => 'Expectation Failed',
            HttpStatus::I_AM_A_TEAPOT                   => "I'm a teapot",
            HttpStatus::ENHANCE_YOUR_CALM               => 'Enhance Your Calm',
            HttpStatus::MISDIRECTED_REQUEST             => 'Misdirected Request',
            HttpStatus::UNPROCESSABLE_ENTITY            => 'Unprocessable Entity',
            HttpStatus::LOCKED                          => 'Locked',
            HttpStatus::FAILED_DEPENDENCY               => 'Failed Dependency',
            HttpStatus::UNORDERED_COLLECTION            => 'Unordered Collection',
            HttpStatus::UPGRADE_REQUIRED                => 'Upgrade Required',
            HttpStatus::PRECONDITION_REQUIRED           => 'Precondition Required',
            HttpStatus::TOO_MANY_REQUESTS               => 'Too Many Requests',
            HttpStatus::REQUEST_HEADER_FIELDS_TOO_LARGE => 'Request Header Fields Too Large',
            HttpStatus::UNAVAILABLE_FOR_LEGAL_REASONS   => 'Unavailable For Legal Reasons',
            HttpStatus::INTERNAL_SERVER_ERROR           => 'Internal Server Error',
            HttpStatus::NOT_IMPLEMENTED                 => 'Not Implemented',
            HttpStatus::BAD_GATEWAY                     => 'Bad Gateway',
            HttpStatus::SERVICE_UNAVAILABLE             => 'Service Unavailable',
            HttpStatus::GATEWAY_TIMEOUT                 => 'Gateway Timeout',
            HttpStatus::VERSION_NOT_SUPPORTED           => 'HTTP Version Not Supported',
            HttpStatus::VARIANT_ALSO_NEGOTIATES         => 'Variant Also Negotiates',
            HttpStatus::INSUFFICIENT_STORAGE            => 'Insufficient Storage',
            HttpStatus::LOOP_DETECTED                   => 'Loop Detected',
            HttpStatus::NOT_EXTENDED                    => 'Not Extended',
            HttpStatus::NETWORK_AUTHENTICATION_REQUIRED => 'Network Authentication Required'
        };
    }
}
