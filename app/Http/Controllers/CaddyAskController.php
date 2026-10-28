<?php

namespace App\Http\Controllers;

use App\Models\CustomDomain;
use App\Support\Hosts;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Caddy (FrankenPHP) calls this before issuing a certificate on
// demand (DOMAINS_PROVIDER=caddy). 200 = go ahead, else refuse.
class CaddyAskController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $host = Hosts::normalize($request->query('domain'));
        $active = CustomDomain::isActiveHost($host);

        return response()->noContent($active ? 200 : 404);
    }
}
