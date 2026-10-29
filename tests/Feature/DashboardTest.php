<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\CustomDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected const API = 'https://scd.test/api/v1/accounts/acc_test/upstreams/up_test/custom_domains';

    protected bool $seed = true;

    protected Board $acme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acme = Board::firstWhere('slug', 'acme');
    }

    public function test_adding_a_domain_registers_it_with_saas_custom_domains(): void
    {
        Http::fake([self::API => Http::response(['uuid' => 'dom_1', 'host' => 'jobs.acme.com', 'status' => 'pending_check'], 201)]);

        $this->post("/dashboard/boards/{$this->acme->id}/domains", ['host' => ' Jobs.Acme.com. ', 'dns_contact' => 'it@acme.com'])
            ->assertRedirect('/dashboard');

        $domain = CustomDomain::firstWhere('host', 'jobs.acme.com');
        $this->assertSame('dom_1', $domain->provider_uuid);
        $this->assertSame('Waiting for DNS', $domain->statusLabel());

        Http::assertSent(fn (Request $request) => $request->url() === self::API
            && $request->hasHeader('Authorization', 'Bearer test_api_token')
            && $request['host'] === 'jobs.acme.com'
            && $request['instructions_recipient'] === 'it@acme.com');
    }

    public function test_a_failed_api_call_does_not_keep_the_domain(): void
    {
        Http::fake([self::API => Http::response(['error' => 'Host has already been taken', 'error_code' => 'host_taken'], 400)]);

        $this->post("/dashboard/boards/{$this->acme->id}/domains", ['host' => 'jobs.acme.com'])
            ->assertSessionHasErrors(['host' => 'Host has already been taken'], errorBag: "board{$this->acme->id}");

        $this->assertDatabaseMissing('custom_domains', ['host' => 'jobs.acme.com']);
    }

    public function test_rejects_our_own_domains_invalid_hosts_and_duplicates(): void
    {
        Http::fake();
        $this->acme->customDomains()->create(['host' => 'jobs.acme.com']);
        $add = fn (string $host) => $this->post("/dashboard/boards/{$this->acme->id}/domains", ['host' => $host]);
        $bag = "board{$this->acme->id}";

        $add('admin.hireboard.app')->assertSessionHasErrors(['host' => "That's one of our domains."], errorBag: $bag);
        $add('not a domain')->assertSessionHasErrors(['host' => '"not a domain" isn\'t a valid domain.'], errorBag: $bag);
        $add('jobs.acme.com')->assertSessionHasErrors(['host' => 'jobs.acme.com is already connected to a job board.'], errorBag: $bag);
        Http::assertNothingSent();
    }

    public function test_check_dns_uses_the_api(): void
    {
        Http::fake([self::API.'/dom_1/verify_dns_records' => Http::response(['dns_status' => 'valid'])]);
        $domain = $this->acme->customDomains()->create(['host' => 'jobs.acme.com', 'provider_uuid' => 'dom_1']);

        $this->post("/dashboard/domains/{$domain->id}/check")->assertRedirect('/dashboard');

        $this->assertSame(CustomDomain::ACTIVE, $domain->fresh()->status);
    }

    public function test_removing_a_domain_that_is_already_gone_upstream_still_removes_it(): void
    {
        Http::fake([self::API.'/dom_1' => Http::response(['error' => 'Custom domain dom_1 not found.', 'error_code' => 'not_found'], 404)]);
        $domain = $this->acme->customDomains()->create(['host' => 'jobs.acme.com', 'provider_uuid' => 'dom_1']);

        $this->delete("/dashboard/domains/{$domain->id}")->assertRedirect('/dashboard');

        $this->assertModelMissing($domain);
    }

    public function test_a_failed_delete_keeps_the_row_for_a_retry(): void
    {
        Http::fake([self::API.'/dom_1' => Http::response('', 500)]);
        $domain = $this->acme->customDomains()->create(['host' => 'jobs.acme.com', 'provider_uuid' => 'dom_1']);

        try {
            $this->withoutExceptionHandling()->delete("/dashboard/domains/{$domain->id}");
            $this->fail('Expected the API error to bubble up.');
        } catch (RequestException $e) {
            $this->assertSame(500, $e->response->status());
        }

        $this->assertModelExists($domain);
    }
}
