<?php

return [

    // Laravel's TrustProxies middleware reads this when bootstrap/app.php
    // doesn't list proxies itself. "*" trusts whoever connects, which is
    // right when only your load balancer (or SaaS Custom Domains) can
    // reach the app. Leave it empty if requests reach PHP directly.
    'proxies' => env('TRUSTED_PROXIES'),

];
