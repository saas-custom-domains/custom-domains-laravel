<?php

namespace App\Services;

use App\Models\Board;
use App\Models\CustomDomain;
use Throwable;

// Adds, checks and removes custom domains at SaaS Custom Domains and
// keeps the local table in step.
class DomainService
{
    public function __construct(protected SaasCustomDomains $api) {}

    public function add(
        Board $board,
        string $host,
        ?string $dnsContact = null,
    ): CustomDomain {
        // The unique index on host turns a duplicate into an
        // exception here, before the API is called.
        $domain = $board->customDomains()->create(['host' => $host]);

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
        $result = $this->api->verifyDns($domain->provider_uuid);
        $valid = $result['dns_status'] === 'valid';

        $domain->update([
            'status' => $valid
                ? CustomDomain::ACTIVE
                : CustomDomain::PENDING,
        ]);

        return $valid;
    }

    public function remove(CustomDomain $domain): void
    {
        // Proxy first, database second: if the API call fails, the
        // row is still here and the user can retry.
        if ($domain->provider_uuid) {
            $this->api->deleteDomain($domain->provider_uuid);
        }

        $domain->delete();
    }
}
