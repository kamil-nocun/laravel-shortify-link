<?php

namespace App\Support;

class MainHost
{
    /**
     * Lower-cased host of APP_URL, without the port.
     */
    public static function name(): string
    {
        return strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
    }

    /**
     * Host of APP_URL with its port, if any; what users see and type.
     */
    public static function withPort(): string
    {
        $port = parse_url(config('app.url'), PHP_URL_PORT);

        return parse_url(config('app.url'), PHP_URL_HOST).($port ? ':'.$port : '');
    }
}
