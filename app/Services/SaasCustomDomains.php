<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

// A small client for the SaaS Custom Domains API.
// Docs: https://docs.saascustomdomains.com/custom-domains
class SaasCustomDomains
{
    /** @return array{uuid: string, host: string, status: string} */
    public function createDomain(string $host, ?string $instructionsRecipient = null): array
    {
        return $this->client()->post($this->url(), array_filter([
            'host' => $host,
            // We email this address the exact DNS records to add.
            'instructions_recipient' => $instructionsRecipient,
        ]))->throw()->json();
    }

    /** @return array{dns_status: 'valid'|'pending'|'invalid'} */
    public function verifyDns(string $uuid): array
    {
        // send() without data: post() would send an empty JSON array, "[]".
        return $this->client()->send('POST', $this->url("{$uuid}/verify_dns_records"))->throw()->json();
    }

    public function deleteDomain(string $uuid): void
    {
        $response = $this->client()->delete($this->url($uuid));

        // Already gone is fine: the goal is that it doesn't exist.
        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    protected function client(): PendingRequest
    {
        return Http::withToken(config('services.saascustomdomains.token'))
            ->acceptJson()
            ->timeout(10);
    }

    protected function url(string $path = ''): string
    {
        $config = config('services.saascustomdomains');

        return rtrim($config['url'], '/')
            ."/api/v1/accounts/{$config['account']}"
            ."/upstreams/{$config['upstream']}/custom_domains"
            .($path === '' ? '' : "/{$path}");
    }
}
