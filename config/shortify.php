<?php

return [

    /*
    | Host that users point their custom domains' CNAME at.
    */
    'cname_target' => env('SHORTIFY_CNAME_TARGET') ?: parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST),

    /*
    | Shared secret for the endpoint that tells the proxy which domains it may issue certificates for.
    */
    'domain_check_secret' => env('SHORTIFY_DOMAIN_CHECK_SECRET'),

];
