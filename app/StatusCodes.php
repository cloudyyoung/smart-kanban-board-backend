<?php

namespace App;


class StatusCodes {

    // [Informational 1xx]
    const CONTINUE                        = 100;
    const SWITCHING_PROTOCOLS             = 101;
    const PROCESSING                      = 102;
    const EARLY_HINTS                     = 103;

    // [Successful 2xx]
    const OK                              = 200;
    const CREATED                         = 201;
    const ACCEPTED                        = 202;
    const NONAUTHORITATIVE_INFORMATION    = 203;
    const NO_CONTENT                      = 204;
    const RESET_CONTENT                   = 205;
    const PARTIAL_CONTENT                 = 206;
    const MULTI_STATUS                    = 207;
    const ALREADY_REPORTED                = 208;
    const IM_USED                         = 226;

    // [Redirection 3xx]
    const MULTIPLE_CHOICES                = 300;
    const MOVED_PERMANENTLY               = 301;
    const FOUND                           = 302;
    const SEE_OTHER                       = 303;
    const NOT_MODIFIED                    = 304;
    const USE_PROXY                       = 305;
    const UNUSED                          = 306;
    const TEMPORARY_REDIRECT              = 307;
    const PERMANENT_REDIRECT              = 308;

    // [Client Error 4xx]
    const errorCodesBeginAt               = 400;
    const BAD_REQUEST                     = 400;
    const UNAUTHORIZED                    = 401;
    const PAYMENT_REQUIRED                = 402;
    const FORBIDDEN                       = 403;
    const NOT_FOUND                       = 404;
    const METHOD_NOT_ALLOWED              = 405;
    const NOT_ACCEPTABLE                  = 406;
    const PROXY_AUTHENTICATION_REQUIRED   = 407;
    const REQUEST_TIMEOUT                 = 408;
    const CONFLICT                        = 409;
    const GONE                            = 410;
    const LENGTH_REQUIRED                 = 411;
    const PRECONDITION_FAILED             = 412;
    const REQUEST_ENTITY_TOO_LARGE        = 413;
    const REQUEST_URI_TOO_LONG            = 414;
    const UNSUPPORTED_MEDIA_TYPE          = 415;
    const REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    const EXPECTATION_FAILED              = 417;
    const IM_A_TEA_POT                    = 418;
    const MISDIRECTED_REQUEST             = 421;
    const UNPROCESSABLE_ENTITY            = 422;
    const LOCKED                          = 423;
    const FAILED_DEPENDENCY               = 424;
    const TOO_EARLY                       = 425;
    const UPGRADE_REQUIRED                = 426;
    const PRECONDITION_REQUIRED           = 428;
    const TOO_MANY_REQUESTS               = 429;
    const REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    const LOGIN_TIME_OUT                  = 440;
    const RETRY_WITH                      = 449;
    const UNAVAILABLE_FOR_LEGAL_REASONS   = 451;

    // [Server Error 5xx]
    const INTERNAL_SERVER_ERROR           = 500;
    const NOT_IMPLEMENTED                 = 501;
    const BAD_GATEWAY                     = 502;
    const SERVICE_UNAVAILABLE             = 503;
    const GATEWAY_TIMEOUT                 = 504;
    const VERSION_NOT_SUPPORTED           = 505;
    const VARIANT_ALSO_NEGOTIAS           = 506;
    const INSUFFICIENT_STORAGE            = 507;
    const LOOP_DETECTED                   = 508;
    const NOT_EXTENDED                    = 510;
    const NETWORK_AUTHENTICATION_REQUIRED = 511;
    const SERVICE_ERROR                   = 540;

    private static $messages = array(
        // [Informational 1xx]
        100 => '100 Continue',
        101 => '101 Switching Protocols',
        102 => '102 Processing',
        103 => '103 Early Hints',

        // [Successful 2xx]
        200 => '200 OK',
        201 => '201 Created',
        202 => '202 Accepted',
        203 => '203 Non-Authoritative Information',
        204 => '204 No Content',
        205 => '205 Reset Content',
        206 => '206 Partial Content',
        207 => '207 Multi-Status',
        208 => '208 Already Reported',
        226 => '226 IM Used',

        // [Redirection 3xx]
        300 => '300 Multiple Choices',
        301 => '301 Moved Permanently',
        302 => '302 Found',
        303 => '303 See Other',
        304 => '304 Not Modified',
        305 => '305 Use Proxy',
        306 => '306 (Unused)',
        307 => '307 Temporary Redirect',
        308 => '308 Permanent Redirect',

        // [Client Error 4xx]
        400 => '400 Bad Request',
        401 => '401 Unauthorized',
        402 => '402 Payment Required',
        403 => '403 Forbidden',
        404 => '404 Not Found',
        405 => '405 Method Not Allowed',
        406 => '406 Not Acceptable',
        407 => '407 Proxy Authentication Required',
        408 => '408 Request Timeout',
        409 => '409 Conflict',
        410 => '410 Gone',
        411 => '411 Length Required',
        412 => '412 Precondition Failed',
        413 => '413 Request Entity Too Large',
        414 => '414 Request-URI Too Long',
        415 => '415 Unsupported Media Type',
        416 => '416 Requested Range Not Satisfiable',
        417 => '417 Expectation Failed',
        418 => '418 I\'m a teapot',
        421 => '421 Misdirected Request',
        422 => '422 Unprocessable Entity',
        423 => '423 Locked',
        424 => '424 Failed Dependency',
        425 => '425 Too Early',
        426 => '426 Upgrade Required',
        428 => '428 Precondition Required',
        429 => '429 Too Many Requests',
        431 => '431 Request Header Fields Too Large',
        440 => "440 Login Time-Out",                   // UNOFFICIAL: Microsoft Internet Information Services 
        449 => "449 Retry With",                       // UNOFFICIAL: Microsoft Internet Information Services 
        451 => '451 Unavailable For Legal Reasons',

        // [Server Error 5xx]
        500 => '500 Internal Server Error',
        501 => '501 Not Implemented',
        502 => '502 Bad Gateway',
        503 => '503 Service Unavailable',
        504 => '504 Gateway Timeout',
        505 => '505 HTTP Version Not Supported',
        506 => '506 Variant Also Negotiates',
        507 => '507 Insufficient Storage',
        508 => '508 Loop Detected',
        510 => '510 Not Extended',
        511 => '511 Network Authentication Required',
        540 => '540 Service Error'
    );

    public static function httpHeaderFor($code){
        return 'HTTP/1.1 ' . self::$messages[$code];
    }

    public static function getMessageForCode($code){
        return self::$messages[$code];
    }

    public static function isError($code){
        return is_numeric($code) && $code >= self::BAD_REQUEST;
    }

    public static function canHaveBody($code){
        return
            // True if not in 100s
            ($code < self::CONTINUE || $code >= self::OK)
            && // and not 204 NO CONTENT
            $code != self::NO_CONTENT
            && // and not 304 NOT MODIFIED
            $code != self::NOT_MODIFIED;
    }
}