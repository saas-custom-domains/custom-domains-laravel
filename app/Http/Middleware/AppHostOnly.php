<?php

namespace App\Http\Middleware;

use App\Support\Hosts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// The dashboard lives on Hireboard's own hostnames, never on a customer's.
class AppHostOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Hosts::isAppHost(Hosts::normalize($request->getHost())), 404);

        return $next($request);
    }
}
