<?php

namespace Tests\Unit;

use App\Services\DnsCheck;
use Tests\TestCase;

class DnsCheckTest extends TestCase
{
    public function test_localhost_subdomains_always_point_at_us(): void
    {
        $this->assertTrue((new DnsCheck)->pointsAtUs('acme.localhost'));
    }

    public function test_matches_the_cname_target_or_a_server_ip(): void
    {
        config(['domains.cname_target' => 'in.hireboard.app', 'domains.server_ips' => ['203.0.113.7']]);

        $dns = fn (array $records) => new class($records) extends DnsCheck
        {
            public function __construct(private array $fake) {}

            protected function records(string $host): array
            {
                return $this->fake;
            }
        };

        $this->assertTrue($dns([['type' => 'CNAME', 'target' => 'In.Hireboard.app.']])->pointsAtUs('jobs.acme.com'));
        $this->assertTrue($dns([['type' => 'A', 'ip' => '203.0.113.7']])->pointsAtUs('acme.com'));
        $this->assertFalse($dns([['type' => 'A', 'ip' => '198.51.100.1']])->pointsAtUs('acme.com'));
    }
}
