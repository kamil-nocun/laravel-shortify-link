<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs/CIDRs of proxies whose X-Forwarded-* headers are
    | trusted, so the visitor's real IP reaches click statistics. Use "*" only
    | for local testing, never in production: any client could fake its IP.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
