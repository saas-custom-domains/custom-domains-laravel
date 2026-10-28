<?php

namespace App\Http\Middleware;

use App\Support\Hosts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// The dashboard lives on Hireboard's own hostnames, never on a
// customer's.
class AppHostOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = Hosts::normalize($request->getHost());
        abort_unless(Hosts::isAppHost($host), 404);

        return $next($request);
    }
}
