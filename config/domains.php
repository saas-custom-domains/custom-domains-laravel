<?php

return [

    // Hostnames that serve Hireboard itself (the dashboard), not a
    // company's job board. No ports. "localhost" covers local development.
    'app_hosts' => array_values(array_filter(array_map(
        fn ($host) => strtolower(trim($host)),
        explode(',', env('APP_HOSTS', 'localhost')),
    ))),

    // The domain your customers can't claim, e.g. "hireboard.app".
    'app_domain' => env('APP_DOMAIN'),

    // The upstream's auth token. SaaS Custom Domains sends it in
    // X-SaaS-Domains-Auth-Token with every request it forwards.
    'proxy_auth_token' => env('PROXY_AUTH_TOKEN'),

];
