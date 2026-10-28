<?php

namespace App\Http\Middleware;

use App\Support\Hosts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Behind SaaS Custom Domains, Host is your app's own hostname and
// the customer's domain arrives in X-Served-For. Once the upstream's
// auth token proves where the request came from, make the customer's
// domain the request's host. From here on, $request->getHost(),
// url(), route() and redirects all use it.
class UseServedForHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasHeader('X-Served-For')) {
            $host = static::verifiedHost($request);
            $request->headers->set('Host', $host);
            // A trusted X-Forwarded-Host would win over Host. Here it
            // holds your own hostname, so drop it.
            $request->headers->remove('X-Forwarded-Host');
            $request->headers->remove('X-Forwarded-Port');
        }

        return $next($request);
    }

    // Anyone can send X-Served-For. Only the proxy knows the token.
    public static function verifiedHost(Request $request): string
    {
        $expected = (string) config('domains.proxy_auth_token');
        $token = (string) $request->header('X-SaaS-Domains-Auth-Token');

        abort_if(
            $expected === '' || ! hash_equals($expected, $token),
            403,
        );

        return Hosts::normalize($request->header('X-Served-For'));
    }
}
