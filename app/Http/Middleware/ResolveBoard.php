<?php

namespace App\Http\Middleware;

use App\Models\Board;
use App\Support\Hosts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Finds the company whose job board this host serves. Runs on every
// board route; unknown and pending domains get a 404.
class ResolveBoard
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = Hosts::normalize($request->getHost());

        if (Hosts::isAppHost($host)) {
            // Boards are only reachable on their own domain. Our own
            // homepage is the dashboard.
            return $request->is('/') ? redirect()->route('dashboard') : abort(404);
        }

        $board = Board::forActiveHost($host);
        abort_if($board === null, 404, 'Unknown domain');

        $request->attributes->set('board', $board);

        return $next($request);
    }
}
