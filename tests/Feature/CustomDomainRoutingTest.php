<?php

namespace Tests\Feature;

use App\Models\Board;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomDomainRoutingTest extends TestCase
{
    use RefreshDatabase;

    // SaaS Custom Domains sends requests to the upstream's own host. The
    // customer's domain is in X-Served-For.
    protected const APP = 'http://app.hireboard.app';

    protected bool $seed = true;

    protected function viaProxy(string $customerDomain, string $token = 'test_proxy_token'): array
    {
        return [
            'X-Served-For' => $customerDomain,
            'X-SaaS-Domains-Auth-Token' => $token,
            'X-Forwarded-Host' => 'app.hireboard.app',
            'X-Forwarded-Proto' => 'https',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['domains.app_hosts' => ['localhost', 'app', 'app.hireboard.app']]);

        Board::firstWhere('slug', 'acme')->customDomains()->create(['host' => 'jobs.acme.com', 'status' => 'active']);
        Board::firstWhere('slug', 'globex')->customDomains()->create(['host' => 'jobs.globex.com']);
    }

    public function test_the_app_host_serves_the_dashboard(): void
    {
        $this->get('http://localhost/')->assertRedirect('http://localhost/dashboard');
        $this->get('http://localhost/dashboard')->assertOk()->assertSee('Hireboard dashboard');
    }

    public function test_board_routes_are_not_reachable_on_the_app_host(): void
    {
        $job = Board::firstWhere('slug', 'acme')->jobPostings()->first();

        $this->get("http://localhost/jobs/{$job->id}")->assertNotFound();
    }

    public function test_an_active_custom_domain_serves_its_board(): void
    {
        $this->get('http://jobs.acme.com/')->assertOk()->assertSee('Acme Robotics');
    }

    public function test_the_dashboard_is_not_reachable_on_a_custom_domain(): void
    {
        $this->get('http://jobs.acme.com/dashboard')->assertNotFound();
    }

    public function test_pending_and_unknown_domains_get_a_404(): void
    {
        $this->get('http://jobs.globex.com/')->assertNotFound();
        $this->get('http://evil.example/')->assertNotFound();
    }

    public function test_reads_x_served_for_when_the_proxy_token_is_valid(): void
    {
        $this->get(self::APP.'/', $this->viaProxy('jobs.acme.com'))->assertOk()->assertSee('Acme Robotics');
        $this->get(self::APP.'/', $this->viaProxy('jobs.globex.com'))->assertNotFound();
    }

    public function test_rejects_x_served_for_without_the_right_token(): void
    {
        $spoofed = ['X-Served-For' => 'jobs.acme.com'];

        $this->get(self::APP.'/', $spoofed)->assertForbidden();
        $this->get(self::APP.'/', $spoofed + ['X-SaaS-Domains-Auth-Token' => 'guess'])->assertForbidden();
    }

    public function test_rejects_x_served_for_when_no_token_is_configured(): void
    {
        config(['domains.proxy_auth_token' => null]);

        $this->get(self::APP.'/', $this->viaProxy('jobs.acme.com', ''))->assertForbidden();
    }

    public function test_links_on_a_custom_domain_point_at_the_custom_domain(): void
    {
        $this->get(self::APP.'/', $this->viaProxy('jobs.acme.com'))
            ->assertSee('href="http://jobs.acme.com/jobs/', false)
            ->assertDontSee('app.hireboard.app');
    }

    // The redirect after a form POST must stay on the customer's domain.
    // Without UseServedForHost it goes to http://app.hireboard.app/jobs/1,
    // where boards don't exist.
    public function test_applying_through_the_proxy_redirects_back_to_the_custom_domain(): void
    {
        $job = Board::firstWhere('slug', 'acme')->jobPostings()->first();

        $this->post(self::APP."/jobs/{$job->id}/apply", ['name' => 'Ada', 'email' => 'ada@example.com'], $this->viaProxy('jobs.acme.com'))
            ->assertRedirect("http://jobs.acme.com/jobs/{$job->id}")
            ->assertSessionHas('status', 'Thanks, Ada. Acme Robotics has your application.');

        $this->assertSame(1, $job->applications()->count());
    }

    public function test_with_trusted_proxies_links_are_https(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->get(self::APP.'/', $this->viaProxy('jobs.acme.com'))->assertSee('href="https://jobs.acme.com/jobs/', false);
    }

    public function test_job_ids_from_another_board_are_not_found(): void
    {
        $globexJob = Board::firstWhere('slug', 'globex')->jobPostings()->first();

        $this->get("http://jobs.acme.com/jobs/{$globexJob->id}")->assertNotFound();
    }

    public function test_health_check_works_on_any_host(): void
    {
        $this->get('http://10.0.0.12/up')->assertOk();
    }
}
