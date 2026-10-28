<?php

namespace App\Http\Controllers;

use App\Models\CustomDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Events: https://docs.saascustomdomains.com/webhooks
class SaasCustomDomainsWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $expected = (string) config('services.saascustomdomains.webhook_token');
        $token = (string) $request->query('token');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            return response('Unauthorized', 401);
        }

        $uuid = $request->input('domain.uuid');
        $domain = $uuid ? CustomDomain::firstWhere('provider_uuid', $uuid) : null;

        // Not ours (or already removed): acknowledge so it isn't retried.
        if ($domain === null) {
            return response()->noContent();
        }

        match ($request->input('event')) {
            'domain_dns_proxy_record_valid' => $domain->update(['status' => CustomDomain::ACTIVE]),
            'domain_dns_proxy_record_invalid' => $domain->update(['status' => CustomDomain::PENDING]),
            'tls_certificate_issued' => $domain->update(['https_ready' => true]),
            default => null,
        };

        return response()->noContent();
    }
}
