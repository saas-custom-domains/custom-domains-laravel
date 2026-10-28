<?php

namespace Tests\Unit;

use App\Support\Hosts;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HostsTest extends TestCase
{
    public function test_normalize_lowercases_and_strips_port_and_trailing_dot(): void
    {
        $this->assertSame('jobs.acme.com', Hosts::normalize(' Jobs.Acme.com.:443 '));
        $this->assertSame('', Hosts::normalize(null));
    }

    #[DataProvider('validHosts')]
    public function test_accepts_valid_hosts(string $host): void
    {
        $this->assertTrue(Hosts::isValid($host));
    }

    public static function validHosts(): array
    {
        return [['jobs.acme.com'], ['acme.co.uk'], ['xn--bcher-kva.example']];
    }

    #[DataProvider('invalidHosts')]
    public function test_rejects_invalid_hosts(string $host): void
    {
        $this->assertFalse(Hosts::isValid($host));
    }

    public static function invalidHosts(): array
    {
        return [['acme'], ['-acme.com'], ['acme..com'], ['ac me.com'], ['https://a.com']];
    }

    public function test_reserved_hosts_are_ours_and_our_subdomains(): void
    {
        $this->assertTrue(Hosts::isReserved('hireboard.app'));
        $this->assertTrue(Hosts::isReserved('admin.hireboard.app'));
        $this->assertTrue(Hosts::isReserved('localhost'));
        $this->assertFalse(Hosts::isReserved('nothireboard.app'));
    }
}
