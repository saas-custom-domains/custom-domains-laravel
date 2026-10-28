<?php

namespace App\Support;

class Hosts
{
    // "Jobs.Acme.com." and "jobs.acme.com:443" are the same host.
    public static function normalize(?string $host): string
    {
        $host = strtolower(trim((string) $host));

        return rtrim(preg_replace('/:\d+$/', '', $host), '.');
    }

    public static function isValid(string $host): bool
    {
        if (strlen($host) > 253) {
            return false;
        }

        $labels = explode('.', $host);

        return count($labels) >= 2 && collect($labels)->every(
            fn ($label) => preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/', $label) === 1
        );
    }

    // Hireboard's own hostnames (the dashboard), from APP_HOSTS.
    public static function isAppHost(string $host): bool
    {
        return in_array($host, config('domains.app_hosts'), true);
    }

    // Domains customers can't connect: ours, and anything under APP_DOMAIN.
    public static function isReserved(string $host): bool
    {
        $appDomain = static::normalize(config('domains.app_domain'));

        return static::isAppHost($host)
            || ($appDomain !== '' && ($host === $appDomain || str_ends_with($host, '.'.$appDomain)));
    }
}
