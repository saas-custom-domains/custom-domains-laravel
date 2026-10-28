<?php

namespace App\Services;

// Used when you run the proxy yourself (DOMAINS_PROVIDER=caddy).
// With SaaS Custom Domains, the API checks DNS for you.
class DnsCheck
{
    public function pointsAtUs(string $host): bool
    {
        // *.localhost always resolves to this machine.
        if (str_ends_with($host, '.localhost')) {
            return true;
        }

        $target = strtolower(config('domains.cname_target'));
        $ips = config('domains.server_ips');

        foreach ($this->records($host) as $record) {
            $cname = rtrim(strtolower($record['target'] ?? ''), '.');
            $ip = $record['ip'] ?? null;

            if ($cname === $target || in_array($ip, $ips, true)) {
                return true;
            }
        }

        return false;
    }

    protected function records(string $host): array
    {
        return @dns_get_record($host, DNS_CNAME | DNS_A) ?: [];
    }
}
