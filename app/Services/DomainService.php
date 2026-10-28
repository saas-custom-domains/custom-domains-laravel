<?php

namespace App\Services;

use App\Models\Board;
use App\Models\CustomDomain;
use Throwable;

// Adds, checks and removes custom domains with either provider.
class DomainService
{
    public function __construct(
        protected SaasCustomDomains $api,
        protected DnsCheck $dns,
    ) {}

    public function add(Board $board, string $host, ?string $dnsContact = null): CustomDomain
    {
        // The unique index on host turns a duplicate into an exception here.
        $domain = $board->customDomains()->create(['host' => $host]);

        if (! $this->managed()) {
            return $domain;
        }

        try {
            $remote = $this->api->createDomain($host, $dnsContact);
        } catch (Throwable $e) {
            // Don't keep a row the proxy doesn't know about.
            $domain->delete();
            throw $e;
        }

        $domain->update(['provider_uuid' => $remote['uuid']]);

        return $domain;
    }

    public function check(CustomDomain $domain): bool
    {
        $valid = $this->managed() && $domain->provider_uuid
            ? $this->api->verifyDns($domain->provider_uuid)['dns_status'] === 'valid'
            : $this->dns->pointsAtUs($domain->host);

        $domain->update([
            'status' => $valid ? CustomDomain::ACTIVE : CustomDomain::PENDING,
            // With SaaS Custom Domains, a webhook says when HTTPS is ready.
            // Caddy issues the certificate on the first visit instead.
            ...($this->managed() ? [] : ['https_ready' => $valid]),
        ]);

        return $valid;
    }

    public function remove(CustomDomain $domain): void
    {
        // Proxy first, database second: if the API call fails, the row
        // is still here and the user can retry.
        if ($this->managed() && $domain->provider_uuid) {
            $this->api->deleteDomain($domain->provider_uuid);
        }

        $domain->delete();
    }

    protected function managed(): bool
    {
        return config('domains.provider') === 'saascustomdomains';
    }
}
